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

// Get car ID from URL
$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;

if ($car_id <= 0) {
    header("Location: index.php");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DriveEase - <?php echo htmlspecialchars($car['title']); ?> - Book</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f5f5f5; color:#333; }
.container { max-width:1200px; margin:0 auto; padding:20px; }
.header { text-align:center; margin-bottom:30px; padding:20px; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05);}
.header h1 { color:#8B4513; font-size:2.2rem; margin-bottom:10px; }
.booking-container { display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-bottom:30px; }
.car-info,.booking-form { background:#fff; border-radius:8px; padding:25px; box-shadow:0 3px 15px rgba(0,0,0,0.08);}
.car-image { width:100%; height:250px; border-radius:8px; overflow:hidden; margin-bottom:20px; }
.car-image img { width:100%; height:100%; object-fit:cover; }
.placeholder-image { width:100%; height:100%; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#ccc; }
.car-title { font-size:1.5rem; margin-bottom:15px; font-weight:600; }
.car-specs { display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px; }
.spec-item { display:flex; align-items:center; gap:10px; padding:10px; background:#f9f5f0; border-radius:6px; border-left:3px solid #8B4513; }
.spec-item i { color:#8B4513; }
.price-info { background:#8B4513; color:white; padding:20px; border-radius:8px; text-align:center; }
.price-label { font-size:1rem; margin-bottom:5px; }
.price-value { font-size:2rem; font-weight:bold; }
.form-title { color:#8B4513; font-size:1.3rem; margin-bottom:20px; font-weight:600; }
.form-group { margin-bottom:20px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
.form-group label { display:block; margin-bottom:8px; font-weight:500; }
.form-group input,.form-group select,.form-group textarea { width:100%; padding:12px; border:1px solid #ddd; border-radius:4px; font-size:1rem; }
.form-group input:focus,.form-group select:focus,.form-group textarea:focus { outline:none; border-color:#8B4513; box-shadow:0 0 0 2px rgba(139,69,19,0.1);}
.form-group input.error,.form-group select.error { border-color:#dc3545; }
.error-message { color:#dc3545; font-size:0.85rem; margin-top:5px; }
.checkbox-group { display:flex; align-items:center; gap:10px; padding:15px; background:#f9f5f0; border-radius:6px; border-left:3px solid #8B4513; }
.cost-summary { background:#f9f9f9; padding:20px; border-radius:8px; margin:20px 0; border:2px solid #8B4513; }
.cost-row { display:flex; justify-content:space-between; margin-bottom:10px; }
.cost-row.total { font-weight:bold; font-size:1.1rem; color:#8B4513; border-top:1px solid #ddd; padding-top:10px; }
.btn-group { display:flex; gap:15px; margin-top:20px; }
.btn { padding:12px 30px; border:none; border-radius:4px; font-size:1rem; font-weight:600; cursor:pointer; transition:0.3s; text-align:center; }
.btn-primary { background:#8B4513; color:white; flex:1; }
.btn-primary:hover { background:#73401a; }
.btn-primary:disabled { background:#ccc; cursor:not-allowed; }
@media(max-width:768px){.booking-container{grid-template-columns:1fr;}.form-row{grid-template-columns:1fr;}.btn-group{flex-direction:column;}}
</style>
</head>
<body>
<div class="container">
<div class="header">
    <h1><i class="fas fa-calendar-check"></i> Book Your Car</h1>
    <p>Complete your booking for <?php echo htmlspecialchars($car['title']); ?></p>
</div>

<div class="booking-container">
    <!-- Car Information -->
    <div class="car-info">
        <div class="car-image">
            <?php if (!empty($car['image'])): ?>
                <img src="<?php echo htmlspecialchars($car['image']); ?>" alt="<?php echo htmlspecialchars($car['title']); ?>">
            <?php else: ?>
                <div class="placeholder-image"><i class="fas fa-car fa-3x"></i></div>
            <?php endif; ?>
        </div>
        <h2 class="car-title"><?php echo htmlspecialchars($car['title']); ?></h2>
        <div class="car-specs">
            <div class="spec-item"><i class="fas fa-gas-pump"></i><span><?php echo htmlspecialchars($car['fuel_type']); ?></span></div>
            <div class="spec-item"><i class="fas fa-users"></i><span><?php echo htmlspecialchars($car['seating_capacity']); ?> Seats</span></div>
            <div class="spec-item"><i class="fas fa-cogs"></i><span><?php echo htmlspecialchars($car['transmission']); ?></span></div>
            <div class="spec-item"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($car['availability_status']); ?></span></div>
        </div>
        <div class="price-info">
            <div class="price-label">Daily Rental Rate</div>
            <div class="price-value">₹<?php echo number_format($car['price']); ?></div>
        </div>
    </div>

    <!-- Booking Form -->
    <div class="booking-form">
        <h3 class="form-title"><i class="fas fa-edit"></i> Booking Details</h3>
        <form id="bookingForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Pickup Date</label>
                    <input type="date" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                    <div class="error-message" id="start_date_error"></div>
                </div>
                <div class="form-group">
                    <label for="end_date">Return Date</label>
                    <input type="date" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                    <div class="error-message" id="end_date_error"></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="pickup_time">Pickup Time</label>
                    <input type="time" id="pickup_time" name="pickup_time" required>
                    <div class="error-message" id="pickup_time_error"></div>
                </div>
                <div class="form-group">
                    <label for="return_time">Return Time</label>
                    <input type="time" id="return_time" name="return_time" required>
                    <div class="error-message" id="return_time_error"></div>
                </div>
            </div>
            <div class="form-group">
                <label for="pickup_location">Pickup Location</label>
                <select id="pickup_location" name="pickup_location" required>
                    <option value="">Select Pickup Location</option>
                    <option value="Airport">Airport</option>
                    <option value="City Center">City Center</option>
                    <option value="Hotel">Hotel</option>
                    <option value="Railway Station">Railway Station</option>
                    <option value="Other">Other</option>
                </select>
                <div class="error-message" id="pickup_location_error"></div>
            </div>
            <div class="form-group">
                <label for="return_location">Return Location</label>
                <select id="return_location" name="return_location" required>
                    <option value="">Select Return Location</option>
                    <option value="Airport">Airport</option>
                    <option value="City Center">City Center</option>
                    <option value="Hotel">Hotel</option>
                    <option value="Railway Station">Railway Station</option>
                    <option value="Other">Other</option>
                </select>
                <div class="error-message" id="return_location_error"></div>
            </div>
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="driver_needed" name="driver_needed">
                    <label for="driver_needed"><i class="fas fa-user-tie"></i> Need a driver? (Additional ₹1,000/day)</label>
                </div>
            </div>
            <div class="form-group">
                <label for="special_requests">Special Requests (Optional)</label>
                <textarea id="special_requests" name="special_requests" rows="3" placeholder="Any special requirements or requests..."></textarea>
            </div>

            <!-- Cost Summary -->
            <div class="cost-summary" id="costSummary">
                <h4><i class="fas fa-calculator"></i> Cost Summary</h4>
                <div class="cost-row"><span>Rental Days:</span><span id="rentalDays">1 day</span></div>
                <div class="cost-row"><span>Car Rental:</span><span id="carCost">₹<?php echo number_format($car['price']); ?></span></div>
                <div class="cost-row" id="driverCostRow" style="display:none;"><span>Driver Cost:</span><span id="driverCost">₹0</span></div>
                <div class="cost-row total"><span>Total Cost:</span><span id="totalCost">₹<?php echo number_format($car['price']); ?></span></div>
            </div>

            <!-- Pay Now Button -->
            <div class="btn-group">
                <button type="button" id="payNowBtn" class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Pay Now
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<script>
const pickupDateInput = document.getElementById('start_date');
const returnDateInput = document.getElementById('end_date');
const pickupTimeInput = document.getElementById('pickup_time');
const returnTimeInput = document.getElementById('return_time');
const pickupLocationInput = document.getElementById('pickup_location');
const returnLocationInput = document.getElementById('return_location');
const driverCheckbox = document.getElementById('driver_needed');
const payNowBtn = document.getElementById('payNowBtn');
const dailyRate = <?php echo $car['price']; ?>;

// Validation functions
function showError(elementId, message) {
    const element = document.getElementById(elementId);
    const errorDiv = document.getElementById(elementId + '_error');
    element.classList.add('error');
    errorDiv.textContent = message;
}

function clearError(elementId) {
    const element = document.getElementById(elementId);
    const errorDiv = document.getElementById(elementId + '_error');
    element.classList.remove('error');
    errorDiv.textContent = '';
}

function validateForm() {
    let isValid = true;
    
    // Clear all errors first
    ['start_date', 'end_date', 'pickup_time', 'return_time', 'pickup_location', 'return_location'].forEach(clearError);
    
    // Validate dates
    if (!pickupDateInput.value) {
        showError('start_date', 'Please select a pickup date');
        isValid = false;
    }
    
    if (!returnDateInput.value) {
        showError('end_date', 'Please select a return date');
        isValid = false;
    } else if (pickupDateInput.value && new Date(returnDateInput.value) <= new Date(pickupDateInput.value)) {
        showError('end_date', 'Return date must be after pickup date');
        isValid = false;
    }
    
    // Validate times
    if (!pickupTimeInput.value) {
        showError('pickup_time', 'Please select a pickup time');
        isValid = false;
    }
    
    if (!returnTimeInput.value) {
        showError('return_time', 'Please select a return time');
        isValid = false;
    }
    
    // Validate locations
    if (!pickupLocationInput.value) {
        showError('pickup_location', 'Please select a pickup location');
        isValid = false;
    }
    
    if (!returnLocationInput.value) {
        showError('return_location', 'Please select a return location');
        isValid = false;
    }
    
    return isValid;
}

function calculateCost() {
    const pickupDate = new Date(pickupDateInput.value);
    const returnDate = new Date(returnDateInput.value);
    if (pickupDate && returnDate && returnDate > pickupDate) {
        const timeDiff = returnDate - pickupDate;
        const daysDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
        const carCost = dailyRate * daysDiff;
        const driverCost = driverCheckbox.checked ? 1000 * daysDiff : 0;
        const totalCost = carCost + driverCost;
        document.getElementById('rentalDays').textContent = daysDiff + (daysDiff > 1 ? ' days' : ' day');
        document.getElementById('carCost').textContent = '₹' + carCost.toLocaleString();
        document.getElementById('driverCost').textContent = '₹' + driverCost.toLocaleString();
        document.getElementById('totalCost').textContent = '₹' + totalCost.toLocaleString();
        document.getElementById('driverCostRow').style.display = driverCheckbox.checked ? 'flex' : 'none';
    }
}

// Event listeners
pickupDateInput.addEventListener('change', function() { 
    returnDateInput.min = this.value; 
    calculateCost();
    clearError('start_date');
});

returnDateInput.addEventListener('change', function() {
    calculateCost();
    clearError('end_date');
});

pickupTimeInput.addEventListener('change', function() {
    clearError('pickup_time');
});

returnTimeInput.addEventListener('change', function() {
    clearError('return_time');
});

pickupLocationInput.addEventListener('change', function() {
    clearError('pickup_location');
});

returnLocationInput.addEventListener('change', function() {
    clearError('return_location');
});

driverCheckbox.addEventListener('change', calculateCost);

// Pay Now button click
payNowBtn.addEventListener('click', function() {
    if (!validateForm()) {
        alert('Please fill in all required fields correctly.');
        return;
    }

    calculateCost();
    
    console.log("Pay Now Clicked - All validations passed");

    const url = 'payment.php?car_id=<?php echo $car_id; ?>' +
        '&pickup_date=' + encodeURIComponent(pickupDateInput.value) +
        '&return_date=' + encodeURIComponent(returnDateInput.value) +
        '&pickup_time=' + encodeURIComponent(pickupTimeInput.value) +
        '&return_time=' + encodeURIComponent(returnTimeInput.value) +
        '&pickup_location=' + encodeURIComponent(pickupLocationInput.value) +
        '&return_location=' + encodeURIComponent(returnLocationInput.value) +
        '&driver_needed=' + (driverCheckbox.checked ? 1 : 0) +
        '&special_requests=' + encodeURIComponent(document.getElementById('special_requests').value);
    
    console.log("Redirecting to: " + url);
    window.location.href = url;
});

// Initialize
calculateCost();
</script>
</body>
</html>
<?php $conn->close(); ?>