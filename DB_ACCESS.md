# DB_ACCESS.md — DB-Zugriff-Mechanik in cevian/pmwh3

Stand: 2026-09-12. Begleitet `AGENTS.md` (Regeln). Ziel: jede frische
Session versteht den DB-Fluss ohne Recherche, inkl. geplanter
Erweiterungen (Etappe 1 der Roadmap).

## 1. Der Standard-Fluss (Framework-DB)

```
Controller/Model/Utils
  → Config::moduleDb()                    // library/ckvsoft/mvc/config.php:167
    → getModuleDbInstance($module)        // config.php:90 (lazy, 1x pro Modul)
      → ModulManager::getModuleDb($m)     // library/ckvsoft/modulmanager.php:65
        → liest module.json → "database"   // modulmanager.php:69 (FIXED top-level!)
        → new Database([...])             // modulmanager.php:75
  → Cache: $moduleSharedDbMap[$module]
```

- Ohne eigenen `database`-Node fällt die Modul-DB auf die
  Framework-Shared-DB zurück (`config/config.json` → `self::db()`).
- **Backtrace-Erkennung** (config.php:66-85): `getModuleNameFromBacktrace()`
  matcht `(modules|core_modules)/<modul>/` im Pfad des Aufrufers.
  Funktioniert auch für statische Calls (Adapter unter
  `modules/pmwh3/utils/**` → Modul `pmwh3` wird erkannt).

## 2. Was die JSON schon kann (und der fehlende generalized Pfad)

- `Config::module('dns')` bzw. `Config::module('dns.database')`:
  dotted-Key-Zugriff auf jeden config-Node ist **schon**
  implementiert (config.php:126-129, `explode('.')`).
- `pmwh3/module.json` hat bereits `dns.database` als nested Node
  (und künftig `mail.database`).
- **Lücke (Etappe 1):** `ModulManager::getModuleDb()` liest nur den
  top-level `database`-Node (modulmanager.php:69). Geplant:
  `$configPath`-Parameter (dotted), Default `'database'`,
  Cache-Key-Suffix `"{$module}:{$configPath}"` — Callshapes ohne
  Parameter bleiben exakt identisch.

## 3. Geplante API (Etappe 1, Roadmap)

```php
// config.php — neu/erweitert:
moduleDb(?string $moduleName = null, ?string $configPath = null): Database
cachedDatabase(array $creds): Database        // runtime-Creds (z.B. DB-Admin), Cache-Hash
// library/ckvsoft/database.php — ctor:
dbname optional (DSN ohne ';dbname='), Port-Support in DSN
```

## 4. Status DB-Regel (Stand 2026-09-13, Etappe 0+1 umgesetzt)

**Framework umgesetzt:**
- `Config::moduleDb($moduleName, $configPath)` — dotted configPath
  (z.B. `'dns.database'`), Cache-Key `"{module}:{path}"` (config.php).
- `Config::cachedDatabase(array $creds)` — Runtime-Creds, Cache via
  creds-Hash.
- `Database::__construct` — dbname optional + port-support in DSN.
- `Database::execDdl(string $sql, array $params = [])` — zentraler
  DDL/Admin-Punkt (CREATE/DROP/GRANT/FLUSH/ALTER USER); Parameter
  per :named möglich, Identifier müssen vorher validiert werden.

**Modul.pmwh3 umgewandelt (verifies clean):**
- abstractdnsadapter.php → `Config::moduleDb(null,'dns.database')`
  (ok, backtrace erkennt Modul); Node existiert aber inkonfig → harter
  CkvException-Fallback nicht mehr silent (bewusst).
- postfixadapter.php → `moduleDb(null,'mail.database')`.
- mysqldbadapter.php → `Config::cachedDatabase()` + `execDdl` (DDL);
  listUsers/listDatabases via `select()`.
- traffic_model.php / pmwh3menu_helper.php / pmwh3i18n.php →
  `select()/selectOne()` Helper.
- learn_all.php → Bootstrap via var/config.php + `Config::moduleDb()`,
  Gruppennamen aus RBAC `roles` (pmwh3_groups ab 4d weg).

**Gate:** `grep -rnE '\->(query|exec|prepare)\(' modules/pmwh3` und
`grep -rn 'new PDO\|new Database' modules/pmwh3` sind beide leer.

**Wichtige Semantik:** `_prepareAndBind` (database.php:497+) baut
`:{$key}` — Bind-Keys im select/select/execDdl **ohne** Leading
Colon (z.B. `['db' => $name]`). DB-Admin-Adapter (mysqldbadapter)
verbindet mit leerem dbname (`primary`-Fallback),
daher unset dbname im cachedDatabase-Aufruf.

## 5. Adapter-Konventionen (DNS als Muster)

- `utils/dns/*`: non-abstract Klasse, implementiert
  `DnsAdapterInterface` + statics `getKey()/getName()/isAvailable()`
  → wird via `AdapterRegistry::discover($dir, $interface)`
  automatisch gefunden (utils/adapterregistry.php).
- `AbstractDnsAdapter` enthält No-op-Defaults für Writes/DNSSEC
  (dead safe: "unsupported"). DnsManager delegiert; Fähigkeiten
  sollen via Capability-API explizit werden (Etappe DNS.1, Roadmap).
- Tabelle für Prefix-Varianten (PowerDNS): `dns.table_prefix` aus
  module.json; Fallback automatisch (pdnsadapter.php:81-96).

## 6. Server-Notizen (kurz, Details siehe AGENTS.md)

- Live-DNS: `pdns`-Container, Port 53, DB `ck000005_pmwh2`
  (Prefix-Tabellen `pdns_*`) — read-only für Entwicklung.
- Test: `pdns-test`-Container, Port 5301 (nicht published),
  DB `pdns` (stock, keine Präfix) — nur klar benannte Testzonen
  anfassen, die 11 Produkt-Zonen read-only.
- Prod-Schema `ck000005_pmwh2` enthält auch Legacy:
  `mydns_soa/mydns_rr`, `amavis_*`, `apache_subdomains` — Referenz
  für die MyDNS-Adapter-Verifikation und die Spätere
  Subdomain-Komponente.
