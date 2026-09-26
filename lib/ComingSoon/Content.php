<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comingsoon_Content — what the holding page shows, resolved from data the install already has.
 *
 * The page is zero-config beyond skin selection: the site name, tagline, contact and social links come
 * from the install's existing Site Identity config; only the skin (and the active flag) are the module's
 * own stored settings. Every getter is defensive — the page renders cleanly with just a name (falling
 * back to the request host), so a brand-new account looks intentional immediately.
 *
 * @api
 */
class Comingsoon_Content
{
    /** Social platforms shown if a URL is configured, in display order (label + Font Awesome-free glyph). */
    const SOCIAL = ['x', 'twitter', 'instagram', 'facebook', 'linkedin', 'youtube', 'tiktok', 'github'];

    /** Defaults for the owner-editable message (used until the owner sets their own). */
    const DEFAULT_HEADING = 'Coming Soon';
    const DEFAULT_TAGLINE = 'Our new web experience will be here soon.';

    /** The site/brand name — the <h1>. Falls back to the request host, then a neutral default. */
    public static function name(): string
    {
        $n = self::cfg('tiger.site.name') ?: self::cfg('tiger.site.title') ?: self::cfg('site.name');
        if ($n === '') { $n = self::host(); }
        return $n !== '' ? $n : 'This site';
    }

    /** The main HEADING (the big centered line) — the owner's message. Editable; defaults to "Coming Soon". */
    public static function heading(): string
    {
        return self::cfg('comingsoon.heading') ?: self::DEFAULT_HEADING;
    }

    /** The tagline / subheading — the owner's message. Editable; defaults to a friendly placeholder. */
    public static function tagline(): string
    {
        return self::cfg('comingsoon.tagline') ?: self::DEFAULT_TAGLINE;
    }

    /** The chosen background media id ('' when none — the skin's own background shows). */
    public static function backgroundId(): string
    {
        return self::cfg('comingsoon.background_media');
    }

    /**
     * Resolve the background media to ['url'=>…, 'kind'=>'image'|'video', 'mime'=>…], or null when
     * none is set / it can't be resolved. A video background renders as an autoplay/muted/loop <video>;
     * an image as a cover background. Defensive: any failure just falls back to the skin's background.
     *
     * @return array{url:string,kind:string,mime:string}|null
     */
    public static function background(): ?array
    {
        return self::resolveMedia(self::backgroundId());
    }

    /** Resolve any media id to ['url','kind','mime'] (image/video only), or null. Used live by the preview too. */
    public static function resolveMedia(string $id): ?array
    {
        $id = trim($id);
        if ($id === '' || !class_exists('Tiger_Model_Media')) { return null; }
        try {
            $mm  = new Tiger_Model_Media();
            $row = $mm->findById($id);
            if (!$row) { return null; }
            $arr  = $row->toArray();
            $kind = (string) ($arr['kind'] ?? '');
            if (!in_array($kind, ['image', 'video'], true)) { return null; }
            return ['url' => (string) $mm->url($arr), 'kind' => $kind, 'mime' => (string) ($arr['mime_type'] ?? '')];
        } catch (Throwable $e) {
            return null;
        }
    }

    /** A public contact email, or '' — shown as a mailto if present. */
    public static function email(): string
    {
        $e = self::cfg('tiger.site.email') ?: self::cfg('tiger.site.contact_email');
        return (filter_var($e, FILTER_VALIDATE_EMAIL)) ? $e : '';
    }

