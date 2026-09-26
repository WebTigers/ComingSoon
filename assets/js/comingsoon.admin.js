/* SPDX-License-Identifier: BSD-3-Clause
 * ComingSoon admin — live editor. Nothing saves on click: picking a look, typing a heading/tagline, or
 * choosing a background just updates the LIVE preview. One Save button persists everything (op=save).
 * "Go live" (op=set_active) is the separate on/off action. Over /comingsoon/admin/api. */
(function () {
    'use strict';
    var root = document.getElementById('cs-admin');
    if (!root) { return; }
    var fb       = document.getElementById('cs-admin-feedback');
    var preview  = document.getElementById('cs-preview');
    var base     = root.getAttribute('data-preview-base') || '/comingsoon/admin/preview';
    var elHead   = document.getElementById('cs-heading');
    var elTag    = document.getElementById('cs-tagline');
    var elBgId   = document.getElementById('cs-bg-id');
    var elThumb  = document.getElementById('cs-bg-thumb');
    var elRemove = document.getElementById('cs-bg-remove');

    function api(op, params) {
        return fetch('/comingsoon/admin/api', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(Object.assign({ op: op }, params || {}))
        }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'bad response' }; }); });
    }
    function note(msg, type) {
        if (window.TigerDOM && TigerDOM.notify) { TigerDOM.notify(fb, msg, { type: type || 'info' }); }
        else { var d = document.createElement('div'); d.className = 'alert alert-' + (type === 'error' ? 'danger' : (type || 'info')); d.textContent = msg; fb.innerHTML = ''; fb.appendChild(d); }
    }
    function bad(r) { if (r && r.ok) { return false; } note((r && r.error) || 'error', 'error'); return true; }

    function currentSkin() {
        var on = root.querySelector('.cs-skin[aria-pressed="true"]');
        return on ? on.getAttribute('data-skin') : '';
    }
    function values() {
        return { skin: currentSkin(), heading: elHead ? elHead.value : '', tagline: elTag ? elTag.value : '', background: elBgId ? elBgId.value : '' };
    }

    /* ---- live preview (debounced) ------------------------------------------------------------- */
    var pt = null;
    function updatePreview() {
        clearTimeout(pt);
        pt = setTimeout(function () {
            var v = values();
            preview.src = base + '?skin=' + encodeURIComponent(v.skin)
                + '&heading=' + encodeURIComponent(v.heading)
                + '&tagline=' + encodeURIComponent(v.tagline)
                + '&bg=' + encodeURIComponent(v.background)
                + '&t=' + Date.now();
        }, 200);
    }

    /* ---- look picker: SELECT only (no save) --------------------------------------------------- */
    document.getElementById('cs-skins').addEventListener('click', function (e) {
        var card = e.target.closest('.cs-skin'); if (!card) { return; }
        Array.prototype.forEach.call(root.querySelectorAll('.cs-skin'), function (c) {
            var on = (c === card);
            c.classList.toggle('border-primary', on);
            c.setAttribute('aria-pressed', on ? 'true' : 'false');
            var badge = c.querySelector('[data-cs-current]');
            if (on && !badge) {
                var row = c.querySelector('.fw-semibold');
                if (row && row.parentNode) { badge = document.createElement('span'); badge.className = 'badge text-bg-primary'; badge.setAttribute('data-cs-current', ''); badge.textContent = 'Current'; row.parentNode.appendChild(badge); }
            } else if (!on && badge) { badge.remove(); }
        });
        updatePreview();
    });

    /* ---- heading / tagline: live preview as you type ------------------------------------------ */
    if (elHead) { elHead.addEventListener('input', updatePreview); }
    if (elTag)  { elTag.addEventListener('input', updatePreview); }

    /* ---- background media (image OR video) ---------------------------------------------------- */
    function setThumb(item) {
        if (!elThumb) { return; }
        if (item && item.kind === 'image' && (item.thumb || item.url)) {
            elThumb.innerHTML = '<img src="' + (item.thumb || item.url) + '" alt="" style="width:100%;height:100%;object-fit:cover;">';
        } else if (item && item.kind === 'video') {
            elThumb.innerHTML = '<i class="fa-solid fa-film fa-lg"></i>';
        } else {
            elThumb.innerHTML = '<i class="fa-solid fa-image"></i>';
        }
    }
    var choose = document.getElementById('cs-bg-choose');
    if (choose) {
        choose.addEventListener('click', function () {
            if (!window.TigerMediaPicker || !TigerMediaPicker.open) { note('Media library unavailable.', 'error'); return; }
            TigerMediaPicker.open({ multiple: false, kind: '', onSelect: function (items) {
                var item = items && items[0]; if (!item) { return; }
                if (item.kind !== 'image' && item.kind !== 'video') { note('Pick an image or a video for the background.', 'error'); return; }
                elBgId.value = item.media_id || '';
                setThumb(item);
                if (elRemove) { elRemove.classList.remove('d-none'); }
                updatePreview();
            } });
        });
    }
    if (elRemove) {
        elRemove.addEventListener('click', function () {
            elBgId.value = '';
            setThumb(null);
            elRemove.classList.add('d-none');
            updatePreview();
        });
    }

    /* ---- Save (persists everything) ----------------------------------------------------------- */
    document.getElementById('cs-save').addEventListener('click', function () {
        var btn = this;
        var task = function () { return api('save', values()); };
        var run = (window.TigerButton && TigerButton.run) ? TigerButton.run(btn, task) : task();
        Promise.resolve(run).then(function (r) { if (bad(r)) { return; } note('Saved.', 'success'); }).catch(function () { note('Network error — please try again.', 'error'); });
    });

    /* ---- On/off ("Go live") — the one explicit immediate action ------------------------------- */
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
