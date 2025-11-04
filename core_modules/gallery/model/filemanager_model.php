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

require_once __DIR__ . '/gallerymanager_model.php';

class Filemanager_Model extends GalleryManager_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Helper to find the nearest ancestor path that is owned by the current user.
     * This is crucial for correct '..' navigation when inside a deep, non-owned path (e.g., 'Auspuff/BOS/Hornet').
     * @param string $path The current path (e.g., 'Auspuff/BOS/Hornet').
     * @param int $currentUserId The ID of the current user.
     * @return string The nearest owned path (e.g., 'Auspuff') or empty string (Virtual Root).
     */
    protected function getNearestOwnedAncestor(string $path, int $currentUserId): string
    {
        $currentPath = $path;
        while (!empty($currentPath)) {
            $parentPath = dirname($currentPath);
            $parentPath = ($parentPath === '.') ? '' : $parentPath;

// Stop if we hit the Virtual Root
            if (empty($parentPath)) {
                return '';
            }

            $parentAlbumData = $this->checkAlbumPermissions($parentPath);
            if ($parentAlbumData && (string) $parentAlbumData['owner_user_id'] === (string) $currentUserId) {
                return $parentPath;
            }

            $currentPath = $parentPath;
        }

        return '';
    }

    /**
     * Lists directory contents for the File Manager, filtering by ownership for regular users.
     *
     * Rules for non-Admin users (Owner-based Access):
     * 1. If current path is NOT owned, access is denied.
     * 2. In the Root (empty path), display:
     * a. Top-level albums owned directly by the user (e.g., 'Motorrad').
     * b. Owned albums nested under non-owned top-level paths, shown as a jump path
     * (e.g., 'Auspuff -> BOS' if Auspuff is not owned, but BOS is).
     * 3. In a Nested Album (owned path), display:
     * a. Direct sub-albums also owned by the user (strict hierarchy).
     * b. Owned albums nested deeper under a non-owned immediate child, shown as a jump path.
     *
     * @param string $relativePath The relative path (e.g., 'user_a/docs').
     * @return array List of items, or ['error' => ...] on access problems.
     */
    public function listDirectoryContents(string $relativePath): array
    {
        $isAdmin = \ckvsoft\Auth::getUserPermissionLevel() >= 3;
        $currentUserId = \ckvsoft\Auth::getUserId();
        $normalizedPath = trim($relativePath, '/');

        if ($isAdmin) {
            // if (!empty($normalizedPath) && !$this->db->selectOne("SELECT 1 FROM `gallery_albums` WHERE `album_path` = :path", ['path' => $normalizedPath])) {
            //     // Logic to handle unmanaged folders for Admin
            // }
            // Retrieve all sub-albums and media (Admin ignores ownership/permissions checks here, using Model's methods that handle permissions on read if applicable)
            $subAlbums = $this->getSubAlbums($normalizedPath);
            $mediaItems = $this->getMediaByAlbum($normalizedPath);
            $items = [];

            if (!empty($normalizedPath)) {
                $parentPath = dirname($normalizedPath);
                $parentPath = ($parentPath === '.') ? '' : $parentPath;
                $items[] = [
                    'name' => '..',
                    'type' => 'album',
                    'path' => $parentPath,
                    'isParent' => true
                ];
            }

            foreach ($subAlbums as $album) {
                $items[] = [
                    'name' => basename($album['path']),
                    'type' => 'album',
                    'path' => $album['path'],
                ];
            }

            foreach ($mediaItems as $media) {
                $items[] = [
                    'name' => basename($media['file']),
                    'type' => $media['type'] ?? 'file',
                    'path' => trim($normalizedPath . '/' . $media['file'], '/'),
                    'url' => $media['url'],
                    'thumburl' => $media['thumburl'],
                    'size' => $media['size'],
                    'date_formatted' => $media['date_formatted']
                ];
            }
            return $items;
        }

        if ($currentUserId === null) {
            return ['error' => _('Permission Denied: You must be logged in to access the file manager.')];
        }

        if (empty($normalizedPath)) {
            $items = [];
            $ownedAlbums = $this->getOwnedAlbums($currentUserId);
            $alreadyListedTopPaths = [];

            foreach ($ownedAlbums as $album) {
                $fullPath = $album['album_path'];
                $pathSegments = explode('/', $fullPath);
                $topPath = $pathSegments[0];

                // If this top-level path has already been processed, skip (handles jump-path logic cleanly)
                if (isset($alreadyListedTopPaths[$topPath])) {
                    continue;
                }

                if (count($pathSegments) === 1) {
                    $albumData = $this->checkAlbumPermissions($topPath);
                    if ($albumData && (string) $albumData['owner_user_id'] === (string) $currentUserId) {
                        $items[] = [
                            'name' => basename($topPath),
                            'type' => 'album',
                            'path' => $topPath,
                        ];
                        $alreadyListedTopPaths[$topPath] = true;
                    }
                } else {
                    $topAlbumData = $this->checkAlbumPermissions($topPath);

                    if ($topAlbumData && (string) $topAlbumData['owner_user_id'] !== (string) $currentUserId) {

                        $displayName = str_replace('/', ' -> ', $fullPath);

                        $items[] = [
                            'name' => $displayName,
                            'type' => 'album',
                            'path' => $fullPath,
                        ];
                        $alreadyListedTopPaths[$topPath] = true;
                    }
                }
            }

            usort($items, fn($a, $b) => strcmp($a['name'], $b['name']));
            return $items;
        }

        $albumData = $this->checkAlbumPermissions($normalizedPath);

        if ($albumData && (string) $albumData['owner_user_id'] === (string) $currentUserId) {

            $subAlbums = $this->getSubAlbums($normalizedPath); // Direct children only
            $mediaItems = $this->getMediaByAlbum($normalizedPath);
            $items = [];
            $alreadyListedPaths = []; // To track direct children added
            if (!empty($normalizedPath)) {
                $backPath = $this->getNearestOwnedAncestor($normalizedPath, $currentUserId);

                $items[] = [
                    'name' => '..',
                    'type' => 'album',
                    'path' => $backPath,
                    'isParent' => true
                ];
            }

            $allOwnedUnderPath = $this->db->select("
                SELECT album_path, title FROM gallery_albums
                WHERE owner_user_id = :userId AND album_path LIKE :pathPrefix
                AND album_path != :currentPath
                ORDER BY album_path
            ", [
                'userId' => $currentUserId,
                'pathPrefix' => $normalizedPath . '/%',
                'currentPath' => $normalizedPath
            ]);

            foreach ($subAlbums as $album) {
                $path = $album['path'];
                $subAlbumData = $this->checkAlbumPermissions($path);

                if ($subAlbumData && (string) $subAlbumData['owner_user_id'] === (string) $currentUserId) {
                    $items[] = [
                        'name' => basename($path),
                        'type' => 'album',
                        'path' => $path,
                    ];
                    $alreadyListedPaths[$path] = true;
                }
            }

            if (is_array($allOwnedUnderPath)) {
                foreach ($allOwnedUnderPath as $album) {
                    $fullPath = $album['album_path'];

                    if (isset($alreadyListedPaths[$fullPath]))
                        continue;

                    $pathRelativeToCurrent = substr($fullPath, strlen($normalizedPath) + 1);
                    $pathSegments = explode('/', $pathRelativeToCurrent);
                    $immediateChildName = $pathSegments[0];
                    $immediateChildPath = $normalizedPath . '/' . $immediateChildName;

                    $immediateChildData = $this->checkAlbumPermissions($immediateChildPath);
                    $isImmediateChildOwned = ($immediateChildData && (string) $immediateChildData['owner_user_id'] === (string) $currentUserId);

                    if (!$isImmediateChildOwned) {
                        $displayName = str_replace('/', ' -> ', $pathRelativeToCurrent);

                        $items[] = [
                            'name' => $displayName,
                            'type' => 'album',
                            'path' => $fullPath,
                        ];
                    }
                }
            }

            foreach ($mediaItems as $media) {
                $items[] = [
                    'name' => basename($media['file']),
                    'type' => $media['type'] ?? 'file',
                    'path' => trim($normalizedPath . '/' . $media['file'], '/'),
                    'url' => $media['url']
                ];
            }

            usort($items, fn($a, $b) => strcmp($a['name'], $b['name']));
            return $items;
        } else {
            return ['error' => _('Permission Denied: You do not own this folder.')];
        }
    }

    /**
     * Creates a new directory. Relies on the inherited checkAlbumPermissions for authorization.
     * @param string $targetPath The relative path of the parent directory (e.g., 'photos/2025').
     * @param string $newDirName The name of the new directory to create (e.g., 'April').
     * @return bool True on success.
     * @throws \ckvsoft\CkvException If the directory already exists or creation fails.
     */
    public function createDirectory(string $targetPath, string $newDirName): bool
    {
        return $this->createPhysicalDirectory($targetPath, $newDirName);
    }

    /**
     * Creates a new physical directory and inserts a corresponding album record into the database.
     * This method assumes permissions were already checked by the calling method.
     * It throws an exception if the directory already exists or creation fails,
     * allowing the Controller to return a clean error message.
     *
     * @param string $targetPath The relative path of the parent directory (e.g., 'photos/2025').
     * @param string $newDirName The name of the new directory to create (e.g., 'April').
     * @return bool True on success (physical folder and DB entry created), false otherwise.
     * @throws \ckvsoft\CkvException If the directory already exists or physical creation fails.
     */
    protected function createPhysicalDirectory(string $targetPath, string $newDirName): bool
    {
        $targetPath = trim($targetPath, '/');
        $newDirName = trim($newDirName, '/');
        $fullRelativePath = trim($targetPath . '/' . $newDirName, '/');

        $absolutePath = $this->basePath . $fullRelativePath;

        if (is_dir($absolutePath)) {
            $errorMessage = _('Directory already exists') . ": " . $newDirName;
            // THROW 1: Directory already exists
            throw new \ckvsoft\CkvException($errorMessage);
        }

        if (!mkdir($absolutePath, 0755, true)) {
            $errorMessage = _('Failed to create directory') . ": {$absolutePath}. " . _('Check file permissions.');
            // THROW 2: Failed to create directory
            throw new \ckvsoft\CkvException($errorMessage);
        }


        $ownerId = \ckvsoft\Auth::getUserId() ?? 1;
        $trimmedPath = trim($fullRelativePath, '/');

        $folderName = basename($trimmedPath);

        $albumData = [
            'album_path' => $trimmedPath,
            'title' => $folderName,
            'permissions_level' => 2,
            'owner_user_id' => $ownerId
        ];

        try {
            $insertId = $this->db->insert('gallery_albums', $albumData);

            if ($insertId === false || $insertId === 0) {
                if (is_dir($absolutePath) && count(scandir($absolutePath)) === 2) {
                    rmdir($absolutePath);
                }
                $errorMessage = _("Failed to insert new album into DB for path: {$fullRelativePath}. Directory cleaned up.");
                error_log($errorMessage);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            if (is_dir($absolutePath) && count(scandir($absolutePath)) === 2) {
                rmdir($absolutePath);
            }
            error_log("DB Exception on album creation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Moves a file or folder. Handles both simple moves (rename/relocation)
     * and folder merges/overwrites.
     * @param string $sourcePath The relative path of the item to move (e.g., 'AlbumA/FolderB').
     * @param string $targetPath The relative path of the target directory (e.g., 'AlbumC').
     * @return bool True on success.
     */
    public function moveItem(string $sourcePath, string $targetPath): bool
    {
        $targetPath = trim($targetPath, '/');
        $sourcePath = trim($sourcePath, '/');
        $itemName = basename($sourcePath); // e.g., FolderB
        $isFolder = !pathinfo($itemName, PATHINFO_EXTENSION);
        $finalNewPath = trim($targetPath . '/' . $itemName, '/'); // e.g., AlbumC/FolderB
        // NOTE: We assume $this->basePath is the clean, absolute root path for albums.
        $absoluteFinalDestination = $this->basePath . $finalNewPath;

        // --- MERGE LOGIC CHECK (FOLDER OVERWRITE) ---
        if ($isFolder && file_exists($absoluteFinalDestination) && is_dir($absoluteFinalDestination)) {
            // Case 1: Folder MERGE operation (must be atomic: use transaction)

            $this->db->beginTransaction();

            try {
                // 1. Get Source and Target Album IDs (pre-check)
                $oldAlbum = $this->db->selectOne("SELECT album_id FROM gallery_albums WHERE album_path = :path", ['path' => $sourcePath]);
                $newAlbum = $this->db->selectOne("SELECT album_id FROM gallery_albums WHERE album_path = :path", ['path' => $finalNewPath]);

                if (!$oldAlbum || !$newAlbum) {
                    throw new \ckvsoft\CkvException("Merge failed: Source or Target album entry not found in DB.");
                }

                $oldAlbumId = $oldAlbum['album_id']; // ID 10
                $newAlbumId = $newAlbum['album_id']; // ID 110
                // 2. Perform Physical Merge recursively. The source folder is DELETED HERE.
                $mergeSuccess = $this->mergeFolderContents($this->basePath . $sourcePath, $absoluteFinalDestination);
                if (!$mergeSuccess) {
                    throw new \ckvsoft\CkvException("Physical merge failed.");
                }

                // 3. DB Step A: Re-assign media of the SOURCE root folder (ID 10) to the TARGET ID (ID 110).
                $dbSuccessMedia = $this->updateMediaIdForSingleAlbum($oldAlbumId, $newAlbumId);

                // 4. DB Step B: Update paths of all sub-albums that were MOVED (ID 11, 12 etc.).
                $dbSuccessSubAlbums = $this->updateSubAlbumPathsAfterMerge($sourcePath, $finalNewPath);

                // 5. DB Step C: Delete the original SOURCE album DB entry (ID 10).
                $dbSuccessDelete = $this->db->delete('gallery_albums', 'album_id = :id', ['id' => $oldAlbumId]);

                if (!$dbSuccessMedia || !$dbSuccessSubAlbums || $dbSuccessDelete === false) {
                    throw new \ckvsoft\CkvException("Database merge updates failed.");
                }

                // *** CRITICAL CHANGE: NO PHYSICAL FOLDER DELETION IS NEEDED HERE. ***
                // *** The physical folder was already cleaned up by mergeFolderContents. ***

                $this->db->commit();
                return true;
            } catch (\Exception $e) {
                $this->db->rollBack();
                error_log("Folder MERGE FAILED in transaction: " . $e->getMessage());
                return false;
            }
        }
        // --- END MERGE LOGIC ---
        // --- SIMPLE MOVE/RENAME LOGIC (EXECUTED IF NO MERGE WAS REQUIRED) ---

        $physicalMoveSuccess = $this->movePhysicalItem($this->basePath . $sourcePath, $this->basePath . $targetPath);

        if (!$physicalMoveSuccess) {
            return false;
        }

        // DB Update Logic for SIMPLE MOVE/RENAME
        if ($isFolder) {
            $dbSuccess = $this->updateAlbumPath($sourcePath, $finalNewPath);
        } else {
            $dbSuccess = $this->updateMediaAlbumId($itemName, dirname($sourcePath), $targetPath);
        }

        return $dbSuccess;
    }

    /**
     * Recursively moves the CONTENTS of the source directory into the existing target directory.
     * This function handles the physical MERGE operation and ensures clean up of the source.
     *
     * @param string $sourceDir Absolute path of the source folder.
     * @param string $targetDir Absolute path of the target folder (must exist).
     * @return bool True on success, false if any critical file operation fails.
     */
    private function mergeFolderContents(string $sourceDir, string $targetDir): bool
    {
        // Safety checks
        if (!is_dir($targetDir)) {
            error_log("mergeFolderContents failed: Target directory does not exist: {$targetDir}");
            return false;
        }

        $success = true;

        if (!is_dir($sourceDir) || !is_readable($sourceDir)) {
            error_log("mergeFolderContents failed: Source directory is invalid or unreadable: {$sourceDir}");
            return false;
        }

        try {
            $iterator = new \DirectoryIterator($sourceDir);
        } catch (\UnexpectedValueException $e) {
            error_log("mergeFolderContents failed: Cannot open directory {$sourceDir}. Error: " . $e->getMessage());
            return false;
        }

        foreach ($iterator as $item) {
            if ($item->isDot())
                continue;

            $sourcePath = $item->getPathname();
            $targetPath = $targetDir . '/' . $item->getFilename();
            $itemName = $item->getFilename();

            if ($item->isDir()) {
                // --- RECURSIVE CHECK FOR SUB-FOLDER MERGE ---
                if (is_dir($targetPath)) {
                    $success &= $this->mergeFolderContents($sourcePath, $targetPath);
                } else {
                    // Simple move of the entire sub-folder.
                    try {
                        // Assuming movePhysicalItem is robust and handles directory moves
                        $success &= $this->movePhysicalItem($sourcePath, $targetDir);
                    } catch (\Exception $e) {
                        error_log("Physical move failed during sub-folder simple move of {$itemName}: " . $e->getMessage());
                        $success = false;
                    }
                }
            } else {
                // --- FILE HANDLING ---
                // Skip known thumbnail files to avoid double-processing (leading to "Source file/folder does not exist" error).
                if (str_contains($itemName, '_thumb')) {
                    error_log("Skipping known thumbnail file {$itemName} during merge iteration.");
                    continue;
                }

                // Primary file move (including associated thumbnails handled internally by movePhysicalItem).
                try {
                    // Using overwrite=true for file merge collisions
                    $success &= $this->movePhysicalItem($sourcePath, $targetDir, true);
                } catch (\Exception $e) {
                    error_log("File move failed during merge of {$itemName}: " . $e->getMessage());
                    $success = false;
                }
            }
        }

        // Clean up the source folder after moving its contents.
        if ($success) {
            // *** CLEAN CODE: Check return value of rmdir() instead of using @ ***
            if (rmdir($sourceDir) === false) {
                // This indicates the directory might not be empty (due to hidden files or other issues)
                // or permissions are insufficient. We log a warning but DO NOT set $success = false,
                // as the merge integrity (files in target, DB updated) is preserved.
                error_log("Warning: Failed to remove source directory {$sourceDir}. It may not be empty or permissions are lacking.");
            }
        }

        return $success;
    }

    /**
     * Updates the album_id of all media items that previously belonged to $oldAlbumId
     * to the $newAlbumId. Used only for the media directly in the main merged folder
     * (since the old album entry will be deleted).
     *
     * @param int $oldAlbumId The ID of the source album (e.g., ID 10).
     * @param int $newAlbumId The ID of the target album (e.g., ID 110).
     * @return bool True on success, false otherwise.
     */
    public function updateMediaIdForSingleAlbum(int $oldAlbumId, int $newAlbumId): bool
    {
        // Use $this->db->update() with a custom DbExpr to set the new album_id
        $updatedRows = $this->db->update('gallery_media_stats',
                ['album_id' => $newAlbumId],
                'album_id = :oldId',
                ['oldId' => $oldAlbumId]
        );

        return $updatedRows !== false;
    }

    /**
     * Updates the paths of all sub-albums that were MOVED (not merged) within the hierarchy.
     * This is crucial because sub-albums that are only moved retain their ID but need a path update.
     * * @param string $oldPath The original relative path of the source album (e.g., 'AlbumA/FolderB').
     * @param string $newPath The new relative path of the target album (e.g., 'AlbumC/FolderB').
     * @return bool True on success, false otherwise.
     */
    public function updateSubAlbumPathsAfterMerge(string $oldPath, string $newPath): bool
    {
        $trimmedOldPath = trim($oldPath, '/');
        $trimmedNewPath = trim($newPath, '/');

        $oldPrefix = $trimmedOldPath . '/';
        $newPrefix = $trimmedNewPath . '/';

        // We use DbExpr for the powerful and atomic REPLACE function in MySQL.
        $data = [
            'album_path' => new \ckvsoft\DbExpr(
                    "REPLACE(album_path, :oldPrefix, :newPrefix)"
            )
        ];

        // The condition matches all sub-albums (paths starting with old prefix + slash).
        $where = "album_path LIKE :prefixWildcard";
        $bindWhereParams = [
            'oldPrefix' => $oldPrefix,
            'newPrefix' => $newPrefix,
            'prefixWildcard' => $oldPrefix . '%'
        ];

        // Assuming $this->db->update is implemented to combine parameters from $data (for DbExpr)
        // and $bindWhereParams (for WHERE clause) before executing the query.
        $success = $this->db->update('gallery_albums',
                $data,
                $where,
                $bindWhereParams);

        return $success !== false;
    }

    /**
     * Handles the actual physical file/folder move operation.
     * Moves the main file/folder AND any accompanying * _thumb.* asset.
     * @param string $sourcePath The full path of the item to move.
     * @param string $targetPath The full path of the target directory.
     * @return bool True on success.
     * @throws \ckvsoft\CkvException If the physical move fails (Source/Destination errors, rename errors).
     */
    public function movePhysicalItem(string $sourcePath, string $targetPath): bool
    {
        $itemName = basename($sourcePath);
        $finalDestination = rtrim($targetPath, '/') . '/' . $itemName;

        $filenameWithoutExt = pathinfo($itemName, PATHINFO_FILENAME);
        $oldThumbPattern = dirname($sourcePath) . '/' . $filenameWithoutExt . '_thumb.*';
        $thumbsToMove = glob($oldThumbPattern);
        $thumbToMove = $thumbsToMove[0] ?? null;

        if (!file_exists($sourcePath)) {
            // THROW 3: Source does not exist
            throw new \ckvsoft\CkvException(_("Source file/folder does not exist") . ": {$sourcePath}");
        }

        if (file_exists($finalDestination)) {
            // THROW 4: Destination already exists
            throw new \ckvsoft\CkvException(_("Destination already contains an item with the same name") . ": {$finalDestination}");
        }

        set_error_handler(function ($errno, $errstr) use ($sourcePath, $finalDestination) {
            if ($errno === E_WARNING) {
                // THROW 5: Rename failed
                throw new \ckvsoft\CkvException(_("Rename failed") . ": {$errstr} ({$sourcePath} → {$finalDestination})");
            }
            return false;
        });

        rename($sourcePath, $finalDestination);

        restore_error_handler();

        if ($thumbToMove) {
            $thumbItemName = basename($thumbToMove);
            $thumbDestination = rtrim($targetPath, '/') . '/' . $thumbItemName;

            set_error_handler(function () {
                return true;
            });

            $thumbMoved = rename($thumbToMove, $thumbDestination);

            restore_error_handler();
        }

        return true;
    }

    protected function getMediaType(string $extension): string
    {
        if (in_array($extension, self::SUPPORTED_VIDEO_EXT)) {
            return 'video';
        }
        if (in_array($extension, self::SUPPORTED_IMAGE_EXT)) {
            return 'image';
        }
        return 'file'; // Default fallback
    }

    /**
     * Uploads a file and registers it in the database for visibility.
     * This method ensures the file's metadata (type, size, mtime) is correctly
     * persisted to the gallery_media_stats table, but skips database registration
     * for thumbnail files (those ending in '_thumb').
     *
     * @param string $targetpath The relative path of the album (e.g., 'photos/summer').
     * @param string $filename The name of the file field in $_FILES or the target file name.
     * @param bool $overwrite Whether to overwrite an existing file.
     * @return bool True on successful upload (and successful DB registration for non-thumbs), false otherwise.
     */
    public function uploadImage($targetpath, $filename, $overwrite = true)
    {
        $targetpath = trim($targetpath, '/');
        $directory = $this->basePath . $targetpath;

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $allowedExtensions = array_merge(self::SUPPORTED_IMAGE_EXT, self::SUPPORTED_VIDEO_EXT);

        if (!in_array($extension, $allowedExtensions)) {
            error_log("Upload failed: File extension '{$extension}' is not permitted.");
            return false;
        }

        $album = $this->db->selectOne(
                "SELECT album_id FROM gallery_albums WHERE album_path = :path",
                ['path' => $targetpath]
        );

        if (!$album) {
            error_log("Upload failed: Target album '{$targetpath}' not found in DB.");
            return false;
        }
        $albumId = $album['album_id'];

        $upload = new \ckvsoft\Upload($filename, $directory, $filename, $overwrite);
        $success = $upload->submit();

        if ($success) {
            $fullPath = $directory . '/' . $filename;

            if (!file_exists($fullPath)) {
                error_log("CRITICAL: Upload successful, but file not found at: {$fullPath}");
                return false;
            }

            $nameNoExt = pathinfo($filename, PATHINFO_FILENAME);
            $isThumbnail = str_ends_with(strtolower($nameNoExt), '_thumb');

            if ($isThumbnail) {
                return true;
            }

            $mediaType = $this->getMediaType($extension);
            $fileSize = filesize($fullPath);
            $fileMtime = filemtime($fullPath);

            $mediaData = [
                'album_id' => $albumId,
                'file_name' => $filename,
                'media_type' => $mediaType,
                'file_size' => $fileSize,
                'file_mtime' => $fileMtime
            ];

            $result = $this->db->insertUpdate('gallery_media_stats', $mediaData, ['album_id', 'file_name']);

            if ($result === false) {
                error_log("DB registration/update failed for file '{$filename}' in album ID '{$albumId}'.");
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Deletes a file or folder (album) by relative path.
     * This is the main entry point from the Controller.
     *
     * @param string $relativePath The item's relative path (e.g., 'albumA/file.jpg' or 'albumA/folderB').
     * @return bool True on success (physical and DB deletion).
     * @throws \ckvsoft\CkvException If the item does not exist, database fails, or permissions are denied.
     */
    public function deleteItem(string $relativePath): bool
    {
        $relativePath = trim($relativePath, '/');
        $absolutePath = $this->basePath . $relativePath;
        $itemName = basename($relativePath);
        $parentPath = dirname($relativePath);
        $parentPath = ($parentPath === '.') ? '' : $parentPath;

        $currentUserId = \ckvsoft\Auth::getUserId();

        if (!file_exists($absolutePath)) {

            $albumExistsInDb = $this->db->selectOne("SELECT 1 FROM gallery_albums WHERE album_path = :path", ['path' => $relativePath]);

            if (!empty($albumExistsInDb)) {
                error_log("DB CLEANUP: Album '{$relativePath}' is physically missing. Deleting DB entry only.");

                return $this->deleteAlbumAndContents($relativePath);
            }

            $album = $this->db->selectOne("SELECT album_id FROM gallery_albums WHERE album_path = :path", ['path' => $parentPath]);
            if ($album) {
                $mediaExistsInDb = $this->db->selectOne(
                        "SELECT 1 FROM gallery_media_stats WHERE file_name = :file AND album_id = :aid",
                        ['file' => $itemName, 'aid' => $album['album_id']]
                );

                if (!empty($mediaExistsInDb)) {
                    error_log("DB CLEANUP: Media file '{$relativePath}' is physically missing. Deleting DB entry only.");
                    return $this->deleteMediaRecord($itemName, $parentPath);
                }
            }

            // THROW 6: Item not found
            throw new \ckvsoft\CkvException(_("Item not found") . ": {$relativePath}");
        }

        $isFolder = is_dir($absolutePath);

        $albumPathToCheck = $isFolder ? $relativePath : $parentPath;

        $albumData = $this->checkAlbumPermissions($albumPathToCheck);

        if (!$albumData) {
            // THROW 7: Associated album/folder could not be found for permission check
            throw new \ckvsoft\CkvException(_("The associated album/folder could not be found for permission check") . ": {$albumPathToCheck}");
        }

        // Only perform the strict ownership check if the user is NOT an administrator.
        if (!\ckvsoft\Auth::hasRole('admin')) {
            // Standard Ownership Check
            if ((string) $albumData['owner_user_id'] !== (string) $currentUserId) {
                // THROW 8: Permission Denied (Ownership)
                throw new \ckvsoft\CkvException(_("Permission Denied: You do not have ownership rights to delete this item") . ". ({$relativePath})");
            }
        }

        $this->deletePhysicalItem($absolutePath);

        if ($isFolder) {
            $dbSuccess = $this->deleteAlbumAndContents($relativePath);
        } else {
            $dbSuccess = $this->deleteMediaRecord($itemName, $parentPath);
        }

        if (!$dbSuccess) {
            error_log("CRITICAL: DB deletion failed for '{$relativePath}'. File system item is gone.");
        }

        return $dbSuccess;
    }

    /**
     * Recursively deletes a file or directory from the file system.
     * This method also handles the optional deletion of associated thumbnails without failing if they are missing.
     *
     * @param string $absolutePath The absolute path of the file or directory to delete.
     * @return bool True on successful deletion.
     * @throws \ckvsoft\CkvException If the directory is not empty or if deletion fails for the main item.
     */
    protected function deletePhysicalItem(string $absolutePath): bool
    {
        if (!file_exists($absolutePath) && strpos($absolutePath, '_thumb.') !== false) {
            return true;
        }

        if (!file_exists($absolutePath)) {
            // THROW 9: Cannot delete: Item not found
            throw new \ckvsoft\CkvException(_("Cannot delete: Item not found at path") . ": {$absolutePath}");
        }

        if (is_dir($absolutePath)) {
            $files = array_diff(scandir($absolutePath) ?: [], ['.', '..']);

            foreach ($files as $file) {
                $this->deletePhysicalItem(rtrim($absolutePath, '/') . '/' . $file);
            }

            if (!rmdir($absolutePath)) {
                // THROW 10: Failed to remove directory
                throw new \ckvsoft\CkvException(_("Failed to remove directory") . ": {$absolutePath}. " . _("Directory might not be empty."));
            }

            return true;
        } else {
            if (!unlink($absolutePath)) {
                // THROW 11: Failed to delete file
                throw new \ckvsoft\CkvException(_("Failed to delete file") . ": {$absolutePath}. " . _("Check file permissions."));
            }

            $itemName = basename($absolutePath);
            $filenameWithoutExt = pathinfo($itemName, PATHINFO_FILENAME);
            $parentDir = dirname($absolutePath);

            $thumbPattern = $parentDir . '/' . $filenameWithoutExt . '_thumb.*';
            $thumbsToDelete = glob($thumbPattern);

            foreach ($thumbsToDelete as $thumbPath) {
                if (file_exists($thumbPath)) {
                    if (!unlink($thumbPath)) {
                        error_log("Warning: Could not delete thumbnail: " . $thumbPath);
                    }
                }
            }

            return true;
        }
    }

    /**
     * Deletes a single media record from the database using $this->db->delete().
     * * @param string $fileName The file name.
     * @param string $albumPath The parent album's relative path.
     * @return bool True on success (or if record was already gone).
     */
    protected function deleteMediaRecord(string $fileName, string $albumPath): bool
    {
        $album = $this->db->selectOne("SELECT album_id FROM gallery_albums WHERE album_path = :path", ['path' => $albumPath]);

        if (!$album) {
            return true;
        }

        $rowsAffected = $this->db->delete(
                'gallery_media_stats',
                'album_id = :aid AND file_name = :file',
                [
                    'aid' => $album['album_id'],
                    'file' => $fileName
                ]
        );

        return $rowsAffected !== false;
    }

    /**
     * Deletes an album record and all related media/sub-album records recursively using a transaction.
     * Uses $this->db->select() and $this->db->delete().
     * @param string $albumPath The path of the album to delete.
     * @return bool True on success.
     */
    protected function deleteAlbumAndContents(string $albumPath): bool
    {
        $this->db->beginTransaction();

        try {
            $albumRecords = $this->db->select("
                SELECT album_id FROM gallery_albums
                WHERE album_path = :path OR album_path LIKE :pathPrefix
            ", [
                'path' => $albumPath,
                'pathPrefix' => $albumPath . '/%'
            ]);

            if (!is_array($albumRecords)) {
                $albumRecords = [];
            }

            $ids = array_column($albumRecords, 'album_id');

            if (empty($ids)) {
                $this->db->commit();
                return true;
            }

            $idList = implode(',', array_map('intval', $ids));

            $sqlDeleteMedia = "DELETE FROM gallery_media_stats WHERE album_id IN ({$idList})";
            $mediaDeleted = $this->db->query($sqlDeleteMedia);

            $sqlCondition = "album_path = :path OR album_path LIKE :pathPrefix";
            $params = [
                'path' => $albumPath,
                'pathPrefix' => $albumPath . '/%'
            ];

            $albumsDeleted = $this->db->delete('gallery_albums', $sqlCondition, $params);

            if ($mediaDeleted === false || $albumsDeleted === false) {
                $this->db->rollBack();
                error_log("DB transaction failed during deletion of album content or album record.");
                return false;
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("DB Exception during deleteAlbumAndContents: " . $e->getMessage());
            return false;
        }
    }
}
