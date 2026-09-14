# AGENTS.md — Arbeits- & Deployregeln für cevian / pmwh3

Stand: 2026-09-12. Geschrieben aus der Planungssession (DNS/DB/Rspamd-Roadmap).
Referenz für weitere Vorhaben: `DB_ACCESS.md` (Code-Mechanik DB-Zugriff),
`modules/pmwh3/MIGRATION_PLAN_RBAC.md` (RBAC, abgeschlossen).

## Verzeichnis- & Datei-Konventionen

- **Keine in-Source-Arbeitsverzeichnisse.** Temp-/Backup-/Export-Pfade
  niemals innerhalb des Repos oder Modulpfads anlegen
  (pmwh2-Style `pmwh2/backup/` ist tabu).
- **Shadow-Verzeichnisse** außerhalb des Repos, z. B. `~/tmp/...`
  (lokal) bzw. Server-Konvention `/srv/docker/...` nur für bestehende
  Prod-Volumes; neue Test-Direktiven unter `/srv/docker-test/`.
- **Beispiele ja, echts nie:** `config/config.json`, `var/config.php`
  und module.json-Creds werden **nicht** eingecheckt. Nur
  `config/config_example.json` bzw. Platzhalter-Werte im Repo.

## DB-Regeln (immer gültig)

- DB-Zugriff **ausschließlich** über die cevian-Database-Klasse
  (`ckvsoft\Database`) **via** `Config::moduleDb()` /
  `Config::moduleDb($module, $configPath)` / `Config::cachedDatabase()`.
- **Kein `new PDO`**, **kein selbst gebautes `new Database`** im
  Modulcode, keine rohen `->query/->exec/->prepare` außerhalb der
  Framework-Klasse. Solche Stellen sind Fehler und werden entfernt.
- Details/Cache-Fluss/Referenzen: `DB_ACCESS.md`.

## Deployment-/Server-Fakten (für Tests & Deploys)

- Produktiv-Stack läuft am Server (`root@ns1.ckvsoft.at -p 19022`,
  **vorsicht**) **in Docker**:
  mariadb, pdns (öffentlich DNS, Port 53), pdns-recursor, rspamd,
  redis, php84_fpm, postfix, dovecot, apache2, proftpd, clamav,
  portainer.
- **Liveserver nur lesend anfassen** (SSH). Schreib-/Deploy-Aktionen
  nur nach expliziter Freigabe; herkömmliches Deployment: Hotfix-ZIP(s)
  wie bisher.
- **DNS-Topologie am Server:**
  - `pdns`-Container (Port 53 öffentlich) → DB `ck000005_pmwh2`
    (prefix-Tabellen `pdns_domains`/`pdns_records/...` + Legacy
    `mydns_*`, `amavis_*`, `apache_subdomains`)  → LIVE, read-only.
  - `pdns-test`-Container (Port 5301, nicht published) → DB `pdns`
    (stock-Schema ohne Präfix, 11 echte Zonen) → **Test-Ziel der
    pmwh3-Adapter**. pmwh3 schreibt dort auch Produktionruntime
    (`module.json → dns.database = pdns`).
  - DNS-Integrationstests: nur **eigen klar benannte Testzonen**
    (z.B. `dns1-test.*`) anlegen/löschen; die 11 echten Zonen strikt
    read-only. Public-DNS (Port 53) unberührt.
- **Rspamd:** Redis bereits angebunden (`local.d/redis.conf →
  redis:6379`), `/srv/docker/rspamd/config` gemountet → Map-Files
  landen dort (entsprechend `/etc/rspamd` im Container; nichts im
  Source-Tree). Multimap-Files werden mtime-basiert automatisch
  eingelesen (kein Reload-Zwang).
- **php84_fpm hat kein `mysqldump`** → Backup-Tool: PHP-Dump über die
  cevian-Database-Klasse (PMWH2-Prinzip), Ziel Shadow-Dir.
- Auth-Datenbank IDs (`PDNS_API_*`/`PDNS_DB_NAME` env oder
  module.json-Examples) niemals in Logs/Commits kopieren.

