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

    /**
     * Main gallery page.
     */
// Gallery.php

    public function index(string ...$albumNameParts)
    {
        $albumName = trim(implode('/', $albumNameParts), '/');
// Hier ist nur 'gallery' als Name korrekt, wenn die Helper-Klasse Gallery_Helper heißt.
        // Falls der Pfad 'gallery/gallery' korrekt ist, behalten Sie ihn bei.
        $galleryHelper = $this->loadHelper('gallery/gallery');

        // Die gesamte Logik zum Laden von Alben und Medien (der lange Block mit den foreach-Schleifen)
        // MUSS HIER ENTFERNT werden, da sie jetzt in der Helper-Methode ist.
        // --- NEU: Rendering-Anweisungen vom Helper holen ---
        // Wir rufen die neue Helper-Methode auf, die die Daten holt und die Anweisungsliste liefert.

        $itemInstructions = $galleryHelper->getAlbumGridInstructions($this->loadModel('gallery', "gallery"), $albumName, true);

        // --- NEU: Item-Anweisungen rendern und in HTML-String umwandeln ---
        $galleryHtml = '';

        // Da wir wissen, dass JEDER Controller $this->view->render kann:
        foreach ($itemInstructions as $instruction) {
            // Die dritte true-Variable sorgt für die String-Rückgabe
            $galleryHtml .= $this->view->render($instruction['view'], $instruction['data'], true);
        }

        // --- Breadcrumb & Titel (Bleibt gleich) ---
        $data = [
            'currentAlbum' => empty($albumName) ? 'ALL_ALBUMS' : $albumName,
            'title' => empty($albumName) ? 'All Albums' : 'Album: ' . $albumName,
            'galleryHtml' => $galleryHtml, // Der fertige HTML-String
        ];

        $this->render('gallery/index', $data);
    }

    /**
     * Media serving (images/videos)
     */
    public function media(string ...$pathParts)
    {
        if (empty($pathParts)) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        $encodedFileName = array_pop($pathParts);
        $decodedAlbum = implode('/', array_map('urldecode', $pathParts));
        $decodedFile = urldecode($encodedFileName);

        $model = $this->loadModel('gallery', 'gallery');
        $filePath = $model->getFilePath($decodedAlbum, $decodedFile);

        if (!file_exists($filePath)) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        if (is_dir($filePath)) {
            $this->redirect(BASE_URI . 'gallery/index/' . implode('/', $pathParts) . '/' . $encodedFileName);
            exit;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => mime_content_type($filePath),
        };

        header("Content-Type: $mimeType");
        header("Content-Length: " . filesize($filePath));
        header('Cache-Control: public, max-age=3600');

        readfile($filePath);
        exit;
    }
}
