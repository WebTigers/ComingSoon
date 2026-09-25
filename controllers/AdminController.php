<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_AdminController — the owner's skin picker + status (/comingsoon/admin), admin+ (acl.ini).
 *
 * index renders the admin chrome (skin cards, a live preview, the on/off status). api saves the skin or
 * the active flag (AJAX). preview renders the holding page itself for a given skin so the admin can see
 * any skin applied to their own site name/tagline, live, in an iframe — without changing what's published.
 */
class Comingsoon_AdminController extends Tiger_Controller_Admin_Action
{
    const OPS = ['set_skin', 'set_active'];

    public function indexAction()
    {
        // NB: namespaced view vars — a bare `skins` would clobber the admin layout's own theme-skin list
        // (core _partials/skin-switcher.phtml iterates $skins), 500ing the whole admin chrome.
        $this->view->title     = 'Coming Soon page';
        $this->view->csSkins   = Comingsoon_Skins::all();
        $this->view->csCurrent = Comingsoon_Content::skin();
        $this->view->csActive  = Comingsoon_Content::isActive();
        $this->view->csSite    = Comingsoon_Content::name();
    }

    /** AJAX: op=set_skin&skin=<name> | op=set_active&on=0|1 */
    public function apiAction()
    {
        if (strtolower((string) $this->getRequest()->getHeader('X-Requested-With')) !== 'xmlhttprequest') {
            return $this->_json(['ok' => false, 'error' => 'ajax only'], 400);
        }
        $op = preg_replace('/[^a-z_]/', '', strtolower((string) $this->getRequest()->getParam('op', '')));
        if (!in_array($op, self::OPS, true)) { return $this->_json(['ok' => false, 'error' => 'unknown op'], 400); }

        if ($op === 'set_skin') {
            $skin = (string) $this->getRequest()->getParam('skin', '');
            if (!Comingsoon_Content::setSkin($skin)) { return $this->_json(['ok' => false, 'error' => 'invalid skin'], 400); }
            return $this->_json(['ok' => true, 'skin' => $skin]);
        }
        $on = !in_array(strtolower((string) $this->getRequest()->getParam('on', '1')), ['0', 'false', 'off', 'no'], true);
        Comingsoon_Content::setActive($on);
        return $this->_json(['ok' => true, 'active' => $on]);
    }

    /** Render the holding page for ?skin=<name> (the live-preview iframe). Its own document, no chrome. */
    public function previewAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $skin = (string) $this->getRequest()->getParam('skin', '');
        if (!Comingsoon_Skins::has($skin)) { $skin = Comingsoon_Content::skin(); }
        $meta = Comingsoon_Skins::get($skin);

        $this->view->skinName      = $skin;
        $this->view->layoutVariant = $meta['layout'];
        $this->view->skinCss       = Comingsoon_Skins::css($skin);
        $this->view->siteName      = Comingsoon_Content::name();
        $this->view->tagline       = Comingsoon_Content::tagline();
        $this->view->email         = Comingsoon_Content::email();
        $this->view->social        = Comingsoon_Content::social();

        $this->getResponse()->setHeader('X-Robots-Tag', 'noindex', true);
        echo $this->view->render('index/index.phtml');
    }
}
