<div align="center">
    <fieldset>
        <form action="login/submit" method="post" id="loginForm">
            <div class="form-row">
                <label for="email">Email:</label>
                <input type="text" id="email" name="email">
            </div>
            <div class="form-row">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password">
            </div>
            <div class="form-row">
                <label for="submit">&nbsp;</label>
                <input type="submit" value="Login">
            </div>
        </form>
    </fieldset>
</div>

<style>
    #loginForm {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .form-row {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
    }

    label {
        width: 100px;
        text-align: right;
        margin-right: 10px;
    }

    input[type="text"],
    input[type="password"],
    input[type="submit"] {
        width: 200px;
    }
</style>

<br />

<script>
    window.onload = function () {
        document.querySelector("#loginForm").addEventListener("submit", function (e) {
            e.preventDefault();

            var url = this.action;
            var postData = new FormData(this);

            var xhr = new XMLHttpRequest();
            xhr.open("POST", url);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success === 1) {
                        displayMessage("success", <?= json_encode(_("Login")) ?>, <?= json_encode(_("Login successful")) ?>);
                        window.location.href = 'dashboard';
                    } else {
                        var status = '';
                        for (var key in response.errorMessage) {
                            if (response.errorMessage.hasOwnProperty(key)) {
                                status += key + ' ' + response.errorMessage[key] + '<br />';
                            }
                        }
                        displayMessage("error", <?= json_encode(_("Login")) ?>, status);
                        document.querySelector("#status").innerHTML = status;
                        document.querySelector("#status").style.display = "block";
                    }
                } else {
                    displayMessage("error", <?= json_encode(_("Login")) ?>, <?= json_encode(_("Server error! Status: ")) ?> + xhr.status);
                }
            };
            xhr.send(postData);
        });
    };</script>