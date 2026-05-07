<?php

/*
 * The MIT License
 *
 * Copyright 2025 chris.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace ckvsoft;

use ckvsoft\mvc\Config;

// ------------------------------------------------------------------
// INTERNATIONALIZATION (i18n) FALLBACK AND HELPER DEFINITIONS
// Defines the translation functions globally.
// ------------------------------------------------------------------
if (!function_exists('_')) {

    /**
     * Fallback translation function: Returns the original string.
     * Used when gettext is unavailable.
     *
     * @param string $text The original string to be translated (the message ID).
     * @return string
     */
    function _(string $text): string
    {
        return trim($text);
    }

}

if (!function_exists('ngettext')) {

    /**
     * Fallback plural function: Returns singular if count is 1, otherwise plural.
     * Used when gettext is unavailable.
     */
    function ngettext(string $singular, string $plural, int $count): string
    {
        return ($count === 1) ? $singular : $plural;
    }

}

if (!function_exists('_n')) {

    /**
     * Abbreviation for the standard plural function (ngettext).
     * * @param string $singular The singular message ID.
     * @param string $plural The plural message ID.
     * @param int $count The number to determine the plural form.
     * @return string The translated singular or plural string.
     */
    function _n(string $singular, string $plural, int $count): string
    {
        return ngettext($singular, $plural, $count);
    }

}

if (!function_exists('__')) {

    /**
     * Module translation helper. Automatically determines the module domain
     * by analyzing the call stack and translates the message using dgettext.
     *
     * @param string $message The message ID to translate.
     * @return string The translated string.
     */
    function __(string $message): string
    {
        if (class_exists('\\ckvsoft\\I18n')) {
            return \ckvsoft\I18n::getModuleMessage($message);
        }
        // Fallback to standard translation if I18n class is not available
        return _($message);
    }

}

if (!function_exists('__n')) {

    /**
     * Module plural translation helper. Automatically determines the module domain
     * and translates the plural message using dngettext.
     *
     * @param string $singular The singular message ID.
     * @param string $plural The plural message ID.
     * @param int $count The number to determine the plural form.
     * @return string The translated singular or plural string.
     */
    function __n(string $singular, string $plural, int $count): string
    {
        if (class_exists('\\ckvsoft\\I18n')) {
            return \ckvsoft\I18n::getModulePluralMessage($singular, $plural, $count);
        }
        // Fallback to standard plural translation if I18n class is not available
        return _n($singular, $plural, $count);
    }

}

class I18n
{

    private static $rootPath;

    // The default domain used by the entire application (often 'messages' or the app name)
    const DEFAULT_DOMAIN = 'ckvsoft'; // Updated default domain for the framework

    /**
     * Initializes the Gettext configuration.
     *
     * @param string $rootPath The application's root path.
     */
    public static function init(string $rootPath)
    {
        self::$rootPath = $rootPath;

        // Set the path to the translation files for the default domain
        $localePath = self::$rootPath . 'locale';

        // --- Determine Target Locale from Configuration ---
        if (class_exists('\\ckvsoft\\mvc\\Config') && method_exists('\\ckvsoft\\mvc\\Config', 'get')) {
            // Read locale from configuration. Default to 'en_US' if not set in config.
            $targetBaseLocale = \ckvsoft\mvc\Config::get('app.locale', 'en_US');
        } else {
            // Fallback placeholder if Config is unavailable or method is missing.
            $targetBaseLocale = 'en_US';
            error_log("Warning: ckvsoft\\Config class or 'get' method not found. Defaulting locale to 'en_US'.");
        }
        // --------------------------------------------------
        // --- THE CRUCIAL INITIALIZATION STEP: Robust setlocale for cross-platform compatibility ---
        // List of possible locale strings for various operating systems (Windows, Linux, macOS)
        $localeMap = [
            'en_US' => ['en_US.UTF-8', 'en_US.utf8', 'en_US', 'english', 'C'],
            'de_DE' => ['de_DE.UTF-8', 'de_DE.utf8', 'de_DE', 'de', 'German_Germany.1252', 'German_Germany', 'German'],
        ];

        // Use the configured locale map, falling back to en_US if the configured base locale is unknown
        $localesToTry = $localeMap[$targetBaseLocale] ?? $localeMap['en_US'];

        $success = false;
        foreach ($localesToTry as $localeString) {
            // LC_ALL sets all locale categories (numeric, currency, time, etc.)
            if (setlocale(LC_ALL, $localeString) !== false) {
                $success = true;
                error_log('Info: cevian set locale: ' . $localeString);
                break;
            }
        }

        if (!$success) {
            // Fallback warning if no supported locale could be set
            error_log('Warning: Could not set a compatible locale for ' . $targetBaseLocale . '. Falling back to system default.');
        }
        // ------------------------------------------------------------------
        // Ensure gettext is installed before calling its functions
        if (function_exists('bindtextdomain')) {
            bindtextdomain(self::DEFAULT_DOMAIN, $localePath);
            bind_textdomain_codeset(self::DEFAULT_DOMAIN, 'UTF-8');
            textdomain(self::DEFAULT_DOMAIN);
        }
    }

