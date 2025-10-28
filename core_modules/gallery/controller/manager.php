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
        return $this->loadModel('gallery', 'gallery');
    }

    /**
     * Retrieves the Manager Model (used for administrative updates and rescans).
     * @return GalleryManager_Model
     */
    private function getGalleryManagerModel(): GalleryManager_Model
    {
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
        $managerModel = $this->getGalleryManagerModel();

        try {
            $this->input->post('title')
                    ->post('owner_user_id')
                    ->post('album_id')
                    ->post('permissions_level')
                    ->post('apply_owner_to_subfolders')
                    ->post('apply_permissions_to_subfolders');

            $this->input->submit();
            $data = $this->input->fetch();

            if (!empty($data)) {
                $title = $data['title'] === '' ? null : $data['title'];
                $ownerId = $data['owner_user_id'] === '' ? null : (int) $data['owner_user_id'];

                $applyOwnerToSubfolders = isset($data['apply_owner_to_subfolders']) && $data['apply_owner_to_subfolders'] == '1';
                $applyPermissionsToSubfolders = isset($data['apply_permissions_to_subfolders']) && $data['apply_permissions_to_subfolders'] == '1';

                $updateData = [
                    'title' => $title,
                    'owner_user_id' => $ownerId,
                    'permissions_level' => (int) $data['permissions_level']
                ];

                $updateOptions = [
                    'apply_owner_to_subfolders' => $applyOwnerToSubfolders,
                    'apply_permissions_to_subfolders' => $applyPermissionsToSubfolders,
                ];

                $success = $managerModel->updateAlbumPermissions(
                        $data['album_id'],
                        $updateData,
                        $updateOptions // Pass the new options
                );

                if ($success) {
                    \ckvsoft\Output::success(['message' => _('Album permissions updated successfully.')]);
                } else {
                    \ckvsoft\Output::error(['message' => _('No changes made or update failed.')]);
                }
                return;
            }
        } catch (\ckvsoft\CkvException $e) {
            \ckvsoft\Output::error(['message' => implode('; ', $this->input->fetchErrors())]);
            return;
        }

        $album = $managerModel->getAlbumById($albumId); // DELEGATION

        if (!$album) {
            \ckvsoft\Output::error([_('Album not found.')]);
            $this->redirect(BASE_URI . 'gallery/manager/index');
            return;
        }

        $users = $managerModel->getAllPossibleOwners();
        $rawPermissionMap = $managerModel::PERMISSION_LEVELS;

        $permissionMap = [];
        foreach ($rawPermissionMap as $level => $text) {
            $permissionMap[$level] = _($text);
        }

        $this->render('gallery/manager/edit', [
            'album' => $album,
            'users' => $users,
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
            \ckvsoft\Output::error([_('Album not found.')]);
            $this->redirect(BASE_URI . 'gallery/manager/index');
            return;
        }

        $galleryModel = $this->getGalleryModel();

        $media = $galleryModel->getMediaByAlbum($album['album_path'], false, false, true);

        $this->render('gallery/manager/media', [
            'album' => $album,
            'media' => $media,
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
        $managerModel = $this->getGalleryManagerModel();

        try {
            $this->input->post('media_id')
                    ->post('title')
                    ->post('description');

            $this->input->submit();
            $data = $this->input->fetch();

            if (!empty($data)) {
                if ((int) $data['media_id'] !== $mediaId) {
                    \ckvsoft\Output::error(['message' => _('Security check failed: Media ID mismatch.')]);
                    return;
                }

                $updateData = [
                    'title' => $data['title'] === '' ? null : $data['title'],
                    'description' => $data['description'] === '' ? null : $data['description']
                ];

                $success = $managerModel->updateMediaDetails(
                        $mediaId,
                        $updateData
                );

                if ($success) {
                    \ckvsoft\Output::success(['message' => _('Media details updated successfully.')]);
                } else {
                    \ckvsoft\Output::error(['message' => _('No changes made or update failed.')]);
                }
                return;
            }
        } catch (\ckvsoft\CkvException $e) {
            \ckvsoft\Output::error(['message' => implode('; ', $this->input->fetchErrors())]);
            return;
        }

        $mediaItem = $managerModel->getMediaItemById($mediaId); // Assumption: Method exists and retrieves details

        if (!$mediaItem) {
            \ckvsoft\Output::error([_('Media item not found.')]);
            $this->redirect(BASE_URI . 'gallery/manager/album_media/' . ($this->input->get('album_id') ?? ''));
            return;
        }

        $this->render('gallery/manager/edit_media', [
            'item' => $mediaItem,
            'title' => _('Edit Media: ') . htmlspecialchars($mediaItem['title'] ?? $mediaItem['file'] ?? _('Unknown'))
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
            \ckvsoft\Output::success(['message' => sprintf(_('Media ID %d and associated files deleted successfully.'), $mediaId)]);
        } else {
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
        $results = $managerModel->rescanAlbums($currentUserId, $progressId);

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
        $managerModel = $this->getGalleryManagerModel();
        $results = $managerModel->rescanAlbumMedia($progressId);

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
        $managerModel = $this->getGalleryManagerModel();

        $affectedRows = $managerModel->resetAlbumViewCounter($albumId);

        if ($affectedRows >= 0) {
            $message = sprintf(_('View counter for Album ID %d reset successfully. %d items affected.'), $albumId, $affectedRows);
            \ckvsoft\Output::success(['message' => $message]);
        } else {
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
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
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

        \ckvsoft\Output::error(['error' => _('Job not running or progress data invalid.')]);
    }
}
