// ===== Enhanced JS with Animations =====

// Sidebar toggle (mobile)
const mobileToggle = document.getElementById('mobileMenuToggle');
const sidebar = document.getElementById('sidebar');
mobileToggle?.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    mobileToggle.querySelector('i').classList.toggle('fa-bars');
    mobileToggle.querySelector('i').classList.toggle('fa-times');
});

// Section switching with animation
document.querySelectorAll('.sidebar .menu-item').forEach(item => {
    item.addEventListener('click', () => {
        // Animate current section out
        const currentSection = document.querySelector('.dashboard-section.active');
        if (currentSection) {
            currentSection.style.animation = 'fadeOut 0.3s ease-out forwards';
            setTimeout(() => {
                currentSection.classList.remove('active');
                
                // Update menu items
                document.querySelectorAll('.sidebar .menu-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
                
                // Show new section with animation
                const section = item.getAttribute('data-section');
                const newSection = document.getElementById(section);
                if (newSection) {
                    newSection.classList.add('active');
                    newSection.style.animation = 'fadeIn 0.5s ease-out';
                }
                
                // Update page title
                const pageTitle = document.querySelector('.page-title h1');
                if (pageTitle && item.querySelector('span')) {
                    pageTitle.textContent = item.querySelector('span').textContent;
                }
                
                // Close sidebar on mobile after selection
                sidebar.classList.remove('open');
            }, 300);
        }
    });
});

// Modal functionality with animations
const modal = document.getElementById('carModal');
const addCarBtn = document.getElementById('addCarBtn');
const closeModal = document.getElementById('closeModal');
const cancelBtn = document.getElementById('cancelBtn');

addCarBtn?.addEventListener('click', () => {
    resetForm();
    document.getElementById('modalTitle').textContent = 'Add New Car';
    document.getElementById('submitBtn').textContent = 'Add Car';
    document.getElementById('submitBtn').name = 'add_car';
    document.getElementById('availabilityGroup').style.display = 'none';
    modal.style.display = 'block';
    
    // Trigger reflow for animation
    void modal.offsetWidth;
    document.querySelector('.modal-content').style.animation = 'bounceIn 0.4s ease-out';
});

function closeModalWithAnimation() {
    document.querySelector('.modal-content').style.animation = 'fadeOut 0.3s ease-out forwards';
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

closeModal?.addEventListener('click', closeModalWithAnimation);
cancelBtn?.addEventListener('click', closeModalWithAnimation);
window.addEventListener('click', (e) => { 
    if (e.target === modal) { 
        closeModalWithAnimation();
    }
});

// Image option toggle
const uploadBtn = document.getElementById('uploadBtn');
const urlBtn = document.getElementById('urlBtn');
const uploadContainer = document.getElementById('uploadContainer');
const urlContainer = document.getElementById('urlContainer');
const imageFile = document.getElementById('imageFile');
const imageUrl = document.getElementById('imageUrl');
const imageMode = document.getElementById('image_mode');

uploadBtn?.addEventListener('click', () => {
    uploadBtn.classList.add('active'); 
    urlBtn.classList.remove('active');
    uploadContainer.style.display = 'block';
    urlContainer.style.display = 'none';
    // Trigger animation
    void uploadContainer.offsetWidth;
    uploadContainer.style.animation = 'fadeIn 0.4s ease-out';
    if (imageUrl) imageUrl.disabled = true;
    if (imageFile) imageFile.disabled = false;
    if (imageMode) imageMode.value = 'upload';
});

urlBtn?.addEventListener('click', () => {
    urlBtn.classList.add('active'); 
    uploadBtn.classList.remove('active');
    urlContainer.style.display = 'block';
    uploadContainer.style.display = 'none';
    // Trigger animation
    void urlContainer.offsetWidth;
    urlContainer.style.animation = 'fadeIn 0.4s ease-out';
    if (imageFile) imageFile.value = ''; 
    if (imageFile) imageFile.disabled = true;
    if (imageUrl) imageUrl.disabled = false;
    if (imageMode) imageMode.value = 'url';
});

// File input filename
imageFile?.addEventListener('change', function() {
    const label = this.parentElement.querySelector('span');
    if (this.files[0]) {
        label.textContent = this.files[0].name;
        label.style.fontWeight = '500';
        label.style.color = 'var(--primary)';
    } else {
        label.textContent = 'Click to select image file or drag and drop';
        label.style.fontWeight = 'normal';
        label.style.color = 'inherit';
    }
});

// Edit car function
function editCar(carData) {
    resetForm();
    
    // Fill form with car data
    document.getElementById('modalTitle').textContent = 'Edit Car';
    document.getElementById('submitBtn').textContent = 'Update Car';
    document.getElementById('submitBtn').name = 'edit_car';
    document.getElementById('availabilityGroup').style.display = 'block';
    
    document.getElementById('carId').value = carData.car_id;
    document.getElementById('currentImage').value = carData.image || '';
    document.getElementById('title').value = carData.title || '';
    document.getElementById('fuel_type').value = carData.fuel_type || '';
    document.getElementById('seating_capacity').value = carData.seating_capacity || '';
    document.getElementById('transmission').value = carData.transmission || '';
    document.getElementById('price').value = carData.price || '';
    document.getElementById('availability_status').value = carData.availability_status || 'Available';
    
    // Show current image if exists
    if (carData.image) {
        document.getElementById('currentImagePreview').style.display = 'block';
        document.getElementById('currentImageDisplay').src = carData.image;
    } else {
        document.getElementById('currentImagePreview').style.display = 'none';
    }
    
    modal.style.display = 'block';
    
    // Trigger reflow for animation
    void modal.offsetWidth;
    document.querySelector('.modal-content').style.animation = 'bounceIn 0.4s ease-out';
}

// Reset form function
function resetForm() {
    document.getElementById('carForm').reset();
    document.getElementById('carId').value = '';
    document.getElementById('currentImage').value = '';
    document.getElementById('currentImagePreview').style.display = 'none';
    
    // Reset image options to upload mode
    uploadBtn.classList.add('active'); 
    urlBtn.classList.remove('active');
    uploadContainer.style.display = 'block';
    urlContainer.style.display = 'none';
    if (imageUrl) imageUrl.disabled = true;
    if (imageFile) imageFile.disabled = false;
    if (imageMode) imageMode.value = 'upload';
    
    // Reset file input label
    const label = document.querySelector('#uploadContainer span');
    if (label) {
        label.textContent = 'Click to select image file or drag and drop';
        label.style.fontWeight = 'normal';
        label.style.color = 'inherit';
    }
}

// Delete confirm with animation
function confirmDelete() { 
    return confirm("Are you sure you want to delete this car?"); 
}

// Add fadeOut animation to CSS
document.head.insertAdjacentHTML('beforeend', '<style>@keyframes fadeOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(10px); } }</style>');

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Animate cards on page load
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});

window.confirmDelete = confirmDelete;
window.editCar = editCar;