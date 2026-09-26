# AGENTS.md — ComingSoon

Module-specific notes. Shared Tiger conventions live in tiger-core; this file only records what is
particular to ComingSoon.

## What it is
A public (BSD-3) Tiger module named **ComingSoon** (slug `comingsoon`, class prefix `Comingsoon_`). One
skinnable holding page that stands in front of the whole public surface **for guests** while active, and
steps aside the moment a real site publishes. Not a theme, not a site builder.

## Load-bearing facts
- **The takeover is a front-controller plugin** (`Comingsoon_Plugin_Home`, registered in `Bootstrap`), not
  a router route, so it wins deterministically over CMS/theme dispatch and covers routes that don't exist
  yet. While active (v1.1): **any logged-in user bypasses it entirely** (`Zend_Auth::hasIdentity()` — any
  role) and sees the real site; a **guest** gets `comingsoon/index` for every public GET. A short allowlist
  always passes (leading segment in `login|logout|lockscreen|auth|api|admin|comingsoon`, an `/_*` asset
  prefix, or any path ending in a file extension) — a client must be able to log IN from behind the wall.
  Walled responses are sent `Cache-Control: no-store, private`. Deactivating the module removes the plugin —
  nothing to clean up. Register the plugin with **no explicit stackIndex** (a fixed one collides with a core
  plugin and fatals every request).
- **Class prefix is `Comingsoon_`, not `ComingSoon_`.** The framework resolves the ACL resource and
  controller class as `_studly(slug)` = `Comingsoon` (it lowercases first). A capital-S prefix silently
  fails ACL (guest → login redirect) and dispatch. Display name is still "ComingSoon".
- **Never name an admin view var `skins`** (or any core layout var). The admin runs inside the PUMA `admin`
  layout, whose `_partials/skin-switcher.phtml` iterates `$skins` (the theme's skin-name list). A bare
  `$this->view->skins` clobbers it and 500s the whole admin chrome. All ComingSoon admin vars are
  `cs`-prefixed (`csSkins`, `csCurrent`, `csActive`, `csSite`, `csHeading`, `csTagline`, `csBgId`, `csBg`).
- **The public page inlines its skin CSS** (the skin file's contents, read by the controller) — this is the
  one place CSS is inlined, for self-containment (theme-independent, works on a brand-new account) and
  Lighthouse (no render-blocking request). Skins remain separate, swappable files; no bespoke CSS is
  authored in `.phtml`. The admin screen follows the normal house rules (semantic Bootstrap, `asset()` JS,
  no inline style — SVG swatches use presentation attributes, not CSS).
- **The owner's message is stored; site chrome is read.** As of v1.1 the owner edits an explicit
  **heading**, **tagline**, and optional **background media** (image *or* video, a media-library id) — stored
  as `comingsoon.heading` / `comingsoon.tagline` / `comingsoon.background`, defaulting via
  `Comingsoon_Content::DEFAULT_HEADING`/`DEFAULT_TAGLINE`. Contact/social still come from Site Identity
  config. All module settings (`comingsoon.skin`, `comingsoon.active`, and the three above) are written via
  `Tiger_Model_Config` (SCOPE_GLOBAL). The admin **never saves on click** — edits update the preview only;
  the explicit **Save** button (`op=save`) persists skin+heading+tagline+background together. "Go live"
  (`set_active`) stays a separate immediate toggle.
- **Skins auto-discover** from `configs/skins/*.php` (must have a matching `assets/skins/<name>.css`).
  Adding a skin = adding those two files.

## Lifecycle (spec: coming-soon-module-spec.md / TIGER-229)
Active → the holding page shows to guests across the public surface (logged-in users bypass). The owner's
**Go live** toggle sets `comingsoon.active=0` (real site shows to everyone) without deactivating. Auto-activate on account creation (TigerServer) and auto-deactivate on
first real publish (a core theme-publish hook) are the intended automation — the manual override + the
active flag are the mechanism today; wire the events on the epic.

## No external CDN
Fonts are system stacks by design; no webfonts, no CDN — required for the offline/self-contained guarantee
and the Lighthouse target.
