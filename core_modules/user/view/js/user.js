document.addEventListener('DOMContentLoaded', function () {
    var userForm = document.getElementById('userForm');
    userForm.addEventListener('submit', function (e) {
        e.preventDefault();

        var url = this.getAttribute('action');
        var postData = new FormData(this);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.onload = function () {
            if (xhr.status === 200) {
                var o = JSON.parse(xhr.responseText);
                if (o.success === 1) {
                    window.location.href = BASE_URI + 'user';
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

    var userListEl = document.getElementById('userlist');
    var xhr2 = new XMLHttpRequest();
    xhr2.open('GET', 'user/userList');
    xhr2.onload = function () {
        if (xhr2.status === 200) {
            userListEl.innerHTML = xhr2.responseText;
        }
    };
    xhr2.send();
});
