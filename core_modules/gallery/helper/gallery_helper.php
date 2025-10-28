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

/**
 * Helper responsible for loading specific album content and preparing the
 * View Instruction Array for rendering in another module's Controller.
 */
class Gallery_Helper extends \ckvsoft\mvc\Helper
{

    /**
     * Collects the raw View Instruction entries for the individual grid items.
     * @param array $contentList The merged list of albums and media items.
     * @return array A list of View Definitions: [['view' => '...', 'data' => [...], ...]]
     */
    private function collectGalleryItemInstructions(array $contentList): array
    {
        $instructions = [];

        foreach ($contentList as $item) {
            $partialPath = match ($item['type'] ?? 'media') {
                'album' => 'gallery/partials/album-item',
                'image', 'media' => 'gallery/partials/image-item',
                'video' => 'gallery/partials/video-item',
                default => null,
            };

            if ($partialPath) {
                $instructions[] = [
                    'view' => $partialPath,
                    'data' => ['item' => $item]
                ];
            }
        }
        return $instructions;
    }

    /**
     * Prepares the grid items (sub-albums and media) for the given album path.
     * * @param Gallery_Model $model
     * @param string $albumPath The path to the album (e.g., 'events/wedding')
     * @param bool $includeSubAlbums Whether sub-albums should be included
     * @param bool $recursive
     * @param bool $random
     * @return array The complete list of instructions, ready for the Controller to execute.
     */
    public function getAlbumGridInstructions(Gallery_Model $model, string $albumPath, bool $includeSubAlbums = true, bool $recursive = false, bool $random = false, string $baseControllerPath = 'gallery/index'): array // 💡 NEUER PARAMETER
    {
        $contentList = [];

        if ($includeSubAlbums) {
            $subAlbums = $model->getSubAlbums($albumPath);
            foreach ($subAlbums as $album) {

                $fullAlbumPath = $album['path'];
                $albumTitle = $album['title'] ?? $model->formatMediaName($album['name']);
                $thumb = $model->getRandomThumbnailUrl($fullAlbumPath, true) ?? BASE_URI . 'gallery/media/default/image_thumb.jpg';

                $contentList[] = [
                    'type' => 'album',
                    'name' => $albumTitle,
                    'url' => BASE_URI . $baseControllerPath . '/' . $fullAlbumPath,
                    'path' => $fullAlbumPath,
                    'thumbnailUrl' => $thumb,
                ];
            }
        }

        $mediaItems = $model->getMediaByAlbum($albumPath, $recursive, $random);
        foreach ($mediaItems as $item) {
            $contentList[] = [
                'type' => $item['type'] ?? 'media',
                'name' => $item['title'] ?? basename($item['url']),
                'description' => $item['description'] ?? '',
                'url' => $item['url'],
                'thumburl' => $item['thumburl'] ?? $item['url'],
            ];
        }

        if (empty($contentList)) {
            return [];
        }

        return $this->collectGalleryItemInstructions($contentList);
    }

    /**
     * Prepares the structured data necessary to display a breadcrumb trail.
     * Fetches the actual album titles from the database for each path segment.
     * * @param Gallery_Model $model The Gallery Model instance.
     * @param string $currentAlbumPath The path to the current album (e.g., 'events/wedding').
     * @return array List of path segments with their DB title and full path.
     */
    public function getBreadcrumbData(Gallery_Model $model, string $currentAlbumPath): array
    {
        if (empty($currentAlbumPath) || $currentAlbumPath === 'ALL_ALBUMS') {
            return [];
        }

        $segments = explode('/', trim($currentAlbumPath, '/'));
        $pathMap = [];
        $pathAccumulator = '';

        foreach ($segments as $segment) {
            if (empty($segment))
                continue;

            $pathAccumulator = trim($pathAccumulator . '/' . $segment, '/');

            $albumData = $model->checkAlbumPermissions($pathAccumulator);

            $title = $albumData['title'] ?? $segment;

            $pathMap[] = [
                'name' => $segment,
                'title' => $title,
                'path' => $pathAccumulator
            ];
        }
        return $pathMap;
    }
}
