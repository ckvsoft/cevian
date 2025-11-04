<?php

/*
 * The MIT License
 *
 * Copyright 2025 chris.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OR TORT OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

use ckvsoft\Progress;
use ckvsoft\Image;

require_once __DIR__ . '/gallery_model.php';

/**
 * Model responsible for administrative tasks: rescanning albums, synchronizing
 * database entries with the filesystem, and managing media statistics.
 */
class GalleryManager_Model extends Gallery_Model
{

    const UPDATE_FREQUENCY = 10;

    /**
     * Constructor receives Gallery_Model (Dependency Injection) to eliminate
     * internal duplication of constants and helper methods.
     */
    public function __construct()
    {
        parent::__construct();
    }

    // ------------------------------------------------------------------
    // ADMINISTRATIVE HELPERS
    // ------------------------------------------------------------------

    /**
     * Retrieves the current status of the progress tracking for a given ID.
     * @param int $progressId The ID of the current progress task.
     * @return array Progress status data.
     */
    public function getProgressStatus(int $progressId): array
    {
        return Progress::getStatus($progressId, $this->db);
    }

    /**
     * Helper: Counts the total number of media files across all albums for the Progress Bar.
     * Uses constants from Gallery_Model.
     * @param array $dbAlbums List of albums from the DB.
     * @return int Total number of files.
     */
    private function countTotalMediaFiles(array $dbAlbums): int
    {
        $totalCount = 0;
        $imageExt = Gallery_Model::SUPPORTED_IMAGE_EXT;
        $videoExt = Gallery_Model::SUPPORTED_VIDEO_EXT;

        foreach ($dbAlbums as $album) {
            $fullPath = $this->basePath . $album['album_path'];

            try {
                $dirIterator = new \DirectoryIterator($fullPath);

                foreach ($dirIterator as $item) {
                    if ($item->isDot() || $item->isDir()) {
                        continue;
                    }

                    $origExt = $item->getExtension();
                    $ext = strtolower($origExt);
                    $nameNoExt = $item->getBasename('.' . $origExt);

                    if (str_ends_with($nameNoExt, '_thumb')) {
                        continue;
                    }

                    if (in_array($ext, $imageExt) || in_array($ext, $videoExt)) {
                        $totalCount++;
                    }
                }
            } catch (\Exception $e) {
                error_log("Cannot read directory {$fullPath}: " . $e->getMessage());
            }
        }

        return $totalCount;
    }

    // ------------------------------------------------------------------
    // MEDIA RESCAN LOGIC
    // ------------------------------------------------------------------

