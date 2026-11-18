<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_Service_Settings — the /api behind the Coming Soon admin (TIGER-538): save the look +
 * message, and turn the holding page on or off.
 *
 * Admin-gated in acl.ini (the whole service shares one role). Every write validates a Tiger_Form first —
 * which is what carries the CSRF token — and lands as an ORG-scoped config row for the caller's current
 * org (TIGER-511); a caller with no org has no lane to save into and writes nothing. These replace the
 * old `/comingsoon/admin/api` controller action, which took writes on the strength of an
 * X-Requested-With header alone.
 *
 * @api
 */
class Comingsoon_Service_Settings extends Tiger_Service_Service
{
    /**
     * Save the holding page's look and message for the caller's org.
     *
     * @param  array $params {skin?, heading?, tagline?, background?}
     * @apiRequest Comingsoon_Form_Settings
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Comingsoon_Form_Settings();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }

        $org = Comingsoon_Content::adminOrgId();
        if ($org === '') { $this->_error('comingsoon.error.no_org'); return; }

        $v = $form->getValues();
        try {
            // All four or none: a half-saved look (new skin, old heading) is worse than a refused save.
            $this->_transaction(function () use ($v, $org) {
                $ok = (($v['skin'] ?? '') === '' || Comingsoon_Content::setSkin((string) $v['skin'], $org))
                    && Comingsoon_Content::setHeading((string) ($v['heading'] ?? ''), $org)
                    && Comingsoon_Content::setTagline((string) ($v['tagline'] ?? ''), $org)
                    && Comingsoon_Content::setBackground((string) ($v['background'] ?? ''), $org);
                if (!$ok) { throw new RuntimeException('comingsoon.error.save'); }
            });
            $this->_success(null, 'comingsoon.saved');
        } catch (Throwable $e) {
            Comingsoon_Content::resetCache();   // drop any value cached from the rolled-back writes
            $this->_error('comingsoon.error.save');
        }
    }

    /**
     * Turn the holding page on (visitors see it) or off (visitors see the site as it is) for the caller's org.
     *
     * @param  array $params {on: 0|1}
     * @apiRequest Comingsoon_Form_Active
     * @return void
     */
    public function setActive(array $params): void
    {
        if (!$this->_isAllowed('setActive')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Comingsoon_Form_Active();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }

        $org = Comingsoon_Content::adminOrgId();
        if ($org === '') { $this->_error('comingsoon.error.no_org'); return; }

        $on = in_array((string) $form->getValue('on'), ['1', 'true', 'on', 'yes'], true);
        Comingsoon_Content::setActive($on, $org)
            ? $this->_success(['active' => $on], $on ? 'comingsoon.saved.on' : 'comingsoon.saved.off')
            : $this->_error('comingsoon.error.save');
    }
}
