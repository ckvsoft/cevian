ALTER TABLE permissions
    ADD COLUMN module VARCHAR(64) NOT NULL DEFAULT '__core__' AFTER permKey,
    ADD INDEX permissions_module (module);
