<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Razorpay Configuration
$razorpay_key_id = 'rzp_test_RH7LqPTsspswiS'; // Replace with your actual Razorpay key
$razorpay_secret = 'fhUoE9Sk7B6C9DGiLwacwXu4'; // Replace with your actual secret

// Database connection
$conn = new mysqli("localhost", "root", "", "car_rental");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user ID from username in session
$username = $_SESSION['username'];
$user_sql = "SELECT user_id FROM users WHERE username = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows == 0) {
    echo "<script>alert('User not found. Please login again.'); window.location.href='login.php';</script>";
    exit();
}

$user_data = $user_result->fetch_assoc();
$user_id = $user_data['user_id'];

// Get booking details from URL
$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;
$pickup_date = $_GET['pickup_date'] ?? '';
$return_date = $_GET['return_date'] ?? '';
$pickup_time = $_GET['pickup_time'] ?? '';
$return_time = $_GET['return_time'] ?? '';
$pickup_location = $_GET['pickup_location'] ?? '';
$return_location = $_GET['return_location'] ?? '';
$driver_needed = isset($_GET['driver_needed']) ? intval($_GET['driver_needed']) : 0;
$special_requests = $_GET['special_requests'] ?? '';

// DEBUG: Add this to check if return_location is being received
error_log("DEBUG - Pickup Location: " . $pickup_location);
error_log("DEBUG - Return Location: " . $return_location);

// Validate required fields
if (!$car_id || !$pickup_date || !$return_date || !$pickup_time || !$return_time || !$pickup_location || !$return_location) {
    echo "<script>alert('Missing required booking information. Pickup: $pickup_location, Return: $return_location'); window.location.href='index.php';</script>";
    exit();
}

// Fetch car details
$sql = "SELECT * FROM cars WHERE car_id = ? AND availability_status = 'Available'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<script>alert('Car not available for booking.'); window.location.href='index.php';</script>";
    exit();
}

$car = $result->fetch_assoc();

// Calculate costs
$pickup_datetime = new DateTime($pickup_date);
$return_datetime = new DateTime($return_date);
$interval = $pickup_datetime->diff($return_datetime);
$rental_days = max(1, $interval->days);

$car_cost = $car['price'] * $rental_days;
$driver_cost = $driver_needed ? 1000 * $rental_days : 0;
$total_amount = $car_cost + $driver_cost;

// Convert to paise (Razorpay requires amount in paise)
$razorpay_amount = $total_amount * 100;

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['razorpay_payment_id'])) {
    require_once('razorpay-php/Razorpay.php');

    $api = new Razorpay\Api\Api($razorpay_key_id, $razorpay_secret);

    try {
        $attributes = array(
            'razorpay_order_id' => $_POST['razorpay_order_id'],
            'razorpay_payment_id' => $_POST['razorpay_payment_id'],
            'razorpay_signature' => $_POST['razorpay_signature']
        );

        $api->utility->verifyPaymentSignature($attributes);

        // Payment verified, proceed to insert booking
        $booking_id = 'BK' . time() . rand(100, 999);
        $payment_id = $_POST['razorpay_payment_id'];

        // DEBUG: Log the values before insertion
        error_log("DEBUG - Before INSERT - Pickup Location: " . $pickup_location);
        error_log("DEBUG - Before INSERT - Return Location: " . $return_location);

        $insert_sql = "INSERT INTO bookings (
            booking_id, user_id, car_id, pickup_date, return_date, pickup_time, return_time,
            pickup_location, return_location, driver_needed, special_requests, total_amount,
            payment_id, payment_status, booking_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', 'Confirmed', NOW())";

        $insert_stmt = $conn->prepare($insert_sql);
        
        // FIXED: Corrected parameter binding - the issue was the parameter type string
        // Changed from "siisssssissis" to proper types
        $insert_stmt->bind_param(
            "siissssssisis", // Fixed: s=string, i=integer, s=string pattern
            $booking_id,     // s - string
            $user_id,        // i - integer 
            $car_id,         // i - integer
            $pickup_date,    // s - string (date)
            $return_date,    // s - string (date)
            $pickup_time,    // s - string (time)
            $return_time,    // s - string (time)
            $pickup_location,// s - string
            $return_location,// s - string (THIS WAS THE ISSUE!)
            $driver_needed,  // i - integer (tinyint)
            $special_requests,// s - string
            $total_amount,   // i - we'll treat as integer for cents or use 'd' for decimal
            $payment_id      // s - string
        );

        if ($insert_stmt->execute()) {
            // DEBUG: Verify the insertion
            $verify_sql = "SELECT pickup_location, return_location FROM bookings WHERE booking_id = ?";
            $verify_stmt = $conn->prepare($verify_sql);
            $verify_stmt->bind_param("s", $booking_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_data = $verify_result->fetch_assoc();
            
            error_log("DEBUG - After INSERT - Pickup: " . $verify_data['pickup_location'] . ", Return: " . $verify_data['return_location']);
            
            // Update car availability
            $update_sql = "UPDATE cars SET availability_status = 'Booked' WHERE car_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $car_id);
            $update_stmt->execute();

            // Redirect to success page
            header("Location: booking_success.php?booking_id=" . $booking_id);
            exit();
        } else {
            error_log("DEBUG - INSERT failed: " . $insert_stmt->error);
            $error_message = "Booking failed. Please contact support. Error: " . $insert_stmt->error;
        }
    } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
        $error_message = "Payment verification failed. Please try again.";
    } catch (Exception $e) {
        $error_message = "Payment processing failed. Please try again.";
    }
}

