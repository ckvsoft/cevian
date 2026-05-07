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

namespace ckvsoft\mvc;

class Bootstrap extends \stdClass
{

    /**
     * @var string $_controllerDefault The default controller to load
     */
    private $_controllerDefault = 'index';

    /**
     * @var string $_uriController The controller name requested via URI
     */
    private $_uriController;

    /**
     * @var string $_uriSubController The sub-controller name requested via URI (if applicable)
     */
    private $_uriSubController;

    /**
     * @var string $_uriMethod The method to call on the controller
     */
    private $_uriMethod;

    /**
     * @var array $this->_uriValue Values beyond the controller/method, used as method arguments
     */
    private $_uriValue = array();

    /**
     * @var string $_pathModel Where the models are located
     */
    private $_pathModel;

    /**
     * @var string $_pathConfig Where the configuration files are located
     */
    private $_pathConfig;

    /**
     * @var string $_pathView Where the views are located
     */
    private $_pathView;

    /**
     * @var string $_pathController Where the controllers are located
     */
    private $_pathController;

    /**
     * @var string $_pathHelper Where the helper files are located
     */
    private $_pathHelper;

    /**
     * @var object $_basePath The base path to include files from (unused property, likely)
     */
    private $_basePath;

    /**
     * @var string $uri The raw URI string requested by the user
     */
    public $uri;

    /**
     * @var array $uriSegments Each URI segment in an array
     */
    public $uriSegments;

    /**
     * @var string $uriSlashPath The relative path string (e.g., '../', '../../')
     */
    public $uriSlashPath;

    /**
     * @var object $_view The view object instance
     */
    private $_view;

    /**
     * __construct - Get the URL and prepare the internal data
     *
     * This is prepared so a route check can happen before things are initialized
     */
    public function __construct()
    {

        $uri = filter_input(INPUT_GET, 'uri');

        if ($uri !== null && $uri !== false) {
            $uri = rtrim($uri, '/');
        }

        $this->uri = $uri ?: '';
    }

    /**
     * init - Initializes the bootstrap handler once ready
     *
     * @param boolean|string $overrideUri
     */
    public function init($overrideUri = false)
    {
        if (!isset($this->_pathRoot))
            die('You must run setPathRoot($path)');

        // --- I18n INITIALIZATION (MUST be called after setPathRoot) ---
        if (class_exists('\\ckvsoft\\I18n')) {
            \ckvsoft\I18n::init($this->_pathRoot);
        }
        // ---------------------------------------------------------------

        $updater = new \ckvsoft\Update\Updater();

        if ($updater->needsUpdate()) {
            try {
                $updater->runUpdate();
            } catch (\Exception $e) {
                error_log("Framework update failed: " . $e->getMessage());
            }
        }
        /** When a route overrides a URI we build the path here */
        $urlToBuild = ($overrideUri == true) ? $overrideUri : $this->uri;
        $this->_buildComponents($urlToBuild);

        /** The order of these is important */
        $this->_initController();
    }

    /**
     * _buildComponents - Sets up the pieces for the Controller, Model, Value
     *
     * @param string $uri
     */
    private function _buildComponents($uri)
    {
        // 1. Split the URI into segments. These segments are still URL-encoded.
        $uriSegments = explode('/', trim($uri, '/'));
        $this->uriSegments = $uriSegments;
        $this->_initUriSlashPath();

        $module = strtolower($uriSegments[0] ?? $this->_controllerDefault);
        $subcontroller = strtolower($uriSegments[1] ?? '');
        // $method = strtolower($uriSegments[2] ?? 'index');
        $method = $uriSegments[2] ?? 'index';

        $uriValueSliceIndex = 0; // Index from where the value segments start
        // Check if Subcontroller exists in modules
        $subcontrollerFileModules = $this->_pathController . $module . "/controller/" . $subcontroller . ".php";

        // Check if Subcontroller exists in core_modules
        $subcontrollerFileCore = str_replace(MODULES_URI, CORE_MODULES_URI, $subcontrollerFileModules);

        if ($subcontroller && file_exists($subcontrollerFileModules)) {
            // Subcontroller found in modules
            $this->_uriModule = $module;
            $this->_uriController = ucwords($module);
            $this->_uriSubController = ucwords($subcontroller);
            $this->_uriMethod = $method;
            $uriValueSliceIndex = 3;
        } elseif ($subcontroller && file_exists($subcontrollerFileCore)) {
            // Subcontroller found in core_modules
            $this->_uriModule = $module;
            $this->_uriController = ucwords($module);
            $this->_uriSubController = ucwords($subcontroller);
            $this->_uriMethod = $method;
            $uriValueSliceIndex = 3;
        } else {
            // No subcontroller, use module controller
            $this->_uriModule = $module;
            $this->_uriController = ucwords($module);
            $this->_uriMethod = $subcontroller ?: 'index';
            $uriValueSliceIndex = 2;
        }
        // 3. Slice the remaining segments as the method parameters
        $uriValue = array_slice($uriSegments, $uriValueSliceIndex);

        // 4. CRITICAL FIX: Decode ALL parameter values here once!
        // This ensures spaces (%20) and umlauts (%C3%A4) are converted to clean strings (" ", "ä")
        // before being passed to the controller method as arguments (string ...$pathParts).
        $this->_uriValue = array_map('urldecode', $uriValue);

        // Default Controller if nothing is set
        if (empty($this->_uriController)) {
            $this->_uriController = ucwords($this->_controllerDefault);
        }

        // Default Method
        if (empty($this->_uriMethod)) {
            $this->_uriMethod = 'index';
        }
    }

