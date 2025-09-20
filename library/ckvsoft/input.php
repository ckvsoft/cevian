<?php

namespace ckvsoft;

class Input
{

    /**
     * @var object $_format The formatting object. Only instantiated if the method is called.
     */
    private $_format = false;

    /**
     * @var object $_validate The validation object. Only instantiated if the method is called.
     */
    private $_validate = false;

    /**
     * @var array $_inputData Holds the POSTED data inside the object for post-processing
     */
    private $_inputData = array();

    /**
     * @var array $_errorData Holds the VALIDATION errors
     */
    private $_errorData = array();

    /**
     * @var string $_currentRecord Holds the immediate record being handled (To chain validation on the spot)
     */
    private $_currentRecord = null;

    /**
     * @var mixed $_mimicPost Used for passing artificial $_POST requests
     */
    private $_mimicPost = null;

    /**
     * @var string $_mode Sets the mode to POST, GET, or REQUEST
     */
    private $_mode = 'POST';

    /**
     * @var boolean $_storeSession Stores items in an empty session
     */
    private $_storeSession = true;

    /**
     * __construct - Instanatiates the Validate object
     *
     * @param mixed $mimicPost (Optional) Pass an associative array matching the form->post() names to mimic a POST
     */
    public function __construct($mimicPost = null)
    {
        $this->_mimicPost = $mimicPost;
        $this->storeSession(true);
    }

    /**
     * useTempSession - Store data in a $_SESSION variable titled $_SESSION[tmp][data]
     *
     * @param boolean $boolean
     */
    public function storeSession($boolean = true)
    {
        $this->_storeSession = (boolean) $boolean;
    }

    /**
     * post - Retrieves $_POST data and saves it to the object
     *
     * @param string $name The name of the field to post
     * @param string $required_or_checkbox (Default = false) true/false/checkbox When set to true && the value is NULL: Unset the value internally and do validate.
     */
    public function post($name, $required_or_checkbox = false)
    {
        $this->_mode = 'POST';
        return $this->_handle_input($name, $required_or_checkbox);
    }

    /**
     * get - Retrieves $_GET data and saves it to the object
     *
     * @param string $name The name of the item to get
     * @param string $required (Default = false) When set to true && the value is NULL: Unset the value internally and do validate.
     */
    public function get($name, $required = false)
    {
        $this->_mode = 'GET';
        return $this->_handle_input($name, $required);
    }

    /**
     * request - Handles the $_REQUEST data
     */
    public function request($name, $required = false)
    {
        $this->_mode = 'REQUEST';
        return $this->_handle_input($name, $required);
    }

