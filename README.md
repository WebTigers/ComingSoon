# ComingSoon

A branded, single-page **Coming Soon** holding page for a Tiger site — the first thing the public sees
from the moment DNS resolves until the real site is published. It makes a fresh domain look intentional
and professional immediately, instead of a blank page or a raw docroot.

ComingSoon is **not a theme and not a site builder**. It is one skinnable holding page with a single job,
built to turn itself off the instant a real site goes live.

- **Owns the public home (`/`) while active** — via a front-controller plugin, so only the home is
  affected; `/login`, the admin, and every other route are untouched.
- **Zero-config beyond a skin** — the site name, tagline, contact and social links come from the
  install's existing Site Identity; the only choice is which look to use.
- **Five preset skins** — `midnight`, `clean`, `bold`, `warm`, `studio` — each a distinct, self-contained
  aesthetic (background, palette, typography, layout). Adding a skin is adding two files; no template change.
- **Fast and self-contained** — one request, inlined CSS, system fonts, **no external CDN**, built to
  clear Lighthouse 95+.
- **Turns off cleanly** — an owner "Go live" toggle (or deactivating the module) hands the site straight
  back to its real theme. No migration, no residue.

## Skins

Each skin is two files: a catalog entry `configs/skins/<name>.php` (label, tagline, layout variant, and a
preview swatch for the admin cards) and its visual `assets/skins/<name>.css` (a CSS-variable overlay on
the shared `assets/base.css`). Skins are auto-discovered — drop in the two files and the new skin appears
in the picker. Skins are independent of the site's real theme; this is a holding page, not a design preview.

| Skin | Look |
|------|------|
| `midnight` | Dark, confident — deep midnight gradient, luminous accent, light-weight sans |
| `clean` | Light, minimal — white ground, near-black type, one restrained accent |
| `bold` | High-contrast, energetic — charcoal, huge uppercase heading, accent bar |
| `warm` | Approachable, personal — linen ground, serif heading, terracotta accent |
| `studio` | Creative, editorial — light-gray grid, monospace heading, magenta accent |

## Admin

The owner picks a skin from their control panel (**Coming Soon** in the admin sidebar): preview cards, a
live preview of their own site name and tagline in each skin, the on/off status, and a manual **Go live**
override that shows the real site immediately even if it isn't finished.

## Install

The repository root is the module. It installs into a Tiger site at
`application/modules/comingsoon/` and activates with `tiger module:activate comingsoon`. It is bundled
with TigerInstall as the default fresh-install landing.

## License

BSD-3-Clause. Tiger™ and WebTigers™ are trademarks of WebTigers.
