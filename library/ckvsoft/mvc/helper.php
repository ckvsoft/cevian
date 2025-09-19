<?php

namespace ckvsoft\mvc;

/**
 * Description of helper
 *
 * @author chris
 */
class Helper extends \ckvsoft\mvc\Config
{

    protected $baseController;

    public function __construct($baseController)
    {
        parent::__construct();
        $this->baseController = $baseController;
    }
}
