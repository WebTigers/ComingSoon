<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace ComingSoon\Tests\Integration;

use Comingsoon_Content as Content;
use Comingsoon_Form_Active;
use Comingsoon_Form_Settings;
use Comingsoon_Service_Settings as Settings;
use ComingSoon\Tests\Support\IntegrationTestCase;
use Tiger_Acl_Acl;
use Zend_Registry;
use Zend_Session;

/**
 * TIGER-538 — the Coming Soon admin's writes go through a Tiger_Form, so they carry a CSRF token.
 *
 * They used to land on a controller action that checked only the X-Requested-With header. Now
 * `Comingsoon_Service_Settings` validates `Comingsoon_Form_Settings` / `Comingsoon_Form_Active`:
 *
 *   - a cookie-session call with no token, or a forged one, is refused and writes NOTHING;
 *   - with the token the screen renders, Save and the toggle land as ORG-scoped rows for the admin's org,
 *     and ONE rendered token serves both (shared salt) — the screen renders only one;
 *   - a skin the module doesn't ship is a per-field error, not a silent write;
 *   - a caller with no org writes nothing; a role without the admin grant is refused.
 */
final class SettingsServiceTest extends IntegrationTestCase
{
    private const ORG = '01999999-0000-7000-8000-0000000000d4';

    private static bool $aclReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        // The service opens its own transaction; PDO can't nest one inside the harness's, so this test
        // runs without it and removes its own rows in tearDown.
        try { $this->db->rollBack(); } catch (\Throwable $e) { /* none open */ }
        self::ensureAcl();
        Zend_Session::$_unitTestEnabled = true;
        Zend_Registry::set('tiger.auth.stateless', false);   // a browser session: the token is enforced
        $_SESSION = [];
        $this->siteOrg(self::ORG);
        $this->login(self::ORG, 'user-admin', 'admin');
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->delete('config', ["config_key LIKE 'comingsoon.%'"]);
        }
        $_SESSION = [];
        Zend_Registry::set('tiger.auth.stateless', true);
        parent::tearDown();
    }

    /** Build the ACL from this module's acl.ini (+ core's), the way an install does. */
    private static function ensureAcl(): void
    {
        if (self::$aclReady) { return; }
        $root    = dirname(__DIR__, 2);
        $scratch = sys_get_temp_dir() . '/comingsoon-acl-app';
        @mkdir($scratch . '/modules', 0777, true);
        $link = $scratch . '/modules/comingsoon';
        if (!is_link($link) && !is_dir($link)) { @symlink($root, $link); }
        if (!defined('APPLICATION_PATH')) { define('APPLICATION_PATH', $scratch); }
        Zend_Registry::set('Zend_Acl', new Tiger_Acl_Acl());
        self::$aclReady = true;
    }

    /** The token exactly as the admin screen renders it (arming the session the way render() does). */
    private function token(): string
    {
        $el = (new Comingsoon_Form_Settings())->getElement('_csrf');
        $el->initCsrfToken();
        return (string) $el->getHash();
    }

    private function call(string $method, array $params): object
    {
        return (new Settings(['action' => $method] + $params))->getResponse();
    }

    private function messages(object $res): string
    {
        return json_encode(array_map(static fn ($m) => $m->message, $res->messages ?? []));
    }

    public function testATokenlessOrForgedWriteIsRefusedAndWritesNothing(): void
    {
        $before = $this->configRows();
        foreach ([[], ['_csrf' => 'not-a-real-hash']] as $tok) {
            $save = $this->call('save', ['heading' => 'Hacked', 'skin' => 'midnight'] + $tok);
            $this->assertSame(0, $save->result, 'Save without a valid token must be refused');
            $this->assertStringContainsString('core.api.error.csrf', $this->messages($save));

            $off = $this->call('setActive', ['on' => '0'] + $tok);
            $this->assertSame(0, $off->result, 'turning the page off without a valid token must be refused');
        }
        $this->assertSame($before, $this->configRows(), 'nothing was written');
        $this->assertTrue(Content::isActive(self::ORG), 'the page is still on');
    }

    public function testWithTheScreensTokenBothWritesLandInTheAdminsOrgLane(): void
    {
        $tok = $this->token();   // ONE rendered token serves Save AND the toggle

        $save = $this->call('save', ['_csrf' => $tok, 'skin' => 'midnight', 'heading' => 'Soon', 'tagline' => 'Hang on', 'background' => '']);
        $this->assertSame(1, $save->result, $this->messages($save));
        Content::resetCache();
        $this->assertSame('midnight', Content::skin(self::ORG));
        $this->assertSame('Soon', Content::heading(self::ORG));

        $off = $this->call('setActive', ['_csrf' => $tok, 'on' => '0']);
        $this->assertSame(1, $off->result, $this->messages($off));
        Content::resetCache();
        $this->assertFalse(Content::isActive(self::ORG));

        foreach ($this->configRows() as $row) {
            if (strpos($row, 'comingsoon.') === false) { continue; }
            $this->assertStringStartsWith('org|' . self::ORG . '|', $row, 'every write is ORG-scoped');
        }
    }

    public function testAnUnknownSkinIsAFieldErrorNotAWrite(): void
    {
        $res = $this->call('save', ['_csrf' => $this->token(), 'skin' => 'not-a-skin']);
        $this->assertSame(0, $res->result);
        $this->assertArrayHasKey('skin', (array) $res->form);
        $this->assertSame([], array_values(array_filter($this->configRows(), static fn ($r) => strpos($r, 'comingsoon.') !== false)));
    }

    public function testNoOrgWritesNothingAndANonAdminIsRefused(): void
    {
        $this->login('', 'user-x', 'admin');
        $res = $this->call('setActive', ['_csrf' => $this->token(), 'on' => '0']);
        $this->assertSame(0, $res->result);
        $this->assertStringContainsString('comingsoon.error.no_org', $this->messages($res));

        $this->login(self::ORG, 'user-y', 'user');
        $res = $this->call('setActive', ['_csrf' => $this->token(), 'on' => '0']);
        $this->assertSame(0, $res->result);
        $this->assertStringContainsString('core.api.error.not_allowed', $this->messages($res));
        $this->assertSame([], array_values(array_filter($this->configRows(), static fn ($r) => strpos($r, 'comingsoon.') !== false)));
    }

    public function testEveryOperationDeclaresItsInput(): void
    {
        $this->assertSame([], (new \Tiger_OpenApi_Generator())->untyped([Settings::class]));
    }

    public function testBothFormsShareOneCsrfSalt(): void
    {
        $salt = static function (object $f): string {
            $m = new \ReflectionMethod($f, 'csrfSalt');
            return (string) $m->invoke($f);
        };
        $this->assertSame($salt(new Comingsoon_Form_Settings()), $salt(new Comingsoon_Form_Active()));
    }
}
