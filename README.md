# simple_php_framework
Simple PHP Framework
# CKVSoft MVC Framework

CKVSoft MVC ist ein leichtgewichtiges, modulares PHP-Framework, das die klassische **Model-View-Controller (MVC)**-Architektur implementiert. Es legt den Fokus auf Modularität, einfache Konfiguration und eine klare Trennung von Logik, Darstellung und Datenzugriff. Das Framework bietet außerdem Utilities für CSS/JS-Analyse, Mobile-Erkennung und flexible Helper-Integration.

## Features

- **Modularität:** Trennung zwischen Modul-Controllern, Subcontrollern, Views, Modellen und Core-Modulen.
- **Dynamisches Laden:** Automatisches Laden von Controllern, Modellen, Helfern und Views basierend auf der URI.
- **Fehlerbehandlung:** Detaillierte Fehlermeldungen im Debug-Modus, zentrale Exceptions im Produktionsmodus.
- **Asset-Management:** Logging von fehlgeleiteten Asset-Anfragen (CSS/JS/Bilder).
- **CSS/JS-Analyse:** Automatische Überprüfung ungenutzter CSS-Selektoren und JS-Verwendung pro View.
- **Mobile-Erkennung:** Zugriff über `$controller->mobile` und `$view->mobile`.
- **Flexible Konfiguration:** Alle App- und Datenbank-Einstellungen werden automatisch vom Installer erstellt.

## Installation

1. Repository in das Projekt klonen.
2. Installer automatisch starten:
   - Wenn `config/config.json` oder `config/app.json` fehlen, wird beim ersten Aufruf automatisch der Installer gestartet.
   - Der Installer erstellt die Konfigurationsdateien, initialisiert die Datenbank und legt den ersten Admin-User an.
   - Ein `hash_key` wird automatisch generiert und in `config/app.json` gespeichert.
3. Root- und Modulpfade werden automatisch vom Framework gesetzt.

## Nutzung

### Bootstrap
Das Framework initialisiert automatisch alle Controller, Views und Module. **Es sind keine manuellen Anpassungen nötig**, auch nicht bei Updates.

### Controller
Controller erweitern die `Controller`-Klasse und können Modelle, Helfer und Views laden.

```php
class Index extends \ckvsoft\mvc\Controller {
    public function index() {
        $this->view->render('index', ['message' => 'Hallo Welt']);
    }
}
```

### Modelle
Modelle erweitern die `Model`-Klasse und bieten Zugriff auf die Datenbank.

```php
class User_model extends \ckvsoft\mvc\Model {
    public function getAllUsers() {
        return $this->db->query('SELECT * FROM users');
    }
}
```

### Views
Views werden über das `View`-Objekt gerendert. CSS- und JS-Nutzung kann automatisch analysiert werden.

```php
$this->view->render('header');
$this->view->render('content', ['data' => $data]);
$this->view->render('footer');
```

### Helper
Helper können aus Modul- oder Core-Verzeichnissen geladen werden.

```php
$helper = $this->loadHelper('form');
$helper->validate($data);
```

## Konfiguration

- Alle wichtigen Einstellungen (App, Datenbank) werden automatisch vom Installer in `config/config.json` und `config/app.json` angelegt.
- Manuelles Erstellen von Konfigurationsdateien ist nicht notwendig.
- Der Installer erzeugt auch den ersten Admin-User und den `hash_key`.

## Lizenz

CKVSoft MVC steht unter der **MIT-Lizenz**.

---

# CKVSoft MVC Framework (English)

CKVSoft MVC is a lightweight, modular PHP framework implementing the classic **Model-View-Controller (MVC)** architecture. It emphasizes modularity, easy configuration, and a clear separation between logic, presentation, and data access. The framework also provides utilities for CSS/JS analysis, mobile detection, and flexible helper integration.

## Features

- **Modularity:** Separation of module controllers, subcontrollers, views, models, and core modules.
- **Dynamic Loading:** Automatic loading of controllers, models, helpers, and views based on the URI.
- **Error Handling:** Detailed error messages in debug mode and centralized exceptions in production mode.
- **Asset Management:** Logging of misrouted asset requests (CSS/JS/images).
- **CSS/JS Analysis:** Automatic checking of unused CSS selectors and JS usage per view.
- **Mobile Detection:** Access via `$controller->mobile` and `$view->mobile`.
- **Flexible Configuration:** All app and database settings are automatically created by the installer.

## Installation

1. Clone the repository into your project.
2. Installer starts automatically:
   - If `config/config.json` or `config/app.json` are missing, the installer runs automatically on first access.
   - The installer creates the configuration files, initializes the database, and creates the first admin user.
   - A `hash_key` is automatically generated and stored in `config/app.json`.
3. Root and module paths are set automatically by the framework.

## Usage

### Bootstrap
The framework automatically initializes all controllers, views, and modules. **No manual modifications are necessary**, even during updates.

### Controllers
Controllers extend the `Controller` class and can load models, helpers, and views.

```php
class Index extends \ckvsoft\mvc\Controller {
    public function index() {
        $this->view->render('index', ['message' => 'Hello World']);
    }
}
```

### Models
Models extend the `Model` class and provide database access.

```php
class User_model extends \ckvsoft\mvc\Model {
    public function getAllUsers() {
        return $this->db->query('SELECT * FROM users');
    }
}
```

### Views
Views are rendered via the `View` object. CSS/JS usage can be automatically analyzed.

```php
$this->view->render('header');
$this->view->render('content', ['data' => $data]);
$this->view->render('footer');
```

### Helpers
Helpers can be loaded from module-specific or core directories.

```php
$helper = $this->loadHelper('form');
$helper->validate($data);
```

## Configuration

- All important settings (app, database) are automatically created by the installer in `config/config.json` and `config/app.json`.
- Manual creation of configuration files is not required.
- The installer also creates the first admin user and the `hash_key`.

## License

CKVSoft MVC is licensed under the **MIT License**.

