const confetti = document.getElementById('confetti');
const ctx = confetti.getContext('2d');

let particles = [];
const colors = ['#1e3a8a', '#14b8a6', '#facc15']; // standardized palette

function resizeCanvas() {
  confetti.width = window.innerWidth;
  confetti.height = window.innerHeight;
}

function createParticle() {
  return {
    x: Math.random() * confetti.width,
    y: Math.random() * confetti.height - confetti.height,
    size: Math.random() * 8 + 4,
    color: colors[Math.floor(Math.random() * colors.length)],
    speed: Math.random() * 3 + 2,
    rotation: Math.random() * 360,
    rotationSpeed: Math.random() * 10 - 5
  };
}

function drawParticle(p) {
  ctx.save();
  ctx.translate(p.x, p.y);
  ctx.rotate((p.rotation * Math.PI) / 180);
  ctx.fillStyle = p.color;
  ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
  ctx.restore();
}

function updateParticles() {
  ctx.clearRect(0, 0, confetti.width, confetti.height);
  particles.forEach((p, i) => {
    p.y += p.speed;
    p.rotation += p.rotationSpeed;
    if (p.y > confetti.height) particles[i] = createParticle();
    drawParticle(p);
  });
  requestAnimationFrame(updateParticles);
}

function initParticles() {
  particles = Array.from({ length: 150 }, createParticle);
  updateParticles();
}

window.addEventListener('resize', resizeCanvas);
resizeCanvas();
initParticles();
