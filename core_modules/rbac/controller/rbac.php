<?php

class Rbac extends ckvsoft\mvc\Controller
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
        $this->view->render('dashboard/inc/header', ['menuitems' => $menuhelper->getMenu(0, 10)]);
        $this->view->render('rbac/index', ['roles' => $this->getAllRoles()]);
        $this->view->render('dashboard/inc/footer');
    }

    public function getAllRoles()
    {
        $model = $this->loadModel('rbac');
        return $model->getAllRoles("full");
    }

    public function perms()
    {

        $menuhelper = $this->loadHelper("menu/menu");
        $script = $this->loadScript("js/perms.js");
        $this->view->title = 'Permissions';
        $this->view->render('dashboard/inc/header', ['menuitems' => $menuhelper->getMenu(0), 'script' => $script]);
        $this->view->render('rbac/perms');
        $this->view->render('dashboard/inc/footer');
    }

    public function getAllPerms()
    {
        $model = $this->loadModel('rbac');
        $perms = $model->getAllPerms("full");
        if (count($perms) < 1) {
            return "No permissions yet.";
        }

        $perms_html = "<table><tr><th>id</th><th>Name</th><th>Description</th><th></th><th></th></tr>";

        foreach ($perms as $v) {
            $perms_html .= '<tr><td>' . $v['id'] . '</td><td>' . $v['Name'] . '</td><td>' . $v['Description'] . '</td> <td> <a href="' . BASE_URI . 'rbac/editperms/' . $v['id'] . '">Edit</a> ';
            $perms_html .= '<a href="' . BASE_URI . 'rbac/deleteperm/' . $v['id'] . '">Delete</a>';
            $perms_html .= '</td></tr>';
        }
        $perms_html .= '</table>';

        echo $perms_html;
    }
}
