<?php

/*
 * Site-Stub für den Shared-Split-Betrieb (cevian als Einmal-Installation).
 *
 * Dieser kleine Stub liegt im Docroot JEDER Site. Er definiert den
 * Site-Docroot (CEVIAN_SITE_ROOT) und lädt den gemeinsamen Bootstrap aus dem
 * geteilten Framework-Baum (_cevian). Bewusst ein absoluter Klartext-Pfad
 * (kein Symlink) — seafile-Sync-/Symlink-Instabilität (WP-Lektion 2026-09).
 *
 *   Geteilt (z. B. /vhome/vhtml/_cevian):
 *     library/, core_modules/, locale/, index.php, config/app_defaults.json
 *   Pro Site (dieser Docroot):
 *     config/config.json + config/app.json, modules/, var/, public/,
 *     .htaccess, .git (Deploy)
 *
 * Neue Site = Docroot anlegen (config/, modules/, var/, public/) + diesen
 * Stub ablegen. Funktional byte-gleich zum alten Einzelbaum-Betrieb.
 */
define('CEVIAN_SITE_ROOT', __DIR__ . '/');

require '/vhome/vhtml/_cevian/index.php';