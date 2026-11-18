<?php
// === Session & Auth ===
session_start();
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

// === Handle Logout ===
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    session_destroy();
    header('Location: 1.php'); // Redirect to login page
    exit();
}

// === DB Connection ===
$conn = new mysqli("localhost", "root", "", "car_rental");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Helper: sanitize numeric id
function as_int($v){ return (int)($v ?? 0); }

// === Auto-update booking statuses based on dates ===
$current_datetime = date('Y-m-d H:i:s');

// 1. Change 'Approved' bookings to 'In Progress' when pickup date/time has passed
$stmt_in_progress = $conn->prepare("
    UPDATE bookings 
    SET booking_status='In Progress', updated_at=CURRENT_TIMESTAMP 
    WHERE CONCAT(pickup_date, ' ', pickup_time) <= ? 
      AND booking_status = 'Approved'
");
if ($stmt_in_progress) {
    $stmt_in_progress->bind_param("s", $current_datetime);
    $stmt_in_progress->execute();
    $stmt_in_progress->close();
}

// 2. Change 'In Progress' bookings to 'Completed' when return date/time has passed
$stmt_auto_complete = $conn->prepare("
    UPDATE bookings 
    SET booking_status='Completed', updated_at=CURRENT_TIMESTAMP 
    WHERE CONCAT(return_date, ' ', return_time) <= ? 
      AND booking_status = 'In Progress'
");
if ($stmt_auto_complete) {
    $stmt_auto_complete->bind_param("s", $current_datetime);
    $stmt_auto_complete->execute();
    $stmt_auto_complete->close();
}

// 3. Update car availability when bookings are completed
$stmt_update_cars = $conn->prepare("
    UPDATE cars c
    INNER JOIN bookings b ON c.car_id = b.car_id
    SET c.availability_status = 'Available'
    WHERE b.booking_status = 'Completed' 
    AND c.availability_status = 'Rented'
");
if ($stmt_update_cars) {
    $stmt_update_cars->execute();
    $stmt_update_cars->close();
}

// === DELETE Car (POST) ===
$success_message = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_car') {
    $car_id = as_int($_POST['car_id'] ?? 0);
    if ($car_id > 0) {
        // (Optional) fetch to delete uploaded file from disk
        $get = $conn->prepare("SELECT image FROM cars WHERE car_id=?");
        $get->bind_param("i", $car_id);
        $get->execute();
        $imgRes = $get->get_result();
        $imgRow = $imgRes->fetch_assoc();
        $get->close();

        $stmt = $conn->prepare("DELETE FROM cars WHERE car_id=?");
        $stmt->bind_param("i", $car_id);
        if ($stmt->execute()) {
            // If the image path is a local upload, you may unlink it
            if ($imgRow && !empty($imgRow['image']) && strpos($imgRow['image'], 'uploads/cars/') === 0 && file_exists($imgRow['image'])) {
                @unlink($imgRow['image']);
            }
            $success_message = "Car deleted successfully.";
        } else {
            $error_message = "Failed to delete car: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "Invalid car id.";
    }
}

// === BOOKING STATUS MANAGEMENT (IMPROVED VERSION) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'process_booking') {
    $booking_id = as_int($_POST['booking_id'] ?? 0);
    $booking_action = trim($_POST['booking_action'] ?? '');
    
    if ($booking_id > 0 && in_array($booking_action, ['confirm', 'approve', 'cancel', 'complete'])) {
        // Check current booking status and details
        $check_stmt = $conn->prepare("SELECT booking_status, pickup_date, pickup_time, return_date, return_time, car_id FROM bookings WHERE id=?");
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $booking_result = $check_stmt->get_result();
        $booking_data = $booking_result->fetch_assoc();
        $check_stmt->close();
        
        if ($booking_data) {
            $current_status = $booking_data['booking_status'];
            $car_id = $booking_data['car_id'];
            $pickup_datetime = $booking_data['pickup_date'] . ' ' . $booking_data['pickup_time'];
            $return_datetime = $booking_data['return_date'] . ' ' . $booking_data['return_time'];
            $current_datetime = date('Y-m-d H:i:s');
            
            if ($booking_action === 'confirm') {
                // Change from Pending to Confirmed
                if ($current_status === 'Pending') {
                    $stmt = $conn->prepare("UPDATE bookings SET booking_status='Confirmed', updated_at=CURRENT_TIMESTAMP WHERE id=?");
                    $stmt->bind_param("i", $booking_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Booking confirmed successfully.";
                    } else {
                        $error_message = "Failed to confirm booking: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Can only confirm pending bookings. Current status: {$current_status}";
                }
                
            } elseif ($booking_action === 'approve') {
                // Change from Confirmed to Approved
                if ($current_status === 'Confirmed') {
                    $stmt = $conn->prepare("UPDATE bookings SET booking_status='Approved', payment_status='Paid', updated_at=CURRENT_TIMESTAMP WHERE id=?");
                    $stmt->bind_param("i", $booking_id);
                    
                    if ($stmt->execute()) {
                        // Update car status to Rented when booking is approved
                        $car_stmt = $conn->prepare("UPDATE cars SET availability_status='Rented' WHERE car_id=?");
                        $car_stmt->bind_param("i", $car_id);
                        $car_stmt->execute();
                        $car_stmt->close();
                        
                        $success_message = "Booking approved successfully. Car status updated to Rented.";
                    } else {
                        $error_message = "Failed to approve booking: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Cannot approve booking - it must be in Confirmed status. Current status: {$current_status}";
                }
                
            } elseif ($booking_action === 'complete') {
                // Manually mark as completed
                if (in_array($current_status, ['In Progress', 'Approved'])) {
                    $stmt = $conn->prepare("UPDATE bookings SET booking_status='Completed', updated_at=CURRENT_TIMESTAMP WHERE id=?");
                    $stmt->bind_param("i", $booking_id);
                    
                    if ($stmt->execute()) {
                        // Make car available again
                        $car_stmt = $conn->prepare("UPDATE cars SET availability_status='Available' WHERE car_id=?");
                        $car_stmt->bind_param("i", $car_id);
                        $car_stmt->execute();
                        $car_stmt->close();
                        
                        $success_message = "Booking marked as completed. Car is now available.";
                    } else {
                        $error_message = "Failed to complete booking: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Can only complete bookings that are In Progress or Approved. Current status: {$current_status}";
                }
                
            } elseif ($booking_action === 'cancel') {
                // Cancel booking from any status except Completed
                $allowed_statuses = ['Pending', 'Confirmed', 'Approved', 'In Progress'];
                
                if (in_array($current_status, $allowed_statuses)) {
                    $stmt = $conn->prepare("UPDATE bookings SET booking_status='Cancelled', updated_at=CURRENT_TIMESTAMP WHERE id=?");
                    $stmt->bind_param("i", $booking_id);
                    
                    if ($stmt->execute()) {
                        // If booking was approved/in progress, make car available again
                        if (in_array($current_status, ['Approved', 'In Progress'])) {
                            $car_stmt = $conn->prepare("UPDATE cars SET availability_status='Available' WHERE car_id=?");
                            $car_stmt->bind_param("i", $car_id);
                            $car_stmt->execute();
                            $car_stmt->close();
                        }
                        
                        $success_message = "Booking cancelled successfully.";
                    } else {
                        $error_message = "Failed to cancel booking: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Cannot cancel booking with status: {$current_status}";
                }
            }
        } else {
            $error_message = "Booking not found.";
        }
    } else {
        $error_message = "Invalid booking data.";
    }
}

// === EDIT Car (POST) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_car'])) {
    $car_id            = as_int($_POST['car_id'] ?? 0);
    $title             = trim($_POST['title'] ?? '');
    $fuel_type         = trim($_POST['fuel_type'] ?? '');
    $seating_capacity  = as_int($_POST['seating_capacity'] ?? 0);
    $transmission      = trim($_POST['transmission'] ?? '');
    $price             = trim($_POST['price'] ?? '0');
    $availability_status = trim($_POST['availability_status'] ?? 'Available');
    $image             = trim($_POST['current_image'] ?? ''); // Keep current image by default

    // Validate basics
    if ($car_id <= 0) {
        $error_message = "Invalid car ID.";
    } elseif ($title === '' || $fuel_type === '' || $seating_capacity <= 0 || $transmission === '' || !is_numeric($price)) {
        $error_message = "Please fill all required fields correctly.";
    }

    // Image handling (upload or URL) - only if new image is provided
    if (!isset($error_message)) {
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0 && ($_POST['image_mode'] ?? '') === 'upload') {
            $target_dir = "uploads/cars/";
            $file_extension = strtolower(pathinfo($_FILES["image_file"]["name"], PATHINFO_EXTENSION));
            $allowed = ["jpg","jpeg","png","gif","webp"];
            if (!in_array($file_extension, $allowed)) {
                $error_message = "Only JPG, JPEG, PNG, GIF, WEBP files are allowed.";
            } elseif ($_FILES["image_file"]["size"] > 5 * 1024 * 1024) {
                $error_message = "File is too large. Max 5MB.";
            } else {
                $check = @getimagesize($_FILES["image_file"]["tmp_name"]);
                if ($check === false) {
                    $error_message = "Uploaded file is not a valid image.";
                } else {
                    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
                    $new_filename = uniqid("car_", true) . "." . $file_extension;
                    $target_file = $target_dir . $new_filename;
                    if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $target_file)) {
                        // Delete old image if it was a local upload
                        if (!empty($image) && strpos($image, 'uploads/cars/') === 0 && file_exists($image)) {
                            @unlink($image);
                        }
                        $image = $target_file; // store new relative path
                    } else {
                        $error_message = "Error uploading file.";
                    }
                }
            }
        } elseif (($_POST['image_mode'] ?? '') === 'url') {
            $url = trim($_POST['image_url'] ?? '');
            if ($url !== '') {
                // very light validation
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    // Delete old image if it was a local upload
                    if (!empty($image) && strpos($image, 'uploads/cars/') === 0 && file_exists($image)) {
                        @unlink($image);
                    }
                    $image = $url;
                } else {
                    $error_message = "Invalid image URL.";
                }
            }
        }
    }

    if (!isset($error_message)) {
        $sql = "UPDATE cars SET title=?, fuel_type=?, seating_capacity=?, transmission=?, price=?, image=?, availability_status=? WHERE car_id=?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssissssi", $title, $fuel_type, $seating_capacity, $transmission, $price, $image, $availability_status, $car_id);
            if ($stmt->execute()) {
                $success_message = "Car updated successfully!";
            } else {
                $error_message = "Error updating car: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Database error: " . $conn->error;
        }
    }
}

