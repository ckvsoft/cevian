<?php

class Logout extends \ckvsoft\mvc\BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        \ckvsoft\MultiLoginManager::logout('ckvsoft');

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        $this->renderPage([
            ['view' => '/inc/header'],
            ['view' => 'logout/index'],
            ['view' => '/inc/footer'],
        ]);
    }
}
