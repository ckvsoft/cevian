<?php
/** @var array $albums */
$baseUri = BASE_URI;
?>

<h1><?= htmlspecialchars($this->title) ?></h1>

<fieldset>
    <legend><?= _('Backup') ?></legend>
    <section class="page-content">
        <section class="grid">
            <article>
                <div class="backup-content-wrapper">
                    <p><?= _('Current/Last Backup') ?></p>
                    <div>
                        <?= _('Database') ?>: <?= $this->database ?><br />
                        <?= _('Images') ?>: <?= $this->images ?>
                    </div>
                    <hr>

                    <div class="form-group">
                        <label></label>
                        <div>
                            <button class="button small-action save" onclick="startBackupDatabase()">
                                <?= _('Start Database Backup') ?>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="progress-bar1"><?= _('Database Backup Progress') ?>:</label>
                        <div>
                            <progress id="progress-bar1" value="0" max="100"></progress>
                            <span id="progress-percent1">0%</span>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label for="image-dir"><?= _('Image Directory') ?>:</label>
                        <select id="image-dir">
                            <option value=""><?= _('Loading directories...') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label></label>
                        <div>
                            <button class="button small-action save" onclick="startBackupImages()">
                                <?= _('Start Images Backup') ?>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="progress-bar"><?= _('Images Backup Progress') ?>:</label>
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
    // Global function/placeholder for localization (as requested)
    function _(text) {
        return text;
    }

    document.addEventListener("DOMContentLoaded", () => {
        // Load the image directories when the page is ready
        loadImageDirectories();
        // Assuming updateProgress calls (1 and 2) are handled elsewhere or intentionally commented out
        // updateProgress(1);
        // updateProgress(2);
    });

    /**
     * Fills the dropdown with directories from the server.
     */
    function loadImageDirectories() {
        fetchAndLog("backup/listImageDirs")
                .then(data => {
                    const select = document.getElementById("image-dir");
                    select.innerHTML = "";
                    if (data && data.dirs && data.dirs.length > 0) {
                        data.dirs.forEach(dir => {
                            const option = document.createElement("option");
                            option.value = dir;
                            option.textContent = dir;
                            select.appendChild(option);
                        });
                    } else {
                        select.innerHTML = `<option value="">${_('No image folders found')}</option>`;
                    }
                })
                .catch(err => console.error(_("Error loading directories:"), err));
    }

    /**
     * Starts the Database Backup process.
     */
    function startBackupDatabase() {
        displayMessage("info", _("Backup"), _("Starting database backup."));
        startBackupDB();
        updateProgress(2);
    }

    /**
     * Calls the backend endpoint to start the DB backup.
     */
    function startBackupDB() {
        return fetchAndLog("backup/backupDatabase/", {method: "POST"});
    }

    /**
     * Initiates the Images Backup process (selects directory, counts files, then starts backup).
     */
    function startBackupImages() {
        const dir = document.getElementById("image-dir").value;
        // Check if a directory is selected.
        if (!dir || dir === _('No image folders found')) {
            displayMessage("info", _("Backup"), _("Please select a directory!"));
            return;
        }
        // Starts file counting and subsequent backup
        startImageBackup(dir);
    }

    /**
     * Counts files and, if > 0, starts the image backup.
     * @param {string} dir The directory to backup.
     */
    function startImageBackup(dir) {
        displayMessage("info", _("Backup"), _("Starting backup. Counting files..."));

        // 1. Count files to be copied (differential)
        fetchAndLog('backup/countFilesToCopy/', {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({directory: dir})
        })
                .then(jsonData => {
                    let totalFiles = 0;

                    // Robustly try to determine the file count from the response
                    if (typeof jsonData === 'object' && jsonData !== null) {
                        // Try common keys: 'totalSize', 'count', or 'data.totalSize'
                        totalFiles = parseInt(jsonData.totalSize ?? jsonData.count ?? jsonData.data?.totalSize ?? 0);
                    } else if (typeof jsonData === 'string') {
                        // If the controller returned a plain number string (e.g., "10")
                        totalFiles = parseInt(jsonData);
                    }

                    // Fallback for failed parsing
                    if (isNaN(totalFiles)) {
                        totalFiles = 0;
                    }

                    if (totalFiles <= 0) {
                        // No files to copy, skip backup start
                        displayMessage("success", _("Backup"), _("No files to backup. All files are up-to-date."));

                        // Reset progress bar to 0%
                        document.getElementById("progress-bar").value = 0;
                        document.getElementById("progress-percent").textContent = "0%";

                    } else {
                        // START BACKUP: Files found, proceed with the process
                        displayMessage("info", _("Backup"), `${_('Starting backup of')} ${totalFiles} ${_('files.')}`);

                        // 2. Start the actual backup process in the backend
                        fetchAndLog('backup/backupFiles/', {
                            method: "POST",
                            headers: {"Content-Type": "application/json"},
                            body: JSON.stringify({directory: dir})
                        })
                                .then(jsonData => {
                                    console.log(_("Backup started:"), jsonData);
                                })
                                .catch(error => console.error(error));

                        // 3. Start the progress bar for ID 1
                        updateProgress(1);
                    }
                })
                .catch(error => console.error(_("Error during file count:"), error));
    }

    /**
     * Updates the progress bar status from the server.
     * @param {number} processid The ID of the process (1 for images, 2 for DB).
     */
    let backupRunning = {1: false, 2: false}; // Track whether backup is currently running

    function updateProgress(processid) {
        if (backupRunning[processid])
            return; // Already running -> no new interval

        backupRunning[processid] = true;

        let intervalId = setInterval(() => {
            fetchAndLog('backup/progress/' + processid, {cache: "no-cache"})
                    .then(progressData => {
                        let progress = parseFloat(progressData?.percent);

                        if (Number.isFinite(progress)) {
                            const progressBar = document.getElementById(`progress-bar${processid === 2 ? '1' : ''}`);
                            const progressPercent = document.getElementById(`progress-percent${processid === 2 ? '1' : ''}`);

                            progressBar.value = progress;
                            progressPercent.textContent = `${progress}%`;

                            if (progress >= 100 || progress < 0) {
                                clearInterval(intervalId);
                                displayMessage("success", _("Backup"), _("Backup is complete."));
                                backupRunning[processid] = false; // Reset flag
                            }
                        }
                    })
                    .catch(error => console.error(error));
        }, 1000);
    }

    /**
     * Fetches data from a URL and logs the response.
     * @param {string} url The URL to fetch.
     * @param {object} options Fetch options.
     * @returns {Promise<any>}
     */
    function fetchAndLog(url, options = {}) {
        return fetch(url, options)
                .then(async resp => {
                    const text = await resp.text();
                    console.log(_("Response from"), url, ":", text || _("<empty>"));

                    if (!text) {
                        // Empty response – just return null
                        console.warn(_("Response is empty."));
                        return null;
                    }

                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.warn(_("Not a valid JSON response:"), e.message);
                        return text;
                    }
                })
                .catch(err => {
                    console.error(_("Fetch error at"), url, ":", err);
                    throw err;
                });
    }
</script>