SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET foreign_key_checks = 0;
START TRANSACTION;

CREATE TABLE `gallery_albums` (
    `album_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `album_path` VARCHAR(512) NOT NULL COMMENT 'Relative path to the album, e.g., events/hochzeit',
    `title` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Display title of the album',
    `owner_user_id` INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'ID of the user who owns the album (NULL if public/system-owned)',
    `permissions_level` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Public, 1=User, 2=Admin etc.',
    PRIMARY KEY (`album_id`),
    UNIQUE KEY `path_unique` (`album_path`(255)),
    KEY `idx_owner_user_id` (`owner_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gallery_media_stats` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `album_id` INT(11) UNSIGNED NOT NULL COMMENT 'Foreign key to the gallery_albums table',
    `file_name` VARCHAR(255) NOT NULL COMMENT 'Media filename (e.g., image.jpg)',
    `views` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total view count for this media file',
    `last_view` DATETIME NULL DEFAULT NULL COMMENT 'Timestamp of the last view',
    PRIMARY KEY (`id`),
    -- Unique key ensures only one counter entry per album/file combination
    UNIQUE KEY `album_file_unique` (`album_id`, `file_name`),
    -- Foreign key constraint
    CONSTRAINT `fk_album_id` FOREIGN KEY (`album_id`) REFERENCES `gallery_albums` (`album_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;