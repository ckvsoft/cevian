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

    /**
     * Extracts the module name (which serves as the text domain) from the call stack.
     *
     * @return string|null The module name (text domain) or null if not found.
     */
    private static function _getModuleDomain(): ?string
    {
        // Get the call stack
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Path pattern for module files (e.g., /modules/my_module/...)
        $modulePattern = '#/(modules|core_modules)/([^/]+)/#i';

        // Iterate through the call stack to find a file path belonging to a module
        foreach ($trace as $step) {
            if (isset($step['file']) && preg_match($modulePattern, $step['file'], $matches)) {
                // $matches[2] is the module name (e.g., 'user', 'blog')
                $moduleName = strtolower($matches[2]);

                // Bind the domain for the discovered module if not already bound
                if (function_exists('bindtextdomain') && self::$rootPath) {
                    $localePath = self::$rootPath . 'locale';
                    // Bind it to the same locale directory as the default domain
                    bindtextdomain($moduleName, $localePath);
                    bind_textdomain_codeset($moduleName, 'UTF-8');
                }
                return $moduleName;
            }
        }

        // If no specific module file is found in the stack, return the default domain
        return self::DEFAULT_DOMAIN;
    }
}
