<?php

// gallery/model/Gallery_Model.php

use ckvsoft\mvc\Config;
use ckvsoft\Image; // Assuming ckvsoft\Image is the class used for thumbnail generation
use ckvsoft\DbExpr; // Required for incrementViewCounter
use ckvsoft\Auth;
use ckvsoft\CkvException;

/**
 * The main model responsible for gallery access, path calculations, file scanning,
 * permission checks, and view counting.
 */
class Gallery_Model extends ckvsoft\mvc\Model
{

    private string $basePath;
    private string $albumsBaseUrl;

    // CONSOLIDATED CONSTANTS: Used by both Gallery_Model and GalleryManager_Model
    public const SUPPORTED_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const SUPPORTED_VIDEO_EXT = ['mp4', 'webm', 'ogg'];
    private const DEFAULT_VIDEO_THUMB_URL = BASE_URI . 'gallery/media/default/video_thumb.jpg';
    private const DEFAULT_IMAGE_THUMB_URL = BASE_URI . 'gallery/media/default/image_thumb.jpg';

    public function __construct()
    {
        parent::__construct();

        $relativePath = trim(Config::get('paths.albums_relative_path') ?? 'public/albums/', '/');
        // Calculate the absolute path to the albums base directory
        $this->basePath = __DIR__ . '/../../../' . $relativePath . '/';
        $this->albumsBaseUrl = BASE_URI . $relativePath . '/';
    }

    // ------------------------------------------------------------------
    // PATH & ID LOOKUP (ESSENTIAL FOR FRONTEND)
    // ------------------------------------------------------------------

    /**
     * Retrieves the album path for a given album ID.
     * @param int $albumId
     * @return string|null The album path or null if not found.
     */
    public function getAlbumPathById(int $albumId): ?string
    {
        $result = $this->db->selectOne(
                "SELECT album_path FROM gallery_albums WHERE album_id = :id",
                ['id' => $albumId]
        );
        return $result['album_path'] ?? null;
    }

    /**
     * Gets the album ID by path, or creates a new entry if not found during access.
     * @param string $albumPath The path of the album.
     * @param int|null $currentUserId Optional ID of the user creating the album.
     * @return int The ID of the album.
     * @throws \ckvsoft\CkvException If the album ID cannot be retrieved or created.
     */
    private function getAlbumIdByPath(string $albumPath, ?int $currentUserId = null): int
    {
        $normalizedPath = trim($albumPath, '/');

        $album = $this->db->selectOne(
                "SELECT `album_id` FROM `gallery_albums` WHERE `album_path` = :path",
                ['path' => $normalizedPath]
        );

        if (!empty($album)) {
            return (int) $album['album_id'];
        }

        $data = [
            'album_path' => $normalizedPath,
            'permissions_level' => 2, // Default to admin
            'owner_user_id' => $currentUserId, // Assign owner here
        ];

        $albumId = $this->db->insertUpdate('gallery_albums', $data);

        if ($albumId > 0) {
            return (int) $albumId;
        }

        // Fallback for Race Condition
        $album = $this->db->selectOne(
                "SELECT `album_id` FROM `gallery_albums` WHERE `album_path` = :path",
                ['path' => $normalizedPath]
        );

        if (!empty($album)) {
            return (int) $album['album_id'];
        }

        throw new CkvException("Could not determine or create album_id for path '{$normalizedPath}'.");
    }

    // ------------------------------------------------------------------
    // PERMISSION CHECK & VIEW COUNTER
    // ------------------------------------------------------------------

    /**
     * Checks permission for an album.
     * @param string $albumPath The album path.
     * @return array|false The album data array upon success, otherwise false.
     */
    public function checkAlbumPermissions(string $albumPath): array|false
    {
        $userLevel = Auth::getUserPermissionLevel();

        $normalizedPath = trim($albumPath, '/');
        $album = $this->db->selectOne(
                "SELECT `permissions_level`, `album_id`, `owner_user_id` FROM `gallery_albums` WHERE `album_path` = :path",
                ['path' => $normalizedPath]
        );

        if (empty($album))
            return false;

        $albumData = $album;
        $requiredLevel = (int) $albumData['permissions_level'];
        $currentUserId = Auth::getUserId();

        // Check level permission
        if ($userLevel >= $requiredLevel) {
            return $albumData;
        }

        // Check owner override
        if ($currentUserId !== null && (int) $currentUserId === (int) $albumData['owner_user_id']) {
            return $albumData;
        }

        return false;
    }