    /** Configured social links as [ ['key'=>..,'label'=>..,'url'=>..,'glyph'=>..], ... ], only those set + valid. */
    public static function social(): array
    {
        $out = [];
        foreach (self::SOCIAL as $key) {
            $url = self::cfg('tiger.site.social.' . $key);
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $out[] = ['key' => $key, 'label' => ucfirst($key === 'x' ? 'X' : $key), 'url' => $url, 'glyph' => self::socialGlyph($key)];
            }
        }
        return $out;
    }

    /**
     * Inline SVG path(s) for a social/contact glyph, for a `0 0 24 24` viewBox with `fill="currentColor"`.
     * The holding page is self-contained (no Font Awesome), so footer icons ship as inline SVG. An unknown
     * key falls back to a generic link glyph.
     */
    public static function socialGlyph(string $key): string
    {
        $g = [
            'email'     => '<path d="M1.5 8.67v8.58a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3V8.67l-8.928 5.493a3 3 0 0 1-3.144 0L1.5 8.67Z"/><path d="M22.5 6.908V6.75a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3v.158l9.714 5.978a1.5 1.5 0 0 0 1.572 0L22.5 6.908Z"/>',
            'x'         => '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.45-6.231Zm-1.161 17.52h1.833L7.084 4.126H5.117l11.966 15.644Z"/>',
            'twitter'   => '<path d="M23.953 4.57a10 10 0 0 1-2.825.775 4.958 4.958 0 0 0 2.163-2.723 9.99 9.99 0 0 1-3.127 1.195 4.92 4.92 0 0 0-8.384 4.482A13.978 13.978 0 0 1 1.64 3.162a4.92 4.92 0 0 0 1.523 6.574 4.9 4.9 0 0 1-2.229-.616v.06a4.923 4.923 0 0 0 3.946 4.827 4.996 4.996 0 0 1-2.212.085 4.936 4.936 0 0 0 4.604 3.417 9.868 9.868 0 0 1-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 0 0 7.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.936 9.936 0 0 0 24 4.59Z"/>',
            'instagram' => '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069ZM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0Zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324ZM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881Z"/>',
            'facebook'  => '<path d="M24 12.073c0-6.627-5.373-12-12-12S0 5.446 0 12.073c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073Z"/>',
            'linkedin'  => '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286ZM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065Zm1.782 13.019H3.555V9h3.564v11.452ZM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003Z"/>',
            'youtube'   => '<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814ZM9.545 15.568V8.432L15.818 12l-6.273 3.568Z"/>',
            'github'    => '<path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12Z"/>',
            'tiktok'    => '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07Z"/>',
        ];
        return $g[$key] ?? '<path d="M3.9 12a5 5 0 0 1 5-5h3v2h-3a3 3 0 0 0 0 6h3v2h-3a5 5 0 0 1-5-5Zm4-1h8v2h-8v-2Zm5-4h3a5 5 0 0 1 0 10h-3v-2h3a3 3 0 0 0 0-6h-3V7Z"/>';
    }

    /** The selected skin name (validated), else the default. */
    public static function skin(): string
    {
        $s = self::cfg('comingsoon.skin');
        return Comingsoon_Skins::has($s) ? $s : Comingsoon_Skins::DEFAULT_SKIN;
    }

    /** Whether the holding page should show. True unless explicitly turned off (manual override / gone live). */
    public static function isActive(): bool
    {
        $v = self::cfg('comingsoon.active', '1');
        return !in_array(strtolower((string) $v), ['0', 'false', 'off', 'no'], true);
    }

    // ---- writers (admin) ------------------------------------------------------------------------

    /** Persist the selected skin. */
    public static function setSkin(string $name): bool
    {
        if (!Comingsoon_Skins::has($name)) { return false; }
        return self::store('comingsoon.skin', $name);
    }

    /** Persist the editable heading (trimmed, capped). Empty clears it back to the default. */
    public static function setHeading(string $v): bool
    {
        return self::store('comingsoon.heading', mb_substr(trim($v), 0, 160));
    }

    /** Persist the editable tagline (trimmed, capped). Empty clears it back to the default. */
    public static function setTagline(string $v): bool
    {
        return self::store('comingsoon.tagline', mb_substr(trim($v), 0, 300));
    }

    /** Persist the background media id (sanitised to a UUID shape; '' clears it). */
    public static function setBackground(string $mediaId): bool
    {
        $id = preg_replace('/[^a-fA-F0-9-]/', '', trim($mediaId));
        return self::store('comingsoon.background_media', (string) $id);
    }

    /** Turn the holding page on/off (off = the real site shows immediately). */
    public static function setActive(bool $on): bool
    {
        return self::store('comingsoon.active', $on ? '1' : '0');
    }

    // ---- internals ------------------------------------------------------------------------------

    /** Read a dotted key from the merged Zend_Config, '' when unset. */
    protected static function cfg(string $key, string $default = ''): string
    {
        $node = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        if (!$node instanceof Zend_Config) { return $default; }
        foreach (explode('.', $key) as $seg) {
            $node = ($node instanceof Zend_Config) ? $node->get($seg) : null;
            if ($node === null) { return $default; }
        }
        return is_scalar($node) ? trim((string) $node) : $default;
    }

    /** Write an install-level (global) setting via the config model. */
    protected static function store(string $key, string $value): bool
    {
        if (!class_exists('Tiger_Model_Config')) { return false; }
        try {
            (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_GLOBAL, '', $key, $value);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    protected static function host(): string
    {
        $h = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        return preg_match('/^[a-z0-9.-]+$/', $h) ? $h : '';
    }
}
