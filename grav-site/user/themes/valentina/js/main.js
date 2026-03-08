// Intersection Observer for scroll animations
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) entry.target.classList.add('visible');
  });
}, { root: null, rootMargin: '0px', threshold: 0.1 });

document.addEventListener('DOMContentLoaded', () => {
  document.body.classList.add('js-enabled');

  // Mobile Menu Toggle
  const menuToggle = document.querySelector('.menu-toggle');
  const nav = document.getElementById('main-nav');

  if (menuToggle && nav) {
    // Add close button
    if (!nav.querySelector('.nav-close')) {
      const closeBtn = document.createElement('button');
      closeBtn.className = 'nav-close';
      closeBtn.innerHTML = '✕';
      closeBtn.setAttribute('aria-label', 'Chiudi menu');
      nav.prepend(closeBtn);
      closeBtn.addEventListener('click', () => {
        nav.classList.remove('active');
        menuToggle.innerHTML = '☰';
        document.body.style.overflow = '';
      });
    }

    // Move nav to body on mobile to avoid stacking context issues
    if (window.innerWidth <= 768 && nav.parentNode !== document.body) {
      document.body.appendChild(nav);
    }

    menuToggle.addEventListener('click', () => {
      nav.classList.toggle('active');
      menuToggle.innerHTML = nav.classList.contains('active') ? '✕' : '☰';
    });

    nav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        nav.classList.remove('active');
        menuToggle.innerHTML = '☰';
      });
    });
  }

  // Scroll animations
  document.querySelectorAll('[data-animate]').forEach(el => observer.observe(el));

  // Highlight active nav link
  const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
  document.querySelectorAll('nav a').forEach(link => {
    const href = link.getAttribute('href') || '';
    const linkPath = href.replace(/\/$/, '') || '/';
    if (linkPath === currentPath || (currentPath !== '/' && currentPath.startsWith(linkPath + '/') && linkPath !== '/')) {
      link.classList.add('active');
    }
  });
});
