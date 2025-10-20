<?php

// gallery/model/Gallery_Model.php

use ckvsoft\mvc\Config;
use ckvsoft\Image;
use ckvsoft\DbExpr;
use ckvsoft\Auth;
use ckvsoft\CkvException;

/**
 * Main gallery model responsible for:
 * - Path calculations
 * - File scanning
 * - Permission checks
 * - View counting
 */
class Gallery_Model extends ckvsoft\mvc\Model
{

    private string $basePath;
    private string $albumsBaseUrl;

    // Supported file types
    public const SUPPORTED_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const SUPPORTED_VIDEO_EXT = ['mp4', 'webm', 'ogg'];
    // Permission levels
    public const PERMISSION_LEVELS = [
        0 => 'Public (Everyone)',
        1 => 'Restricted (Members)',
        2 => 'Private (Owner Only)',
        3 => 'Admin (Administrator)',
    ];
    // Default thumbnails
    private const DEFAULT_VIDEO_THUMB_URL = BASE_URI . 'gallery/media/default/video_thumb.jpg';
    private const DEFAULT_IMAGE_THUMB_URL = BASE_URI . 'gallery/media/default/image_thumb.jpg';

    public function __construct()
    {
        parent::__construct();
        $relativePath = trim(Config::get('paths.albums_relative_path') ?? 'public/albums/', '/');

        // Absolute path to albums
        $this->basePath = __DIR__ . '/../../../' . $relativePath . '/';
        $this->albumsBaseUrl = BASE_URI . $relativePath . '/';
    }

    // ------------------------------------------------------------------
    // PATH & ID LOOKUP
    // ------------------------------------------------------------------

    public function getAlbumPathById(int $albumId): ?string
    {
        $result = $this->db->selectOne(
                "SELECT album_path FROM gallery_albums WHERE album_id = :id",
                ['id' => $albumId]
        );
        return $result['album_path'] ?? null;
    }

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

        // Insert new album if not exists (This is needed by incrementViewCounter)
        $data = [
            'album_path' => $normalizedPath,
            'permissions_level' => 2, // Default to private/admin
            'owner_user_id' => $currentUserId,
        ];

        $albumId = $this->db->insertUpdate('gallery_albums', $data);
        if ($albumId > 0) {
            return (int) $albumId;
        }

        // Fallback in case of race condition
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
    // PERMISSIONS & VIEW COUNTER
    // ------------------------------------------------------------------

    /**
     * Checks if the current user has permission to view a specific album.
     * @param string $albumPath The path of the album.
     * @return array|false Returns the full album data if permitted, false otherwise.
     */
    public function checkAlbumPermissions(string $albumPath): array|false
    {
        $userLevel = Auth::getUserPermissionLevel();
        $normalizedPath = trim($albumPath, '/');

        $album = $this->db->selectOne(
                "SELECT `permissions_level`, `album_id`, `title`, `owner_user_id`
           FROM `gallery_albums` WHERE `album_path` = :path",
                ['path' => $normalizedPath]
        );

        if (empty($album))
            return false; // Album must exist in DB to be accessible

        $albumData = $album;

        // Title fallback to folder name if not set in DB
        $albumData['title'] = $albumData['title'] ?? basename($normalizedPath);

        $requiredLevel = (int) $albumData['permissions_level'];
        $currentUserId = Auth::getUserId();

        // Admin check (Level 3) is explicitly removed, as requested by the user.
        // if ($userLevel >= 3) return $albumData;
        // Owner Check
        if ($currentUserId !== null && (string) $currentUserId === (string) $albumData['owner_user_id'])
            return $albumData;

        // Regular Permissions Check (Public/Restricted)
        if ($userLevel >= $requiredLevel)
            return $albumData;

        return false;
    }

