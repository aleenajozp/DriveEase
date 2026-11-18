// Slideshow background images
const images = [
  "assets/images/car1.jpeg",
  "assets/images/car2.jpeg",
  "assets/images/car3.jpeg",
  "assets/images/car4.jpeg"
];

let index = 0;
const heroSection = document.querySelector(".hero");

// Set initial background
heroSection.style.backgroundImage = `url('${images[index]}')`;

// Change every 5 seconds
setInterval(() => {
  index = (index + 1) % images.length;
  heroSection.style.backgroundImage = `url('${images[index]}')`;
}, 5000);
