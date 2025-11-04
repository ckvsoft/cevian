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

require_once 'library/ckvsoft/autoload.php';

// Autoload
$autoload = new \ckvsoft\Autoload([
    __DIR__ . '/library',
    __DIR__ . '/modules',
        ]);

$config = new \ckvsoft\mvc\Config();
$configData = $config->getMergedConfig();

// --- PHP Settings ---
$phpSettings = $configData['php_settings'];
ini_set('display_errors', $phpSettings['display_errors']);
ini_set('display_startup_errors', $phpSettings['display_startup_errors']);
ini_set('log_errors', $phpSettings['log_errors']);
ini_set('error_log', __DIR__ . $phpSettings['error_log_path']);

// Error Reporting
$errorReportingLevel = match ($phpSettings['error_reporting']) {
    'E_ALL' => E_ALL,
    'E_NOTICE' => E_NOTICE,
    'E_ALL & ~E_NOTICE' => E_ALL & ~E_NOTICE,
    default => E_ALL
};
error_reporting($errorReportingLevel);

// --- Paths & App ---
$paths = $configData['paths'];
$app = $configData['app'];
$session = $configData['session'];

$request = new \ckvsoft\Request();
define('BASE_URI', $request->getBaseUri());

define('MODULES_URI', rtrim($paths['modules_uri'], '/') . '/');
define('CORE_MODULES_URI', rtrim($paths['core_modules_uri'], '/') . '/');
define('APP_DEBUG', $app['debug']);
define('CSS_JS_DEBUG', $app['css_js_debug']);
define('HASH_KEY', $app['hash_key']);

// --- Critical Checks ---
if (empty($app['hash_key'])) {
    $bootstrap = new ckvsoft\mvc\Bootstrap();
    $bootstrap->setPathRoot(getcwd() . '/');
    $bootstrap->setControllerDefault('installer');
    $bootstrap->init();
    exit;
}

// --- Session ---
$timeout = $session['timeout'];
session_start();
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    \ckvsoft\MultiLoginManager::logoutCurrentSession();
    \ckvsoft\MultiLoginManager::runGarbageCollection($timeout);
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time();
\ckvsoft\MultiLoginManager::updateActivityTimestamp();

// --- Bootstrap ---
$bootstrap = new ckvsoft\mvc\Bootstrap();
$bootstrap->setPathRoot(getcwd() . '/');
$bootstrap->setControllerDefault($app['controller_default']);
$bootstrap->init();
