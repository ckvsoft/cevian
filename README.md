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
- **Flexible Konfiguration:** JSON-basierte Konfigurationsdateien für Datenbank- und App-Einstellungen.

## Installation

1. Repository in das Projekt klonen.
2. `config/config.json` und `config/app.json` erstellen.
3. Root- und Modulpfade in der `Bootstrap`-Klasse definieren.

## Nutzung

### Bootstrap
Die `Bootstrap`-Klasse analysiert die URI, wählt Controller und Methode aus, übergibt Parameter und initialisiert Controller und Views.

```php
$bootstrap = new \ckvsoft\mvc\Bootstrap();
$bootstrap->setPathRoot('/path/to/your/modules');
$bootstrap->init();
```

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

- **config/config.json**: Datenbankkonfiguration.
- **config/app.json**: App-weite Einstellungen.

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
- **Flexible Configuration:** JSON-based configuration files for database and app settings.

## Installation

1. Clone the repository into your project.
2. Set up `config/config.json` and `config/app.json`.
3. Define the root path and module paths in the `Bootstrap` class.

## Usage

### Bootstrap
The `Bootstrap` class parses the URI, selects controller and method, passes parameters, and initializes controllers and views.

```php
$bootstrap = new \ckvsoft\mvc\Bootstrap();
$bootstrap->setPathRoot('/path/to/your/modules');
$bootstrap->init();
```

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

- **config/config.json**: Database configuration.
- **config/app.json**: Application-wide settings.

## License

CKVSoft MVC is licensed under the **MIT License**.

