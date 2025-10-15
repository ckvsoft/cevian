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
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

use ckvsoft\mvc\Config;
use ckvsoft\Progress;

require_once __DIR__ . '/gallery_model.php';

/**
 * Model responsible for administrative tasks: rescanning albums, synchronizing
 * database entries with the filesystem, and managing media statistics.
 */
class GalleryManager_Model extends Gallery_Model
{

    private string $basePath;

    // Constant specific to this model's batch processing
    const UPDATE_FREQUENCY = 10;

    /**
     * Constructor receives Gallery_Model (Dependency Injection) to eliminate
     * internal duplication of constants and helper methods.
     * @param Gallery_Model $galleryModel The main gallery model dependency.
     */
    public function __construct()
    {
        parent::__construct();

        $relativePath = trim(Config::get('paths.albums_relative_path') ?? 'public/albums/', '/');
        $this->basePath = __DIR__ . '/../../../' . $relativePath . '/';
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
                // Nur oberstes Verzeichnis scannen, kein rekursiv
                $dirIterator = new \DirectoryIterator($fullPath);

                foreach ($dirIterator as $item) {
                    if ($item->isDot() || $item->isDir()) {
                        continue;
                    }

                    $origExt = $item->getExtension();
                    $ext = strtolower($origExt);
                    $nameNoExt = $item->getBasename('.' . $origExt);

                    // Thumbnails überspringen
                    if (str_ends_with($nameNoExt, '_thumb')) {
                        continue;
                    }

                    // Nur unterstützte Bild-/Video-Extensions zählen
                    if (in_array($ext, $imageExt) || in_array($ext, $videoExt)) {
                        $totalCount++;
                    }
                }
            } catch (\Exception $e) {
                // Directory nicht lesbar, ignorieren
                error_log("Cannot read directory {$fullPath}: " . $e->getMessage());
            }
        }

        error_log("totalcount: " . $totalCount);
        return $totalCount;
    }

    // ------------------------------------------------------------------
    // MEDIA RESCAN LOGIC
    // ------------------------------------------------------------------

    /**
     * Scans the filesystem, synchronizes gallery_media_stats table, and removes orphaned media/thumbnails.
     * Includes Progress Tracking.
     * @param int $progressId The ID for progress tracking.
     * @return array Summary of the rescan results.
     */
    public function rescanAlbumMedia(int $progressId): array
    {
        $addedCount = 0;
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

                // Thumbnails und nicht unterstützte Dateitypen überspringen
                if (str_ends_with($nameNoExt, '_thumb')) {
                    continue;
                }
                if (!in_array($ext, $imageExt) && !in_array($ext, $videoExt)) {
                    continue;
                }

                $fileName = $file;

                // Prüfen, ob Datei bereits in DB vorhanden ist
                $existing = $this->db->selectOne(
                        "SELECT id FROM gallery_media_stats WHERE album_id = :aid AND file_name = :file",
                        ['aid' => $albumId, 'file' => $fileName]
                );

                if (!$existing) {
                    $this->db->insertUpdate('gallery_media_stats', [
                        'album_id' => $albumId,
                        'file_name' => $fileName
                    ]);
                    $addedCount++;
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

        // 3. Orphaned DB-Einträge und Thumbnails aufräumen
        $dbMediaEntries = $this->db->select("SELECT id, album_id, file_name FROM gallery_media_stats");
        error_log("progress current: " . $progress->getCurrent());

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
                if (in_array($ext, $imageExt)) {
                    $thumbFile = $fullAlbumPath . '/' . $nameNoExt . '_thumb.' . $origExt;
                } elseif (in_array($ext, $videoExt)) {
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
            'skipped_count' => $skippedCount,
            'deleted_db_entries' => $deletedDbEntries,
            'deleted_thumbnails' => $deletedThumbs
        ];
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
            // Use iterator to scan all subdirectories
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

        // 1. Progress Initialisierung (Wenn ID vorhanden)
        if ($progressId !== null) {
            $progress = new \ckvsoft\Progress(0, $progressId, $this->db);
            $progress->updateProgress(0);
        }

        $rootPathInDb = '';
        $this->ensureRootAlbumExists($rootPathInDb, $currentUserId);

        $fsPaths = $this->getAvailableAlbumPaths();

        if ($progressId !== null) {
            $progress->updateProgress(30);
        }

        // --- Phase 2: DB-Daten holen und vergleichen (ca. 40%) ---
        $dbAlbums = $this->db->select("SELECT album_id, album_path FROM `gallery_albums`");
        $dbPaths = array_column($dbAlbums, 'album_path');
        $dbPathToId = array_column($dbAlbums, 'album_id', 'album_path');

        if ($progressId !== null) {
            $progress->updateProgress(40);
        }

        // --- Phase 3: Neue Alben hinzufügen (ca. 60%) ---
        $pathsToAdd = array_diff($fsPaths, $dbPaths);

        foreach ($pathsToAdd as $path) {
            try {
                $ownerId = $currentUserId ?? 1;

                $data = [
                    'album_path' => trim($path, '/'),
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

        // --- Phase 4: Veraltete Alben löschen (ca. 90%) ---
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

                    // IMPORTANT: Also delete all associated media statistics!
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

        // 2. Progress Finalisierung (100%)
        if ($progressId !== null) {
            $progress->updateProgress(100);
        }

        return $results;
    }

    /**
     * Ensures the primary album root directory (gallery base path) is registered as an album
     * in the gallery_albums table, if it's not already present.
     *
     * @param string $rootAlbumPath The path representation for the root album (e.g., '/').
     * @param int $userId The user ID to assign as the creator/owner.
     * @return void
     */
    private function ensureRootAlbumExists(string $rootAlbumPath, int $userId): void
    {
        $this->db->beginTransaction();

        try {
            // 1. Prüfen, ob der Root-Eintrag existiert
            $rootAlbumExists = $this->db->selectOne(
                    "SELECT album_id FROM gallery_albums WHERE album_path = :path",
                    ['path' => $rootAlbumPath]
            );

            if (!$rootAlbumExists) {
                // 2. Erstellen des Root-Eintrags
                $this->db->insertUpdate('gallery_albums', [
                    'album_path' => $rootAlbumPath,
                    'title' => 'Root Gallery',
                    'permissions_level' => 2,
                    'owner_user_id' => $userId,
                ]);
                error_log("INFO: Root Album ('{$rootAlbumPath}') entry created in gallery_albums.");
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
     * Updates the access permissions and/or owner for a specific album ID.
     * @param int $albumId The ID of the album to update.
     * @param array $data Associative array containing update fields (e.g., 'permissions_level', 'owner_user_id').
     * @return bool True on successful update, false otherwise.
     */
    public function updateAlbumPermissions(int $albumId, array $data): bool
    {
        $allowedFields = ['permissions_level', 'owner_user_id'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            return false;
        }

        return $this->db->update('gallery_albums', $updateData, 'album_id = :id', ['id' => $albumId]);
    }

// ------------------------------------------------------------------
// MEDIA ITEM ACTIONS
// ------------------------------------------------------------------

    /**
     * Retrieves a single media item entry by its ID, including album path for context.
     * @param int $mediaId The ID of the media item.
     * @return array|null Media item data or null if not found.
     */
    public function getMediaItemById(int $mediaId): ?array
    {
        $sql = "
        SELECT
            gms.id, gms.album_id, gms.file_name AS file, gms.views, gms.last_view,
            ga.album_path
        FROM
            gallery_media_stats gms
        JOIN
            gallery_albums ga ON gms.album_id = ga.album_id
        WHERE
            gms.id = :id
    ";

        $item = $this->db->selectOne($sql, ['id' => $mediaId]);

        if ($item === null) {
            return null;
        }

        // Füge die URL- und Thumb-Links hinzu (Nutzt die Logik aus Gallery_Model/Manager)
        $item['url'] = BASE_URI . 'gallery/media/' . $item['album_path'] . '/' . urlencode($item['file']);

        // Annahme: getMediaThumbLink ist entweder in Gallery_Model oder muss hier implementiert werden.
        // Da wir von Gallery_Model erben, nutzen wir hier das manuelle Erstellen des Thumb-Links,
        // analog zur Logik in der getMediaByAlbum Methode.

        $pathInfo = pathinfo($item['file']);
        $nameNoExt = $pathInfo['filename'];
        $ext = strtolower($pathInfo['extension']);

        $thumbFile = null;
        if (in_array($ext, self::SUPPORTED_IMAGE_EXT)) {
            // Bilder nutzen die gleiche Extension für den Thumb
            $thumbFile = $nameNoExt . '_thumb.' . $ext;
        } elseif (in_array($ext, self::SUPPORTED_VIDEO_EXT)) {
            // Videos nutzen den vordefinierten Thumb (z.B. .jpg)
            $thumbFile = $nameNoExt . '_thumb.jpg';
        }

        if ($thumbFile) {
            $item['thumburl'] = BASE_URI . 'gallery/media/' . $item['album_path'] . '/' . urlencode($thumbFile);
        } else {
            // Fallback-Logik, falls kein Thumb-Link ermittelt werden kann
            $item['thumburl'] = $item['url'];
        }


        return $item;
    }

    /**
     * Placeholder for deleting a media item (File, Thumbnail, and DB entry).
     * Needs implementation!
     * @param int $mediaId The ID of the media item to delete.
     * @return bool True on success, false otherwise.
     */
    public function deleteMediaItem(int $mediaId): bool
    {
        // TODO: 1. DB-Eintrag holen (um Dateipfade zu ermitteln)
        // TODO: 2. Hauptdatei und Thumbnail löschen (unlink)
        // TODO: 3. DB-Eintrag aus gallery_media_stats löschen
        // Fürs Erste: Dummy-Rückgabe, bis die Logik implementiert ist
        return true;
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
}