    /**
     * Scans the filesystem, synchronizes gallery_media_stats table (metadata and stats),
     * and removes orphaned media files and thumbnails.
     * This method is the SOLE source of truth for media metadata synchronization.
     * Includes Progress Tracking.
     * @param int $progressId The ID for progress tracking.
     * @return array Summary of the rescan results.
     */
    public function rescanAlbumMedia(int $progressId): array
    {
        $addedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $deletedDbEntries = 0;
        $deletedThumbs = 0;

        $this->rescanAlbums(ckvsoft\Auth::getUserId());
        $dbAlbums = $this->db->select("SELECT album_id, album_path FROM gallery_albums");
        $totalFiles = $this->countTotalMediaFiles($dbAlbums);
        $progress = new Progress($totalFiles, $progressId, $this->db);

        $imageExt = Gallery_Model::SUPPORTED_IMAGE_EXT;
        $videoExt = Gallery_Model::SUPPORTED_VIDEO_EXT;
        $updateCounter = 0;

        foreach ($dbAlbums as $album) {
            $existingMedia = $this->db->select(
                    "SELECT id, file_name, file_mtime FROM gallery_media_stats WHERE album_id = :aid",
                    ['aid' => $album['album_id']]
            );
            $existingMediaMap = array_column($existingMedia, 'file_mtime', 'file_name');

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            usleep(100);

            $albumId = $album['album_id'];
            $albumPath = $album['album_path'];
            $fullPath = $this->basePath . $albumPath;

            try {
                $dirIterator = new \DirectoryIterator($fullPath);
            } catch (\Exception $e) {
                error_log("Cannot scan directory: {$fullPath}. Skipping album ID {$albumId}.");
                continue;
            }

            foreach ($dirIterator as $item) {
                if ($item->isDot() || $item->isDir()) {
                    continue;
                }

                $file = $item->getFilename();
                $origExt = $item->getExtension();
                $ext = strtolower($origExt);
                $nameNoExt = $item->getBasename('.' . $origExt);
                $filePath = $item->getPathname();

                if (str_ends_with($nameNoExt, '_thumb') ||
                        (!in_array($ext, $imageExt) && !in_array($ext, $videoExt))) {
                    continue;
                }

                $fileName = $file;
                $fileMTime = $item->getMTime();
                $fileSize = $item->getSize();
                $mediaType = in_array($ext, $imageExt) ? 'image' : 'video';

                $isNew = !isset($existingMediaMap[$fileName]);
                $isModified = !$isNew && ((string) $existingMediaMap[$fileName] !== (string) $fileMTime);

                $thumbExt = ($mediaType === 'video') ? 'jpg' : $origExt;
                $thumbFileName = $nameNoExt . '_thumb.' . $thumbExt;
                $thumbFile = $fullPath . '/' . $thumbFileName;

                $shouldRegenerateThumb = $isModified || !file_exists($thumbFile);

                if ($shouldRegenerateThumb && $mediaType === 'image') {
                    $this->ensureThumbnail($filePath, $thumbFile, $isModified, $deletedThumbs);
                }
                if ($isNew || $isModified) {
                    $dataToSave = [
                        'album_id' => $albumId,
                        'file_name' => $fileName,
                        'file_mtime' => $fileMTime,
                        'file_size' => $fileSize,
                        'media_type' => $mediaType,
                    ];

                    if ($isNew) {
                        $this->db->insert('gallery_media_stats', $dataToSave);
                        $addedCount++;
                    } else {
                        $updateData = $dataToSave;
                        unset($updateData['album_id'], $updateData['file_name']);

                        $this->db->update('gallery_media_stats', $updateData, 'album_id = :aid AND file_name = :file', [
                            'aid' => $albumId,
                            'file' => $fileName
                        ]);
                        $updatedCount++;
                    }
                } else {
                    $skippedCount++;
                }

                $progress->increment();
                $updateCounter++;
                if ($updateCounter >= self::UPDATE_FREQUENCY) {
                    $progress->updateProgress();
                    $updateCounter = 0;
                }
            }
        }

        $progress->updateProgress();

        $dbMediaEntries = $this->db->select("SELECT id, album_id, file_name FROM gallery_media_stats");

        foreach ($dbMediaEntries as $entry) {
            $albumId = $entry['album_id'];
            $fileName = $entry['file_name'];
            $albumPath = $this->getAlbumPathById($albumId);

            if ($albumPath === null) {
                $this->db->delete('gallery_media_stats', 'id = :id', ['id' => $entry['id']]);
                $deletedDbEntries++;
                continue;
            }

            $fullAlbumPath = $this->basePath . $albumPath;
            $filePath = $fullAlbumPath . '/' . $fileName;

            if (!file_exists($filePath)) {
                $this->db->delete('gallery_media_stats', 'id = :id', ['id' => $entry['id']]);
                $deletedDbEntries++;

                $nameNoExt = pathinfo($fileName, PATHINFO_FILENAME);
                $origExt = pathinfo($fileName, PATHINFO_EXTENSION);
                $ext = strtolower($origExt);

                $thumbFile = null;
                if (in_array($ext, Gallery_Model::SUPPORTED_IMAGE_EXT)) {
                    $thumbFile = $fullAlbumPath . '/' . $nameNoExt . '_thumb.' . $origExt;
                } elseif (in_array($ext, Gallery_Model::SUPPORTED_VIDEO_EXT)) {
                    $thumbFile = $fullAlbumPath . '/' . $nameNoExt . '_thumb.jpg';
                }

                if ($thumbFile && file_exists($thumbFile)) {
                    unlink($thumbFile);
                    $deletedThumbs++;
                }
            }
        }

        $progress->updateProgress(100);

        return [
            'added_count' => $addedCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'deleted_db_entries' => $deletedDbEntries,
            'deleted_thumbnails' => $deletedThumbs
        ];
    }

