<?php

namespace ckvsoft;

class Output
{

    /**
     * json - Standard JSON Output
     *
     * @param mixed $data Content to encode
     */
    public static function json($data)
    {
        header('Content-type: application/json');
        echo json_encode($data);
    }

    /**
     * json - Standard JSON Output
     *
     * @param mixed $data Content to encode
     */
    public static function base64json($data)
    {
        header('Content-type: application/json');
        echo base64_encode(json_encode($data));
    }

    /**
     * success - Pre-Packaged JSON Output (success, errorMessage, data)
     *
     * @param string $data Content to encode
     */
    public static function success($data = null)
    {
        header('Content-type: application/json');
        echo json_encode(array('success' => 1, 'errorMessage' => null, 'data' => $data));
    }

    /**
     * error - Pre-Packaged JSON Output (success, errorMessage, data)
     *
     * @param string $errorMessage
     */
    public static function error($errorMessage = null)
    {
        header('Content-type: application/json');
        echo json_encode(array('success' => 0, 'errorMessage' => $errorMessage, 'data' => null));
    }
}
