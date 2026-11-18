<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_AdminController — the owner's editor (/comingsoon/admin), admin+ (acl.ini).
 *
 * index renders the editor: a live preview, the skin "look" cards, and the message fields (heading,
 * tagline, background media) below it. Nothing saves on click — the admin edits freely and the preview
 * updates live; a single Save button persists through `/api` (Comingsoon_Service_Settings::save) and the
 * on/off toggle through `setActive` — both form-validated with the screen's one CSRF token (TIGER-538;
 * this controller used to take those writes itself, on an X-Requested-With header alone). preview renders
 * the holding page for the CURRENT (possibly unsaved) values passed as query params, so the admin sees
 * exactly what will publish before saving.
 */
class Comingsoon_AdminController extends Tiger_Controller_Admin_Action
{
    public function indexAction()
    {
        // NB: namespaced view vars — a bare `skins` would clobber the admin layout's own theme-skin list
        // (core _partials/skin-switcher.phtml iterates $skins), 500ing the whole admin chrome.
        // Everything reads back the ADMIN's org lane — the same tier api() writes (TIGER-511).
        $org = Comingsoon_Content::adminOrgId();
        $this->view->title     = 'Coming Soon page';
        $this->view->csSkins   = Comingsoon_Skins::all();
        $this->view->csCurrent = Comingsoon_Content::skin($org);
        $this->view->csActive  = Comingsoon_Content::showing($org);   // on AND not stood down by a published home page
        $this->view->csSite    = Comingsoon_Content::name();
        $this->view->csHeading = Comingsoon_Content::heading($org);
        $this->view->csTagline = Comingsoon_Content::tagline($org);
        $this->view->csBgId    = Comingsoon_Content::backgroundId($org);
        $this->view->csBg      = Comingsoon_Content::background($org);   // ['url','kind','mime'] or null (thumbnail)
        $this->view->csForm    = new Comingsoon_Form_Settings();         // renders the screen's one write token
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
        $org = Comingsoon_Content::adminOrgId();   // preview the admin's own lane (stored values + media)

        $skin = (string) $req->getParam('skin', '');
        if (!Comingsoon_Skins::has($skin)) { $skin = Comingsoon_Content::skin($org); }
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
            : Comingsoon_Content::heading($org);
        $this->view->tagline       = $tagline !== null
            ? (trim((string) $tagline) !== '' ? trim((string) $tagline) : Comingsoon_Content::DEFAULT_TAGLINE)
            : Comingsoon_Content::tagline($org);
        // ?bg is caller-supplied: resolveMedia() only mints a URL for the admin's org's (or shared '') media.
        $this->view->background    = $bgId !== null
            ? Comingsoon_Content::resolveMedia((string) $bgId, $org)
            : Comingsoon_Content::background($org);
        $this->view->email         = Comingsoon_Content::email();
        $this->view->social        = Comingsoon_Content::social();

        $this->getResponse()->setHeader('X-Robots-Tag', 'noindex', true);
        echo $this->view->render('index/index.phtml');
    }
}
