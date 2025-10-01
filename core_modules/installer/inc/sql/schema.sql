-- -----------------------------
-- DATABASE INSTALLATION SCRIPT
-- -----------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

--
-- Tabellenstruktur für Tabelle `mainmenu`
--

CREATE TABLE `mainmenu` (
  `id` int(11) NOT NULL,
  `label` varchar(50) NOT NULL DEFAULT '',
  `link` varchar(100) NOT NULL DEFAULT '#',
  `parent` int(11) NOT NULL DEFAULT 0,
  `sort` int(11) DEFAULT NULL,
  `role` varchar(255) DEFAULT 'owner',
  `is_public` tinyint(1) NOT NULL DEFAULT -1,
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Daten für Tabelle `mainmenu`
--

INSERT INTO `mainmenu` (`id`, `label`, `link`, `parent`, `sort`, `role`, `is_public`, `date_added`, `date_changed`) VALUES
(1, 'Dashboard', 'dashboard', 0, 0, 'admin', -1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(2, 'User-Manager', 'user', 5, 0, 'admin', -1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(3, 'Menu-Manager', 'menu', 5, 0, 'admin', -1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(4, 'Logout', 'logout', 0, 99, 'owner', -1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(5, 'Manager', '#', 0, 0, 'admin', -1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(6, 'Backup', 'backup', 0, 0, 'owner', -1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(7, 'Login', 'login', 0, 99, 'None', 1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(8, 'Home', 'home', 0, 0, 'None', 1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(9, 'Dataprotection', 'home/dataprotection', 0, 0, 'None', 1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(10, 'Rbac', '#', 5, 4, 'admin', -1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(11, 'Permissions', 'rbac/permissions', 10, NULL, 'admin', -1, '2025-10-01 10:58:06', '2025-10-01 10:58:06'),
(12, 'Roles', 'rbac', 10, NULL, 'admin', -1, '2025-10-01 10:58:06', '2025-10-01 10:58:06');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `migrations`
--

CREATE TABLE `migrations` (
  `id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `migration` varchar(255) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `migrations`
--

INSERT INTO `migrations` (`id`, `module_name`, `migration`, `applied_at`) VALUES
(1, '_core_', '0.7.0', '2025-09-26 05:24:01'),
(2, '_core_', '0.8.0', '2025-10-01 15:24:01');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `version` varchar(50) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `core` tinyint(1) DEFAULT 0,
  `installed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `module_user_mapping`
--

CREATE TABLE `module_user_mapping` (
  `id` int(11) NOT NULL,
  `framework_user_id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `module_user_id` int(11) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `multi_login_sessions`
--

CREATE TABLE `multi_login_sessions` (
  `session_id` char(64) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `user_key` char(64) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `last_active` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permKey` varchar(100) DEFAULT NULL,
  `permName` varchar(100) DEFAULT NULL,
  `permDescription` varchar(255) DEFAULT NULL,
  `is_used` int(1) NOT NULL DEFAULT 0,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Zeitpunkt der Erstellung des Eintrags',
  `date_changed` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Zeitpunkt der letzten Aktualisierung des Eintrags'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `permissions`
--

INSERT INTO `permissions` (`id`, `permKey`, `permName`, `permDescription`, `is_used`, `date_added`, `date_changed`) VALUES
(1, 'view_dashboard', 'View Dashboard', 'Permission to view the main administrative dashboard.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(2, 'view_manager_menu', 'View Manager Menu', 'Permission to see the main \"Manager\" navigation entry.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(3, 'view_user_manager', 'View User Manager', 'Permission to access the user management interface.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(4, 'view_menu_manager', 'View Menu Manager', 'Permission to access the menu management interface.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(5, 'view_rbac_manager', 'View RBAC Manager', 'Permission to access the Role-Based Access Control configuration.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(6, 'view_rbac_roles', 'View RBAC Roles', 'Permission to view the list of system roles.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(7, 'view_rbac_permissions', 'View RBAC Permissions', 'Permission to view the list of permission definitions.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(8, 'user_read', 'Read User Data', 'Permission to view user details (e.g., list users).', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(9, 'user_create', 'Create Users', 'Permission to add new users to the system.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(10, 'user_update', 'Edit Users', 'Permission to modify existing user details (e.g., name, status).', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(11, 'user_delete', 'Delete Users', 'Permission to permanently remove users from the system.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(12, 'user_assign_roles', 'Assign User Roles', 'Permission to modify the roles assigned to a user.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(13, 'menu_read', 'Read Menu Data', 'Permission to view the menu structure.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(14, 'menu_create', 'Create Menu Items', 'Permission to add new entries to the menu structure.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(15, 'menu_update', 'Edit Menu Items', 'Permission to modify existing menu entries (e.g., name, path, icon).', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(16, 'menu_delete', 'Delete Menu Items', 'Permission to permanently remove menu entries.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(17, 'rbac_role_read', 'Read Roles', 'Permission to view role details and their permission assignments.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(18, 'rbac_role_create', 'Create Roles', 'Permission to define new roles and set parent hierarchy.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(19, 'rbac_role_update', 'Edit Roles', 'Permission to rename roles or change their position/parent.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(20, 'rbac_role_delete', 'Delete Roles', 'Permission to delete roles and all subordinate roles/assignments.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(21, 'rbac_role_assign_perms', 'Assign Role Permissions', 'Permission to modify the permissions assigned to a specific role.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(22, 'rbac_perm_read', 'Read Permissions', 'Permission to view permission definitions.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(23, 'rbac_perm_create', 'Create Permissions', 'Permission to define new permission keys.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(24, 'rbac_perm_update', 'Edit Permissions', 'Permission to modify existing permission keys/names/descriptions.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(25, 'rbac_perm_delete', 'Delete Permissions', 'Permission to delete permission definitions.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(26, 'system_backup', 'Manage System Backup', 'Permission to create, download, or manage system backups.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(27, 'system_settings', 'Modify System Settings', 'Global permission for modifying core framework settings.', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17'),
(28, 'system_maintenance', 'Run Maintenance Tasks', 'Permission to execute system maintenance tasks (e.g., cache clear).', 0, '2025-10-01 13:28:17', '2025-10-01 13:28:17');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `progress_bars`
--

CREATE TABLE `progress_bars` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL DEFAULT 'default',
  `percent` int(11) NOT NULL DEFAULT -1,
  `modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Daten für Tabelle `progress_bars`
--

INSERT INTO `progress_bars` (`id`, `name`, `percent`, `modified`) VALUES
(1, 'images', 100, '2025-09-27 06:08:33'),
(2, 'database', 100, '2025-09-27 03:54:14');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `roleName` varchar(100) NOT NULL,
  `lft` int(11) NOT NULL,
  `rgt` int(11) NOT NULL,
  `depth` int(11) NOT NULL DEFAULT 0,
  `date_added` datetime DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `roles`
--

INSERT INTO `roles` (`id`, `roleName`, `lft`, `rgt`, `depth`, `date_added`, `date_changed`) VALUES
(1, 'Guest', 1, 10, 0, '2025-10-01 15:34:23', '2025-10-01 15:34:23'),
(2, 'Member', 2, 5, 1, '2025-10-01 15:34:23', '2025-10-01 15:34:23'),
(3, 'Editor', 6, 9, 1, '2025-10-01 15:34:23', '2025-10-01 15:34:23'),
(4, 'Manager', 7, 8, 2, '2025-10-01 15:34:23', '2025-10-01 15:34:23'),
(5, 'Administrator', 11, 12, 0, '2025-10-01 15:34:23', '2025-10-01 15:34:23'),
(6, 'Owner', 13, 14, 0, '2025-10-01 15:34:23', '2025-10-01 15:34:23');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `role_perms`
--

CREATE TABLE `role_perms` (
  `id` int(11) NOT NULL,
  `roleID` int(11) NOT NULL,
  `permID` int(11) NOT NULL,
  `value` tinyint(1) NOT NULL DEFAULT 1,
  `date_changed` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user`
--

CREATE TABLE `user` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(64) NOT NULL,
  `role` varchar(32) NOT NULL,
  `code` varchar(40) NOT NULL DEFAULT 'NONE',
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_perms`
--

CREATE TABLE `user_perms` (
  `id` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `permID` int(11) NOT NULL,
  `value` tinyint(1) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `roleID` int(11) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `mainmenu`
--
ALTER TABLE `mainmenu`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_menu_item` (`label`,`parent`,`link`);

--
-- Indizes für die Tabelle `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_migration` (`module_name`,`migration`);

--
-- Indizes für die Tabelle `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `module_user_mapping`
--
ALTER TABLE `module_user_mapping`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_module_mapping` (`framework_user_id`,`module_name`);

--
-- Indizes für die Tabelle `multi_login_sessions`
--
ALTER TABLE `multi_login_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `module_name` (`module_name`);

--
-- Indizes für die Tabelle `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permKey` (`permKey`);

--
-- Indizes für die Tabelle `progress_bars`
--
ALTER TABLE `progress_bars`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `role_perms`
--
ALTER TABLE `role_perms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roleID` (`roleID`,`permID`);

--
-- Indizes für die Tabelle `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indizes für die Tabelle `user_perms`
--
ALTER TABLE `user_perms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `userID` (`userID`,`permID`);

--
-- Indizes für die Tabelle `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`);

COMMIT;
