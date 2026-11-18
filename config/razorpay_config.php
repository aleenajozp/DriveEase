<?php
/**
 * Razorpay Configuration File
 * Replace with your actual Razorpay credentials
 */

// Razorpay API Credentials
define('RAZORPAY_KEY_ID', 'rzp_test_RH7LqPTsspswiS');
define('RAZORPAY_SECRET', 'fhUoE9Sk7B6C9DGiLwacwXu4');

// For Live Environment, use:
// define('RAZORPAY_KEY_ID', 'rzp_live_YOUR_LIVE_KEY_ID');
// define('RAZORPAY_SECRET', 'YOUR_LIVE_SECRET_KEY');

// Payment Settings
define('PAYMENT_CURRENCY', 'INR');
define('COMPANY_NAME', 'Car Rental Service');
define('COMPANY_LOGO', 'https://your-website.com/logo.png'); // Optional

// Webhook Settings (for advanced integration)
define('RAZORPAY_WEBHOOK_SECRET', 'YOUR_WEBHOOK_SECRET_HERE');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'car_rental');

/**
 * Get Razorpay API instance
 */
function getRazorpayApi() {
    require_once(__DIR__ . '/../razorpay-php/Razorpay.php');
    return new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_SECRET);
}

/**
 * Get database connection
 */
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

/**
 * Generate unique booking ID
 */
function generateBookingId() {
    return 'BK' . date('Ymd') . rand(1000, 9999);
}

/**
 * Log payment transactions
 */
function logTransaction($booking_id, $payment_id, $amount, $status, $response_data = null) {
    $conn = getDbConnection();
    
    $log_data = json_encode($response_data);
    $sql = "INSERT INTO payment_logs (booking_id, payment_id, amount, status, response_data, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdss", $booking_id, $payment_id, $amount, $status, $log_data);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

/**
 * Send booking confirmation email (placeholder)
 */
function sendBookingConfirmation($booking_id, $user_email, $booking_details) {
    // Implement email sending logic here
    // You can use PHPMailer, Swift Mailer, or any other email library
    
    $subject = "Booking Confirmation - " . $booking_id;
    $message = "Your car rental booking has been confirmed. Booking ID: " . $booking_id;
    
    // For now, just return true
    // mail($user_email, $subject, $message);
    return true;
}

/**
 * Validate booking dates
 */
function validateBookingDates($pickup_date, $return_date) {
    $pickup = new DateTime($pickup_date);
    $return = new DateTime($return_date);
    $today = new DateTime();
    
    // Check if pickup date is in future
    if ($pickup < $today) {
        return ['valid' => false, 'message' => 'Pickup date cannot be in the past'];
    }
    
    // Check if return date is after pickup date
    if ($return <= $pickup) {
        return ['valid' => false, 'message' => 'Return date must be after pickup date'];
    }
    
    return ['valid' => true, 'message' => 'Dates are valid'];
}

/**
 * Calculate rental cost
 */
function calculateRentalCost($daily_rate, $pickup_date, $return_date, $driver_needed = false) {
    $pickup = new DateTime($pickup_date);
    $return = new DateTime($return_date);
    $days = max(1, $pickup->diff($return)->days);
    
    $car_cost = $daily_rate * $days;
    $driver_cost = $driver_needed ? 1000 * $days : 0;
    
    return [
        'days' => $days,
        'car_cost' => $car_cost,
        'driver_cost' => $driver_cost,
        'total_cost' => $car_cost + $driver_cost
    ];
}
?>