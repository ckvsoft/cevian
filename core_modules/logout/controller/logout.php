<?php

class Logout extends \ckvsoft\mvc\Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        // 1. MultiLoginManager Logout
        \ckvsoft\MultiLoginManager::logout('ckvsoft');

        // 2. PHP-Session zurücksetzen
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        // 3. Zurück zur Startseite
        header('Location: ' . BASE_URI);
        exit;
    }
}
