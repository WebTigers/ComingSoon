/* SPDX-License-Identifier: BSD-3-Clause
 * ComingSoon admin — live editor. Nothing saves on click: picking a look, typing a heading/tagline, or
 * choosing a background just updates the LIVE preview. One Save button persists everything; the on/off
 * toggle is the separate immediate action. Both go to /api (Comingsoon_Service_Settings save / setActive)
 * carrying the screen's CSRF token. */
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

    // Every write goes through /api (Comingsoon_Service_Settings) with the screen's CSRF token (TIGER-538).
    function api(method, params) {
        var tok  = document.querySelector('#cs-csrf [name="_csrf"]');
        var body = Object.assign({ module: 'comingsoon', service: 'settings', method: method, _csrf: tok ? tok.value : '' }, params || {});
        return fetch('/api', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(body)
        }).then(function (r) { return r.json().catch(function () { return { result: 0, messages: [] }; }); });
    }
    function note(msg, type) {
        if (window.TigerDOM && TigerDOM.notify) { TigerDOM.notify(fb, msg, { type: type || 'info' }); }
        else { var d = document.createElement('div'); d.className = 'alert alert-' + (type === 'error' ? 'danger' : (type || 'info')); d.textContent = msg; fb.innerHTML = ''; fb.appendChild(d); }
    }
    // The standard envelope: result 1 = ok; otherwise show the server's messages, and any per-field errors.
    function bad(r) {
        if (r && r.result === 1) { return false; }
        var msgs = (r && r.messages) || [];
        if (r && r.form) {
            Object.keys(r.form).forEach(function (f) {
                var errs = r.form[f]; Object.keys(errs || {}).forEach(function (k) { msgs.push({ message: errs[k], class: 'error' }); });
            });
        }
        if (!msgs.length) { msgs.push({ message: 'Something went wrong — please try again.', class: 'error' }); }
        msgs.forEach(function (m) { note(m.message, m.class === 'success' ? 'success' : 'error'); });
        return true;
    }

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
        Promise.resolve(run).then(function (r) {
            if (bad(r)) { return; }
            var m = (r.messages || [])[0];   // the server's translated "Saved."
            note(m ? m.message : 'Saved.', 'success');
        }).catch(function () { note('Network error — please try again.', 'error'); });
    });

    /* ---- On/off — the one explicit immediate action ------------------------------------------ */
    // Turning the page OFF takes it down for every visitor, so it asks first (TIGER-537: the old
    // "Show my site now" label read like "show ME my site" and switched it off without a word).
    // Turning it back ON needs no confirm. Strings come from the page's i18n carrier.
    function tr(alias) { return (window.Tiger && Tiger.t) ? Tiger.t(alias) : alias; }
    function confirmOff() {
        if (!(window.TigerModal && TigerModal.confirm)) { return Promise.resolve(true); }
        return TigerModal.confirm({
            title: tr('cs_confirm_title'), body: tr('cs_confirm_body'),
            confirmLabel: tr('cs_confirm_ok'), cancelLabel: tr('cs_confirm_cancel'), variant: 'warning'
        });
    }
    var toggle = document.getElementById('cs-toggle');
    toggle.addEventListener('click', function () {
        var btn = this;
        var makeActive = root.getAttribute('data-cs-active') !== '1';
        (makeActive ? Promise.resolve(true) : confirmOff()).then(function (ok) {
            if (!ok) { return; }
            var task = function () { return api('setActive', { on: makeActive ? '1' : '0' }); };
            var run = (window.TigerButton && TigerButton.run) ? TigerButton.run(btn, task) : task();
            return Promise.resolve(run).then(function (r) {
                if (bad(r)) { return; }
                root.setAttribute('data-cs-active', makeActive ? '1' : '0');
                var badge = document.getElementById('cs-status-badge');
                var icon = badge.querySelector('i');
                badge.classList.toggle('text-bg-success', makeActive);
                badge.classList.toggle('text-bg-secondary', !makeActive);
                if (icon) { icon.className = 'fa-solid ' + (makeActive ? 'fa-circle-check' : 'fa-circle') + ' me-1'; }
                document.getElementById('cs-status-label').textContent = tr(makeActive ? 'cs_status_on' : 'cs_status_off');
                document.getElementById('cs-status-note').textContent = tr(makeActive ? 'cs_status_on_note' : 'cs_status_off_note');
                toggle.textContent = makeActive ? toggle.getAttribute('data-off-label') : toggle.getAttribute('data-on-label');
                note(tr(makeActive ? 'cs_saved_on' : 'cs_saved_off'), 'success');
            });
        }).catch(function () { note('Network error — please try again.', 'error'); });
    });
})();
