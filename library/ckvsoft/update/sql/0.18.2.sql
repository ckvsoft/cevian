-- Add the MultiLogin admin tool to the framework's main menu under
-- the "Manager" group (parent=5).
--
-- The (label, parent, link) tuple is UNIQUE in mainmenu, so this is
-- idempotent -- re-running it won't insert duplicates.

INSERT IGNORE INTO `mainmenu` (`label`, `link`, `parent`, `sort`, `role`, `is_public`)
VALUES ('MultiLogin', 'multilogin', 5, 5, 'admin', -1);
