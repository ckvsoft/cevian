<?php

use ckvsoft\MultiLogin\ProviderRegistry;

/**
 * Reads and writes module_user_mapping. Pulls Framework-user list
 * straight from the `user` table; module-user lists come from each
 * module's UserProviderInterface implementation.
 */
class Multilogin_Model extends \ckvsoft\mvc\Model
{

    public function __construct()
    {
        parent::__construct();
    }

    /** All Framework users (from the `user` table). */
    public function listFrameworkUsers(): array
    {
        return self::$sharedDb->select(
                "SELECT user_id, username, email, role
                   FROM user
               ORDER BY username", []
        );
    }

    /** One Framework user by id. */
    public function getFrameworkUser(int $userId): ?array
    {
        return self::$sharedDb->selectOne(
                "SELECT user_id, username, email, role
                   FROM user
                  WHERE user_id = :uid", ['uid' => $userId]
        );
    }

    /**
     * Module keys that have a registered UserProvider, mapped to
     * their human-readable label.
     *
     * @return array<string,string>  module_key => display label
     */
    public function listModules(): array
    {
        $providers = ProviderRegistry::discover();
        $out = [];
        foreach ($providers as $key => $class) {
            try {
                $out[$key] = (string) $class::getModuleLabel();
            } catch (\Throwable $e) {
                error_log("multilogin: getModuleLabel failed for {$class}: " . $e->getMessage());
                $out[$key] = $key;
            }
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    /**
     * All current mappings.
     *
     * @return list<array{framework_user_id:int, module_name:string, module_user_id:int}>
     */
    public function listMappings(): array
    {
        return self::$sharedDb->select(
                "SELECT framework_user_id, module_name, module_user_id
                   FROM module_user_mapping", []
        );
    }

    /** Mapping for one (fwUser, module) pair, or null. */
    public function getMapping(int $frameworkUserId, string $moduleName): ?array
    {
        return self::$sharedDb->selectOne(
                "SELECT framework_user_id, module_name, module_user_id
                   FROM module_user_mapping
                  WHERE framework_user_id = :fw AND module_name = :m",
                ['fw' => $frameworkUserId, 'm' => $moduleName]
        );
    }

    /**
     * Insert or update a mapping. UNIQUE(framework_user_id, module_name)
     * means one mapping per (fwUser, module).
     */
    public function setMapping(int $frameworkUserId, string $moduleName, int $moduleUserId): bool
    {
        return (bool) self::$sharedDb->insertUpdate('module_user_mapping', [
            'framework_user_id' => $frameworkUserId,
            'module_name'       => $moduleName,
            'module_user_id'    => $moduleUserId,
        ]);
    }

    public function deleteMapping(int $frameworkUserId, string $moduleName): bool
    {
        return (bool) self::$sharedDb->delete(
                'module_user_mapping',
                'framework_user_id = :fw AND module_name = :m',
                ['fw' => $frameworkUserId, 'm' => $moduleName]
        );
    }

    /**
     * Resolves a module_user_id to its display label via the
     * registered provider. Returns null if no provider for the
     * module or if the provider doesn't know the id.
     */
    public function resolveModuleUser(string $moduleName, int $moduleUserId): ?array
    {
        $class = ProviderRegistry::get($moduleName);
        if ($class === null) {
            return null;
        }
        try {
            return $class::getUser($moduleUserId);
        } catch (\Throwable $e) {
            error_log("multilogin: getUser failed for {$class}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * The user picker for a module: full list from the provider.
     * Returns [] if no provider.
     *
     * @return list<array{id:int, label:string, secondary?:string}>
     */
    public function listModuleUsers(string $moduleName): array
    {
        $class = ProviderRegistry::get($moduleName);
        if ($class === null) {
            return [];
        }
        try {
            return (array) $class::listUsers();
        } catch (\Throwable $e) {
            error_log("multilogin: listUsers failed for {$class}: " . $e->getMessage());
            return [];
        }
    }
}
