-- Migration Script: 0.8.0 - FINAL DIFF SKRIPT V3
-- Strategy: Pure ALTER TABLE / DROP/CREATE/INSERT with explicit PRIMARY KEY setup.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET foreign_key_checks = 0;
START TRANSACTION;

-- --------------------------------------------------------
-- 1. SCHEMA-KORREKTUREN FÜR BESTEHENDE TABELLEN
-- --------------------------------------------------------

-- a) user Tabelle: Hinzufügen von username und unique Index (Fehleranfällig beim wiederholten Lauf)
-- HINWEIS: Diese Befehle müssen bei wiederholtem Lauf, bei dem die Spalten bereits existieren, MANUELL GELÖSCHT werden!
ALTER TABLE `user` ADD COLUMN `username` VARCHAR(50) NOT NULL AFTER `user_id`;
ALTER TABLE `user` ADD UNIQUE KEY `username` (`username`);
ALTER TABLE `user` ADD UNIQUE KEY `email` (`email`);


-- b) mainmenu Tabelle: Hinzufügen von Datumsstempeln und unique Index
ALTER TABLE `mainmenu` ADD COLUMN `date_added` DATETIME NOT NULL DEFAULT current_timestamp();
ALTER TABLE `mainmenu` ADD COLUMN `date_changed` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
ALTER TABLE `mainmenu` ADD UNIQUE KEY `unique_menu_item` (`label`,`parent`,`link`);


-- c) permissions Tabelle: Modifikationen
-- 1. PRIMÄRSCHLÜSSEL SICHERN (Vermeidet 1075 Fehler bei AUTO_INCREMENT)
ALTER TABLE `permissions` ADD PRIMARY KEY (`id`);
-- 2. AUTO_INCREMENT setzen und Typ anpassen
ALTER TABLE `permissions` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `permissions` MODIFY `permKey` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `permissions` MODIFY `permName` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `permissions` MODIFY `permDescription` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `permissions` ADD COLUMN `is_used` INT(1) NOT NULL DEFAULT 0 AFTER `permDescription`;
ALTER TABLE `permissions` ADD COLUMN `date_added` TIMESTAMP NOT NULL DEFAULT current_timestamp() COMMENT 'Zeitpunkt der Erstellung des Eintrags';
ALTER TABLE `permissions` ADD COLUMN `date_changed` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Zeitpunkt der letzten Aktualisierung des Eintrags';
ALTER TABLE `permissions` DROP KEY IF EXISTS `permKey`;
ALTER TABLE `permissions` ADD UNIQUE KEY `permKey` (`permKey`);


-- d) roles Tabelle: Hinzufügen von Nested Set Spalten
-- 1. PRIMÄRSCHLÜSSEL SICHERN (Vermeidet 1075 Fehler bei AUTO_INCREMENT)
ALTER TABLE `roles` ADD PRIMARY KEY (`id`);
-- 2. AUTO_INCREMENT setzen und Typ anpassen
ALTER TABLE `roles` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `roles` ADD COLUMN `lft` INT(11) NOT NULL;
ALTER TABLE `roles` ADD COLUMN `rgt` INT(11) NOT NULL;
ALTER TABLE `roles` ADD COLUMN `depth` INT(11) NOT NULL DEFAULT 0;
ALTER TABLE `roles` ADD COLUMN `date_added` DATETIME DEFAULT current_timestamp();
ALTER TABLE `roles` ADD COLUMN `date_changed` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();


-- e) role_perms Tabelle: Spalten anpassen
-- 1. PRIMÄRSCHLÜSSEL SICHERN (Vermeidet 1075 Fehler bei AUTO_INCREMENT)
ALTER TABLE `role_perms` ADD PRIMARY KEY (`id`);
-- 2. AUTO_INCREMENT setzen und Typ anpassen
ALTER TABLE `role_perms` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `role_perms` DROP COLUMN `addDate`;
ALTER TABLE `role_perms` MODIFY `value` TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `role_perms` ADD COLUMN `date_changed` DATETIME DEFAULT current_timestamp() AFTER `value`;
ALTER TABLE `role_perms` DROP KEY IF EXISTS `roleID`; -- Index löschen, falls alter 0.7.0-Index vorhanden
ALTER TABLE `role_perms` ADD UNIQUE KEY `roleID` (`roleID`,`permID`);


-- --------------------------------------------------------
-- 2. NEUE/INKOMPATIBLE TABELLEN ERSTELLEN (user_roles und user_perms)
-- --------------------------------------------------------

-- ZUERST ALTE, INKOMPATIBLE TABELLEN LÖSCHEN (UNUMGÄNGLICH)
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `user_perms`;


-- user_roles NEU ERSTELLEN (mit 0.8.0 Struktur)
CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `roleID` int(11) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user_perms NEU ERSTELLEN (mit 0.8.0 Struktur)
CREATE TABLE `user_perms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `permID` int(11) NOT NULL,
  `value` tinyint(1) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `userID` (`userID`,`permID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- 3. DATEN-BEREINIGUNG FÜR NEU-EINFÜGUNG
-- --------------------------------------------------------

-- LÖSCHT ALLE DATEN, die durch die neuen Standard-INSERTS ersetzt werden MÜSSEN.
DELETE FROM `mainmenu` WHERE id IN (1, 2, 3, 4, 5, 6, 7, 8, 9);
DELETE FROM `roles`;
DELETE FROM `permissions`;
DELETE FROM `role_perms`;


-- --------------------------------------------------------
-- 3. NEUE DATEN EINFÜGEN
-- --------------------------------------------------------

