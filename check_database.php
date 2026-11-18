<?php
// Database troubleshooting script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection and Table Check</h2>";

// Database connection
$conn = new mysqli("localhost", "root", "", "car_rental");
if ($conn->connect_error) {
    die("<p style='color:red;'>Connection failed: " . $conn->connect_error . "</p>");
}
echo "<p style='color:green;'>✓ Database connection successful</p>";

// Check if bookings table exists
$result = $conn->query("SHOW TABLES LIKE 'bookings'");
if ($result->num_rows > 0) {
    echo "<p style='color:green;'>✓ 'bookings' table exists</p>";
    
    // Show table structure
    echo "<h3>Bookings Table Structure:</h3>";
    $result = $conn->query("DESCRIBE bookings");
    echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
    echo "<tr style='background:#f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check existing bookings
    $result = $conn->query("SELECT COUNT(*) as count FROM bookings");
    $row = $result->fetch_assoc();
    echo "<p>Total bookings in database: <strong>{$row['count']}</strong></p>";
    
    if ($row['count'] > 0) {
        echo "<h3>Recent Bookings:</h3>";
        $result = $conn->query("SELECT * FROM bookings ORDER BY created_at DESC LIMIT 5");
        echo "<table border='1' style='border-collapse:collapse; margin:10px 0; font-size:12px;'>";
        echo "<tr style='background:#f0f0f0;'><th>Booking ID</th><th>User ID</th><th>Car ID</th><th>Total Amount</th><th>Payment Status</th><th>Created At</th></tr>";
        while ($booking = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$booking['booking_id']}</td>";
            echo "<td>{$booking['user_id']}</td>";
            echo "<td>{$booking['car_id']}</td>";
            echo "<td>₹{$booking['total_amount']}</td>";
            echo "<td>{$booking['payment_status']}</td>";
            echo "<td>{$booking['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} else {
    echo "<p style='color:red;'>✗ 'bookings' table does not exist</p>";
    echo "<p>Please create the table using the SQL provided.</p>";
    echo "<h3>Create table SQL:</h3>";
    echo "<textarea rows='10' cols='80' readonly>";
    echo "CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` varchar(20) NOT NULL UNIQUE,
  `user_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `pickup_date` date NOT NULL,
  `return_date` date NOT NULL,
  `pickup_time` time NOT NULL,
  `return_time` time NOT NULL,
  `pickup_location` varchar(100) NOT NULL,
  `return_location` varchar(100) NOT NULL,
  `driver_needed` tinyint(1) DEFAULT 0,
  `special_requests` text,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_id` varchar(100) NOT NULL,
  `payment_status` enum('Pending','Paid','Failed','Refunded') DEFAULT 'Pending',
  `booking_status` enum('Pending','Confirmed','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_car_id` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    echo "</textarea>";
}

// Check session data
session_start();
echo "<h3>Session Information:</h3>";
if (isset($_SESSION['username'])) {
    echo "<p>Username: <strong>{$_SESSION['username']}</strong></p>";
    echo "<p>User ID: <strong>" . ($_SESSION['user_id'] ?? 'Not set - This might be the problem!') . "</strong></p>";
    echo "<p>Email: <strong>" . ($_SESSION['email'] ?? 'Not set') . "</strong></p>";
} else {
    echo "<p style='color:red;'>No active session found</p>";
}

// Test a simple insert
echo "<h3>Database Insert Test:</h3>";
$test_sql = "INSERT INTO bookings (booking_id, user_id, car_id, pickup_date, return_date, pickup_time, return_time, pickup_location, return_location, driver_needed, special_requests, total_amount, payment_id, payment_status, booking_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', 'Confirmed', NOW())";

$test_stmt = $conn->prepare($test_sql);
if ($test_stmt) {
    echo "<p style='color:green;'>✓ SQL prepare successful</p>";
    
    // Test values
    $test_booking_id = 'TEST' . time();
    $test_user_id = 1;
    $test_car_id = 1;
    $test_pickup_date = '2025-01-20';
    $test_return_date = '2025-01-22';
    $test_pickup_time = '10:00:00';
    $test_return_time = '18:00:00';
    $test_pickup_location = 'Airport';
    $test_return_location = 'Airport';
    $test_driver_needed = 0;
    $test_special_requests = 'Test booking';
    $test_total_amount = 2400.00;
    $test_payment_id = 'test_payment_' . time();
    
    $test_stmt->bind_param("siisssssisss", 
        $test_booking_id, $test_user_id, $test_car_id, $test_pickup_date, $test_return_date, 
        $test_pickup_time, $test_return_time, $test_pickup_location, $test_return_location, 
        $test_driver_needed, $test_special_requests, $test_total_amount, $test_payment_id
    );
    
    if ($test_stmt->execute()) {
        echo "<p style='color:green;'>✓ Test booking inserted successfully with ID: $test_booking_id</p>";
        
        // Clean up test data
        $conn->query("DELETE FROM bookings WHERE booking_id = '$test_booking_id'");
        echo "<p>Test data cleaned up</p>";
    } else {
        echo "<p style='color:red;'>✗ Test insert failed: " . $test_stmt->error . "</p>";
    }
} else {
    echo "<p style='color:red;'>✗ SQL prepare failed: " . $conn->error . "</p>";
}

// Check error logs
echo "<h3>PHP Error Log Check:</h3>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $recent_errors = array_slice(file($error_log), -10);
    echo "<p>Recent errors from: $error_log</p>";
    echo "<textarea rows='10' cols='100' readonly>";
    foreach ($recent_errors as $error) {
        echo htmlspecialchars($error);
    }
    echo "</textarea>";
} else {
    echo "<p>Error log not found or not configured</p>";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { width: 100%; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
textarea { width: 100%; margin: 10px 0; }
</style>