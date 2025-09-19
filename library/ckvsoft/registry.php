<?php

namespace ckvsoft;

class Registry
{

    /**
     * @var array $_record Stores records
     */
    private static $_record = array();

    /**
     * set - Places an item inside the registry record
     *
     * @param string $key The name of the item
     * @param mixed &$item The item to reference
     */
    public static function set($key, &$item)
    {
        /** This will overwrite key's with the same name */
        self::$_record[$key] = &$item;
    }

    /**
     * fetch - Gets an item out of the registry
     *
     * @param string $key The name of the stored record
     *
     * return mixed
     */
    public static function fetch($key)
    {
        if (isset(self::$_record[$key]))
            return self::$_record[$key];
        else
            return false;
    }
}
