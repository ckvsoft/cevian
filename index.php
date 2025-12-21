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

// --- START SESSION CHECK BLOCK ---
// CRITICAL: Check for a status request and abort execution immediately after responding.
// This must happen AFTER config is loaded, but BEFORE the session is renewed below.
// Use the Request object to avoid superglobals.
if ($request->getPost('action') === 'check_session_status') {

    // Get the timeout value from the already loaded configuration
    $timeout = $session['timeout'];

    // Start session to access existing data (CRITICAL: MUST NOT renew activity yet)
    session_start();

    // Prepare default response for inactive/expired state
    $response = [
        'is_active' => false,
        'time_remaining_seconds' => 0,
        'expiry_time_iso' => null,
        // Default redirect path is the BASE_URI
        'redirect_path' => BASE_URI
    ];

    // Determine if the user is logged in using the new global flag.
    // This flag must be set in MultiLoginManager::login().
    $isUserLoggedIn = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

    // Check if session activity is present AND if a logged-in state exists.
    if (isset($_SESSION['LAST_ACTIVITY']) && $isUserLoggedIn) {

        // If a module name is available in the session (must be set in MultiLoginManager::login)
        if (isset($_SESSION['last_module_name']) && $_SESSION['last_module_name'] !== '') {
            // Append module name to BASE_URI, e.g., /app/modulename
            $response['redirect_path'] = rtrim(BASE_URI, '/') . '/' . trim($_SESSION['last_module_name'], '/');
        }

        $lastActivity = $_SESSION['LAST_ACTIVITY'];

        // Calculate the time when the session should officially expire
        $expiryTimestamp = $lastActivity + $timeout;

        // Calculate the time left
        $timeRemaining = $expiryTimestamp - time();

        if ($timeRemaining > 0) {
            // Session is active
            $response['is_active'] = true;
            $response['time_remaining_seconds'] = $timeRemaining;
            $response['expiry_time_iso'] = date('c', $expiryTimestamp);
        } else {
            // Session has logically expired for a logged-in user. Trigger 401 response code.
            // The JSON response (including redirect_path) will still be sent afterwards.
            http_response_code(401);
        }
    } else {
        // No session data or no user logged in. Return default inactive response (200 OK).
        // The 401 status is only needed for an active session that expired.
    }

    // Send the JSON response and immediately stop the script execution (No-Touch Principle)
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
// --- END SESSION CHECK BLOCK ---
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
