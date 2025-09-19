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

    /**
     *
     * @param string $model
     * @return \ckvsoft\MVC\model
     */
    /*
      public function loadModel($model)
      {
      return \ModelLoader_helper::loadModel($this->pathModel, $this->pathClass , $model);
      }
     */

    /*
      public function loadModel($model, $module = null)
      {
      if ($module === null) {
      // Aktuelles Modul aus pathClass ableiten
      $parts = explode('/', rtrim($this->pathClass, '/'));
      $module = end($parts);
      }

      $modelFile = $this->pathModel . $module . '/model/' . $model . '_model.php';
      if (!file_exists($modelFile)) {
      throw new \Exception("Model file not found: $modelFile");
      }

      require_once $modelFile;
      $modelClass = $model . '_Model';
      return new $modelClass();
      }
     * 
     */

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

    public function loadHelper($helper, $params = [])
    {
        try {
            if (strpos($helper, '/') !== false) {
                $helper_parts = explode('/', $helper);
                $module_name = $helper_parts[0];
                $helper_file = $helper_parts[1] . '_helper.php';
                $helper_path = $this->pathHelper . $module_name . '/helper/' . $helper_file;
                require_once($helper_path);

                $helper_name = $helper_parts[1] . '_helper';
            } else {
                // Framework-Helper: Namespace + _helper anhängen
                $helper_name = 'ckvsoft\\helper\\' . $helper . '_helper';
            }

            $helperObject = new $helper_name($this->baseController);

            if (isset($params['method']) && is_callable([$helperObject, $params['method']])) {
                return call_user_func_array([$helperObject, $params['method']], $params['args']);
            }

            return $helperObject;
        } catch (\Exception $e) {
            throw new ckvsoft\CkvException($e->getMessage());
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
     * or from the general 'modules' directory.
     *
     * Usage:
     * 1. Relative path (within current module view):
     *      loadScript("js/script.js") 
     *      -> modules/<current_module>/view/js/script.js
     *
     * 2. Absolute path (within general modules folder):
     *      loadScript("/inc/js/another_script.js") 
     *      -> modules/inc/js/another_script.js
     *
     * @param string $script Path to the script, relative or starting with '/' for modules root
     * @return string The content of the script file
     * @throws \ckvsoft\CkvException if the file does not exist
     */
    public function loadScript(string $script)
    {
        if (substr($script, 0, 1) === '/') {
            // Absolute path in general modules folder
            $fullpath = rtrim($this->pathModel, '/') . '/' . $script;
        } else {
            // Relative path in current module view folder
            $fullpath = rtrim($this->pathClass, '/') . '/view/' . $script;
        }

        // Normalize double slashes
        $fullpath = preg_replace('#/+#', '/', $fullpath);

        if (file_exists($fullpath)) {
            return file_get_contents($fullpath);
        }

        throw new \ckvsoft\CkvException("Script not found: $fullpath");
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
