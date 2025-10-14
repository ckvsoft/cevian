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
        $galleryHelper = $this->loadHelper('gallery/gallery');
        $itemInstructions = $galleryHelper->getAlbumGridInstructions($this->loadModel('gallery', "gallery"), $albumName, true);

        $galleryHtml = '';

        foreach ($itemInstructions as $instruction) {
            $galleryHtml .= $this->view->render($instruction['view'], $instruction['data'], true);
        }

        $data = [
            'currentAlbum' => empty($albumName) ? 'ALL_ALBUMS' : $albumName,
            'title' => empty($albumName) ? 'All Albums' : 'Album: ' . $albumName,
            'galleryHtml' => $galleryHtml, // Der fertige HTML-String
        ];

        $this->render('gallery/index', $data);
    }
}
