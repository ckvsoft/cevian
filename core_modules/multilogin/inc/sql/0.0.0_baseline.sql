-- multilogin module baseline.
--
-- The two tables used here -- module_user_mapping and multi_login_sessions --
-- are created by the framework installer (core_modules/installer/inc/sql/schema.sql).
-- This module reuses those tables; it owns the UI, not the schema.
--
-- File present so the Updater registers the module at version 0.0.0
-- and future migrations get a place to land.

SELECT 1;
