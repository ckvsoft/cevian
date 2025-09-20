<style>
    button {
        padding: 10px;
        border: none;
        border-radius: 5px;
        background-color: #233853;
        font-size: 12px;
        color: #fff;
        cursor: pointer;
        margin-left: 2px;
    }
    button:hover {
        background-color: #0666a3;
    }
    select {
        padding: 5px;
        font-size: 12px;
        margin-left: 5px;
        margin-right: 5px;
    }
</style>

<fieldset>
    <legend>Backup</legend>
    <section class="page-content">
        <section class="grid" style="overflow: auto; width: 1123px;">
            <article>
                <p>Aktuelles/Letztes Backup</p>
                <div>
                    Database: <?= $this->database ?> Images: <?= $this->images ?>
                </div>
                <hr>

                <!-- Datenbank Backup -->
                <div>
                    <button onclick="startBackupDatabase()">Backup Datenbank starten</button>
                </div>
                <div>
                    <label for="progress-bar1" style="white-space: nowrap; width: 210px; text-align: right;">
                        Backup Database Fortschritt:
                    </label>
                    <progress id="progress-bar1" class="progress-bar" value="0" max="100"></progress>
                    <span id="progress-percent1">0%</span>
                </div>

                <hr>

                <!-- Image Backup -->
                <div style="margin-top:10px;">
                    <label for="image-dir">Image-Verzeichnis:</label>
                    <select id="image-dir">
                        <option value="">Lade Verzeichnisse...</option>
                    </select><br />
                    <button onclick="startBackupImages()">Backup Images starten</button>
                </div>
                <div>
                    <label for="progress-bar" style="white-space: nowrap; width: 210px; text-align: right;">
                        Backup Images Fortschritt:
                    </label>
                    <progress id="progress-bar" class="progress-bar" value="0" max="100"></progress>
                    <span id="progress-percent">0%</span>
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

    // Gesamtanzahl der Dateien für Backup ermitteln
    function getTotalFileSize(dir) {
        fetchAndLog(`backup/countFilesToCopy/${dir}`, {method: "GET"})
                .then(jsonData => {
                    if (jsonData.totalSize === 0) {
                        displayMessage("info", "Backup", "Es gibt nichts zu sichern.");
                    } else {
                        displayMessage("info", "Backup", `Starte Backup von ${jsonData.totalSize} Dateien.`);
                        startBackup(jsonData.totalSize, dir);
                    }
                })
                .catch(error => console.log(error));
    }

    // Startet das Image-Backup
    function startBackup(totalSize, dir) {
        return fetchAndLog(`backup/backupFiles/${dir}/`, {method: "POST"});
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
        fetchAndLog(`backup/countFilesToCopy/${dir}`, {method: "GET"})
                .then(jsonData => {
                    if (jsonData.totalSize === 0) {
                        displayMessage("info", "Backup", "Es gibt nichts zu sichern.");
                        // Stoppe eventuell laufenden Fortschrittsbalken, falls vorhanden
                        updateProgress(-1);
                    } else {
                        displayMessage("info", "Backup", `Starte Backup von ${jsonData.totalSize} Dateien.`);
                        // Starte den Backup-Prozess im Backend
                        fetchAndLog(`backup/backupFiles/${dir}/`, {method: "POST"})
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
