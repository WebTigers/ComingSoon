<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_Skins — the skin catalog.
 *
 * A skin is a self-contained preset: a metadata entry (configs/skins/<name>.php — label, tagline, layout
 * variant, preview swatch for the admin cards) plus its visual (assets/skins/<name>.css, a CSS-variable
 * overlay). Skins are AUTO-DISCOVERED from configs/skins/, so adding a skin is adding those two files —
 * no code change. Skins are independent of the site theme; this is a holding page, not a design preview.
 *
 * @api
 */
class Comingsoon_Skins
{
    /** The default skin when none is selected (light, safe, universally readable). */
    const DEFAULT_SKIN = 'clean';

    const LAYOUTS = ['centered', 'left', 'split'];

    /** The module root (…/application/modules/comingsoon). */
    public static function moduleRoot(): string
    {
        return dirname(dirname(__DIR__));
    }

    /** Every skin, name => meta, sorted by label. Meta is normalized + safe to hand to a view. */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::moduleRoot() . '/configs/skins/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if (!preg_match('/^[a-z][a-z0-9_-]{1,30}$/', $name)) { continue; }
            $meta = self::normalize($name, (array) (include $file));
            if (is_file(self::moduleRoot() . '/assets/skins/' . $name . '.css')) { $out[$name] = $meta; }
        }
        uasort($out, static fn ($a, $b) => strcasecmp($a['label'], $b['label']));
        return $out;
    }

    public static function has(string $name): bool
    {
        return $name !== '' && preg_match('/^[a-z][a-z0-9_-]{1,30}$/', $name)
            && is_file(self::moduleRoot() . '/configs/skins/' . $name . '.php')
            && is_file(self::moduleRoot() . '/assets/skins/' . $name . '.css');
    }

    /** One skin's meta (falls back to the default when unknown). */
    public static function get(string $name): array
    {
        if (!self::has($name)) { $name = self::has(self::DEFAULT_SKIN) ? self::DEFAULT_SKIN : (array_key_first(self::all()) ?? self::DEFAULT_SKIN); }
        return self::normalize($name, (array) (include self::moduleRoot() . '/configs/skins/' . $name . '.php'));
    }

    /** The concatenated CSS the page inlines: the shared base + the selected skin. '' if missing. */
    public static function css(string $name): string
    {
        $base = (string) @file_get_contents(self::moduleRoot() . '/assets/base.css');
        $skin = self::has($name) ? (string) @file_get_contents(self::moduleRoot() . '/assets/skins/' . $name . '.css') : '';
        return trim($base . "\n" . $skin);
    }

    /** Clamp a skin's metadata to a known shape (never trust a raw config file into a view). */
    protected static function normalize(string $name, array $m): array
    {
        $layout = in_array($m['layout'] ?? '', self::LAYOUTS, true) ? $m['layout'] : 'centered';
        $sw = is_array($m['swatch'] ?? null) ? $m['swatch'] : [];
        $hex = static fn ($v, $d) => (is_string($v) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) ? $v : $d;
        return [
            'name'    => $name,
            'label'   => isset($m['label']) ? (string) $m['label'] : ucfirst($name),
            'tagline' => isset($m['tagline']) ? (string) $m['tagline'] : '',
            'layout'  => $layout,
            'swatch'  => [
                'bg'     => $hex($sw['bg'] ?? null, '#faf7f2'),
                'fg'     => $hex($sw['fg'] ?? null, '#1b1a18'),
                'accent' => $hex($sw['accent'] ?? null, '#d97706'),
            ],
        ];
    }
}