-- INSERT mainmenu
INSERT INTO `mainmenu` (`id`, `label`, `link`, `parent`, `sort`, `role`, `is_public`) VALUES
(1, 'Dashboard', 'dashboard', 0, 0, 'admin', -1),
(2, 'User-Manager', 'user', 5, 0, 'admin', -1),
(3, 'Menu-Manager', 'menu', 5, 0, 'admin', -1),
(4, 'Logout', 'logout', 0, 99, 'owner', -1),
(5, 'Manager', '#', 0, 0, 'admin', -1),
(6, 'Backup', 'backup', 0, 0, 'owner', -1),
(7, 'Login', 'login', 0, 99, 'None', 1),
(8, 'Home', 'home', 0, 0, 'None', 1),
(9, 'Dataprotection', 'home/dataprotection', 0, 0, 'None', 1),
(10, 'Rbac', '#', 5, 4, 'admin', -1),
(11, 'Permissions', 'rbac/permissions', 10, NULL, 'admin', -1),
(12, 'Roles', 'rbac', 10, NULL, 'admin', -1)
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `link`=VALUES(`link`);

-- INSERT roles
INSERT INTO `roles` (`id`, `roleName`, `lft`, `rgt`, `depth`) VALUES
(1, 'Guest', 1, 10, 0),
(2, 'Member', 2, 5, 1),
(3, 'Editor', 6, 9, 1),
(4, 'Manager', 7, 8, 2),
(5, 'Administrator', 11, 12, 0),
(6, 'Owner', 13, 14, 0)
ON DUPLICATE KEY UPDATE `roleName`=VALUES(`roleName`), `lft`=VALUES(`lft`);

-- INSERT permissions
INSERT INTO `permissions` (`id`, `permKey`, `permName`, `permDescription`, `is_used`) VALUES
(1, 'view_dashboard', 'View Dashboard', 'Permission to view the main administrative dashboard.', 0),
(2, 'view_manager_menu', 'View Manager Menu', 'Permission to see the main "Manager" navigation entry.', 0),
(3, 'view_user_manager', 'View User Manager', 'Permission to access the user management interface.', 0),
(4, 'view_menu_manager', 'View Menu Manager', 'Permission to access the menu management interface.', 0),
(5, 'view_rbac_manager', 'View RBAC Manager', 'Permission to access the Role-Based Access Control configuration.', 0),
(6, 'view_rbac_roles', 'View RBAC Roles', 'Permission to view the list of system roles.', 0),
(7, 'view_rbac_permissions', 'View RBAC Permissions', 'Permission to view the list of permission definitions.', 0),
(8, 'user_read', 'Read User Data', 'Permission to view user details (e.g., list users).', 0),
(9, 'user_create', 'Create Users', 'Permission to add new users to the system.', 0),
(10, 'user_update', 'Edit Users', 'Permission to modify existing user details (e.g., name, status).', 0),
(11, 'user_delete', 'Delete Users', 'Permission to permanently remove users from the system.', 0),
(12, 'user_assign_roles', 'Assign User Roles', 'Permission to modify the roles assigned to a user.', 0),
(13, 'menu_read', 'Read Menu Data', 'Permission to view the menu structure.', 0),
(14, 'menu_create', 'Create Menu Items', 'Permission to add new entries to the menu structure.', 0),
(15, 'menu_update', 'Edit Menu Items', 'Permission to modify existing menu entries (e.g., name, path, icon).', 0),
(16, 'menu_delete', 'Delete Menu Items', 'Permission to permanently remove menu entries.', 0),
(17, 'rbac_role_read', 'Read Roles', 'Permission to view role details and their permission assignments.', 0),
(18, 'rbac_role_create', 'Create Roles', 'Permission to define new roles and set parent hierarchy.', 0),
(19, 'rbac_role_update', 'Edit Roles', 'Permission to rename roles or change their position/parent.', 0),
(20, 'rbac_role_delete', 'Delete Roles', 'Permission to delete roles and all subordinate roles/assignments.', 0),
(21, 'rbac_role_assign_perms', 'Assign Role Permissions', 'Permission to modify the permissions assigned to a specific role.', 0),
(22, 'rbac_perm_read', 'Read Permissions', 'Permission to view permission definitions.', 0),
(23, 'rbac_perm_create', 'Create Permissions', 'Permission to define new permission keys.', 0),
(24, 'rbac_perm_update', 'Edit Permissions', 'Permission to modify existing permission keys/names/descriptions.', 0),
(25, 'rbac_perm_delete', 'Delete Permissions', 'Permission to delete permission definitions.', 0),
(26, 'system_backup', 'Manage System Backup', 'Permission to create, download, or manage system backups.', 0),
(27, 'system_settings', 'Modify System Settings', 'Global permission for modifying core framework settings.', 0),
(28, 'system_maintenance', 'Run Maintenance Tasks', 'Permission to execute system maintenance tasks (e.g., cache clear).', 0)
ON DUPLICATE KEY UPDATE `permName`=VALUES(`permName`), `permDescription`=VALUES(`permDescription`);

-- ASSIGN Permissions to Administrator (ID 5) & Assign Role to Superuser (ID 1)
INSERT INTO `role_perms` (`roleID`, `permID`, `value`)
SELECT 5, id, '1' FROM `permissions`
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

INSERT INTO `user_roles` (`userID`, `roleID`) VALUES
(1, 5)
ON DUPLICATE KEY UPDATE `roleID`=VALUES(`roleID`);

-- --------------------------------------------------------
-- 5. ABSCHLUSS
-- --------------------------------------------------------

COMMIT;
SET foreign_key_checks = 1;