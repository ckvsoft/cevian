<?php

namespace ckvsoft\mvc;

class Controller extends \stdClass
{

    /** @var object $view Set from the bootstrap */
    public $view;

    /** @var string $pathModel Reusable path declared from the bootstrap */
    public $pathModel;
    public $pathHelper;
    public $pathRoot;
    public $pathClass;
    public $mobile = false;
    public $baseController;
    public $modulePath;
    public $coreModulePath;

    /**
     * __construct - Required
     */
    public function __construct()
    {
        $user_agent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_UNSAFE_RAW);
        $user_agent = $user_agent !== null ? strip_tags($user_agent) : '';
        if (strpos($user_agent, 'Mobile') !== false)
            $this->mobile = true;
    }

    public function loadModel($model, $module = null, ...$params)
    {
        // Modul ableiten, wenn null
        if ($module === null) {
            $parts = explode('/', rtrim($this->pathClass, '/'));
            $module = end($parts);
        }

        $modelFile = rtrim($this->pathModel, '/') . '/' . $module . '/model/' . $model . '_model.php';
        if (!file_exists($modelFile)) {
            throw new \Exception("Model file not found: $modelFile");
        }

        require_once $modelFile;

        $modelClass = $model . '_model';
        if (!class_exists($modelClass)) {
            throw new \Exception("Model class $modelClass does not exist in $modelFile");
        }

        return new $modelClass(...$params);
    }

    /**
     * loadHelper - Load a helper from module folder or core_modules fallback
     *
     * @param string $helper Helper name, either "module/helper" or just core helper name
     * @param array $params Optional parameters for method call
     * @return object|mixed The helper object or the method call result
     * @throws \ckvsoft\CkvException if helper cannot be found
     */
    public function loadHelper($helper, $params = [])
    {
        try {
            $helperFile = '';
            $helperClass = '';

            if (strpos($helper, '/') !== false) {
                // Module-specific helper requested
                [$moduleName, $helperName] = explode('/', $helper);

                // Look first in module folder
                $helperFile = $this->pathHelper . $moduleName . '/helper/' . $helperName . '_helper.php';
                if (!file_exists($helperFile)) {
                    // fallback to core_modules
                    $helperFile = str_replace(MODULES_URI, CORE_MODULES_URI, $helperFile);
                    if (!file_exists($helperFile)) {
                        throw new \Exception("Helper file not found in module or core_modules: $helper");
                    }
                }

                require_once($helperFile);
                $helperClass = $helperName . '_helper';
            } else {
                // Core helper only: namespace ckvsoft\helper
                $helperClass = 'ckvsoft\\helper\\' . $helper . '_helper';
                if (!class_exists($helperClass)) {
                    throw new \Exception("Core helper class not found: $helperClass");
                }
            }

            // Instantiate helper
            $helperObject = new $helperClass($this->baseController);

            // Call method directly if provided
            if (isset($params['method']) && is_callable([$helperObject, $params['method']])) {
                return call_user_func_array([$helperObject, $params['method']], $params['args'] ?? []);
            }

            return $helperObject;
        } catch (\Exception $e) {
            throw new \ckvsoft\CkvException($e->getMessage());
        }
    }

    public function getRoot()
    {
        return $this->pathRoot;
    }

    /**
     * location - Shortcut for a page redirect
     *
     * @param string $url
     */
    public function location($url)
    {
        header("location: $url");
        exit(0);
    }

    /**
     * Load a JavaScript file either from the current module's view folder 
     * or from the general 'modules' directory, with fallback to core_modules.
     *
     * Usage:
     * 1. Relative path (within current module view):
     *      loadScript("js/script.js") 
     *      -> modules/<current_module>/view/js/script.js
     *
     * 2. Absolute path (starting with '/') for modules root:
     *      loadScript("/inc/js/another_script.js") 
     *      -> modules/inc/js/another_script.js
     *
     * @param string $script Path to the script, relative or starting with '/' for modules root
     * @return string The content of the script file
     * @throws \ckvsoft\CkvException if the file does not exist in both module and core_modules
     */
    public function loadScript(string $script)
    {
        // absoluter Basis-Pfad = Arbeitsverzeichnis + BASE_URI
        // $baseFs = rtrim(getcwd(), '/') . '/' . ltrim(BASE_URI, '/');
        $baseFs = rtrim(getcwd(), '/') . '/';


        $paths = [];

        if ($script[0] === '/') {
            // Absoluter Modulpfad → Module + Core
            $scriptRel = ltrim($script, '/');
            $paths = [
                $baseFs . trim(MODULES_URI, '/') . '/' . $scriptRel,
                $baseFs . trim(CORE_MODULES_URI, '/') . '/' . $scriptRel,
            ];
        } else {
            // Relativer Pfad → Modulname ermitteln
            $parts = explode('/', trim($this->pathClass, '/'));
            $module = $parts[0] ?? '';

            $paths = [
                $baseFs . trim(MODULES_URI, '/') . '/' . $module . '/view/' . $script,
                $baseFs . trim(CORE_MODULES_URI, '/') . '/' . $module . '/view/' . $script,
            ];
        }

        foreach ($paths as $fullpath) {
            $fullpath = preg_replace('#/+#', '/', $fullpath);
            if (file_exists($fullpath)) {
                return file_get_contents($fullpath);
            }
        }

        throw new \ckvsoft\CkvException("Script not found: " . implode(" | ", $paths));
    }

    /**
     * __call - Error Catcher
     *
     * @param string $name
     * @param string $arg
     */
    public function __call($name, $arg)
    {
        die("<div>Controller Error: (Method) <b>$name</b> is not defined</div>");
    }
}
