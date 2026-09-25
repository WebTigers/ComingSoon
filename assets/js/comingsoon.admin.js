/* SPDX-License-Identifier: BSD-3-Clause
 * ComingSoon admin — skin picker + live preview + on/off, over /comingsoon/admin/api. */
(function () {
    'use strict';
    var root = document.getElementById('cs-admin');
    if (!root) { return; }
    var fb = document.getElementById('cs-admin-feedback');
    var preview = document.getElementById('cs-preview');

    function api(op, params) {
        var body = Object.assign({ op: op }, params || {});
        return fetch('/comingsoon/admin/api', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(body)
        }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'bad response' }; }); });
    }
    function note(msg, type) {
        if (window.TigerDOM && TigerDOM.notify) { TigerDOM.notify(fb, msg, { type: type || 'info' }); }
        else {
            var d = document.createElement('div');
            d.className = 'alert alert-' + (type === 'error' ? 'danger' : (type || 'info'));
            d.textContent = msg; fb.innerHTML = ''; fb.appendChild(d);
        }
    }
    function bad(r) { if (r && r.ok) { return false; } note((r && r.error) || 'error', 'error'); return true; }

    // Skin selection
    document.getElementById('cs-skins').addEventListener('click', function (e) {
        var card = e.target.closest('.cs-skin'); if (!card) { return; }
        var skin = card.getAttribute('data-skin');
        api('set_skin', { skin: skin }).then(function (r) {
            if (bad(r)) { return; }
            // Highlight the chosen card, clear the others, move the "Current" badge.
            Array.prototype.forEach.call(root.querySelectorAll('.cs-skin'), function (c) {
                var on = (c === card);
                c.classList.toggle('border-primary', on);
                c.setAttribute('aria-pressed', on ? 'true' : 'false');
                var badge = c.querySelector('[data-cs-current]');
                if (on && !badge) {
                    var row = c.querySelector('.fw-semibold');
                    if (row && row.parentNode) {
                        badge = document.createElement('span');
                        badge.className = 'badge text-bg-primary';
                        badge.setAttribute('data-cs-current', '');
                        badge.textContent = 'Current';
                        row.parentNode.appendChild(badge);
                    }
                } else if (!on && badge) { badge.remove(); }
            });
            // Refresh the live preview.
            preview.src = '/comingsoon/admin/preview?skin=' + encodeURIComponent(skin) + '&t=' + Date.now();
            note('Saved.', 'success');
        });
    });

    // On/off toggle
    var toggle = document.getElementById('cs-toggle');
    toggle.addEventListener('click', function () {
        var makeActive = root.getAttribute('data-cs-active') !== '1';
        api('set_active', { on: makeActive ? '1' : '0' }).then(function (r) {
            if (bad(r)) { return; }
            root.setAttribute('data-cs-active', makeActive ? '1' : '0');
            var badge = document.getElementById('cs-status-badge');
            var icon = badge.querySelector('i');
            badge.classList.toggle('text-bg-success', makeActive);
            badge.classList.toggle('text-bg-secondary', !makeActive);
            if (icon) { icon.className = 'fa-solid ' + (makeActive ? 'fa-circle-check' : 'fa-circle') + ' me-1'; }
            document.getElementById('cs-status-label').textContent = makeActive ? 'Your Coming Soon page is live' : 'Your real site is showing';
            document.getElementById('cs-status-note').textContent = makeActive
                ? 'Visitors to your site see this page. It is replaced automatically when you publish your site.'
                : 'The Coming Soon page is turned off — visitors see your site as it is now.';
            toggle.textContent = makeActive ? toggle.getAttribute('data-off-label') : toggle.getAttribute('data-on-label');
            note('Saved.', 'success');
        });
    });
})();
