document.addEventListener('DOMContentLoaded', function () {

    var menuForm = document.getElementById('menuForm');
    menuForm.addEventListener('submit', function (e) {
        e.preventDefault();

        var url = this.getAttribute('action');
        var postData = new FormData(this);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.onload = function () {
            if (xhr.status === 200) {
                var o = JSON.parse(xhr.responseText);
                if (o.success === 1) {
                    window.location.href = BASE_URI + 'menu';
                } else {
                    var status = '';
                    for (var key in o.errorMessage) {
                        if (o.errorMessage.hasOwnProperty(key)) {
                            status += key + ' ' + o.errorMessage[key] + '<br />';
                        }
                    }
                    var statusEl = document.getElementById('status');
                    statusEl.innerHTML = status;
                    statusEl.style.display = 'block';
                }
            }
        };
        xhr.send(postData);
    });

    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var data = xhr.responseText;
            var menulist = document.getElementById('menulist');
            menulist.innerHTML = data;
            showPagination();
        }

    };
    xhr.open('GET', 'menu/menuList', true);
    xhr.send();


    function showPagination()
    {
        var table = document.getElementById('menu-table');
        var rowsPerPage = 15;
        var currentPage = 1;
        var totalPages = Math.ceil(table.rows.length / rowsPerPage);
        var pagination = document.getElementById('pagination');

        var prevButton = pagination.getElementsByTagName('button')[0];
        var nextButton = pagination.getElementsByTagName('button')[1];

        prevButton.addEventListener('click', prevPage);
        nextButton.addEventListener('click', nextPage);

        function showPage(page) {
            var startRow = (page - 1) * rowsPerPage;
            var endRow = startRow + rowsPerPage;

            for (var i = 0; i < table.rows.length; i++) {
                if (i < startRow || i >= endRow) {
                    table.rows[i].style.display = 'none';
                } else {
                    table.rows[i].style.display = '';
                }
            }
        }

        function nextPage() {
            if (currentPage < totalPages) {
                currentPage++;
                showPage(currentPage);
            }
        }

        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                showPage(currentPage);
            }
        }

        showPage(1);

    }
});