<?php

use ckvsoft\mvc\Config;

class Gallery_Model extends ckvsoft\mvc\Model
{

    private string $basePath;
    private string $albumsBaseUrl;

    private const DEFAULT_VIDEO_THUMB = BASE_URI . 'inc/images/default_video_thumb.jpg';

    public function __construct()
    {
        parent::__construct();

        $relativePath = Config::get('paths.albums_relative_path') ?? 'public/albums/';
        $relativePath = trim($relativePath, '/');

        $this->basePath = defined('APP_ROOT_PATH') ? APP_ROOT_PATH . '/' . $relativePath . '/' : __DIR__ . '/../../../' . $relativePath . '/';
        $this->albumsBaseUrl = BASE_URI . $relativePath . '/';
    }

    /**
     * Increments the view counter for the given media file.
     * It first determines/creates the album_id and then updates the counter.
     * * @param string $albumPath The path to the album (e.g., 'events/hochzeit').
     * @param string $fileName The filename (e.g., 'image.jpg').
     * @param int|null $currentUserId Optional ID of the user accessing the medium (used for initial album creation).
     */
    public function incrementViewCounter(string $albumPath, string $fileName, ?int $currentUserId = null): void
    {
        // STEP 1: Get the Album ID (pass the user ID for creation)
        try {
            // Pass the user ID to the internal method for owner assignment
            $albumId = $this->getAlbumIdByPath($albumPath, $currentUserId);
        } catch (\Exception $e) {
            error_log("Database error during album ID retrieval: " . $e->getMessage());
            return;
        }

        // STEP 2: Update the counter entry using album_id
        $this->db->insertUpdate('gallery_media_stats', [
            'album_id' => $albumId,
            'file_name' => $fileName,
            // Atomic count update: start at 1 if new, increment by 1 if existing
            'views' => new \ckvsoft\DbExpr('IF(`views` IS NULL, 1, `views` + 1)')
        ]);
    }

    /**
     * Gets the album ID by path, or creates a new entry in gallery_albums if the path does not exist.
     * Handles race conditions using insertUpdate/select fallback.
     * @param string $albumPath The path of the album (e.g., 'events/hochzeit').
     * @param int|null $currentUserId Optional ID of the user creating the album.
     * @return int The ID of the album.
     * @throws \ckvsoft\CkvException If the album ID cannot be retrieved or created.
     */
    private function getAlbumIdByPath(string $albumPath, ?int $currentUserId = null): int
    {
        $normalizedPath = trim($albumPath, '/');

        // 1. ATTEMPT: Retrieve existing album ID
        $album = $this->db->select(
                "SELECT `album_id` FROM `gallery_albums` WHERE `album_path` = :path",
                ['path' => $normalizedPath]
        );

        if (!empty($album)) {
            return (int) $album[0]['album_id'];
        }

        // 2. IF NOT FOUND: Create album using insertUpdate.
        $data = [
            'album_path' => $normalizedPath,
            'permissions_level' => 0, // Default to public
            'owner_user_id' => $currentUserId, // Assign owner here
        ];

        // Create album using insertUpdate (Assuming this method returns the new ID or 0 on duplicate/failure)
        $albumId = $this->db->insertUpdate('gallery_albums', $data);

        if ($albumId > 0) {
            return (int) $albumId;
        }

        // 3. FALLBACK: Re-select the ID. This catches the case where another process
        // just created the album (race condition), and the initial insertUpdate did not return the ID.
        $album = $this->db->select(
                "SELECT `album_id` FROM `gallery_albums` WHERE `album_path` = :path",
                ['path' => $normalizedPath]
        );

        if (!empty($album)) {
            return (int) $album[0]['album_id'];
        }

        // Fail if ID could not be determined
        throw new \ckvsoft\CkvException("Could not determine or create album_id for path '{$normalizedPath}'.");
    }

