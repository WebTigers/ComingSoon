<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_AdminController — the owner's editor (/comingsoon/admin), admin+ (acl.ini).
 *
 * index renders the editor: a live preview, the skin "look" cards, and the message fields (heading,
 * tagline, background media) below it. Nothing saves on click — the admin edits freely and the preview
 * updates live; a single Save button persists via `api` (op=save). `set_active` is the separate on/off
 * ("Go live") action. preview renders the holding page for the CURRENT (possibly unsaved) values passed
 * as query params, so the admin sees exactly what will publish before saving.
 */
class Comingsoon_AdminController extends Tiger_Controller_Admin_Action
{
    const OPS = ['save', 'set_active'];

    public function indexAction()
    {
        // NB: namespaced view vars — a bare `skins` would clobber the admin layout's own theme-skin list
        // (core _partials/skin-switcher.phtml iterates $skins), 500ing the whole admin chrome.
        $this->view->title     = 'Coming Soon page';
        $this->view->csSkins   = Comingsoon_Skins::all();
        $this->view->csCurrent = Comingsoon_Content::skin();
        $this->view->csActive  = Comingsoon_Content::isActive();
        $this->view->csSite    = Comingsoon_Content::name();
        $this->view->csHeading = Comingsoon_Content::heading();
        $this->view->csTagline = Comingsoon_Content::tagline();
        $this->view->csBgId    = Comingsoon_Content::backgroundId();
        $this->view->csBg      = Comingsoon_Content::background();   // ['url','kind','mime'] or null (thumbnail)
    }

    /** AJAX: op=save (skin/heading/tagline/background) | op=set_active&on=0|1 */
    public function apiAction()
    {
        if (strtolower((string) $this->getRequest()->getHeader('X-Requested-With')) !== 'xmlhttprequest') {
            return $this->_json(['ok' => false, 'error' => 'ajax only'], 400);
        }
        $op = preg_replace('/[^a-z_]/', '', strtolower((string) $this->getRequest()->getParam('op', '')));
        if (!in_array($op, self::OPS, true)) { return $this->_json(['ok' => false, 'error' => 'unknown op'], 400); }

        if ($op === 'save') {
            $req  = $this->getRequest();
            $skin = (string) $req->getParam('skin', '');
            if ($skin !== '' && !Comingsoon_Content::setSkin($skin)) {
                return $this->_json(['ok' => false, 'error' => 'invalid skin'], 400);
            }
            Comingsoon_Content::setHeading((string) $req->getParam('heading', ''));
            Comingsoon_Content::setTagline((string) $req->getParam('tagline', ''));
            Comingsoon_Content::setBackground((string) $req->getParam('background', ''));
            return $this->_json(['ok' => true]);
        }

        $on = !in_array(strtolower((string) $this->getRequest()->getParam('on', '1')), ['0', 'false', 'off', 'no'], true);
        Comingsoon_Content::setActive($on);
        return $this->_json(['ok' => true, 'active' => $on]);
    }

    /**
     * Render the holding page for the live editor values (?skin, ?heading, ?tagline, ?bg=<media id>),
     * each falling back to the stored value — so the preview iframe shows UNSAVED edits. Its own
     * document, no chrome, noindex.
     */
    public function previewAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $req = $this->getRequest();

        $skin = (string) $req->getParam('skin', '');
        if (!Comingsoon_Skins::has($skin)) { $skin = Comingsoon_Content::skin(); }
        $meta = Comingsoon_Skins::get($skin);

        // A present-but-empty field previews the DEFAULT (empty = "use the default"); an absent field
        // previews the stored value.
        $heading = $req->getParam('heading', null);
        $tagline = $req->getParam('tagline', null);
        $bgId    = $req->getParam('bg', null);

        $this->view->skinName      = $skin;
        $this->view->layoutVariant = $meta['layout'];
        $this->view->skinCss       = Comingsoon_Skins::css($skin);
        $this->view->siteName      = Comingsoon_Content::name();
        $this->view->heading       = $heading !== null
            ? (trim((string) $heading) !== '' ? trim((string) $heading) : Comingsoon_Content::DEFAULT_HEADING)
            : Comingsoon_Content::heading();
        $this->view->tagline       = $tagline !== null
            ? (trim((string) $tagline) !== '' ? trim((string) $tagline) : Comingsoon_Content::DEFAULT_TAGLINE)
            : Comingsoon_Content::tagline();
        $this->view->background    = $bgId !== null
            ? Comingsoon_Content::resolveMedia((string) $bgId)
            : Comingsoon_Content::background();
        $this->view->email         = Comingsoon_Content::email();
        $this->view->social        = Comingsoon_Content::social();

        $this->getResponse()->setHeader('X-Robots-Tag', 'noindex', true);
        echo $this->view->render('index/index.phtml');
    }
}
