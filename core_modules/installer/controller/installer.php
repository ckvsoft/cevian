<?php

class Installer extends ckvsoft\mvc\BaseController
{

    private string $stateFile;
    private string $securityFile;
    private array $state;

    public function __construct()
    {
        parent::__construct();

        $configPath = __DIR__ . '/../../../config/config.json';
        $appPath = __DIR__ . '/../../../config/app.json';
        if (file_exists($configPath) && file_exists($appPath)) {
            error_log("Installer is locked. Application already installed.");
            $this->location(BASE_URI);
        }

        $this->stateFile = __DIR__ . '/../../../var/installer_state.json';
        if (!file_exists(dirname($this->stateFile))) {
            mkdir(dirname($this->stateFile), 0775, true);
        }

        if (file_exists($this->stateFile)) {
            // Datei existiert schon → nur laden
            $this->state = json_decode(file_get_contents($this->stateFile), true);
            $this->securityFile = $this->state['step1']['securityFile'] 
                                  ?? 'install_' . bin2hex(random_bytes(8)) . '.txt';
        } else {
            // Datei existiert noch nicht → anlegen
            $this->securityFile = 'install_' . bin2hex(random_bytes(8)) . '.txt';
            $this->state = [
                'step1' => ['securityFile' => $this->securityFile, 'done' => false],
                'step2' => ['done' => false],
                'step3' => ['done' => false], // Admin user setup
                'step4' => ['done' => false], // Database
                'step5' => ['done' => false], // Finish
            ];

            // Nur schreiben, wenn Datei wirklich noch nicht existiert
            file_put_contents($this->stateFile, json_encode($this->state, JSON_PRETTY_PRINT));
        }
    }

    private function renderPage(string $view, array $data = [])
    {
        $this->view->render('installer/inc/header');
        $this->view->render("installer/{$view}", $data);
        $this->view->render('installer/inc/footer');
    }

    // Einstieg → zeigt automatisch Step1
    public function index()
    {
        // Hinweise vorbereiten
        $baseUri = rtrim(BASE_URI, '/');
        $htaccessHint = '';
        if ($baseUri !== '') {
            $htaccessHint = "⚠ Apache: RewriteBase in `.htaccess` auf '$baseUri/' setzen!";
        }
        $nginxHint = "⚠ Nginx: Lies die `readme.md` im nginx-Ordner für passende Konfiguration.";

        // Seite rendern
        $this->renderPage('index', [
            'securityFile' => $this->securityFile,
            'htaccessHint' => $htaccessHint,
            'nginxHint' => $nginxHint
        ]);
    }

    public function checkSecurity()
    {
        $fullPath = __DIR__ . '/../../../' . $this->securityFile;
        $ok = file_exists($fullPath);

        // Erst prüfen, dann State setzen
        $this->state['step1']['done'] = $ok;
        $this->saveState();

        $this->renderPage('step1_security', [
            'ok' => $ok,
            'securityFile' => $this->securityFile
        ]);
    }

    public function checkEnvironment()
    {
        $writablePaths = ['config/', 'var/', 'modules/'];
        $readablePaths = ['config/', 'var/', 'modules/'];
        $checks = ['writable' => [], 'readable' => []];

        foreach ($writablePaths as $path) {
            $full = $this->pathRoot . $path;
            $checks['writable'][$path] = is_writable($full);
        }
        foreach ($readablePaths as $path) {
            $full = $this->pathRoot . $path;
            $checks['readable'][$path] = is_readable($full);
        }

        $this->state['step2']['done'] = !in_array(false, $checks['writable']) && !in_array(false, $checks['readable']);
        $this->state['step2']['checks'] = $checks;
        $this->saveState();

        $this->renderPage('step2_environment', ['checks' => $checks, 'state' => $this->state]);
    }

    public function setupAdmin()
    {
        // POST-Daten sicher abrufen
        $postData = filter_input_array(INPUT_POST, [
            'username' => FILTER_SANITIZE_STRING,
            'password' => FILTER_SANITIZE_STRING,
            'email' => FILTER_VALIDATE_EMAIL
        ]);

        if ($postData) {
            $username = trim($postData['username'] ?? '');
            $password = trim($postData['password'] ?? '');
            $email = trim($postData['email'] ?? '');

            if (!$username || !$password || !$email) {
                $error = "Bitte alle Felder ausfüllen!";
            } else {
                // State aktualisieren
                $this->state['step3']['admin'] = [
                    'username' => $username,
                    'password' => $password,
                    'email' => $email
                ];
                $this->state['step3']['done'] = true;
                $this->saveState();

                // Weiter zu Step4
                $this->location('databaseSetup');
                return;
            }
        }

        $this->renderPage('step3_admin', [
            'state' => $this->state,
            'error' => $error ?? null
        ]);
    }

    public function databaseSetup()
    {
        // POST-Daten sicher abrufen
        $postData = filter_input_array(INPUT_POST, [
            'db_host' => FILTER_SANITIZE_STRING,
            'db_name' => FILTER_SANITIZE_STRING,
            'db_user' => FILTER_SANITIZE_STRING,
            'db_pass' => FILTER_SANITIZE_STRING
        ]);

        if ($postData) {
            $model = $this->loadModel('installer');

            // Admin-Daten aus State holen
            $adminData = $this->state['step3']['admin'] ?? [];

            // Kombinieren mit POST-Daten
            $postData['admin_user'] = $adminData['username'] ?? null;
            $postData['admin_email'] = $adminData['email'] ?? null;
            $postData['admin_pass'] = $adminData['password'] ?? null;

            $result = $model->setupDatabase($postData);

            if ($result['success']) {
                $this->state['step4']['done'] = true;
                $this->saveState();
                $this->finish();
                return;
            } else {
                $this->renderPage('step4_database', ['error' => $result['error']]);
                return;
            }
        }

        $this->renderPage('step4_database');
    }

    public function finish()
    {
        $this->state['step5']['done'] = true;
        $this->saveState();

        // Cleanup
        $deletedFiles = [];
        $undeletedFiles = [];

        if (file_exists($this->stateFile)) {
            if (@unlink($this->stateFile)) {
                $deletedFiles[] = $this->stateFile;
            } else {
                $undeletedFiles[] = $this->stateFile;
            }
        }

        $fullSecurityPath = __DIR__ . '/../../../' . $this->securityFile;
        if (file_exists($fullSecurityPath)) {
            if (@unlink($fullSecurityPath)) {
                $deletedFiles[] = $fullSecurityPath;
            } else {
                $undeletedFiles[] = $fullSecurityPath;
            }
        }

        $this->renderPage('step5_finish', [
            'undeletedFiles' => $undeletedFiles,
            'deletedFiles' => $deletedFiles,
            'baseUri' => BASE_URI // Link zur laufenden Seite
        ]);
    }

    private function saveState()
    {
        file_put_contents($this->stateFile, json_encode($this->state, JSON_PRETTY_PRINT));
    }
}