// === ADD Car (POST) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_car'])) {
    $title             = trim($_POST['title'] ?? '');
    $fuel_type         = trim($_POST['fuel_type'] ?? '');
    $seating_capacity  = as_int($_POST['seating_capacity'] ?? 0);
    $transmission      = trim($_POST['transmission'] ?? '');
    $price             = trim($_POST['price'] ?? '0');
    $image             = '';

    // Validate basics
    if ($title === '' || $fuel_type === '' || $seating_capacity <= 0 || $transmission === '' || !is_numeric($price)) {
        $error_message = "Please fill all required fields correctly.";
    }

    // Image handling (upload or URL)
    if (!isset($error_message)) {
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0 && ($_POST['image_mode'] ?? '') === 'upload') {
            $target_dir = "uploads/cars/";
            $file_extension = strtolower(pathinfo($_FILES["image_file"]["name"], PATHINFO_EXTENSION));
            $allowed = ["jpg","jpeg","png","gif","webp"];
            if (!in_array($file_extension, $allowed)) {
                $error_message = "Only JPG, JPEG, PNG, GIF, WEBP files are allowed.";
            } elseif ($_FILES["image_file"]["size"] > 5 * 1024 * 1024) {
                $error_message = "File is too large. Max 5MB.";
            } else {
                $check = @getimagesize($_FILES["image_file"]["tmp_name"]);
                if ($check === false) {
                    $error_message = "Uploaded file is not a valid image.";
                } else {
                    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
                    $new_filename = uniqid("car_", true) . "." . $file_extension;
                    $target_file = $target_dir . $new_filename;
                    if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $target_file)) {
                        $image = $target_file; // store relative path
                    } else {
                        $error_message = "Error uploading file.";
                    }
                }
            }
        } elseif (($_POST['image_mode'] ?? '') === 'url') {
            $url = trim($_POST['image_url'] ?? '');
            if ($url !== '') {
                // very light validation
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $image = $url;
                } else {
                    $error_message = "Invalid image URL.";
                }
            }
        }
    }

    if (!isset($error_message)) {
        $sql = "INSERT INTO cars (title, fuel_type, seating_capacity, transmission, price, image, availability_status)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $availability_status = 'Available';
            $stmt->bind_param("ssissss", $title, $fuel_type, $seating_capacity, $transmission, $price, $image, $availability_status);
            if ($stmt->execute()) {
                $success_message = "Car added successfully!";
            } else {
                $error_message = "Error adding car: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Database error: " . $conn->error;
        }
    }
}

