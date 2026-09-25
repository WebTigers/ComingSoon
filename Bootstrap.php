<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * ComingSoon module bootstrap.
 *
 * Loads the module's helper classes (Skins/Content/Plugin) explicitly — they don't depend on a module
 * autoloader resource type — then registers the front-controller plugin that makes the public home render
 * the holding page while active, and an admin nav entry for the skin picker.
 */
class Comingsoon_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Load helpers + own the public home while active. */
    protected function _initComingSoon()
    {
        $lib = __DIR__ . '/lib/ComingSoon';
        require_once $lib . '/Skins.php';
        require_once $lib . '/Content.php';
        require_once $lib . '/Plugin/Home.php';

        // Append (no explicit stackIndex — a fixed one collides with core plugins). Module bootstraps run
        // AFTER core, so an appended plugin sorts after the core plugins and still has the final say over
        // how '/' dispatches (see the plugin doc).
        Zend_Controller_Front::getInstance()->registerPlugin(new Comingsoon_Plugin_Home());
    }

    /** A sidebar entry (admin+, ACL-filtered) for the skin picker + status. */
    protected function _initComingSoonNav()
    {
        if (!class_exists('Tiger_Admin_Nav')) { return; }
        Tiger_Admin_Nav::register([
            'key'      => 'comingsoon',
            'label'    => 'Coming Soon',
            'icon'     => 'fa-rocket',
            'href'     => '/comingsoon/admin',
            'resource' => 'Comingsoon_AdminController',
            'order'    => 12,
        ]);
    }
}
