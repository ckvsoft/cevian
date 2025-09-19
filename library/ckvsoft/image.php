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

    /** @var origName $_path */
    private $_origName = null;

    /** @var ext $_path */
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
        $this->_ext = $info['extension'];
        $this->_origName = $info['filename'];
        $this->_newName = $newName;
    }

    /**
     * resize
     *
     * @param array $dimensions Two values for height and width, [125, 125]
     */
    public function resize($dimensions = array(125, 125))
    {
        if (!is_array($dimensions) && count($dimensions) != 2) {
            throw new \ckvsoft\CkvException('Dimensions must be an array of two');
        }

        if ($this->_originalFile == null || $this->_path == null || $this->_newName == null) {
            throw new \ckvsoft\CkvException('originalFile, path, newName must be set');
        }

        /** Prepare the width and height */
        $width = $dimensions[0];
        $height = $dimensions[1];

        /** Adjust the image ratio */
        list($origWidth, $origHeight) = getimagesize($this->_originalFile);
        $ratio = $origWidth / $origHeight;

        if ($width / $height > $ratio) {
            $width = (int) round($height * $ratio);
        } else {
            $height = (int) round($width / $ratio);
        }

        /** Create the image */
        $image = imagecreatetruecolor($width, $height);

        /** Determine the extension */
        switch ($this->_ext) {
            case "jpg":
            case "jpeg":
                $src = imagecreatefromjpeg($this->_originalFile);
                imagecopyresampled($image, $src, 0, 0, /* left */ 0, /* top */ 0,
                        $width, $height, $origWidth, $origHeight);
                $result = imagejpeg($image, $this->_path . $this->_newName, /* compression */ 90);
                break;
            case "png":
                imagealphablending($image, false);
                imagesavealpha($image, true);

                $src = imagecreatefrompng($this->_originalFile);
                imagecopyresampled($image, $src, 0, 0, /* left */ 0, /* top */ 0,
                        $width, $height, $origWidth, $origHeight);
                imagealphablending($src, true);
                $result = imagepng($image, $this->_path . $this->_newName, /* compression */ 9);
                break;
            default:
                return false;
        }

        /** Remove Temporary Image */
        imagedestroy($image);
        return $result;
    }

    /**
     * 
     * @return type
     * @throws \ckvsoft\CkvException
     */
    public function toBase64()
    {
        if (!file_exists($this->_originalFile)) {
            throw new \ckvsoft\CkvException("Image not found: " . $this->_originalFile);
        }

        // MIME-Type bestimmen
        $mime = mime_content_type($this->_originalFile);
        $data = file_get_contents($this->_originalFile);

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
