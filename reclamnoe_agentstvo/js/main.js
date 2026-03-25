
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const navMenu = document.getElementById('navMenu');
    
    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', function() {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
        
        document.addEventListener('click', function(event) {
            if (!navMenu.contains(event.target) && !menuToggle.contains(event.target) && navMenu.classList.contains('active')) {
                menuToggle.classList.remove('active');
                navMenu.classList.remove('active');
            }
        });
    }
    
    const slides = document.querySelectorAll('.banner-slide');
    const indicators = document.querySelectorAll('.indicator');
    let currentSlide = 0;
    let slideInterval;
    
    if (slides.length > 0) {
        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            indicators.forEach(indicator => indicator.classList.remove('active'));
            
            slides[index].classList.add('active');
            indicators[index].classList.add('active');
            
            currentSlide = index;
        }
        
        function nextSlide() {
            let next = currentSlide + 1;
            if (next >= slides.length) next = 0;
            showSlide(next);
        }
        
        function startSlider() {
            slideInterval = setInterval(nextSlide, 5000);
        }
        
        startSlider();
        
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                clearInterval(slideInterval);
                showSlide(index);
                startSlider();
            });
        });
        
        const bannerContainer = document.querySelector('.banner-container');
        if (bannerContainer) {
            bannerContainer.addEventListener('mouseenter', () => {
                clearInterval(slideInterval);
            });
            
            bannerContainer.addEventListener('mouseleave', () => {
                startSlider();
            });
        }
    }
    
    const themeToggle = document.getElementById('themeToggle');
    
    if (themeToggle) {
        if (localStorage.getItem('theme') === 'light') {
            document.body.classList.add('light-theme');
            themeToggle.textContent = '☀️';
        }
        
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('light-theme');
            
            const isLight = document.body.classList.contains('light-theme');
            themeToggle.textContent = isLight ? '☀️' : '🌙';
            
            localStorage.setItem('theme', isLight ? 'light' : 'dark');
        });
    }
    
    const formPopup = document.getElementById('projectForm');
    const openFormButtons = document.querySelectorAll('.discuss-btn, .contact-button, #openFormBtn');
    const closeFormButton = document.getElementById('closeForm');
    const projectForm = document.getElementById('projectDiscussionForm');
    
    if (formPopup) {
        function openForm() {
            formPopup.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeForm() {
            formPopup.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        openFormButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                openForm();
            });
        });
        
        if (closeFormButton) {
            closeFormButton.addEventListener('click', closeForm);
        }
        
        formPopup.addEventListener('click', function(e) {
            if (e.target === formPopup) {
                closeForm();
            }
        });
        
        if (projectForm) {
            projectForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const name = document.getElementById('name').value;
                const phone = document.getElementById('phone').value;
                const email = document.getElementById('email').value;
                const service = document.getElementById('service').value;
                const message = document.getElementById('message').value;
                
                const requests = JSON.parse(localStorage.getItem('requests')) || [];
                
                requests.push({
                    id: Date.now(),
                    name,
                    phone,
                    email,
                    service,
                    message,
                    status: 'new',
                    date: new Date().toISOString()
                });
                
                localStorage.setItem('requests', JSON.stringify(requests));
                alert(`Спасибо, ${name}! Ваша заявка принята. Мы свяжемся с вами в ближайшее время.`);
                
                projectForm.reset();
                closeForm();
                
                setTimeout(() => {
                    window.location.href = 'thank-you.html';
                }, 1000);
            });
        }
    }
    
    const filterButtons = document.querySelectorAll('.filter-btn');
    const portfolioCards = document.querySelectorAll('.portfolio-card');
    
    if (filterButtons.length > 0 && portfolioCards.length > 0) {
        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                
                const filter = button.dataset.filter;
                
                portfolioCards.forEach(card => {
                    if (filter === 'all' || card.dataset.category === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    }
    
    const loadMoreBtn = document.getElementById('loadMore');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            alert('Функция "Показать еще" будет реализована в полной версии сайта');
        });
    }
    
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const linkPage = link.getAttribute('href');
        if (linkPage === currentPage || 
            (currentPage === '' && linkPage === 'index.html') ||
            (currentPage === 'index.html' && linkPage === 'index.html')) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
    
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.startsWith('7') || value.startsWith('8')) {
                value = value.substring(1);
            }
            
            let formattedValue = '+7 (';
            
            if (value.length > 0) {
                formattedValue += value.substring(0, 3);
            }
            if (value.length > 3) {
                formattedValue += ') ' + value.substring(3, 6);
            }
            if (value.length > 6) {
                formattedValue += '-' + value.substring(6, 8);
            }
            if (value.length > 8) {
                formattedValue += '-' + value.substring(8, 10);
            }
            
            e.target.value = formattedValue;
        });
    }
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = 1;
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, {
        threshold: 0.1
    });
    
    document.querySelectorAll('.service-card, .service-detail-card, .stat-card, .review-card, .portfolio-card').forEach(el => {
        el.style.opacity = 0;
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
});