    /**
     * Dynamically determines the calling module's domain and translates the message.
     * Used by the global helper function __().
     *
     * @param string $message The message ID to translate.
     * @return string The translated string.
     */
    public static function getModuleMessage(string $message): string
    {
        // Fallback to simple return if the required gettext function is not available
        if (!function_exists('dgettext')) {
            return $message;
        }

        $domain = self::_getModuleDomain();

        if ($domain && $domain !== self::DEFAULT_DOMAIN) {
            return dgettext($domain, $message);
        }

        // Fallback to the default domain translation
        return gettext($message);
    }

    /**
     * Dynamically determines the calling module's domain and translates the plural message.
     * Used by the global helper function __n().
     *
     * @param string $singular The singular message ID.
     * @param string $plural The plural message ID.
     * @param int $count The number to determine the plural form.
     * @return string The translated singular or plural string.
     */
    public static function getModulePluralMessage(string $singular, string $plural, int $count): string
    {
        // Fallback to standard ngettext if dngettext is not available
        if (!function_exists('dngettext')) {
            // This relies on the ngettext fallback in Bootstrap.php
            return ngettext($singular, $plural, $count);
        }

        $domain = self::_getModuleDomain();

        if ($domain && $domain !== self::DEFAULT_DOMAIN) {
            // Use domain-specific plural translation
            return dngettext($domain, $singular, $plural, $count);
        }

        // Fallback to the default domain plural translation (ngettext uses the current textdomain)
        return ngettext($singular, $plural, $count);
    }

    /*
     * Extracts the module name (which serves as the text domain) from the call stack.
     * This now uses the cached result from the Config class to avoid multiple debug_backtrace() calls.
     *
     * @return string|null The module name (text domain) or the default domain.
     */

    private static function _getModuleDomain(): ?string
    {
        // Use the cached method from Config
        $moduleName = Config::getModuleNameFromBacktrace();

        if ($moduleName !== null) {
            // Bind the domain for the discovered module if not already bound
            // NOTE: We assume Config::getModuleNameFromBacktrace() runs before this,
            // but we must check for gettext functions here as it's the i18n class.

            /*
              if (function_exists('bindtextdomain') && self::$rootPath) {
              $localePath = bindtextdomain($moduleName);

              $localePath = self::$rootPath . 'locale';
              // Bind it to the same locale directory as the default domain
              // If it's already bound, PHP will just confirm the binding.
              bindtextdomain($moduleName, $localePath);
              bind_textdomain_codeset($moduleName, 'UTF-8');
              }
             */
            return $moduleName;
        }

        // If no specific module file is found in the stack, return the default domain
        return self::DEFAULT_DOMAIN;
    }
}
