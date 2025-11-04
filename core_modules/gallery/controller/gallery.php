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

class Gallery extends ckvsoft\mvc\BaseController
{

    public function __construct()
    {
        parent::__construct();
        // Checks if the user is logged out (or not logged in)
        \ckvsoft\Auth::isNotLogged();
    }

    /**
     * Renders the page with common includes and gallery-specific CSS/JS.
     *
     * @param string $view The view file to render.
     * @param array $data Data to pass to the view.
     */
    private function render(string $view, array $data = [])
    {
        // Default title for translation
        $defaultTitle = _('Gallery');

        // Load extra CSS files inline
        $extraCss = "<style>"
                . $this->loadHelper("css", ['method' => 'getCss', 'args' => ['/inc/css/simple-lightbox.css']])
                . $this->loadHelper("css", ['method' => 'getCss', 'args' => ['inc/css/gallery.css']])
                . "</style>";

        // Load extra JavaScript file inline
        $extraJs = "<script>" . $this->loadScript("/inc/js/simple-lightbox.min.js") . "</script>";

        // Render the complete page structure (header, content, footer)
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => $data['title'] ?? $defaultTitle]],
            ['view' => $view, 'data' => $data],
            ['view' => '/inc/footer']
                ], $extraCss, $extraJs);
    }

    /**
     * Main action to display the gallery or a specific album.
     *
     * @param string ...$albumNameParts URL segments making up the album path.
     */
    public function index(string ...$albumNameParts)
    {
        // Reconstruct the full album path from URL segments
        $albumName = trim(implode('/', $albumNameParts), '/');

        // Load necessary model and helper
        $galleryModel = $this->loadModel('gallery', "gallery");
        $galleryHelper = $this->loadHelper('gallery/gallery');

        // Get instructions (view and data) for rendering the grid of albums/images
        $album_data = $galleryHelper->getAlbumGridInstructions($galleryModel, $albumName, true);

        $itemInstructions = $album_data['instructions'];
        // Render all gallery items into a single HTML string
        $galleryHtml = '';
        foreach ($itemInstructions as $instruction) {
            $galleryHtml .= $this->view->render($instruction['view'], $instruction['data'], true);
        }

        // Get data for the breadcrumb navigation
        $breadcrumbData = $galleryHelper->getBreadcrumbData($galleryModel, $albumName);
        // Default title for the main index page (for translation)
        $currentAlbumTitle = _('All Albums');

        if (!empty($breadcrumbData)) {
            // The title of the current album is the title of the last breadcrumb element
            $currentAlbumTitle = end($breadcrumbData)['title'];
        }

        // 4. Prepare Data for View
        $data = [
            'currentAlbum' => empty($albumName) ? 'ALL_ALBUMS' : $albumName,
            'title' => empty($albumName) ? _('All Albums') : $currentAlbumTitle,
            'galleryHtml' => $galleryHtml,
            'breadcrumbData' => $breadcrumbData,
            'albumCountDirect' => $album_data['albumCountDirect'],
            'albumCountRecursive' => $album_data['albumCountRecursive'],
            'mediaCount' => $album_data['mediaCount'],
        ];

        // Render the main gallery view
        $this->render('gallery/index', $data);
    }
}
