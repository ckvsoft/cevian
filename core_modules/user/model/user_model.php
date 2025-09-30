<?php

class User_Model extends \ckvsoft\mvc\Model
{

    private $_table = 'user';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Attempt to log the user in.
     *
     * @param   array   $data   From the Input class returned array
     * @return  array|false Result set on success, false on failure.
     */
    public function login($data)
    {
        $result = $this->db->select("
            SELECT  user_id, role
            FROM    user
            WHERE   email = :email
            AND     password = :password
        ", array(
            'email' => $data['email'],
            'password' => $data['password']
        ));

        if (!empty($result)) {
            return $result;
        }

        return false;
    }

    public function userList()
    {
        return $this->db->select('SELECT user_id, email, role FROM user');
    }

    public function userSingleList($userid)
    {
        return $this->db->select('SELECT user_id, email, role FROM user WHERE user_id = :user_id', array('user_id' => $userid));
    }

    /**
     * Creates a user based on data.
     *
     * @param array $data
     * @return integer|string The new user_id on success, or a string error message on failure.
     */
    public function create($data)
    {
        try {
            $this->db->insert($this->_table, $data);
            return $this->db->id();
        } catch (\ckvsoft\CkvException $e) {
            // CATCH: Intercept the exception thrown by the Database class.
            // CHECK: Look for known DB errors (e.g., Duplicate Entry, SQLSTATE '23000')
            if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
                // Return a specific, user-friendly message
                return "The email address already exists.";
            }

            // FALLBACK: Return a generic database error message
            return "Database error: User could not be created.";
        }
    }

    /**
     * Update a user based on user_id.
     *
     * @param integer $user_id
     * @param array $data
     * @return boolean|string True on success, or a string error message on failure.
     */
    public function update($user_id, $data)
    {
        try {
            // db->update returns true/false based on successful execution
            return $this->db->update($this->_table, $data, "user_id = :user_id", array('user_id' => $user_id));
        } catch (\ckvsoft\CkvException $e) {
            // CATCH: Intercept the exception thrown by the Database class.
            // CHECK: Look for Duplicate Key violation (SQLSTATE '23000')
            if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
                return "The email address you tried to set already exists.";
            }

            // FALLBACK: Return a generic failure message
            return "Database error: Changes could not be saved.";
        }
    }

    /**
     * Delete a user based on user_id.
     *
     * @param integer $user_id
     * @return integer|string Total affected rows on success, or a string error message on failure.
     */
    public function delete($user_id)
    {
        // NOTE: The _getUser call here is likely for authorization/logging, not strictly necessary for deletion logic.
        $user = $this->_getUser($user_id);

        try {
            // db->delete returns the row count (integer)
            return $this->db->delete($this->_table, "user_id = :user_id", array('user_id' => $user_id));
        } catch (\ckvsoft\CkvException $e) {
            // CATCH: Intercept the exception thrown by the Database class.
            // CHECK: Look for Foreign Key Constraint violation (SQLSTATE '23000')
            if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
                return "Cannot delete user. They have active data (e.g., orders, posts) in the system.";
            }

            // FALLBACK: Return a generic failure message
            return "Database error: User could not be deleted.";
        }
    }

    /**
     * Grabs information about a particular user
     *
     * @param integer $user_id
     * @return boolean|array
     */
    private function _getUser($user_id)
    {
        $result = $this->db->select("
            SELECT  *
            FROM    user
            WHERE   user_id = :user_id
        ", array(
            'user_id' => $user_id
        ));

        if (!empty($result)) {
            return $result[0];
        } else {
            return false;
        }
    }
}
