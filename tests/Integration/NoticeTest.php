<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace ComingSoon\Tests\Integration;

use Comingsoon_Content as Content;
use Comingsoon_Plugin_Notice as Notice;
use ComingSoon\Tests\Support\IntegrationTestCase;
use Tiger_Model_Config;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Layout;

/**
 * TIGER-537 — the signed-in "visitors see your Coming Soon page" bar.
 *
 * A signed-in user bypasses the wall, so without the bar the owner never learns visitors see something
 * else. These pin WHEN it shows (signed in + the holding page actually up + a public HTML page) and when
 * it must not (a guest, a published site, the page switched off, admin/auth layouts, JSON, XHR, redirects),
 * plus the injection itself (after <body>, stylesheet before </head>, once, never into a non-document).
 */
final class NoticeTest extends IntegrationTestCase
{
    private const ORG = '01999999-0000-7000-8000-0000000000c3';
    private const PAGE = '<!doctype html><html><head><title>x</title></head><body class="p"><main>site</main></body></html>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->siteOrg(self::ORG);
        $layout = Zend_Layout::getMvcInstance() ?: Zend_Layout::startMvc();
        $layout->enableLayout();
        $layout->setLayout('layout');
    }

    private function cfg(string $key, string $value): void
    {
        (new Tiger_Model_Config())->set('org', self::ORG, $key, $value);
        Content::resetCache();
    }

    /** Run the plugin over a finished response; return the body it leaves. */
    private function render(string $body = self::PAGE, array $opts = []): string
    {
        $req = new Zend_Controller_Request_HttpTestCase();
        $req->setMethod('GET')->setRequestUri($opts['path'] ?? '/about');
        $req->setModuleName($opts['module'] ?? 'default')->setControllerName('page')->setActionName('view');
        if (!empty($opts['xhr'])) { $req->setHeader('X-Requested-With', 'XMLHttpRequest'); }

        $res = new Zend_Controller_Response_HttpTestCase();
        $res->setBody($body);
        if (isset($opts['type']))     { $res->setHeader('Content-Type', $opts['type']); }
        if (!empty($opts['redirect'])) { $res->setRedirect('/elsewhere'); }

        $plugin = new Notice();
        $plugin->setRequest($req);
        $plugin->setResponse($res);
        $plugin->dispatchLoopShutdown();
        return (string) $res->getBody();
    }

    private function hasBar(string $html): bool
    {
        return strpos($html, 'data-cs-notice') !== false;
    }

    // ----- when it shows ---------------------------------------------------------------------------

    public function testASignedInUserSeesTheBarWhileTheHoldingPageIsUp(): void
    {
        $this->login(self::ORG);
        $html = $this->render();
        $this->assertTrue($this->hasBar($html));
        $this->assertMatchesRegularExpression('#<body class="p"><div class="cs-notice"#', $html, 'right after <body>');
        $this->assertMatchesRegularExpression('#notice\.css[^"]*"></head>#', $html, 'stylesheet before </head>');
    }

    public function testAGuestNeverSeesIt(): void
    {
        $this->assertFalse($this->hasBar($this->render()));
    }

    public function testItIsGoneOnceTheSiteIsPublishedOrSwitchedOff(): void
    {
        $this->login(self::ORG);
        $this->cfg('tiger.site.home_page', 'home');
        $this->assertFalse($this->hasBar($this->render()), 'a published site has no holding page to warn about');

        $this->cfg('tiger.site.home_page', '');
        $this->cfg('comingsoon.active', '0');
        $this->assertFalse($this->hasBar($this->render()), 'switched off = visitors already see the site');
    }

    public function testNotOnAdminOrAuthScreens(): void
    {
        $this->login(self::ORG);
        Zend_Layout::getMvcInstance()->setLayout('admin');
        $this->assertFalse($this->hasBar($this->render()));
        Zend_Layout::getMvcInstance()->setLayout('auth');
        $this->assertFalse($this->hasBar($this->render()));
    }

    public function testNotOnMachineResponsesOrTheModulesOwnScreens(): void
    {
        $this->login(self::ORG);
        $this->assertFalse($this->hasBar($this->render(self::PAGE, ['type' => 'application/json'])), 'JSON');
        $this->assertFalse($this->hasBar($this->render(self::PAGE, ['xhr' => true])), 'XHR');
        $this->assertFalse($this->hasBar($this->render(self::PAGE, ['redirect' => true])), 'redirect');
        $this->assertFalse($this->hasBar($this->render(self::PAGE, ['module' => 'comingsoon'])), 'its own screens');
        Zend_Layout::getMvcInstance()->disableLayout();
        $this->assertFalse($this->hasBar($this->render()), 'a full-document view with no layout');
    }

    public function testReadingTheStateWritesNothing(): void
    {
        $this->login(self::ORG);
        $before = $this->configRows();
        $this->render();
        $this->assertSame($before, $this->configRows());
    }

    // ----- the injection itself --------------------------------------------------------------------

    public function testInjectIsIdempotentAndLeavesNonDocumentsAlone(): void
    {
        $bar  = Notice::markup(false);
        $once = Notice::inject(self::PAGE, $bar, '<link rel="stylesheet" href="/x.css">');
        $this->assertSame($once, Notice::inject($once, $bar, '<link rel="stylesheet" href="/x.css">'), 'never twice');
        $this->assertSame(1, substr_count($once, 'data-cs-notice'));
        $this->assertSame('{"ok":true}', Notice::inject('{"ok":true}', $bar), 'no <body>, no change');
        $this->assertSame('', Notice::inject('', $bar));
    }

    public function testLinksOnlyForARoleThatMayManage(): void
    {
        $this->assertStringContainsString('/comingsoon/admin/preview', Notice::markup(true));
        $this->assertStringContainsString('href="/comingsoon/admin"', Notice::markup(true));
        $this->assertStringNotContainsString('/comingsoon/admin', Notice::markup(false));
    }
}
