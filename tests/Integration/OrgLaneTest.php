<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace ComingSoon\Tests\Integration;

use Comingsoon_Content as Content;
use Comingsoon_Plugin_Home;
use Comingsoon_Skins;
use ComingSoon\Tests\Support\IntegrationTestCase;
use Tiger_Model_Config;
use Tiger_Uuid;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * TIGER-511 — ComingSoon stays in its org lane. Against core's real `config` + `media` tables:
 *
 *   - the admin's writes land as ORG-scoped rows for the admin's current org (never a GLOBAL row), and the
 *     admin screen reads that same tier back; a caller with no org writes nothing;
 *   - the public page reads the SITE org (never the signed-in visitor's own org), falls back to the legacy
 *     GLOBAL rows, and never sees another org's rows — an org row cleared to '' still beats a legacy global;
 *   - the auto stand-down is computed at read time: a guest GET with a published home page passes through
 *     to the real site and writes NOTHING (it used to write a global `comingsoon.active=0`);
 *   - a background media id resolves only to the lane org's (or shared '') media — stored or preview `bg`.
 */
final class OrgLaneTest extends IntegrationTestCase
{
    private const ORG_A = '01999999-0000-7000-8000-0000000000a1';
    private const ORG_B = '01999999-0000-7000-8000-0000000000b2';

    private function cfg(string $scope, string $scopeId, string $key, string $value): void
    {
        (new Tiger_Model_Config())->set($scope, $scopeId, $key, $value);
        Content::resetCache();
    }

    private function media(string $org, string $kind = 'image'): string
    {
        $id = Tiger_Uuid::v7();
        $this->db->insert('media', [
            'media_id' => $id, 'org_id' => $org, 'disk' => 'local', 'storage_key' => 'images/' . $id . '.jpg',
            'visibility' => 'public', 'kind' => $kind, 'mime_type' => 'image/jpeg', 'status' => 'active',
            'deleted' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $id;
    }

    /** Run the front-controller plugin for a GET of $path; true when it walled the request. */
    private function walls(string $path = '/'): bool
    {
        $req = new Zend_Controller_Request_HttpTestCase();
        $req->setMethod('GET')->setRequestUri($path);
        $req->setModuleName('default')->setControllerName('index')->setActionName('index');
        $plugin = new Comingsoon_Plugin_Home();
        $plugin->setResponse(new Zend_Controller_Response_HttpTestCase());
        $plugin->preDispatch($req);
        return $req->getModuleName() === 'comingsoon';
    }

    // ----- admin writes: org-scoped, read back from the same tier ------------------------------------

    public function testAdminWritesOrgScopedRowsAndReadsThemBack(): void
    {
        $this->login(self::ORG_A);
        $org = Content::adminOrgId();
        $this->assertSame(self::ORG_A, $org);

        $this->assertTrue(Content::setSkin('midnight', $org));
        $this->assertTrue(Content::setHeading('Org A launches soon', $org));
        $this->assertTrue(Content::setTagline('A tagline', $org));
        $this->assertTrue(Content::setActive(false, $org));

        $rows = $this->configRows();
        foreach ($rows as $r) {
            $this->assertStringStartsWith('org|' . self::ORG_A . '|', $r, 'every write is an org row for the admin\'s org');
        }
        $this->assertContains('org|' . self::ORG_A . '|comingsoon.heading=Org A launches soon', $rows);

        $this->assertSame('midnight', Content::skin($org));
        $this->assertSame('Org A launches soon', Content::heading($org));
        $this->assertSame('A tagline', Content::tagline($org));
        $this->assertFalse(Content::isActive($org));
    }

    public function testACallerWithNoOrgWritesNothing(): void
    {
        $this->assertSame('', Content::adminOrgId());
        $this->assertFalse(Content::setHeading('Nowhere', ''));
        $this->assertFalse(Content::setActive(false, ''));
        $this->assertSame([], $this->configRows(), 'no org lane = no row, and never a global row');
    }

    // ----- isolation + legacy fallback ---------------------------------------------------------------

    public function testOneOrgsSettingsNeverBleedIntoAnother(): void
    {
        $this->cfg('org', self::ORG_A, 'comingsoon.heading', 'Org A only');
        $this->cfg('org', self::ORG_A, 'comingsoon.skin', 'midnight');
        $this->cfg('org', self::ORG_A, 'comingsoon.active', '0');

        // Org B's site and org B's admin both see defaults.
        $this->siteOrg(self::ORG_B);
        $this->assertSame(Content::DEFAULT_HEADING, Content::heading());
        $this->assertSame(Comingsoon_Skins::DEFAULT_SKIN, Content::skin());
        $this->assertTrue(Content::isActive());
        $this->assertSame(Content::DEFAULT_HEADING, Content::heading(self::ORG_B));

        // Org A's own site sees its rows.
        $this->siteOrg(self::ORG_A);
        $this->assertSame('Org A only', Content::heading());
        $this->assertFalse(Content::isActive());
    }

    public function testLegacyGlobalRowsAreTheFallback(): void
    {
        $this->cfg('global', '', 'comingsoon.heading', 'Legacy heading');
        $this->cfg('global', '', 'comingsoon.active', '0');
        $this->siteOrg(self::ORG_A);

        $this->assertSame('Legacy heading', Content::heading(), 'a pre-1.3 global row still serves');
        $this->assertFalse(Content::isActive());

        // The org's own row wins — even an explicit '' (cleared back to the default) beats the legacy value.
        $this->cfg('org', self::ORG_A, 'comingsoon.heading', '');
        $this->cfg('org', self::ORG_A, 'comingsoon.active', '1');
        $this->assertSame(Content::DEFAULT_HEADING, Content::heading());
        $this->assertTrue(Content::isActive());
    }

    public function testThePublicLaneIsTheSiteOrgNotTheVisitorsOrg(): void
    {
        $this->cfg('org', self::ORG_B, 'comingsoon.heading', 'Visitor org heading');
        $this->cfg('org', self::ORG_A, 'comingsoon.heading', 'Site org heading');
        $this->siteOrg(self::ORG_A);
        $this->login(self::ORG_B);   // a signed-in member of another org views this site

        $this->assertSame('Site org heading', Content::heading());
    }

    // ----- the guest-write case: stand-down computed at read time --------------------------------------

    public function testAGuestGetWithAPublishedHomePageStandsDownWithoutWriting(): void
    {
        $this->siteOrg(self::ORG_A);
        $this->cfg('global', '', 'tiger.site.home_page', '/welcome');   // where CMS Settings writes it
        $before = $this->configRows();

        $this->assertFalse($this->walls('/'), 'a published site is not walled');
        $this->assertFalse($this->walls('/about'));
        $this->assertSame($before, $this->configRows(), 'a guest request wrote NO config row');
        $this->assertTrue(Content::isActive(), 'the flag itself is untouched');
        $this->assertFalse(Content::showing(), 'the effective state (admin badge) reads as stood down');
    }

    public function testAGuestIsWalledUntilPublishAndASignedInUserIsNot(): void
    {
        $this->siteOrg(self::ORG_A);
        $before = $this->configRows();
        $this->assertTrue($this->walls('/'));
        $this->assertTrue($this->walls('/pricing'));
        $this->assertFalse($this->walls('/login'), 'auth paths pass');
        $this->assertSame($before, $this->configRows());

        $this->login(self::ORG_A);
        $this->assertFalse($this->walls('/'), 'any signed-in user bypasses');
    }

    public function testAnotherOrgsGoLiveDoesNotDropThisSitesWall(): void
    {
        $this->cfg('org', self::ORG_B, 'comingsoon.active', '0');
        $this->cfg('org', self::ORG_B, 'tiger.site.home_page', '/b-home');
        $this->siteOrg(self::ORG_A);

        $this->assertTrue($this->walls('/'), 'org B going live must not take org A\'s site live');
    }

    // ----- media: lane org or shared only ----------------------------------------------------------------

    public function testMediaResolvesOnlyForTheLaneOrgOrShared(): void
    {
        $mine   = $this->media(self::ORG_A);
        $shared = $this->media('');
        $theirs = $this->media(self::ORG_B);
        $this->siteOrg(self::ORG_A);

        $this->assertNotNull(Content::resolveMedia($mine));
        $this->assertSame('image', Content::resolveMedia($mine)['kind']);
        $this->assertNotNull(Content::resolveMedia($shared));
        $this->assertNull(Content::resolveMedia($theirs), 'another org\'s media id never resolves on the public page');

        // The admin preview's ?bg resolves in the admin's lane.
        $this->assertNull(Content::resolveMedia($theirs, self::ORG_A));
        $this->assertNotNull(Content::resolveMedia($theirs, self::ORG_B));
    }

    public function testAStoredForeignBackgroundIsNotServed(): void
    {
        $theirs = $this->media(self::ORG_B);
        $this->cfg('org', self::ORG_A, 'comingsoon.background_media', $theirs);
        $this->siteOrg(self::ORG_A);

        $this->assertSame($theirs, Content::backgroundId());
        $this->assertNull(Content::background(), 'a foreign media id stored in settings mints no URL');

        $mine = $this->media(self::ORG_A);
        $this->cfg('org', self::ORG_A, 'comingsoon.background_media', $mine);
        $this->assertNotNull(Content::background());
    }
}