// === Stats (including bookings) ===
$total_users = 0; $total_cars = 0; $available_cars = 0; $total_bookings = 0;

$res = $conn->query("SELECT COUNT(*) AS total_users FROM users");
if ($res && $row = $res->fetch_assoc()) $total_users = (int)$row['total_users'];

$res = $conn->query("SELECT COUNT(*) AS total_cars FROM cars");
if ($res && $row = $res->fetch_assoc()) $total_cars = (int)$row['total_cars'];

$res = $conn->query("SELECT COUNT(*) AS available_cars FROM cars WHERE availability_status='Available'");
if ($res && $row = $res->fetch_assoc()) $available_cars = (int)$row['available_cars'];

$res = $conn->query("SELECT COUNT(*) AS total_bookings FROM bookings");
if ($res && $row = $res->fetch_assoc()) $total_bookings = (int)$row['total_bookings'];

// === Cars list ===
$cars_result = $conn->query("SELECT * FROM cars ORDER BY car_id DESC");

// === Bookings list with user details ===
$bookings_query = "
    SELECT 
        b.*,
        u.username,
        u.email_id,
        c.title as car_title,
        c.image as car_image,
        c.availability_status as car_status
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN cars c ON b.car_id = c.car_id
    ORDER BY b.created_at DESC
";
$bookings_result = $conn->query($bookings_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>DriveEase - Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
<style>
.bookings-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    font-size: 13px;
}

.bookings-table th,
.bookings-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

.bookings-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #1e293b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.bookings-table tbody tr:hover {
    background: #f8fafc;
}

.booking-status, .payment-status {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
    white-space: nowrap;
}

