<?php

// gallery/controller/Manager.php

use ckvsoft\mvc\BaseController;
use ckvsoft\Input;
use ckvsoft\Auth;

class Manager extends BaseController
{

    /**
     * @var \ckvsoft\Input
     */
    private $input;

    public function __construct()
    {
        parent::__construct();
        // Uses Auth::isNotLogged('admin') which presumably redirects if not admin
        Auth::isNotLogged('admin');
        $this->input = new Input();
    }

    /**
     * Retrieves the Frontend Gallery Model (used for data display).
     * @return Gallery_Model
     */
    private function getGalleryModel(): Gallery_Model
    {
        // This remains the original Gallery Model
        return $this->loadModel('gallery', 'gallery');
    }

    /**
     * Retrieves the Manager Model (used for administrative updates and rescans).
     * @return GalleryManager_Model
     */
    private function getGalleryManagerModel(): GalleryManager_Model
    {
        // This is the correct, dedicated Manager Model we previously created
        return $this->loadModel('gallerymanager', 'gallery');
    }

    /**
     * Renders the manager page with common includes.
     * @param string $view The view path.
     * @param array $data Data to pass to the view.
     */
    private function render(string $view, array $data = [])
    {
        $extraCss = "<style>"
                . $this->loadHelper("css", ['method' => 'getCss', 'args' => ['/inc/css/simple-lightbox.css']])
                . $this->loadHelper("css", ['method' => 'getCss', 'args' => ['inc/css/gallery.css']])
                . "</style>";

        $extraJs = "<script>" . $this->loadScript("/inc/js/simple-lightbox.js") . "</script>";

        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => $data['title'] ?? 'Gallery Manager']],
            ['view' => $view, 'data' => $data],
            ['view' => '/inc/footer'],
                ], $extraCss, $extraJs);
    }

    // ------------------------------------------------------------------
    // VIEWS & EDIT ACTIONS
    // ------------------------------------------------------------------

    /**
     * Displays the overview of all albums including stats, owner, and permissions.
     * Route: /gallery/manager/index
     */
    public function index(): void
    {
        // Use the Manager Model for admin data
        $managerModel = $this->getGalleryManagerModel();
        $albums = $managerModel->getAllAlbumsWithStats();

        $message = "Album: size -> " . sizeof($albums);

        $this->render('gallery/manager/index', [
            'albums' => $albums,
            'title' => 'Album Management',
            'message' => $message
        ]);
    }

    /**
     * Handles editing album permissions and assigning the owner.
     * Route: /gallery/manager/edit/123 (Handles POST Submission and GET View)
     * @param int $albumId The ID of the album to edit.
     */
    public function edit(int $albumId): void
    {
        // Use the Manager Model for updates and fetching owner data
        $managerModel = $this->getGalleryManagerModel();

        try {
            $this->input->post('owner_user_id')
                    ->post('album_id')
                    ->post('permissions_level');

            $this->input->submit();
            $data = $this->input->fetch();

            if (!empty($data)) {
                // POST submission detected - attempt to update
                $ownerId = $data['owner_user_id'] === '' ? null : (int) $data['owner_user_id'];

                $success = $managerModel->updateAlbumPermissions(// DELEGATION
                        $data['album_id'],
                        $ownerId,
                        (int) $data['permissions_level']
                );

                if ($success) {
                    \ckvsoft\Output::success(['message' => 'Album permissions updated successfully.']);
                } else {
                    \ckvsoft\Output::error(['message' => 'No changes made or update failed.']);
                }
                return;
            }
        } catch (\ckvsoft\CkvException $e) {
            \ckvsoft\Output::error(['message' => implode('; ', $this->input->fetchErrors())]);
            return;
        }

        // GET request - render the view
        $album = $managerModel->getAlbumById($albumId); // DELEGATION

        if (!$album) {
            \ckvsoft\Output::error(['Album not found.']);
            $this->redirect(BASE_URI . 'gallery/manager/index');
        }

        $users = $managerModel->getAllPossibleOwners(); // DELEGATION

        $this->render('gallery/manager/edit', [
            'album' => $album,
            'users' => $users,
            'title' => 'Edit Album: ' . htmlspecialchars($album['album_path'] ?? '')
        ]);
    }

    /**
     * 1. Displays the media items for a specific album (for editing/management).
     * Route: /gallery/manager/album_media/123
     * @param int $albumId The ID of the album.
     */
    public function album_media(int $albumId): void
    {
        $managerModel = $this->getGalleryManagerModel();
        $album = $managerModel->getAlbumById($albumId);

        if (!$album) {
            \ckvsoft\Output::error(['Album not found.']);
            $this->redirect(BASE_URI . 'gallery/manager/index');
        }

        $galleryModel = $this->getGalleryModel();

        // This method fetches and merges stats (true)
        $media = $galleryModel->getMediaByAlbum($album['album_path'], false, false, true);

        $this->render('gallery/manager/media', [
            'album' => $album,
            'media' => $media,
            'title' => 'Media Management for: ' . htmlspecialchars($album['album_path'])
        ]);
    }

    /**
     * Handles editing media item details (e.g., description, title).
     * Route: /gallery/manager/edit_media/123 (Handles POST Submission and GET View)
     * @param int $mediaId The ID of the media item to edit.
     */
    public function edit_media(int $mediaId): void
    {
        // Hier ist eine Weiterleitung zur Implementierung des Formulars und der POST-Verarbeitung nötig.
        // Für den Anfang können wir eine einfache Weiterleitung oder eine minimale Ansicht rendern.

        $managerModel = $this->getGalleryManagerModel();
        $mediaItem = $managerModel->getMediaItemById($mediaId); // Annahme: Methode existiert

        if (!$mediaItem) {
            \ckvsoft\Output::error(['Media item not found.']);
            // Redirect zurück zur Album-Übersicht, falls das Element nicht existiert.
            $this->redirect(BASE_URI . 'gallery/manager/album_media/' . ($this->input->get('album_id') ?? ''));
            return;
        }

        // HIER WÜRDE DIE FORMULAR-LOGIK UND DAS RENDERN DER EDITIER-ANSICHT FOLGEN.
        // Wir rendern hier nur ein Platzhalter-Array.
        $this->render('gallery/manager/edit_media', [
            'item' => $mediaItem,
            'title' => 'Edit Media: ' . htmlspecialchars($mediaItem['name'] ?? '')
        ]);
    }

    /**
     * Handles the media item deletion process.
     * Route: /gallery/manager/delete_media/123
     * @param int $mediaId The ID of the media item to delete.
     */
    public function delete_media(int $mediaId): void
    {
        $managerModel = $this->getGalleryManagerModel();
        $success = $managerModel->deleteMediaItem($mediaId);

        if ($success) {
            \ckvsoft\Output::success(['message' => "Media ID {$mediaId} and associated files deleted successfully."]);
        } else {
            \ckvsoft\Output::error(['message' => "Failed to delete media item ID {$mediaId}."]);
        }
    }

    // ------------------------------------------------------------------
    // RESCAN ACTIONS (AJAX)
    // ------------------------------------------------------------------

    /**
     * Executes the filesystem and database synchronization (Rescan Albums).
     * Route: /gallery/manager/rescan
     */
    public function rescan($progressId): void
    {
        $managerModel = $this->getGalleryManagerModel();
        $currentUserId = Auth::getUserId() ?? null;
        $results = $managerModel->rescanAlbums($currentUserId, $progressId);

        $message = "Rescan complete: ";
        $message .= "{$results['added_count']} new albums added. ";
        $message .= "{$results['deleted_count']} obsolete albums deleted.";

        \ckvsoft\Output::success(['message' => $message]);
    }

    /**
     * Executes the media synchronization process (Rescan Media).
     * Route: /gallery/manager/rescan_media
     */
    public function rescan_media($progressId): void
    {
        $managerModel = $this->getGalleryManagerModel(); // DELEGATION
        // This method is now in GalleryManager_Model and returns stats
        $results = $managerModel->rescanAlbumMedia($progressId);

        $message = "Media Rescan complete: ";
        $message .= "{$results['added_count']} new media files registered. ";
        $message .= "{$results['skipped_count']} already present or unsupported. ";
        $message .= "{$results['deleted_db_entries']} orphaned DB entries deleted. ";
        $message .= "{$results['deleted_thumbnails']} orphaned thumbnails deleted.";

        \ckvsoft\Output::success(['message' => $message]);
    }

    /**
     * Resets the view counter for all media in a specific album.
     * Route: /gallery/manager/reset_views/123
     * @param int $albumId The ID of the album whose counter to reset.
     */
    public function reset_views(int $albumId): void
    {
        $managerModel = $this->getGalleryManagerModel(); // DELEGATION

        $affectedRows = $managerModel->resetAlbumViewCounter($albumId); // DELEGATION

        if ($affectedRows >= 0) {
            \ckvsoft\Output::success(['message' => "View counter for Album ID {$albumId} reset successfully. {$affectedRows} items affected."]);
        } else {
            \ckvsoft\Output::error(['message' => "Failed to reset view counter for Album ID {$albumId}."]);
        }
    }

// Manager.php | Funktion progress

    public function progress(int $progressId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close(); // <-- gibt die Session frei
        }
        $managerModel = $this->getGalleryManagerModel();
        $progress = $managerModel->getProgressStatus($progressId);

        if (is_array($progress) && isset($progress['percent'])) {
            $modifiedTime = isset($progress['modified']) ? date('H:i:s', strtotime($progress['modified'])) : date('H:i:s');

            \ckvsoft\Output::success([
                'percent' => $progress['percent'] ?? 0,
                'modified' => $modifiedTime
            ]);
            exit;
        }

        \ckvsoft\Output::error(['error' => 'Job not running or progress data invalid.']);
        error_log('Job not running or progress data invalid.');
    }
}
