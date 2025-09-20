document.addEventListener("DOMContentLoaded", function () {

    document.querySelector("#editForm").addEventListener("submit", function (e) {
        e.preventDefault();

        var url = this.getAttribute('action');
        var postData = new FormData(this);

        var xhr = new XMLHttpRequest();
        xhr.open("POST", url, true);

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
                    document.querySelector("#status").innerHTML = status;
                    document.querySelector("#status").style.display = "block";
                }
            } else {
                console.log(xhr.status);
            }
        };

        xhr.send(postData);
    });
});
