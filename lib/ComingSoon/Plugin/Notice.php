<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_Plugin_Notice — tells a signed-in user that visitors see the holding page, not this site.
 *
 * The wall bypasses everyone who is signed in (see Comingsoon_Plugin_Home), so the owner browses the
 * real site and never sees what a visitor gets — and nothing on the page says so. On the tigerdemo site
 * that read as "the Coming Soon page doesn't work" (TIGER-537). While the holding page is actually up
 * (Comingsoon_Content::showing() — switched on AND no home page published), every PUBLIC page rendered
 * for a signed-in user gets a slim bar at the top of <body>:
 *
 *   "Visitors see your Coming Soon page. You're seeing the real site because you're signed in."
 *   + "Preview what visitors see" and "Coming Soon settings" — only for a role that may open the admin.
 *
 * It is injected into the finished response at dispatchLoopShutdown, so it works with ANY public theme
 * without the theme knowing this module exists. It is markup plus one stylesheet <link> — no script, no
 * <style> block — and it is never shown to a guest, on an admin/auth screen, on an AJAX/JSON response, or
 * once the site has gone live. Reading the state never writes anything.
 *
 * @api
 */
class Comingsoon_Plugin_Notice extends Zend_Controller_Plugin_Abstract
{
    /** Layouts that are the back office or the login flow, not the public site. */
    private const SKIP_LAYOUTS = ['admin', 'auth'];

    /** The module's own screens and the machine surfaces never get the bar. */
    private const SKIP_MODULES = ['comingsoon', 'api', 'mcp'];

    /** The stylesheet, as published by the module's `provides.assets`. */
    public const CSS_URL = '/_modules/comingsoon/notice.css';

    /** Where the two links go (the admin screen and its stored-values preview). */
    public const SETTINGS_URL = '/comingsoon/admin';
    public const PREVIEW_URL  = '/comingsoon/admin/preview';

    /**
     * Add the bar to a finished public HTML page for a signed-in user while the holding page is up.
     *
     * @return void
     */
    public function dispatchLoopShutdown()
    {
        try {
            $request  = $this->getRequest();
            $response = $this->getResponse();
            if (!$request || !$response || !$this->_isPublicPage($request, $response)) { return; }
            if (!class_exists('Zend_Auth') || !Zend_Auth::getInstance()->hasIdentity()) { return; }
            if (!class_exists('Comingsoon_Content') || !Comingsoon_Content::showing()) { return; }

            $body = self::inject((string) $response->getBody(), self::markup(self::mayManage()), self::stylesheet());
            $response->setBody($body);
        } catch (Throwable $e) {
            // A notice must never break the page it decorates.
        }
    }

    /**
     * Insert the bar right after the opening <body> tag and its stylesheet before </head>.
     *
     * A document with no <body> (a fragment, a download, a JSON string) comes back untouched, and so does
     * one that already carries the bar.
     *
     * @param  string $html       the rendered response body
     * @param  string $markup     the bar's HTML (from markup())
     * @param  string $stylesheet the <link> tag (from stylesheet()); '' to skip it
     * @return string             the body with the bar added, or $html unchanged
     */
    public static function inject(string $html, string $markup, string $stylesheet = ''): string
    {
        if ($html === '' || strpos($html, 'data-cs-notice') !== false) { return $html; }
        if (!preg_match('/<body\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) { return $html; }

        $at   = $m[0][1] + strlen($m[0][0]);
        $html = substr($html, 0, $at) . $markup . substr($html, $at);

        if ($stylesheet !== '' && preg_match('/<\/head\s*>/i', $html, $h, PREG_OFFSET_CAPTURE)) {
            $html = substr($html, 0, $h[0][1]) . $stylesheet . substr($html, $h[0][1]);
        }
        return $html;
    }

    /**
     * The bar's HTML, translated and escaped.
     *
     * @param  bool   $withLinks true to include the preview + settings links (a role that may open the admin)
     * @return string            the markup
     */
    public static function markup(bool $withLinks): string
    {
        $e = fn(string $key) => htmlspecialchars(self::_t($key), ENT_QUOTES, 'UTF-8');

        $html = '<div class="cs-notice" role="status" data-cs-notice>'
              . '<p class="cs-notice__text">'
              . '<svg class="cs-notice__icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">'
              . '<path fill="currentColor" d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 0 1 6 0v3H9Z"/></svg>'
              . '<strong>' . $e('comingsoon.notice.title') . '</strong> '
              . '<span>' . $e('comingsoon.notice.body') . '</span>'
              . '</p>';
        if ($withLinks) {
            $html .= '<p class="cs-notice__links">'
                   . '<a href="' . self::PREVIEW_URL . '" target="_blank" rel="noopener">' . $e('comingsoon.notice.preview') . '</a>'
                   . '<a href="' . self::SETTINGS_URL . '">' . $e('comingsoon.notice.settings') . '</a>'
                   . '</p>';
        }
        return $html . '</div>';
    }

    /**
     * The <link> tag for the bar's stylesheet, cache-busted by the file's mtime.
     *
     * @return string the tag
     */
    public static function stylesheet(): string
    {
        $file = dirname(__DIR__, 3) . '/assets/notice.css';
        $v    = is_file($file) ? '?v=' . filemtime($file) : '';
        return '<link rel="stylesheet" href="' . self::CSS_URL . $v . '">';
    }

    /**
     * May the signed-in user open the Coming Soon admin (and its preview)?
     *
     * @return bool true when the ACL allows it; false on any doubt
     */
    public static function mayManage(): bool
    {
        if (!class_exists('Tiger_Acl_Acl') || !method_exists('Tiger_Acl_Acl', 'identityIsAllowed')) { return false; }
        return Tiger_Acl_Acl::identityIsAllowed('Comingsoon_AdminController', 'index');
    }

    /** A rendered, public, full HTML page — not admin/auth, not XHR/JSON, not a redirect or an error. */
    private function _isPublicPage(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response): bool
    {
        if (strtoupper((string) $request->getMethod()) !== 'GET') { return false; }
        if ($request instanceof Zend_Controller_Request_Http && $request->isXmlHttpRequest()) { return false; }
        if (in_array(strtolower((string) $request->getModuleName()), self::SKIP_MODULES, true)) { return false; }
        if ($response->isRedirect() || $response->isException()) { return false; }
        if ($response->getHttpResponseCode() >= 300) { return false; }

        foreach ($response->getHeaders() as $h) {
            if (strtolower((string) $h['name']) === 'content-type' && stripos((string) $h['value'], 'text/html') === false) {
                return false;
            }
        }

        $layout = class_exists('Zend_Layout') ? Zend_Layout::getMvcInstance() : null;
        if (!$layout || !$layout->isEnabled()) { return false; }   // a full-document view (the holding page itself, a builder)
        return !in_array(strtolower((string) $layout->getLayout()), self::SKIP_LAYOUTS, true);
    }

    /** Translate a key through the registered translator, else return the key. */
    private static function _t(string $key): string
    {
        try {
            if (Zend_Registry::isRegistered('Zend_Translate')) {
                return (string) Zend_Registry::get('Zend_Translate')->translate($key);
            }
        } catch (Throwable $e) { /* fall through */ }
        return $key;
    }
}
