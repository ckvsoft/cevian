<?php

namespace ckvsoft\MultiLogin;

/**
 * A module that wants its users to be selectable in the framework's
 * MultiLogin mapping UI implements this interface and drops the file
 * at:
 *
 *   modules/<modname>/utils/multilogin/userprovider.php
 *
 * (or the equivalent path under core_modules/). One class per module.
 *
 * Discovery is done by ProviderRegistry, which scans both modules/
 * and core_modules/ for the conventional path. The registry calls
 * the static methods directly -- there's no instantiation, so the
 * provider class shouldn't carry per-request state.
 *
 * Identifiers
 * -----------
 * Module user IDs are integers. The name is for display only --
 * uniqueness is on the ID. If a module's natural identifier is a
 * string (e.g. a username), the provider has to fold it into a
 * numeric ID first.
 */
interface UserProviderInterface
{

    /**
     * Module key. Must equal the directory name under modules/ or
     * core_modules/. Used as `module_user_mapping.module_name`.
     */
    public static function getModuleKey(): string;

    /**
     * Human-readable module label for the UI (column header).
     */
    public static function getModuleLabel(): string;

    /**
     * @return list<array{id:int, label:string, secondary?:string}>
     *
     * - id        -- the numeric module user id (cid for pmwh3, etc.)
     * - label     -- short name (username, login)
     * - secondary -- optional extra info (real name, email) shown
     *                next to the label in the picker dialog
     *
     * Order: provider's choice. The UI will sort alphabetically by
     * label if it wants a different order.
     */
    public static function listUsers(): array;

    /**
     * Look up a single user by id. Returns null if no such user.
     * Used to render the current selection in the mapping list
     * (you have a row with module_user_id=23 -- show "ckvsoft").
     *
     * @return array{id:int, label:string, secondary?:string}|null
     */
    public static function getUser(int $id): ?array;

    /**
     * Optional: filter listUsers() by a search term. Used by the
     * picker dialog when the user starts typing. A naive default
     * implementation can just call listUsers() and filter
     * client-side.
     *
     * @return list<array{id:int, label:string, secondary?:string}>
     */
    public static function searchUsers(string $term): array;
}
