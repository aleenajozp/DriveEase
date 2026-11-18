<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$dbname = "car_rental";
$dbuser = "root";
$dbpass = "";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $user_id = $user_data['user_id'];
} else {
    echo "User not found.";
    exit();
}

// AUTO-COMPLETE RENTALS: Update booking status to 'Completed' for past rentals
$auto_complete_query = "UPDATE bookings 
                       SET booking_status = 'Completed' 
                       WHERE user_id = ? 
                       AND booking_status IN ('Confirmed', 'Pending') 
                       AND return_date < CURDATE()";

$auto_complete_stmt = $conn->prepare($auto_complete_query);
$auto_complete_stmt->bind_param("i", $user_id);
$auto_complete_stmt->execute();

// Also auto-complete for rentals where return_date + return_time has passed
$auto_complete_datetime_query = "UPDATE bookings 
                                SET booking_status = 'Completed' 
                                WHERE user_id = ? 
                                AND booking_status IN ('Confirmed', 'Pending') 
                                AND CONCAT(return_date, ' ', return_time) < NOW()";

$auto_complete_datetime_stmt = $conn->prepare($auto_complete_datetime_query);
$auto_complete_datetime_stmt->bind_param("i", $user_id);
$auto_complete_datetime_stmt->execute();

// Fetch rentals with updated column names
$rental_query = "SELECT b.*, c.car_id, c.title, c.image, c.fuel_type 
                FROM bookings b 
                LEFT JOIN cars c ON b.car_id = c.car_id 
                WHERE b.user_id = ? 
                ORDER BY b.pickup_date DESC";

$rental_stmt = $conn->prepare($rental_query);
$rental_stmt->bind_param("i", $user_id);
$rental_stmt->execute();
$rental_result = $rental_stmt->get_result();

$rentals = [];
while ($row = $rental_result->fetch_assoc()) {
    $current_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s');
    $pickup_date = $row['pickup_date'];
    $return_date = $row['return_date'];
    $pickup_datetime = $pickup_date . ' ' . $row['pickup_time'];
    $return_datetime = $return_date . ' ' . $row['return_time'];
    
    // Determine status based on dates and booking_status
    $date_status = '';
    if ($current_datetime < $pickup_datetime) {
        $date_status = 'upcoming';
    } elseif ($current_datetime >= $pickup_datetime && $current_datetime <= $return_datetime) {
        $date_status = 'active';
    } else {
        $date_status = 'completed';
    }
    
    // Use booking_status from database (which should now be auto-updated)
    $display_status = strtolower($row['booking_status']);
    
    // Enhanced status logic with date-time consideration
    if ($display_status === 'approved') {
        if ($date_status === 'upcoming') {
            $display_status = 'approved'; // Upcoming confirmed booking
        } elseif ($date_status === 'active') {
            $display_status = 'in-progress'; // Currently active
        } elseif ($date_status === 'completed') {
            $display_status = 'completed'; // Should be completed based on dates
        }
    } elseif ($display_status === 'pending') {
        if ($date_status === 'completed') {
            $display_status = 'completed'; // Override pending if dates indicate completion
        } elseif ($date_status === 'active') {
            $display_status = 'in-progress'; // Currently active pending booking
        }
    }
    
    // Calculate rental days
    $pickup = new DateTime($pickup_date);
    $return = new DateTime($return_date);
    $rental_days = $pickup->diff($return)->days;
    if ($rental_days == 0) $rental_days = 1; // Minimum 1 day
    
    $rentals[] = [
        'booking_id' => $row['booking_id'],
        'car' => $row['car_id'] . ' ' . $row['title'],
        'image' => $row['image'] ?: 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
        'dates' => date('M d', strtotime($pickup_date)) . ' - ' . date('M d, Y', strtotime($return_date)),
        'times' => date('H:i', strtotime($row['pickup_time'])) . ' - ' . date('H:i', strtotime($row['return_time'])),
        'location' => $row['pickup_location'] . ' → ' . $row['return_location'],
        'status' => $display_status,
        'booking_status' => $row['booking_status'],
        'payment_status' => $row['payment_status'],
        'total_amount' => $row['total_amount'],
        'rental_days' => $rental_days,
        'driver_needed' => $row['driver_needed'],
        'special_requests' => $row['special_requests']
    ];
}

