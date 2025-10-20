<?php

class Gallery extends ckvsoft\mvc\BaseController
{

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isNotLogged();
    }

    /**
     * Renders the page with common includes and gallery-specific CSS/JS.
     */
    private function render(string $view, array $data = [])
    {
        $extraCss = "<style>"
                . $this->loadHelper("css", ['method' => 'getCss', 'args' => ['/inc/css/simple-lightbox.css']])
                . $this->loadHelper("css", ['method' => 'getCss', 'args' => ['inc/css/gallery.css']])
                . "</style>";

        $extraJs = "<script>" . $this->loadScript("/inc/js/simple-lightbox.js") . "</script>";

        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => $data['title'] ?? 'Gallery']],
            ['view' => $view, 'data' => $data],
            ['view' => '/inc/footer']
                ], $extraCss, $extraJs);
    }

    public function index(string ...$albumNameParts)
    {
        $albumName = trim(implode('/', $albumNameParts), '/');

        $galleryModel = $this->loadModel('gallery', "gallery");
        $galleryHelper = $this->loadHelper('gallery/gallery');

        $itemInstructions = $galleryHelper->getAlbumGridInstructions($galleryModel, $albumName, true);

        $galleryHtml = '';
        foreach ($itemInstructions as $instruction) {
            $galleryHtml .= $this->view->render($instruction['view'], $instruction['data'], true);
        }

        $breadcrumbData = $galleryHelper->getBreadcrumbData($galleryModel, $albumName);
        $currentAlbumTitle = 'All Albums';

        if (!empty($breadcrumbData)) {
            // Der Titel des aktuellen Albums ist der Titel des letzten Breadcrumb-Elements
            $currentAlbumTitle = end($breadcrumbData)['title'];
        }

        // 4. Prepare Data for View
        $data = [
            'currentAlbum' => empty($albumName) ? 'ALL_ALBUMS' : $albumName,
            'title' => empty($albumName) ? 'All Albums' : $currentAlbumTitle,
            'galleryHtml' => $galleryHtml,
            'breadcrumbData' => $breadcrumbData, // Übergabe an das View
        ];

        $this->render('gallery/index', $data);
    }
}
