<?php

/*
 * Site-Stub für den Shared-Split-Betrieb (cevian als Einmal-Installation).
 *
 * Dieser kleine Stub liegt im Docroot JEDER Site. Er definiert den
 * Site-Docroot (CEVIAN_SITE_ROOT) und lädt den gemeinsamen Bootstrap aus dem
 * geteilten Framework-Baum. Bewusst ein absoluter Klartext-Pfad (kein
 * Symlink) — seafile-Sync-/Symlink-Instabilität (WP-Lektion 2026-09).
 *
 *   Geteilt (Framework-Baum):
 *     library/, core_modules/, locale/, index.php, config/app_defaults.json
 *   Pro Site (dieser Docroot):
 *     config/config.json + config/app.json, modules/, var/, public/,
 *     .htaccess, .git (Deploy)
 *
 * Root-Auflösung (falls die Server-Struktur nicht dem Standard entspricht):
 *   1. env CEVIAN_ROOT (z. B. via FPM/VHost-SetEnv, Unit-File, CLI) hat
 *      Vorrang — ein Server, ein Pfad, identischer Stub auf allen Sites.
 *   2. sonst: der Klartext-Pfad unten — pro Server-Struktur anpassen.
 *
 * Neue Site = Docroot anlegen (config/, modules/, var/, public/) + diesen
 * Stub ablegen (Pfad ggf. anpassen). Funktional byte-gleich zum alten
 * Einzelbaum-Betrieb.
 */

$cevianRoot = getenv('CEVIAN_ROOT');

if ($cevianRoot === false || $cevianRoot === '') {
    // Standard-Struktur (z. B. ns1: Shared-Baum unter /vhome/vhtml/_cevian).
    // Auf Servern mit anderer Struktur hier anpassen ODER CEVIAN_ROOT setzen.
    $cevianRoot = '/vhome/vhtml/_cevian/';
}

$cevianRoot = rtrim($cevianRoot, '/') . '/';

if (!is_file($cevianRoot . 'index.php')) {
    http_response_code(500);
    exit('cevian: geteilter Framework-Baum nicht gefunden: ' . $cevianRoot
        . ' (env CEVIAN_ROOT setzen oder Pfad im Site-Stub anpassen)');
}

define('CEVIAN_SITE_ROOT', __DIR__ . '/');
require $cevianRoot . 'index.php';