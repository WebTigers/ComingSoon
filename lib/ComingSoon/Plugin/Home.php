<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_Plugin_Home — while the module is active AND the page is switched on
 * (comingsoon.active=1), the holding page stands in front of the WHOLE public surface for GUESTS.
 *
 * The rule (v1.1): a coming-soon site is not production, so the wall is deliberately blunt —
 *
 *   - ANY logged-in user bypasses it completely. Give the developer or the client a login and they
 *     browse the real site as it's being built; nobody else sees anything but the holding page. No URL
 *     flags, no preview tokens, no nav clutter — the session is the switch.
 *   - GUESTS see the holding page for every public GET, not just '/'. A front-controller plugin (not a
 *     route) so it wins deterministically over CMS/theme dispatch and covers pages that don't exist yet.
 *   - A short allowlist always passes, even for a guest: the auth paths (a client must be able to log
 *     IN from behind the wall — /login, /logout, /lockscreen), the machine surface (/api), the admin,
 *     this module's own screens, and anything that resolves to a static asset. Finding the login is
 *     the operator's job, not ours — this is a holding page, not an access-control product.
 *
 * Turning the page off is a config flag (comingsoon.active=0, the admin "Go live"); deactivating the
 * module removes the plugin entirely — so "released on deactivate, no cleanup" falls out of the
 * lifecycle. Walled responses are marked no-store so a shared cache can't serve the holding page to a
 * logged-in user (or vice versa).
 *
 * @api
 */
class Comingsoon_Plugin_Home extends Zend_Controller_Plugin_Abstract
{
    /** Leading path segments that always pass, even for a guest. */
    private const PASS = ['login', 'logout', 'lockscreen', 'auth', 'api', 'admin', 'comingsoon'];

    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        if (strtoupper((string) $request->getMethod()) !== 'GET') { return; }         // visible browsing only
        if (strtolower((string) $request->getModuleName()) === 'comingsoon') { return; } // no re-entry
        if (!class_exists('Comingsoon_Content') || !Comingsoon_Content::isActive()) { return; }
        if ($this->_isLoggedIn()) { return; }                                          // any authed user bypasses

        $path = trim((string) $request->getPathInfo(), '/');
        if ($this->_isAllowed($path)) { return; }                                      // auth/api/admin/assets

        // Guest, walled path: stand the holding page in front of it.
        $response = $this->getResponse();
        if ($response) { $response->setHeader('Cache-Control', 'no-store, private', true); }

        $request->setModuleName('comingsoon')
                ->setControllerName('index')
                ->setActionName('index')
                ->setDispatched(false);
    }

    /** True when someone is signed in — any identity, any role, is enough to bypass the wall. */
    private function _isLoggedIn(): bool
    {
        return class_exists('Zend_Auth') && Zend_Auth::getInstance()->hasIdentity();
    }

    /** Auth, machine and admin surfaces, plus static assets, always pass for a guest. */
    private function _isAllowed(string $path): bool
    {
        if ($path === '') { return false; }                                            // '/' is walled
        $first = strtolower(explode('/', $path)[0]);
        if (in_array($first, self::PASS, true)) { return true; }
        if ($first !== '' && $first[0] === '_') { return true; }                        // /_theme, /_modules, /_<key>
        if (preg_match('/\.[a-z0-9]{2,5}$/i', $path)) { return true; }                  // a file, not a page view
        return false;
    }
}