    /**
     * Returns the absolute file path for a medium.
     * Increments the counter for non-thumbnail/non-placeholder media entries.
     * * @param string $albumName The album path.
     * @param string $fileName The medium's filename.
     * @return string The absolute path to the file.
     */
    public function getFilePath(string $albumName, string $fileName): string
    {
        $currentUserId = \ckvsoft\Auth::getUserId();
        $nameNoExt = pathinfo($fileName, PATHINFO_FILENAME);

        // 1. Exclusion Checks:
        $isThumbnail = str_ends_with(strtolower($nameNoExt), '_thumb');
        $isDefaultPlaceholder = (str_contains(strtolower($fileName), 'placeholder') || str_contains(strtolower($fileName), 'default_video_thumb'));

        // 2. Counter Logic: Only count main media files
        if (!$isThumbnail && !$isDefaultPlaceholder) {
            // Ruft den vereinfachten Zähler auf
            $this->incrementViewCounter($albumName, $fileName, $currentUserId);
        }

        // 3. Return file path:
        return $this->basePath . trim($albumName . '/' . $fileName, '/');
    }

    public function getAllAlbums(): array
    {
        return $this->getSubAlbums('');
    }

    public function getSubAlbums(string $albumName): array
    {
        $albumDir = rtrim($this->basePath . '/' . trim($albumName, '/'), '/');
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

    public function getMediaByAlbum(string $albumName, bool $recursive = false, bool $random = false): array
    {
        $albumDir = rtrim($this->basePath . '/' . trim($albumName, '/'), '/');

        if (!is_dir($albumDir))
            return [];

        $media = $recursive ? $this->scanDirectoryRecursive($albumDir, $albumName) : $this->scanDirectory($albumDir, $albumName);

        if ($random)
            shuffle($media);
        else
            usort($media, fn($a, $b) => strcmp($a['file'], $b['file']));

        return $media;
    }

    public function getRandomThumbnailUrl(string $albumName, bool $recursive = false): ?string
    {
        $allMedia = $this->getMediaByAlbum($albumName, $recursive, false);
        $itemsWithThumbs = array_filter($allMedia, fn($item) => isset($item['thumburl']) && $item['thumburl'] !== self::DEFAULT_VIDEO_THUMB);

        if (empty($itemsWithThumbs))
            return null;

        $randomIndex = array_rand($itemsWithThumbs);
        return $itemsWithThumbs[$randomIndex]['thumburl'];
    }

    public function formatMediaName(string $fileName): string
    {
        // 1. Dateierweiterung entfernen
        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);

        // 2. Unterstriche (_) durch Leerzeichen ersetzen
        $nameCleaned = str_replace('_', ' ', $nameWithoutExt);

        // 3. Anfangsbuchstaben groß schreiben (Title Case)
        return ucwords(strtolower($nameCleaned));
    }

