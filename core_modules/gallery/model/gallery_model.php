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

use ckvsoft\mvc\Model;
use ckvsoft\CkvException;
use ckvsoft\DbExpr;
use ckvsoft\SizeConverter;
use ckvsoft\Auth;
use ckvsoft\mvc\Config;

/**
 * Main gallery model responsible for:
 * - Path calculations
 * - Media retrieval (DB-based)
 * - Permission checks
 * - View counting
 */
class Gallery_Model extends Model
{

    protected string $basePath;
    protected string $albumsBaseUrl;

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
    protected const DEFAULT_VIDEO_THUMB_URL = BASE_URI . 'gallery/media/default/video_thumb.jpg';
    protected const DEFAULT_IMAGE_THUMB_URL = BASE_URI . 'gallery/media/default/image_thumb.jpg';

    public function __construct()
    {
        parent::__construct();
        $relativePath = trim(Config::get('paths.albums_relative_path') ?? 'public/albums/', '/');
        $this->basePath = __DIR__ . '/../../../' . $relativePath . '/';

        $this->albumsBaseUrl = BASE_URI . $relativePath . '/';
    }

    /**
     * Retrieves the album_path for a given album_id.
     * Required for file system operations when cleaning up media.
     * @param int $albumId The ID of the album.
     * @return string|null The album path string, or null if not found.
     */
    protected function getAlbumPathById(int $albumId): ?string
    {
        $result = $this->db->selectOne(
                "SELECT album_path FROM gallery_albums WHERE album_id = :id",
                ['id' => $albumId]
        );

        return $result ? $result['album_path'] : null;
    }

    /**
     * Retrieves the album ID by its path, creating a new entry if none exists.
     * @param string $albumPath The path of the album.
     * @param ?int $currentUserId The ID of the current user for ownership tracking (used on creation).
     * @return int The album ID.
     * @throws CkvException If the album ID cannot be determined or created.
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
            'permissions_level' => 2, // Default to Private (Owner Only)
            'owner_user_id' => $currentUserId,
        ];

        $albumId = $this->db->insertUpdate('gallery_albums', $data);
        if ($albumId > 0) {
            return (int) $albumId;
        }

        $album = $this->db->selectOne(
                "SELECT `album_id` FROM `gallery_albums` WHERE `album_path` = :path",
                ['path' => $normalizedPath]
        );
        if (!empty($album)) {
            return (int) $album['album_id'];
        }

        throw new CkvException("Could not determine or create album_id for path '{$normalizedPath}'.");
    }

    /**
     * Checks if the current user has permission to view a specific album.
     * The owner is always permitted. Otherwise, the user's level must meet or exceed the album's required level.
     * @param string $albumPath The path of the album.
     * @return array|false Returns the full album data if permitted, false otherwise.
     */
    public function checkAlbumPermissions(string $albumPath): array|false
    {
        $userLevel = Auth::getUserPermissionLevel();
        $currentUserId = Auth::getUserId();
        $normalizedPath = trim($albumPath, '/');

        $album = $this->db->selectOne(
                "SELECT `permissions_level`, `album_id`, `title`, `owner_user_id`
            FROM `gallery_albums` WHERE `album_path` = :path",
                ['path' => $normalizedPath]
        );

        if (empty($album)) {
            return false;
        }

        $albumData = $album;
        $albumData['title'] = $albumData['title'] ?? basename($normalizedPath);

        $requiredLevel = (int) $albumData['permissions_level'];

        if ($currentUserId !== null && (string) $currentUserId === (string) $albumData['owner_user_id']) {
            return $albumData;
        }

        if ($userLevel >= $requiredLevel) {
            return $albumData;
        }

        return false;
    }

    /**
     * Increments the view counter for a specific media file within an album.
     * @param string $albumPath The path of the album.
     * @param string $fileName The name of the media file.
     * @param ?int $currentUserId The ID of the current user (passed to getAlbumIdByPath if creation is needed).
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
     * Retrieves the physical file path for a media item after checking permissions and incrementing view count.
     * @param string $albumName The album path.
     * @param string $fileName The file name.
     * @return string|null The full file path, or null if not permitted.
     */
    public function getFilePath(string $albumName, string $fileName): ?string
    {
        $albumData = $this->checkAlbumPermissions($albumName);
        if ($albumData === false) {
            return null;
        }

        $currentUserId = Auth::getUserId();
        $nameNoExt = pathinfo($fileName, PATHINFO_FILENAME);

        $isThumbnail = str_ends_with(strtolower($nameNoExt), '_thumb');

        if (!$isThumbnail) {
            $this->incrementViewCounter($albumName, $fileName, $currentUserId);
        }

        return rtrim($this->basePath, '/') . '/' . trim($albumName, '/') . '/' . $fileName;
    }

