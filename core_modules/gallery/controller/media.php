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

class Media extends ckvsoft\mvc\BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function media(string ...$pathParts)
    {
        if (empty($pathParts)) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        // Create a Request instance (ideally injected via controller constructor)
        $request = new \ckvsoft\Request();

        $encodedFileName = array_pop($pathParts);
        $decodedAlbum = implode('/', array_map('urldecode', $pathParts));
        $decodedFile = urldecode($encodedFileName);

        $model = $this->loadModel('gallery', 'gallery');
        $filePath = $model->getFilePath($decodedAlbum, $decodedFile);

        // 🔸 Fallback handling for default or deny images
        if ($filePath === null) {
            $defaultAssetBaseDir = __DIR__ . '/../view/inc/images/';

            if ($decodedAlbum === 'default' && in_array($decodedFile, ['video_thumb.jpg', 'image_thumb.jpg'])) {
                $potentialPath = $defaultAssetBaseDir . $decodedFile;
                if (file_exists($potentialPath)) {
                    $filePath = $potentialPath;
                }
            }
        }

        // 🔸 Access denied fallback image
        if ($filePath === null) {
            $nameNoExt = pathinfo($decodedFile, PATHINFO_FILENAME);
            $isFileAThumb = str_ends_with(strtolower($nameNoExt), '_thumb');
            $denyImageBaseDir = __DIR__ . '/../view/inc/images/';
            $denyImagePath = $denyImageBaseDir . ($isFileAThumb ? 'deny_thumb.png' : 'deny.png');

            header("HTTP/1.0 403 Forbidden");

            if (file_exists($denyImagePath)) {
                $mimeType = mime_content_type($denyImagePath);
                header("Content-Type: $mimeType");
                header("Content-Length: " . filesize($denyImagePath));
                header('Cache-Control: public, max-age=3600');
                readfile($denyImagePath);
            } else {
                echo "Access Denied. Missing deny image.";
            }
            exit;
        }

        // Redirect if the path points to a directory
        if (is_dir($filePath)) {
            $this->redirect(BASE_URI . 'gallery/index/' . implode('/', $pathParts) . '/' . $encodedFileName);
            exit;
        }

        // File not found
        if (!file_exists($filePath)) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        // Determine MIME type based on file extension
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => mime_content_type($filePath),
        };

        // 💡 File metadata for caching validation
        $lastModifiedTime = filemtime($filePath);
        $etag = md5_file($filePath);
        $lastModifiedHttp = gmdate('D, d M Y H:i:s', $lastModifiedTime) . ' GMT';

        // Retrieve client cache headers via Request class
        $ifNoneMatch = $request->getServerVar('HTTP_IF_NONE_MATCH');
        $ifModifiedSince = $request->getServerVar('HTTP_IF_MODIFIED_SINCE');

        // Send standard file headers
        header("Content-Type: $mimeType");
        header("Content-Length: " . filesize($filePath));
        header('Cache-Control: private, max-age=86400, must-revalidate');
        header("Last-Modified: $lastModifiedHttp");
        header("ETag: \"$etag\"");

        // ✅ Client-side cache validation
        $etagMatch = $ifNoneMatch && trim($ifNoneMatch) === "\"$etag\"";
        $timeMatch = $ifModifiedSince && strtotime($ifModifiedSince) === $lastModifiedTime;

        // If the file has not changed, return 304 (no body)
        if ($etagMatch || $timeMatch) {
            header("HTTP/1.1 304 Not Modified");
            exit;
        }

        // Otherwise, send the file content
        readfile($filePath);
        exit;
    }

    #[Override]
    public function __call($name, $arg)
    {
        $allPathParts = array_merge([$name], $arg);
        return $this->media(...$allPathParts);
    }
}
