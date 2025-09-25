# Cevian: Ein Einfaches & Elegantes PHP Framework

## Deutsch

Cevian ist ein leichtgewichtiges und robustes Model-View-Controller (MVC) Framework, das die Webentwicklung vereinfacht. Es legt den Fokus auf Einfachheit, Sicherheit und eine saubere Struktur, die es erlaubt, leistungsstarke Anwendungen ohne unnötige Komplexität zu erstellen.

Der Name "Cevian" stammt aus der Geometrie, wo ein Cevian eine Linie ist, die einen Eckpunkt eines Dreiecks mit einem Punkt auf der gegenüberliegenden Seite verbindet. Dies spiegelt die Kernphilosophie des Frameworks wider: einen direkten und effizienten Pfad für die Verbindung von Logik, Daten und Views der Anwendung bereitzustellen.

### Wichtige Funktionen

- **Saubere MVC Architektur:** Klare Trennung von Modellen, Views und Controllern.
- **Modulbasierte Struktur:** Erweiterung oder Überschreibung von Kernfunktionen durch Module, ohne das Framework-Kernsystem zu verändern.
- **Sicheres Datenbank-Layer:** Einfacher Zugriff auf Daten über PDO, Schutz vor SQL-Injection.
- **Intuitive Konfiguration:** Alle Einstellungen werden automatisch vom Installer in `config/config.json` und `config/app.json` erstellt. Nachträgliche Anpassungen sind optional.
- **Entwickler-Tools:** CSS/JS-Analyzer erkennt ungenutzten Code und hält Projekte schlank.

### Erste Schritte

Repository klonen:

```bash
git clone https://github.com/ckvsoft/cevian.git
cd cevian
```

Nach dem Clonen und Einrichten der Webserver-Konfiguration (`.htaccess` oder Nginx) wird der Installer **einmalig automatisch** ausgeführt, falls Konfigurationsdateien fehlen.  
Der Installer erstellt:

- `config/config.json` und `config/app.json`
- Datenbankschema
- ersten **Admin-User**
- `hash_key` für sichere Nutzung

Nach Abschluss des Installers ist das Framework sofort einsatzbereit.  
Die Konfigurationsdateien können nachträglich optional angepasst werden.

### Webserver-Konfiguration

Cevian benötigt URL-Rewriting, um alle Anfragen über `index.php` zu leiten.

#### Apache (.htaccess)

```apache
RewriteEngine On

RewriteBase /

RewriteCond %{HTTPS} off
RewriteRule .* https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

RewriteCond %{HTTP_HOST} !^www\.
RewriteRule .* https://www.%{HTTP_HOST}%{REQUEST_URI} [QSA,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)$ index.php?uri=$1 [QSA,L]
```

> **Hinweis:** Wenn Cevian in einem Unterverzeichnis installiert wird (z. B. `/cevian`), muss `RewriteBase /` auf `RewriteBase /cevian/` angepasst werden.

#### Nginx

```nginx
# HTTP → HTTPS + www Redirect
server {
    listen 80;
    server_name example.com www.example.com;

    # Alles auf HTTPS umleiten
    return 301 https://$host$request_uri;
}

# HTTPS Server
server {
    listen 443 ssl;
    server_name example.com www.example.com;

    # SSL-Zertifikate (Let's Encrypt oder andere)
    ssl_certificate     /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    root /var/www/html;
    index index.php;

    # non-www → www Redirect
    if ($host = example.com) {
        return 301 https://www.example.com$request_uri;
    }

    # Alle Requests
    location / {
        # Falls die Datei oder das Verzeichnis existiert → direkt ausliefern (Bilder, CSS, JS, etc.)
        # Falls nicht → an index.php?uri=… weiterleiten (Bootstrap / Router)
        try_files $uri /index.php?uri=$uri&$args;
    }

    # PHP-Files
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;  # PHP-FPM Socket oder TCP-Port anpassen
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Optional: Zugriff auf versteckte Dateien verhindern (.htaccess, .env, etc.)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> **Hinweis:** Bei Installation in einem Unterverzeichnis den `root`-Pfad anpassen (z. B. `root /var/www/html/cevian/public;`).

### Beispiel

#### Controller erstellen

`modules/users/controller/users.php`:

```php
<?php

class Users extends \ckvsoft\mvc\BaseController
{
    public function index()
    {
        $userModel = $this->loadModel('users');
        $users = $userModel->getAllUsers();
        
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => 'User List']],
            ['view' => 'users/index', 'data' => ['users' => $users]],
            ['view' => '/inc/footer']
        ]);
    }
}
```

#### Model erstellen

`modules/users/model/users_model.php`:

```php
<?php

