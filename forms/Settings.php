<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_Form_Settings — the look and message of the holding page (Save): skin, heading, tagline and
 * an optional background media id (TIGER-538).
 *
 * Every field is optional — an empty heading/tagline/background means "use the default", exactly as the
 * screen always behaved. The skin must be one the module ships; the background must look like a media id
 * (Content::setBackground() and resolveMedia() still decide whether it belongs to this org).
 */
class Comingsoon_Form_Settings extends Comingsoon_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            ['text', 'skin', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['InArray', false, ['haystack' => array_keys(Comingsoon_Skins::all()), 'strict' => true,
                    'messages' => ['notInArray' => $this->_t('comingsoon.error.skin')]]]],
            ]],
            ['text', 'heading', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['max' => 160]]],
            ]],
            ['text', 'tagline', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['max' => 300]]],
            ]],
            ['text', 'background', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '/^[a-fA-F0-9-]{0,36}$/',
                    'messages' => ['regexNotMatch' => $this->_t('comingsoon.error.background')]]]],
            ]],
        ];
    }
}
