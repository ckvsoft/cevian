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

namespace ckvsoft;

class Image
{

    /** @var string $_originalFile */
    private $_originalFile = null;

    /** @var string $_newName */
    private $_newName = null;

    /** @var string $_path */
    private $_path = null;

    /** @var string $_origName */
    private $_origName = null;

    /** @var string $_ext */
    private $_ext = null;

    /**
     * __construct
     *
     * @param string $originalFile
     * @param string $newName Do not include an extension
     * @param string $path
     */
    public function __construct($originalFile, $newName = '', $path = '')
    {
        $this->_originalFile = $originalFile;
        $this->_path = $path;

        $info = pathinfo($this->_originalFile);
        $this->_ext = strtolower($info['extension']); // Force lowercase
        $this->_origName = $info['filename'];
        $this->_newName = $newName;
    }

    /**
     * _rotateResource
     * * Helper function to rotate an image resource without saving the file.
     * Updates width/height if 90/270 degree rotation occurs.
     * * @param resource $src The GD image resource to rotate.
     * @param int $degrees Rotation angle in degrees (e.g., -90, 180, 90).
     * @param int $origWidth Current width (will be updated if needed).
     * @param int $origHeight Current height (will be updated if needed).
     * @return resource The new, rotated GD image resource.
     */
    private function _rotateResource($src, int $degrees, int &$origWidth, int &$origHeight)
    {
        $rotatedSrc = imagerotate($src, $degrees, 0);

        // If dimensions switch (90 or 270 degrees), update them
        if (abs($degrees) === 90 || abs($degrees) === 270) {
            list($origWidth, $origHeight) = [$origHeight, $origWidth];
        }

        // Destroy the original resource and return the new rotated one
        imagedestroy($src);
        return $rotatedSrc;
    }

