<?php

namespace ckvsoft;

class ACL extends \ckvsoft\mvc\Config
{

    private $perms = array();  //Array : Stores the permissions for the user
    private $userid = 0;   //Integer : Stores the id of the current user
    private $userRoles = array(); //Array : Stores the roles of the current user

    public function __construct($user_id = -1)
    {
        parent::__construct();

        if ($user_id != -1) {
            $this->userid = $user_id;
        } else {
            $this->userid = $_SESSION['user_id'];
        }
        $this->userRoles = $this->getUserRoles('ids');
        $this->buildACL();
    }

    private function buildACL()
    {
        //first, get the rules for the user's role
        if (count($this->userRoles) > 0) {
            $this->perms = array_merge($this->perms, $this->getRolePerms($this->userRoles));
        }
        //then, get the individual user permissions
        $this->perms = array_merge($this->perms, $this->getUserPerms($this->userid));
    }

    public function getPermKeyFromid($permID)
    {
        $stmt = $this->db->prepare("SELECT `permKey` FROM `permissions` WHERE `id` = :permID LIMIT 1");
        $stmt->execute([':permID' => $permID]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['permKey'] ?? null;
    }

    public function getPermNameFromid($permID)
    {
        $stmt = $this->db->prepare("SELECT `permName` FROM `permissions` WHERE `id` = :permID LIMIT 1");
        $stmt->execute([':permID' => $permID]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['permName'] ?? null;
    }

    public function getRoleNameFromid($roleID)
    {
        $stmt = $this->db->prepare("SELECT `roleName` FROM `roles` WHERE `id` = :roleID LIMIT 1");
        $stmt->execute([':roleID' => $roleID]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['roleName'] ?? null;
    }

    public function getUserRoles()
    {
        $stmt = $this->db->prepare("SELECT * FROM `user_roles` WHERE `userID` = :userID ORDER BY `addDate` ASC");
        $stmt->execute([':userID' => $this->userid]);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $resp = array();

        foreach (array_values($data) as $row) {
            $resp[] = $row['roleID'];
        }
        return $resp;
    }

    public function getAllRoles($f = 'ids')
    {
        $format = strtolower($f);
        $stmt = $this->db->prepare("SELECT * FROM `roles` ORDER BY `roleName` ASC");
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $resp = array();

        foreach (array_values($data) as $row) {
            if ($format == 'full') {
                $resp[] = array("id" => $row['id'], "Name" => $row['roleName']);
            } else {
                $resp[] = $row['id'];
            }
        }
        return $resp;
    }

    public function getAllPerms($f = 'ids')
    {
        $format = strtolower($f);
        $stmt = $this->db->prepare("SELECT * FROM `permissions` ORDER BY `permName` ASC");
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $resp = array();

        foreach (array_values($data) as $row) {
            if ($format == 'full') {
                $resp[$row['permKey']] = array('id' => $row['id'], 'Name' => $row['permName'], 'Key' => $row['permKey'], 'Description' => $row['permDescription']);
            } else {
                $resp[] = $row['id'];
            }
        }
        return $resp;
    }

    private function getRolePerms($role)
    {
        $placeholders = implode(',', array_fill(0, count($role), '?'));
        $sql = "SELECT * FROM `role_perms` WHERE `roleID` IN ($placeholders) ORDER BY `id` ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($role);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $perms = array();
        foreach (array_values($data) as $row) {
            $pK = strtolower($this->getPermKeyFromid($row['permID']));
            if ($pK == '') {
                continue;
            }
            if ($row['value'] === '1') {
                $hP = true;
            } else {
                $hP = false;
            }
            $perms[$pK] = array('perm' => $pK, 'inheritted' => true, 'value' => $hP, 'Name' => $this->getPermNameFromid($row['permID']), 'id' => $row['permID']);
        }
        return $perms;
    }

    private function getUserPerms($user_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM `user_perms` WHERE `userID` = :userID ORDER BY `value`");
        $stmt->execute([':userID' => $user_id]);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $perms = array();

        foreach (array_values($data) as $row) {
            $pK = strtolower($this->getPermKeyFromid($row['permID']));
            if ($pK == '') {
                continue;
            }
            if ($row['value'] == '1') {
                $hP = true;
            } else {
                $hP = false;
            }
            $perms[$pK] = array('perm' => $pK, 'inheritted' => false, 'value' => $hP, 'Name' => $this->getPermNameFromid($row['permID']), 'id' => $row['permID']);
        }
        return $perms;
    }

    public function userHasRole($roleID)
    {
        foreach (array_values($this->userRoles) as $v) {
            if (intval($v) === intval($roleID)) {
                return true;
            }
        }
        if (intval($_SESSION['user_id']) == $this->userid)
            return true;
        return false;
    }

    public function hasPermission($pk)
    {
        $permKey = strtolower($pk);
        if (array_key_exists($permKey, $this->perms)) {
            if ($this->perms[$permKey]['value'] === '1' || $this->perms[$permKey]['value'] === true) {
                return true;
            } else {
                if (intval($_SESSION['user_id']) == 1)
                    return true;
                return false;
            }
        } else {
            if (intval($_SESSION['user_id']) == 1)
                return true;
            return false;
        }
    }

    public function getUser($user_id)
    {
        $stmt = $this->db->prepare("SELECT `user` FROM `user` WHERE `user_id` = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $user_id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['user'] ?? null;
    }
}