    /**
     * The master method which handles a POST/GET/REQUEST
     *
     * @param string $name The name of the item to get
     * @param boolean $required Is this field required?
     *
     * @return \ckvsoft\Input
     * @throws \Exception
     */
    private function _handle_input($name, $required)
    {
        $input = null;

        // Use mimic post if available
        if (is_array($this->_mimicPost) && array_key_exists($name, $this->_mimicPost)) {
            $input = $this->_mimicPost[$name];
        } else {
            switch ($this->_mode) {
                case 'POST':
                    if ($required === 'checkbox') {
                        $input = filter_input(INPUT_POST, $name, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        $input = $input ? '1' : '0';
                    } else {
                        $input = filter_input(INPUT_POST, $name, FILTER_DEFAULT);
                    }
                    break;

                case 'GET':
                    $input = filter_input(INPUT_GET, $name, FILTER_DEFAULT);
                    break;

                case 'REQUEST':
                    // $_REQUEST cannot be fully replaced by filter_input
                    $input = filter_input(INPUT_POST, $name, FILTER_DEFAULT);
                    if ($input === null) {
                        $input = filter_input(INPUT_GET, $name, FILTER_DEFAULT);
                    }
                    break;
            }
        }

        // Skip optional null values
        if ($required == false && $input === null) {
            $this->_currentRecord = null;
            return $this;
        }

        // Required field missing
        if ($required === true && $input === null) {
            $this->_errorData[$name] = 'is required';
        }

        // Store the value internally
        $this->_inputData[$name] = $input;

        // Keep a reference for chaining
        $this->_currentRecord['key'] = &$name;
        $this->_currentRecord['value'] = &$this->_inputData[$name];

        return $this;
    }

    /**
     * set - Set an internal record manually
     *
     * @param string $name
     * @param string $value
     * @return boolean
     */
    public function set($name, $value)
    {
        /** I want this to override stuff */
        $this->_inputData[$name] = $value;

        /**
         * Hold on to the immediate record incase validation is called next
         */
        $this->_currentRecord['key'] = &$name;
        $this->_currentRecord['value'] = &$value;

        return $this;
    }

    /**
     * format - Format the Input contents internally
     *
     * @param string $type The name of a function such as md5, trim, etc
     * @param mixed $param Additional parameters for formatting
     */
    public function format($type, $param = null)
    {
        /** Instantiate the format class only if it's used */
        if ($this->_format == false)
            $this->_format = new Input\Format();

        /** Prevent nulls from creating blank arrays */
        if ($this->_currentRecord == null)
            return $this;

        $key = $this->_currentRecord['key'];

        if ($param == null)
            $this->_inputData[$key] = $this->_format->{$type}($this->_currentRecord['value']);
        else
            $this->_inputData[$key] = $this->_format->{$type}($this->_currentRecord['value'], $param);

        return $this;
    }

    /**
     * validate - Validates the current POST item
     *
     * @param string $action
     * @param array $param If validating length, do .. ->validate('length', array(1, 4));
     * @param mixed $option If validating matchany lowercase, do .. ->validate('matchany', array('Jesse', 'Joe'), false);
     */
    public function validate($action, $param = array(), $option = null)
    {
        /**
         * From the "post() method" if this is null then then this is not required.
         */
        if ($this->_currentRecord == null)
            return $this;

        /** Instantiate the validate class only if it's used */
        if ($this->_validate == false)
            $this->_validate = new Input\Validate();

        $key = $this->_currentRecord['key'];
        $value = $this->_currentRecord['value'];

        /** Make sure the option absolutely is null to skip an option */
        if ($option === null) {
            $validateStatus = $this->_validate->{$action}($value, $param);
        } else {
            $validateStatus = $this->_validate->{$action}($value, $param, $option);
        }

        if ($validateStatus == true)
            $this->_errorData[$key] = $validateStatus;

        return $this;
    }

    /**
     * error - Set a custom error message immediately after calling validate()
     *
     * @param string $msg The text to display if an error fires
     *
     * @return object
     */
    public function error($msg)
    {
        $key = $this->_currentRecord['key'];
        if (isset($this->_errorData[$key])) {
            $this->_errorData[$key] = $msg;
        }

        return $this;
    }

    /**
     * setError - Set a custom error message for any field at anytime
     * This will prevent me from throwing an exception and not having the other errors.
     *
     * @param string $name Name of the field
     * @param string $msg Error Message
     *
     * @return object
     */
    public function setError($name, $msg)
    {
        $this->_errorData[$name] = $msg;
        return $this;
    }

    /**
     * submit - Processes the entire form and gather errors if any exist
     *
     * @param boolean $preserveTemp Keep the previous post data inside a Session
     *
     * @return mixed False for no errors, True (With data) for errors.
     */
    public function submit()
    {
        if (session_id() != '' && $this->_storeSession == true) {
            $_SESSION['tmp']['input'] = $this->_inputData;
        }

        /*
          if (count($this->_errorData) > 0) {
          throw new \ckvsoft\CkvException("There are errors in the form. Please wrap the form in a try/catch and call \$form->fetchErrors() in the catch.\n" . implode(", ", $this->_errorData));
          }
         * 
         */
    }

    /**
     * fetchErrors - Returns the errors
     *
     * @return array
     */
    public function fetchErrors()
    {
        return $this->_errorData;
    }

    /**
     * fetch - Return a value from the POSTED records stored internally
     *
     * @param string $key (Optional) Returns a specific value
     * @return mixed Either a string or all items
     */
    public function fetch($key = null)
    {
        if ($key != null) {
            if (isset($this->_inputData[$key]))
                return $this->_inputData[$key];
            else
                return false;
        } else {
            /**
             * Make sure no empty items get placed
             */
            return array_filter($this->_inputData, 'strlen');
        }
    }

    /**
     * remove - Remove an internal record
     *
     * @param string(s) Pass as many function arguments as needed to unset
     *          eg: form->remove('field', 'field2', 'field3');
     *
     * @return object
     */
    public function remove($unlimited)
    {
        foreach (func_get_args() as $key => $value) {
            if (isset($this->_inputData[$value]))
                unset($this->_inputData[$value]);
        }

        return $this;
    }
}
