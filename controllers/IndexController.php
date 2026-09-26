<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_IndexController — the public holding page (rendered at '/' by Comingsoon_Plugin_Home).
 *
 * Like a full-screen app, it disables the theme layout and renders its OWN complete document: the skin's
 * CSS is inlined (self-contained, theme-independent, no render-blocking request → best Lighthouse, works
 * on a brand-new account before any theme). Guest-readable (configs/acl.ini). No JS.
 */
class Comingsoon_IndexController extends Tiger_Controller_Action
{
    public function init()
    {
        parent::init();
        $this->_helper->layout()->disableLayout();
    }

    public function indexAction()
    {
        $skin = Comingsoon_Content::skin();
        $meta = Comingsoon_Skins::get($skin);

        $this->view->skinName      = $skin;
        $this->view->layoutVariant = $meta['layout'];
        $this->view->skinCss       = Comingsoon_Skins::css($skin);
        $this->view->siteName      = Comingsoon_Content::name();
        $this->view->heading       = Comingsoon_Content::heading();
        $this->view->tagline       = Comingsoon_Content::tagline();
        $this->view->background    = Comingsoon_Content::background();
        $this->view->email         = Comingsoon_Content::email();
        $this->view->social        = Comingsoon_Content::social();

        // A holding page is not the site's real content — keep it out of the index until the site publishes.
        $this->getResponse()->setHeader('X-Robots-Tag', 'noindex', true);
    }
}