    public function getFileModifiedTimeFromDB(string $album, string $file): ?int
    {
        // Remove "_thumb" suffix from filename before extension. Thumbs are not in the DB
        $pathInfo = pathinfo($file);
        $name = $pathInfo['filename'];
        $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        if (str_ends_with(strtolower($name), '_thumb')) {
            $name = substr($name, 0, -6); // remove "_thumb" (6 chars)
        }

        $normalizedFile = $name . $ext;

        try {
            $albumId = $this->getAlbumIdByPath($album);
        } catch (CkvException $e) {
            error_log("DB error retrieving album ID: " . $e->getMessage());
            return null;
        }

        $file_mtime = $this->db->selectOne("SELECT file_mtime FROM gallery_media_stats WHERE album_id = :id AND file_name = :file_name",
                ['id' => $albumId, 'file_name' => $normalizedFile]
        );

        return $file_mtime ? strtotime($file_mtime) : null;
    }

    /**
     * Return counts for sub-albums:
     *  - direct: number of direct children (one level deeper) that the current user may access
     *  - recursive: number of all descendant albums (any depth) that the current user may access
     *
     * This does permission-checking to only count albums the current user is allowed to see.
     *
     * @param string $albumPath parent album path
     * @return array ['direct' => int, 'recursive' => int]
     */
    public function getSubAlbumCounts(string $albumPath): array
    {
        $normalized = trim($albumPath, '/');

        // --- Get candidate descendant paths from DB ---
        // We fetch all paths that start with parent (including exact parent) and then filter.
        // This avoids multiple DB calls per album; we do permission checks in PHP afterwards.
        $prefix = $normalized === '' ? '%' : $normalized . '/%';

        $sql = "
        SELECT album_path
        FROM gallery_albums
        WHERE album_path = :exact OR album_path LIKE :likePrefix
    ";
        $rows = $this->db->select($sql, [
            'exact' => $normalized,
            'likePrefix' => $prefix
        ]);

        if (empty($rows)) {
            return ['direct' => 0, 'recursive' => 0];
        }

        $paths = array_column($rows, 'album_path');

        // Determine depths
        $parentDepth = $normalized === '' ? 0 : substr_count($normalized, '/') + 1;
        $directDepth = $parentDepth + 1;

        $directCount = 0;
        $recursiveCount = 0;

        foreach ($paths as $path) {
            if ($path === $normalized || $path === '') {
                continue; // skip the parent itself
            }

            // permission check: only count albums the user may access
            if ($this->checkAlbumPermissions($path) === false) {
                continue;
            }

            $depth = substr_count($path, '/') + 1; // e.g. 'a/b' => 2
            if ($depth === $directDepth) {
                $directCount++;
                $recursiveCount++; // direct are also recursive
            } elseif ($depth > $directDepth) {
                $recursiveCount++;
            }
        }

        return ['direct' => $directCount, 'recursive' => $recursiveCount];
    }

    /**
     * Retrieves all root-level albums.
     * @return array List of permitted root-level albums.
     */
    public function getAllAlbums(): array
    {
        return $this->getSubAlbums('');
    }

