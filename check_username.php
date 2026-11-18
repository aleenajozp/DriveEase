<?php
$conn = new mysqli("localhost", "root", "", "car_rental");

if ($conn->connect_error) {
    die("DB Connection Failed");
}

if (isset($_POST['username'])) {
    $username = $_POST['username'];
    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "taken", "message" => " Username already taken"]);
    } else {
        echo json_encode(["status" => "available", "message" => " Username available"]);
    }
}
$conn->close();
?>
