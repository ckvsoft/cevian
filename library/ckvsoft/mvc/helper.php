<?php

namespace ckvsoft\mvc;

/**
 * Description of helper
 *
 * @author chris
 */
class Helper extends \ckvsoft\mvc\Config
{

    protected $baseControllerName;

    public function __construct($baseControllerName)
    {
        parent::__construct();
        $this->baseControllerName = $baseControllerName;
    }
}
