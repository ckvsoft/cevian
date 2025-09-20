<?php

use ckvsoft\mvc\Model;

class Menu_Model extends Model
{

    private $_table = 'mainmenu';
    private $_menu;

    public function __construct()
    {
        parent::__construct();
    }

    public function menuList()
    {
        return $this->db->select('SELECT id, label, link, parent, sort, role, is_public  FROM ' . $this->_table);
    }

    public function generateMenuArray($parentId)
    {
        $result = $this->db->select("Select * from " . $this->_table . " where parent=:parent", ['parent' => intval($parentId)]);

        if (empty($result)) {
            return [];
        }

        $menu = [];
        foreach ($result as $value) {
            $submenu = $this->generateMenuArray($value['id']);
            if (!empty($submenu)) {
                $menu[] = [
                    'id' => $value['id'],
                    'label' => $value['label'],
                    'parent' => $value['parent'],
                    'is_public' => $value['is_public'],
                    'submenu' => $submenu
                ];
            } else {
                $menu[] = [
                    'id' => $value['id'],
                    'label' => $value['label'],
                    'parent' => $value['parent'],
                    'is_public' => $value['is_public']
                ];
            }
        }

        return $menu;
    }

    public function menuSingleList($id)
    {
        return $this->db->select('SELECT id, label, link, parent, sort, role, is_public FROM ' . $this->_table . ' WHERE id = :id', array('id' => $id));
    }

    /**
     * Creates a menuentry based on data
     *
     * @param array $data
     * @return integer The new id
     */
    public function create($data)
    {
        $this->db->insert($this->_table, $data);
        return $this->db->id();
    }

    /**
     *
     * @param integer $id
     * @param array $data
     * @return boolean
     */
    public function update($id, $data)
    {
        return $this->db->update($this->_table, $data, "id = :id", array('id' => $id));
    }

    /**
     *
     * @param integer $user_id
     * @return boolean
     */
    public function delete($id)
    {
        $entry = $this->_getMenuEntry($id);
        return $this->db->delete($this->_table, "id = :id", array('id' => $id));
    }

    /**
     * Grabs information about a particular menuentry
     *
     * @param integer $id
     * @return boolean|array
     */
    private function _getMenuEntry($id)
    {
        $result = $this->db->select("SELECT * FROM " . $this->_table . " WHERE   id = :id", array('id' => $id));

        if (!empty($result)) {
            return $result[0];
        } else {
            return false;
        }
    }
}
