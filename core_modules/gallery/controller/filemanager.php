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

use ckvsoft\Auth;

class Filemanager extends ckvsoft\mvc\BaseController
{

    public function __construct()
    {
        parent::__construct();
        // Ensure only logged-in users with permissions can access this tool
        Auth::isNotLogged();
        ;
    }

    /**
     * Display the main Side-by-Side File Manager View.
     * @param string ...$pathParts Optional path segments for the initial view
     */
    public function index(string ...$pathParts)
    {
        // Load the new Filemanager model
        $fileManagerModel = $this->loadModel('filemanager', 'gallery');

        // Concatenate path segments from the URL (e.g., 'performance/team')
        $currentPath = trim(implode('/', $pathParts), '/');

        // Load initial content for the panels
        $leftPanelData = $fileManagerModel->listDirectoryContents($currentPath);

        $data = [
            'leftPanel' => $leftPanelData,
            'rightPanel' => $leftPanelData, // Both panels start at the same location
            'currentPath' => $currentPath,
        ];

        // Render the main page
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => _('File Manager')]],
            ['view' => 'gallery/filemanager/index', 'data' => ['manager' => $data]],
            ['view' => '/inc/footer'],
        ]);
    }

    /**
     * Handles AJAX request to change directory or list contents.
     * @param string ...$pathParts The requested directory path
     */
    public function browse(string ...$pathParts)
    {
        $fileManagerModel = $this->loadModel('filemanager', 'gallery');
        $requestedPath = trim(implode('/', $pathParts), '/');

        $contents = $fileManagerModel->listDirectoryContents($requestedPath);

        \ckvsoft\Output::success(['path' => $requestedPath, 'contents' => $contents]);
    }

    /**
     * Handles POST request for moving a file/folder (Drag-and-Drop).
     */
    public function move()
    {
        $input = new \ckvsoft\Input();
        $input->post('sourcePath', true)
                ->post('targetPath', true)
                ->submit();

        $data = $input->fetch();
        if ($data === false) {
            \ckvsoft\Output::error(['message' => $input->fetchErrors()]);
            return; // Output::error usually exits, but safe to return/exit here
        }

        $sourcePath = $data['sourcePath'] ?? null;
        $targetPath = $data['targetPath'] ?? null;

        if (!$sourcePath || !$targetPath) {
            \ckvsoft\Output::error(['message' => _('Missing source or target path.')]);
            return;
        }

        $fileManagerModel = $this->loadModel('filemanager', 'gallery');

        try {
            $success = $fileManagerModel->moveItem($sourcePath, $targetPath);

            if ($success) {
                \ckvsoft\Output::success(['message' => _('Item moved successfully.')]);
            } else {
                // This path handles DB-related failures where the Model returned false.
                \ckvsoft\Output::error(['message' => _('Move failed due to a database error.')]);
            }
        } catch (\ckvsoft\CkvException $e) {
            // Catches expected conflicts: "Source does not exist", "Destination already exists", "Rename failed".
            \ckvsoft\Output::error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handles POST request to create a new directory.
     */
    public function mkdir()
    {
        // 1. Use ckvsoft\Input for validation and fetching data
        $input = new \ckvsoft\Input();
        $input->post('newDirName', true)
                ->post('targetPath', true)
                ->submit();

        $data = $input->fetch();

        if ($data === false) {
            \ckvsoft\Output::error(['message' => $input->fetchErrors()]);
            return;
        }

        $newDirName = $data['newDirName'] ?? null;
        $targetPath = $data['targetPath'] ?? null;

        if (!$newDirName || !$targetPath) {
            \ckvsoft\Output::error(['message' => _('Missing directory name or target path.')]);
            return;
        }

        $fileManagerModel = $this->loadModel('filemanager', 'gallery');

        try {
            $success = $fileManagerModel->createDirectory($targetPath, $newDirName);

            if ($success) {
                \ckvsoft\Output::success(['message' => _('Directory created successfully.')]);
            } else {
                \ckvsoft\Output::error(_('Failed to create directory due to a database error.'));
            }
        } catch (\ckvsoft\CkvException $e) {
            \ckvsoft\Output::error($e->getMessage());
            exit;
        }
    }

    /**
     * Handles POST request to delete selected files or folders.
     */
    public function delete()
    {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true); // Daten als assoziatives Array
        // Wir suchen direkt nach 'paths' im dekodierten JSON-Array
        if (empty($data) || empty($data['paths']) || !is_array($data['paths'])) {
            \ckvsoft\Output::error(['message' => _('Missing or invalid path list for deletion.')]);
            return;
        }

        $pathsToDelete = $data['paths']; // Jetzt ist $pathsToDelete das Array
        $fileManagerModel = $this->loadModel('filemanager', 'gallery');
        $errors = [];

        // Process each path individually
        foreach ($pathsToDelete as $path) {
            try {
                // The Model handles the logic: permission check, file system, and DB update.
                $success = $fileManagerModel->deleteItem($path);

                if (!$success) {
                    // Collect specific DB errors if the Model returned false
                    $errors[] = _("Failed to delete item from database:") . " {$path}";
                }
            } catch (\ckvsoft\CkvException $e) {
                // Collect file system or permission errors
                $errors[] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            \ckvsoft\Output::success(['message' => _('Selected items deleted successfully.')]);
        } else {
            $responseData = [
                'success' => 0,
                'errorMessage' => null, // Optional
                'data' => [
                    'message' => _('Deletion completed with errors.'),
                    'details' => $errors
                ]
            ];

            \ckvsoft\Output::json($responseData);
        }
    }

    public function upload()
    {
        $input = new \ckvsoft\Input();
        $input->post('targetPath', true) // The target folder for the upload
                ->post('file_name', true) // The name of the file field in the JS FormData object
                ->submit();

        $data = $input->fetch();
        $targetPath = $data['targetPath'] ?? null;
        $fileName = $data['file_name'] ?? null;

        try {
            $model = $this->loadModel('filemanager', 'gallery');
            if ($model->uploadImage($targetPath, $fileName)) {
                \ckvsoft\Output::success(['message' => _('File uploaded successfully.')]);
            } else {
                \ckvsoft\Output::error(['message' => _('File upload failed for unknown reason or file not registered in DB.')]);
            }
        } catch (\ckvsoft\CkvException $e) {
            \ckvsoft\Output::error(['message' => _('Upload Error: ') . $e->getMessage()]);
        }
    }
}
