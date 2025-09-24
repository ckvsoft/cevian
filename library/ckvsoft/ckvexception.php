<?php

namespace ckvsoft;

class CkvException extends \Exception
{

    protected $message;
    private $traceback = "";

    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->message = $message;
        $trace = debug_backtrace();
        foreach ($trace as $item) {
            if (isset($item['file']) && isset($item['line'])) {
                $this->traceback .= sprintf("in %s:%d\n", $item['file'], $item['line']);
            }
            if (isset($item['class']) && isset($item['function'])) {
                $this->traceback .= sprintf("  at %s::%s()\n", $item['class'], $item['function']);
            }
        }

        $this->log();
    }

    public function log()
    {
        $timestamp = date('Y-m-d H:i:s');

        $logMessage = sprintf(
                "[%s] %s trace %s",
                $timestamp,
                $this->message,
                $this->traceback
        );

        error_log($logMessage);

        if (ini_get('display_errors') && !defined('ERROR_DISPLAYED')) {
            define('ERROR_DISPLAYED', true);
            echo $this->formatError();
        }
    }

    private function formatError()
    {
        // Format the error as an HTML page with a backtrace
        $html = '<html>';
        $html .= '<head><title>Error</title></head>';
        $html .= '<body>';
        $html .= '<h1>Error</h1>';
        $html .= '<p><strong>Message:</strong> ' . $this->message . '</p>';
        $html .= '<h2>Backtrace:</h2>';
        $html .= '<pre>';
        $html .= print_r($this->traceback, true);
        $html .= '</pre>';
        $html .= '</body>';
        $html .= '</html>';

        return $html;
    }
}

/*
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (error_reporting()) {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
});
*/