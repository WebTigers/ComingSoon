<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_Form_Write — the shared base for the Coming Soon admin's writes (TIGER-538).
 *
 * The admin screen renders ONE token and drives both Save and the on/off toggle from it, so the two
 * forms share a CSRF salt — `Tiger_Form::csrfSalt()` defaults to the class, which would make the second
 * call fail "your security token expired" (see `Mcp_Form_Write`, `Desk_Form_TicketWrite`).
 *
 * Before this the screen posted to a controller action that checked only the X-Requested-With header:
 * no form, no token, no per-field errors.
 */
abstract class Comingsoon_Form_Write extends Tiger_Form
{
    /**
     * One token for every Coming Soon admin write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Comingsoon_Form_Write';
    }
}
