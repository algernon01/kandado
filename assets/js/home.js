document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('.site-header');
  const navToggle = document.querySelector('.nav-toggle');
  const navLinks = document.querySelector('.nav-links');
  const heroSection = document.querySelector('.hero');
  const animateTargets = Array.from(document.querySelectorAll('[data-animate]'));
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  animateTargets.forEach(el => {
    const delay = Number(el.dataset.delay || 0);
    if (!Number.isNaN(delay) && delay > 0) {
      el.style.setProperty('--stagger-delay', `${delay}ms`);
    }
  });

  const updateHeaderState = () => {
    if (!header) return;
    header.classList.toggle('is-condensed', window.scrollY > 24);
  };

  updateHeaderState();
  window.addEventListener('scroll', updateHeaderState, { passive: true });

  if (navToggle && navLinks) {
    navToggle.addEventListener('click', () => {
      const isOpen = navLinks.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', String(isOpen));
    });

    navLinks.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        navLinks.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  if (prefersReducedMotion) {
    animateTargets.forEach(el => el.classList.add('is-visible'));
    return;
  }

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          obs.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.18,
      rootMargin: '0px 0px -6% 0px'
    });
    animateTargets.forEach(el => observer.observe(el));
  } else {
    animateTargets.forEach(el => el.classList.add('is-visible'));
  }

  let parallaxItems = [];
  let rafId = null;
  const parallaxState = {
    currentX: 0,
    currentY: 0,
    targetX: 0,
    targetY: 0
  };

  const stepParallax = () => {
    if (!parallaxItems.length) {
      rafId = null;
      return;
    }

    parallaxState.currentX += (parallaxState.targetX - parallaxState.currentX) * 0.08;
    parallaxState.currentY += (parallaxState.targetY - parallaxState.currentY) * 0.08;

    parallaxItems.forEach(el => {
      const depth = parseFloat(el.dataset.parallaxDepth || '16');
      const x = parallaxState.currentX * depth;
      const y = parallaxState.currentY * depth;
      el.style.setProperty('--parallax-x', `${x}px`);
      el.style.setProperty('--parallax-y', `${y}px`);
    });

    rafId = requestAnimationFrame(stepParallax);
  };

  const resetParallaxTargets = () => {
    parallaxState.targetX = 0;
    parallaxState.targetY = 0;
  };

  const updateParallaxTargets = event => {
    if (!parallaxItems.length) return;
    const pointer = event.touches ? event.touches[0] : event;
    if (!pointer) return;

    const rect = heroSection?.getBoundingClientRect();
    const width = rect?.width ?? window.innerWidth;
    const height = rect?.height ?? window.innerHeight;
    const originX = (rect?.left ?? 0) + width / 2;
    const originY = (rect?.top ?? 0) + height / 2;

    const relativeX = (pointer.clientX - originX) / width;
    const relativeY = (pointer.clientY - originY) / height;

    parallaxState.targetX = Math.max(-0.75, Math.min(0.75, relativeX));
    parallaxState.targetY = Math.max(-0.75, Math.min(0.75, relativeY));
  };

  const cleanupParallax = () => {
    if (rafId) {
      cancelAnimationFrame(rafId);
      rafId = null;
    }
    parallaxItems.forEach(el => {
      el.style.removeProperty('--parallax-x');
      el.style.removeProperty('--parallax-y');
    });
    parallaxItems = [];
    resetParallaxTargets();
  };

  const evaluateParallax = () => {
    if (window.innerWidth < 900) {
      cleanupParallax();
      return;
    }
    const nextItems = Array.from(document.querySelectorAll('[data-parallax-depth]'));
    if (!nextItems.length) {
      cleanupParallax();
      return;
    }
    cleanupParallax();
    parallaxItems = nextItems;
    rafId = requestAnimationFrame(stepParallax);
  };

  const pointerTarget = heroSection ?? document.body;
  pointerTarget.addEventListener('pointermove', updateParallaxTargets, { passive: true });
  pointerTarget.addEventListener('pointerleave', resetParallaxTargets);
  pointerTarget.addEventListener('touchend', resetParallaxTargets);

  let resizeRaf = null;
  window.addEventListener('resize', () => {
    if (resizeRaf) {
      cancelAnimationFrame(resizeRaf);
    }
    resizeRaf = requestAnimationFrame(() => {
      evaluateParallax();
      resizeRaf = null;
    });
  });

  evaluateParallax();
});
