<?php

namespace ckvsoft\MultiLogin;

use ckvsoft\mvc\Config;

/**
 * Discovers modules that ship a UserProviderInterface implementation.
 *
 * Scans modules/<m>/utils/multilogin/userprovider.php and
 * core_modules/<m>/utils/multilogin/userprovider.php. Each found
 * provider is registered under its getModuleKey().
 *
 * Cached for the duration of one request to avoid repeated filesystem
 * walks. Cache is cleared with flushCache() (mainly useful in tests).
 */
class ProviderRegistry
{

    /** @var array<string, class-string<UserProviderInterface>>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, class-string<UserProviderInterface>>
     *         Map of module-key -> provider class name.
     */
    public static function discover(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $found = [];

        $rootCandidates = [
            __DIR__ . '/../../../' . trim(\MODULES_URI, '/'),
            __DIR__ . '/../../../' . trim(\CORE_MODULES_URI, '/'),
        ];

        foreach ($rootCandidates as $root) {
            if (!is_dir($root)) {
                continue;
            }
            $entries = scandir($root) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $providerFile = $root . '/' . $entry . '/utils/multilogin/userprovider.php';
                if (!is_file($providerFile)) {
                    continue;
                }

                require_once $providerFile;

                // Find the class declared in this file that implements
                // the interface. Filename matching via reflection so we
                // pick the right class even if many were declared.
                $real = realpath($providerFile);
                foreach (get_declared_classes() as $class) {
                    try {
                        $rc = new \ReflectionClass($class);
                    } catch (\Throwable $e) {
                        continue;
                    }
                    if ($rc->getFileName() !== $real) {
                        continue;
                    }
                    if ($rc->isAbstract() || $rc->isInterface()) {
                        continue;
                    }
                    if (!$rc->implementsInterface(UserProviderInterface::class)) {
                        continue;
                    }
                    try {
                        $key = (string) $class::getModuleKey();
                    } catch (\Throwable $e) {
                        error_log("MultiLogin\\ProviderRegistry: getModuleKey() failed for {$class}: " . $e->getMessage());
                        continue;
                    }
                    if ($key !== '') {
                        $found[$key] = $class;
                    }
                    break;  // at most one provider per file
                }
            }
        }

        return self::$cache = $found;
    }

    /**
     * Resolve a single provider by module key. Returns null if the
     * module either doesn't exist or doesn't ship a provider.
     *
     * @return class-string<UserProviderInterface>|null
     */
    public static function get(string $moduleKey): ?string
    {
        $all = self::discover();
        return $all[$moduleKey] ?? null;
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
