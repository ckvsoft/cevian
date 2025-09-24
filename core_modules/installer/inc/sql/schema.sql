-- -----------------------------
-- DATABASE INSTALLATION SCRIPT
-- -----------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- mainmenu
CREATE TABLE IF NOT EXISTS `mainmenu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(50) NOT NULL DEFAULT '',
  `link` varchar(100) NOT NULL DEFAULT '#',
  `parent` int(11) NOT NULL DEFAULT 0,
  `sort` int(11) DEFAULT NULL,
  `role` varchar(255) DEFAULT 'owner',
  `is_public` tinyint(1) NOT NULL DEFAULT -1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

INSERT IGNORE INTO `mainmenu` (`id`, `label`, `link`, `parent`, `sort`, `role`, `is_public`) VALUES
(1, 'Dashboard', 'dashboard', 0, 0, 'admin', -1),
(2, 'User-Manager', 'user', 5, 0, 'admin', -1),
(3, 'Menu-Manager', 'menu', 5, 0, 'admin', -1),
(4, 'Logout', 'logout', 0, 99, 'owner', -1),
(5, 'Manager', '#', 0, 0, 'admin', -1),
(6, 'Backup', 'backup', 0, 0, 'owner', -1),
(7, 'Login', 'login', 0, 99, 'None', 1),
(8, 'Home', 'home', 0, 0, 'None', 1),
(9, 'Dataprotection', 'home/dataprotection', 0, 0, 'None', 1);

-- module_user_mapping
CREATE TABLE IF NOT EXISTS `module_user_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `framework_user_id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `module_user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_module_mapping` (`framework_user_id`,`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- multi_login_sessions
CREATE TABLE IF NOT EXISTS `multi_login_sessions` (
  `session_id` char(64) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `user_key` char(64) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_active` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `user_id` (`user_id`),
  KEY `module_name` (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `permKey` varchar(30) NOT NULL,
  `permName` varchar(30) NOT NULL,
  `permDescription` varchar(255) NOT NULL DEFAULT 'no description',
  PRIMARY KEY (`id`),
  UNIQUE KEY `permKey` (`permKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- progress_bars
CREATE TABLE IF NOT EXISTS `progress_bars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL DEFAULT 'default',
  `percent` int(11) NOT NULL DEFAULT -1,
  `modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

INSERT IGNORE INTO `progress_bars` (`id`, `name`, `percent`, `modified`) VALUES
(1, 'images', -1, '2025-09-18 12:31:01'),
(2, 'database', -1, '2025-09-20 16:01:56');

-- roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `roleName` varchar(20) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `roleName` (`roleName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

INSERT IGNORE INTO `roles` (`id`, `roleName`) VALUES
(1, 'Administrators'),
(2, 'All Users'),
(3, 'Authors'),
(4, 'Premium Members');

-- role_perms
CREATE TABLE IF NOT EXISTS `role_perms` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `roleID` bigint NOT NULL,
  `permID` bigint NOT NULL,
  `value` tinyint(1) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_perm_unique` (`roleID`,`permID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- user
CREATE TABLE IF NOT EXISTS `user` (
  `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(64) NOT NULL,
  `role` varchar(32) NOT NULL,
  `code` varchar(40) NOT NULL DEFAULT 'NONE',
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- user_perms
CREATE TABLE IF NOT EXISTS `user_perms` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `userID` bigint NOT NULL,
  `permID` bigint NOT NULL,
  `value` tinyint(1) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_perm_unique` (`userID`,`permID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- user_roles
CREATE TABLE IF NOT EXISTS `user_roles` (
  `userID` bigint(20) NOT NULL,
  `roleID` bigint(20) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT current_timestamp(),
  `date_changed` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  UNIQUE KEY `user_role_unique` (`userID`,`roleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

COMMIT;
