/* ==========================================
   ResQLink - Main JavaScript File
   ========================================== */

// Document Ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('ResQLink System Loaded');
    initializePage();
});

/**
 * Initialize page functionality
 */
function initializePage() {
    // Add active class to current nav link
    updateActiveNavLink();
    
    // Add scroll animation to elements
    observeElements();
    
    // Add button hover effects
    addButtonEffects();
}

/**
 * Update active navigation link based on scroll position
 */
function updateActiveNavLink() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    window.addEventListener('scroll', function() {
        let current = '';
        const sections = document.querySelectorAll('section[id]');
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            
            if (scrollY >= (sectionTop - 200)) {
                current = section.getAttribute('id');
            }
        });
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href').substring(1) === current) {
                link.classList.add('text-danger');
            } else {
                link.classList.remove('text-danger');
            }
        });
    });
}

/**
 * Observe elements for scroll animations
 */
function observeElements() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe feature cards
    const cards = document.querySelectorAll('.feature-card, .user-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(card);
    });
}

/**
 * Add hover effects to buttons
 */
function addButtonEffects() {
    const buttons = document.querySelectorAll('.btn');
    
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

/**
 * Smooth scroll functionality for navigation links
 */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href !== '#' && document.querySelector(href)) {
            e.preventDefault();
            const target = document.querySelector(href);
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

/**
 * Mobile menu close on link click
 */
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', function() {
        const navbarCollapse = document.querySelector('.navbar-collapse');
        if (navbarCollapse.classList.contains('show')) {
            const toggler = document.querySelector('.navbar-toggler');
            toggler.click();
        }
    });
});

/**
 * Add ripple effect to buttons
 */
function createRipple(event) {
    const button = event.currentTarget;
    const ripple = document.createElement('span');
    
    const rect = button.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    ripple.classList.add('ripple');
    
    // Remove any existing ripple
    const existingRipple = button.querySelector('.ripple');
    if (existingRipple) {
        existingRipple.remove();
    }
    
    button.appendChild(ripple);
}

document.querySelectorAll('.btn').forEach(button => {
    button.addEventListener('click', createRipple);
});

/**
 * Display current year in footer
 */
function updateFooterYear() {
    const year = new Date().getFullYear();
    const footerText = document.querySelector('footer .text-center');
    if (footerText) {
        footerText.innerHTML = footerText.innerHTML.replace(/2026/g, year);
    }
}

// Call on load
updateFooterYear();

/**
 * Add scroll-to-top button functionality
 */
window.addEventListener('scroll', function() {
    const scrollBtn = document.querySelector('.scroll-to-top');
    if (window.scrollY > 300) {
        if (!scrollBtn) {
            const btn = document.createElement('button');
            btn.classList.add('scroll-to-top', 'btn', 'btn-danger', 'btn-sm');
            btn.innerHTML = '<i class="fas fa-arrow-up"></i>';
            btn.style.position = 'fixed';
            btn.style.bottom = '20px';
            btn.style.right = '20px';
            btn.style.zIndex = '99';
            btn.style.borderRadius = '50%';
            btn.style.width = '50px';
            btn.style.height = '50px';
            btn.style.display = 'flex';
            btn.style.alignItems = 'center';
            btn.style.justifyContent = 'center';
            btn.onclick = function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            };
            document.body.appendChild(btn);
        }
    } else {
        const scrollBtn = document.querySelector('.scroll-to-top');
        if (scrollBtn) {
            scrollBtn.remove();
        }
    }
});

/**
 * Log system info
 */
console.log('%cResQLink - Disaster Shelter & Evacuation Management System', 'color: #dc3545; font-size: 16px; font-weight: bold;');
console.log('%cVersion: 1.0.0 | Status: Active', 'color: #28a745; font-size: 12px;');