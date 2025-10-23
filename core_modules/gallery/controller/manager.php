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

use ckvsoft\mvc\BaseController;
use ckvsoft\Input;
use ckvsoft\Auth;

class Manager extends BaseController
{

    /**
     * @var \ckvsoft\Input
     */
    private $input;

    /**
     * Constructor: Initializes the controller and performs authentication check.
     */
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
        // Load custom CSS for the gallery management section
        $extraCss = "<style>"
                . $this->loadHelper("css", ['method' => 'getCss', 'args' => ['/inc/css/simple-lightbox.css']])
                . $this->loadHelper("css", ['method' => 'getCss', 'args' => ['inc/css/gallery.css']])
                . "</style>";

        // Load custom JavaScript
        $extraJs = "<script>" . $this->loadScript("/inc/js/simple-lightbox.js") . "</script>";

        // Render the full page with header, content view, and footer
        $this->renderPage([
            // Use _() for the default page title
            ['view' => '/inc/header', 'data' => ['title' => $data['title'] ?? _('Gallery Manager')]],
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

        // The message includes dynamic content, but the static parts can be translated
        $message = _('Album: size -> ') . sizeof($albums);
        $rawPermissionMap = $managerModel::PERMISSION_LEVELS;

        // Translate permission level texts for display
        $permissionMap = [];
        foreach ($rawPermissionMap as $level => $text) {
            $permissionMap[$level] = _($text); // Use translation function
        }

        // Render the album overview page
        $this->render('gallery/manager/index', [
            'albums' => $albums,
            // Use _() for the section title
            'title' => _('Album Management'),
            'permissionMap' => $permissionMap,
            'message' => $message
        ]);
    }

    /**
     * Handles editing album permissions and assigning the owner (GET View and POST Submission).
     * Route: /gallery/manager/edit/123
     * @param int $albumId The ID of the album to edit.
     */
    public function edit(int $albumId): void
    {
        // Use the Manager Model for updates and fetching owner data
        $managerModel = $this->getGalleryManagerModel();

        try {
            // Define expected POST fields, including the new subfolder flags
            $this->input->post('title')
                    ->post('owner_user_id')
                    ->post('album_id')
                    ->post('permissions_level')
                    ->post('apply_owner_to_subfolders') // New checkbox for owner inheritance
                    ->post('apply_permissions_to_subfolders'); // New checkbox for permission inheritance

            $this->input->submit();
            $data = $this->input->fetch();

            // Check if form data was submitted (POST request)
            if (!empty($data)) {
                $title = $data['title'] === '' ? null : $data['title'];
                // Owner ID can be null if '-- Please select --' is chosen
                $ownerId = $data['owner_user_id'] === '' ? null : (int) $data['owner_user_id'];

                // Checkbox values: will be '1' if checked, or not present in $data if unchecked
                $applyOwnerToSubfolders = isset($data['apply_owner_to_subfolders']) && $data['apply_owner_to_subfolders'] == '1';
                $applyPermissionsToSubfolders = isset($data['apply_permissions_to_subfolders']) && $data['apply_permissions_to_subfolders'] == '1';

                $updateData = [
                    'title' => $title,
                    'owner_user_id' => $ownerId,
                    'permissions_level' => (int) $data['permissions_level']
                ];

                // Include inheritance flags for the Model to handle the logic
                $updateOptions = [
                    'apply_owner_to_subfolders' => $applyOwnerToSubfolders,
                    'apply_permissions_to_subfolders' => $applyPermissionsToSubfolders,
                ];

                // Call the model function, passing the inheritance options
                // NOTE: The updateAlbumPermissions method in the model needs to be adapted to accept and process $updateOptions.
                $success = $managerModel->updateAlbumPermissions(
                        $data['album_id'],
                        $updateData,
                        $updateOptions // Pass the new options
                );

                if ($success) {
                    // Use _() for the success message
                    \ckvsoft\Output::success(['message' => _('Album permissions updated successfully.')]);
                } else {
                    // Use _() for the error message
                    \ckvsoft\Output::error(['message' => _('No changes made or update failed.')]);
                }
                return; // End execution for POST request
            }
        } catch (\ckvsoft\CkvException $e) {
            // Handle validation errors or other CkvExceptions
            // The input errors are usually not translated here, but the surrounding message could be
            \ckvsoft\Output::error(['message' => implode('; ', $this->input->fetchErrors())]);
            return; // End execution on error
        }

        // GET request - render the view
        $album = $managerModel->getAlbumById($albumId); // DELEGATION

        if (!$album) {
            // Use _() for the error message
            \ckvsoft\Output::error([_('Album not found.')]);
            $this->redirect(BASE_URI . 'gallery/manager/index');
            return;
        }

        $users = $managerModel->getAllPossibleOwners(); // DELEGATION
        $rawPermissionMap = $managerModel::PERMISSION_LEVELS;

        // Translate permission level texts for the view
        $permissionMap = [];
        foreach ($rawPermissionMap as $level => $text) {
            $permissionMap[$level] = _($text);
        }

        // Render the album edit view
        $this->render('gallery/manager/edit', [
            'album' => $album,
            'users' => $users,
            // Use _() for the title prefix
            'title' => _('Edit Album: ') . htmlspecialchars($album['album_path'] ?? ''),
            'permissionMap' => $permissionMap
        ]);
    }

    /**
     * Displays the media items for a specific album (for editing/management).
     * Route: /gallery/manager/album_media/123
     * @param int $albumId The ID of the album.
     */
    public function album_media(int $albumId): void
    {
        $managerModel = $this->getGalleryManagerModel();
        $album = $managerModel->getAlbumById($albumId);

        if (!$album) {
            // Use _() for the error message
            \ckvsoft\Output::error([_('Album not found.')]);
            $this->redirect(BASE_URI . 'gallery/manager/index');
            return;
        }

        $galleryModel = $this->getGalleryModel();

        // This method fetches and merges stats (true)
        $media = $galleryModel->getMediaByAlbum($album['album_path'], false, false, true);

        // Render the media management view
        $this->render('gallery/manager/media', [
            'album' => $album,
            'media' => $media,
            // Use _() for the title prefix
            'title' => _('Media Management for: ') . htmlspecialchars($album['album_path'])
        ]);
    }

    /**
     * Handles editing media item details (e.g., description, title).
     * Route: /gallery/manager/edit_media/123 (Handles POST Submission and GET View)
     * @param int $mediaId The ID of the media item to edit.
     */
    public function edit_media(int $mediaId): void
    {
        // NOTE: POST submission logic for media editing would go here.

        $managerModel = $this->getGalleryManagerModel();
        $mediaItem = $managerModel->getMediaItemById($mediaId); // Assumption: Method exists in Model

        if (!$mediaItem) {
            // Use _() for the error message
            \ckvsoft\Output::error([_('Media item not found.')]);
            // Redirect back to the album media overview if the item doesn't exist.
            $this->redirect(BASE_URI . 'gallery/manager/album_media/' . ($this->input->get('album_id') ?? ''));
            return;
        }

        // HERE THE FORM LOGIC AND RENDERING OF THE EDIT VIEW WOULD FOLLOW.
        // Render the media item edit view (placeholder array for now)
        $this->render('gallery/manager/edit_media', [
            'item' => $mediaItem,
            // Use _() for the title prefix
            'title' => _('Edit Media: ') . htmlspecialchars($mediaItem['name'] ?? '')
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
        $success = $managerModel->deleteMediaItem($mediaId); // Assumption: Method exists in Model

        if ($success) {
            // Use _() for the success message with placeholders
            \ckvsoft\Output::success(['message' => sprintf(_('Media ID %d and associated files deleted successfully.'), $mediaId)]);
        } else {
            // Use _() for the error message with placeholders
            \ckvsoft\Output::error(['message' => sprintf(_('Failed to delete media item ID %d.'), $mediaId)]);
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
        // Rescan albums, tracking progress
        $results = $managerModel->rescanAlbums($currentUserId, $progressId);

        // Construct the translatable message parts
        $message = _('Rescan complete: ');
        $message .= sprintf(_('%d new albums added. '), $results['added_count']);
        $message .= sprintf(_('%d obsolete albums deleted.'), $results['deleted_count']);

        \ckvsoft\Output::success(['message' => $message]);
    }

    /**
     * Executes the media synchronization process (Rescan Media).
     * Route: /gallery/manager/rescan_media
     */
    public function rescan_media($progressId): void
    {
        $managerModel = $this->getGalleryManagerModel(); // DELEGATION
        // Rescan media, tracking progress and returning stats
        $results = $managerModel->rescanAlbumMedia($progressId);

        // Construct the translatable message parts
        $message = _('Media Rescan complete: ');
        $message .= sprintf(_('%d new media files registered. '), $results['added_count']);
        $message .= sprintf(_('%d already present or unsupported. '), $results['skipped_count']);
        $message .= sprintf(_('%d orphaned DB entries deleted. '), $results['deleted_db_entries']);
        $message .= sprintf(_('%d orphaned thumbnails deleted.'), $results['deleted_thumbnails']);

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
            // Use _() for the success message with placeholders
            $message = sprintf(_('View counter for Album ID %d reset successfully. %d items affected.'), $albumId, $affectedRows);
            \ckvsoft\Output::success(['message' => $message]);
        } else {
            // Use _() for the error message with placeholders
            $message = sprintf(_('Failed to reset view counter for Album ID %d.'), $albumId);
            \ckvsoft\Output::error(['message' => $message]);
        }
    }

    /**
     * Retrieves the progress status for a running job via AJAX.
     * Route: /gallery/manager/progress/1
     * @param int $progressId The ID of the progress job.
     */
    public function progress(int $progressId): void
    {
        // Release the session lock immediately so long-running processes (like rescan)
        // can update the session/progress data without being blocked.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close(); // <-- Releases the session
        }

        $managerModel = $this->getGalleryManagerModel();
        $progress = $managerModel->getProgressStatus($progressId); // Assumption: Method exists in Model

        if (is_array($progress) && isset($progress['percent'])) {
            $modifiedTime = isset($progress['modified']) ? date('H:i:s', strtotime($progress['modified'])) : date('H:i:s');

            \ckvsoft\Output::success([
                'percent' => $progress['percent'] ?? 0,
                'modified' => $modifiedTime
            ]);
            exit;
        }

        // Use _() for the error message
        \ckvsoft\Output::error(['error' => _('Job not running or progress data invalid.')]);
    }
}
