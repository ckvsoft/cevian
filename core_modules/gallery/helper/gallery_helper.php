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
// Logic: Determine the correct partial path based on the item type.
            $partialPath = match ($item['type'] ?? 'media') {
                'album' => 'gallery/partials/album-item',
                'image', 'media' => 'gallery/partials/image-item',
                'video' => 'gallery/partials/video-item',
                default => null,
            };

            if ($partialPath) {
// Collect the instruction (view path and data)
                $instructions[] = [
                    'view' => $partialPath,
                    'data' => ['item' => $item]
                ];
            }
        }
        return $instructions;
    }

    /**
     * Public Service Method: Fetches the data for a specific album path
     * and returns the instruction list for rendering.
     * @param string $albumPath The path to the album (e.g., 'events/wedding').
     * @param bool $includeSubAlbums Whether sub-albums should be included.
     * @return array The complete list of instructions, ready for the Controller to execute.
     */
    public function getAlbumGridInstructions(Gallery_Model $model, string $albumPath, bool $includeSubAlbums = true): array
    {
        $contentList = [];

// --- 1. Load Albums/Sub-albums ---
// (Logik zur Befüllung von $contentList bleibt unverändert)
        if ($includeSubAlbums) {
            $subAlbumNames = $model->getSubAlbums($albumPath);
            foreach ($subAlbumNames as $name) {
                $fullAlbumPath = empty($albumPath) ? $name : $albumPath . '/' . $name;
                $thumb = $model->getRandomThumbnailUrl($fullAlbumPath, true) ?? BASE_URI . 'inc/images/folder_placeholder.jpg';

                $contentList[] = [
                    'type' => 'album',
                    'name' => $model->formatMediaName($name),
                    'url' => BASE_URI . 'gallery/index/' . $fullAlbumPath,
                    'path' => $fullAlbumPath,
                    'thumbnailUrl' => $thumb,
                ];
            }
        }

// --- 2. Load Media (Images/Videos) ---
        $mediaItems = $model->getMediaByAlbum($albumPath, false, false);
        foreach ($mediaItems as $item) {
            $contentList[] = [
                'type' => $item['type'] ?? 'media',
                'name' => $item['name'] ?? basename($item['url']),
                'url' => $item['url'],
                'thumburl' => $item['thumburl'] ?? $item['url'],
            ];
        }

        if (empty($contentList)) {
            return [];
        }

// 3. Return the instruction list
        return $this->collectGalleryItemInstructions($contentList);
    }
}
