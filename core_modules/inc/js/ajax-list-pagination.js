// IMPORTANT: This file MUST be processed by the PHP interpreter BEFORE it is served to the browser.
// It contains PHP constants (BASE_URI) and localization calls (_("...")) that must be processed server-side.

document.addEventListener('DOMContentLoaded', () => {

    // ----------------------------
    // Progress Polling
    // ----------------------------
    /**
     * Starts polling a progress URL to check the status of a long-running job.
     * @param {string} progressId - The unique ID of the job's progress status.
     * @param {string} progressUrl - The URL path to fetch the progress data (e.g., 'jobs/progress/').
     * @param {HTMLElement} submitButton - The button element to disable/enable.
     * @param {string} originalText - The original text content of the button.
     * @returns {number} The interval ID for clearing later.
     */
    function startProgressPolling(progressId, progressUrl, submitButton, originalText) {
        const container = submitButton; // The button element itself
        const text = document.getElementById('progress-text-' + progressId);
        if (text) {
            text.textContent = '<?= _("Processing...") ?>';
        }

        if (container) {
            container.disabled = true;
        }

        const interval = setInterval(async () => {
            try {
                const data = await fetchAndLog('<?= BASE_URI ?>' + progressUrl + progressId);
                if (data && typeof data === 'object' && data.data && data.data.percent !== undefined) {
                    const percentage = parseInt(data.data.percent, 10);
                    if (text) {
                        const statusText = (percentage < 100)
                                ? '<?= _("Processing: ") ?>' + percentage + '%'
                                : '<?= _("Complete: 100% ✅") ?>';
                        text.textContent = statusText;
                    }
                    if (percentage >= 100) {
                        clearInterval(interval);
                        if (container) {
                            container.disabled = false;
                        }

                        if (text) {
                            text.textContent = '<?= _("Complete") ?>';
                        }
                    }
                }
            } catch (error) {
                console.error('Polling error:', error);
                clearInterval(interval);
                if (container) {
                    container.disabled = false;
                }
                if (text) {
                    text.textContent = '<?= _("Error!") ?>';
                }
            }
        }, 200);
        return interval;
    }

    /**
     * Clears the progress polling interval.
     * @param {number} pollingInterval - The interval ID.
     */
    function stopProgressPolling(pollingInterval) {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    }

// ----------------------------
// Pagination State Storage (per container/album ID)
// ----------------------------

    /**
     * Creates a unique key based on the container ID (which should represent the album ID).
     * @param {string} containerId - The ID of the container (e.g., 'album-content-123').
     * @returns {string} The unique localStorage key.
     */
    function getPaginationKey(containerId) {
        // The key uses the container ID to ensure state is unique per album/list.
        return 'pagination_page_' + containerId;
    }

    /**
     * Stores the current page number in the browser's localStorage.
     * This persists the state across sessions for the specific album/container.
     * @param {string} containerId - The ID of the paginated container.
     * @param {number} page - The page number to store.
     */
    function storePageInLocalStorage(containerId, page) {
        try {
            localStorage.setItem(getPaginationKey(containerId), page.toString());
        } catch (e) {
            console.warn('localStorage is unavailable or quota exceeded.', e);
        }
    }

    /**
     * Loads the saved page number from localStorage.
     * @param {string} containerId - The ID of the paginated container.
     * @returns {number} The stored page number, or 1 if none is found or if the value is invalid.
     */
    function loadPageFromLocalStorage(containerId) {
        try {
            const storedPage = localStorage.getItem(getPaginationKey(containerId));
            const page = parseInt(storedPage, 10);
            return (page > 0) ? page : 1;
        } catch (e) {
            return 1;
        }
    }

    // ----------------------------
    // AJAX form submission logic
    // ----------------------------
    /**
     * Submits a form using AJAX and returns the parsed JSON response data.
     * @param {HTMLFormElement} form - The form element.
     * @returns {Promise<object>} The parsed JSON data from the successful response.
     * @throws {Error} Throws an error if the submission fails (data.success !== 1).
     */
    async function submitFormLogic(form) {
        const url = form.getAttribute('action');
        const container = document.querySelector('[data-form="' + form.id + '"]');
        let sendAsJson = false;
        if (container) {
            sendAsJson = container.dataset.json === '1';
        }

        let options = {method: 'POST'};
        if (sendAsJson) {
            const formData = Object.fromEntries(new FormData(form).entries());
            options.body = JSON.stringify(formData);
            options.headers = {'Content-Type': 'application/json'};
        } else {
            options.body = new FormData(form);
        }

        // fetchAndLog returns the parsed JSON data directly on success
        const data = await fetchAndLog(url, options);
        if (data.success !== 1) {
            // Error handling for business logic failure (e.g., validation errors)
            const errorMsg = Object.entries(data.errorMessage || {})
                    .map(([k, v]) => k + ' ' + v)
                    .join('<br />');
            throw new Error(errorMsg || '<?= _("Unknown error") ?>');
        }
        return data;
    }

    // ----------------------------
    // Setup AJAX form (single submit with progress)
    // ----------------------------
    /**
     * Attaches event listeners to a form to handle AJAX submission, progress polling, and post-submission actions.
     * @param {string} formId - The ID of the form element.
     * @param {string} listUrl - URL to reload the list after submission.
     * @param {string} listContainerId - The ID of the container element for the reloaded list.
     */
    function setupAjaxForm(formId, listUrl, listContainerId) {
        const form = document.getElementById(formId);
        if (!form)
            return;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const progressId = form.dataset.progressId;
            const progressUrl = form.dataset.progressUrl;
            const isLongRunningJob = !!progressId && !!progressUrl;
            const submitButton = form.querySelector('button[type="submit"]');
            let pollingInterval = null;
            let originalText = submitButton ? submitButton.textContent : null;
            try {
                // Start polling for long-running job
                if (isLongRunningJob) {
                    pollingInterval = startProgressPolling(progressId, progressUrl, submitButton, originalText);
                }

                await submitFormLogic(form);
                // Stop polling
                stopProgressPolling(pollingInterval);
                const redirectUrl = form.dataset.redirect;
                const successMessage = form.dataset.message || '<?= _("Operation successful.") ?>';
                if (redirectUrl) {
                    sendMessageAndRedirect('success', '<?= _("Complete") ?>', successMessage, [], '<?= BASE_URI ?>' + redirectUrl)
                } else {
                    form.reset();
                    if (listUrl && listContainerId) {
                        await loadList(listUrl, listContainerId);
                    }
                    displayMessage('success', '<?= _("Complete") ?>', successMessage);
                }
            } catch (error) {
                stopProgressPolling(pollingInterval);
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }

                const statusEl = document.getElementById('status');
                if (statusEl) {
                    statusEl.innerHTML = error.message;
                    statusEl.style.display = 'block';
                }
                console.error('Error submitting the form:', error);
                displayMessage('error', '<?= _("Error") ?>', error.message || '<?= _("Unknown error occurred.") ?>', true);
            }
        });
    }

    // ----------------------------
    // Load list via AJAX
    // ----------------------------
    /**
     * Loads HTML content from a URL via AJAX and injects it into a container.
     * @param {string} url - The URL to fetch the list content from.
     * @param {string} containerId - The ID of the container to update.
     */
    async function loadList(url, containerId) {
        if (!url || !containerId)
            return;
        try {
            const html = await fetchAndLog(url);
            if (typeof html !== 'string') {
                console.error('List URL did not return expected HTML fragment.', html);
                return;
            }

            const container = document.getElementById(containerId);
            if (!container)
                return;
            container.innerHTML = html;
            // Re-setup pagination for the newly loaded content
            setupPagination(container);
        } catch (err) {
            console.error('Error loading the list:', err);
        }
    }

    // ----------------------------
    // Setup pagination for table or generic list
    // ----------------------------
    /**
     * Applies client-side pagination to a content container based on its data-per-page attribute.
     * Handles state persistence using localStorage if the container has an ID.
     * @param {HTMLElement} container - The container element (e.g., a div wrapper for a table or grid).
     */
    function setupPagination(container) {
        if (!container)
            return;
        const containerId = container.id;
        const hasPersistenceId = !!containerId;
        const table = container.querySelector('table');
        let paginatedContent, items = [], totalItems = 0;
        if (table) {
            paginatedContent = table;
            items = Array.from(table.rows).slice(1);
            totalItems = items.length;
        } else {
            paginatedContent = container.querySelector('.list-cards') ||
                    container.querySelector('.image-grid') ||
                    container;
            items = Array.from(paginatedContent.children).filter(el =>
                el.nodeType === 1 &&
                        el.tagName !== 'SCRIPT' &&
                        !el.classList.contains('pagination')
            );
            totalItems = items.length;
        }

        if (totalItems <= 0)
            return;
        const rowsPerPage = parseInt(container.dataset.perPage, 10) || 15;
        let currentPage = 1; // Default starting page is always 1

        if (hasPersistenceId) {
            currentPage = loadPageFromLocalStorage(containerId);
        }

        const totalPages = Math.ceil(totalItems / rowsPerPage);
        if (currentPage > totalPages) {
            currentPage = 1;
            if (hasPersistenceId) {
                storePageInLocalStorage(containerId, currentPage);
            }
        }

        const oldPagination = container.querySelector('.pagination');
        if (oldPagination)
            oldPagination.remove();
        const pagination = document.createElement('div');
        pagination.className = 'pagination';
        pagination.style.marginTop = '10px';
        pagination.style.marginBottom = '20px';
        pagination.style.textAlign = 'left';
        const prevButton = document.createElement('button');
        prevButton.textContent = '<?= _("Previous") ?>';
        prevButton.className = 'button small-action pagination-nav';
        prevButton.style.marginRight = '8px';
        const nextButton = document.createElement('button');
        nextButton.textContent = '<?= _("Next") ?>';
        nextButton.className = 'button small-action pagination-nav';
        const pageStatus = document.createElement('span');
        pageStatus.style.marginLeft = '10px';
        pageStatus.style.fontWeight = 'bold';
        // Use a local function to handle navigation and (optionally) storage
        function navigateAndStore(pageChange) {
            showPage(currentPage + pageChange);
            if (hasPersistenceId) {
                storePageInLocalStorage(containerId, currentPage);
            }
        }

        prevButton.onclick = () => navigateAndStore(-1);
        nextButton.onclick = () => navigateAndStore(1);
        pagination.appendChild(prevButton);
        pagination.appendChild(nextButton);
        pagination.appendChild(pageStatus);
        paginatedContent.insertAdjacentElement('afterend', pagination);
        function showPage(page) {
            if (page < 1)
                page = 1;
            if (page > totalPages)
                page = totalPages;
            currentPage = page;
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const displayValue = table ? 'table-row' : '';
            items.forEach((item, index) => {
                item.style.display = (index >= start && index < end) ? displayValue : 'none';
            });
            pageStatus.textContent = '<?= _("Page") ?> ' + currentPage + ' <?= _("of") ?> ' + totalPages;
            prevButton.disabled = currentPage === 1;
            nextButton.disabled = currentPage === totalPages;
        }

        showPage(currentPage);
    }

    // ----------------------------
    // Sequential save for multiple forms
    // ----------------------------
    /**
     * Submits multiple forms sequentially using AJAX.
     * @param {string[]} formIds - An array of form IDs to submit in order.
     * @param {HTMLElement} triggerButton - The button that initiated the save.
     */
    async function saveSequentially(formIds, triggerButton) {
        const forms = formIds.map(id => document.getElementById(id)).filter(f => f);
        if (forms.length !== formIds.length) {
            console.error('Not all forms found for sequential save:', formIds);
            return;
        }

        triggerButton.disabled = true;
        const originalText = triggerButton.textContent;
        triggerButton.textContent = '<?= _("Saving...") ?>';
        displayMessage('info', '<?= _("Save Process") ?>', '<?= _("Saving sequence initiated...") ?>');
        try {
            for (let i = 0; i < forms.length; i++) {
                const form = forms[i];
                // Submit the form using the core logic
                await submitFormLogic(form);
                displayMessage('success', '<?= _("Step") ?> ' + (i + 1) + '/' + forms.length,
                        '<?= _("Data for") ?> "' + form.id + '" <?= _("saved successfully.") ?>');
            }

            const redirectUrl = forms[0].dataset.redirect;
            const successMessage = forms[0].dataset.message || '<?= _("All changes saved!") ?>';
            if (redirectUrl) {
                sendMessageAndRedirect('success', '<?= _("Complete") ?>', successMessage, [], '<?= BASE_URI ?>' + redirectUrl);
            } else {
                displayMessage('success', '<?= _("Complete") ?>', successMessage);
                document.querySelectorAll('.paginated').forEach(c => setupPagination(c));
            }
        } catch (error) {
            console.error('Save failed:', error);
            displayMessage('error', '<?= _("Save Failed!") ?>', '<?= _("Process aborted:") ?> ' + error.message);
        } finally {
            triggerButton.disabled = false;
            if (triggerButton.textContent === '<?= _("Saving...") ?>')
                triggerButton.textContent = originalText;
        }
    }

    // ----------------------------
    // Initialize sequential save buttons
    // ----------------------------
    /**
     * Finds all buttons marked for sequential saving and attaches the click handler.
     */
    function initSequentialSave() {
        document.querySelectorAll('[data-forms-to-save]').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const formIds = button.dataset.formsToSave.split(',').map(id => id.trim());
                saveSequentially(formIds, button);
            });
        });
    }

    // ----------------------------
    // Global initialization
    // ----------------------------

    // Setup AJAX forms based on data-form attribute
    document.querySelectorAll('[data-form]').forEach(container => {
        const formId = container.dataset.form;
        const listUrl = container.dataset.url;
        const listContainer = listUrl ? document.querySelector('[data-list="' + listUrl + '"]') : null;
        const listContainerId = listContainer ? listContainer.id : null;
        if (document.getElementById(formId)) {
            setupAjaxForm(formId, listUrl, listContainerId);
        }
    });
    document.querySelectorAll('[data-list]').forEach(container => {
        const listUrl = container.dataset.list;
        const containerId = container.id;
        loadList(listUrl, containerId);
    });
    initSequentialSave();
    document.querySelectorAll('.paginated').forEach(container => {
        setupPagination(container);
    });
});