## Test-Konventionen

- Testzonen/Standard-DBs: `cevian_test`, `mydns_test`, `pdns_test`
  (oder bestehendes `pdns`-DB via pdns-test wie oben).
- Test-Creds als Shadow-Konfig (`~/tmp/cevian-test/` oder ENV),
  nie im Checkout-Dateibaum.
- Testscripts im Repo (`modules/pmwh3/tests/`) ohne Creds;
  Zweck: ENV-Args/Shadow-Pfad lesen.
- Pro Etappe: `php -l` Grep-Gates + Logiktests lokal; Verify am
  Test-Stack; Deliverable + (optional) Commits-Hinweis pro Etappe.

## Offene Roadmap (Kurzfassung, Stand 2026-09-13)

1. Framework: `moduleDb($module, $configPath)` + `cachedDatabase()`
   + `Database::__construct` dbname-optional & port-support.  **DONE
   (0.18.3-260913, pushed 66f8f8d/36e0ecb)**
2. DNS.1: Adapter-Capability-API, Registry-Auflösung in DnsManager,
   Result-Shape (`content` statt `data`), README-Kapitel
   "Neuer DNS-Adapter".  **DONE (lokal, im pmwh3-3.0.78-260913-Hotfix-ZIP)**
3. DNS.2: MyDNS-Adapter vollständig (Reads + Writes + Serial; kein
   DNSSEC -> Capability-API blendt aus). **DONE — BOTH adapter
   lifecycle tests ALL PASS (22/22 je) gegen dev-mysql80 Test-Stack**
4. DNS.3/DB-Cleanup: Creds aus module.json in externe
   Server-Mirror-Config (nur Examples im Repo); Adapter auf
   moduleDb-Node-API (done for DNS-/Mail-Adapter); mysqldbadapter ->
   cachedDatabase (done); learn_all.php -> moduleDb + `roles`-Fix
   (done). **Rest: Creds-Migration am Server (DNS.3)**
5. Filtering (Rspamd): `pmwh3_filtering` (wide: tag/kill thresholds
   je scope, greylist+subject global) + Tab + HTTP external_map /
   multimap-Maps (specs final, siehe Session-Recherche).
   **DONE lokal (3.0.17.sql DDL, FilteringManager + inheritance,
   controller/filtering.php CRUD + Endpoints map_wblist/settings_ucl/
   settings_query, Views details/edit, RBAC-Keys in bootstrap_rbac,
   Settings FILTER_POLICY_TYPE/RSPAMD_MAP_TOKEN); Tests ALL PASS
   (test_filteringmanager.php gegen pmwh3_test). REST: rspamd-lokale
   Glue-Config am Server (multimap.conf + settings.conf external_map,
   ein Deploy-Schritt mit Freigabe).** `RspamdManager` (utils/) als
   HTTP-API-Client (Controller /ping /stat /mapping /learn*,
   Worker /checkv2) analog der Dovecot-Rspamd-Learning-Integration;
   Verify läuft über die API (rspamc nur im rspamd-Container, nicht im
   php84_fpm). API-Pfade von php84_fpm live geprüft (pong / score).
   **Server-Glue live (ns1)**: multimap.conf W/B-Blöcke + settings.conf
   `pmwh3_thresholds` external_map (method=body, selector
   `id('rcpt');rcpts:addr.lower`) eingetragen und via Container-Restart
   aktiviert; Token in pmwh3-Options + beiden Enpoints `?key=` --
   Verify via checkv2: WHITELIST/BLACKLIST (multimap prefilter)
   **ALL PASS** (From: probe-Adresse → Action accept/no-action bzw.
   reject). Schwellen-Kette (pmwh3_filtering via external_map) wird
   von rspamd bezogen (Log 'apply settings from external map') und
   pmwh3 liefert die kohärente Schwellen-Paarung
   `{"actions":{"add header":t,"reject":k}}` (tag<kill garantiert,
   rspamd wählt die Action mit der höchsten überschrittenen
   Schwelle — deshalb MUSS "add header" unter reject bleiben).
   Detail-Verify der Action-Auflösung am checkv2-API-Prüfweg
   unvollständig (milter-Pfad abweichend) — **finale Verifikation:
   echte Mail过 via milter + Log settings_id, siehe Geplant.**
   REST: ns2 (mailbackup) gleiche 2 Blöcke einkopieren.**