// Stats with updated queries - Fixed total rent calculation and completed status
$stats_query = "SELECT 
    COUNT(*) as total_rentals,
    SUM(CASE 
        WHEN booking_status = 'Completed' 
        THEN 1 
        ELSE 0 
    END) as completed_rentals,
    SUM(CASE 
        WHEN booking_status IN ('Pending', 'Confirmed','Approved') 
        AND return_date >= CURDATE() 
        THEN 1 
        ELSE 0 
    END) as active_rentals,
    SUM(CASE WHEN booking_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_rentals,
    SUM(total_amount) as total_spent,
    SUM(CASE 
        WHEN booking_status = 'Completed' 
        THEN total_amount 
        ELSE 0 
    END) as completed_amount
    FROM bookings WHERE user_id = ?";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats_row = $stats_result->fetch_assoc();

$stats = [
    'total_rentals' => $stats_row['total_rentals'],
    'completed_rentals' => $stats_row['completed_rentals'],
    'active_rentals' => $stats_row['active_rentals'],
    'cancelled_rentals' => $stats_row['cancelled_rentals'],
    'total_spent' => $stats_row['total_spent'], // Sum of ALL rental amounts
    'completed_amount' => $stats_row['completed_amount'] // Sum of only completed rental amounts
];

$message = "";
$tab = $_GET['tab'] ?? 'overview';

// Handle profile picture upload and profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $profile_updated = false;
    $new_profile_picture = $user_data['profile_picture'];

    // Handle profile picture upload
    if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] == 0) {
        $target_dir = "assets/images/profiles/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $allowed_types = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $filename = $_FILES["profile_picture"]["name"];
        $filetype = $_FILES["profile_picture"]["type"];
        $filesize = $_FILES["profile_picture"]["size"];
        
        // Get file extension
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed_types)) {
            $message = "Error: Please select a valid image format (JPG, JPEG, PNG, GIF).";
        } elseif (in_array($filetype, $allowed_types)) {
            if ($filesize > 5 * 1024 * 1024) { // 5MB limit
                $message = "Error: File size is larger than the allowed limit (5MB).";
            } else {
                // Generate unique filename
                $new_filename = "profile_" . $user_id . "_" . time() . "." . $ext;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                    // Delete old profile picture if it's not the default
                    if ($user_data['profile_picture'] && $user_data['profile_picture'] !== 'assets/images/default-profile.jpg') {
                        if (file_exists($user_data['profile_picture'])) {
                            unlink($user_data['profile_picture']);
                        }
                    }
                    $new_profile_picture = $target_file;
                    $profile_updated = true;
                } else {
                    $message = "Error uploading profile picture.";
                }
            }
        } else {
            $message = "Error: Invalid file type for profile picture.";
        }
    }

    // Update profile information if no image upload errors
    if (empty($message)) {
        // Check if username already exists (excluding current user)
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $check_stmt->bind_param("si", $new_username, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = "Username already exists. Please choose a different username.";
        } else {
            // Update user profile
            $update_stmt = $conn->prepare("UPDATE users SET username = ?, email_id = ?, phone = ?, profile_picture = ? WHERE user_id = ?");
            $update_stmt->bind_param("ssssi", $new_username, $new_email, $phone, $new_profile_picture, $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['username'] = $new_username; // Update session
                header("Location: ?tab=edit_profile&success=1");
                exit();
            } else {
                $message = "Error updating profile information.";
                // If database update failed and we uploaded a new image, remove it
                if ($profile_updated && $new_profile_picture !== $user_data['profile_picture']) {
                    unlink($new_profile_picture);
                }
            }
        }
    }
}