/* Booking Status Colors */
.booking-status.pending { background: #fef3c7; color: #92400e; }
.booking-status.confirmed { background: #d1fae5; color: #065f46; }
.booking-status.approved { background: #a7f3d0; color: #047857; }
.booking-status.in.progress { background: #dbeafe; color: #1e40af; }
.booking-status.completed { background: #e0e7ff; color: #4338ca; }
.booking-status.cancelled { background: #fee2e2; color: #991b1b; }

/* Payment Status Colors */
.payment-status.pending { background: #fef3c7; color: #92400e; }
.payment-status.paid { background: #d1fae5; color: #065f46; }
.payment-status.failed { background: #fee2e2; color: #991b1b; }
.payment-status.refunded { background: #f3e8ff; color: #7c3aed; }

.status-update-form {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.confirm-btn, .approve-btn, .cancel-btn, .complete-btn, .status-btn, .emergency-cancel-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
    transition: all 0.2s ease;
    margin: 2px;
}

.confirm-btn {
    background: #10b981;
    color: white;
}

.confirm-btn:hover {
    background: #059669;
    transform: translateY(-1px);
}

.approve-btn {
    background: #059669;
    color: white;
}

.approve-btn:hover {
    background: #047857;
    transform: translateY(-1px);
}

.complete-btn {
    background: #3b82f6;
    color: white;
}

.complete-btn:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.cancel-btn {
    background: #ef4444;
    color: white;
}

.cancel-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.emergency-cancel-btn {
    background: #dc2626;
    color: white;
    border: 2px solid #991b1b;
}

.emergency-cancel-btn:hover {
    background: #991b1b;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
}

.status-btn {
    background: #6b7280;
    color: white;
    font-size: 9px;
    padding: 3px 6px;
}

.booking-user-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.booking-user-name {
    font-weight: 500;
    color: #1e293b;
    font-size: 12px;
}

.booking-user-email {
    font-size: 10px;
    color: #64748b;
}

.booking-car-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.booking-car-image {
    width: 35px;
    height: 25px;
    object-fit: cover;
    border-radius: 4px;
    flex-shrink: 0;
}

.booking-car-title {
    font-weight: 500;
    color: #1e293b;
    font-size: 11px;
}

.booking-dates {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 10px;
}

.date-label {
    color: #64748b;
    font-weight: 500;
    font-size: 9px;
}

.booking-locations {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 10px;
}

.location-label {
    color: #64748b;
    font-weight: 500;
    font-size: 9px;
}

.booking-id {
    font-weight: 600;
    color: #3b82f6;
    font-family: monospace;
}

.amount-display {
    font-weight: 600;
    color: #059669;
    font-size: 12px;
}

.driver-needed {
    padding: 2px 6px;
    background: #dbeafe;
    color: #1e40af;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 500;
}

.special-requests {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 10px;
    color: #64748b;
}

/* Logout button styles */
.user-profile {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logout-btn {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.logout-btn:hover {
    background: linear-gradient(135deg, #c82333, #a71e2a);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.logout-btn:active {
    transform: translateY(0);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #4a5568;
    font-weight: 500;
}

.user-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e2e8f0;
}

.actions-column {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
    min-width: 140px;
}

.booking-actions {
    display: flex;
    gap: 6px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.status-info {
    font-size: 11px;
    font-weight: 500;
    display: block;
    margin-bottom: 8px;
    padding: 4px 8px;
    border-radius: 4px;
    text-align: center;
}

.status-info.completed {
    color: #4338ca;
    background: #e0e7ff;
}

.status-info.cancelled {
    color: #dc2626;
    background: #fee2e2;
}

.status-info.in-progress {
    color: #1e40af;
    background: #dbeafe;
}

.status-info.approved {
    color: #059669;
    background: #d1fae5;
}

.status-info.confirmed {
    color: #065f46;
    background: #a7f3d0;
}

.status-info.expired {
    color: #dc2626;
    background: #fef2f2;
}

.car-status-badge {
    font-size: 9px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-top: 2px;
    display: inline-block;
}

.car-status-available { background: #d1fae5; color: #065f46; }
.car-status-rented { background: #fee2e2; color: #991b1b; }
.car-status-maintenance { background: #fef3c7; color: #92400e; }

@media (max-width: 1200px) {
    .bookings-table {
        font-size: 11px;
    }
    
    .bookings-table th,
    .bookings-table td {
        padding: 6px 8px;
    }
    
    .actions-column {
        min-width: 120px;
    }
}

@media (max-width: 768px) {
    .bookings-table {
        font-size: 10px;
    }
    
    .bookings-table th,
    .bookings-table td {
        padding: 4px 6px;
    }
    
    .status-update-form {
        flex-direction: column;
        gap: 2px;
    }

    .user-profile {
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }

    .logout-btn {
        font-size: 12px;
        padding: 6px 12px;
    }

    .booking-car-info {
        flex-direction: column;
        gap: 4px;
    }

    .booking-car-image {
        width: 30px;
        height: 20px;
    }
    
    .actions-column {
        min-width: 100px;
    }
    
    .confirm-btn, .approve-btn, .cancel-btn, .complete-btn, .emergency-cancel-btn {
        padding: 4px 8px;
        font-size: 9px;
    }
}

/* Responsive table scroll */
.table-container {
    overflow-x: auto;
    margin-top: 20px;
}

@media (max-width: 1024px) {
    .table-container {
        overflow-x: scroll;
    }
    
    .bookings-table {
        min-width: 1200px;
    }
}
</style>
</head>
<body>
<div class="mobile-menu-toggle" id="mobileMenuToggle"><i class="fas fa-bars"></i></div>

<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <i class="fas fa-shield-alt fa-2x" style="color: #d2b48c;"></i>
    <h3>Admin Panel</h3>
  </div>

  <div class="sidebar-menu">
    <div class="menu-title">Main</div>
    <div class="menu-item active" data-section="dashboard"><i class="fas fa-home"></i><span>Dashboard</span></div>

    <div class="menu-title">Management</div>
    <div class="menu-item" data-section="users"><i class="fas fa-users"></i><span>Users</span></div>
    <div class="menu-item" data-section="cars"><i class="fas fa-car"></i><span>Cars</span></div>
    <div class="menu-item" data-section="bookings"><i class="fas fa-calendar-check"></i><span>Bookings</span></div>

    
  </div>
</div>

<div class="main-content" id="mainContent">
  <div class="header">
    <div class="page-title"><h1>Dashboard</h1></div>
    <div class="user-profile">
      <div class="user-info">
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS-m7Ot8jgPqax--2ouquHa0YWkPHgvklRHb5z325XUzd09wktQKRUOVXw&s" alt="User" class="user-avatar">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
      </div>
      <a href="?logout=true" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">
        <i class="fas fa-sign-out-alt"></i>
        Logout
      </a>
    </div>
  </div>

  <?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
  <?php endif; ?>
  <?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
  <?php endif; ?>

  <div class="dashboard-section active" id="dashboard">
    <div class="section-header"><h2>Overview</h2></div>
    <div class="cards-grid">
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Total Users</div>
            <div class="card-value"><?php echo $total_users; ?></div>
          </div>
          <div class="card-icon brown"><i class="fas fa-users"></i></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Total Cars</div>
            <div class="card-value"><?php echo $total_cars; ?></div>
          </div>
          <div class="card-icon brown"><i class="fas fa-car"></i></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Available Cars</div>
            <div class="card-value"><?php echo $available_cars; ?></div>
          </div>
          <div class="card-icon brown"><i class="fas fa-car"></i></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Total Bookings</div>
            <div class="card-value"><?php echo $total_bookings; ?></div>
          </div>
          <div class="card-icon brown"><i class="fas fa-calendar-check"></i></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="section-header"><h2>Recent Activity</h2></div>
      <div class="activity-list">
        <div class="activity-item">
          <i class="fas fa-user-circle" style="color: var(--primary);"></i>
          <div class="activity-content">
            <p><strong>System</strong> – Dashboard loaded</p>
            <small><?php echo date('d M Y H:i'); ?></small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="dashboard-section" id="users">
    <div class="section-header"><h2>User Management</h2></div>
    <iframe src="get_users.php"></iframe>
  </div>

  <div class="dashboard-section" id="cars">
    <div class="section-header"><h2>Car Management</h2></div>

    <div class="car-actions">
      <button id="addCarBtn" class="btn add-car"><i class="fas fa-plus"></i> Add Car</button>
    </div>

    <?php
    if ($cars_result && $cars_result->num_rows > 0) {
        echo "<div class='cars-grid'>";
        while ($row = $cars_result->fetch_assoc()) {
            echo "<div class='car-card'>";
            if (!empty($row['image'])) {
                $img = htmlspecialchars($row['image']);
                echo "<img src='{$img}' alt='Car Image'>";
            }
            echo "<h3>" . htmlspecialchars($row['title']) . "</h3>";
            echo "<p><strong>Fuel:</strong> " . htmlspecialchars($row['fuel_type']) . "</p>";
            echo "<p><strong>Seats:</strong> " . htmlspecialchars($row['seating_capacity']) . "</p>";
            echo "<p><strong>Transmission:</strong> " . htmlspecialchars($row['transmission']) . "</p>";
            echo "<p><strong>Price:</strong> $" . htmlspecialchars($row['price']) . "/day</p>";
            echo "<p><strong>Status:</strong> " . htmlspecialchars($row['availability_status']) . "</p>";
            
            // Edit and Delete buttons
            echo "<div class='car-card-actions'>
                    <button type='button' class='btn edit-car' onclick='editCar(" . json_encode($row) . ")'><i class=\"fas fa-edit\"></i> Edit</button>
                    <form method='POST' onsubmit='return confirmDelete()' style='display:inline;'>
                      <input type='hidden' name='action' value='delete_car'>
                      <input type='hidden' name='car_id' value='". (int)$row['car_id'] ."'>
                      <button type='submit' class='btn delete-car'><i class=\"fas fa-trash\"></i> Delete</button>
                    </form>
                  </div>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>No cars found. Add some cars to get started!</p>";
    }
    ?>
  </div>

  <div class="dashboard-section" id="bookings">
    <div class="section-header"><h2>Booking Management</h2></div>
    
    <?php if ($bookings_result && $bookings_result->num_rows > 0): ?>
      <div class="card">
        <div class="table-container">
          <table class="bookings-table">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>Customer</th>
                <th>Car</th>
                <th>Pickup</th>
                <th>Return</th>
                <th>Locations</th>
                <th>Driver</th>
                <th>Amount</th>
                <th>Payment Status</th>
                <th>Special Requests</th>
                <th>Created</th>
                <th>Booking Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $current_datetime = date('Y-m-d H:i:s');
              while ($booking = $bookings_result->fetch_assoc()): 
                $pickup_datetime = $booking['pickup_date'] . ' ' . $booking['pickup_time'];
                $return_datetime = $booking['return_date'] . ' ' . $booking['return_time'];
                $is_pickup_passed = $pickup_datetime <= $current_datetime;
                $is_return_passed = $return_datetime <= $current_datetime;
              ?>
                <tr>
                  <td>
                    <span class="booking-id"><?php echo htmlspecialchars($booking['booking_id']); ?></span>
                  </td>
                  <td>
                    <div class="booking-user-info">
                      <span class="booking-user-name">
                        <?php echo htmlspecialchars($booking['username'] ?? 'Unknown User'); ?>
                      </span>
                      <span class="booking-user-email">
                        <?php echo htmlspecialchars($booking['email_id'] ?? 'No email'); ?>
                      </span>
                    </div>
                  </td>
                  <td>
                    <div class="booking-car-info">
                      <?php if (!empty($booking['car_image'])): ?>
                        <img src="<?php echo htmlspecialchars($booking['car_image']); ?>" alt="Car" class="booking-car-image">
                      <?php endif; ?>
                      <span class="booking-car-title">
                        <?php echo htmlspecialchars($booking['car_title'] ?? 'Car not found'); ?>
                      </span>
                      <span class="car-status-badge car-status-<?php echo strtolower($booking['car_status'] ?? 'available'); ?>">
                        <?php echo htmlspecialchars($booking['car_status'] ?? 'Available'); ?>
                      </span>
                    </div>
                  </td>
                  <td>
                    <div class="booking-dates">
                      <span class="date-label">Date:</span>
                      <?php echo date('M d, Y', strtotime($booking['pickup_date'])); ?>
                      <span class="date-label">Time:</span>
                      <?php echo date('H:i', strtotime($booking['pickup_time'])); ?>
                      <?php if ($is_pickup_passed && in_array($booking['booking_status'], ['Approved', 'Confirmed'])): ?>
                        <span style="color: #dc2626; font-size: 9px;">(Passed)</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <div class="booking-dates">
                      <span class="date-label">Date:</span>
                      <?php echo date('M d, Y', strtotime($booking['return_date'])); ?>
                      <span class="date-label">Time:</span>
                      <?php echo date('H:i', strtotime($booking['return_time'])); ?>
                      <?php if ($is_return_passed && $booking['booking_status'] === 'In Progress'): ?>
                        <span style="color: #dc2626; font-size: 9px;">(Passed)</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <div class="booking-locations">
                      <span class="location-label">Pickup:</span>
                      <?php echo htmlspecialchars($booking['pickup_location']); ?>
                      <span class="location-label">Return:</span>
                      <?php echo htmlspecialchars($booking['return_location']); ?>
                    </div>
                  </td>
                  <td>
                    <?php if ($booking['driver_needed']): ?>
                      <span class="driver-needed">Driver Needed</span>
                    <?php else: ?>
                      <span style="color: #64748b; font-size: 10px;">Self Drive</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="amount-display">$<?php echo number_format($booking['total_amount'], 2); ?></span>
                  </td>
                  <td>
                    <span class="payment-status paid">Paid</span>
                  </td>
                  <td>
                    <?php if (!empty($booking['special_requests'])): ?>
                      <span class="special-requests" title="<?php echo htmlspecialchars($booking['special_requests']); ?>">
                        <?php echo htmlspecialchars($booking['special_requests']); ?>
                      </span>
                    <?php else: ?>
                      <span style="color: #94a3b8; font-size: 10px;">None</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="booking-dates">
                      <?php echo date('M d, Y', strtotime($booking['created_at'])); ?>
                      <span style="font-size: 9px; color: #64748b;">
                        <?php echo date('H:i', strtotime($booking['created_at'])); ?>
                      </span>
                    </div>
                  </td>
                  <td>
                    <div class="actions-column">
                      <div style="margin-bottom: 8px;">
                        <span class="booking-status <?php echo strtolower(str_replace(' ', '.', $booking['booking_status'])); ?>">
                          <?php echo htmlspecialchars($booking['booking_status']); ?>
                        </span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div class="actions-column">
                      <?php if ($booking['booking_status'] === 'Completed'): ?>
                        <span class="status-info completed">
                          <i class="fas fa-check-double"></i> Booking Completed
                        </span>
                        <small style="color: #059669; font-size: 9px;">Car is available</small>
                      <?php elseif ($booking['booking_status'] === 'Cancelled'): ?>
                        <span class="status-info cancelled">
                          <i class="fas fa-times-circle"></i> Booking Cancelled
                        </span>
                        <?php if (in_array($booking['car_status'], ['Available'])): ?>
                          <small style="color: #059669; font-size: 9px;">Car is available</small>
                        <?php endif; ?>
                      <?php elseif ($booking['booking_status'] === 'In Progress'): ?>
                        <span class="status-info in-progress">
                          <i class="fas fa-clock"></i> Trip In Progress
                        </span>
                        <div class="booking-actions">
                          <form method="POST" style="display: inline; margin-right: 5px;">
                            <input type="hidden" name="action" value="process_booking">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="booking_action" value="complete">
                            <button type="submit" class="complete-btn" 
                                    onclick="return confirm('Mark this booking as completed? The car will become available.')">
                              <i class="fas fa-check-double"></i> Complete
                            </button>
                          </form>
                          <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="process_booking">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="booking_action" value="cancel">
                            <button type="submit" class="emergency-cancel-btn" 
                                    onclick="return confirm('WARNING: This booking is currently in progress. Are you sure you want to cancel it? This action cannot be undone.')">
                              <i class="fas fa-exclamation-triangle"></i> Emergency Cancel
                            </button>
                          </form>
                        </div>
                      <?php elseif ($booking['booking_status'] === 'Approved'): ?>
                        <span class="status-info approved">
                          <i class="fas fa-check-circle"></i> Booking Approved
                        </span>
                        <?php if ($is_pickup_passed): ?>
                          <small style="color: #dc2626; font-size: 9px;">Pickup time passed</small>
                        <?php endif; ?>
                        <!-- No action buttons shown for Approved status -->
                      <?php elseif ($booking['booking_status'] === 'Confirmed'): ?>
                        <span class="status-info confirmed">
                          <i class="fas fa-check"></i> Booking Confirmed
                        </span>
                        <div class="booking-actions">
                          <form method="POST" style="display: inline; margin-right: 5px;">
                            <input type="hidden" name="action" value="process_booking">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="booking_action" value="approve">
                            <button type="submit" class="approve-btn" 
                                    onclick="return confirm('Are you sure you want to approve this booking? This will mark the car as rented.')">
                              <i class="fas fa-check-double"></i> Approve
                            </button>
                          </form>
                          <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="process_booking">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="booking_action" value="cancel">
                            <button type="submit" class="cancel-btn" 
                                    onclick="return confirm('Are you sure you want to cancel this confirmed booking?')">
                              <i class="fas fa-times"></i> Cancel
                            </button>
                          </form>
                        </div>
                      <?php elseif ($booking['booking_status'] === 'Pending' && $is_pickup_passed): ?>
                        <span class="status-info expired">
                          <i class="fas fa-exclamation-triangle"></i> Pickup Date Passed
                        </span>
                        <div class="booking-actions">
                          <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="process_booking">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="booking_action" value="cancel">
                            <button type="submit" class="cancel-btn" 
                                    onclick="return confirm('Cancel this expired booking?')">
                              <i class="fas fa-times"></i> Cancel
                            </button>
                          </form>
                        </div>
                      <?php elseif ($booking['booking_status'] === 'Pending'): ?>
                        <div class="booking-actions">
                          <form method="POST" style="display: inline; margin-right: 5px;">
                            <input type="hidden" name="action" value="process_booking">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="booking_action" value="confirm">
                            <button type="submit" class="confirm-btn" 
                                    onclick="return confirm('Are you sure you want to confirm this booking?')">
                              <i class="fas fa-check"></i> Confirm
                            </button>
                          </form>
                          <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="process_booking">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <input type="hidden" name="booking_action" value="cancel">
                            <button type="submit" class="cancel-btn" 
                                    onclick="return confirm('Are you sure you want to cancel this booking?')">
                              <i class="fas fa-times"></i> Cancel
                            </button>
                          </form>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="card">
        <p>No bookings found.</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="dashboard-section" id="settings">
    <div class="section-header"><h2>System Settings</h2></div>
    <p>Settings content goes here…</p>
  </div>

  <div class="dashboard-section" id="reports">
    <div class="section-header"><h2>Reports</h2></div>
    <p>Reports content goes here…</p>
  </div>
</div>

<div id="carModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="modalTitle">Add New Car</h2>
      <span class="close" id="closeModal">&times;</span>
    </div>
    <form method="POST" action="" enctype="multipart/form-data" id="carForm">
      <input type="hidden" id="carId" name="car_id" value="">
      <input type="hidden" id="currentImage" name="current_image" value="">
      
      <div class="form-group">
        <label for="title">Car Title *</label>
        <input type="text" id="title" name="title" required placeholder="e.g., Toyota Camry 2023">
      </div>
      <div class="form-group">
        <label for="fuel_type">Fuel Type *</label>
        <select id="fuel_type" name="fuel_type" required>
          <option value="">Select Fuel Type</option>
          <option>Petrol</option><option>Diesel</option><option>Electric</option>
          <option>Hybrid</option><option>CNG</option>
        </select>
      </div>
      <div class="form-group">
        <label for="seating_capacity">Seating Capacity *</label>
        <select id="seating_capacity" name="seating_capacity" required>
          <option value="">Select Capacity</option>
          <option value="2">2 Seater</option><option value="4">4 Seater</option>
          <option value="5">5 Seater</option><option value="7">7 Seater</option>
          <option value="8">8 Seater</option><option value="9">9 Seater</option>
        </select>
      </div>
      <div class="form-group">
        <label for="transmission">Transmission *</label>
        <select id="transmission" name="transmission" required>
          <option value="">Select Transmission</option>
          <option>Manual</option><option>Automatic</option><option>CVT</option>
        </select>
      </div>
      <div class="form-group">
        <label for="price">Price per Day *</label>
        <input type="number" id="price" name="price" required placeholder="Enter price in dollars" step="0.01" min="0">
      </div>
      
      <div class="form-group" id="availabilityGroup" style="display:none;">
        <label for="availability_status">Availability Status</label>
        <select id="availability_status" name="availability_status">
          <option value="Available">Available</option>
          <option value="Rented">Rented</option>
          <option value="Maintenance">Maintenance</option>
        </select>
      </div>

      <div class="form-group">
        <label>Car Image</label>
        <input type="hidden" name="image_mode" id="image_mode" value="upload">
        <div class="image-option-toggle">
          <button type="button" class="option-btn active" id="uploadBtn">Upload Image</button>
          <button type="button" class="option-btn" id="urlBtn">Enter URL</button>
        </div>

        <div class="image-input-container active" id="uploadContainer">
          <div class="file-input-wrapper">
            <div class="file-input" style="border:2px dashed var(--border); padding:12px; border-radius:8px; background:#f8fafc; text-align:center;">
              <input type="file" id="imageFile" name="image_file" accept="image/*" style="width:100%;">
              <span>Click to select image file or drag and drop</span>
            </div>
          </div>
          <small>Supported: JPG, PNG, GIF, WEBP (Max 5MB)</small>
        </div>

        <div class="image-input-container" id="urlContainer">
          <input type="url" id="imageUrl" name="image_url" placeholder="https://example.com/car-image.jpg">
          <small>Enter the URL of the car image</small>
        </div>
        
        <div id="currentImagePreview" style="display:none; margin-top:10px;">
          <small>Current Image:</small>
          <img id="currentImageDisplay" src="" alt="Current car image" style="width:100px; height:60px; object-fit:cover; border-radius:5px; margin-top:5px;">
        </div>
      </div>

      <div class="form-group" style="display:flex; gap:10px; justify-content:flex-end;">
        <button type="button" class="btn" id="cancelBtn" style="background:var(--border);">Cancel</button>
        <button type="submit" name="add_car" id="submitBtn" class="btn btn-primary">Add Car</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/admin.js"></script>
<script>
// Additional JavaScript for booking status updates
document.addEventListener('DOMContentLoaded', function() {
    // Add confirmation for booking status updates
    const statusForms = document.querySelectorAll('.booking-actions form');
    statusForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const action = this.querySelector('input[name="booking_action"]')?.value;
            const bookingId = this.querySelector('input[name="booking_id"]').value;
            
            if (action && !confirm(`Are you sure you want to ${action} booking #${bookingId}?`)) {
                e.preventDefault();
            }
        });
    });
});

// Delete confirmation function
function confirmDelete() {
    return confirm('Are you sure you want to delete this car? This action cannot be undone.');
}

// Car modal functions
function editCar(car) {
    document.getElementById('modalTitle').textContent = 'Edit Car';
    document.getElementById('carId').value = car.car_id;
    document.getElementById('title').value = car.title;
    document.getElementById('fuel_type').value = car.fuel_type;
    document.getElementById('seating_capacity').value = car.seating_capacity;
    document.getElementById('transmission').value = car.transmission;
    document.getElementById('price').value = car.price;
    document.getElementById('availability_status').value = car.availability_status;
    document.getElementById('currentImage').value = car.image;
    
    // Show availability status for editing
    document.getElementById('availabilityGroup').style.display = 'block';
    
    // Show current image preview
    if (car.image) {
        document.getElementById('currentImagePreview').style.display = 'block';
        document.getElementById('currentImageDisplay').src = car.image;
    }
    
    // Change submit button
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update Car';
    document.getElementById('submitBtn').name = 'edit_car';
    
    // Show modal
    document.getElementById('carModal').style.display = 'block';
}

// Modal functionality
document.getElementById('addCarBtn').addEventListener('click', function() {
    document.getElementById('modalTitle').textContent = 'Add New Car';
    document.getElementById('carForm').reset();
    document.getElementById('carId').value = '';
    document.getElementById('availabilityGroup').style.display = 'none';
    document.getElementById('currentImagePreview').style.display = 'none';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-plus"></i> Add Car';
    document.getElementById('submitBtn').name = 'add_car';
    document.getElementById('carModal').style.display = 'block';
});

document.getElementById('closeModal').addEventListener('click', function() {
    document.getElementById('carModal').style.display = 'none';
});

document.getElementById('cancelBtn').addEventListener('click', function() {
    document.getElementById('carModal').style.display = 'none';
});

// Image mode toggle
document.getElementById('uploadBtn').addEventListener('click', function() {
    document.getElementById('uploadBtn').classList.add('active');
    document.getElementById('urlBtn').classList.remove('active');
    document.getElementById('uploadContainer').classList.add('active');
    document.getElementById('urlContainer').classList.remove('active');
    document.getElementById('image_mode').value = 'upload';
});

document.getElementById('urlBtn').addEventListener('click', function() {
    document.getElementById('urlBtn').classList.add('active');
    document.getElementById('uploadBtn').classList.remove('active');
    document.getElementById('urlContainer').classList.add('active');
    document.getElementById('uploadContainer').classList.remove('active');
    document.getElementById('image_mode').value = 'url';
});
</script>
</body>
</html>
<?php $conn->close(); ?>