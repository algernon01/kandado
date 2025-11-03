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

  const carousels = Array.from(document.querySelectorAll('[data-carousel]'));

  carousels.forEach(root => initFeatureCarousel(root));

  function initFeatureCarousel(root) {
    const track = root.querySelector('[data-carousel-track]');
    const slides = Array.from(root.querySelectorAll('[data-carousel-slide]'));
    const dots = Array.from(root.querySelectorAll('[data-carousel-dot]'));
    const prevBtn = root.querySelector('[data-carousel-prev]');
    const nextBtn = root.querySelector('[data-carousel-next]');
    const viewport = root.querySelector('[data-carousel-viewport]');
    if (!track || !slides.length || !viewport) return;

    let activeIndex = 0;
    let autoplayTimer = null;
    let isPointerDown = false;
    let pointerStartX = 0;
    let pointerLastX = 0;
    let resizeRaf = null;
    let animationResetTimer = null;

    const autoplayEnabled = root.dataset.autoplay !== 'false' && slides.length > 1;
    const loopSlides = root.dataset.loop !== 'false';
    const autoplayInterval = Math.max(3500, Number(root.dataset.interval) || 7000);

    slides.forEach((slide, index) => {
      slide.setAttribute('aria-hidden', index === activeIndex ? 'false' : 'true');
      slide.setAttribute('tabindex', index === activeIndex ? '0' : '-1');
    });

    dots.forEach((dot, index) => {
      dot.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
      dot.setAttribute('tabindex', index === activeIndex ? '0' : '-1');
    });

    updateActiveStates();
    updateTrackPosition();
    startAutoplay();

    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        goToSlide(activeIndex - 1, { loop: loopSlides });
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        goToSlide(activeIndex + 1, { loop: loopSlides });
      });
    }

    dots.forEach((dot, index) => {
      dot.addEventListener('click', () => {
        goToSlide(index);
      });
    });

    const pointerTargets = [viewport, track];
    pointerTargets.forEach(target => {
      if (!target) return;

      target.addEventListener('pointerdown', event => {
        isPointerDown = true;
        pointerStartX = event.clientX;
        pointerLastX = event.clientX;
        stopAutoplay();
      });

      target.addEventListener('pointermove', event => {
        if (!isPointerDown) return;
        pointerLastX = event.clientX;
      });

      target.addEventListener('pointerup', () => {
        if (!isPointerDown) return;
        const deltaX = pointerLastX - pointerStartX;
        handleSwipe(deltaX);
        isPointerDown = false;
        pointerStartX = 0;
        pointerLastX = 0;
        startAutoplay();
      });

      target.addEventListener('pointerleave', () => {
        if (!isPointerDown) return;
        const deltaX = pointerLastX - pointerStartX;
        handleSwipe(deltaX);
        isPointerDown = false;
        pointerStartX = 0;
        pointerLastX = 0;
      });

      target.addEventListener('pointercancel', () => {
        isPointerDown = false;
        pointerStartX = 0;
        pointerLastX = 0;
      });
    });

    root.addEventListener('mouseenter', stopAutoplay);
    root.addEventListener('mouseleave', startAutoplay);
    root.addEventListener('focusin', stopAutoplay);
    root.addEventListener('focusout', startAutoplay);

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        stopAutoplay();
      } else {
        startAutoplay();
      }
    });

    window.addEventListener('resize', () => {
      if (resizeRaf) cancelAnimationFrame(resizeRaf);
      resizeRaf = requestAnimationFrame(() => {
        updateTrackPosition();
        resizeRaf = null;
      });
    }, { passive: true });

    function goToSlide(nextIndex, options = {}) {
      const { loop = loopSlides } = options;
      const totalSlides = slides.length;
      let targetIndex;

      if (loop) {
        targetIndex = ((nextIndex % totalSlides) + totalSlides) % totalSlides;
      } else {
        targetIndex = Math.max(0, Math.min(totalSlides - 1, nextIndex));
      }

      if (targetIndex === activeIndex) return false;

      activeIndex = targetIndex;
      updateTrackPosition();
      updateActiveStates();

      if (!loopSlides && activeIndex === totalSlides - 1) {
        stopAutoplay();
      } else {
        restartAutoplay();
      }

      return true;
    }

    function updateTrackPosition() {
      const viewportWidth = viewport.clientWidth;
      const offset = -activeIndex * viewportWidth;
      track.style.transform = `translate3d(${offset}px, 0, 0)`;
    }

    function updateActiveStates() {
      slides.forEach((slide, index) => {
        const isActive = index === activeIndex;
        slide.classList.toggle('is-active', isActive);
        slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        slide.setAttribute('tabindex', isActive ? '0' : '-1');
        if (!isActive) {
          slide.classList.remove('is-animating');
        }
      });

      dots.forEach((dot, index) => {
        const isActive = index === activeIndex;
        dot.classList.toggle('is-active', isActive);
        dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
        dot.setAttribute('tabindex', isActive ? '0' : '-1');
      });

      if (animationResetTimer) {
        window.clearTimeout(animationResetTimer);
        animationResetTimer = null;
      }

      const activeSlide = slides[activeIndex];
      if (!activeSlide) return;

      activeSlide.classList.add('is-animating');
      animationResetTimer = window.setTimeout(() => {
        activeSlide.classList.remove('is-animating');
        animationResetTimer = null;
      }, 900);

      if (prevBtn) {
        prevBtn.disabled = !loopSlides && activeIndex === 0;
      }
      if (nextBtn) {
        nextBtn.disabled = !loopSlides && activeIndex === slides.length - 1;
      }
    }

    function handleSwipe(deltaX) {
      const threshold = viewport.clientWidth * 0.12;
      if (Math.abs(deltaX) < threshold) return;
      if (deltaX > 0) {
        goToSlide(activeIndex - 1);
      } else {
        goToSlide(activeIndex + 1);
      }
    }

    function startAutoplay() {
      if (!autoplayEnabled) return;
      stopAutoplay();
      autoplayTimer = window.setInterval(() => {
        const moved = goToSlide(activeIndex + 1, { loop: loopSlides });
        if (!loopSlides && !moved) {
          stopAutoplay();
        }
      }, autoplayInterval);
    }

    function stopAutoplay() {
      if (!autoplayTimer) return;
      window.clearInterval(autoplayTimer);
      autoplayTimer = null;
    }

    function restartAutoplay() {
      if (!autoplayEnabled) return;
      stopAutoplay();
      startAutoplay();
    }
  }

  const lockerDoors = Array.from(document.querySelectorAll('.locker-door'));
  const lockerInfoCard = document.querySelector('.locker-info-card');
  const lockerMetricsCard = document.querySelector('.locker-metrics-card');
  const lockerActiveId = document.querySelector('[data-locker-active-id]');
  const lockerActiveLabel = document.querySelector('[data-locker-active-label]');
  const lockerActiveNote = document.querySelector('[data-locker-active-note]');
  const indicatorSequence = ['green', 'red', 'blue', 'violet'];
  const indicatorMeta = {
    green: { badge: 'Available', label: 'Available', note: 'Ready for next unlock' },
    red: { badge: 'Occupied', label: 'Occupied', note: 'Session in progress' },
    blue: { badge: 'Hold', label: 'Hold', note: 'Awaiting manual release' },
    violet: { badge: 'Maintenance', label: 'Maintenance', note: 'Service check underway' }
  };
  const desktopMedia = typeof window.matchMedia === 'function' ? window.matchMedia('(min-width: 1024px)') : null;
  const colorState = new WeakMap();

  const isDesktopView = () => {
    if (desktopMedia) return desktopMedia.matches;
    return window.innerWidth >= 1024;
  };

  const hiddenInfoLocker = 'L2';
  const hiddenMetricsLocker = 'L3';
  const hiddenInfoLockerId = hiddenInfoLocker.toUpperCase();
  const hiddenMetricsLockerId = hiddenMetricsLocker.toUpperCase();

  lockerDoors.forEach(door => {
    const initialIndex = Math.max(0, indicatorSequence.indexOf(door.dataset.light || ''));
    applyDoorColor(door, initialIndex);
    door.setAttribute('aria-pressed', 'false');

    door.addEventListener('click', () => {
      handleDoorActivation(door);
    });

    door.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        handleDoorActivation(door);
      }
    });

    door.addEventListener('mouseenter', () => updateInfoPanel(door));
    door.addEventListener('focus', () => updateInfoPanel(door));
  });

  if (lockerDoors.length) {
    updateInfoPanel(lockerDoors[0]);
    refreshOverlayVisibility();
  }

  function applyDoorColor(door, index) {
    if (!door) return indicatorSequence[0];
    const boundedIndex = ((index % indicatorSequence.length) + indicatorSequence.length) % indicatorSequence.length;
    colorState.set(door, boundedIndex);
    const color = indicatorSequence[boundedIndex];
    const meta = indicatorMeta[color];
    door.dataset.light = color;
    if (meta) {
      door.dataset.label = meta.label;
      door.dataset.note = meta.note;
      const labelEl = door.querySelector('.locker-door__label small');
      if (labelEl) {
        labelEl.textContent = meta.badge;
      }
    }
    return color;
  }

  function updateInfoPanel(door) {
    if (!door) return;
    const color = door.dataset.light || indicatorSequence[0];
    if (lockerActiveId) lockerActiveId.textContent = door.dataset.lockerId || '';
    if (lockerActiveLabel) lockerActiveLabel.textContent = door.dataset.label || '';
    if (lockerActiveNote) lockerActiveNote.textContent = door.dataset.note || '';
    if (lockerInfoCard) lockerInfoCard.dataset.light = color;
  }

  function refreshOverlayVisibility(activeLockerId) {
    if (!lockerInfoCard && !lockerMetricsCard) return;
    const isDesktop = isDesktopView();
    const resolvedId = typeof activeLockerId === 'string'
      ? activeLockerId.toUpperCase()
      : (
        isDesktop
          ? ((lockerDoors.find(currentDoor => currentDoor.classList.contains('is-open'))?.dataset.lockerId) || '').toUpperCase()
          : ''
        );

    const shouldHideInfo = Boolean(isDesktop && resolvedId === hiddenInfoLockerId);
    const shouldHideMetrics = Boolean(isDesktop && resolvedId === hiddenMetricsLockerId);

    toggleHeroFloatVisibility(lockerInfoCard, shouldHideInfo);
    toggleHeroFloatVisibility(lockerMetricsCard, shouldHideMetrics);
  }

  function toggleHeroFloatVisibility(element, shouldHide) {
    if (!element) return;
    const isCurrentlyHidden = element.classList.contains('is-hidden');
    if (shouldHide === isCurrentlyHidden) return;

    element.getBoundingClientRect();
    element.classList.toggle('is-hidden', shouldHide);

    if (shouldHide) {
      element.setAttribute('aria-hidden', 'true');
      return;
    }
    element.removeAttribute('aria-hidden');
  }

  function handleDoorActivation(door) {
    if (!door) return;
    const isOpen = door.classList.contains('is-open');

    lockerDoors.forEach(other => {
      if (other === door) return;
      other.classList.remove('is-open', 'is-activating');
      other.setAttribute('aria-pressed', 'false');
    });

    if (isOpen) {
      door.classList.remove('is-open');
      door.setAttribute('aria-pressed', 'false');
      updateInfoPanel(door);
      refreshOverlayVisibility(null);
      return;
    }

    const currentIndex = colorState.get(door);
    const baseIndex = typeof currentIndex === 'number' ? currentIndex : indicatorSequence.indexOf(door.dataset.light || '');
    const nextIndex = ((baseIndex >= 0 ? baseIndex : 0) + 1) % indicatorSequence.length;
    applyDoorColor(door, nextIndex);
    door.classList.add('is-open', 'is-activating');
    door.setAttribute('aria-pressed', 'true');
    updateInfoPanel(door);
    refreshOverlayVisibility((door.dataset.lockerId || '').toUpperCase());
    window.setTimeout(() => door.classList.remove('is-activating'), 900);
  }

  function handleDesktopChange(event) {
    if (!event) return;
    if (event.matches) {
      refreshOverlayVisibility();
      return;
    }
    if (lockerInfoCard) lockerInfoCard.classList.remove('is-hidden');
    if (lockerMetricsCard) lockerMetricsCard.classList.remove('is-hidden');
  }

  if (desktopMedia) {
    if (typeof desktopMedia.addEventListener === 'function') {
      desktopMedia.addEventListener('change', handleDesktopChange);
    } else if (typeof desktopMedia.addListener === 'function') {
      desktopMedia.addListener(handleDesktopChange);
    }
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
      threshold: 0.16,
      rootMargin: '-8% 0px -8% 0px'
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

    parallaxState.currentX += (parallaxState.targetX - parallaxState.currentX) * 0.06;
    parallaxState.currentY += (parallaxState.targetY - parallaxState.currentY) * 0.06;

    parallaxItems.forEach(el => {
      const depth = parseFloat(el.dataset.parallaxDepth || '16');
      const intensity = 0.6;
      const x = parallaxState.currentX * depth * intensity;
      const y = parallaxState.currentY * depth * intensity;
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

    parallaxState.targetX = Math.max(-0.6, Math.min(0.6, relativeX));
    parallaxState.targetY = Math.max(-0.6, Math.min(0.6, relativeY));
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
