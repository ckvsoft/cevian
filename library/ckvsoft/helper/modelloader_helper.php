<?php

/**
 * Description of modelloader_helper
 *
 * @author chris
 */
class ModelLoader_Helper extends \ckvsoft\mvc\Helper
{

    /**
     *
     * @param string $model
     * @return \ckvsoft\MVC\model
     */
    public static function loadModel($path, $class, $model)
    {
        $path = $path . $model . "/model/";
        $model = $model . '_model';

        require_once($path . $model . '.php');

        $args = func_get_args();
        array_shift($args); // das erste Argument entfernen

        if (count($args) > 0) {
            return new $model(...$args);
        } else {
            return new $model();
        }
    }
}