    /**
     * Updates the album_id for a single media item after it has been moved
     * to a new folder on the file system.
     *
     * @param string $fileName The file name (e.g., 'image.jpg').
     * @param string $oldAlbumPath The previous relative album path.
     * @param string $newAlbumPath The new relative album path.
     * @return bool True on successful DB update, false otherwise.
     */
    public function updateMediaAlbumId(string $fileName, string $oldAlbumPath, string $newAlbumPath): bool
    {
        $newAlbum = $this->db->selectOne(
                "SELECT album_id FROM gallery_albums WHERE album_path = :newPath",
                ['newPath' => trim($newAlbumPath, '/')]
        );

        if (!$newAlbum) {
            error_log("updateMediaAlbumId failed: New album not found for path '{$newAlbumPath}'.");
            return false;
        }

        $newAlbumId = $newAlbum['album_id'];

        $oldAlbum = $this->db->selectOne(
                "SELECT album_id FROM gallery_albums WHERE album_path = :oldPath",
                ['oldPath' => trim($oldAlbumPath, '/')]
        );

        if (!$oldAlbum) {
            error_log("updateMediaAlbumId failed: Old album not found for path '{$oldAlbumPath}'.");
            return false;
        }

        $oldAlbumId = $oldAlbum['album_id'];

        $updatedRows = $this->db->update('gallery_media_stats',
                ['album_id' => $newAlbumId],
                'album_id = :oldId AND file_name = :file_name',
                ['oldId' => $oldAlbumId, 'file_name' => $fileName]
        );

        return $updatedRows > 0;
    }

    // ------------------------------------------------------------------
    // ALBUM ADMINISTRATION
    // ------------------------------------------------------------------

