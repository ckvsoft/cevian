<?php
// Diese Datei verhindert das Auflisten des Verzeichnisinhalts
// und kann optional eine 404-Fehlerseite anzeigen.
http_response_code(404);
die();