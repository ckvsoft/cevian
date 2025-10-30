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


// ------------------------------------------------------------------
// INTERNATIONALIZATION (i18n) FALLBACK AND HELPER DEFINITIONS
// Defines the translation functions globally.
// ------------------------------------------------------------------
// 1. STANDARD TRANSLATION (Single message, default domain)
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

// 2. STANDARD PLURAL TRANSLATION (Plural message, default domain)
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

// 3. ABBREVIATION FOR STANDARD PLURAL TRANSLATION
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

// 4. MODULE-SPECIFIC TRANSLATION (Single message, auto-detected domain)
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

// 5. MODULE-SPECIFIC PLURAL TRANSLATION (Plural message, auto-detected domain)
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
