const images = [
  "assets/images/car1.jpeg",
  "assets/images/car2.jpeg",
  "assets/images/car3.jpeg",
  "assets/images/car4.jpeg"
];

let index = 0;
const slideshow = document.querySelector(".background-slideshow");

function changeBackground() {
  slideshow.style.animation = "fadeSlide 1s ease-in-out";
  slideshow.style.backgroundImage = `url(${images[index]})`;
  index = (index + 1) % images.length;
}

changeBackground();
setInterval(changeBackground, 5000);




