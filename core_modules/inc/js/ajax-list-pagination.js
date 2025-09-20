document.addEventListener('DOMContentLoaded', () => {

    // ----------------------------
    // AJAX-Formular einrichten
    // ----------------------------
    function setupAjaxForm(formId, listUrl, listContainerId) {
        const form = document.getElementById(formId);
        if (!form)
            return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const url = form.getAttribute('action');
            const formData = new FormData(form);

            try {
                const resp = await fetch(url, {method: 'POST', body: formData});
                const data = await resp.json();

                if (data.success === 1) {
                    await loadList(listUrl, listContainerId);
                } else {
                    const statusEl = document.getElementById('status');
                    if (statusEl) {
                        statusEl.innerHTML = Object.entries(data.errorMessage)
                                .map(([k, v]) => `${k} ${v}`)
                                .join('<br />');
                        statusEl.style.display = 'block';
                    }
                }
            } catch (err) {
                console.error('Error submitting the form:', err);
            }
        });
    }

    // ----------------------------
    // AJAX-Liste laden
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
    // Pagination für Tabellen
    // ----------------------------
    function setupPagination(container) {
        const table = container.querySelector('table');
        if (!table)
            return;

        const rowsPerPage = 15;
        let currentPage = 1;
        const totalPages = Math.ceil((table.rows.length - 1) / rowsPerPage); // -1 wegen Header

        // Alte Pagination entfernen, falls vorhanden
        const oldPagination = container.querySelector('.pagination');
        if (oldPagination)
            oldPagination.remove();

        // Pagination-Container erstellen
        const pagination = document.createElement('div');
        pagination.className = 'pagination';
        pagination.style.marginTop = '10px';
        pagination.style.textAlign = 'left';

        // Prev/Next Buttons
        const prevButton = document.createElement('button');
        prevButton.textContent = 'Previous';
        prevButton.style.minWidth = '100px';
        prevButton.style.padding = '6px 12px';
        prevButton.style.marginRight = '8px';

        const nextButton = document.createElement('button');
        nextButton.textContent = 'Next';
        nextButton.style.minWidth = '100px';
        nextButton.style.padding = '6px 12px';

        // Status-Element
        const pageStatus = document.createElement('span');
        pageStatus.style.marginLeft = '10px';
        pageStatus.style.fontWeight = 'bold';

        // Button Events
        prevButton.onclick = () => showPage(currentPage - 1);
        nextButton.onclick = () => showPage(currentPage + 1);

        // Pagination zusammensetzen
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

            const start = (page - 1) * rowsPerPage + 1; // +1 wegen Header
            const end = start + rowsPerPage;

            for (let i = 0; i < table.rows.length; i++) {
                table.rows[i].style.display = (i === 0 || (i >= start && i < end)) ? '' : 'none';
            }

            // Status aktualisieren
            pageStatus.textContent = `Seite ${currentPage} von ${totalPages}`;

            // Buttons deaktivieren, falls nötig
            prevButton.disabled = currentPage === 1;
            nextButton.disabled = currentPage === totalPages;
        }

        showPage(1);
    }

    // ----------------------------
    // Alle AJAX-Listen initialisieren
    // ----------------------------
    document.querySelectorAll('.ajax-list').forEach(container => {
        const formId = container.dataset.form;
        const listUrl = container.dataset.url;
        const containerId = container.id;

        setupAjaxForm(formId, listUrl, containerId);
        loadList(listUrl, containerId);
    });

    // ----------------------------
    // Alle statischen Tabellen mit Pagination initialisieren
    // ----------------------------
    document.querySelectorAll('.paginated').forEach(container => {
        setupPagination(container);
    });

});
