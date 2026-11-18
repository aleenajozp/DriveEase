<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "car_rental");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get booking ID from URL
$booking_id = $_GET['booking_id'] ?? '';

if (!$booking_id) {
    header("Location: index.php");
    exit();
}

// Fetch booking details with user information using user_id
$sql = "SELECT b.*, c.title as car_title, c.image as car_image, c.fuel_type, c.seating_capacity, c.transmission,
        u.username, u.email_id 
        FROM bookings b 
        JOIN cars c ON b.car_id = c.car_id 
        JOIN users u ON b.user_id = u.user_id
        WHERE b.booking_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<script>alert('Booking not found.'); window.location.href='1.php';</script>";
    exit();
}

$booking = $result->fetch_assoc();
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<script>alert('Booking not found.'); window.location.href='index.php';</script>";
    exit();
}

$booking = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DriveEase - CarBooked</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f5f5f5; color:#333; }
.container { max-width:800px; margin:0 auto; padding:20px; }
.success-header { text-align:center; margin-bottom:30px; padding:30px; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
.success-icon { font-size:4rem; color:#28a745; margin-bottom:20px; }
.success-header h1 { color:#28a745; font-size:2.2rem; margin-bottom:10px; }
.booking-details { background:#fff; border-radius:8px; padding:30px; box-shadow:0 3px 15px rgba(0,0,0,0.08); margin-bottom:20px; }
.booking-id { background:#28a745; color:white; padding:15px; border-radius:8px; text-align:center; margin-bottom:25px; }
.booking-id h3 { font-size:1.3rem; }
.detail-section { margin-bottom:25px; }
.detail-section h4 { color:#8B4513; margin-bottom:15px; font-size:1.2rem; display:flex; align-items:center; gap:10px; }
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px; }
.detail-item { padding:12px; background:#f9f5f0; border-radius:6px; border-left:3px solid #8B4513; }
.detail-label { font-weight:600; color:#666; font-size:0.9rem; display:block; margin-bottom:5px; }
.detail-value { color:#333; font-size:1rem; }
.car-info { display:flex; align-items:center; gap:20px; padding:20px; background:#f8f9fa; border-radius:8px; margin-bottom:20px; }
.car-image { width:120px; height:80px; border-radius:8px; overflow:hidden; }
.car-image img { width:100%; height:100%; object-fit:cover; }
.car-placeholder { width:100%; height:100%; background:#e9ecef; display:flex; align-items:center; justify-content:center; color:#6c757d; }
.cost-summary { background:#f9f9f9; padding:20px; border-radius:8px; border:2px solid:#28a745; }
.cost-row { display:flex; justify-content:space-between; margin-bottom:10px; }
.cost-row.total { font-weight:bold; font-size:1.2rem; color:#28a745; border-top:1px solid #ddd; padding-top:15px; margin-top:15px; }
.action-buttons { display:flex; gap:15px; margin-top:30px; justify-content:center; }
.btn { padding:12px 25px; border:none; border-radius:6px; font-size:1rem; font-weight:600; cursor:pointer; transition:0.3s; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
.btn-primary { background:#8B4513; color:white; }
.btn-primary:hover { background:#73401a; }
.btn-secondary { background:#6c757d; color:white; }
.btn-secondary:hover { background:#545b62; }
.status-badge { display:inline-flex; align-items:center; gap:5px; padding:6px 12px; border-radius:20px; font-size:0.85rem; font-weight:600; }
.status-confirmed { background:#d4edda; color:#155724; }
.status-paid { background:#cce5ff; color:#004085; }
.print-section { text-align:center; margin-top:20px; }
.customer-info { background:#f8f9fa; padding:20px; border-radius:8px; margin-bottom:20px; }
@media(max-width:768px) { .detail-grid{grid-template-columns:1fr;} .car-info{flex-direction:column;text-align:center;} .action-buttons{flex-direction:column;} }
@media print { .action-buttons, .print-section { display:none; } body { background:white; } }
</style>
</head>
<body>
<div class="container">
<!-- Success Header -->
<div class="success-header">
    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
    <h1>Booking Confirmed!</h1>
    <p>Your car rental has been successfully booked and payment processed.</p>
</div>

<!-- Booking Details -->
<div class="booking-details">
    <!-- Booking ID -->
    <div class="booking-id">
        <h3><i class="fas fa-ticket-alt"></i> Booking ID: <?php echo htmlspecialchars($booking['booking_id']); ?></h3>
        <p>Keep this ID for future reference</p>
    </div>

    <!-- Customer Information -->
    <div class="detail-section">
        <h4><i class="fas fa-user"></i> Customer Information</h4>
        <div class="customer-info">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Customer Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['username']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email Address</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['email_id']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Car Information -->
    <div class="detail-section">
        <h4><i class="fas fa-car"></i> Vehicle Details</h4>
        <div class="car-info">
            <div class="car-image">
                <?php if (!empty($booking['car_image'])): ?>
                    <img src="<?php echo htmlspecialchars($booking['car_image']); ?>" alt="<?php echo htmlspecialchars($booking['car_title']); ?>">
                <?php else: ?>
                    <div class="car-placeholder"><i class="fas fa-car fa-2x"></i></div>
                <?php endif; ?>
            </div>
            <div>
                <h3><?php echo htmlspecialchars($booking['car_title']); ?></h3>
                <div style="display:flex; gap:20px; margin-top:10px; font-size:0.9rem; color:#666;">
                    <span><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($booking['fuel_type']); ?></span>
                    <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($booking['seating_capacity']); ?> Seats</span>
                    <span><i class="fas fa-cogs"></i> <?php echo htmlspecialchars($booking['transmission']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Rental Details -->
    <div class="detail-section">
        <h4><i class="fas fa-calendar-alt"></i> Rental Information</h4>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Pickup Date</span>
                <span class="detail-value"><?php echo date('M d, Y', strtotime($booking['pickup_date'])); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Return Date</span>
                <span class="detail-value"><?php echo date('M d, Y', strtotime($booking['return_date'])); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Pickup Time</span>
                <span class="detail-value"><?php echo date('h:i A', strtotime($booking['pickup_time'])); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Return Time</span>
                <span class="detail-value"><?php echo date('h:i A', strtotime($booking['return_time'])); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Pickup Location</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['pickup_location'] ?? 'Not specified'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Return Location</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['return_location'] ?? 'Not specified'); ?></span>
            </div>
        </div>
    </div>

    <!-- Additional Services -->
    <?php if ($booking['driver_needed'] || !empty($booking['special_requests'])): ?>
    <div class="detail-section">
        <h4><i class="fas fa-plus-circle"></i> Additional Services</h4>
        <?php if ($booking['driver_needed']): ?>
            <div class="detail-item" style="margin-bottom:10px;">
                <span class="detail-label">Driver Service</span>
                <span class="detail-value"><i class="fas fa-check text-success"></i> Included</span>
            </div>
        <?php endif; ?>
        <?php if (!empty($booking['special_requests'])): ?>
            <div class="detail-item">
                <span class="detail-label">Special Requests</span>
                <span class="detail-value"><?php echo htmlspecialchars($booking['special_requests']); ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Payment Summary -->
    <div class="detail-section">
        <h4><i class="fas fa-receipt"></i> Payment Summary</h4>
        <div class="cost-summary">
            <div class="cost-row">
                <span>Rental Duration:</span>
                <span><?php 
                    $pickup = new DateTime($booking['pickup_date']);
                    $return = new DateTime($booking['return_date']);
                    $days = max(1, $pickup->diff($return)->days);
                    echo $days . ' day' . ($days > 1 ? 's' : '');
                ?></span>
            </div>
            <div class="cost-row">
                <span>Total Amount Paid:</span>
                <span>₹<?php echo number_format($booking['total_amount']); ?></span>
            </div>
            <div class="cost-row">
                <span>Payment Status:</span>
                <span><span class="status-badge status-paid"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($booking['payment_status']); ?></span></span>
            </div>
            <div class="cost-row">
                <span>Booking Status:</span>
                <span><span class="status-badge status-confirmed"><i class="fas fa-calendar-check"></i> <?php echo htmlspecialchars($booking['booking_status']); ?></span></span>
            </div>
            <div class="cost-row total">
                <span>Payment ID:</span>
                <span style="font-size:0.9rem;"><?php echo htmlspecialchars($booking['payment_id']); ?></span>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="user.php" class="btn btn-primary">
            <i class="fas fa-home"></i> Back to Home
        </a>
        <a href="userprofile.php" class="btn btn-secondary">
            <i class="fas fa-list"></i> View All Bookings
        </a>
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="fas fa-print"></i> Print Receipt
        </button>
    </div>
</div>

<!-- Print Instructions -->
<div class="print-section">
    <p style="color:#666; font-size:0.9rem; margin-top:20px;">
        <i class="fas fa-info-circle"></i> Please save or print this confirmation for your records. 
        You may need to present this at the time of pickup.
    </p>
</div>
</div>

<script>
// Auto-scroll to top on page load
window.scrollTo(0, 0);

// Show print dialog if requested
<?php if (isset($_GET['print'])): ?>
setTimeout(function() { window.print(); }, 1000);
<?php endif; ?>
</script>
</body>
</html>

<?php $conn->close(); ?>