<?php

require_once 'library/ckvsoft/autoload.php';

$autoload = new \ckvsoft\Autoload([
    __DIR__ . '/library',
    __DIR__ . '/modules',
        ]);

$config = new \ckvsoft\mvc\Config();
$customConfigData = $config->getAppConfig() ?? [];

// --- Default Config mit Fallbacks ---
$defaultConfig = [
    'php_settings' => [
        'display_errors' => 0,
        'display_startup_errors' => 0,
        'log_errors' => 1,
        'error_log_path' => '/var/log/error.log',
        'error_reporting' => 'E_ALL',
    ],
    'paths' => [
        'base_uri' => '/',
        'modules_uri' => 'modules/',
    ],
    'app' => [
        'debug' => false,
        'css_js_debug' => false,
        'controller_default' => 'home',
        'hash_key' => null, // ❌ muss zwingend vorhanden sein
    ],
    'session' => [
        'timeout' => 1800
    ]
];

// Merge Config with Default
$configData = array_replace_recursive($defaultConfig, $customConfigData);

// --- Critical Checks ---
if (empty($configData['app']['hash_key'])) {
    throw new \RuntimeException("Critical config missing: 'app.hash_key' must be set in app.json");
}

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

// --- Konstanten ---
$paths = $configData['paths'];
$app = $configData['app'];
$session = $configData['session'];

define('BASE_URI', $paths['base_uri']);
define('MODULES_URI', $paths['modules_uri']);
define('APP_DEBUG', $app['debug']);
define('CSS_JS_DEBUG', $app['css_js_debug']);
define('HASH_KEY', $app['hash_key']);

// --- Session ---
$timeout = $session['timeout'];
session_start();
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    \ckvsoft\MultiLoginManager::logoutCurrentSession();
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time();

// --- Bootstrap ---
$bootstrap = new ckvsoft\mvc\Bootstrap();
$bootstrap->setPathRoot(getcwd() . '/');
$bootstrap->setControllerDefault($app['controller_default']);
$bootstrap->init();
