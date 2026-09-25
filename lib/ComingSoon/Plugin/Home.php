<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_Plugin_Home — while the module is active, the public home ('/') renders the holding page.
 *
 * A front-controller plugin (not a router route) so it wins deterministically over the CMS home dispatch,
 * and ONLY the home is touched — every other route (/auth/login, /admin, /api, the module's own
 * /comingsoon/admin, assets) is left exactly as it was. Turning the page off is a config flag
 * (comingsoon.active=0, the admin "Go live"); deactivating the module removes the plugin entirely. So
 * "released on deactivate, no cleanup" falls out of the module lifecycle.
 *
 * @api
 */
class Comingsoon_Plugin_Home extends Zend_Controller_Plugin_Abstract
{
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        if (trim((string) $request->getPathInfo(), '/') !== '') { return; }          // home only
        if (strtoupper((string) $request->getMethod()) !== 'GET') { return; }        // normal page view
        if (strtolower((string) $request->getModuleName()) === 'comingsoon') { return; } // no re-entry
        if (!class_exists('Comingsoon_Content') || !Comingsoon_Content::isActive()) { return; }

        $request->setModuleName('comingsoon')
                ->setControllerName('index')
                ->setActionName('index')
                ->setDispatched(false);
    }
}
