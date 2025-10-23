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

namespace ckvsoft\tools;

use ckvsoft\I18n;

/**
 * CliTool provides command-line utilities for the framework,
 * primarily focusing on Internationalization (i18n) tasks like
 * message extraction and compilation.
 */
class CliTool
{

    private $rootPath;

    /**
     * Constructor.
     *
     * @param string $rootPath The absolute path to the application root.
     */
    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, '/') . '/';
    }

    /**
     * Extracts translatable strings from source files and updates the .pot file.
     * This executes the xgettext utility.
     *
     * @param string $domain The text domain (e.g., 'ckvsoft' or a module name).
     */
    public function extractMessages(string $domain = I18n::DEFAULT_DOMAIN): void
    {
        // Define paths
        $outputFile = $this->rootPath . 'locale/' . $domain . '.pot';

        // Use a path pattern for source files.
        $sourcePaths = $this->rootPath . '{library,modules,core_modules}';

        // Define translation functions used in the code
        // __() and _() (single) use one argument, __n() and _n() (plural) use two
        $functions = '__:1;__n:1,2;_:1;_n:1,2';

        echo "--- Extracting Messages for Domain: {$domain} ---\n";
        echo "Searching paths (approximate): {$sourcePaths}/*.php\n";

        // To handle multiple directories recursively, the 'find' command is most robust.
        // We use escapeshellarg for security on the root path.
        $safeRootPath = escapeshellarg($this->rootPath);

        // Command to find relevant PHP files and pipe their paths to xgettext
        // Note: xgettext arguments must not be quoted if they contain the keyword pattern
        $findCommand = "find {$safeRootPath} -type f -regex '.*\\.\\(php\\|js\\)$'";

        $xgettextCommand = "xgettext --from-code=UTF-8 --language=PHP ";
        $xgettextCommand .= "--keyword='{$functions}' ";
        $xgettextCommand .= "--output=" . escapeshellarg($outputFile) . " -f -"; // -f - reads filenames from stdin
        // The full command executed via shell
        $command = "{$findCommand} | {$xgettextCommand}";

        // Execute the command
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            echo "Successfully generated/updated POT file: {$outputFile}\n";
            echo "Next step: Run 'update' command (msgmerge) to update existing PO files from this template.\n\n";
        } else {
            echo "Error running xgettext (Return code: {$returnVar}).\n";
            echo "Output:\n" . implode("\n", $output) . "\n";
            echo "Please ensure the 'gettext' tools (xgettext, find) are installed and accessible on your system PATH.\n\n";
        }
    }

    /**
     * Updates all existing .po files for a given domain by merging them with the latest .pot template.
     * This executes the msgmerge utility.
     *
     * @param string $domain The text domain (e.g., 'ckvsoft' or a module name).
     */
    public function updateMessages(string $domain = I18n::DEFAULT_DOMAIN): void
    {
        $localeDir = $this->rootPath . 'locale/';
        $potFile = $localeDir . $domain . '.pot';

        echo "--- Updating PO Files for Domain: {$domain} (using msgmerge) ---\n";

        if (!file_exists($potFile)) {
            echo "ERROR: POT template file not found at '{$potFile}'. Please run the 'extract' command first.\n";
            return;
        }

        // Find all language subdirectories (e.g., 'de_DE', 'en_US')
        $languages = glob($localeDir . '*', GLOB_ONLYDIR);

        if (empty($languages)) {
            echo "No language directories found in '{$localeDir}'.\n";
            return;
        }

        $errors = false;
        foreach ($languages as $langDir) {
            $poFile = $langDir . '/LC_MESSAGES/' . $domain . '.po';
            $langCode = basename($langDir);

            // Skip directories that do not contain a PO file for this domain
            if (!file_exists($poFile)) {
                echo "Skipping {$langCode}: PO file not found at '{$poFile}'.\n";
                continue;
            }

            echo "Updating {$langCode} (" . basename($poFile) . ")... ";

            // Command to merge the PO file with the POT template
            // --update is required for in-place update
            $command = "msgmerge --update " . escapeshellarg($poFile) . " " . escapeshellarg($potFile);

            // Execute the command
            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                echo "SUCCESS\n";
            } else {
                echo "ERROR (Return code: {$returnVar})\n";
                echo "  Output: " . implode(" ", $output) . "\n";
                $errors = true;
            }
        }

        if ($errors) {
            echo "\n--- Update Completed with ERRORS ---\n";
            echo "Please check the output for warnings (like fuzzy strings) and fatal errors (like duplicate msgids).\n\n";
        } else {
            echo "\n--- Update Complete (All successful) ---\n\n";
        }
    }

    /**
     * Compiles all .po files for a given domain into .mo files.
     * This executes the msgfmt utility.
     *
     * @param string $domain The text domain (e.g., 'ckvsoft' or a module name).
     */
    public function compileMessages(string $domain = I18n::DEFAULT_DOMAIN): void
    {
        $localeDir = $this->rootPath . 'locale/';

        echo "--- Compiling Messages for Domain: {$domain} ---\n";

        // Find all language subdirectories (e.g., 'de_DE', 'en_US')
        $languages = glob($localeDir . '*', GLOB_ONLYDIR);

        if (empty($languages)) {
            echo "No language directories found in '{$localeDir}'.\n";
            return;
        }

        $errors = false;
        foreach ($languages as $langDir) {
            $langCode = basename($langDir);
            $poFile = $langDir . '/LC_MESSAGES/' . $domain . '.po';
            $moDir = $langDir . '/LC_MESSAGES/';
            $moFile = $moDir . $domain . '.mo';

            if (!file_exists($poFile)) {
                echo "Skipping {$langCode}: PO file not found at '{$poFile}'.\n";
                continue;
            }

            // Ensure the LC_MESSAGES directory exists
            if (!is_dir($moDir)) {
                mkdir($moDir, 0755, true);
            }

            echo "Compiling {$langCode} (" . basename($poFile) . " -> " . basename($moFile) . ")... ";

            // Command to compile the PO file to MO
            $command = "msgfmt -o " . escapeshellarg($moFile) . " " . escapeshellarg($poFile);

            // Execute the command
            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                echo "SUCCESS\n";
            } else {
                echo "ERROR (Return code: {$returnVar})\n";
                echo "  Output: " . implode(" ", $output) . "\n";
                $errors = true;
            }
        }

        if ($errors) {
            echo "\n--- Compilation Completed with ERRORS ---\n\n";
            echo "Please ensure the 'msgfmt' tool is installed and accessible.\n";
        } else {
            echo "\n--- Compilation Complete (All successful) ---\n\n";
        }
    }
}