    public function incrementViewCounter(string $albumPath, string $fileName, ?int $currentUserId = null): void
    {
        try {
            // getAlbumIdByPath might create a new entry if one doesn't exist
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

    public function getFilePath(string $albumName, string $fileName): ?string
    {
        $albumData = $this->checkAlbumPermissions($albumName);
        if ($albumData === false)
            return null;

        $currentUserId = Auth::getUserId();
        $nameNoExt = pathinfo($fileName, PATHINFO_FILENAME);
        $isThumbnail = str_ends_with(strtolower($nameNoExt), '_thumb');

        if (!$isThumbnail) {
            $this->incrementViewCounter($albumName, $fileName, $currentUserId);
        }

        return rtrim($this->basePath, '/') . '/' . trim($albumName, '/') . '/' . $fileName;
    }

    // ------------------------------------------------------------------
    // ALBUM & MEDIA LISTING
    // ------------------------------------------------------------------

    public function getAllAlbums(): array
    {
        return $this->getSubAlbums('');
    }

    /**
     * Retrieves sub-albums for a given album path based on database lookup and permission check.
     * Returns an array of permitted sub-albums, including 'name', 'path', and 'title'.
     *
     * @param string $albumName The album path (relative to the base path).
     * @return array List of permitted sub-albums, each as an associative array.
     */
    public function getSubAlbums(string $albumName): array
    {
        $basePath = trim($albumName, '/');
        // Get all direct sub-album names from the DB (e.g., ['album1', 'album2'])
        $potentialSubAlbumNames = $this->_getDirectSubAlbumPathsFromDB($basePath);
        $permittedSubAlbums = [];

        foreach ($potentialSubAlbumNames as $subAlbumName) {
            // Reconstruct the full path to check permissions and fetch data
            $fullSubAlbumPath = empty($basePath) ? $subAlbumName : $basePath . '/' . $subAlbumName;

            // Fetch the full album data (including 'title') and check permissions
            $albumData = $this->checkAlbumPermissions($fullSubAlbumPath);

            // If access is granted:
            if ($albumData !== false) {
                $permittedSubAlbums[] = [
                    'name' => $subAlbumName, // e.g., 'album1' (local folder name)
                    'path' => $fullSubAlbumPath, // e.g., 'events/album1' (full path, crucial for the helper)
                    'title' => $albumData['title'], // Title fetched from DB/Fallback
                ];
            }
        }

        // Sort by local name
        usort($permittedSubAlbums, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $permittedSubAlbums;
    }

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

    public function getRandomThumbnailUrl(string $albumName, bool $recursive = false): ?string
    {
        $allMedia = $this->getMediaByAlbum($albumName, $recursive, false);

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

    public function formatMediaName(string $fileName): string
    {
        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
        $nameCleaned = str_replace('_', ' ', $nameWithoutExt);
        return ucwords(strtolower($nameCleaned));
    }

    // ------------------------------------------------------------------
    // PRIVATE HELPERS
    // ------------------------------------------------------------------

    private function _createMediaItem(string $file, string $currentDirectory, string $currentAlbumPath): ?array
    {
        $origExt = pathinfo($file, PATHINFO_EXTENSION);
        $ext = strtolower($origExt);
        $nameNoExt = pathinfo($file, PATHINFO_FILENAME);
        $filePath = $currentDirectory . '/' . $file;

        if (str_ends_with($nameNoExt, '_thumb') || (!in_array($ext, self::SUPPORTED_IMAGE_EXT) && !in_array($ext, self::SUPPORTED_VIDEO_EXT))) {
            return null;
        }

        $fullUrlPath = trim($currentAlbumPath . '/' . $file, '/');
        $encodedPath = implode('/', array_map('urlencode', explode('/', $fullUrlPath)));

        $mediaItem = [
            'file' => $file,
            'name' => $this->formatMediaName($file),
            'url' => BASE_URI . 'gallery/media/' . $encodedPath,
        ];

        if (in_array($ext, self::SUPPORTED_IMAGE_EXT)) {
            $mediaItem['type'] = 'image';
            $thumbFileName = $nameNoExt . '_thumb.' . $origExt;
            $thumbFile = $currentDirectory . '/' . $thumbFileName;

            $thumbUrlPath = trim($currentAlbumPath . '/' . $thumbFileName, '/');
            $encodedThumbPath = implode('/', array_map('urlencode', explode('/', $thumbUrlPath)));
            $finalThumbUrl = BASE_URI . 'gallery/media/' . $encodedThumbPath;

            if (!file_exists($thumbFile)) {
                $finalThumbUrl = self::DEFAULT_IMAGE_THUMB_URL;
                try {
                    $image = new Image($filePath, $thumbFileName, $currentDirectory . '/');
                    if ($image->resize())
                        $finalThumbUrl = BASE_URI . 'gallery/media/' . $encodedThumbPath;
                } catch (\Exception $e) {
                    error_log("Thumbnail creation failed for {$file}: " . $e->getMessage());
                }
            }
            $mediaItem['thumburl'] = $finalThumbUrl;
        } elseif (in_array($ext, self::SUPPORTED_VIDEO_EXT)) {
            $mediaItem['type'] = 'video';
            $thumbFileName = $nameNoExt . '_thumb.jpg';
            $thumbFile = $currentDirectory . '/' . $thumbFileName;

            $thumbUrlPath = trim($currentAlbumPath . '/' . $thumbFileName, '/');
            $encodedVideoThumbPath = implode('/', array_map('urlencode', explode('/', $thumbUrlPath)));

            $mediaItem['thumburl'] = file_exists($thumbFile) ? BASE_URI . 'gallery/media/' . $encodedVideoThumbPath : self::DEFAULT_VIDEO_THUMB_URL;
        } else {
            return null;
        }

        return $mediaItem;
    }

    private function _scanDirectory(string $directory, string $albumName): array
    {
        $media = [];
        $albumPath = trim($albumName, '/');

        foreach (scandir($directory) as $file) {
            if ($file === '.' || $file === '..' || is_dir($directory . '/' . $file))
                continue;

            $mediaItem = $this->_createMediaItem($file, $directory, $albumPath);
            if ($mediaItem !== null)
                $media[] = $mediaItem;
        }
        return $media;
    }

    private function _scanDirectoryRecursive(string $directory, string $rootAlbumName): array
    {
        $media = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($rii as $item) {
            if ($item->isDir())
                continue;

            $file = $item->getFilename();
            $currentDirectory = $item->getPath();

            $relativePath = str_replace($this->basePath, '', $item->getPathname());
            $albumPath = pathinfo($relativePath, PATHINFO_DIRNAME);
            $cleanAlbumPath = ($albumPath === '.' || $albumPath === false) ? '' : $albumPath;

            $mediaItem = $this->_createMediaItem($file, $currentDirectory, $cleanAlbumPath);
            if ($mediaItem !== null)
                $media[] = $mediaItem;
        }
        return $media;
    }

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

    /**
     * Retrieves the direct sub-album paths from the database.
     * @param string $albumPath The path of the parent album.
     * @return array List of direct sub-album names (e.g., ['album1', 'album2']).
     */
    private function _getDirectSubAlbumPathsFromDB(string $albumPath): array
    {
        $normalizedPath = trim($albumPath, '/');
        $searchPathPrefix = empty($normalizedPath) ? '' : $normalizedPath . '/';

        $sql = "SELECT `album_path` FROM `gallery_albums` WHERE `album_path` LIKE :pathPrefix";
        $bindings = ['pathPrefix' => "{$searchPathPrefix}%"];
        $allDescendantPaths = $this->db->select($sql, $bindings);
        $paths = array_column($allDescendantPaths, 'album_path');

        $directSubAlbums = [];
        // Determine the expected number of path segments for direct children
        $expectedPathDepth = count(explode('/', $searchPathPrefix));

        foreach ($paths as $path) {
            if ($path === $normalizedPath)
                continue;

            $pathSegments = explode('/', $path);
            // Check if the path has exactly one more segment than the parent path
            if (count($pathSegments) === $expectedPathDepth) {
                $directSubAlbums[] = end($pathSegments);
            }
        }

        // Root folder fallback for direct children (albums without '/')
        if (empty($normalizedPath)) {
            $sql = "SELECT `album_path` FROM `gallery_albums`";
            $allPaths = $this->db->select($sql);

            foreach (array_column($allPaths, 'album_path') as $path) {
                if (strpos($path, '/') === false && !empty($path))
                    $directSubAlbums[] = $path;
            }
        }

        return array_unique($directSubAlbums);
    }
}
