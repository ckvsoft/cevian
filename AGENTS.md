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

## Offene Roadmap (Kurzfassung, Stand 2026-09-12)

1. Framework: `moduleDb($module, $configPath)` + `cachedDatabase()`
   + `Database::__construct` dbname-optional & port-support.
2. DNS.1: Adapter-Capability-API, Registry-Auflösung in DnsManager,
   Result-Shape-Verein Banner (`content` statt `data`), README-Kapitel
   "Neuer DNS-Adapter".
3. DNS.2: MyDNS-Adapter vollständig (Reads + Writes + Serial; kein
   DNSSEC → via Capability-API ausblenden). Grund: MyDNS läuft bei
   Kunden.
4. DNS.3/DB-Cleanup: Creds aus module.json in externe
   Server-Mirror-Config (nur Examples im Repo); Adapter auf
   moduleDb-Node-API; mysqldbadapter → cachedDatabase;
   learn_all.php → moduleDb + `roles`-Fix.
5. Filtering (Rspamd): `pmwh3_filtering` + Tab + settings-Export.
6. WBList: `pmwh3_wblist` + Tab + multimap-Maps.
7. Tools: backup (PHP-Dump, Shadow-Dir), news, applications;
   errorlog → Redirect auf options/errorlog.
8. Domain-Reste: Subdomain-CRUD (`apache_subdomains`), Bulk-Import.
9. Kleinzeug: learn_all.php PMWH2-Split-Reste, onsavehooks-TODOs
   (DNS-Rewrite-Daemon, Vhost-Regeneration), Reseller-Hierarchie.

## PMWH2-Feature-Referenz für Stubs

Alt-System liegt als ZIP bei: `~/NetBeansProjects/pmwh2.zip`
- Filtering/wblist via amavis_* (ersetzt durch Rspamd-Konzept).
- Tools-Templates: `pmwh2/templates/pmwh2/modules/tools/`
  (backup/news/applications), Functions unter
  `pmwh2/includes/functions/` (z.B. backupDatabase.php).
