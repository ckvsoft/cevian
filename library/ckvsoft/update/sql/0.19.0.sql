-- Etappe 1 of the pmwh3 ACL -> framework RBAC migration plan.
-- See MIGRATION_PLAN_RBAC.md (pmwh3 repo) for the full context.
--
-- Adds a `module` column to both `permissions` and `roles` so
-- different modules (pmwh3, qrk, ...) can claim ownership of the
-- permissions and roles they introduce, and the RBAC UI can
-- filter by owning module.
--
-- Conventions for the value (same on both tables):
--   '__core__'  -- framework-core, or from a module that
--                  hasn't been migrated to set the column yet
--                  (transitional marker)
--   'pmwh3'     -- owned by the pmwh3 module
--   'qrk'       -- owned by qrk
--   ...         -- one slug per consuming module
--
-- Default '__core__' on both columns means existing rows
-- (the 28 seed permissions and the 6 framework roles like
-- Administrator/Owner/Guest tree) keep working as core
-- without any data migration. Modules that get migrated
-- write their slug explicitly via createPermission() / addRole().
--
-- Why roles needs this too, not just permissions: without it,
-- the rbac core UI would show pmwh3's customer-group roles
-- ("Ultimate Admin", "Reseller", "Customer", ...) mixed with
-- framework roles, and a framework admin could accidentally
-- assign them. The module column lets the UI scope itself.
--
-- The updater tracks applied migrations in a bookkeeping table
-- and skips files that already ran, so this needs no explicit
-- existence-check shell -- it gets executed exactly once per DB.

ALTER TABLE `permissions`
    ADD COLUMN `module` VARCHAR(64) NOT NULL DEFAULT '__core__' AFTER `permKey`,
    ADD INDEX `permissions_module` (`module`);

ALTER TABLE `roles`
    ADD COLUMN `module` VARCHAR(64) NOT NULL DEFAULT '__core__' AFTER `roleName`,
    ADD INDEX `roles_module` (`module`);
