<div align="center">
    <fieldset>
        <form action="login/submit" method="post" id="loginForm">
            <div class="form-row">
                <label for="email"><?= _("Email:") ?></label>
                <input type="email" id="email" name="email" autocomplete="username">
            </div>
            <div class="form-row">
                <label for="password"><?= _("Password:") ?></label>
                <input type="password" id="password" name="password" autocomplete="current-password">
            </div>
            <div class="form-row">
                <label for="submit">&nbsp;</label>
                <input class="button small-action save" type="submit" value="<?= _("Login") ?>">
            </div>
        </form>
    </fieldset>
</div>

<style>
    /* CSS for form styling */
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

    input[type="email"],
    input[type="password"],
    input[type="submit"] {
        width: 200px;
    }
</style>

<br />

<script>
    // JavaScript for AJAX form submission
    window.onload = function () {
        document.querySelector("#loginForm").addEventListener("submit", function (e) {
            e.preventDefault();

            var url = this.action;
            var postData = new FormData(this);

            var xhr = new XMLHttpRequest();
            xhr.open("POST", url);

            // Set expected response header if necessary (e.g., application/json)
            // xhr.setRequestHeader("Accept", "application/json");

            xhr.onload = function () {
                // Check for successful HTTP status code
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);

                    // Check for application-level success flag (response.success === 1)
                    if (response.success === 1) {
                        // Display success message using translated strings
                        displayMessage("success", <?= json_encode(_("Login")) ?>, <?= json_encode(_("Login successful")) ?>);

                        // Redirect to the dashboard on success
                        window.location.href = 'dashboard';
                    } else {
                        // Handle application-level error
                        var status = '';
                        // Loop through error messages
                        for (var key in response.errorMessage) {
                            if (response.errorMessage.hasOwnProperty(key)) {
                                // Concatenate error messages (keys often contain field names, values contain validation errors)
                                status += key + ' ' + response.errorMessage[key] + '<br />';
                            }
                        }

                        // Display error message using translated strings
                        displayMessage("error", <?= json_encode(_("Login")) ?>, status);

                        // Update a status element on the page (assuming an element with id="status" exists)
                        document.querySelector("#status").innerHTML = status;
                        document.querySelector("#status").style.display = "block";
                    }
                } else {
                    // Handle HTTP/Server error
                    displayMessage("error", <?= json_encode(_("Login")) ?>, <?= json_encode(_("Server error! Status: ")) ?> + xhr.status);
                }
            };
            // Send the form data
            xhr.send(postData);
        });
    };
</script>