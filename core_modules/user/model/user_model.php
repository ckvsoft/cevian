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
     * @return  integer userid
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
     * Creates a user based on data
     *
     * @param array $data
     * @return integer The new user_id
     */
    public function create($data)
    {
        $this->db->insert($this->_table, $data);
        return $this->db->id();
    }

    /**
     *
     * @param integer $user_id
     * @param array $data
     * @return boolean
     */
    public function update($user_id, $data)
    {
        return $this->db->update($this->_table, $data, "user_id = :user_id", array('user_id' => $user_id));
    }

    /**
     *
     * @param integer $user_id
     * @return boolean
     */
    public function delete($user_id)
    {
        $user = $this->_getUser($user_id);
        return $this->db->delete($this->_table, "user_id = :user_id", array('user_id' => $user_id));
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
