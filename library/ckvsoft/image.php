<?php

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
     * resize
     *
     * @param array $dimensions Two values for height and width, [125, 125]
     * @return bool
     * @throws \ckvsoft\CkvException
     */
    public function resize($dimensions = array(125, 125))
    {
        if (!is_array($dimensions) || count($dimensions) != 2) {
            throw new \ckvsoft\CkvException('Dimensions must be an array of two');
        }

        if ($this->_originalFile == null || $this->_path == null || $this->_newName == null) {
            throw new \ckvsoft\CkvException('originalFile, path, newName must be set');
        }

        list($width, $height) = $dimensions;
        list($origWidth, $origHeight) = getimagesize($this->_originalFile);
        $ratio = $origWidth / $origHeight;

        if ($width / $height > $ratio) {
            $width = (int) round($height * $ratio);
        } else {
            $height = (int) round($width / $ratio);
        }

        // Initialize resources outside the switch, but without creating them yet.
        $image = null;
        $src = null;
        $result = false;

        switch ($this->_ext) {
            case "jpg":
            case "jpeg":
                // Use imagecreatetruecolor for JPG/JPEG
                $image = imagecreatetruecolor($width, $height);
                $src = imagecreatefromjpeg($this->_originalFile);
                imagecopyresampled($image, $src, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
                $result = imagejpeg($image, $this->_path . $this->_newName, 90);
                // Note: $src destruction moved to the end
                break;

            case "png":
                // Use imagecreatetruecolor for PNG
                $image = imagecreatetruecolor($width, $height);
                // Preserve transparency for PNG
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $src = imagecreatefrompng($this->_originalFile);
                imagecopyresampled($image, $src, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
                imagealphablending($src, true);
                $result = imagepng($image, $this->_path . $this->_newName, 9);
                // Note: $src destruction moved to the end
                break;

            case "gif":
                $src = imagecreatefromgif($this->_originalFile);
                // Use imagecreate (palette-based) for GIF
                $image = imagecreate($width, $height);

                // Preserve transparency
                $transparentIndex = imagecolortransparent($src);
                if ($transparentIndex >= 0) {
                    // Get the transparent color from the source image
                    $transparentColor = imagecolorsforindex($src, $transparentIndex);
                    // Allocate the transparency color in the new image
                    $newTransparentIndex = imagecolorallocate($image, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
                    imagecolortransparent($image, $newTransparentIndex);
                    // Fill the new image with the transparent color
                    imagefill($image, 0, 0, $newTransparentIndex);
                }

                // Resample the image
                imagecopyresampled($image, $src, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
                $result = imagegif($image, $this->_path . $this->_newName);
                // Note: $src destruction moved to the end
                break;

            default:
                // Unsupported format
                return false;
        }

        // FINAL RESOURCE CLEANUP: Destroy both source and target image resources once.
        if ($image !== null) {
            imagedestroy($image);
        }
        if ($src !== null) {
            imagedestroy($src);
        }

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
