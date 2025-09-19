<?php

namespace ckvsoft;

class Upload
{

    /** @var string $_name The name of the field to post */
    private $_name;

    /** @var string $_name The directory to save to */
    private $_directory;

    /** @var string $_saveAs The name of the file to save as */
    private $_saveAs;

    /** @var boolean $_overwrite To overwrite a file if it exists */
    private $_overwrite = true;

    /**
     * __construct - Prepares a file for upload
     *
     * @param string $name The form name value to post
     * @param string $directory The directory to save to
     * @param string $required Is this file required
     * @param string $saveAs (Default: false) Set a name to save it as a custom name (Extension will be automatically added)
     *
     * @return boolean
     *
     * @throws \ckvsoft\CkvException
     */
    public function __construct($name, $directory, $saveAs = "", $overwrite = true)
    {
        /**
         * Set the class-wide properties
         */
        $this->_name = $name;
        $this->_directory = trim($directory, '/') . '/';
        $this->_saveAs = (empty($saveAs)) ? $name : $saveAs;
        $this->_overwrite = $overwrite;

        /**
         * Make sure the pathSave is a directory
         */
        if (!is_dir($this->_directory))
            throw new \ckvsoft\CkvException("must be a directory: {$this->_directory}");

        /**
         * Get the octal permission of the directory, eg: 0777
         * Note: This turns the permission into a (string)
         */
        $writable = substr(sprintf('%o', fileperms($this->_directory)), -4);

        if ($writable != "0755")
            throw new \ckvsoft\CkvException("directory is not writable: {$this->_directory}");

        if ($overwrite == false && file_exists($this->_directory . $this->_saveAs))
            throw new \ckvsoft\CkvException("file already exists and cannot be overwritten: {$this->_directory}{$this->_saveAs}");
    }

    /**
     * submit() - This is to be called from the input\Submit() method, so it only tries to save when the form is complete
     *
     * @return boolean
     */
    public function submit(): bool
    {
        try {
            if (isset($_FILES['image'])) {
                $filename = $_FILES['image']['name'];
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $target_file = $this->getFilename($this->_directory . $this->_saveAs . "." . $extension); // Neuer Name für das Bild

                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    return true;
                    // echo "Die Bilddatei wurde erfolgreich hochgeladen.";
                } else {
                    throw new CkvException("Fehler beim Hochladen der Bilddatei.");
                }
            } else {
                throw new \ckvsoft\CkvException("Es wurde keine Bilddatei hochgeladen.");
            }
        } catch (Exception $e) {
            echo "Fehler: " . $e->getMessage();
        }
        return false;
    }

    private function getFilename($filename): string
    {
        if ($this->_overwrite === true)
            return $filename;

        try {
            $extension = pathinfo($filename, PATHINFO_EXTENSION); // get the file extension
            $basename = pathinfo($filename, PATHINFO_FILENAME); // get the file name without extension
            $dirPath = pathinfo($filename, PATHINFO_DIRNAME); // get the directory path

            $letters = range('a', 'z'); // create an array with all letters from a to z

            $i = 0;
            while (file_exists($filename)) { // check if the file already exists
                $newBasename = $basename . $letters[$i]; // add letters to the basename
                $filename = $dirPath . DIRECTORY_SEPARATOR . $newBasename . '.' . $extension; // construct the new filename
                $i++; // increment i
                if ($i == 26) { // if i exceeds 25 (the index of 'z' in the letters array)
                    $i = 0; // reset i to 0
                }
            }
        } catch (\ckvsoft\CkvException $e) {
            echo $e->getMessage();
            throw new \ckvsoft\CkvException("Umbennenenn: " . $e->getMessage());
        }
//        throw new \ckvsoft\CkvException("Filename: " . $filename);

        return $filename; // output the new filename
    }
}
