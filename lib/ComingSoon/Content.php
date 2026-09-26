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

    /** Configured social links as [ ['key'=>..,'label'=>..,'url'=>..], ... ], only those set + valid. */
    public static function social(): array
    {
        $out = [];
        foreach (self::SOCIAL as $key) {
            $url = self::cfg('tiger.site.social.' . $key);
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $out[] = ['key' => $key, 'label' => ucfirst($key === 'x' ? 'X' : $key), 'url' => $url];
            }
        }
        return $out;
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
