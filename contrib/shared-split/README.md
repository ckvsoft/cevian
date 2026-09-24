# Shared-Split — cevian als Einmal-Installation

Seit **0.19.0** kann cevian als EINMALIGE Framework-Installation betrieben
werden: Der Code (Framework + Core-Module + Locale) liegt einmal in einem
geteilten Baum, jede Site nutzt ihn mit eigenem Config/Modulen/Daten.

## Zielbild

| Inhalt                          | Ort                                             |
| ------------------------------- | ----------------------------------------------- |
| `library/`, `core_modules/`, `locale/`, `index.php`, `config/app_defaults.json` | geteilter Baum, z. B. `/vhome/vhtml/_cevian` |
| `config/config.json` + `config/app.json` | **pro Site** im Docroot |
| `modules/` (z. B. pmwh3, mbv, dyndns, emailcheck) | **pro Site** im Docroot |
| `var/`, `public/`, `.htaccess`, `.git` | **pro Site** im Docroot |

Merge-Priorität unverändert: `app.json` überschreibt `app_defaults.json`
(`array_replace_recursive`). Jede Site kann Config-Overrides und eigene
Module behalten — „service“ und „kvasny“ sind kein Klon.

## Wie es funktioniert

Der Site-Docroot enthält nur noch einen ~5-Zeilen-Stub:

```php
define('CEVIAN_SITE_ROOT', __DIR__ . '/');
require '/vhome/vhtml/_cevian/index.php';
```

Der Stub heißt **bewusst Klartext-Pfad, kein Symlink** (seafile-Sync/
Symlink-Instabilität). `library/ckvsoft/paths.php` (`ckvsoft\Paths`)
unterscheidet:

- `Paths::siteRoot()`   → Docroot der Site (config/, modules/, var/, public/)
- `Paths::coreRoot()`   → geteilter Framework-Baum (library/, locale/, …)
- `Paths::coreModulesDir()` → `coreRoot()/core_modules/`

**Standalone-Fallback:** Ohne Stub (kein `CEVIAN_SITE_ROOT`) fallen beide
Konstanten im gemeinsamen `index.php` auf `__DIR__` zurück — Tests und alte
Einzelbaum-Instanzen verhalten sich byte-gleich wie bisher.

## Migration einer bestehenden Site (am Server, NS1)

1. **Backup:** kompletten Site-Baum als Rollback-Backup liegen lassen
   (z. B. `cp -a` in `/root/site-backup-...`), **VOR** seafile-Tree-Ops
   den seafile-Client stoppen (Lektion: lokale Deletes propagieren!).
2. **Shared-Baum:** Repo einmalig nach `/vhome/vhtml/_cevian` klonen
   (`git clone ... _cevian`). Gelesen werden nur `library/`, `core_modules/`,
   `locale/`, `index.php`, `config/app_defaults.json` — vorhandene
   `modules/`/`.git` im Clone sind harmlos.
3. **Stub:** `contrib/shared-split/site-index.php` in den Site-Docroot
   legen (Pfad im `require` anpassen).
4. **locals aufräumen:** `library/`, `core_modules/`, `locale/` aus dem
   Site-Docroot löschen (liegen jetzt geteilt). `config/app_defaults.json`
   im Site-Docroot lösbar (wird nicht mehr gelesen — loswerden).
5. **Live-Check** mit der Endpoint-Liste (Site → 200, Module → 200).
6. **Alten Baum** (Library+Locale) als Rollback-Backup behalten, bis der
   Betrieb einige Tage grün war.

Neue Site = Docroot + Stub + `config/` + `modules/` anlegen.

## Deploy-Konventionen (danach)

- **Framework-Updates** laufen im geteilten Baum (`_cevian`): einmalig
  `git pull` dort, alle Sites ziehen mit. Version = `0.19.0` für alle.
- **Site-eigene Dateien** (config/, modules/) werden pro Site deployed:
  kvasny weiterhin per `git pull` im Site-Tree, service weiterhin per
  scp/md5 in den service-Tree.
- `module.json`/`config.json` bleiben nur am Server (Repo: Beispiele).