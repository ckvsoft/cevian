<?php

class Dashboard extends ckvsoft\mvc\Controller
{

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isNotLogged();
    }

    /**
     * Display those views!
     */
    public function index()
    {
        $menuhelper = $this->loadHelper("menu/menu");
        $this->view->render('dashboard/inc/header', ['menuitems' => $menuhelper->getMenu(0)]);
        $this->view->render('dashboard/dashboard');
        $this->view->render('dashboard/inc/footer');
    }
}
