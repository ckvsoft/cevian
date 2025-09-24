<?php

class Installer_model extends ckvsoft\mvc\Model
{

    public function setupDatabase(array $data): array
    {
        try {
            $dbHost = $data['db_host'];
            $dbName = $data['db_name'];
            $dbUser = $data['db_user'];
            $dbPass = $data['db_pass'];

            $adminUser = $data['admin_user'];
            $adminEmail = $data['admin_email'];
            $adminPass = $data['admin_pass'];

            $pdo = new \PDO("mysql:host=$dbHost", $dbUser, $dbPass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // DB anlegen
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $pdo->exec("USE `$dbName`;");

            // SQL-Schema laden
            $schemaFile = __DIR__ . "/../inc/sql/schema.sql";
            if (!file_exists($schemaFile)) {
                throw new \Exception("Schema file not found: $schemaFile");
            }
            $sql = file_get_contents($schemaFile);

            foreach (array_filter(array_map('trim', explode(";", $sql))) as $statement) {
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            // App-Hash-Key und Base-URI prüfen / setzen
            $appFile = __DIR__ . '/../../../config/app.json';

            if (file_exists($appFile)) {
                $app = json_decode(file_get_contents($appFile), true);
            } else {
                $app = [
                    'app' => [],
                    'paths' => []
                ];
            }

            $hashKey = bin2hex(random_bytes(32));

            // Hash-Key generieren falls nicht vorhanden
            if (empty($app['app']['hash_key'])) {
                $app['app']['hash_key'] = $hashKey;
            }

            // Base-URI setzen (oder überschreiben)
            $app['paths']['base_uri'] = BASE_URI;


            // Admin-User hinzufügen
            $stmt = $pdo->prepare("INSERT IGNORE INTO user (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $adminUser,
                $adminEmail,
                \ckvsoft\Hash::create('sha256', $adminPass, $hashKey),
                'admin'
            ]);

            // config.json schreiben
            $config = [
                'database' => [
                    'type' => 'mysql',
                    'host' => $dbHost,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                ]
            ];

            file_put_contents(__DIR__ . '/../../../config/config.json', json_encode($config, JSON_PRETTY_PRINT));
            file_put_contents($appFile, json_encode($app, JSON_PRETTY_PRINT));

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
