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
