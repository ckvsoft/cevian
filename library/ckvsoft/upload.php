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

class Upload
{

    /** @var string $_name The name of the field to post (Often the fallback key in $_FILES, e.g., 'image'). */
    private $_name;

    /** @var string $_directory The directory path where the file will be saved. */
    private $_directory;

    /** @var string $_saveAs The desired name of the file to save as (including extension). */
    private $_saveAs;

    /** @var boolean $_overwrite Flag to determine if an existing file can be overwritten. */
    private $_overwrite = true;

    /**
     * __construct - Prepares a file for upload by validating the directory and permissions.
     *
     * @param string $name The form field name value to post (or a placeholder for old modules).
     * @param string $directory The absolute directory path to save the file to.
     * @param string $saveAs (Default: "") The custom name to save the file as.
     * @param boolean $overwrite Flag to allow overwriting of existing files.
     *
     * @throws \ckvsoft\CkvException If the directory is invalid, not writable, or file exists and overwrite is disabled.
     */
    public function __construct($name, $directory, $saveAs = "", $overwrite = true)
    {
        $this->_name = $name;
        $this->_directory = rtrim($directory, '/') . '/';
        $this->_saveAs = (empty($saveAs)) ? $name : $saveAs;
        $this->_overwrite = $overwrite;

        if (!is_dir($this->_directory))
            throw new \ckvsoft\CkvException("Target must be a directory: {$this->_directory}");

        if (!is_writable($this->_directory)) {
            throw new \ckvsoft\CkvException("Directory is not writable: {$this->_directory}");
        }
        if ($overwrite == false && file_exists($this->_directory . $this->_saveAs))
            throw new \ckvsoft\CkvException("File already exists and cannot be overwritten: {$this->_directory}{$this->_saveAs}");
    }

    /**
     * submit() - Moves the uploaded file from the temporary location to the final target.
     * * NOTE: This method uses the hardcoded fallback 'image' as the $_FILES key
     * to maintain compatibility with existing module calls.
     *
     * @return bool True on successful move, false otherwise.
     */
    public function submit(): bool
    {
        // The assumed/hardcoded upload field name for compatibility
        $uploadFieldName = 'image';

        try {
            if (isset($_FILES[$uploadFieldName]) && $_FILES[$uploadFieldName]['error'] === UPLOAD_ERR_OK) {

                $uploadedFile = $_FILES[$uploadFieldName];
                $originalFilename = $uploadedFile['name'];
                $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);

                $targetBaseName = $this->_saveAs;
                $target_file = $this->_directory . $targetBaseName;

                if (strtolower(pathinfo($targetBaseName, PATHINFO_EXTENSION)) !== strtolower($extension)) {
                    $target_file .= "." . $extension;
                }

                $target_file = $this->getFilename($target_file);

                if (move_uploaded_file($uploadedFile["tmp_name"], $target_file)) {
                    return true;
                } else {
                    throw new CkvException("Failed to move the uploaded file. PHP Error Code: " . $uploadedFile["error"]);
                }
            } else {
                $errorCode = $_FILES[$uploadFieldName]['error'] ?? UPLOAD_ERR_NO_FILE;
                throw new \ckvsoft\CkvException("No file uploaded or upload error occurred. Error code: " . $errorCode);
            }
        } catch (\ckvsoft\CkvException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \ckvsoft\CkvException("An unexpected error occurred during file submission.");
        }
        return false;
    }

    /**
     * Generates a unique filename by appending sequential letters (a, b, c...)
     * if the file already exists and overwrite is disabled.
     *
     * @param string $filename The initial desired full file path and name (e.g., '/path/1.jpg').
     * @return string The unique file path and name (e.g., '/path/1a.jpg').
     */
    private function getFilename($filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION); // Get the file extension
        $basename = pathinfo($filename, PATHINFO_FILENAME); // Get the file name without extension
        $dirPath = pathinfo($filename, PATHINFO_DIRNAME); // Get the directory path
        // If overwrite is true, return the original filename immediately
        if ($this->_overwrite === true)
            return $filename;

        try {
            $letters = range('a', 'z'); // Create an array with all letters from a to z
            $i = 0;

            // Check if the file already exists and start appending 'a'
            while (file_exists($filename)) {

                $suffix = '';
                $num = $i + 1;
                // Convert number to base-26 letter sequence (1=a, 26=z, 27=aa, 28=ab...)
                while ($num > 0) {
                    $remainder = ($num - 1) % 26;
                    $suffix = $letters[$remainder] . $suffix;
                    $num = intdiv($num - 1, 26);
                }

                $newBasename = $basename . $suffix;
                $filename = $dirPath . DIRECTORY_SEPARATOR . $newBasename . '.' . $extension;
                $i++;
            }
        } catch (\ckvsoft\CkvException $e) {
            throw new \ckvsoft\CkvException("Rename process failed: " . $e->getMessage());
        }

        return $filename;
    }
}