    private function scanDirectory(string $directory, string $albumName): array
    {
        $media = [];
        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $videoExt = ['mp4', 'webm', 'ogg'];

        foreach (scandir($directory) as $file) {
            if ($file === '.' || $file === '..')
                continue;

            $filePath = $directory . '/' . $file;
            if (is_dir($filePath))
                continue;

            $origExt = pathinfo($file, PATHINFO_EXTENSION);
            $ext = strtolower($origExt);
            $nameNoExt = pathinfo($file, PATHINFO_FILENAME);

            if (str_ends_with($nameNoExt, '_thumb'))
                continue;

            // Originalpfad (enthält Leerzeichen)
            $fullUrlPath = trim($albumName . '/' . $file, '/');

            // NEU: Pfadsegmente teilen und URL-KODIEREN
            $pathSegments = explode('/', $fullUrlPath);
            $encodedPath = implode('/', array_map('urlencode', $pathSegments));

            if (in_array($ext, $imageExt)) {
                $thumbFile = $directory . '/' . $nameNoExt . '_thumb.' . $origExt;
                if (!file_exists($thumbFile)) {
                    $image = new \ckvsoft\Image($filePath, $nameNoExt . '_thumb.' . $origExt, $directory . '/');
                    $image->resize();
                }

                // NEU: Thumbnail-Pfad KODIEREN
                $thumbFileName = $nameNoExt . '_thumb.' . $origExt;
                $thumbUrlPath = trim($albumName . '/' . $thumbFileName, '/');
                $thumbSegments = explode('/', $thumbUrlPath);
                $encodedThumbPath = implode('/', array_map('urlencode', $thumbSegments));

                $media[] = [
                    'type' => 'image',
                    'file' => $file,
                    'name' => $this->formatMediaName($file),
                    'url' => BASE_URI . 'gallery/media/' . $encodedPath, // KODIERT
                    'thumburl' => BASE_URI . 'gallery/media/' . $encodedThumbPath, // KODIERT
                ];
            } elseif (in_array($ext, $videoExt)) {
                $thumbFile = $directory . '/' . $nameNoExt . '_thumb.jpg';

                // NEU: Video-Thumbnail-Pfad KODIEREN
                $thumbPathSegments = explode('/', trim($albumName . '/' . $nameNoExt . '_thumb.jpg', '/'));
                $encodedVideoThumbPath = implode('/', array_map('urlencode', $thumbPathSegments));

                $finalThumb = file_exists($thumbFile) ? BASE_URI . 'gallery/media/' . $encodedVideoThumbPath // KODIERT
                        : self::DEFAULT_VIDEO_THUMB;

                $media[] = [
                    'type' => 'video',
                    'file' => $file,
                    'name' => $this->formatMediaName($file),
                    'url' => BASE_URI . 'gallery/media/' . $encodedPath, // KODIERT
                    'thumburl' => $finalThumb,
                ];
            }
        }

        return $media;
    }

    private function scanDirectoryRecursive(string $directory, string $rootAlbumName): array
    {
        $media = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS));
        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $videoExt = ['mp4', 'webm', 'ogg'];

        foreach ($rii as $item) {
            if ($item->isDir())
                continue;

            $file = $item->getFilename();
            $origExt = $item->getExtension();
            $ext = strtolower($origExt);
            $nameNoExt = $item->getBasename('.' . $origExt);

            if (str_ends_with($nameNoExt, '_thumb'))
                continue;

            $absolutePath = $item->getPathname();
            $relativePath = str_replace($this->basePath, '', $absolutePath);
            $fullUrlPath = trim($relativePath, '/');

            if (in_array($ext, $imageExt)) {
                $thumbFile = $item->getPath() . '/' . $nameNoExt . '_thumb.' . $origExt;
                if (!file_exists($thumbFile)) {
                    $image = new \ckvsoft\Image($absolutePath, $nameNoExt . '_thumb.' . $origExt, $item->getPath() . '/');
                    $image->resize();
                }

                $media[] = [
                    'type' => 'image',
                    'file' => $file,
                    'name' => $this->formatMediaName($file),
                    'url' => BASE_URI . 'gallery/media/' . $fullUrlPath,
                    'thumburl' => BASE_URI . 'gallery/media/' . trim(str_replace($this->basePath, '', $thumbFile), '/'),
                ];
            } elseif (in_array($ext, $videoExt)) {
                $thumbFile = $item->getPath() . '/' . $nameNoExt . '_thumb.jpg';
                $finalThumb = file_exists($thumbFile) ? BASE_URI . 'gallery/media/' . trim(str_replace($this->basePath, '', $thumbFile), '/') : self::DEFAULT_VIDEO_THUMB;

                $media[] = [
                    'type' => 'video',
                    'file' => $file,
                    'name' => $this->formatMediaName($file),
                    'url' => BASE_URI . 'gallery/media/' . $fullUrlPath,
                    'thumburl' => $finalThumb,
                ];
            }
        }

        return $media;
    }
}
