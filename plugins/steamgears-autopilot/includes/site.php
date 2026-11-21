<?php
/**
 * SGAP_Site — what site the plugin is installed on (7.92.0).
 *
 * Before 7.92 every AI prompt said "steamgears.eu, a steampunk shop" and "Write in English", so the
 * plugin wrote steampunk posts in English on any other site it was installed on (it runs on
 * brygguiden.dk, a Danish coffee guide, too). This class reads the site instead: name, domain,
 * language, whether WooCommerce is there, and two optional texts the owner can set
 * (site_about, site_voice). steamgears.eu keeps its exact old wording as its default, so nothing
 * changes there.
 *
 * @package Steamgears_Autopilot
 */

if (!defined('ABSPATH')) {
    exit;
}

class SGAP_Site {
    /** The site the plugin was written for. Its defaults are the wording the prompts had before 7.92. */
    const HOME_DOMAIN = 'steamgears.eu';
    const HOME_ABOUT = 'a shop and blog for steampunk costumes, accessories and DIY guides';
    const HOME_VOICE = 'enthusiastic, knowledgeable, a touch of Victorian flair, never cheesy or pushy';
    const DEFAULT_VOICE = 'friendly, knowledgeable and practical, never cheesy or pushy';

    /** WordPress locale prefix => language name the AI understands. */
    const LANGUAGES = [
        'da' => 'Danish', 'en' => 'English', 'de' => 'German', 'sv' => 'Swedish', 'nb' => 'Norwegian',
        'nn' => 'Norwegian', 'fi' => 'Finnish', 'nl' => 'Dutch', 'fr' => 'French', 'es' => 'Spanish',
        'it' => 'Italian', 'pt' => 'Portuguese', 'pl' => 'Polish', 'is' => 'Icelandic',
    ];

    /** Host of the site without "www.", e.g. "brygguiden.dk". */
    public static function domain(): string {
        $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        return (string) preg_replace('/^www\./', '', $host);
    }

    public static function is_home(): bool {
        return self::domain() === self::HOME_DOMAIN;
    }

    /** The site title, or the domain when the title is empty. */
    public static function name(): string {
        $name = trim(wp_strip_all_tags(html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES, 'UTF-8')));
        return $name !== '' ? $name : self::domain();
    }

    /** "Kaffeguiden (brygguiden.dk)"; just the domain when the title is the domain. */
    public static function label(): string {
        $name = self::name();
        return strcasecmp($name, self::domain()) === 0 ? $name : $name . ' (' . self::domain() . ')';
    }

    public static function is_shop(): bool {
        return class_exists('WooCommerce');
    }

    /** "shop and blog" with WooCommerce, otherwise "website". */
    public static function kind(): string {
        return self::is_shop() ? 'shop and blog' : 'website';
    }

    /** One line on what the site is about: the owner's text, else the old text on steamgears.eu, else the tagline. */
    public static function about(): string {
        $own = trim((string) SGAP_Settings::get('site_about', ''));
        if ($own !== '') {
            return $own;
        }
        if (self::is_home()) {
            return self::HOME_ABOUT;
        }
        $tagline = trim(wp_strip_all_tags(html_entity_decode((string) get_bloginfo('description'), ENT_QUOTES, 'UTF-8')));
        return $tagline !== '' ? 'a ' . self::kind() . ': ' . $tagline : 'a ' . self::kind();
    }

    public static function voice(): string {
        $own = trim((string) SGAP_Settings::get('site_voice', ''));
        if ($own !== '') {
            return $own;
        }
        return self::is_home() ? self::HOME_VOICE : self::DEFAULT_VOICE;
    }

    /** Content language: the setting when it is filled in, else the WordPress locale (da_DK => Danish). */
    public static function language(): string {
        $own = trim((string) SGAP_Settings::get('content_language', ''));
        if ($own !== '') {
            return $own;
        }
        return self::language_of_locale(function_exists('get_locale') ? (string) get_locale() : 'en_US');
    }

    public static function language_of_locale(string $locale): string {
        $prefix = strtolower(substr($locale, 0, 2));
        return self::LANGUAGES[$prefix] ?? 'English';
    }

    /** "Kaffeguiden (brygguiden.dk), a website: Find det rigtige kaffegrej" — the subject of most prompts. */
    public static function intro(): string {
        return self::label() . ', ' . self::about();
    }

    /** Snapshot for the self-check, the REST snapshot and Command Center. */
    public static function info(): array {
        return [
            'domain' => self::domain(), 'name' => self::name(), 'about' => self::about(), 'voice' => self::voice(),
            'language' => self::language(), 'shop' => self::is_shop(), 'home' => self::is_home(),
        ];
    }
}
