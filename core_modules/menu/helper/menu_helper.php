<?php

/**
 * Description of menuhelper
 *
 * @author chris
 */
use ckvsoft\Auth;

class Menu_Helper extends \ckvsoft\mvc\Helper
{

    private $_menu;
    private $_table = 'mainmenu';

    public function getMenu($parentid, $myseclevel = 10, $is_mobile = false)
    {
        $tmpmyseclevel = $myseclevel + 1;
        $role = '';
        $enc = \ckvsoft\Hash::create('sha256', 'admin', HASH_KEY);
        if (isset($_SESSION['user_role'])) {
            if ($_SESSION['user_role'] == $enc)
                $role = "admin";
        }

        $result = "";
        if (Auth::getUserId() > 0)
            $result = $this->db->select("Select * from " . $this->_table . " where parent=:parent and (role <> 'none' and (role='owner' or role=:role or is_public=1)) order by sort ASC, is_public DESC", ['parent' => $parentid, 'role' => $role]);
        else
            $result = $this->db->select("Select * from " . $this->_table . " where parent=:parent and is_public=1 and (role='none' or role='owner') order by sort ASC, is_public DESC", ['parent' => $parentid]);

        if (empty($result)) {
            return "";
        }

        $this->_menu .= ($parentid > 0) ? "<ul id= 'menu_{$tmpmyseclevel}' class='subnav'>" : "<ul id='menu_{$tmpmyseclevel}' class='topnav'>";
        foreach ($result as $value) {
            $tmpparent = $value['id'];

            if ($is_mobile && $value['link'] == '#') {
                $this->getMenu($tmpparent, $tmpmyseclevel, $is_mobile);
                continue;
            }
            $this->_menu .= ($value['link'] == '#') ? "<li><a>" . $value['label'] . "</a>" : "<li><a href=\"" . BASE_URI . $value['link'] . "\">" . $value['label'] . "</a>";
            $this->getMenu($tmpparent, $tmpmyseclevel);
            $this->_menu .= "</li>";
        }
        $this->_menu .= "</ul>";
        return $this->_menu;
    }
}