    /**
     * Increments the view counter for the given media file.
     * @param string $albumPath The path to the album (e.g., 'events/hochzeit').
     * @param string $fileName The filename (e.g., 'image.jpg').
     * @param int|null $currentUserId Optional ID of the user accessing the medium.
     */
    public function incrementViewCounter(string $albumPath, string $fileName, ?int $currentUserId = null): void
    {
        try {
            $albumId = $this->getAlbumIdByPath($albumPath, $currentUserId);
        } catch (CkvException $e) {
            error_log("Database error during album ID retrieval: " . $e->getMessage());
            return;
        }

        $this->db->insertUpdate('gallery_media_stats', [
            'album_id' => $albumId,
            'file_name' => $fileName,
            'views' => new DbExpr('IF(`views` IS NULL, 1, `views` + 1)'),
            'last_view' => new DbExpr('NOW()')
        ]);
    }

    /**
     * Returns the absolute file path for a medium and increments view counter.
     * @param string $albumName The album path.
     * @param string $fileName The medium's filename.
     * @return string|null The absolute path to the file, or NULL if access is denied.
     */
    public function getFilePath(string $albumName, string $fileName): ?string
    {
        $albumData = $this->checkAlbumPermissions($albumName);

        if ($albumData === false)
            return null;

        $currentUserId = Auth::getUserId();
        $nameNoExt = pathinfo($fileName, PATHINFO_FILENAME);
        // The original logic checks only the lowercase filename part
        $isThumbnail = str_ends_with(strtolower($nameNoExt), '_thumb');

        if (!$isThumbnail) {
            $this->incrementViewCounter($albumName, $fileName, $currentUserId);
        }

        // Correctly construct the absolute path
        return rtrim($this->basePath, '/') . '/' . trim($albumName, '/') . '/' . $fileName;
    }

    // ------------------------------------------------------------------
    // ALBUM AND MEDIA LISTING
    // ------------------------------------------------------------------

    /**
     * Retrieves all albums available in the root folder.
     * @return array List of album names.
     */
    public function getAllAlbums(): array
    {
        return $this->getSubAlbums('');
    }

    /**
     * Retrieves sub-albums for a given album path based on filesystem scan.
     * @param string $albumName The album path (relative to the base path).
     * @return array List of sub-album names.
     */
    public function getSubAlbums(string $albumName): array
    {
        $albumDir = rtrim($this->basePath, '/') . '/' . trim($albumName, '/');
        $subAlbums = [];

        if (is_dir($albumDir) && $handle = opendir($albumDir)) {
            while (($entry = readdir($handle)) !== false) {
                if ($entry !== '.' && $entry !== '..' && is_dir($albumDir . '/' . $entry)) {
                    $subAlbums[] = $entry;
                }
            }
            closedir($handle);
        }

        sort($subAlbums);
        return $subAlbums;
    }

    /**
     * Retrieves media files for an album from the filesystem, optionally merging with DB stats.
     * @param string $albumName The album path.
     * @param bool $recursive Whether to scan subdirectories.
     * @param bool $random Whether to shuffle the results.
     * @param bool $includeStats Whether to fetch and merge DB stats.
     * @return array List of media items.
     */
    public function getMediaByAlbum(string $albumName, bool $recursive = false, bool $random = false, bool $includeStats = false): array
    {
        $albumDir = rtrim($this->basePath, '/') . '/' . trim($albumName, '/');

        if (!is_dir($albumDir))
            return [];

        $media = $recursive ? $this->_scanDirectoryRecursive($albumDir, $albumName) : $this->_scanDirectory($albumDir, $albumName);

        if ($includeStats) {
            try {
                $albumId = $this->getAlbumIdByPath($albumName);
                $media = $this->_mergeViewsWithMedia($albumId, $media);
            } catch (CkvException $e) {
                error_log("Cannot get album ID for stats: " . $e->getMessage());
            }
        }

        if ($random)
            shuffle($media);
        else
            usort($media, fn($a, $b) => strcmp($a['file'], $b['file']));

        return $media;
    }

