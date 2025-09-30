document.addEventListener('DOMContentLoaded', () => {

    // --- CORE LOGIC ---
    // Handles the actual AJAX submission for a single form (reusable by all handlers).
    async function submitFormLogic(form) {
        const url = form.getAttribute('action');
        const container = document.querySelector(`[data-form="${form.id}"]`);

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
            const errorMsg = Object.entries(data.errorMessage)
                    .map(([k, v]) => `${k} ${v}`)
                    .join('<br />');
            throw new Error(errorMsg);
        }
        return data;
    }

    // ----------------------------
    // 1. Setup AJAX Form (for standard single submits)
    // ----------------------------
    function setupAjaxForm(formId, listUrl, listContainerId) {
        const form = document.getElementById(formId);
        if (!form)
            return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            try {
                await submitFormLogic(form);

                const redirectUrl = form.dataset.redirect;
                if (redirectUrl) {
                    displayMessage("success", "Edit", "Modification was successful");
                    setTimeout(() => window.location.href = BASE_URI + redirectUrl, 2000);
                } else {
                    form.reset();
                    await loadList(listUrl, listContainerId);
                }
            } catch (error) {
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
    // 2. Load List (Unchanged)
    // ----------------------------
    async function loadList(url, containerId) {
        try {
            const resp = await fetch(url);
            const html = await resp.text();
            const container = document.getElementById(containerId);
            container.innerHTML = html;
            setupPagination(container);
        } catch (err) {
            console.error('Error loading the list:', err);
        }
    }

    // ----------------------------
    // 3. Setup Pagination (COMPLETE CODE!)
    // ----------------------------
    function setupPagination(container) {
        const table = container.querySelector('table');
        if (!table)
            return;

        const rowsPerPage = 15;
        let currentPage = 1;
        const totalPages = Math.ceil((table.rows.length - 1) / rowsPerPage);

        const oldPagination = container.querySelector('.pagination');
        if (oldPagination)
            oldPagination.remove();

        const pagination = document.createElement('div');
        pagination.className = 'pagination';

        // --- MISSING CODE BLOCK REINSERTED ---
        pagination.style.marginTop = '10px';
        pagination.style.textAlign = 'left';

        const prevButton = document.createElement('button');
        prevButton.textContent = 'Previous';
        prevButton.style.minWidth = '100px';
        prevButton.style.padding = '6px 12px';
        prevButton.style.marginRight = '8px';

        const nextButton = document.createElement('button');
        nextButton.textContent = 'Next';
        nextButton.style.minWidth = '100px';
        nextButton.style.padding = '6px 12px';

        const pageStatus = document.createElement('span');
        pageStatus.style.marginLeft = '10px';
        pageStatus.style.fontWeight = 'bold';

        prevButton.onclick = () => showPage(currentPage - 1);
        nextButton.onclick = () => showPage(currentPage + 1);

        pagination.appendChild(prevButton);
        pagination.appendChild(nextButton);
        pagination.appendChild(pageStatus);

        table.insertAdjacentElement('afterend', pagination);

        function showPage(page) {
            if (page < 1)
                page = 1;
            if (page > totalPages)
                page = totalPages;
            currentPage = page;

            const start = (page - 1) * rowsPerPage + 1;
            const end = start + rowsPerPage;

            for (let i = 0; i < table.rows.length; i++) {
                table.rows[i].style.display = (i === 0 || (i >= start && i < end)) ? '' : 'none';
            }

            pageStatus.textContent = `Page ${currentPage} of ${totalPages}`; // English output
            prevButton.disabled = currentPage === 1;
            nextButton.disabled = currentPage === totalPages;
        }

        showPage(1);
    }

    // ----------------------------
    // 4. New Sequential Save Logic
    // ----------------------------
    async function saveSequentially(formIds, triggerButton) {
        const forms = formIds.map(id => document.getElementById(id)).filter(f => f);
        if (forms.length !== formIds.length) {
            console.error('Configuration error: Not all forms found for sequential save.', formIds);
            return;
        }

        triggerButton.disabled = true;
        const originalText = triggerButton.textContent;
        triggerButton.textContent = 'Saving...';
        displayMessage('info', 'Save Process', 'Saving sequence initiated...');

        try {
            for (let i = 0; i < forms.length; i++) {
                const form = forms[i];
                await submitFormLogic(form);
                displayMessage('success', `Step ${i + 1}/${forms.length}`, `Data for '${form.id}' saved successfully.`);
            }

            const redirectUrl = forms[0].dataset.redirect;
            if (redirectUrl) {
                displayMessage('success', 'Complete', 'All changes saved! Redirecting...');
                setTimeout(() => window.location.href = BASE_URI + redirectUrl, 1500);
            } else {
                displayMessage('success', 'Complete', 'All changes saved!');

                // IMPORTANT: If the list is NOT reloaded (no redirect), 
                // the pagination must be manually re-applied to ensure it works after the save.
                // We re-run the pagination setup for ALL static paginated containers.
                document.querySelectorAll('.paginated').forEach(container => {
                    setupPagination(container);
                });
            }

        } catch (error) {
            console.error('Save failed:', error);
            displayMessage('error', 'Save Failed!', `Process aborted: ${error.message}`);
            triggerButton.textContent = 'Save Failed';

            const statusEl = document.getElementById('status');
            if (statusEl) {
                statusEl.innerHTML = `Error: ${error.message}`;
                statusEl.style.display = 'block';
            }
        } finally {
            triggerButton.disabled = false;
            if (triggerButton.textContent === 'Saving...') {
                triggerButton.textContent = originalText;
            }
        }
    }

    // 5. Initialization for sequential saving buttons
    function initSequentialSave() {
        document.querySelectorAll('[data-forms-to-save]').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const formIds = button.dataset.formsToSave.split(',').map(id => id.trim());
                saveSequentially(formIds, button);
            });
        });
    }

    // --- GLOBAL INITIALIZATION ---

// Formulare
    document.querySelectorAll('[data-form]').forEach(container => {
        const formId = container.dataset.form;
        const listUrl = container.dataset.url; // <-- Die URL, die die Liste neu laden muss
        const containerId = container.id;

        const listContainer = document.querySelector(`[data-list="${listUrl}"]`);

        const listContainerId = listContainer ? listContainer.id : null;

        if (document.getElementById(formId)) {
            setupAjaxForm(formId, listUrl, listContainerId); // Wenn Sie listContainerId nicht übergeben
        }
    });

// Listen
    document.querySelectorAll('[data-list]').forEach(container => {
        const listUrl = container.dataset.list;
        const containerId = container.id;
        loadList(listUrl, containerId);
    });

    initSequentialSave();

    // Initialize pagination for static tables (Runs once on page load)
    document.querySelectorAll('.paginated').forEach(container => {
        setupPagination(container);
    });
});