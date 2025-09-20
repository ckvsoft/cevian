<?php

class Rbac_Model extends \ckvsoft\mvc\Model
{

    private $_table_role = 'roles';
    private $rbac;

    public function __construct()
    {
        parent::__construct();
        $this->rbac = new \ckvsoft\ACL();
    }

    public function getAllRoles($format = 'ids') /* ids / full */
    {
        return $this->rbac->getAllRoles($format);
    }

    public function getAllPerms($format = 'ids') /* ids / full */
    {
        return $this->rbac->getAllPerms($format);
    }

}
