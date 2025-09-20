document.addEventListener('DOMContentLoaded', function () {
    var editForm = document.getElementById('editForm');
    editForm.addEventListener('submit', function (e) {
        e.preventDefault();

        var url = this.getAttribute('action');
        var formData = new FormData(this);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.onload = function () {
            if (xhr.status === 200) {
                var o = JSON.parse(xhr.responseText);
                if (o.success === 1) {
                    window.location.href = BASE_URI + 'user';
                } else {
                    var status = '';
                    alert(o.errorMessage);
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
        xhr.send(formData);
    });
});