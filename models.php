<?php
// Start session
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "car_rental");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all cars from database
$sql = "SELECT * FROM cars ORDER BY car_id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DriveEase - Models</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    * {margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif;}
    body {background:#f3f6fa; color:#333;}

    /* --- Navbar (copied from your reference) --- */
    .navbar {
      background-color: #000;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 60px;
      position: fixed;
      width: 100%;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 10px rgba(0,0,0,0.6);
    }
    .logo {font-size:28px; font-weight:700;}
    .logo span:nth-child(1){color:white;}
    .logo span:nth-child(2){color:#f24822;}
    .nav-links {display:flex; gap:25px; align-items:center;}
    .nav-links a {
      color:white; text-decoration:none; font-size:16px; font-weight:500;
      padding:10px 18px; border-radius:25px; transition:all 0.3s ease;
    }
    .nav-links a:hover {background-color:#f24822; color:white;}
    .signin-btn {
      background-color:transparent; border:2px solid #f24822;
      color:#f24822; font-weight:600; transition:all 0.3s ease;
    }
    .signin-btn:hover {background-color:#f24822; color:white;}

    /* --- Page Content --- */
    .container {max-width:1200px; margin:0 auto; padding:0 15px;}
    .header {text-align:center; margin:120px 0 30px;}
    .header h1 {font-size:2rem; color:#8B4513;}
    .filter-section {
      background:white; border-radius:8px; padding:20px; margin-bottom:30px;
      box-shadow:0 2px 10px rgba(0,0,0,0.05);
    }
    .filter-title {color:#8B4513; font-weight:600; margin-bottom:10px;}
    .filter-options {display:flex; flex-wrap:wrap; gap:15px;}
    .filter-group {flex:1; min-width:180px;}
    .filter-group label {font-weight:500; font-size:0.9rem;}
    .filter-group select {width:100%; padding:10px; border:1px solid #ddd; border-radius:4px;}
    .cars-grid {display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:25px; margin-bottom:40px;}
    .car-card {
      background:white; border-radius:8px; overflow:hidden;
      box-shadow:0 3px 15px rgba(0,0,0,0.08); transition:all 0.3s ease;
      animation:fadeIn 0.5s ease forwards;
    }
    .car-card:hover {transform:translateY(-5px);}
    .car-image {height:200px; background:#f0f0f0; position:relative;}
    .car-image img {width:100%; height:100%; object-fit:cover;}
    .car-badge {
      position:absolute; top:10px; right:10px;
      background:#4CAF50; color:white; padding:5px 10px;
      border-radius:4px; font-size:0.8rem; font-weight:bold;
    }
    .car-badge.unavailable {background:#d32f2f;}
    .car-details {padding:20px;}
    .car-title {font-weight:600; margin-bottom:10px;}
    .price-section {
      background:#f9f5f0; padding:10px; text-align:center;
      border-left:3px solid #8B4513; border-radius:6px; margin-bottom:10px;
    }
    .price-value {font-weight:700; font-size:1.2rem;}
    .availability-status {margin-bottom:10px; text-align:center;}
    .availability-status span {font-weight:bold;}
    .car-specs {display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.9rem; margin-bottom:15px;}
    .spec-item {display:flex; align-items:center; gap:8px; color:#555;}
    .book-now-btn {
      width:100%; padding:10px; background:#8B4513; color:white; border:none;
      border-radius:4px; font-weight:600; cursor:pointer;
    }
    .book-now-btn:disabled {background:gray; cursor:not-allowed;}
    .no-cars {text-align:center; padding:50px; color:#777;}
    @keyframes fadeIn {from{opacity:0; transform:translateY(20px);} to{opacity:1; transform:translateY(0);}}
  </style>
</head>
<body>

<!-- Navbar -->
<header class="navbar">
  <div class="logo">
    <span>DRIVE</span><span>Ease</span>
  </div>
  <nav class="nav-links">
    <a href="1.php">Home</a>
    <a href="models.php">Models</a>
    <a href="about.php">About</a>
    <a href="contact.php">Contact</a>
    <a href="login.php" class="signin-btn">Sign In</a>
  </nav>
</header>

<div class="container">
  <div class="header">
    <h1><i class="fas fa-car"></i> Available Cars</h1>
    <p>Choose from our premium collection of rental cars</p>
  </div>

  <!-- Filters -->
  <div class="filter-section">
    <div class="filter-title"><i class="fas fa-filter"></i> Filter Cars</div>
    <div class="filter-options">
      <div class="filter-group">
        <label for="fuelFilter">Fuel Type</label>
        <select id="fuelFilter">
          <option value="">All Fuel Types</option>
          <option value="Petrol">Petrol</option>
          <option value="Diesel">Diesel</option>
          <option value="Electric">Electric</option>
          <option value="Hybrid">Hybrid</option>
          <option value="CNG">CNG</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="seatFilter">Seating</label>
        <select id="seatFilter">
          <option value="">All Seating</option>
          <option value="2">2 Seater</option>
          <option value="4">4 Seater</option>
          <option value="5">5 Seater</option>
          <option value="7">7 Seater</option>
          <option value="8">8 Seater</option>
          <option value="9">9 Seater</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="transFilter">Transmission</label>
        <select id="transFilter">
          <option value="">All Transmission</option>
          <option value="Manual">Manual</option>
          <option value="Automatic">Automatic</option>
          <option value="CVT">CVT</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Cars Grid -->
  <div class="cars-grid" id="carsGrid">
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while($row = $result->fetch_assoc()): ?>
        <?php
          $status = $row['availability_status'] ?? "Available";
          $badgeClass = (strtolower($status) === "available") ? "" : "unavailable";
          $isAvailable = (strtolower($status) === "available");
        ?>
        <div class="car-card" 
             data-fuel="<?php echo htmlspecialchars($row['fuel_type']); ?>"
             data-seats="<?php echo htmlspecialchars($row['seating_capacity']); ?>"
             data-transmission="<?php echo htmlspecialchars($row['transmission']); ?>">
          <div class="car-badge <?php echo $badgeClass; ?>">
            <?php echo htmlspecialchars($status); ?>
          </div>
          <div class="car-image">
            <?php if (!empty($row['image'])): ?>
              <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
            <?php else: ?>
              <div class="placeholder-image"><i class="fas fa-car"></i></div>
            <?php endif; ?>
          </div>
          <div class="car-details">
            <h3 class="car-title"><?php echo htmlspecialchars($row['title']); ?></h3>
            <div class="price-section">
              <div>Rental Price</div>
              <div class="price-value">₹<?php echo htmlspecialchars($row['price']); ?>/day</div>
            </div>
            <div class="availability-status">
              Status: 
              <?php if ($isAvailable): ?>
                <span style="color:green;">Available</span>
              <?php else: ?>
                <span style="color:red;"><?php echo htmlspecialchars($status); ?></span>
              <?php endif; ?>
            </div>
            <div class="car-specs">
              <div class="spec-item"><i class="fas fa-gas-pump"></i><?php echo htmlspecialchars($row['fuel_type']); ?></div>
              <div class="spec-item"><i class="fas fa-users"></i><?php echo htmlspecialchars($row['seating_capacity']); ?> Seats</div>
              <div class="spec-item"><i class="fas fa-cogs"></i><?php echo htmlspecialchars($row['transmission']); ?></div>
            </div>
            <button class="book-now-btn" 
                    onclick="bookCar(<?php echo $row['car_id']; ?>, '<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>')" 
                    <?php echo $isAvailable ? "" : "disabled"; ?>>
              <i class="fas fa-calendar-check"></i> Book Now
            </button>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="no-cars">
        <i class="fas fa-car"></i>
        <h2>No Cars Available</h2>
        <p>Please check back later for available cars.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  const fuelFilter=document.getElementById('fuelFilter');
  const seatFilter=document.getElementById('seatFilter');
  const transFilter=document.getElementById('transFilter');
  const carCards=document.querySelectorAll('.car-card');

  function filterCars(){
    const f=fuelFilter.value, s=seatFilter.value, t=transFilter.value;
    carCards.forEach(card=>{
      const mf=!f||card.dataset.fuel===f;
      const ms=!s||card.dataset.seats===s;
      const mt=!t||card.dataset.transmission===t;
      card.style.display=(mf&&ms&&mt)?'block':'none';
    });
  }
  fuelFilter.addEventListener('change',filterCars);
  seatFilter.addEventListener('change',filterCars);
  transFilter.addEventListener('change',filterCars);

  function bookCar(carId, carTitle) {
    <?php if (isset($_SESSION['username'])): ?>
      if(confirm("Do you want to book \""+carTitle+"\"?")){
        window.location.href="login.php?car_id="+carId;
      }
    <?php else: ?>
      if(confirm("You need to login to book \""+carTitle+"\". Go to login page?")){
        window.location.href='login.php';
      }
    <?php endif; ?>
  }
</script>
</body>
</html>

<?php $conn->close(); ?>
