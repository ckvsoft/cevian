<?php

namespace ckvsoft\mvc;

/**
 * Description of servicefactory
 *
 * @author chris
 */
class ServiceFactory
{

    public function __construct()
    {
        
    }

    public static function createService($moduleName, $serviceName)
    {
        $servicePath = MODULES_URI . $moduleName . '/services/';
        $serviceClass = $serviceName . '_service';
        $serviceFile = $servicePath . $serviceClass . '.php';

        if (file_exists($serviceFile)) {
            require_once $serviceFile;

            return new $serviceClass();
        }

        throw new \ckvsoft\CkvException('Service ' . $serviceClass . ' not found');
    }

    public static function listModules()
    {
        $modulesPath = MODULES_DIR;
        $modules = array_diff(scandir($modulesPath), array('..', '.'));

        return $modules;
    }
}
