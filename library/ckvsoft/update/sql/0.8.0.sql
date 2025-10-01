-- Migration Script: 0.8.0
-- Changes: Introduces full RBAC (Role-Based Access Control) structure
--          and updates the mainmenu for RBAC management.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET foreign_key_checks = 0; -- Temporarily disable FK checks if any exist

-- --------------------------------------------------------
-- 1. DROP and CREATE/ALTER TABLES (RBAC)
-- Note: We assume the existing tables are mostly empty or the new structure is mandatory.
--       If the tables already exist from 0.7.0, we use ALTER/DROP/RENAME/ADD.

-- Rename existing permissions and roles table if they exist, to prepare for new structure
RENAME TABLE `permissions` TO `permissions_old_070`,
             `roles` TO `roles_old_070`,
             `role_perms` TO `role_perms_old_070`;

-- RBAC Tables (New/Updated Schema)
-- --------------------------------------------------------

-- a) New/Updated `permissions` Table
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permKey` varchar(100) DEFAULT NULL,
  `permName` varchar(100) DEFAULT NULL,
  `permDescription` varchar(255) DEFAULT NULL,
  `is_used` int(1) NOT NULL DEFAULT 0,
  `date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Zeitpunkt der Erstellung des Eintrags',
  `date_changed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zeitpunkt der letzten Aktualisierung des Eintrags',
  PRIMARY KEY (`id`),
  UNIQUE KEY `permKey` (`permKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- b) New/Updated `roles` Table (Nested Set)
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `roleName` varchar(100) NOT NULL,
  `lft` int(11) NOT NULL,
  `rgt` int(11) NOT NULL,
  `depth` int(11) NOT NULL DEFAULT 0,
  `date_added` datetime DEFAULT CURRENT_TIMESTAMP(),
  `date_changed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- c) New/Updated `role_perms` Table
CREATE TABLE IF NOT EXISTS `role_perms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `roleID` int(11) NOT NULL,
  `permID` int(11) NOT NULL,
  `value` tinyint(1) NOT NULL DEFAULT 1,
  `date_changed` datetime DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `roleID` (`roleID`,`permID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- d) New/Updated `user_perms` Table
CREATE TABLE IF NOT EXISTS `user_perms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `permID` int(11) NOT NULL,
  `value` tinyint(1) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `date_changed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `userID` (`userID`,`permID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- e) New/Updated `user_roles` Table
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `roleID` int(11) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `date_changed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. UPDATE mainmenu for RBAC Links (ID 10, 11, 12)
-- Note: Assuming IDs 10, 11, 12 are new and won't conflict with existing data.

-- Add missing date columns (if not present from 0.7.0)
ALTER TABLE `mainmenu`
  ADD COLUMN `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP() AFTER `is_public`,
  ADD COLUMN `date_changed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() AFTER `date_added`;

-- Insert the new RBAC menu items (ID 10, 11, 12)
INSERT INTO `mainmenu` (`id`, `label`, `link`, `parent`, `sort`, `role`, `is_public`, `date_added`, `date_changed`) VALUES
(10, 'Rbac', '#', 5, 4, 'admin', -1, NOW(), NOW()),
(11, 'Permissions', 'rbac/permissions', 10, NULL, 'admin', -1, NOW(), NOW()),
(12, 'Roles', 'rbac', 10, NULL, 'admin', -1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `link`=VALUES(`link`), `parent`=VALUES(`parent`), `sort`=VALUES(`sort`), `role`=VALUES(`role`);

-- --------------------------------------------------------
-- 3. INSERT Default Nested Roles

INSERT INTO `roles` (`id`, `roleName`, `lft`, `rgt`, `depth`, `date_added`, `date_changed`) VALUES
(1, 'Guest', 1, 10, 0, NOW(), NOW()),
(2, 'Member', 2, 5, 1, NOW(), NOW()),
(3, 'Editor', 6, 9, 1, NOW(), NOW()),
(4, 'Manager', 7, 8, 2, NOW(), NOW()),
(5, 'Administrator', 11, 12, 0, NOW(), NOW()),
(6, 'Owner', 13, 14, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `roleName`=VALUES(`roleName`), `lft`=VALUES(`lft`), `rgt`=VALUES(`rgt`), `depth`=VALUES(`depth`);

-- --------------------------------------------------------
-- 4. INSERT Permissions

INSERT INTO `permissions` (`id`, `permKey`, `permName`, `permDescription`, `is_used`, `date_added`, `date_changed`) VALUES
(1, 'view_dashboard', 'View Dashboard', 'Permission to view the main administrative dashboard.', 0, NOW(), NOW()),
(2, 'view_manager_menu', 'View Manager Menu', 'Permission to see the main "Manager" navigation entry.', 0, NOW(), NOW()),
(3, 'view_user_manager', 'View User Manager', 'Permission to access the user management interface.', 0, NOW(), NOW()),
(4, 'view_menu_manager', 'View Menu Manager', 'Permission to access the menu management interface.', 0, NOW(), NOW()),
(5, 'view_rbac_manager', 'View RBAC Manager', 'Permission to access the Role-Based Access Control configuration.', 0, NOW(), NOW()),
(6, 'view_rbac_roles', 'View RBAC Roles', 'Permission to view the list of system roles.', 0, NOW(), NOW()),
(7, 'view_rbac_permissions', 'View RBAC Permissions', 'Permission to view the list of permission definitions.', 0, NOW(), NOW()),
(8, 'user_read', 'Read User Data', 'Permission to view user details (e.g., list users).', 0, NOW(), NOW()),
(9, 'user_create', 'Create Users', 'Permission to add new users to the system.', 0, NOW(), NOW()),
(10, 'user_update', 'Edit Users', 'Permission to modify existing user details (e.g., name, status).', 0, NOW(), NOW()),
(11, 'user_delete', 'Delete Users', 'Permission to permanently remove users from the system.', 0, NOW(), NOW()),
(12, 'user_assign_roles', 'Assign User Roles', 'Permission to modify the roles assigned to a user.', 0, NOW(), NOW()),
(13, 'menu_read', 'Read Menu Data', 'Permission to view the menu structure.', 0, NOW(), NOW()),
(14, 'menu_create', 'Create Menu Items', 'Permission to add new entries to the menu structure.', 0, NOW(), NOW()),
(15, 'menu_update', 'Edit Menu Items', 'Permission to modify existing menu entries (e.g., name, path, icon).', 0, NOW(), NOW()),
(16, 'menu_delete', 'Delete Menu Items', 'Permission to permanently remove menu entries.', 0, NOW(), NOW()),
(17, 'rbac_role_read', 'Read Roles', 'Permission to view role details and their permission assignments.', 0, NOW(), NOW()),
(18, 'rbac_role_create', 'Create Roles', 'Permission to define new roles and set parent hierarchy.', 0, NOW(), NOW()),
(19, 'rbac_role_update', 'Edit Roles', 'Permission to rename roles or change their position/parent.', 0, NOW(), NOW()),
(20, 'rbac_role_delete', 'Delete Roles', 'Permission to delete roles and all subordinate roles/assignments.', 0, NOW(), NOW()),
(21, 'rbac_role_assign_perms', 'Assign Role Permissions', 'Permission to modify the permissions assigned to a specific role.', 0, NOW(), NOW()),
(22, 'rbac_perm_read', 'Read Permissions', 'Permission to view permission definitions.', 0, NOW(), NOW()),
(23, 'rbac_perm_create', 'Create Permissions', 'Permission to define new permission keys.', 0, NOW(), NOW()),
(24, 'rbac_perm_update', 'Edit Permissions', 'Permission to modify existing permission keys/names/descriptions.', 0, NOW(), NOW()),
(25, 'rbac_perm_delete', 'Delete Permissions', 'Permission to delete permission definitions.', 0, NOW(), NOW()),
(26, 'system_backup', 'Manage System Backup', 'Permission to create, download, or manage system backups.', 0, NOW(), NOW()),
(27, 'system_settings', 'Modify System Settings', 'Global permission for modifying core framework settings.', 0, NOW(), NOW()),
(28, 'system_maintenance', 'Run Maintenance Tasks', 'Permission to execute system maintenance tasks (e.g., cache clear).', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `permName`=VALUES(`permName`), `permDescription`=VALUES(`permDescription`);

-- --------------------------------------------------------
-- 5. ASSIGN Permissions to Administrator (ID 5) & Assign Role to Superuser (ID 1)

-- Assign all 28 permissions to the Administrator role (ID 5)
INSERT INTO `role_perms` (`roleID`, `permID`, `value`, `date_changed`)
SELECT 5, id, '1', NOW() FROM `permissions`
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

-- Assign Administrator role (ID 5) to Superuser (userID 1)
INSERT INTO `user_roles` (`userID`, `roleID`, `date_added`, `date_changed`) VALUES
(1, 5, NOW(), NOW())
ON DUPLICATE KEY UPDATE `roleID`=VALUES(`roleID`);

SET foreign_key_checks = 1; -- Re-enable FK checks