    /**
     * Performs a recursive filesystem scan to find all valid album directories
     * within the base path.
     * @return array List of relative album paths (e.g., 'summer_2024', 'events/wedding').
     */
    public function getAvailableAlbumPaths(): array
    {
        $albumPaths = [];
        $albumPaths[] = '';

        try {
            if (!is_dir($this->basePath)) {
                return [];
            }
            $rii = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($this->basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($rii as $item) {
                if ($item->isDir()) {
                    $relativePath = trim(str_replace($this->basePath, '', $item->getPathname()), '/');
                    if ($relativePath) {
                        $albumPaths[] = $relativePath;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Failed during album path scanning: " . $e->getMessage());
        }

        return $albumPaths;
    }

    /**
     * Rescans all albums and synchronizes the gallery_albums table with the filesystem.
     * Handles adding new folders and deleting database entries for missing folders.
     * @param int|null $currentUserId Optional user ID to be assigned as owner for new albums.
     * @param int|null $progressId The ID for the progress bar (e.g., 3).
     * @return array An array containing the results of the operation.
     */
    public function rescanAlbums(?int $currentUserId = null, ?int $progressId = null): array
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        usleep(100);

        $results = [
            'added_count' => 0,
            'deleted_count' => 0,
            'added_albums' => [],
            'deleted_albums' => [],
        ];

        if ($progressId !== null) {
            $progress = new \ckvsoft\Progress(0, $progressId, $this->db);
            $progress->updateProgress(0);
        }

        $rootPathInDb = '';
        $this->ensureRootAlbumExists($rootPathInDb, $currentUserId ?? 1); // Pass a default user ID

        $fsPaths = $this->getAvailableAlbumPaths();

        if ($progressId !== null) {
            $progress->updateProgress(30);
        }

        $dbAlbums = $this->db->select("SELECT album_id, album_path FROM `gallery_albums`");
        $dbPaths = array_column($dbAlbums, 'album_path');
        $dbPathToId = array_column($dbAlbums, 'album_id', 'album_path');

        if ($progressId !== null) {
            $progress->updateProgress(40);
        }

        $pathsToAdd = array_diff($fsPaths, $dbPaths);

        foreach ($pathsToAdd as $path) {
            try {
                $ownerId = $currentUserId ?? 1;
                $trimmedPath = trim($path, '/');

                $folderName = basename($trimmedPath);
                $generatedTitle = $this->formatMediaName($folderName);

                $data = [
                    'album_path' => $trimmedPath,
                    'title' => $generatedTitle,
                    'permissions_level' => 2,
                    'owner_user_id' => $ownerId
                ];

                $this->db->insert('gallery_albums', $data);

                $results['added_count']++;
                $results['added_albums'][] = $path;
            } catch (\Exception $e) {
                error_log("!!! FATAL INSERT ERROR for '{$path}' !!!: " . $e->getMessage());
            }
        }

        if ($progressId !== null) {
            $progress->updateProgress(60);
        }

        $pathsToDelete = array_diff($dbPaths, $fsPaths);

        if (!empty($pathsToDelete)) {
            $idsToDelete = [];
            foreach ($pathsToDelete as $path) {
                if (isset($dbPathToId[$path])) {
                    $idsToDelete[] = $dbPathToId[$path];
                    $results['deleted_albums'][] = $path;
                }
            }

            if (!empty($idsToDelete)) {

                $namedPlaceholders = [];
                $boundParams = [];

                foreach ($idsToDelete as $index => $id) {
                    $placeholderName = "album_id_{$index}";
                    $namedPlaceholders[] = ":{$placeholderName}";
                    $boundParams[$placeholderName] = $id;
                }

                $placeholderList = implode(', ', $namedPlaceholders);
                $whereCondition = "`album_id` IN ({$placeholderList})";

                try {
                    $deleteCount = $this->db->delete('gallery_albums', $whereCondition, $boundParams);

                    $this->db->delete('gallery_media_stats', $whereCondition, $boundParams);

                    $results['deleted_count'] = $deleteCount;
                } catch (\PDOException $e) {
                    error_log("DB Error during album deletion: " . $e->getMessage());
                    $results['deleted_count'] = 0;
                }
            }
        }

        if ($progressId !== null) {
            $progress->updateProgress(90);
        }

        if ($progressId !== null) {
            $progress->updateProgress(100);
        }

        return $results;
    }

    /**
     * Updates the path of an album and recursively updates the paths of all its sub-albums.
     * @param string $oldPath The original relative path of the album (e.g., 'old_folder/sub').
     * @param string $newPath The new relative path of the album (e.g., 'new_folder/sub').
     * @return bool True on successful DB update, false otherwise.
     */
    public function updateAlbumPath(string $oldPath, string $newPath): bool
    {
        $trimmedOldPath = trim($oldPath, '/');
        $trimmedNewPath = trim($newPath, '/');

        if ($trimmedOldPath === '' || $trimmedNewPath === '') {
            return false;
        }

        $this->db->beginTransaction();

        try {
            $album = $this->db->selectOne(
                    "SELECT album_id FROM gallery_albums WHERE album_path = :oldPath",
                    ['oldPath' => $trimmedOldPath]
            );

            if (!$album) {
                $this->db->rollBack();
                error_log("UpdateAlbumPath failed: Album not found for path '{$trimmedOldPath}'.");
                return false;
            }

            $albumId = $album['album_id'];

            $success = $this->db->update('gallery_albums',
                    ['album_path' => $trimmedNewPath],
                    'album_id = :id',
                    ['id' => $albumId]
            );

            if (!$success) {
                $this->db->rollBack();
                error_log("UpdateAlbumPath failed to update the main album ID {$albumId}.");
                return false;
            }

            $oldPrefix = $trimmedOldPath . '/';
            $dbSubAlbums = $this->db->select(
                    // Use LIKE to find all paths starting with the old path followed by a slash
                    "SELECT album_id, album_path FROM gallery_albums WHERE album_path LIKE :prefixWildcard",
                    ['prefixWildcard' => $oldPrefix . '%']
            );

            $updatedCount = 0;

            foreach ($dbSubAlbums as $subAlbum) {
                $newSubPath = str_replace($oldPrefix, $trimmedNewPath . '/', $subAlbum['album_path']);

                $success = $this->db->update('gallery_albums',
                        ['album_path' => $newSubPath],
                        'album_id = :id',
                        ['id' => $subAlbum['album_id']]
                );

                if ($success) {
                    $updatedCount++;
                } else {
                    error_log("UpdateAlbumPath Warning: Failed to update sub-album {$subAlbum['album_id']}.");
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("UpdateAlbumPath DB transaction FAILED: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensures the primary album root directory (gallery base path) is registered as an album
     * in the gallery_albums table, if it's not already present.
     *
     * @param string $rootAlbumPath The path representation for the root album (e.g., '').
     * @param int $userId The user ID to assign as the creator/owner.
     * @return void
     */
    private function ensureRootAlbumExists(string $rootAlbumPath, int $userId): void
    {
        $this->db->beginTransaction();

        try {
            $rootAlbumExists = $this->db->selectOne(
                    "SELECT album_id FROM gallery_albums WHERE album_path = :path",
                    ['path' => $rootAlbumPath]
            );

            if (!$rootAlbumExists) {
                // 2. Create the root entry
                $this->db->insertUpdate('gallery_albums', [
                    'album_path' => $rootAlbumPath,
                    'title' => 'Root Gallery',
                    'permissions_level' => 2,
                    'owner_user_id' => $userId,
                ]);
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("ERROR: Failed to ensure Root Album existence: " . $e->getMessage());
        }
    }

    /**
     * Gets all albums from the database, including media statistics for the admin overview.
     * @return array List of albums with stats.
     */
    public function getAllAlbumsWithStats(): array
    {
        $sql = "
            SELECT
                a.album_id, a.album_path, a.title, a.permissions_level, a.owner_user_id,
                u.username AS owner_username,
                SUM(gms.views) AS total_media_views
            FROM
                gallery_albums a
            LEFT JOIN
                gallery_media_stats gms ON a.album_id = gms.album_id
            LEFT JOIN
                `user` u ON a.owner_user_id = u.user_id
            GROUP BY
                a.album_id
            ORDER BY
                a.album_path ASC;
        ";
        return $this->db->select($sql);
    }

    /**
     * Resets the view counter and last_view timestamp for all media files in a specific album.
     * @param int $albumId The ID of the album to reset.
     * @return int The number of affected rows.
     */
    public function resetAlbumViewCounter(int $albumId): int
    {
        $affectedRows = $this->db->update('gallery_media_stats', [
            'views' => 0,
            'last_view' => null
                ], "album_id = :id", [
            'id' => $albumId
        ]);

        return $affectedRows;
    }

    /**
     * Retrieves a single album entry by its ID.
     * @param int $albumId The ID of the album.
     * @return array|null Album data or null if not found.
     */
    public function getAlbumById(int $albumId): ?array
    {
        $sql = '
        SELECT
            ga.*,
            u.username AS owner_username
        FROM
            gallery_albums ga
        JOIN
            user u ON u.user_id = ga.owner_user_id
        WHERE
            ga.album_id = :id
    ';
        return $this->db->selectOne($sql, ['id' => $albumId]);
    }

    /**
     * Updates the title, access permissions, and/or owner for a specific album.
     * Executes a single update for the current album (including title) and a subsequent
     * recursive update for descendant albums (excluding title), if required.
     */
    public function updateAlbumPermissions(int $albumId, array $data, array $options = []): bool
    {
        $allowedFields = ['title', 'permissions_level', 'owner_user_id'];
        $filteredData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($filteredData)) {
            return true;
        }

        $success = $this->db->update('gallery_albums', $filteredData, 'album_id = :id', ['id' => $albumId]);

        if (!$success) {
            return false;
        }

        $applyOwner = $options['apply_owner_to_subfolders'] ?? false;
        $applyPermissions = $options['apply_permissions_to_subfolders'] ?? false;

        if ($applyOwner || $applyPermissions) {

            $updateDataForRecursive = $filteredData;
            unset($updateDataForRecursive['title']);

            if (!$applyOwner) {
                unset($updateDataForRecursive['owner_user_id']);
            }
            if (!$applyPermissions) {
                unset($updateDataForRecursive['permissions_level']);
            }

            if (empty($updateDataForRecursive)) {
                return (bool) $success;
            }

            $album = $this->getAlbumById($albumId);
            if (!$album) {
                return false;
            }

            $basePath = trim($album['album_path'], '/');

            if ($basePath === '') {
                $whereCondition = 'album_path != :rootPath';
                $bindings = [
                    'rootPath' => ''
                ];
            } else {
                $whereCondition = 'album_path LIKE :pathPrefix';
                $bindings = [
                    'pathPrefix' => "{$basePath}/%"
                ];
            }

            try {
                $success = $this->db->update('gallery_albums', $updateDataForRecursive, $whereCondition, $bindings);
            } catch (\Exception $e) {
                error_log("Recursive album update failed: " . $e->getMessage());
                return false;
            }
        }

        return (bool) $success;
    }

    // ------------------------------------------------------------------
    // MEDIA ITEM ACTIONS
    // ------------------------------------------------------------------

    /**
     * Retrieves a single media item entry by its ID, including album path and new metadata for context.
     * * NOTE: The query now includes a LEFT JOIN to gallery_media_details to fetch title and description.
     * * @param int $mediaId The ID of the media item.
     * @return array|null Media item data or null if not found.
     */
    public function getMediaItemById(int $mediaId): ?array
    {
        $sql = "
            SELECT
                gms.id, gms.album_id, gms.file_name AS file, gms.views, gms.last_view,
                gms.file_size, gms.file_mtime, gms.media_type,
                ga.album_path,
                gmd.title,
                gmd.description
            FROM
                gallery_media_stats gms
            JOIN
                gallery_albums ga ON gms.album_id = ga.album_id
            LEFT JOIN
                gallery_media_details gmd ON gms.id = gmd.media_id -- LEFT JOIN is crucial here
            WHERE
                gms.id = :id
            ";

        $item = $this->db->selectOne($sql, ['id' => $mediaId]);

        if ($item === null) {
            return null;
        }

        $item['url'] = BASE_URI . 'gallery/media/' . $item['album_path'] . '/' . urlencode($item['file']);

        $pathInfo = pathinfo($item['file']);
        $nameNoExt = $pathInfo['filename'];
        $ext = strtolower($pathInfo['extension']);

        $thumbFile = null;
        if ($item['media_type'] === 'image') {
            $thumbFile = $nameNoExt . '_thumb.' . $ext;
        } elseif ($item['media_type'] === 'video') {
            $thumbFile = $nameNoExt . '_thumb.jpg';
        }

        if ($thumbFile) {
            $item['thumburl'] = BASE_URI . 'gallery/media/' . $item['album_path'] . '/' . urlencode($thumbFile);
        } else {
            $item['thumburl'] = $item['url'];
        }

        return $item;
    }

    /**
     * Updates or inserts the title and description details for a media item
     * based on an associative array of data.
     *
     * It utilizes the Database class's replace() method for UPSERT functionality.
     * * @param int $mediaId The ID of the media item from gallery_media_stats.
     * @param array $updateData Associative array containing 'title' and 'description'.
     * @return bool True on successful DB operation, false otherwise.
     */
    public function updateMediaDetails(int $mediaId, array $updateData): bool
    {
        $title = $updateData['title'] ?? null;
        $description = $updateData['description'] ?? null;

        $data = [
            'media_id' => $mediaId,
            'title' => !empty($title) ? trim($title) : null,
            'description' => !empty($description) ? trim($description) : null,
        ];

        try {
            $result = $this->db->replace('gallery_media_details', $data);

            return (bool) $result;
        } catch (\ckvsoft\CkvException $e) {
            error_log("updateMediaDetails failed for ID {$mediaId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a media item: its main file, its thumbnail, and its DB entry.
     * @param int $mediaId The ID of the media item to delete.
     * @return bool True on success, false otherwise.
     */
    public function deleteMediaItem(int $mediaId): bool
    {
        $mediaItem = $this->getMediaItemById($mediaId);

        if (!$mediaItem) {
            error_log("deleteMediaItem failed: Media ID {$mediaId} not found in DB.");
            return false;
        }

        $albumPath = $mediaItem['album_path'];
        $fileName = $mediaItem['file'];
        $mediaType = $mediaItem['media_type'];

        $fullAlbumPath = $this->basePath . $albumPath;
        $filePath = $fullAlbumPath . '/' . $fileName;

        $pathInfo = pathinfo($fileName);
        $nameNoExt = $pathInfo['filename'];
        $origExt = $pathInfo['extension'];

        $thumbFile = null;
        if ($mediaType === 'image') {
            $thumbFile = $fullAlbumPath . '/' . $nameNoExt . '_thumb.' . $origExt;
        } elseif ($mediaType === 'video') {
            $thumbFile = $fullAlbumPath . '/' . $nameNoExt . '_thumb.jpg';
        }

        try {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            if ($thumbFile && file_exists($thumbFile)) {
                unlink($thumbFile);
            }
        } catch (\Exception $e) {
            error_log("File deletion FAILED for {$filePath}: " . $e->getMessage());
        }

        $dbDeleted = $this->db->delete('gallery_media_stats', 'id = :id', ['id' => $mediaId]);

        return (bool) $dbDeleted;
    }

    public function rotateMedia(int $mediaId, int $degrees): bool|string
    {
        if (!in_array($degrees, [-90, 90, 180]))
            return _('Invalid rotation degree provided.');

        $media = $this->getMediaItemById($mediaId);
        if (!$media)
            return _('Media item not found.');

        $filePath = $this->getFilePath($media['album_path'], $media['file']);
        if (!file_exists($filePath) || !is_writable($filePath))
            return _('Media file missing or not writable.');

        try {
            $image = new Image($filePath);
            $result = $image->rotate($degrees);
            if ($result === true) {
                $pathInfo = pathinfo($media['file']);
                $nameNoExt = $pathInfo['filename'];
                $origExt = $pathInfo['extension'];

                $thumbFile = $media['media_type'] === 'image' ? $this->basePath . $media['album_path'] . '/' . $nameNoExt . '_thumb.' . $origExt : $this->basePath . $media['album_path'] . '/' . $nameNoExt . '_thumb.jpg';

                $deletedThumbs = 0;
                $this->ensureThumbnail($filePath, $thumbFile, true, $deletedThumbs);
            } else
                return _('Rotation failed.');
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return true;
    }

    private function ensureThumbnail(string $filePath, string $thumbFile, bool $isModified, int &$deletedThumbs): void
    {
        try {
            if ($isModified && file_exists($thumbFile)) {
                unlink($thumbFile);
                $deletedThumbs++;
            }
            if (!file_exists($thumbFile)) {
                $image = new Image($filePath, basename($thumbFile), dirname($thumbFile) . '/');
                $image->resize();
            }
        } catch (\Exception $e) {
            error_log("Thumbnail creation failed for {$filePath}: " . $e->getMessage());
        }
    }

    /**
     * Fetches all possible owners (users) from the User table for the dropdown list.
     * @return array
     */
    public function getAllPossibleOwners(): array
    {
        $sql = "SELECT user_id, username FROM `user` ORDER BY username ASC";
        return $this->db->select($sql);
    }

    /**
     * Retrieves all albums owned by the given user.
     * @param int $userId The ID of the current user.
     * @return array List of album data arrays.
     */
    protected function getOwnedAlbums(int $userId): array
    {
        return $this->db->select(
                        "SELECT `album_path`, `title` FROM `gallery_albums` WHERE `owner_user_id` = :user_id",
                        ['user_id' => $userId]
                );
    }
}
