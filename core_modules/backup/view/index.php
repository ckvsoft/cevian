<?php
/** @var array $albums */
$baseUri = BASE_URI;
?>

<h1><?= htmlspecialchars($this->title) ?></h1>

<fieldset>
    <legend>Backup</legend>
    <section class="page-content">
        <section class="grid">
            <article>
                <div class="backup-content-wrapper">
                    <p>Aktuelles/Letztes Backup</p>
                    <div>
                        Database: <?= $this->database ?><br />
                        Images: <?= $this->images ?>
                    </div>
                    <hr>

                    <div class="form-group">
                        <label></label>
                        <div>
                            <button class="button small-action save" onclick="startBackupDatabase()">
                                Backup Datenbank starten
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="progress-bar1">Backup Datenbank Fortschritt:</label>
                        <div>
                            <progress id="progress-bar1" value="0" max="100"></progress>
                            <span id="progress-percent1">0%</span>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label for="image-dir">Image-Verzeichnis:</label>
                        <select id="image-dir">
                            <option value="">Lade Verzeichnisse...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label></label>
                        <div>
                            <button class="button small-action save" onclick="startBackupImages()">
                                Backup Images starten
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="progress-bar">Backup Images Fortschritt:</label>
                        <div>
                            <progress id="progress-bar" value="0" max="100"></progress>
                            <span id="progress-percent" >0%</span>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </section>
</fieldset>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        loadImageDirectories();
        // updateProgress(1);
        // updateProgress(2);
    });

    // Dropdown mit Server-Verzeichnissen füllen
    function loadImageDirectories() {
        fetchAndLog("backup/listImageDirs")
                .then(data => {
                    const select = document.getElementById("image-dir");
                    select.innerHTML = "";
                    if (data.dirs && data.dirs.length > 0) {
                        data.dirs.forEach(dir => {
                            const option = document.createElement("option");
                            option.value = dir;
                            option.textContent = dir;
                            select.appendChild(option);
                        });
                    } else {
                        select.innerHTML = '<option value="">Keine Bild-Ordner gefunden</option>';
                    }
                })
                .catch(err => console.error("Fehler beim Laden der Verzeichnisse:", err));
    }

    // Startet das Datenbank-Backup
    function startBackupDatabase() {
        displayMessage("info", "Backup", "Starte Backup der Datenbank.");
        startBackupDB();
        updateProgress(2);
    }

    // Startet das Datenbank-Backup
    function startBackupDB() {
        return fetchAndLog("backup/backupDatabase/", {method: "POST"});
    }

    function startBackupImages() {
        const dir = document.getElementById("image-dir").value;
        if (!dir) {
            displayMessage("info", "Backup", "Bitte ein Verzeichnis auswählen!");
            return;
        }
        // Startet direkt das Backup und dann die Fortschrittsanzeige
        startBackup(dir);
    }

    function startBackup(dir) {
        displayMessage("info", "Backup", "Starte Backup. Zähle Dateien...");
        fetchAndLog('backup/countFilesToCopy/', {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({directory: dir})  // <- hier der Pfad
        })
                .then(jsonData => {
                    if (jsonData.totalSize === 0) {
                        displayMessage("info", "Backup", "Es gibt nichts zu sichern.");
                        // Stoppe eventuell laufenden Fortschrittsbalken, falls vorhanden
                        updateProgress(-1);
                    } else {
                        displayMessage("info", "Backup", `Starte Backup von ${jsonData.data.totalSize} Dateien.`);
                        // Starte den Backup-Prozess im Backend

                        fetchAndLog('backup/backupFiles/', {
                            method: "POST",
                            headers: {"Content-Type": "application/json"},
                            body: JSON.stringify({directory: dir})  // <- hier der Pfad
                        })
                                // fetchAndLog(`backup/backupFiles/${dir}/`, {method: "POST"})
                                .then(jsonData => {
                                    console.log("Backup gestartet:", jsonData);
                                })
                                .catch(error => console.log(error));

                        // Starte die Fortschrittsanzeige für ID 1
                        updateProgress(1);
                    }
                })
                .catch(error => console.log(error));
    }

    // Fortschrittsanzeige aktualisieren
    let backupRunning = {1: false, 2: false}; // Tracken, ob Backup läuft

    function updateProgress(processid) {
        if (backupRunning[processid])
            return; // Schon läuft → kein neues Interval
        backupRunning[processid] = true;

        let intervalId = setInterval(() => {
            fetchAndLog('backup/progress/' + processid, {cache: "no-cache"})
                    .then(progressData => {
                        let progress = parseFloat(progressData?.percent);
                        if (Number.isFinite(progress)) {
                            if (processid === 1) {
                                document.getElementById("progress-bar").value = progress;
                                document.getElementById("progress-percent").innerHTML = `${progress}%`;
                            } else {
                                document.getElementById("progress-bar1").value = progress;
                                document.getElementById("progress-percent1").innerHTML = `${progress}%`;
                            }

                            if (progress >= 100 || progress < 0) {
                                clearInterval(intervalId);
                                displayMessage("success", "Backup", `Backup ist fertig.`);
                                backupRunning[processid] = false; // Flag zurücksetzen
                            }
                        }
                    })
                    .catch(error => console.log(error));
        }, 1000);
    }

    function fetchAndLog(url, options = {}) {
        return fetch(url, options)
                .then(async resp => {
                    const text = await resp.text();
                    console.log("Response von", url, ":", text || "<leer>");

                    if (!text) {
                        // Leere Antwort – einfach null zurückgeben
                        console.warn("Antwort ist leer.");
                        return null;
                    }

                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.warn("Keine gültige JSON-Antwort:", e.message);
                        return text;
                    }
                })
                .catch(err => {
                    console.error("Fetch-Fehler bei", url, ":", err);
                    throw err;
                });
    }
</script>