    /**
     * Retrieves a random thumbnail URL for a given album.
     * @param string $albumName The album path.
     * @param bool $recursive Whether to check sub-albums recursively.
     * @return string|null The URL of the random thumbnail, or null.
     */
    public function getRandomThumbnailUrl(string $albumName, bool $recursive = false): ?string
    {
        $allMedia = $this->getMediaByAlbum($albumName, $recursive, false);

        // Filter out items using default placeholder URLs
        $itemsWithThumbs = array_filter($allMedia, fn($item) =>
                isset($item['thumburl']) &&
                $item['thumburl'] !== self::DEFAULT_VIDEO_THUMB_URL &&
                $item['thumburl'] !== self::DEFAULT_IMAGE_THUMB_URL
        );

        if (empty($itemsWithThumbs))
            return null;

        $randomIndex = array_rand($itemsWithThumbs);
        return $itemsWithThumbs[$randomIndex]['thumburl'];
    }

    /**
     * Formats a filename (without extension) into a human-readable title.
     * @param string $fileName The original filename.
     * @return string
     */
    public function formatMediaName(string $fileName): string
    {
        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
        $nameCleaned = str_replace('_', ' ', $nameWithoutExt);
        return ucwords(strtolower($nameCleaned));
    }

    // ------------------------------------------------------------------
    // PRIVATE HELPER METHODS (CONSOLIDATED LOGIC)
    // ------------------------------------------------------------------

    /**
     * CONSOLIDATED LOGIC: Creates a single media item array from file info.
     * This method contains all file type checking, URL encoding, and thumbnail logic.
     * @param string $file The filename (e.g., 'image.jpg').
     * @param string $currentDirectory The absolute path to the directory containing the file.
     * @param string $currentAlbumPath The relative album path (e.g., 'events/wedding').
     * @return array|null The formatted media array or null if unsupported.
     */
    private function _createMediaItem(string $file, string $currentDirectory, string $currentAlbumPath): ?array
    {
        $origExt = pathinfo($file, PATHINFO_EXTENSION);
        $ext = strtolower($origExt);
        $nameNoExt = pathinfo($file, PATHINFO_FILENAME);
        $filePath = $currentDirectory . '/' . $file;

        if (str_ends_with($nameNoExt, '_thumb') || (!in_array($ext, self::SUPPORTED_IMAGE_EXT) && !in_array($ext, self::SUPPORTED_VIDEO_EXT))) {
            return null;
        }

        // --- PATH ENCODING (Centralized Logic from original scans) ---
        $fullUrlPath = trim($currentAlbumPath . '/' . $file, '/');
        $pathSegments = explode('/', $fullUrlPath);
        $encodedPath = implode('/', array_map('urlencode', $pathSegments));

        $mediaItem = [
            'file' => $file,
            'name' => $this->formatMediaName($file),
            'url' => BASE_URI . 'gallery/media/' . $encodedPath,
        ];

        if (in_array($ext, self::SUPPORTED_IMAGE_EXT)) {
            $mediaItem['type'] = 'image';

            $thumbFileName = $nameNoExt . '_thumb.' . $origExt;
            $thumbFile = $currentDirectory . '/' . $thumbFileName;

            // Thumbnail path encoding
            $thumbUrlPath = trim($currentAlbumPath . '/' . $thumbFileName, '/');
            $thumbSegments = explode('/', $thumbUrlPath);
            $encodedThumbPath = implode('/', array_map('urlencode', $thumbSegments));
            $finalThumbUrl = BASE_URI . 'gallery/media/' . $encodedThumbPath; // Default to expected path

            if (!file_exists($thumbFile)) {
                $finalThumbUrl = self::DEFAULT_IMAGE_THUMB_URL; // Fallback to default path
                // Attempt to create the thumbnail (Original logic)
                try {
                    $image = new Image($filePath, $thumbFileName, $currentDirectory . '/');
                    if ($image->resize()) {
                        // If successful, reset to the correct URL
                        $finalThumbUrl = BASE_URI . 'gallery/media/' . $encodedThumbPath;
                    }
                } catch (\Exception $e) {
                    error_log("Thumbnail creation failed for {$file}: " . $e->getMessage());
                }
            }
            $mediaItem['thumburl'] = $finalThumbUrl;
        } elseif (in_array($ext, self::SUPPORTED_VIDEO_EXT)) {
            $mediaItem['type'] = 'video';

            $thumbFileName = $nameNoExt . '_thumb.jpg';
            $thumbFile = $currentDirectory . '/' . $thumbFileName;

            // Thumbnail path encoding
            $thumbUrlPath = trim($currentAlbumPath . '/' . $thumbFileName, '/');
            $thumbSegments = explode('/', $thumbUrlPath);
            $encodedVideoThumbPath = implode('/', array_map('urlencode', $thumbSegments));

            $mediaItem['thumburl'] = file_exists($thumbFile) ? BASE_URI . 'gallery/media/' . $encodedVideoThumbPath : self::DEFAULT_VIDEO_THUMB_URL;
        } else {
            return null; // Should not happen due to initial check
        }

        return $mediaItem;
    }

