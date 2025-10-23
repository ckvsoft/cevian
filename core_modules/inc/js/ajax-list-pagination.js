// IMPORTANT: This file MUST be processed by the PHP interpreter BEFORE it is served to the browser.

document.addEventListener('DOMContentLoaded', () => {

    // ----------------------------
    // Progress Polling
    // ----------------------------
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
                const resp = await fetch(BASE_URI + progressUrl + progressId);
                const data = await resp.json();

                if (data && data.data.percent !== undefined) {
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

    function stopProgressPolling(pollingInterval) {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    }

    // ----------------------------
    // AJAX form submission logic
    // ----------------------------
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

        const resp = await fetch(url, options);
        const data = await resp.json();

        if (data.success !== 1) {
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
                if (redirectUrl) {
                    displayMessage('success', '<?= _("Complete") ?>', '<?= _("Operation successful. Redirecting...") ?>', true);
                    setTimeout(() => window.location.href = BASE_URI + redirectUrl, 1500);
                } else {
                    form.reset();
                    if (listUrl && listContainerId) {
                        await loadList(listUrl, listContainerId);
                    }
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
            }
        });
    }

    // ----------------------------
    // Load list via AJAX
    // ----------------------------
    async function loadList(url, containerId) {
        if (!url || !containerId)
            return;

        try {
            const resp = await fetch(url);
            const html = await resp.text();
            const container = document.getElementById(containerId);
            if (!container)
                return;

            container.innerHTML = html;
            setupPagination(container);
        } catch (err) {
            console.error('Error loading the list:', err);
        }
    }

    // ----------------------------
    // Setup pagination for table or generic list
    // ----------------------------
    function setupPagination(container) {
        if (!container)
            return;

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
        let currentPage = 1;
        const totalPages = Math.ceil(totalItems / rowsPerPage);

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

        prevButton.onclick = () => showPage(currentPage - 1);
        nextButton.onclick = () => showPage(currentPage + 1);

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

        showPage(1);
    }

    // ----------------------------
    // Sequential save for multiple forms
    // ----------------------------
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
                await submitFormLogic(form);
                displayMessage('success', '<?= _("Step") ?> ' + (i + 1) + '/' + forms.length,
                    '<?= _("Data for") ?> "' + form.id + '" <?= _("saved successfully.") ?>');
            }

            const redirectUrl = forms[0].dataset.redirect;
            if (redirectUrl) {
                displayMessage('success', '<?= _("Complete") ?>', '<?= _("All changes saved! Redirecting...") ?>');
                setTimeout(() => window.location.href = BASE_URI + redirectUrl, 1500);
            } else {
                displayMessage('success', '<?= _("Complete") ?>', '<?= _("All changes saved!") ?>');
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
    document.querySelectorAll('[data-form]').forEach(container => {
        const formId = container.dataset.form;
        const listUrl = container.dataset.url;
        const containerId = container.id;
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
