<?php

namespace ckvsoft;

class Version
{

    /**
     * Base version of the application (without Git info)
     * @var string
     */
    private static $baseVersion = "0.6.3-250922";

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