// Password change - UPDATED: Only update password field
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($db_password);
        $stmt->fetch();

        if ($current_password === $db_password) {
            if ($new_password === $confirm_password) {
                // Only update password field, not confirm_password
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
                $update_stmt->bind_param("ss", $new_password, $username);

                if ($update_stmt->execute()) {
                    header("Location: ?tab=change_password&success=1");
                    exit();
                } else {
                    $message = "Error updating password.";
                }
            } else {
                $message = "New passwords do not match.";
            }
        } else {
            $message = "Current password is incorrect.";
        }
    }
}

// Handle feedback submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    $rating = $_POST['rating'] ?? 0;
    $message_text = trim($_POST['message'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $message = "Please select a rating between 1 and 5 stars.";
    } elseif (empty($message_text)) {
        $message = "Please provide feedback message.";
    } else {
        // Create feedback table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS feedback (
            feedback_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )";
        
        if ($conn->query($create_table) === TRUE) {
            $insert_stmt = $conn->prepare("INSERT INTO feedback (user_id, rating, message) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("iis", $user_id, $rating, $message_text);
            
            if ($insert_stmt->execute()) {
                header("Location: ?tab=feedback&success=1");
                exit();
            } else {
                $message = "Error submitting feedback. Please try again.";
            }
        } else {
            $message = "Error creating feedback table: " . $conn->error;
        }
    }
}

if (isset($_GET['success'])) {
    if ($tab === 'change_password') {
        $message = "Password changed successfully!";
    } elseif ($tab === 'edit_profile') {
        $message = "Profile updated successfully!";
    } elseif ($tab === 'feedback') {
        $message = "Thank you for your feedback!";
    }
}

// Refresh user data after update
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
}

