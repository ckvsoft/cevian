<?php

namespace ckvsoft;

class cmd
{

    /**
     * interval - Run an interval repeatedly
     *
     * @param function $function The anonymous function to run
     * @param interval $seconds How often to run
     * @param interval $run How many times to run (Default: Infinite)
     */
    public static function interval($function, $seconds = 1, $run = null)
    {
        self::_isCallable($function);

        /** Run Command  */
        $i = 0;
        while (true) {
            /** Call the function */
            call_user_func($function);

            /** If run is limited, stop the loop */
            if ($run === $i) {
                break;
            }

            $i++;
            sleep($seconds);
        }
    }

    /**
     * timeout - Set a timeout
     *
     * @param interval $seconds How often to run
     * @param interval $run How many times to run (Default: Infinite)
     */
    public static function timeout($function, $seconds)
    {
        self::_isCallable($function);

        /** Timeout */
        sleep($seconds);

        /** Run Command  */
        call_user_func($function);
    }

    /**
     * _isCallable - Is this function callable
     *
     * @param function $function Test if this is a real function
     *
     * @throws \ckvsoft\CkvException
     */
    public static function _isCallable($function)
    {
        if (!is_callable($function)) {
            throw new \ckvsoft\CkvException('Your first argument must be a function');
        }
    }
}