// Create Razorpay order (if payment not yet made)
if (!isset($error_message)) {
    require_once('razorpay-php/Razorpay.php');
    $api = new Razorpay\Api\Api($razorpay_key_id, $razorpay_secret);

    $order_data = [
        'receipt' => 'rcpt_' . time(),
        'amount' => $razorpay_amount,
        'currency' => 'INR'
    ];

    $razorpay_order = $api->order->create($order_data);
    $razorpay_order_id = $razorpay_order['id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DriveEase - Payment</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f5f5f5; color:#333; }
.container { max-width:800px; margin:0 auto; padding:20px; }
.header { text-align:center; margin-bottom:30px; padding:20px; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
.header h1 { color:#8B4513; font-size:2.2rem; margin-bottom:10px; }
.payment-container { background:#fff; border-radius:8px; padding:30px; box-shadow:0 3px 15px rgba(0,0,0,0.08); }
.booking-summary { background:#f9f5f0; padding:20px; border-radius:8px; margin-bottom:25px; border-left:4px solid #8B4513; }
.car-info { display:flex; align-items:center; gap:20px; padding:20px; background:#f8f9fa; border-radius:8px; margin-bottom:20px; }
.car-image { width:120px; height:80px; border-radius:8px; overflow:hidden; }
.car-image img { width:100%; height:100%; object-fit:cover; }
.car-placeholder { width:100%; height:100%; background:#e9ecef; display:flex; align-items:center; justify-content:center; color:#6c757d; }
.summary-row { display:flex; justify-content:space-between; margin-bottom:10px; padding:5px 0; }
.summary-row.total { font-weight:bold; font-size:1.1rem; color:#8B4513; border-top:1px solid #ddd; padding-top:15px; margin-top:15px; }
.user-info { background:#e8f4f8; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #17a2b8; }
.payment-btn { padding:15px 30px; background:#28a745; color:white; border:none; border-radius:4px; font-size:1.1rem; font-weight:600; cursor:pointer; width:100%; transition:0.3s; display:flex; align-items:center; justify-content:center; gap:10px; }
.payment-btn:hover { background:#218838; }
.error { color:#dc3545; margin-bottom:15px; padding:15px; background:#f8d7da; border-radius:4px; border:1px solid #f5c6cb; }
.loading { text-align:center; color:#666; font-size:1.1rem; }
.loading i { animation:spin 1s linear infinite; margin-right:10px; }
@keyframes spin { 0% { transform:rotate(0deg); } 100% { transform:rotate(360deg); } }
</style>
</head>
<body>
<div class="container">
<div class="header">
    <h1><i class="fas fa-credit-card"></i> Complete Payment</h1>
    <p>Secure payment powered by Razorpay</p>
</div>

<div class="payment-container">
    <?php if (isset($error_message)): ?>
    <div class="error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        <br><br>
        <a href="booking.php?car_id=<?php echo $car_id; ?>" style="color:#721c24;">← Go back to booking</a>
    </div>
    <?php endif; ?>

    <!-- User Information -->
    <div class="user-info">
        <h4><i class="fas fa-user"></i> Booking for: <?php echo htmlspecialchars($username); ?></h4>
        <small>User ID: <?php echo $user_id; ?></small>
    </div>

    <!-- Car Information -->
    <div class="car-info">
        <div class="car-image">
            <?php if (!empty($car['image'])): ?>
                <img src="<?php echo htmlspecialchars($car['image']); ?>" alt="<?php echo htmlspecialchars($car['title']); ?>">
            <?php else: ?>
                <div class="car-placeholder"><i class="fas fa-car fa-2x"></i></div>
            <?php endif; ?>
        </div>
        <div>
            <h3><?php echo htmlspecialchars($car['title']); ?></h3>
            <div style="display:flex; gap:20px; margin-top:10px; font-size:0.9rem; color:#666;">
                <span><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($car['fuel_type']); ?></span>
                <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($car['seating_capacity']); ?> Seats</span>
                <span><i class="fas fa-cogs"></i> <?php echo htmlspecialchars($car['transmission']); ?></span>
            </div>
        </div>
    </div>

    <!-- Booking Summary -->
    <h3><i class="fas fa-receipt"></i> Booking Summary</h3>
    <div class="booking-summary">
        <div class="summary-row"><span>Pickup Date:</span><span><?php echo date('M d, Y', strtotime($pickup_date)); ?></span></div>
        <div class="summary-row"><span>Return Date:</span><span><?php echo date('M d, Y', strtotime($return_date)); ?></span></div>
        <div class="summary-row"><span>Pickup Time:</span><span><?php echo date('h:i A', strtotime($pickup_time)); ?></span></div>
        <div class="summary-row"><span>Return Time:</span><span><?php echo date('h:i A', strtotime($return_time)); ?></span></div>
        <div class="summary-row"><span>Pickup Location:</span><span><?php echo htmlspecialchars($pickup_location); ?></span></div>
        <div class="summary-row"><span>Return Location:</span><span><?php echo htmlspecialchars($return_location); ?></span></div>
        <div class="summary-row"><span>Duration:</span><span><?php echo $rental_days; ?> day<?php echo $rental_days > 1 ? 's' : ''; ?></span></div>
        <div class="summary-row"><span>Car Rental (₹<?php echo number_format($car['price']); ?>/day):</span><span>₹<?php echo number_format($car_cost); ?></span></div>
        <?php if ($driver_needed): ?>
        <div class="summary-row"><span>Driver Service (₹1,000/day):</span><span>₹<?php echo number_format($driver_cost); ?></span></div>
        <?php endif; ?>
        <?php if (!empty($special_requests)): ?>
        <div class="summary-row" style="border-top:1px solid #ddd; padding-top:10px; margin-top:10px;"><span>Special Requests:</span><span style="font-style:italic; font-size:0.9rem;"><?php echo htmlspecialchars($special_requests); ?></span></div>
        <?php endif; ?>
        <div class="summary-row total"><span>Total Amount:</span><span>₹<?php echo number_format($total_amount); ?></span></div>
    </div>

    <?php if (!isset($error_message)): ?>
    <button id="payBtn" class="payment-btn">
        <i class="fas fa-shield-alt"></i> Pay ₹<?php echo number_format($total_amount); ?> Securely
    </button>
    
    <div id="loadingMsg" class="loading" style="display:none; margin-top:20px;">
        <i class="fas fa-spinner"></i> Redirecting to Razorpay...
    </div>
    <?php endif; ?>
</div>
</div>

<?php if (!isset($error_message)): ?>
<script>
document.getElementById('payBtn').addEventListener('click', function() {
    // Show loading message
    document.getElementById('payBtn').style.display = 'none';
    document.getElementById('loadingMsg').style.display = 'block';
    
    const options = {
        "key": "<?php echo $razorpay_key_id; ?>",
        "amount": "<?php echo $razorpay_amount; ?>",
        "currency": "INR",
        "name": "DriveEase Car Rental",
        "description": "Car Booking - <?php echo htmlspecialchars($car['title']); ?>",
        "order_id": "<?php echo $razorpay_order_id; ?>",
        "prefill": {
            "name": "<?php echo htmlspecialchars($username); ?>"
        },
        "theme": {
            "color": "#8B4513"
        },
        "handler": function (response) {
            // Submit form with payment info
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = ''; // current page

            const fields = {
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_order_id: response.razorpay_order_id,
                razorpay_signature: response.razorpay_signature
            };

            for (const key in fields) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
        },
        "modal": {
            "ondismiss": function() {
                // Hide loading message and show button again
                document.getElementById('loadingMsg').style.display = 'none';
                document.getElementById('payBtn').style.display = 'flex';
            }
        }
    };

    const rzp = new Razorpay(options);
    rzp.open();
});
</script>
<?php endif; ?>
</body>
</html>

<?php $conn->close(); ?>