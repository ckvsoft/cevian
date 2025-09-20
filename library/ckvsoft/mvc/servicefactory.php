<?php

namespace ckvsoft\mvc;

class ServiceFactory
{

    public static function createService($moduleName, $serviceName)
    {
        $serviceFileModule = MODULES_DIR . $moduleName . '/services/' . $serviceName . '_service.php';
        $serviceFileCore = CORE_MODULES_DIR . $moduleName . '/services/' . $serviceName . '_service.php';

        if (file_exists($serviceFileModule)) {
            require_once $serviceFileModule;
        } elseif (file_exists($serviceFileCore)) {
            require_once $serviceFileCore;
        } else {
            throw new \Exception("Service '{$serviceName}_service' not found in module '{$moduleName}' or core_modules.");
        }

        $className = $serviceName . '_service';
        return new $className();
    }

    public static function listModules()
    {
        $modulesPath = MODULES_DIR;
        $modules = array_diff(scandir($modulesPath), array('..', '.'));

        return $modules;
    }

    public static function listCoreModules()
    {
        $modulesPath = CORE_MODULES_DIR;
        $modules = array_diff(scandir($modulesPath), array('..', '.'));

        return $modules;
    }
}