    /**
     * rotate
     *
     * Rotates the original file physically by the specified degrees (90, -90, 180).
     * Now uses _rotateResource for the actual rotation.
     *
     * @param int $degrees Rotation angle: 90 (clockwise), -90 (counter-clockwise), 180.
     * @return bool True on success, false on failure.
     * @throws \ckvsoft\CkvException
     */
    public function rotate(int $degrees): bool
    {
        if (!in_array(abs($degrees), [90, 180])) {
            throw new \ckvsoft\CkvException('Rotation degrees must be 90, 180, or -90.');
        }

        if (!file_exists($this->_originalFile)) {
            throw new \ckvsoft\CkvException("Original file not found: " . $this->_originalFile);
        }

        $src = null;
        $result = false;
        $quality = 90; // Default quality for JPEG
        // Dummy variables needed for _rotateResource signature
        $dummyWidth = 0;
        $dummyHeight = 0;

        try {
            switch ($this->_ext) {
                case 'jpg':
                case 'jpeg':
                    $src = imagecreatefromjpeg($this->_originalFile);
                    $saveFunc = 'imagejpeg';
                    break;
                case 'png':
                    $src = imagecreatefrompng($this->_originalFile);
                    $saveFunc = 'imagepng';
                    $quality = 9;
                    break;
                case 'gif':
                    $src = imagecreatefromgif($this->_originalFile);
                    $saveFunc = 'imagegif';
                    break;
                default:
                    // Unsupported format for rotation
                    throw new \ckvsoft\CkvException("Unsupported format for rotation: " . $this->_ext);
            }

            if (!$src) {
                throw new \ckvsoft\CkvException("Failed to create image resource from file: " . $this->_originalFile);
            }

            // Use the new helper to get the rotated image resource
            $rotatedImage = $this->_rotateResource($src, $degrees, $dummyWidth, $dummyHeight);

            // Save the rotated image (overwriting the original file).
            if ($this->_ext === 'gif') {
                $result = $saveFunc($rotatedImage, $this->_originalFile);
            } else {
                $result = $saveFunc($rotatedImage, $this->_originalFile, $quality);
            }

            imagedestroy($rotatedImage);

            return $result;
        } catch (\Throwable $e) {
            if (isset($src) && $src !== null)
                imagedestroy($src);
            throw new \ckvsoft\CkvException("Rotation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * resize
     *
     * @param array $dimensions Two values for height and width, [125, 125]
     * @return bool
     * @throws \ckvsoft\CkvException
     */
    public function resize($dimensions = [125, 125])
    {
        if (!is_array($dimensions) || count($dimensions) != 2) {
            throw new \ckvsoft\CkvException('Dimensions must be an array of two');
        }

        if ($this->_originalFile == null || $this->_path == null || $this->_newName == null) {
            throw new \ckvsoft\CkvException('originalFile, path, newName must be set');
        }

        list($targetWidth, $targetHeight) = $dimensions;

        $src = null;
        $image = null;
        $result = false;

        switch ($this->_ext) {
            case 'jpg':
            case 'jpeg':
                $src = imagecreatefromjpeg($this->_originalFile);

                // Get original size
                $origWidth = imagesx($src);
                $origHeight = imagesy($src);

                // Apply EXIF rotation if needed, using the helper
                if (function_exists('exif_read_data')) {
                    $exif = @exif_read_data($this->_originalFile);
                    if (!empty($exif['Orientation'])) {
                        $degrees = 0;
                        switch ($exif['Orientation']) {
                            case 3: $degrees = 180;
                                break;
                            case 6: $degrees = -90;
                                break;
                            case 8: $degrees = 90;
                                break;
                        }

                        if ($degrees !== 0) {
                            // Use helper to rotate the resource in memory for thumbnail creation
                            $src = $this->_rotateResource($src, $degrees, $origWidth, $origHeight);
                        }
                    }
                }
                break;

            case 'png':
                $src = imagecreatefrompng($this->_originalFile);
                $origWidth = imagesx($src);
                $origHeight = imagesy($src);
                break;

            case 'gif':
                $src = imagecreatefromgif($this->_originalFile);
                $origWidth = imagesx($src);
                $origHeight = imagesy($src);
                break;

            default:
                return false; // unsupported format
        }

        if (!$src) {
            throw new \ckvsoft\CkvException('Failed to create image resource from original file.');
        }

        // Calculate target size keeping aspect ratio
        $ratio = $origWidth / $origHeight;
        if ($targetWidth / $targetHeight > $ratio) {
            $targetWidth = (int) round($targetHeight * $ratio);
        } else {
            $targetHeight = (int) round($targetWidth / $ratio);
        }

        // Create the target image
        if ($this->_ext === 'png') {
            $image = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($image, false);
            imagesavealpha($image, true);
        } elseif ($this->_ext === 'gif') {
            $image = imagecreate($targetWidth, $targetHeight);
            $transparentIndex = imagecolortransparent($src);
            if ($transparentIndex >= 0) {
                $transparentColor = imagecolorsforindex($src, $transparentIndex);
                $newTransparentIndex = imagecolorallocate($image, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
                imagecolortransparent($image, $newTransparentIndex);
                imagefill($image, 0, 0, $newTransparentIndex);
            }
        } else {
            $image = imagecreatetruecolor($targetWidth, $targetHeight);
        }

        // Resample
        imagecopyresampled($image, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);

        // Save
        switch ($this->_ext) {
            case 'jpg':
            case 'jpeg':
                $result = imagejpeg($image, $this->_path . $this->_newName, 90);
                break;
            case 'png':
                $result = imagepng($image, $this->_path . $this->_newName, 9);
                break;
            case 'gif':
                $result = imagegif($image, $this->_path . $this->_newName);
                break;
        }

        // Cleanup
        if ($image !== null)
            imagedestroy($image);
        if ($src !== null) // This is a safety net; should have been destroyed in _rotateResource for rotated images
            imagedestroy($src);

        return $result;
    }

    /**
     * toBase64
     *
     * @return string
     * @throws \ckvsoft\CkvException
     */
    public function toBase64()
    {
        if (!file_exists($this->_originalFile)) {
            throw new \ckvsoft\CkvException("Image not found: " . $this->_originalFile);
        }

        $mime = mime_content_type($this->_originalFile);
        $data = file_get_contents($this->_originalFile);

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
