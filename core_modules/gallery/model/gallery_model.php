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

    public function getFilePath(string $albumName, string $fileName): string
    {
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