    /**
     * setPathRoot - Required
     *
     * @param type $path Location of the root path
     */
    public function setPathRoot($path)
    {
        $this->_pathRoot = rtrim($path, '/') . '/';

        /**
         * Set the default paths afterwards
         */
        $this->_pathController = $this->_pathRoot . MODULES_URI;
        $this->_pathModel = $this->_pathRoot . MODULES_URI;
        $this->_pathView = $this->_pathRoot . MODULES_URI;
        $this->_pathHelper = $this->_pathRoot . MODULES_URI;
        $this->_pathConfig = $this->_pathRoot . 'config/';
    }

    /**
     * setPathController - Default path is 'controller/'
     *
     * @param string $path Location for the controllers
     */
    public function setPathController($path)
    {
        $this->_pathController = $this->_pathRoot . trim($path, '/') . '/';
    }

    /**
     * setPathModel - Default path is 'model/'
     *
     * @param string $path Location for the models
     */
    public function setPathModel($path)
    {
        $this->_pathModel = $this->_pathRoot . trim($path, '/') . '/';
    }

    /**
     * setPathHelper - Default path is 'helper/'
     *
     * @param string $path Location for the helpers
     */
    public function setPathHelper($path)
    {
        $this->_pathHelper = $this->_pathRoot . trim($path, '/') . '/';
    }

    /**
     * setPathView - Default path is 'view/'
     *
     * @param string $path Location for the views
     */
    public function setPathView($path)
    {
        $this->_pathView = $this->_pathRoot . trim($path, '/') . '/';
    }

    /**
     * setControllerDefault - The default controller to load when nothing is passed
     *
     * @param string $controller Name of the controller
     */
    public function setControllerDefault($controller)
    {
        $this->_controllerDefault = strtolower($controller);
    }

    /**
     * _initUriSlashPath - Sets up the relative path length (e.g., '../')
     */
    private function _initUriSlashPath()
    {
        /** Create the "../" path for convenience */
        $this->uriSlashPath = '';

        /** The real segments (Not the overriden one) */
        $realSegments = explode('/', $this->uri);

        for ($i = 1; $i < count($realSegments); $i++) {
            $this->uriSlashPath .= '../';
        }
    }