    /**
     * Helper: Scans a single directory non-recursively for media items.
     */
    private function _scanDirectory(string $directory, string $albumName): array
    {
        $media = [];
        $albumPath = trim($albumName, '/');

        foreach (scandir($directory) as $file) {
            if ($file === '.' || $file === '..' || is_dir($directory . '/' . $file))
                continue;

            $mediaItem = $this->_createMediaItem($file, $directory, $albumPath);
            if ($mediaItem !== null) {
                $media[] = $mediaItem;
            }
        }
        return $media;
    }

    /**
     * Helper: Scans a directory and all subdirectories recursively for media items.
     */
    private function _scanDirectoryRecursive(string $directory, string $rootAlbumName): array
    {
        $media = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($rii as $item) {
            if ($item->isDir())
                continue;

            $file = $item->getFilename();
            $currentDirectory = $item->getPath();

            // Calculate the relative album path for the media item
            $relativePath = str_replace($this->basePath, '', $item->getPathname());
            $albumPath = pathinfo($relativePath, PATHINFO_DIRNAME);
            $cleanAlbumPath = ($albumPath === '.' || $albumPath === false) ? '' : $albumPath;

            $mediaItem = $this->_createMediaItem($file, $currentDirectory, $cleanAlbumPath);
            if ($mediaItem !== null) {
                $media[] = $mediaItem;
            }
        }
        return $media;
    }

    /**
     * Helper: Merges the media file list (from filesystem scan) with database view counts.
     * @param int $albumId The ID of the album.
     * @param array $mediaList The file list array generated by scanDirectory/scanDirectoryRecursive.
     * @return array The merged media list.
     */
    private function _mergeViewsWithMedia(int $albumId, array $mediaList): array
    {
        $dbStats = $this->db->select("
            SELECT id, file_name, views, last_view
            FROM gallery_media_stats
            WHERE album_id = :id
        ", ['id' => $albumId]);

        $statsMap = [];
        foreach ($dbStats as $stat) {
            $statsMap[$stat['file_name']] = [
                'id' => (int) $stat['id'],
                'views' => (int) $stat['views'],
                'last_view' => $stat['last_view']
            ];
        }

        $mergedMedia = [];
        foreach ($mediaList as $mediaItem) {
            $fileName = $mediaItem['file'];
            $stats = $statsMap[$fileName] ?? ['id' => null, 'views' => 0, 'last_view' => 'never'];

            $mediaItem['id'] = $stats['id'];
            $mediaItem['views'] = $stats['views'];
            $mediaItem['last_view'] = $stats['last_view'];

            $mergedMedia[] = $mediaItem;
        }

        return $mergedMedia;
    }
}
