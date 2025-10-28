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
     * @param string $relativePath The relative path (e.g., 'user_a/docs').
     * @return array List of items, or ['error' => ...] on access problems.
     */
    public function listDirectoryContents(string $relativePath): array
    {
        $isAdmin = \ckvsoft\Auth::getUserPermissionLevel() >= 3;
        $currentUserId = \ckvsoft\Auth::getUserId();
        $normalizedPath = trim($relativePath, '/');

        if ($isAdmin) {
            if (!empty($normalizedPath) && !$this->db->selectOne("SELECT 1 FROM `gallery_albums` WHERE `album_path` = :path", ['path' => $normalizedPath])) {

            }

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

        if (empty($normalizedPath)) {
            $items = [];
            $ownedAlbums = $this->getOwnedAlbums($currentUserId);
            $topLevelPaths = [];

            foreach ($ownedAlbums as $album) {
                $pathSegments = explode('/', $album['album_path']);
                $topPath = $pathSegments[0];

                if (!isset($topLevelPaths[$topPath])) {
                    $topLevelPaths[$topPath] = $topPath;
                }
            }

            foreach ($topLevelPaths as $topPath) {
                $albumData = $this->checkAlbumPermissions($topPath);
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

        $albumData = $this->checkAlbumPermissions($normalizedPath);

        if ($albumData && (string) $albumData['owner_user_id'] === (string) $currentUserId) {

            $subAlbums = $this->getSubAlbums($normalizedPath); // Direct children only
            $mediaItems = $this->getMediaByAlbum($normalizedPath);
            $items = [];
            $alreadyListedPaths = [];

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
                        'name' => $album['title'] ?? basename($path),
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

                    $immediateChildPath = $normalizedPath . '/' . $pathSegments[0];

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
        $physicalMoveSuccess = $this->movePhysicalItem($absoluteSourcePath, $absoluteTargetPath);

        if (!$physicalMoveSuccess) {
            return false;
        }

        $oldAlbumPath = dirname($sourcePath);
        $newAlbumPath = $targetPath;

        $isFolder = !pathinfo($itemName, PATHINFO_EXTENSION);

        if ($isFolder) {
            $oldFullPath = $sourcePath;
            $newFullPath = trim($newAlbumPath . '/' . $itemName, '/');

            $dbSuccess = $this->updateAlbumPath($oldFullPath, $newFullPath);

            if (!$dbSuccess) {
                error_log("DB update FAILED for album move: {$oldFullPath} to {$newFullPath}");
            }
            return $dbSuccess;
        } else {
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

        $filenameWithoutExt = pathinfo($itemName, PATHINFO_FILENAME);
        $oldThumbPattern = dirname($sourcePath) . '/' . $filenameWithoutExt . '_thumb.*';
        $thumbsToMove = glob($oldThumbPattern);
        $thumbToMove = $thumbsToMove[0] ?? null;

        if (!file_exists($sourcePath)) {
            throw new \ckvsoft\CkvException("Source does not exist: {$sourcePath}");
        }

        if (file_exists($finalDestination)) {
            throw new \ckvsoft\CkvException("Destination already exists: {$finalDestination}");
        }

        set_error_handler(function ($errno, $errstr) use ($sourcePath, $finalDestination) {
            if ($errno === E_WARNING) {
                throw new \ckvsoft\CkvException("Rename failed: {$errstr} ({$sourcePath} → {$finalDestination})");
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

            throw new \ckvsoft\CkvException("Item not found: {$relativePath}");
        }

        $isFolder = is_dir($absolutePath);

        $albumPathToCheck = $isFolder ? $relativePath : $parentPath;

        $albumData = $this->checkAlbumPermissions($albumPathToCheck);

        if (!$albumData) {
            throw new \ckvsoft\CkvException("The associated album/folder could not be found for permission check: {$albumPathToCheck}");
        }

        if ((string) $albumData['owner_user_id'] !== (string) $currentUserId) {
            throw new \ckvsoft\CkvException("Permission Denied: You do not have ownership rights to delete this item. ({$relativePath})");
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
            throw new \ckvsoft\CkvException("Cannot delete: Item not found at path: {$absolutePath}");
        }

        if (is_dir($absolutePath)) {
            $files = array_diff(scandir($absolutePath) ?: [], ['.', '..']);

            foreach ($files as $file) {
                $this->deletePhysicalItem(rtrim($absolutePath, '/') . '/' . $file);
            }

            if (!rmdir($absolutePath)) {
                throw new \ckvsoft\CkvException("Failed to remove directory: {$absolutePath}. Directory might not be empty.");
            }

            return true;
        } else {
            if (!unlink($absolutePath)) {
                throw new \ckvsoft\CkvException("Failed to delete file: {$absolutePath}. Check file permissions.");
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
