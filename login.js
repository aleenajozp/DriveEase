const usernameField = document.getElementById("username");
const statusSpan = document.getElementById("usernameStatus");

usernameField.addEventListener("keyup", function() {
    const username = usernameField.value.trim();
    if (username.length < 3) {
        statusSpan.style.color = "red";
        statusSpan.textContent = "⚠️ Minimum 3 characters";
        return;
    }

    // Send AJAX request
    fetch("check_username.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "username=" + encodeURIComponent(username)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "taken") {
            statusSpan.style.color = "red";
        } else {
            statusSpan.style.color = "white";
        }
        statusSpan.textContent = data.message;
    })
    .catch(err => {
        console.error("Error:", err);
    });
});