class Users_Model extends \ckvsoft\mvc\Model
{
    public function getAllUsers()
    {
        return $this->db->select("SELECT id, name, email FROM users");
    }
}
```

#### View erstellen

`modules/users/view/users/index.php`:

```php
<h2>User List</h2>

<?php if (!empty($this->users)): ?>
    <ul>
    <?php foreach ($this->users as $user): ?>
        <li><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)</li>
    <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>Keine Benutzer gefunden.</p>
<?php endif; ?>
```

---

## Lizenz

Cevian steht unter der **MIT-Lizenz**.

# English

## Cevian: A Simple & Elegant PHP Framework

Cevian is a lightweight and robust Model-View-Controller (MVC) framework designed to streamline web application development. Focused on simplicity and security, it provides a clean and intuitive structure that allows you to build powerful applications without unnecessary complexity.

The name "Cevian" is inspired by geometry, where a cevian is a line segment connecting a triangle's vertex to a point on the opposite side. This reflects the framework's core philosophy: providing a direct and efficient path for connecting your application's logic, data, and views.

### Key Features

- **Clean MVC Architecture:** Clear separation of models, views, and controllers.
- **Module-Based Structure:** Extend or override core functionality through modules without touching the core.
- **Smart Database Layer:** Simple and secure database access via PDO, protected against SQL injection.
- **Intuitive Configuration:** All settings are automatically created by the installer in `config/config.json` and `config/app.json`. Optional adjustments can be made afterwards.
- **Built-in Development Tools:** CSS/JS analyzer detects unused code and keeps projects lean.

### Getting Started

Clone the repository:

```bash
git clone https://github.com/ckvsoft/cevian.git
cd cevian
```

After cloning and setting up the web server configuration (`.htaccess` or Nginx), the installer runs **once automatically** if configuration files are missing.  
The installer will create:

- `config/config.json` and `config/app.json`
- Database schema
- The first **admin user**
- `hash_key` for secure usage

After installation, the framework is ready to use. Configuration files can be optionally adjusted afterwards.

### Web Server Configuration

Cevian requires URL rewriting to route all requests through `index.php`.

#### Apache (.htaccess)

```apache
RewriteEngine On

RewriteBase /

RewriteCond %{HTTPS} off
RewriteRule .* https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

RewriteCond %{HTTP_HOST} !^www\.
RewriteRule .* https://www.%{HTTP_HOST}%{REQUEST_URI} [QSA,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)$ index.php?uri=$1 [QSA,L]
```

> **Note:** If Cevian is installed in a subdirectory (e.g., `/cevian`), adjust `RewriteBase /` to `RewriteBase /cevian/`.

#### Nginx

```nginx
# HTTP → HTTPS + www Redirect
server {
    listen 80;
    server_name example.com www.example.com;

    # Redirect all HTTP requests to HTTPS
    return 301 https://$host$request_uri;
}

# HTTPS Server
server {
    listen 443 ssl;
    server_name example.com www.example.com;

    # SSL certificates (Let's Encrypt or other)
    ssl_certificate     /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    root /var/www/html;
    index index.php;

    # Redirect non-www to www
    if ($host = example.com) {
        return 301 https://www.example.com$request_uri;
    }

    # Main location block
    location / {
        # If the requested file or directory exists, serve it directly (images, CSS, JS, etc.)
        # Otherwise, pass the request to index.php with the 'uri' parameter for routing
        try_files $uri /index.php?uri=$uri&$args;
    }

    # PHP handling
    location ~ \.php$ {
        include fastcgi_params;  # Load standard FastCGI parameters
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;  # Adjust PHP-FPM socket or TCP port
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;  # Full path to PHP file
    }

    # Optional: Deny access to hidden files (like .htaccess, .env, etc.)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> **Note:** For installation in a subdirectory, adjust the `root` path (e.g., `root /var/www/html/cevian/public;`).

### Example

#### Define Controller

`modules/users/controller/users.php`:

```php
<?php

class Users extends \ckvsoft\mvc\BaseController
{
    public function index()
    {
        $userModel = $this->loadModel('users');
        $users = $userModel->getAllUsers();
        
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => 'User List']],
            ['view' => 'users/index', 'data' => ['users' => $users]],
            ['view' => '/inc/footer']
        ]);
    }
}
```

#### Define Model

`modules/users/model/users_model.php`:

```php
<?php

class Users_Model extends \ckvsoft\mvc\Model
{
    public function getAllUsers()
    {
        return $this->db->select("SELECT id, name, email FROM users");
    }
}
```

#### Define View

`modules/users/view/users/index.php`:

```php
<h2>User List</h2>

<?php if (!empty($this->users)): ?>
    <ul>
    <?php foreach ($this->users as $user): ?>
        <li><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)</li>
    <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No users found.</p>
<?php endif; ?>
```

---

## License

Cevian is licensed under the **MIT License**.
