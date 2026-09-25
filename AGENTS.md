# AGENTS.md — ComingSoon

Module-specific notes. Shared Tiger conventions live in tiger-core; this file only records what is
particular to ComingSoon.

## What it is
A public (BSD-3) Tiger module named **ComingSoon** (slug `comingsoon`, class prefix `Comingsoon_`). One
skinnable holding page that owns the public home (`/`) while active and steps aside the moment a real site
publishes. Not a theme, not a site builder.

## Load-bearing facts
- **The `/` takeover is a front-controller plugin** (`Comingsoon_Plugin_Home`, registered in `Bootstrap`),
  not a router route. It rewrites only the home dispatch (path `''`, GET) to `comingsoon/index`; every
  other route is untouched. Deactivating the module removes the plugin — nothing to clean up. Register the
  plugin with **no explicit stackIndex** (a fixed one collides with a core plugin and fatals every request).
- **Class prefix is `Comingsoon_`, not `ComingSoon_`.** The framework resolves the ACL resource and
  controller class as `_studly(slug)` = `Comingsoon` (it lowercases first). A capital-S prefix silently
  fails ACL (guest → login redirect) and dispatch. Display name is still "ComingSoon".
- **Never name an admin view var `skins`** (or any core layout var). The admin runs inside the PUMA `admin`
  layout, whose `_partials/skin-switcher.phtml` iterates `$skins` (the theme's skin-name list). A bare
  `$this->view->skins` clobbers it and 500s the whole admin chrome. All ComingSoon admin vars are
  `cs`-prefixed (`csSkins`, `csCurrent`, `csActive`, `csSite`).
- **The public page inlines its skin CSS** (the skin file's contents, read by the controller) — this is the
  one place CSS is inlined, for self-containment (theme-independent, works on a brand-new account) and
  Lighthouse (no render-blocking request). Skins remain separate, swappable files; no bespoke CSS is
  authored in `.phtml`. The admin screen follows the normal house rules (semantic Bootstrap, `asset()` JS,
  no inline style — SVG swatches use presentation attributes, not CSS).
- **Content is read, not stored.** Site name/tagline/contact/social come from the install's Site Identity
  config (`Comingsoon_Content`); only `comingsoon.skin` and `comingsoon.active` are the module's own
  settings, written via `Tiger_Model_Config` (SCOPE_GLOBAL).
- **Skins auto-discover** from `configs/skins/*.php` (must have a matching `assets/skins/<name>.css`).
  Adding a skin = adding those two files.

## Lifecycle (spec: coming-soon-module-spec.md / TIGER-229)
Active → the holding page shows at `/`. The owner's **Go live** toggle sets `comingsoon.active=0` (real
site shows) without deactivating. Auto-activate on account creation (TigerServer) and auto-deactivate on
first real publish (a core theme-publish hook) are the intended automation — the manual override + the
active flag are the mechanism today; wire the events on the epic.

## No external CDN
Fonts are system stacks by design; no webfonts, no CDN — required for the offline/self-contained guarantee
and the Lighthouse target.
