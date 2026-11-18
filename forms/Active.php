<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_Form_Active — turn the holding page on or off (TIGER-538).
 *
 * Turning it off shows an unfinished site to every visitor, which is exactly the write a cross-site page
 * must not be able to make on an admin's behalf.
 */
class Comingsoon_Form_Active extends Comingsoon_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            ['text', 'on', [
                'required'   => true,
                'filters'    => ['StringTrim', 'StringToLower'],
                'validators' => [['InArray', false, ['haystack' => ['0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'], 'strict' => true]]],
            ]],
        ];
    }
}
