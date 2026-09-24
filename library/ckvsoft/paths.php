<?php

namespace ckvsoft;

/**
 * Paths - zentrale Pfadauflösung einer cevian-Instanz.
 *
 * Shared-Split: Das Framework (library/, core_modules/, locale/) kann in einem
 * geteilten Baum (z. B. /vhome/vhtml/_cevian) liegen, während jede Site ihren
 * eigenen Docroot mit config/, modules/, var/, public/, .htaccess behält.
 *
 *   CEVIAN_ROOT:       Wurzel des geteilten Framework-Baums (library/-Ort).
 *                      Wird vom gemeinsamen index.php gesetzt; CLI/Tests
 *                      fallen auf die library-Relation zurück.
 *   CEVIAN_SITE_ROOT:  Docroot der aktuellen Site. Wird vom Site-Stub VOR dem
 *                      require des gemeinsamen index.php gesetzt (siehe
 *                      contrib/shared-split/site-index.php).
 *
 * Standalone (kein Stub, Tests, alte Bäume): beide Konstanten fallen auf den
 * Framework-Baum zurück - das Verhalten ist byte-gleich zum bisherigen
 * Einzelbaum-Betrieb.
 */
class Paths
{

    /**
     * Docroot der aktuellen Site (config/, modules/, var/, public/).
     */
    public static function siteRoot(): string
    {
        if (defined('CEVIAN_SITE_ROOT')) {
            return self::normalize(CEVIAN_SITE_ROOT);
        }
        return self::coreRoot();
    }

    /**
     * Wurzel des geteilten Framework-Baums (library/, core_modules/, locale/).
     */
    public static function coreRoot(): string
    {
        if (defined('CEVIAN_ROOT')) {
            return self::normalize(CEVIAN_ROOT);
        }
        // Fallback: library/ckvsoft/paths.php -> Baumwurzel
        return self::normalize(dirname(__DIR__, 2) . '/');
    }

    /**
     * Verzeichnis der geteilten Core-Module.
     */
    public static function coreModulesDir(): string
    {
        return self::coreRoot() . 'core_modules/';
    }

    /**
     * Verzeichnis der Site-Module.
     */
    public static function siteModulesDir(): string
    {
        return self::siteRoot() . 'modules/';
    }

    /**
     * Normalisiert einen Pfad auf trailing slash und löst realpath auf,
     * wenn das Verzeichnis existiert (symlink-sicher).
     */
    private static function normalize(string $path): string
    {
        $path = rtrim($path, '/\\') . '/';
        $real = realpath($path);
        return $real !== false ? rtrim($real, '/\\') . '/' : $path;
    }
}