    /**
     * Retrieves direct sub-albums for a given album path based on database lookup and permission check.
     * Returns an array of permitted sub-albums, including 'name', 'path', and 'title'.
     *
     * @param string $albumName The album path (relative to the base path).
     * @return array List of permitted sub-albums, each as an associative array.
     */
    public function getSubAlbums(string $albumName): array
    {
        $basePath = trim($albumName, '/');
        $potentialSubAlbumNames = $this->_getDirectSubAlbumPathsFromDB($basePath);
        $permittedSubAlbums = [];

        foreach ($potentialSubAlbumNames as $subAlbumName) {
            $fullSubAlbumPath = empty($basePath) ? $subAlbumName : $basePath . '/' . $subAlbumName;

            $albumData = $this->checkAlbumPermissions($fullSubAlbumPath);

            if ($albumData !== false) {
                $permittedSubAlbums[] = [
                    'name' => $subAlbumName,
                    'path' => $fullSubAlbumPath,
                    'title' => $albumData['title'],
                ];
            }
        }

        usort($permittedSubAlbums, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $permittedSubAlbums;
    }

    /**
     * Retrieves all media items from a given album path, using database lookup.
     *
     * @param string $albumName The path of the album (relative to basePath).
     * @param bool $recursive Whether to include media from subdirectories (uses DB lookup).
     * @param bool $random Whether to randomize the order of the media items.
     * @param bool $includeStats Whether to include view statistics for the media.
     * @return array List of media items, or an empty array if access is denied.
     */
    public function getMediaByAlbum(string $albumName, bool $recursive = false, bool $random = false, bool $includeStats = false): array
    {
        $normalizedPath = trim($albumName, '/');

        $albumData = $this->checkAlbumPermissions($normalizedPath);
        if (!$albumData) {
            return [];
        }

        if ($recursive) {
            $media = $this->_fetchMediaAndStatsFromDBRecursive($normalizedPath, $includeStats);

            $media = array_filter($media, function ($item) {
                return (bool) $this->checkAlbumPermissions($item['album_path']);
            });
        } else {
            try {
                $albumId = $albumData['album_id'] ?? $this->getAlbumIdByPath($normalizedPath);
            } catch (\ckvsoft\CkvException $e) {
                error_log("Database error: Cannot get album ID for path '{$normalizedPath}'.");
                return [];
            }
            $media = $this->_fetchMediaAndStatsFromDB($albumId, $includeStats);
        }

        if ($random) {
            shuffle($media);
        } else {
            usort($media, fn($a, $b) => strcmp($a['file'], $b['file']));
        }

        return $media;
    }

    /**
     * Retrieves all media items for a specific album ID directly from the database (non-recursive).
     * * Uses $includeStats to optionally fetch views and last_view for performance optimization.
     * * @param int $albumId The ID of the album.
     * @param bool $includeStats Whether to include views and last_view.
     * @return array List of media items.
     */
    private function _fetchMediaAndStatsFromDB(int $albumId, bool $includeStats): array
    {
        // Base columns (always fetched)
        $selectBase = "
        gms.id AS media_id,
        gms.file_name,
        gms.file_size,
        gms.file_mtime,
        gms.media_type,
        ga.album_path,
        gmd.title,
        gmd.description
    ";

        // Dynamically add stats columns only if requested
        $selectStats = "";
        if ($includeStats) {
            $selectStats = ", gms.views, gms.last_view";
        }

        $sql = "
        SELECT {$selectBase} {$selectStats} -- Combined SELECT string
        FROM gallery_media_stats gms
        JOIN gallery_albums ga ON ga.album_id = gms.album_id
        LEFT JOIN gallery_media_details gmd ON gmd.media_id = gms.id
        WHERE gms.album_id = :id
        ORDER BY gms.file_name
    ";

        $dbData = $this->db->select($sql, ['id' => $albumId]);

        $mediaList = [];
        foreach ($dbData as $data) {
            $mediaList[] = $this->_transformDbToMediaItem($data, true);
        }

        return $mediaList;
    }

    /**
     * Retrieves all media from the specified album path and all sub-albums.
     * Uses a DB lookup to find all descendant album IDs and queries media for all of them.
     * * Uses $includeStats to optionally fetch views and last_view for performance optimization.
     * * @param string $albumPath The path of the parent album (relative).
     * @param bool $includeStats Whether to include views and last_view.
     * @return array List of media items.
     */
    private function _fetchMediaAndStatsFromDBRecursive(string $albumPath, bool $includeStats): array
    {
        $normalizedPath = trim($albumPath, '/');
        $searchPrefix = empty($normalizedPath) ? '%' : $normalizedPath . '/%';

        $sqlAlbums = "
            SELECT album_id
            FROM gallery_albums
            WHERE album_path = :exactPath OR album_path LIKE :prefix
        ";
        $dbAlbums = $this->db->select($sqlAlbums, [
            'exactPath' => $normalizedPath,
            'prefix' => $searchPrefix
        ]);

        $albumIds = array_column($dbAlbums, 'album_id');

        if (empty($albumIds)) {
            return [];
        }

        $safeAlbumIds = array_map('intval', $albumIds);
        $idList = implode(', ', $safeAlbumIds);
        $rawInClause = new DbExpr($idList);

        // Base columns (always fetched)
        $selectBase = "
        gms.id AS media_id,
        gms.file_name,
        gms.file_size,
        gms.file_mtime,
        gms.media_type,
        ga.album_path,
        gmd.title,
        gmd.description
    ";

        // Dynamically add stats columns only if requested
        $selectStats = "";
        if ($includeStats) {
            $selectStats = ", gms.views, gms.last_view";
        }

        $sqlMedia = "
            SELECT {$selectBase} {$selectStats} -- Combined SELECT string
            FROM gallery_media_stats gms
            JOIN gallery_albums ga ON ga.album_id = gms.album_id
            LEFT JOIN gallery_media_details gmd ON gmd.media_id = gms.id
            WHERE gms.album_id IN ({$rawInClause})
            ORDER BY gms.file_name
        ";

        $dbData = $this->db->select($sqlMedia, []);

        $mediaList = [];
        foreach ($dbData as $data) {
            $mediaList[] = $this->_transformDbToMediaItem($data, true);
        }

        return $mediaList;
    }

    /**
     * Checks if the expected physical thumbnail file exists.
     * @param string $albumPath The relative album path.
     * @param string $thumbFileName The expected file name of the thumbnail (e.g., 'video_thumb.jpg').
     * @return bool True if the file exists, false otherwise.
     */
    private function _doesThumbnailExist(string $albumPath, string $thumbFileName): bool
    {
        $fullPath = rtrim($this->basePath, '/') . '/' . trim($albumPath, '/') . '/' . $thumbFileName;
        return file_exists($fullPath);
    }

    /**
     * Transforms a raw database result row into the final, standardized media item array.
     *
     * Correctly constructs the media URL (/gallery/media/) and the THUMBNAIL URL (/thumbs/),
     * applying the '_thumb' suffix and handling video file extensions.
     *
     * @param array $data Raw data row fetched from the database.
     * @param bool $includeStats If true, views and last_view fields are included (Note: currently always true as the data is fetched).
     * @return array Standardized media item array.
     */
    private function _transformDbToMediaItem(array $data, bool $includeStats): array
    {
        $item = [
            'id' => $data['media_id'] ?? 0,
            'album_path' => $data['album_path'] ?? '',
            'file' => $data['file_name'] ?? '',
            'size' => SizeConverter::bytesToHumanReadable((int) ($data['file_size'] ?? 0)),
            'mtime' => (int) ($data['file_mtime'] ?? 0),
            'date_formatted' => date('Y-m-d H:i:s', ($data['file_mtime'] ?? 0)),
            'type' => $data['media_type'] ?? 'image',
            'url' => '',
            'thumburl' => '',
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        if (!empty($item['file'])) {
            $fileName = $item['file'];
            $albumPath = $item['album_path'];

            $mediaPath = trim($albumPath, '/') . '/' . urlencode($fileName);
            $item['url'] = BASE_URI . 'gallery/media/' . $mediaPath;

            $fileParts = pathinfo($fileName);
            $baseName = $fileParts['filename'] ?? '';
            $extension = $fileParts['extension'] ?? '';

            $thumbExt = ($item['type'] === 'video') ? 'jpg' : $extension;

            $thumbFileName = $baseName . '_thumb.' . $thumbExt;

            if ($this->_doesThumbnailExist($albumPath, $thumbFileName)) {
                $thumbPath = trim($albumPath, '/') . '/' . urlencode($thumbFileName);
                $item['thumburl'] = BASE_URI . 'gallery/media/' . $thumbPath;
            } else {
                if ($item['type'] === 'video') {
                    $item['thumburl'] = self::DEFAULT_VIDEO_THUMB_URL;
                } else {
                    $item['thumburl'] = self::DEFAULT_IMAGE_THUMB_URL;
                }
            }
        }

        if ($includeStats) {
            $item['views'] = (int) ($data['views'] ?? 0);
            $item['last_view'] = $data['last_view'] ?? null;
        }

        return $item;
    }

    /**
     * Retrieves a random thumbnail URL from media items within the specified album.
     *
     * It uses getMediaByAlbum() with $random = false to ensure an efficient, non-random
     * retrieval of the media list (which might be cached), and then selects one randomly.
     *
     * @param string $albumName The path of the album.
     * @param bool $recursive Whether to include media from subdirectories.
     * @return string|null The URL of a random thumbnail, or null if none is found or access is denied.
     */
    public function getRandomThumbnailUrl(string $albumName, bool $recursive = false): ?string
    {
        // Use $random=false to fetch the list efficiently, then randomize here
        $allMedia = $this->getMediaByAlbum($albumName, $recursive, false, true);
        if (empty($allMedia)) {
            return null;
        }

        $itemsWithThumbs = array_filter($allMedia, fn($item) =>
                isset($item['thumburl']) &&
                $item['thumburl'] !== self::DEFAULT_VIDEO_THUMB_URL &&
                $item['thumburl'] !== self::DEFAULT_IMAGE_THUMB_URL
        );

        if (empty($itemsWithThumbs)) {
            return null;
        }

        $randomIndex = array_rand($itemsWithThumbs);
        return $itemsWithThumbs[$randomIndex]['thumburl'];
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
        $expectedPathDepth = empty($searchPathPrefix) ? 1 : count(explode('/', trim($searchPathPrefix, '/'))) + 1;

        foreach ($paths as $path) {
            if ($path === $normalizedPath) {
                continue;
            }

            $pathSegments = explode('/', $path);

            if (count($pathSegments) === $expectedPathDepth) {
                $directSubAlbums[] = end($pathSegments);
            }
        }

        if (empty($normalizedPath)) {
            $sql = "SELECT `album_path` FROM `gallery_albums`";
            $allPaths = $this->db->select($sql);

            foreach (array_column($allPaths, 'album_path') as $path) {
                if (strpos($path, '/') === false && !empty($path)) {
                    $directSubAlbums[] = $path;
                }
            }
        }

        return array_unique($directSubAlbums);
    }
}
