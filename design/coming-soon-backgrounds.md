# ComingSoon — background image briefs

Art-directed prompts to generate the optional background textures for the ComingSoon skins. Each is a
**subtle, low-contrast background** that sits *behind centered/left-aligned page text* — so the number-one
rule is **legibility**: calm, muted, lots of empty space, no busy focal point.

**How to use (ChatGPT):** open ChatGPT with image generation, paste one skin's *Prompt* verbatim, download
the result, and save it under the exact *Filename*. Send them back and they get wired into the skin CSS.

**How to use (the `openai-image` skill, once the OpenAI project has credits):** each prompt runs as
`python3 scripts/gen.py -p "<prompt>" -o assets/skins/img/<file> --size 1536x1024 --quality high`.

**Recommended output:** 1536×1024 (landscape 3:2), PNG or WebP. The page cover-fits it, so keep the
interesting parts loose and edge-safe — no important detail in the exact center or corners.

**Only three skins get a background.** `clean` stays pure white and `bold` stays solid charcoal — their
minimalism is the design, and a texture would fight the type. Leave those two alone.

---

## midnight  →  `assets/skins/img/midnight-bg.png`
Palette: bg `#0a0e17` → `#101a30`, accent (emerald) `#34d399`. Text is centered, light, low-weight.

**Prompt:**
> A deep midnight background for a premium tech landing page. A smooth dark gradient from near-black
> #0a0e17 at the edges to a subtle deep indigo #101a30 toward the upper center, with a very faint, soft
> aurora haze — a whisper of cool cyan-green (#34d399) glow low and diffuse near the top, quickly fading to
> darkness. Extremely low contrast, calm and confident, cinematic but restrained. Generous empty dark space
> in the middle for centered white text. Fine, barely-visible film grain. No stars, no nebula clutter, no
> text, no logos, no people, no hard edges, no bright hotspots, no vignette.

## warm  →  `assets/skins/img/warm-bg.png`
Palette: linen `#f5efe3` / `#f2ead8`, warm ink `#2b2620`, accent (terracotta) `#c2683f`. Text is left-aligned.

**Prompt:**
> A soft, warm, approachable background for a personal site. A gentle cream-and-linen surface (#f5efe3 to
> #f2ead8) with a very subtle natural paper/canvas texture and an extremely soft, diffuse warm glow drifting
> in from the upper-left corner, like morning light on textured paper. Faint, organic, low contrast, cozy
> and human. Lots of calm negative space on the left for left-aligned dark text. A trace of muted terracotta
> warmth (#c2683f) is welcome but must stay faint. No pattern repetition, no text, no logos, no people, no
> objects, no hard edges, no vignette.

## studio  →  `assets/skins/img/studio-bg.png`
Palette: light gray `#ededed`, ink `#141414`, accent (magenta) `#e11d74`. Editorial, monospace heading, left/bottom text.

**Prompt:**
> A minimal editorial studio background: a flat, light neutral-gray paper surface (#ededed) with a very
> fine, subtle concrete/matte paper grain and the faintest suggestion of a precise light-gray grid or
> blueprint lines, barely perceptible. Clean, modern, gallery-like, high-key, almost white. Very low
> contrast so dark text and a small magenta (#e11d74) accent read crisply on top. Mostly empty with a calm
> uniform surface. No bold shapes, no color blocks, no text, no logos, no people, no hard edges, no
> vignette.

---

### Notes for wiring (developer)
- Save as PNG from ChatGPT; convert to WebP (`cwebp -q 82`) before committing to keep the page light.
- Each skin's CSS sets `--cs-bg-image: url(img/<skin>-bg.webp)` (path is relative to the skin CSS file).
  The page still cover-fits and the existing gradient stays as a fallback under the image.
- After adding, re-check text legibility on the live preview for every skin at phone + desktop widths, and
  keep the page's Lighthouse/perf budget in mind (one modest image, lazy where possible).
