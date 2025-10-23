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

// cli.php
// Script for executing command-line tools like i18n extraction and compilation.
// --- 1. Setup Environment ---
// Determine the root path. Assuming this script is at the root or a known offset.
// Adjust the realpath part if this file is not directly in the root directory.
// Example uses realpath(__DIR__ . '/../../../') to simulate finding the root from a deep directory.
// If cli.php is directly in the root, use: $rootPath = __DIR__ . '/';
$rootPath = rtrim(realpath(__DIR__ . '/../../../'), '/') . '/';

require_once $rootPath . 'library/ckvsoft/autoload.php';

// Autoload setup (assuming Autoload class exists and is necessary for ckvsoft\tools\CliTool)
$autoload = new \ckvsoft\Autoload([
    $rootPath . '/library',
        ]);

use ckvsoft\tools\CliTool;
use ckvsoft\I18n;

// --- 2. Initialize the Tool ---
$cliTool = new CliTool($rootPath);

// --- 3. Argument Handling ---
// $argv contains the arguments: $argv[0] is the script name (cli.php)
global $argv;
$command = strtolower($argv[1] ?? ''); // The first parameter (e.g., 'extract' or 'compile')
$domain = $argv[2] ?? I18n::DEFAULT_DOMAIN; // The optional second parameter (the text domain)

if (empty($command)) {
    echo "Usage:\n";
    echo "  php cli.php [command] [domain]\n\n";
    echo "Commands:\n";
    echo "  extract    Extracts translatable strings and creates/updates the .pot file.\n";
    echo "  lernen     (Alias for extract)\n";
    echo "  update     Merges the latest .pot template into existing .po files (msgmerge).\n";
    echo "  compile    Compiles all .po files into .mo files.\n";
    echo "  pocompile  (Alias for compile)\n";
    echo "Example:\n";
    echo "  php cli.php extract\n";
    echo "  php cli.php update\n";
    echo "  php cli.php compile user_module\n";
    exit(1);
}

// --- 4. Command Execution ---

switch ($command) {
    case 'extract':
    case 'lernen':
        $cliTool->extractMessages($domain);
        break;

    case 'update':
        $cliTool->updateMessages($domain);
        break;

    case 'compile':
    case 'pocompile':
        $cliTool->compileMessages($domain);
        break;

    default:
        echo "Unknown command: '{$command}'.\n";
        exit(1);
}

// Exit success
exit(0);
