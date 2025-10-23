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

    /**
     * Retrieves a list of all menu entries.
     * @return array
     */
    public function menuList()
    {
        return $this->db->select('SELECT id, label, link, parent, sort, role, is_public FROM ' . $this->_table);
    }

    /**
     * Recursively generates a nested array structure for the menu.
     *
     * @param int $parentId The ID of the parent menu item.
     * @return array
     */
    public function generateMenuArray($parentId)
    {
        $result = $this->db->select("Select * from " . $this->_table . " where parent=:parent", ['parent' => intval($parentId)]);

        if (empty($result)) {
            return [];
        }

        $menu = [];
        foreach ($result as $value) {
            // Recursively fetch submenus
            $submenu = $this->generateMenuArray($value['id']);

            // Structure the menu entry based on the existence of a submenu
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

    /**
     * Retrieves a single menu entry by its ID.
     *
     * @param int $id The menu ID.
     * @return array
     */
    public function menuSingleList($id)
    {
        return $this->db->select('SELECT id, label, link, parent, sort, role, is_public FROM ' . $this->_table . ' WHERE id = :id', ['id' => $id]);
    }

    /**
     * Creates a menu entry based on provided data.
     *
     * @param array $data The data to insert.
     * @return integer|string The new ID on success, or an error message string on failure.
     */
    public function create($data)
    {
        try {
            $this->db->insert($this->_table, $data);
            return (int) $this->db->id();
        } catch (\ckvsoft\CkvException $e) {
            // Intercept the exception thrown by the Database class.
            // Check for known DB errors (e.g., Duplicate Entry, SQLSTATE '23000')
            if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
                // Return a specific, user-friendly message for translation
                return _("The menu already exists.");
            }

            // Fallback: Return a generic database error message for translation
            return _("Database error: Menu entry could not be created.");
        }
    }

    /**
     * Updates an existing menu entry.
     *
     * @param int $id The ID of the menu entry to update.
     * @param array $data The data to update.
     * @return boolean True on successful update, false otherwise.
     */
    public function update($id, $data)
    {
        return $this->db->update($this->_table, $data, "id = :id", ['id' => $id]);
    }

    /**
     * Deletes a menu entry by ID.
     *
     * @param int $id The ID of the menu entry to delete.
     * @return boolean True on successful delete, false otherwise.
     */
    public function delete($id)
    {
        // NOTE: The private method call _getMenuEntry($id) is currently unused but kept for context.
        // $entry = $this->_getMenuEntry($id);
        return $this->db->delete($this->_table, "id = :id", ['id' => $id]);
    }

    /**
     * Grabs information about a particular menu entry.
     *
     * @param int $id The ID of the menu entry.
     * @return boolean|array The menu entry data array, or false if not found.
     */
    private function _getMenuEntry($id)
    {
        $result = $this->db->select("SELECT * FROM " . $this->_table . " WHERE id = :id", ['id' => $id]);

        if (!empty($result)) {
            return $result[0];
        } else {
            return false;
        }
    }
}