// Set default profile picture if none exists
$profile_picture = $user_data['profile_picture'] ?? 'assets/images/default-profile.jpg';
if (!file_exists($profile_picture)) {
    $profile_picture = 'assets/images/default-profile.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DriveEase - User Profile</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
    margin: 0;
    padding: 0;
    background: #fdf8f5;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #4e342e;
}
.container {
    display: flex;
    max-width: 1200px;
    margin: 30px auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(93, 64, 55, 0.1);
    overflow: hidden;
    opacity: 0;
    transform: translateY(20px);
    animation: fadeInUp 0.8s forwards;
}
@keyframes fadeInUp {
    to { opacity: 1; transform: translateY(0); }
}
.sidebar {
    width: 250px;
    background: linear-gradient(135deg, #a1887f, #6d4c41);
    color: white;
    padding: 30px 0;
}
.profile-info {
    text-align: center;
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    margin-bottom: 20px;
    opacity: 0;
    animation: fadeInLeft 0.8s forwards;
    animation-delay: 0.2s;
}
@keyframes fadeInLeft {
    to { opacity:1; transform: translateX(0);}
}
.profile-pic {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid white;
    margin-bottom: 15px;
    cursor: pointer;
    transition: transform 0.3s ease;
}
.profile-pic:hover {
    transform: scale(1.05);
}
.edit-profile-btn {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    cursor: pointer;
    margin-top: 8px;
    transition: background 0.3s;
}
.edit-profile-btn:hover {
    background: rgba(255,255,255,0.3);
}
.nav-links { list-style: none; padding:0 20px; }
.nav-links li { margin-bottom: 10px; }
.nav-links a {
    display: flex;
    align-items: center;
    color: white;
    text-decoration: none;
    padding: 12px 15px;
    border-radius: 5px;
    transition: background 0.3s;
    gap: 12px;
}
.nav-links a:hover, .nav-links a.active {
    background: rgba(255,255,255,0.2);
    font-weight:bold;
}
.main-content { flex:1; padding:30px; }
.stats { display:flex; flex-wrap:wrap; gap: 15px; }
.stat-card {
    display:inline-block;
    background:#fff5f0;
    padding:20px;
    border-radius:8px;
    border-top:4px solid #8d6e63;
    box-shadow:0 2px 10px rgba(93,64,55,0.05);
    text-align:center;
    opacity:0;
    transform:translateY(20px);
    animation: fadeInUp 0.8s forwards;
    min-width: 120px;
}
.stat-card:nth-child(1){animation-delay:0.3s;}
.stat-card:nth-child(2){animation-delay:0.4s;}
.stat-card:nth-child(3){animation-delay:0.5s;}
.stat-card:nth-child(4){animation-delay:0.6s;}
.account-section, .rentals-section {
    background:#fff;
    border-radius:8px;
    padding:25px;
    margin-top:30px;
    box-shadow:0 2px 10px rgba(93,64,55,0.05);
    opacity:0;
    transform:translateY(20px);
    animation: fadeInUp 0.8s forwards;
}
.account-section{animation-delay:0.5s;}
.rentals-section{animation-delay:0.6s;}
.rental-card {
    display:flex;
    align-items:center;
    padding:15px;
    border:1px solid #d7ccc8;
    border-radius:8px;
    margin-bottom:15px;
    background:#fafafa;
    opacity:0;
    transform:translateY(20px);
    animation: fadeInUp 0.8s forwards;
    position: relative;
}
.rental-card:nth-child(1){animation-delay:0.7s;}
.rental-card:nth-child(2){animation-delay:0.75s;}
.rental-card:nth-child(3){animation-delay:0.8s;}
.rental-img { width:100px; height:70px; object-fit:cover; border-radius:5px; margin-right:15px; }
.rental-details h3 { color:#6d4c41; margin: 0 0 10px 0; }
.rental-details p { color:#5d4037; margin: 5px 0; font-size: 14px; }
.rental-details .booking-id { 
    font-size: 12px; 
    color: #8d6e63; 
    font-weight: 600; 
    background: #f5f5f5; 
    padding: 2px 6px; 
    border-radius: 3px; 
    display: inline-block; 
    margin-bottom: 5px;
}
.status-badge {
    padding: 4px 8px; 
    border-radius: 12px; 
    font-size: 12px; 
    font-weight: 600; 
    text-transform: uppercase;
}
.status-badge.status-completed { background:#e8f5e8; color:#4caf50; }
.status-badge.status-confirmed, .status-badge.status-in-progress { background:#fff3e0; color:#ff9800; }
.status-badge.status-pending { background:#e3f2fd; color:#2196f3; }
.status-badge.status-cancelled { background:#ffebee; color:#f44336; }
.payment-status {
    font-size: 12px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 8px;
}
.payment-status.paid { background: #e8f5e8; color: #4caf50; }
.payment-status.pending { background: #fff3e0; color: #ff9800; }
.payment-status.failed { background: #ffebee; color: #f44336; }
.payment-status.refunded { background: #f3e5f5; color: #9c27b0; }
.rental-amount {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 18px;
    font-weight: 600;
    color: #6d4c41;
}
.special-info {
    background: #e8f4fd;
    padding: 8px 12px;
    border-radius: 6px;
    margin-top: 8px;
    border-left: 3px solid #2196f3;
}
.special-info.driver-needed {
    background: #fff3e0;
    border-left-color: #ff9800;
}
.btn {
    padding:12px 20px;
    border-radius:5px;
    font-weight:600;
    cursor:pointer;
    transition:background 0.3s;
    border:none;
    background:#795548;
    color:white;
    text-decoration:none;
    display:inline-block;
}
.btn:hover { background:#6d4c41; }
.notification {
    position:fixed;
    top:20px;
    right:20px;
    padding:15px 20px;
    background:#4caf50;
    color:white;
    border-radius:5px;
    z-index:1000;
}
.no-rentals { text-align:center; padding:40px; color:#999; }
.no-rentals i { font-size:48px; margin-bottom:20px; color:#d7ccc8; }

/* Edit Profile Form Styles */
.edit-profile-section {
    background:#fff;
    border-radius:8px;
    padding:30px;
    margin-top:30px;
    box-shadow:0 2px 10px rgba(93,64,55,0.05);
}

.profile-form {
    display: grid;
    gap: 20px;
    max-width: 600px;
}

.profile-form h2 {
    color: #6d4c41;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #6d4c41;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="tel"] {
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: #8d6e63;
    box-shadow: 0 0 0 3px rgba(141,110,99,0.1);
}

.form-group input:invalid {
    border-color: #f44336;
}

.form-group input:valid {
    border-color: #4caf50;
}

.profile-picture-section {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px dashed #d0d7de;
}

.current-picture {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #8d6e63;
}

.picture-upload {
    flex: 1;
}

.file-input-wrapper {
    position: relative;
    display: inline-block;
}

.file-input {
    display: none;
}

.file-input-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #8d6e63;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.3s;
}

.file-input-label:hover {
    background: #6d4c41;
}

.file-info {
    margin-top: 10px;
    font-size: 12px;
    color: #6c757d;
}

.selected-file {
    margin-top: 8px;
    padding: 5px 0;
    color: #28a745;
    font-size: 13px;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid #e9ecef;
}

.btn-primary {
    background: #8d6e63;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-primary:hover {
    background: #6d4c41;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary:hover {
    background: #5a6268;
}

/* ----- Change Password Form ----- */
.account-section form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.account-section label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #6d4c41;
}
.account-section input[type="password"] {
    padding: 10px 15px;
    border: 1px solid #d7ccc8;
    border-radius: 5px;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.3s, box-shadow 0.3s;
}
.account-section input[type="password"]:focus {
    border-color: #8d6e63;
    box-shadow: 0 0 5px rgba(141,110,99,0.3);
}
.account-section button.btn {
    width: 200px;
    align-self: flex-start;
    background: #8d6e63;
    padding: 12px 20px;
    font-size: 1rem;
    border-radius: 5px;
    font-weight: 600;
    transition: background 0.3s;
}
.account-section button.btn:hover { background: #6d4c41; }
.info-item { display: flex; flex-direction: column; }

/* ----- Feedback Form Styles ----- */
.feedback-form {
    max-width: 600px;
    margin-top: 20px;
}

.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    margin: 10px 0;
}

.star-rating input {
    display: none;
}

.star-rating label {
    cursor: pointer;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ddd;
    font-size: 30px;
    transition: color 0.3s;
}

.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input:checked ~ label {
    color: #ffc107;
}

.feedback-form textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    min-height: 120px;
    resize: vertical;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.feedback-form textarea:focus {
    outline: none;
    border-color: #8d6e63;
    box-shadow: 0 0 0 3px rgba(141,110,99,0.1);
}

.feedback-form .btn {
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .profile-picture-section {
        flex-direction: column;
        text-align: center;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .star-rating label {
        width: 35px;
        height: 35px;
        font-size: 25px;
    }
    
    .rental-card {
        flex-direction: column;
        text-align: left;
    }
    
    .rental-amount {
        position: static;
        margin-top: 10px;
    }
    
    .stats {
        justify-content: center;
    }
    
    .stat-card {
        flex: 1;
        min-width: 100px;
    }
}
</style>
</head>
<body>

<?php if(!empty($message)): ?>
<div class="notification"><?php echo $message; ?></div>
<?php endif; ?>

<div class="container">
    <div class="sidebar">
        <div class="profile-info">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="profile-pic">
            <h2><?php echo htmlspecialchars($user_data['username']); ?></h2>
            <p>Member</p>
            <button class="edit-profile-btn" onclick="location.href='?tab=edit_profile'">
                <i class="fas fa-edit"></i> Edit Profile
            </button>
        </div>
        <ul class="nav-links">
            <li><a href="?tab=overview" class="<?php echo ($tab==='overview')?'active':''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="?tab=edit_profile" class="<?php echo ($tab==='edit_profile')?'active':''; ?>"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
            <li><a href="?tab=rentals" class="<?php echo ($tab==='rentals')?'active':''; ?>"><i class="fas fa-car"></i> My Rentals</a></li>
            <li><a href="?tab=change_password" class="<?php echo ($tab==='change_password')?'active':''; ?>"><i class="fas fa-key"></i> Change Password</a></li>
            <li><a href="?tab=feedback" class="<?php echo ($tab==='feedback')?'active':''; ?>"><i class="fas fa-comment"></i> Feedback</a></li>
            <li><a href="user.php"><i class="fas fa-home"></i> Back to Home</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="welcome">
            <h1>Welcome back, <?php echo htmlspecialchars($user_data['username']); ?>!</h1>
            <p>Here's your account overview and recent activity</p>
        </div>

        <?php if($tab !== 'edit_profile' && $tab !== 'feedback'): ?>
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $stats['total_rentals']; ?></h3>
                <p>Total Rentals</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['completed_rentals']; ?></h3>
                <p>Completed</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['active_rentals']; ?></h3>
                <p>Active/Pending</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($stats['total_spent'], 0); ?></h3>
                <p>Total Rent</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if($tab==='overview'): ?>
        <div class="account-section">
            <h2><i class="fas fa-info-circle"></i> Account Information</h2>
            <p><strong><i class="fas fa-user"></i> Username:</strong> <?php echo htmlspecialchars($user_data['username']); ?></p>
            <p><strong><i class="fas fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($user_data['email_id']); ?></p>
            <p><strong><i class="fas fa-phone"></i> Phone:</strong> <?php echo htmlspecialchars($user_data['phone'] ?? 'Not provided'); ?></p>
            <p><strong><i class="fas fa-check-circle"></i> Status:</strong> <?php echo htmlspecialchars($user_data['status'] ?? 'Active'); ?></p>
            <p><strong><i class="fas fa-car"></i> Total Rentals:</strong> <?php echo $stats['total_rentals']; ?></p>
            <p><strong><i class="fas fa-rupee-sign"></i> Total Rent:</strong> ₹<?php echo number_format($stats['total_spent'], 2); ?></p>
        </div>

        <?php elseif($tab==='edit_profile'): ?>
        <div class="edit-profile-section">
            <form method="POST" enctype="multipart/form-data" class="profile-form">
                <h2><i class="fas fa-user-edit"></i> Edit Profile</h2>
                
                <!-- Profile Picture Section -->
                <div class="form-group full-width">
                    <label><i class="fas fa-camera"></i> Profile Picture</label>
                    <div class="profile-picture-section">
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Current Profile Picture" class="current-picture">
                        <div class="picture-upload">
                            <div class="file-input-wrapper">
                                <input type="file" name="profile_picture" id="profile-picture-input" class="file-input" accept="image/*" onchange="updateFileName()">
                                <label for="profile-picture-input" class="file-input-label">
                                    <i class="fas fa-upload"></i> Choose New Picture
                                </label>
                            </div>
                            <div class="file-info">
                                <small>Supported formats: JPG, JPEG, PNG, GIF (Max size: 5MB)</small>
                            </div>
                            <div class="selected-file" id="selected-file" style="display: none;"></div>
                        </div>
                    </div>
                </div>

                <!-- Personal Information with Validation -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($user_data['username']); ?>" 
                               minlength="3" 
                               maxlength="10" 
                               pattern="[A-Za-z0-9_]{3,10}" 
                               title="Username must be 3-10 characters (letters, numbers, underscore only)" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user_data['email_id']); ?>" 
                               pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" 
                               title="Please enter a valid email address (e.g., user@example.com)" 
                               required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" 
                               pattern="[0-9]{10}" 
                               maxlength="10" 
                               title="Please enter exactly 10 digits" 
                               placeholder="Enter your 10-digit phone number">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Member Since</label>
                        <input type="text" value="<?php echo date('F Y', strtotime($user_data['created_at'] ?? 'now')); ?>" readonly style="background: #f8f9fa;">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                    <a href="?tab=overview" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>

        <?php elseif($tab==='change_password'): ?>
        <div class="account-section">
            <h2><i class="fas fa-key"></i> Change Password</h2>
            <form method="POST" action="?tab=change_password">
                <div class="info-item">
                    <label for="current_password">Current Password</label>
                    <input type="password" name="current_password" id="current_password" placeholder="Enter current password" required>
                </div>
                <div class="info-item">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" id="new_password" placeholder="Enter new password" required>
                </div>
                <div class="info-item">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                </div>
                <button type="submit" name="change_password" class="btn">Update Password</button>
            </form>
        </div>

        <?php elseif($tab==='feedback'): ?>
        <div class="account-section">
            <h2><i class="fas fa-comment"></i> Share Your Feedback</h2>
            <p>We value your opinion! Please rate your experience and share any suggestions.</p>
            
            <form method="POST" class="feedback-form">
                <div class="form-group">
                    <label>Your Rating</label>
                    <div class="star-rating">
                        <input type="radio" id="star-5" name="rating" value="5" />
                        <label for="star-5"><i class="fas fa-star"></i></label>
                        <input type="radio" id="star-4" name="rating" value="4" />
                        <label for="star-4"><i class="fas fa-star"></i></label>
                        <input type="radio" id="star-3" name="rating" value="3" />
                        <label for="star-3"><i class="fas fa-star"></i></label>
                        <input type="radio" id="star-2" name="rating" value="2" />
                        <label for="star-2"><i class="fas fa-star"></i></label>
                        <input type="radio" id="star-1" name="rating" value="1" />
                        <label for="star-1"><i class="fas fa-star"></i></label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="message">Your Feedback</label>
                    <textarea id="message" name="message" rows="5" placeholder="Please share your experience or suggestions..." required></textarea>
                </div>
                
                <button type="submit" name="submit_feedback" class="btn">
                    <i class="fas fa-paper-plane"></i> Submit Feedback
                </button>
            </form>
        </div>

        <?php elseif($tab==='rentals'): ?>
        <div class="rentals-section">
            <h2><i class="fas fa-car"></i> My Rentals</h2>
            <?php if(empty($rentals)): ?>
            <div class="no-rentals">
                <i class="fas fa-car"></i>
                <h3>No Rentals Found</h3>
                <p>You haven't made any car rentals yet.</p>
                <a href="user.php" class="btn">Browse Cars</a>
            </div>
            <?php else: ?>
                <?php foreach($rentals as $rental): ?>
                <div class="rental-card">
                    <img src="<?php echo $rental['image']; ?>" alt="Car" class="rental-img">
                    <div class="rental-details">
                        <div class="booking-id">Booking ID: <?php echo htmlspecialchars($rental['booking_id']); ?></div>
                        <h3><?php echo htmlspecialchars($rental['car']); ?></h3>
                        <p><i class="fas fa-calendar"></i> <?php echo $rental['dates']; ?></p>
                        <p><i class="fas fa-clock"></i> <?php echo $rental['times']; ?></p>
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($rental['location']); ?></p>
                        <p><i class="fas fa-clock"></i> <?php echo $rental['rental_days']; ?> day(s) • 
                            <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($rental['status'])); ?>">
                                <?php echo ucfirst(str_replace('-', ' ', $rental['status'])); ?>
                            </span>
                            <span class="payment-status <?php echo strtolower($rental['payment_status']); ?>">
                                <?php echo $rental['payment_status']; ?>
                            </span>
                        </p>
                        
                        <?php if($rental['driver_needed']): ?>
                        <div class="special-info driver-needed">
                            <i class="fas fa-user-tie"></i> Driver service included
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($rental['special_requests'])): ?>
                        <div class="special-info">
                            <i class="fas fa-info-circle"></i> Special requests: <?php echo htmlspecialchars($rental['special_requests']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="rental-amount">
                        <strong>₹<?php echo number_format($rental['total_amount']); ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notification = document.querySelector('.notification');
    if(notification){
        setTimeout(()=>{notification.style.display='none';}, 3000);
    }
});

function updateFileName() {
    const fileInput = document.getElementById('profile-picture-input');
    const selectedFile = document.getElementById('selected-file');
    
    if (fileInput.files.length > 0) {
        const fileName = fileInput.files[0].name;
        const fileSize = (fileInput.files[0].size / 1024 / 1024).toFixed(2);
        selectedFile.innerHTML = `<i class="fas fa-check-circle"></i> Selected: ${fileName} (${fileSize} MB)`;
        selectedFile.style.display = 'block';
    } else {
        selectedFile.style.display = 'none';
    }
}
</script>
</body>
</html>