6. WBList: `pmwh3_wblist` + Tab + multimap (W/B prefilter).
   **DONE im selben 3.0.78er-Hotfix wie Item 5 (gleiche DB-Welle,
   gleicher Controller). REST: Server-Glue wie Item 5.**
7. Tools: backup (PHP-Dump, Shadow-Dir), news (Tabelle+Settings
   existieren, Controller/View fehlen), applications (Tabelle fehlt);
   errorlog -> Redirect + ErrorHandler-Runtime (Schema-Keys existieren).
   **DONE lokal (3.0.18.sql applications-DDL, Tools-Model, controller/
   tools.php actions backup/do_backup/download_backup/delete_backup +
   news CRUD (incl. save→int-ids) + applications CRUD (APPS_PER_ROW
   anbindet), tools/errorlog → Redirect auf options/errorlog, Views
   backup/news/edit_news/applications/edit_application, Settings
   BACKUP_DIR (Shadow-Dir, default /srv/docker/pmwh3-backup);
   BackupUtil PHP-Dump über cevian-Database + 3-Phasen-Restore
   (DROPs→CREATEs→INSERTs); Tests ALL PASS (test_tools.php):
   news/application roundtrip + dump→restore-Survivor erzeugt.]**
   REST (Runtime-Verifikation am Server): Backup-Dir writable für
   php84_fpm (create dir testweis), Tools-Menü-Links mit Errorlog
   redirect sichtbar; ErrorHandler-Runtime bleibt als separater
   Punkt offen (errorlog-Viewer + Handlers — in Options).**
8. Domain-Reste: Subdomain-CRUD (`apache_subdomains`), Bulk-Import.
9. Kleinzeug: onsavehooks-TODOs (DNS-Rewrite-Daemon, Vhost-
   Regeneration), Reseller-Hierarchie, SA-userprefs-Kompatibilität
   später (SA-Adapter liest pmwh3-DB direkt).

## Test-Infrastruktur (Stand 2026-09-13)

- **developer.ckvsoft.at** (chris, -p 19022): `mysql80`-Container
  (MySQL 8.0.46, Port 3306) mit Test-DBs `mydns_test`, `pdns_test`,
  `pmwh3_test` (User pmtst).php Code Staging: `/tmp/pmwhtest/` (scp
  aus ~/tmp/pmwhtest-staging, kein Prod-Kontakt).
- Testlauf (beide DNS-Adapter, im php:8.4-cli-Wegwerf-Container):
  `run <adapter>` = `test_schema.php <adapter>` (Seed DNS_TYPE) +
  `test_dns_adapters.php <adapter>`; Achtung: module.json
  `dns.database`-name muss zum Testfauxname synchron sein (mydns_test
  ↔ pdns_test), sonst resolved der Registry-Adapter zu null.
- `pdns-test` (ns1) / `pdns`-DB bleibt REAL-Daten-Kopie (11 echte
  Zonen) — nur read-only oder klar benannte Testzonen.
- ns1 mysql-test-Container ist entfernt (wurde durch den dev-Server
  mysql80 ersetzt); lokal existiert kein MySQL/Docker.

## PMWH2-Feature-Referenz für Stubs

Alt-System liegt als ZIP bei: `~/NetBeansProjects/pmwh2.zip`
- Filtering/wblist via amavis_* (ersetzt durch Rspamd-Konzept).
- Tools-Templates: `pmwh2/templates/pmwh2/modules/tools/`
  (backup/news/applications), Functions unter
  `pmwh2/includes/functions/` (z.B. backupDatabase.php).
