<?php
// Start session and check authentication
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "car_rental");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $fuel_type = $_POST['fuel_type'];
    $seating_capacity = $_POST['seating_capacity'];
    $transmission = $_POST['transmission'];
    $price = $_POST['price'];
    $image = '';
    
    // Validate required fields
    if (empty($title) || empty($fuel_type) || empty($seating_capacity) || empty($transmission) || empty($price)) {
        $error_message = "All required fields must be filled.";
    } else {
        // Handle image upload or URL
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            // Handle file upload
            $target_dir = "uploads/cars/";
            $file_extension = strtolower(pathinfo($_FILES["image_file"]["name"], PATHINFO_EXTENSION));
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // Check if image file is actual image
            $check = getimagesize($_FILES["image_file"]["tmp_name"]);
            if($check !== false) {
                // Check file size (5MB max)
                if ($_FILES["image_file"]["size"] <= 5000000) {
                    // Allow certain file formats
                    if($file_extension == "jpg" || $file_extension == "png" || $file_extension == "jpeg" || $file_extension == "gif") {
                        if (!file_exists($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $target_file)) {
                            $image = $target_file;
                        } else {
                            $error_message = "Error uploading file.";
                        }
                    } else {
                        $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
                    }
                } else {
                    $error_message = "File is too large. Maximum size is 5MB.";
                }
            }