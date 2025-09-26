<?php

namespace ckvsoft\mvc;

class Bootstrap extends \stdClass
{

    /**
     * @var string $_controllerDefault The default controller to load
     */
    private $_controllerDefault = 'index';

    /**
     * @var string $_uriController The controller to call
     */
    private $_uriController;

    /**
     * @var string $_uriSubController The controller to call
     */
    private $_uriSubController;

    /**
     * @var string $_uriMethod The method call
     */
    private $_uriMethod;

    /**
     * @var array $this->_uriValue Values beyond the controller/method
     */
    private $_uriValue = array();

    /**
     * @var string $_pathModel Where the models are located
     */
    private $_pathModel;

    /**
     * @var string $_pathModel Where the models are located
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
     * @var string $_pathController Where the controllers are located
     */
    private $_pathHelper;

    /**
     * @var object $_basePath The basepath to include files from
     */
    private $_basePath;

    /**
     * @var string $uri The URI string
     */
    public $uri;

    /**
     * @var array $uriSegments Each URI segment in an array
     */
    public $uriSegments;

    /**
     * @var string $downPath The ../ path count
     */
    public $uriSlashPath;

    /**
     * @var object $_view The view object
     */
    private $_view;

    /**
     * __construct - Get the URL and prepare the internal data
     *
     * This is prepared so a route check can happen before things are initialized
     */
    public function __construct()
    {
        $uri = filter_input(INPUT_GET, 'uri', FILTER_SANITIZE_URL);

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

        /** The order of these are important */
        $this->_initController();
    }

    /**
     * _buildComponents - Sets up the pieces for the Controller, Model, Value
     *
     * @param string $uri
     */
    private function _buildComponents($uri)
    {
        $uri = explode('/', trim($uri, '/'));
        $this->uriSegments = $uri;
        $this->_initUriSlashPath();

        $module = strtolower($uri[0] ?? $this->_controllerDefault);
        $subcontroller = strtolower($uri[1] ?? '');
        $method = strtolower($uri[2] ?? 'index');

        // Prüfen ob Subcontroller existiert
        if ($subcontroller && file_exists($this->_pathController . $module . "/controller/" . $subcontroller . ".php")) {
            $this->_uriModule = $module;
            $this->_uriController = ucwords($module);
            $this->_uriSubController = ucwords($subcontroller);
            $this->_uriMethod = $method;
            $this->_uriValue = array_splice($uri, 3);
        } else {
            // kein Subcontroller, Modulcontroller verwenden
            $this->_uriModule = $module;
            $this->_uriController = ucwords($module);
            $this->_uriMethod = $subcontroller ?: 'index';
            $this->_uriValue = array_splice($uri, 2);
        }

        // Default-Controller wenn nichts gesetzt
        if (empty($this->_uriController)) {
            $this->_uriController = ucwords($this->_controllerDefault);
        }

        // Default-Methode
        if (empty($this->_uriMethod)) {
            $this->_uriMethod = 'index';
        }
    }

    /**
     * setPathBase - Required
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
     * setPathController - Default is 'controller/'
     *
     * @param string $path Location for the controllers
     */
    public function setPathController($path)
    {
        $this->_pathController = $this->_pathRoot . trim($path, '/') . '/';
    }

    /**
     * setPathModel - Default is 'model/'
     *
     * @param string $path Location for the models
     */
    public function setPathModel($path)
    {
        $this->_pathModel = $this->_pathRoot . trim($path, '/') . '/';
    }

    /**
     * setPathHelper - Default is 'helper/'
     *
     * @param string $path Location for the models
     */
    public function setPathHelper($path)
    {
        $this->_pathHelper = $this->_pathRoot . trim($path, '/') . '/';
    }

    /**
     * setPathView - Default is 'view/'
     *
     * @param string $path Location for the models
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
     * _initUriSlashPath - Sets up the dot dot slash path length
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

        // --- Asset Request Logging (CSS, JS, images, fonts, etc.) ---
        if ($lastSegment && preg_match('/\.(css|js|png|jpg|gif|cur|svg|ico|woff2?|ttf|eot)$/i', $lastSegment)) {
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
                    "[%s] Misrouted asset request: %s | Referrer: %s | Agent: %s | URI: %s | Trace: %s\n",
                    $timestamp,
                    $this->uri,
                    filter_input(INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_URL),
                    strip_tags((string) filter_input(INPUT_SERVER, 'HTTP_USER_AGENT')),
                    filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL),
                    $traceString
            );
            error_log($msg, 3, $logDir . 'bootstrap_assets.log');
        }

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
                die(__CLASS__ . ': error (non-existent controller): ' . $this->_uriController);
            }
            $isCoreModule = true;
        }

        // --- Include controller ---
        require $controllerFile;

        // --- Optional module autoload ---
        $moduleAutoloadPath = $controllerFile ? dirname($controllerFile) . '/' : '';
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

        $this->controller->baseController = strtolower($baseController);
        $this->controller->coreModulePath = $this->_pathRoot . CORE_MODULES_URI;

        // --- Initialize the view object ---
        $this->controller->view = new View(defined('CSS_JS_DEBUG') && CSS_JS_DEBUG === true);
        $this->controller->view->setPath($this->_pathView); // Modulpfad
        $this->controller->view->setCoreModulePath($this->_pathRoot . CORE_MODULES_URI); // Core-Fallback
        // --- Call the requested method with parameters ---
        if (isset($this->_uriMethod)) {
            if (!empty($this->_uriValue)) {
                switch (count($this->_uriValue)) {
                    case 1: $this->controller->{$this->_uriMethod}($this->_uriValue[0]);
                        break;
                    case 2: $this->controller->{$this->_uriMethod}($this->_uriValue[0], $this->_uriValue[1]);
                        break;
                    case 3: $this->controller->{$this->_uriMethod}($this->_uriValue[0], $this->_uriValue[1], $this->_uriValue[2]);
                        break;
                    case 4: $this->controller->{$this->_uriMethod}($this->_uriValue[0], $this->_uriValue[1], $this->_uriValue[2], $this->_uriValue[3]);
                        break;
                    case 5: $this->controller->{$this->_uriMethod}($this->_uriValue[0], $this->_uriValue[1], $this->_uriValue[2], $this->_uriValue[3], $this->_uriValue[4]);
                        break;
                    default: call_user_func_array([$this->controller, $this->_uriMethod], $this->_uriValue);
                }
            } else {
                $this->controller->{$this->_uriMethod}();
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
