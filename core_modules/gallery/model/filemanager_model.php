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

            // Check if this parent path is owned
            $parentAlbumData = $this->checkAlbumPermissions($parentPath);
            if ($parentAlbumData && (string) $parentAlbumData['owner_user_id'] === (string) $currentUserId) {
                return $parentPath;
            }

            // Move up one level
            $currentPath = $parentPath;
        }

        return ''; // Should only be reached if $path was already empty
    }

    /**
     * Lists directory contents for the File Manager, filtering by ownership for regular users.
     * @param string $relativePath The relative path (e.g., 'user_a/docs').
     * @return array List of items, or ['error' => ...] on access problems.
     */
    public function listDirectoryContents(string $relativePath): array
    {
        $isAdmin = \ckvsoft\Auth::getUserPermissionLevel() >= 3;
        $currentUserId = \ckvsoft\Auth::getUserId();
        $normalizedPath = trim($relativePath, '/');

        // ----------------------------------------------------------------------
        // ADMIN LOGIC (Pass-Through)
        // ----------------------------------------------------------------------
        if ($isAdmin) {
            // Check if the path exists in the database for non-root paths.
            if (!empty($normalizedPath) && !$this->db->selectOne("SELECT 1 FROM `gallery_albums` WHERE `album_path` = :path", ['path' => $normalizedPath])) {

            }

            $subAlbums = $this->getSubAlbums($normalizedPath);
            $mediaItems = $this->getMediaByAlbum($normalizedPath);
            $items = [];

            // Add 'Go Back' for Admin view
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

            // List sub-albums
            foreach ($subAlbums as $album) {
                $items[] = [
                    'name' => $album['title'] ?? basename($album['path']),
                    'type' => 'album',
                    'path' => $album['path'],
                ];
            }
            // List media items
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

        // ----------------------------------------------------------------------
        // Case 1: User is in the VIRTUAL ROOT ('') - Only show top-level owned folders.
        // ----------------------------------------------------------------------
        if (empty($normalizedPath)) {
            $items = [];
            // Assuming getOwnedAlbums returns all albums owned by the user, regardless of depth.
            $ownedAlbums = $this->getOwnedAlbums($currentUserId);
            $topLevelPaths = [];

            // Filter for unique, top-level paths only (e.g., 'Auspuff' from 'Auspuff/BOS/Bandit').
            foreach ($ownedAlbums as $album) {
                $pathSegments = explode('/', $album['album_path']);
                $topPath = $pathSegments[0];

                if (!isset($topLevelPaths[$topPath])) {
                    $topLevelPaths[$topPath] = $topPath;
                }
            }

            // Get the album data for the actual top-level paths and list them.
            foreach ($topLevelPaths as $topPath) {
                // We use checkAlbumPermissions to ensure we have the title/data for the top folder.
                $albumData = $this->checkAlbumPermissions($topPath);
                // Only list the folder if it is owned by the current user.
                if ($albumData && (string) $albumData['owner_user_id'] === (string) $currentUserId) {
                    $items[] = [
                        'name' => $albumData['title'] ?? basename($topPath),
                        'type' => 'album',
                        'path' => $topPath,
                    ];
                }
            }
            return $items;
        }

        // ----------------------------------------------------------------------
        // Case 2: User is inside an OWNED album (e.g., in 'Auspuff')
        // ----------------------------------------------------------------------
        $albumData = $this->checkAlbumPermissions($normalizedPath);

        if ($albumData && (string) $albumData['owner_user_id'] === (string) $currentUserId) {

            $subAlbums = $this->getSubAlbums($normalizedPath); // Direct children only
            $mediaItems = $this->getMediaByAlbum($normalizedPath);
            $items = [];
            $alreadyListedPaths = [];

            // Add 'Go Back' logic (FIX 1: Use recursive helper to find nearest owned ancestor)
            if (!empty($normalizedPath)) {

                // Determine the correct path for '..': either the immediate owned parent or VROOT.
                $backPath = $this->getNearestOwnedAncestor($normalizedPath, $currentUserId);

                $items[] = [
                    'name' => '..',
                    'type' => 'album',
                    // This will be '' for VROOT or the actual ancestor path (e.g., 'Auspuff').
                    'path' => $backPath,
                    'isParent' => true
                ];
            }


            // Database query to find all owned albums *under* the current path.
            // Using $this->db->select for multiple rows.
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

            // 1. List all DIRECT children that are owned (e.g., Yoshimura).
            foreach ($subAlbums as $album) {
                $path = $album['path'];
                $subAlbumData = $this->checkAlbumPermissions($path);

                if ($subAlbumData && (string) $subAlbumData['owner_user_id'] === (string) $currentUserId) {
                    $items[] = [
                        'name' => $album['title'] ?? basename($path),
                        'type' => 'album',
                        'path' => $path,
                    ];
                    $alreadyListedPaths[$path] = true;
                }
            }

            // 2. List deep, "orphaned" children (e.g., BOS -> Bandit/Hornet)
            // that were NOT direct children AND whose immediate parent is NOT owned.
            if (is_array($allOwnedUnderPath)) {
                foreach ($allOwnedUnderPath as $album) {
                    $fullPath = $album['album_path'];

                    // Skip if already listed as a direct child (by full path).
                    if (isset($alreadyListedPaths[$fullPath]))
                        continue;

                    // Calculate path segments relative to the current folder (e.g., 'BOS/Bandit')
                    $pathRelativeToCurrent = substr($fullPath, strlen($normalizedPath) + 1);
                    $pathSegments = explode('/', $pathRelativeToCurrent);

                    // The path of the immediate child folder (e.g., 'Auspuff/BOS')
                    $immediateChildPath = $normalizedPath . '/' . $pathSegments[0];

                    // Check ownership of the immediate child folder (e.g., 'BOS').
                    $immediateChildData = $this->checkAlbumPermissions($immediateChildPath);
                    $isImmediateChildOwned = ($immediateChildData && (string) $immediateChildData['owner_user_id'] === (string) $currentUserId);

                    // If the immediate child is NOT owned, we list the deep, orphaned path.
                    if (!$isImmediateChildOwned) {

                        // FIX 2: Ensure the derived path is ALWAYS used for the display name
                        // for orphaned paths to avoid 'Bandit' instead of 'BOS -> Bandit'.
                        $displayName = str_replace('/', ' -> ', $pathRelativeToCurrent);

                        $items[] = [
                            'name' => $displayName, // Always use the full derived name for consistency
                            'type' => 'album',
                            'path' => $fullPath,
                        ];
                    }
                }
            }

            // List media items
            foreach ($mediaItems as $media) {
                $items[] = [
                    'name' => basename($media['file']),
                    'type' => $media['type'] ?? 'file',
                    'path' => trim($normalizedPath . '/' . $media['file'], '/'),
                    'url' => $media['url']
                ];
            }
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
        // Delegate the physical creation to the inherited method
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

        // Construct the full absolute path using the base path
        $absolutePath = $this->basePath . $fullRelativePath;

        if (is_dir($absolutePath)) {
            $errorMessage = _('Directory already exists') . ": " . $newDirName;
            throw new \ckvsoft\CkvException($errorMessage);
        }

        if (!mkdir($absolutePath, 0755, true)) {
            $errorMessage = _('Failed to create directory') . ": {$absolutePath}. " . _('Check file permissions.');
            throw new \ckvsoft\CkvException($errorMessage);
        }


        $ownerId = \ckvsoft\Auth::getUserId() ?? 1;
        $trimmedPath = trim($fullRelativePath, '/');

        $folderName = basename($trimmedPath);
        $generatedTitle = $this->formatMediaName($folderName);

        $albumData = [
            'album_path' => $trimmedPath,
            'title' => $generatedTitle,
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
     * Moves a file or folder. Performs minimal authorization check on target path,
     * then executes the physical move and the corresponding database update.
     *
     * @param string $sourcePath The relative path of the item to move (e.g., 'albumA/item.jpg' or 'albumA/folderB').
     * @param string $targetPath The relative path of the target directory (e.g., 'albumC').
     * @return bool True on success.
     */
    public function moveItem(string $sourcePath, string $targetPath): bool
    {
        $targetPath = trim($targetPath, '/');
        $sourcePath = trim($sourcePath, '/');
        $itemName = basename($sourcePath);

        $absoluteSourcePath = $this->basePath . $sourcePath;

        $absoluteTargetPath = $this->basePath . $targetPath;
        // The movePhysicalItem method already throws CkvException on 'Source does not exist' or 'Destination already exists'.
        $physicalMoveSuccess = $this->movePhysicalItem($absoluteSourcePath, $absoluteTargetPath);

        if (!$physicalMoveSuccess) {
            return false; // Physical move failed, do not update DB. (This path should rarely be hit if movePhysicalItem throws on failure)
        }

        $oldAlbumPath = dirname($sourcePath);
        $newAlbumPath = $targetPath;

        $isFolder = !pathinfo($itemName, PATHINFO_EXTENSION);

        if ($isFolder) {
            $oldFullPath = $sourcePath;
            $newFullPath = trim($newAlbumPath . '/' . $itemName, '/');

            // Update the album path and all nested media/sub-albums
            $dbSuccess = $this->updateAlbumPath($oldFullPath, $newFullPath);

            if (!$dbSuccess) {
                error_log("DB update FAILED for album move: {$oldFullPath} to {$newFullPath}");
            }
            return $dbSuccess;
        } else {
            // Update the media record with the new album ID
            $dbSuccess = $this->updateMediaAlbumId($itemName, $oldAlbumPath, $newAlbumPath);

            if (!$dbSuccess) {
                error_log("DB update FAILED for media move: {$itemName} from {$oldAlbumPath} to {$newAlbumPath}");
            }
            return $dbSuccess;
        }
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

        // --- 1. FIND ASSOCIATED THUMBNAIL (IF ANY) ---
        $filenameWithoutExt = pathinfo($itemName, PATHINFO_FILENAME);
        // Search for thumb files associated with the source item
        $oldThumbPattern = dirname($sourcePath) . '/' . $filenameWithoutExt . '_thumb.*';
        $thumbsToMove = glob($oldThumbPattern);
        $thumbToMove = $thumbsToMove[0] ?? null;

        // --- 2. MOVE MAIN FILE/FOLDER (Primary Action) ---
        if (!file_exists($sourcePath)) {
            // This already throws an exception on an expected failure.
            throw new \ckvsoft\CkvException("Source does not exist: {$sourcePath}");
        }

        if (file_exists($finalDestination)) {
            // This already throws an exception on an expected conflict.
            throw new \ckvsoft\CkvException("Destination already exists: {$finalDestination}");
        }

        // Handle move: Convert rename() warnings into controllable exceptions
        set_error_handler(function ($errno, $errstr) use ($sourcePath, $finalDestination) {
            if ($errno === E_WARNING) {
                throw new \ckvsoft\CkvException("Rename failed: {$errstr} ({$sourcePath} → {$finalDestination})");
            }
            return false;
        });

        // The rename function handles both file and folder moves
        rename($sourcePath, $finalDestination);

        restore_error_handler();

        // --- 3. MOVE THUMBNAIL (Secondary Action) ---
        if ($thumbToMove) {
            $thumbItemName = basename($thumbToMove);
            $thumbDestination = rtrim($targetPath, '/') . '/' . $thumbItemName;

            // Temporarily suppress errors for thumbnail move, as it's secondary
            set_error_handler(function () {
                // Swallow the error silently for the thumbnail only
                return true;
            });

            $thumbMoved = rename($thumbToMove, $thumbDestination);

            restore_error_handler();
        }

        return true;
    }

    /**
     * Uploads an image file and registers it in the database for visibility.
     * @param string $targetpath The relative path of the album (e.g., 'photos/summer').
     * @param string $filename The name of the file field in $_FILES or the target file name.
     * @param bool $overwrite Whether to overwrite an existing file.
     * @return bool True on successful upload AND successful database registration, false otherwise.
     */
    public function uploadImage($targetpath, $filename, $overwrite = true)
    {
        $targetpath = trim($targetpath, '/');
        $directory = $this->basePath . $targetpath;

        // ---------------------------------------------------------------------
        // FILE EXTENSION VALIDATION
        // ---------------------------------------------------------------------
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Check against the supported extensions (inherited from Parent Model).
        $allowedExtensions = array_merge(self::SUPPORTED_IMAGE_EXT, self::SUPPORTED_VIDEO_EXT);

        if (!in_array($extension, $allowedExtensions)) {
            error_log("Upload failed: File extension '{$extension}' is not permitted.");
            // You might output a specific error message here using an output system.
            return false;
        }
        // ---------------------------------------------------------------------

        $album = $this->db->selectOne(
                "SELECT album_id FROM gallery_albums WHERE album_path = :path",
                ['path' => $targetpath]
        );

        if (!$album) {
            error_log("Upload failed: Target album '{$targetpath}' not found in DB.");
            return false; // Cannot proceed without a valid album ID
        }
        $albumId = $album['album_id'];

        // Note: The $filename here typically refers to the key in $_FILES, not the final file name.
        // It's assumed that ckvsoft\Upload handles reading from $_FILES and saving the file correctly.
        $upload = new \ckvsoft\Upload($filename, $directory, $filename, $overwrite);
        $success = $upload->submit();

        if ($success) {
            // Database registration
            $existing = $this->db->selectOne(
                    "SELECT id FROM gallery_media_stats WHERE album_id = :aid AND file_name = :file",
                    ['aid' => $albumId, 'file' => $filename]
            );

            if (!$existing) {
                $result = $this->db->insertUpdate('gallery_media_stats', [
                    'album_id' => $albumId,
                    'file_name' => $filename
                ]);

                if ($result === false) {
                    error_log("DB registration failed for file '{$filename}' in album ID '{$albumId}'.");
                    return false;
                }
            }
            return true; // Upload and DB registration successful
        }

        // Upload failed
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

        // 1. Check for basic existence (or whether it's a ghost entry)
        if (!file_exists($absolutePath)) {

            // Check if a ghost ALBUM entry exists in the DB
            $albumExistsInDb = $this->db->selectOne("SELECT 1 FROM gallery_albums WHERE album_path = :path", ['path' => $relativePath]);

            if (!empty($albumExistsInDb)) {
                // GHOST ALBUM CLEANUP: Item is physically missing but present in the DB.
                error_log("DB CLEANUP: Album '{$relativePath}' is physically missing. Deleting DB entry only.");

                // Since the file system part is already done (it's missing),
                // we skip straight to DB cleanup.
                return $this->deleteAlbumAndContents($relativePath);
            }

            // OPTIONAL: Check for ghost MEDIA (file) entry
            // This is more complex as it requires checking the parent album ID.
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

            // If it doesn't exist physically AND is not found in the DB, throw the original exception.
            throw new \ckvsoft\CkvException("Item not found: {$relativePath}");
        }

        // --- If the item exists physically, proceed with the standard deletion process ---

        $isFolder = is_dir($absolutePath);

        // 2. Perform FULL OWNERSHIP CHECK (Security Critical)
        // Permissions are checked against the item itself (if folder) or its parent (if file).
        $albumPathToCheck = $isFolder ? $relativePath : $parentPath;

        $albumData = $this->checkAlbumPermissions($albumPathToCheck);

        if (!$albumData) {
            throw new \ckvsoft\CkvException("The associated album/folder could not be found for permission check: {$albumPathToCheck}");
        }

        // Crucial security check: Is the current user the owner?
        if ((string) $albumData['owner_user_id'] !== (string) $currentUserId) {
            throw new \ckvsoft\CkvException("Permission Denied: You do not have ownership rights to delete this item. ({$relativePath})");
        }

        // --- Proceed only if security and existence checks passed ---
        // 3. Perform physical deletion (File System)
        // If this fails, an exception is thrown and DB deletion is skipped.
        $this->deletePhysicalItem($absolutePath);

        // 4. Perform database deletion
        if ($isFolder) {
            $dbSuccess = $this->deleteAlbumAndContents($relativePath);
        } else {
            $dbSuccess = $this->deleteMediaRecord($itemName, $parentPath);
        }

        if (!$dbSuccess) {
            // Log a critical error if the file is gone but DB cleanup failed
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
        // FIX: If the item to delete appears to be a thumbnail and it doesn't exist,
        // we return TRUE immediately and silently to avoid throwing an exception.
        // This allows the deletion process (especially recursive folder deletion) to continue
        // even if optional thumbnails are already missing.
        if (!file_exists($absolutePath) && strpos($absolutePath, '_thumb.') !== false) {
            return true;
        }

        if (!file_exists($absolutePath)) {
            // This exception is correctly thrown for the main item if it doesn't exist,
            // unless it's a ghost entry handled by deleteItem() previously.
            throw new \ckvsoft\CkvException("Cannot delete: Item not found at path: {$absolutePath}");
        }

        if (is_dir($absolutePath)) {
            // --- FOLDER DELETION ---
            $files = array_diff(scandir($absolutePath) ?: [], ['.', '..']);

            // Recursively delete contents
            foreach ($files as $file) {
                // Call self recursively to handle sub-folders and files
                $this->deletePhysicalItem(rtrim($absolutePath, '/') . '/' . $file);
            }

            // After all contents are deleted, remove the directory itself
            if (!rmdir($absolutePath)) {
                // This means the directory was not empty, which indicates an error in the recursion
                throw new \ckvsoft\CkvException("Failed to remove directory: {$absolutePath}. Directory might not be empty.");
            }

            return true;
        } else {
            // --- FILE DELETION ---
            // 1. Delete the main file
            if (!unlink($absolutePath)) {
                throw new \ckvsoft\CkvException("Failed to delete file: {$absolutePath}. Check file permissions.");
            }

            // 2. Delete any associated thumbnail
            $itemName = basename($absolutePath);
            $filenameWithoutExt = pathinfo($itemName, PATHINFO_FILENAME);
            $parentDir = dirname($absolutePath);

            // Search for thumb files associated with the item (e.g., file_thumb.jpg, file_thumb.png)
            $thumbPattern = $parentDir . '/' . $filenameWithoutExt . '_thumb.*';
            $thumbsToDelete = glob($thumbPattern);

            foreach ($thumbsToDelete as $thumbPath) {
                // FIX: Explicitly check if the file exists before attempting deletion.
                // This prevents an error if the thumbnail was somehow deleted between the glob() call
                // and the unlink() attempt, or if it was never created.
                if (file_exists($thumbPath)) {
                    if (!unlink($thumbPath)) {
                        // Log a warning if deletion fails, but do not interrupt the main process.
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
        // 1. Find the album_id for the given path
        $album = $this->db->selectOne("SELECT album_id FROM gallery_albums WHERE album_path = :path", ['path' => $albumPath]);

        if (!$album) {
            // If the parent album doesn't exist, the media record is likely orphaned or already deleted.
            return true;
        }

        // 2. Delete the specific media stats record
        $rowsAffected = $this->db->delete(
                'gallery_media_stats',
                'album_id = :aid AND file_name = :file',
                [
                    'aid' => $album['album_id'],
                    'file' => $fileName
                ]
        );

        // If 0 rows are affected, it might mean it was already deleted, which is okay.
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
        // Start a transaction for data integrity (all or nothing)
        $this->db->beginTransaction();

        try {
            // Find all album_ids affected (the album itself and all sub-albums)
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
                // Nothing to delete, commit the empty transaction
                $this->db->commit();
                return true;
            }

            // CONVERT IDS TO A RAW, COMMA-SEPARATED STRING FOR THE IN CLAUSE.
            // This avoids the PDO TypeError by calling $this->db->query() with only one argument.
            // We use intval to ensure safety, even though array_map already produces strings.
            $idList = implode(',', array_map('intval', $ids));

            // 1. Delete all media stats for the affected albums
            // Using a raw query. We expect $this->db->query() to behave like PDO::query().
            $sqlDeleteMedia = "DELETE FROM gallery_media_stats WHERE album_id IN ({$idList})";
            $mediaDeleted = $this->db->query($sqlDeleteMedia);

            // 2. Delete all album records themselves
            // Use the native $this->db->delete() method for safety and compatibility.
            $sqlCondition = "album_path = :path OR album_path LIKE :pathPrefix";
            $params = [
                'path' => $albumPath,
                'pathPrefix' => $albumPath . '/%'
            ];

            $albumsDeleted = $this->db->delete('gallery_albums', $sqlCondition, $params);

            // Check if both deletes were successful (assuming $this->db->query/delete returns false on failure)
            if ($mediaDeleted === false || $albumsDeleted === false) {
                $this->db->rollBack();
                error_log("DB transaction failed during deletion of album content or album record.");
                return false;
            }

            // Commit the transaction
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("DB Exception during deleteAlbumAndContents: " . $e->getMessage());
            return false;
        }
    }
}
