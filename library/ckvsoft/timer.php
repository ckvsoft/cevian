<?php

namespace ckvsoft;

class Timer
{

    /**
     * @var array $_timer Collection of timers
     */
    private static $_timer = array();

    /**
     * start - Start a timer
     *
     * @param string $id The id of the timer to start
     */
    public static function start($id)
    {
        if (isset(self::$_timer[$id]))
            throw new \ckvsoft\CkvException("Timer already set: $id");

        self::$_timer[$id] = microtime();
    }

    /**
     * stop - Stop a timer
     *
     * @param string $id The id of the timer to stop
     */
    public static function stop($id)
    {
        return microtime() - self::$_timer[$id] / 1000;
    }
}
