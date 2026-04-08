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

class Version
{

    /**
     * Base version of the application (without Git info)
     * @var string
     */
    private static $baseVersion = "0.18.1-260408";

    /**
     * Returns the application name
     *
     * @return string
     */
    public static function name()
    {
        return 'ckvsoft';
    }

    /**
     * Returns the version.
     * If Git is available, the Git commit hash will be appended.
     *
     * @return string
     */
    public static function version()
    {
        $version = self::$baseVersion;

        $gitVersion = self::getGitVersion();
        if ($gitVersion !== null) {
            $version .= " (git: " . $gitVersion . ")";
        }

        return $version;
    }

    /**
     * Reads the current Git commit hash directly from the .git directory
     *
     * @return string|null Short commit hash, or null if not available
     */
    private static function getGitVersion()
    {
        $gitDir = __DIR__ . '/../../.git';

        if (!is_dir($gitDir)) {
            return null;
        }

        $headFile = $gitDir . '/HEAD';
        if (!file_exists($headFile)) {
            return null;
        }

        $head = trim(file_get_contents($headFile));

        // HEAD contains either a commit hash or a ref (e.g. "ref: refs/heads/main")
        if (strpos($head, 'ref:') === 0) {
            $refPath = $gitDir . '/' . trim(substr($head, 5));
            if (file_exists($refPath)) {
                $hash = trim(file_get_contents($refPath));
                return substr($hash, 0, 7); // short hash
            }
        } else {
            // detached HEAD → directly a commit hash
            return substr($head, 0, 7);
        }

        return null;
    }
}
