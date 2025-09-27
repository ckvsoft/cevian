<?php

namespace ckvsoft;

class ACL extends \ckvsoft\mvc\Config
{

    private $perms = [];      // Array: Stores the permissions for the user
    private $userid = 0;      // Integer: Current user ID
    private $userRoles = [];  // Array: Current user's roles

    public function __construct($user_id = -1)
    {
        parent::__construct();

        // Determine user ID
        $this->userid = ($user_id != -1) ? $user_id : $_SESSION['user_id'] ?? 0;

        // Load user roles and build ACL
        $this->userRoles = $this->getUserRoles();
        $this->buildACL();
    }

    /**
     * Build the ACL for current user by merging role and user permissions
     */
    private function buildACL()
    {
        if (!empty($this->userRoles)) {
            $this->perms = array_merge($this->perms, $this->getRolePerms($this->userRoles));
        }
        $this->perms = array_merge($this->perms, $this->getUserPerms($this->userid));
    }

    /**
     * Check if current user is superuser (user_id == 1)
     *
     * @return bool
     */
    public function isSuperuser(): bool
    {
        if (!isset($_SESSION['user_id'], $_SESSION['user_key']))
            return false;

        $expectedKey = \ckvsoft\Hash::create('sha256', $_SESSION['user_id'], HASH_KEY);
        return hash_equals($expectedKey, $_SESSION['user_key']) && $_SESSION['user_id'] == 1;
    }

    /**
     * Get permission key from permission ID
     */
    public function getPermKeyFromid($permID)
    {
        $result = $this->db->select(
                "SELECT `permKey` FROM `permissions` WHERE `id` = :id LIMIT 1",
                ['id' => $permID]
        );
        return $result[0]['permKey'] ?? null;
    }

    /**
     * Get permission name from permission ID
     */
    public function getPermNameFromid($permID)
    {
        $result = $this->db->select(
                "SELECT `permName` FROM `permissions` WHERE `id` = :id LIMIT 1",
                ['id' => $permID]
        );
        return $result[0]['permName'] ?? null;
    }

    /**
     * Get role name from role ID
     */
    public function getRoleNameFromid($roleID)
    {
        $result = $this->db->select(
                "SELECT `roleName` FROM `roles` WHERE `id` = :id LIMIT 1",
                ['id' => $roleID]
        );
        return $result[0]['roleName'] ?? null;
    }

    /**
     * Get roles of current user
     *
     * @return array
     */
    public function getUserRoles()
    {
        $data = $this->db->select(
                "SELECT roleID FROM `user_roles` WHERE `userID` = :userID ORDER BY `addDate` ASC",
                ['userID' => $this->userid]
        );
        $roles = [];
        foreach ($data as $row) {
            $roles[] = $row['roleID'];
        }
        return $roles;
    }

    /**
     * Get all roles (optionally full details)
     */
    public function getAllRoles($format = 'ids')
    {
        $data = $this->db->select("SELECT * FROM `roles` ORDER BY `roleName` ASC");
        $resp = [];
        foreach ($data as $row) {
            if (strtolower($format) === 'full') {
                $resp[] = ['id' => $row['id'], 'Name' => $row['roleName']];
            } else {
                $resp[] = $row['id'];
            }
        }
        return $resp;
    }

    /**
     * Get all permissions (optionally full details)
     */
    public function getAllPerms($format = 'ids')
    {
        $data = $this->db->select("SELECT * FROM `permissions` ORDER BY `permName` ASC");
        $resp = [];
        foreach ($data as $row) {
            if (strtolower($format) === 'full') {
                $resp[$row['permKey']] = [
                    'id' => $row['id'],
                    'Name' => $row['permName'],
                    'Key' => $row['permKey'],
                    'Description' => $row['permDescription']
                ];
            } else {
                $resp[] = $row['id'];
            }
        }
        return $resp;
    }

    /**
     * Get permissions inherited from roles
     */
    private function getRolePerms($roles)
    {
        if (empty($roles))
            return [];

        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT * FROM `role_perms` WHERE `roleID` IN ($placeholders) ORDER BY `id` ASC";
        $data = $this->db->select($sql, $roles);

        $perms = [];
        foreach ($data as $row) {
            $pK = strtolower($this->getPermKeyFromid($row['permID']));
            if (!$pK)
                continue;
            $perms[$pK] = [
                'perm' => $pK,
                'inheritted' => true,
                'value' => $row['value'] === '1',
                'Name' => $this->getPermNameFromid($row['permID']),
                'id' => $row['permID']
            ];
        }
        return $perms;
    }

    /**
     * Get permissions assigned to user
     */
    private function getUserPerms($user_id)
    {
        $data = $this->db->select(
                "SELECT * FROM `user_perms` WHERE `userID` = :userID ORDER BY `value`",
                ['userID' => $user_id]
        );

        $perms = [];
        foreach ($data as $row) {
            $pK = strtolower($this->getPermKeyFromid($row['permID']));
            if (!$pK)
                continue;
            $perms[$pK] = [
                'perm' => $pK,
                'inheritted' => false,
                'value' => $row['value'] === '1',
                'Name' => $this->getPermNameFromid($row['permID']),
                'id' => $row['permID']
            ];
        }
        return $perms;
    }

    /**
     * Check if user has a specific role
     */
    public function userHasRole($roleID)
    {
        if ($this->isSuperuser())
            return true;
        return in_array(intval($roleID), array_map('intval', $this->userRoles), true);
    }

    /**
     * Check if user has a permission key
     */
    public function hasPermission($permission_key)
    {
        $permKey = strtolower($permission_key);

        // Superuser shortcut
        if ($this->isSuperuser()) {
            // check if permission exists
            $perm = $this->db->select("SELECT id FROM permissions WHERE permKey = :key", ['key' => $permKey]);
            if (empty($perm)) {
                // auto-create missing permission
                $this->db->insert('permissions', [
                    'permKey' => $permKey,
                    'permName' => ucfirst($permKey),
                    'permDescription' => 'Automatically created for superuser'
                ]);
            }
            return true;
        }

        return isset($this->perms[$permKey]) && ($this->perms[$permKey]['value'] === true || $this->perms[$permKey]['value'] === '1');
    }

    /**
     * Get username for user_id
     */
    public function getUserName($user_id)
    {
        $result = $this->db->select(
                "SELECT `user` FROM `username` WHERE `user_id` = :user_id LIMIT 1",
                ['user_id' => $user_id]
        );
        return $result[0]['username'] ?? null;
    }
}
