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
        return $this->db->select('SELECT id, label, link, parent, sort, role, is_public FROM ' . $this->_table . ' WHERE id = :id', ['id' => $id]);
    }

    /**
     * Creates a menuentry based on data
     *
     * @param array $data
     * @return integer The new id
     */
    public function create($data)
    {
        try {
            $this->db->insert($this->_table, $data);
            return (int) $this->db->id();
        } catch (\ckvsoft\CkvException $e) {
            // CATCH: Intercept the exception thrown by the Database class.
            // CHECK: Look for known DB errors (e.g., Duplicate Entry, SQLSTATE '23000')
            if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
                // Return a specific, user-friendly message
                return "The menu already exists.";
            }

            // FALLBACK: Return a generic database error message
            return "Database error: User could not be created.";
        }
    }

    /**
     *
     * @param integer $id
     * @param array $data
     * @return boolean
     */
    public function update($id, $data)
    {
        return $this->db->update($this->_table, $data, "id = :id", ['id' => $id]);
    }

    /**
     *
     * @param integer $user_id
     * @return boolean
     */
    public function delete($id)
    {
        $entry = $this->_getMenuEntry($id);
        return $this->db->delete($this->_table, "id = :id", ['id' => $id]);
    }

    /**
     * Grabs information about a particular menuentry
     *
     * @param integer $id
     * @return boolean|array
     */
    private function _getMenuEntry($id)
    {
        $result = $this->db->select("SELECT * FROM " . $this->_table . " WHERE   id = :id", ['id' => $id]);

        if (!empty($result)) {
            return $result[0];
        } else {
            return false;
        }
    }
}