    /**
     * _initController - Load the controller based on the URL
     *
     * This method determines whether the controller exists in the user's
     * modules folder or in core_modules, loads it, initializes paths,
     * sets up the view, and calls the requested method with parameters.
     */
    private function _initController()
    {
        $lastSegment = $this->_uriValue[array_key_last($this->_uriValue)] ?? null;

        // --- Require custom user configuration ---
        // $this->_requireCustomConfig();

        $module = rtrim(strtolower($this->_uriController), '/') . '/';
        $baseController = $this->_uriController;

        if ($this->_uriSubController)
            $this->_uriController = $this->_uriSubController;

        // --- Determine whether controller exists in modules or core_modules ---
        $controllerFile = $this->_pathController . $module . "controller/" . strtolower($this->_uriController) . '.php';
        $isCoreModule = false;

        if (!file_exists($controllerFile)) {
            // fallback to core_modules
            $controllerFile = str_replace(MODULES_URI, CORE_MODULES_URI, $controllerFile);

            if (!file_exists($controllerFile)) {
                // --- Asset Request Logging (CSS, JS, images, fonts, etc.) ---
                if ($lastSegment && preg_match('/\.(css|js|png|jpg|gif|cur|svg|ico|woff2?|ttf|eot|json|mp4|webm|webp)$/i', $lastSegment)) {
                    $logDir = dirname(__DIR__, 3) . '/var/log/';
                    $timestamp = date('Y-m-d H:i:s');

                    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
                    $traceLines = [];
                    foreach ($trace as $t) {
                        $file = $t['file'] ?? '[internal]';
                        $line = $t['line'] ?? '';
                        $func = $t['function'] ?? '';
                        $class = $t['class'] ?? '';
                        $traceLines[] = "{$file}:{$line} {$class}{$func}()";
                    }
                    $traceString = implode(" | ", $traceLines);

                    $msg = sprintf(
                            "[%s] Misrouted asset request: %s (Asset not found or misrouted) | Referrer: %s | Agent: %s | URI: %s | Trace: %s\n",
                            $timestamp,
                            $this->uri,
                            filter_input(INPUT_SERVER, 'HTTP_REFERRER', FILTER_SANITIZE_URL),
                            strip_tags((string) filter_input(INPUT_SERVER, 'HTTP_USER_AGENT')),
                            filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL),
                            $traceString
                    );
                    error_log($msg, 3, $logDir . 'bootstrap_assets.log');
                }
                if (defined('APP_DEBUG') && APP_DEBUG === true) {
                    exit(__CLASS__ . ': error (non-existent controller): ' . $this->_uriController);
                }
                error_log(__CLASS__ . ': error (non-existent controller): ' . $this->_uriController);
                exit(1);
            }
            $isCoreModule = true;
        }

        // --- Include controller ---
        require $controllerFile;

        // --- Optional module autoload ---
        $moduleAutoloadPath = $controllerFile ? dirname($controllerFile, 2) . '/' : '';
        $autoloadFile = $moduleAutoloadPath . 'modulautoload.php';
        if (file_exists($autoloadFile)) {
            require_once $autoloadFile;
        }

        $controllerClass = $this->_uriController;
        $this->controller = new $controllerClass();

        // --- Set controller paths ---
        $this->controller->pathModel = $isCoreModule ? str_replace(MODULES_URI, CORE_MODULES_URI, $this->_pathModel) : $this->_pathModel;

        $this->controller->pathHelper = $isCoreModule ? str_replace(MODULES_URI, CORE_MODULES_URI, $this->_pathHelper) : $this->_pathHelper;

        $this->controller->pathClass = $isCoreModule ? str_replace(MODULES_URI, CORE_MODULES_URI, $this->_pathController) . $module : $this->_pathController . $module;

        $this->controller->baseControllerName = strtolower($baseController);
        $this->controller->coreModulePath = $this->_pathRoot . CORE_MODULES_URI;

        // --- Initialize the view object ---
        $this->controller->view = new View(defined('CSS_JS_DEBUG') && CSS_JS_DEBUG === true);
        $this->controller->view->setPath($this->_pathView); // Module path
        $this->controller->view->setCoreModulePath($this->_pathRoot . CORE_MODULES_URI); // Core-Fallback
        // --- Call the requested method with parameters ---
        if (isset($this->_uriMethod)) {
            $method = $this->_uriMethod;
            $params = $this->_uriValue;

            // Verwenden Sie call_user_func_array für alle Parameter.
            // Dies stellt sicher, dass die Variadic-Funktion (...$pathParts) im Controller
            // das Array $params korrekt empfängt.
            if (!empty($params)) {
                call_user_func_array([$this->controller, $method], $params);
            } else {
                // Ruft die Methode ohne Argumente auf
                $this->controller->{$method}();
            }
        } else {
            $this->controller->index();
        }
    }

    private function _requireCustomConfig()
    {
        // Check if the config file does not exist
        if (!file_exists($this->_pathConfig . 'config.json')) {
            // If the config file is missing, we need to handle it.
            // We set the controller and method to the installer.
            $this->_uriController = 'Installer';
            $this->_uriMethod = 'index';
            $this->_uriValue = [];

            // Now, we need to "re-route" the internal state of the application.
            // This is a direct approach, but it changes the state before the main
            // controller initialization. A cleaner way would be to return a flag
            // and handle the logic in the init() method.
            // To make it work with the current _initController() logic,
            // we can simply exit the current method and let _initController()
            // proceed with the new URI values.
            return;
        }

        // Existing logic for when the config file exists
        // (e.g., loading config data, etc.)
    }
}
