<?php
session_start();

$role = $_SESSION['role'] ?? null;
$isAdmin = $role === 'admin';
$isUser = $role === 'user';

$primaryCtaHref = $role
  ? ($isAdmin ? 'public/admin/dashboard.php' : 'public/user/dashboard.php')
  : 'public/register.php';
$primaryCtaLabel = $role ? 'Go to Dashboard' : 'Create an Account';

$secondaryCtaHref = $role ? ($isAdmin ? 'public/admin/dashboard.php#modules' : 'public/user/dashboard.php#modules') : 'public/login.php';
$secondaryCtaLabel = $role ? 'Explore Modules' : 'Sign in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kandado | Secure Locker Management Platform</title>
  <link rel="icon" href="assets/icon/icon_tab.png" sizes="any">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/home.css">
</head>
<body class="home-body">
  <header class="site-header" data-animate>
    <div class="header-inner container">
      <a class="brand" href="index.php">
        <span class="brand-icon">
          <img src="assets/icon/kandado2.png" alt="Kandado logo" loading="lazy">
        </span>
        <span class="brand-text">Kandado</span>
      </a>

      <nav class="site-nav" aria-label="Primary">
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
          <svg class="nav-toggle-icon" viewBox="0 0 24 16" aria-hidden="true" focusable="false">
            <path d="M2 2h20M2 8h20M2 14h20"></path>
          </svg>
          <span class="sr-only">Toggle navigation</span>
        </button>

        <div class="nav-links" id="primary-navigation">
          <a href="#platform" class="nav-link">Platform</a>
          <a href="#features" class="nav-link">Capabilities</a>
          <a href="#security" class="nav-link">Security</a>
          <a href="#insights" class="nav-link">Insights</a>
          <a href="#cta" class="nav-link">Get Started</a>
        </div>
      </nav>

      <div class="header-cta">
        <?php if (!$role): ?>
          <a class="btn ghost" href="public/login.php">Login</a>
          <a class="btn primary" href="public/register.php">Register</a>
        <?php else: ?>
          <a class="btn ghost" href="<?= htmlspecialchars($secondaryCtaHref) ?>">Modules</a>
          <a class="btn primary" href="<?= htmlspecialchars($primaryCtaHref) ?>">Dashboard</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main>
    <section class="hero section-surface" id="hero">
      <div class="hero-inner container">
        <div class="hero-copy" data-animate data-delay="60">
          <div class="eyebrow">Modern Locker Access</div>
          <h1>Give your users secure, effortless access with Kandado.</h1>
          <p>
            Kandado unifies locker reservations, identity verification, payments, and security monitoring into one elegant platform&mdash;designed for teams who value clarity and control.
          </p>
          <div class="hero-actions">
            <a class="btn primary" href="<?= htmlspecialchars($primaryCtaHref) ?>"><?= htmlspecialchars($primaryCtaLabel) ?></a>
            <a class="btn secondary" href="<?= htmlspecialchars($secondaryCtaHref) ?>"><?= htmlspecialchars($secondaryCtaLabel) ?></a>
          </div>

          <dl class="hero-stats" data-animate data-delay="180">
            <div>
              <dt>Active lockers managed</dt>
              <dd>8.4k+</dd>
            </div>
            <div>
              <dt>Monthly access requests</dt>
              <dd>120k</dd>
            </div>
            <div>
              <dt>Automated security flags</dt>
              <dd>99.6% resolved</dd>
            </div>
          </dl>
        </div>

        <div class="hero-visual" aria-hidden="true" data-animate data-delay="140">
          <div class="locker-showcase" data-parallax-depth="28">
            <div class="locker-ambient" aria-hidden="true"></div>
            <div class="locker-grid" role="presentation">
              <button type="button" class="locker-door" data-locker-id="L1" data-light="green" data-label="Available" data-note="Ready for next unlock" aria-pressed="false">
                <span class="locker-door__casing" aria-hidden="true"></span>
                <span class="locker-door__panel">
                  <span class="locker-door__indicator" aria-hidden="true">
                    <span class="locker-door__indicator-glow"></span>
                  </span>
                  <span class="locker-door__label">
                    <strong>L1</strong>
                    <small>Available</small>
                  </span>
                  <span class="locker-door__handle" aria-hidden="true"></span>
                </span>
                <span class="locker-door__lightbar" aria-hidden="true"></span>
              </button>
              <button type="button" class="locker-door" data-locker-id="L2" data-light="red" data-label="Occupied" data-note="Session in progress" aria-pressed="false">
                <span class="locker-door__casing" aria-hidden="true"></span>
                <span class="locker-door__panel">
                  <span class="locker-door__indicator" aria-hidden="true">
                    <span class="locker-door__indicator-glow"></span>
                  </span>
                  <span class="locker-door__label">
                    <strong>L2</strong>
                    <small>Occupied</small>
                  </span>
                  <span class="locker-door__handle" aria-hidden="true"></span>
                </span>
                <span class="locker-door__lightbar" aria-hidden="true"></span>
              </button>
              <button type="button" class="locker-door" data-locker-id="L3" data-light="violet" data-label="Maintenance" data-note="Service check underway" aria-pressed="false">
                <span class="locker-door__casing" aria-hidden="true"></span>
                <span class="locker-door__panel">
                  <span class="locker-door__indicator" aria-hidden="true">
                    <span class="locker-door__indicator-glow"></span>
                  </span>
                  <span class="locker-door__label">
                    <strong>L3</strong>
                    <small>Maintenance</small>
                  </span>
                  <span class="locker-door__handle" aria-hidden="true"></span>
                </span>
                <span class="locker-door__lightbar" aria-hidden="true"></span>
              </button>
              <button type="button" class="locker-door" data-locker-id="L4" data-light="blue" data-label="Hold" data-note="Awaiting manual release" aria-pressed="false">
                <span class="locker-door__casing" aria-hidden="true"></span>
                <span class="locker-door__panel">
                  <span class="locker-door__indicator" aria-hidden="true">
                    <span class="locker-door__indicator-glow"></span>
                  </span>
                  <span class="locker-door__label">
                    <strong>L4</strong>
                    <small>Hold</small>
                  </span>
                  <span class="locker-door__handle" aria-hidden="true"></span>
                </span>
                <span class="locker-door__lightbar" aria-hidden="true"></span>
              </button>
            </div>
          </div>
          <div class="locker-info-card hero-float" data-animate data-delay="220" data-parallax-depth="40" data-light="green">
            <span class="locker-info-card__label">Live Locker Feed</span>
            <div class="locker-info-card__active">
              <span class="locker-info-card__tag" data-locker-active-id>L1</span>
              <div>
                <strong data-locker-active-label>Available</strong>
                <small data-locker-active-note>Ready for next unlock</small>
              </div>
            </div>
            <ul>
              <li>
                <span>Unlock accuracy</span>
                <span>99.2%</span>
              </li>
              <li>
                <span>Queue time</span>
                <span>1m 42s</span>
              </li>
            </ul>
          </div>
          <div class="locker-metrics-card hero-float" data-animate data-delay="260" data-parallax-depth="46">
            <strong>Ambient Controls</strong>
            <p>Tap a locker to open the bay and cycle its indicator channel.</p>
            <div class="locker-metrics-card__grid">
              <div>
                <span class="label">Active bays</span>
                <span class="value">4</span>
              </div>
              <div>
                <span class="label">Alerts</span>
                <span class="value badge">0</span>
              </div>
              <div>
                <span class="label">Light channels</span>
                <span class="value">RGBV</span>
              </div>
            </div>
          </div>
        </div>
      </div>


    </section>

    <section class="platform section-surface" id="platform" data-animate>
      <div class="section-heading">
        <h2>How to Use the System</h2>
        <p>Run these end-to-end scenarios to confirm onsite access, fail-safes, and administrative controls work as designed.</p>
      </div>

      <div class="platform-grid">
        <article class="platform-card focus slide-from-left" data-animate>
          <h3>Steps 1-9 &middot; Onboarding and Access</h3>
          <p>Connect locally, register, and verify that standard reservation and unlock flows complete without issues.</p>
          <ol class="platform-steps">
            <li>Connect to the MCC LAN.</li>
            <li>Scan the QR on the locker to open the website.</li>
            <li>Sign up as a new user and verify your account.</li>
            <li>Reserve a locker and get your QR code and PIN (website or email).</li>
            <li>Unlock with QR using the shared scanner.</li>
            <li>Unlock with PIN on the keypad (fallback).</li>
            <li>Store an item, close the door, and check the status light and dashboard state.</li>
            <li>Leave the door open; confirm the buzzer warns you to close it.</li>
            <li>Extend your active session (on LAN) and confirm the new end time.</li>
          </ol>
        </article>
        <article class="platform-card slide-from-bottom" data-animate data-delay="140">
          <h3>Steps 10-18 &middot; Session Integrity</h3>
          <p>Exercise session lifecycle, off-campus behaviors, and hold scenarios to ensure accurate enforcement and notifications.</p>
          <ol class="platform-steps" start="10">
            <li>Terminate the locker session.</li>
            <li>Try to reuse an old QR/PIN &rarr; it must fail and be logged.</li>
            <li>Switch to off-campus internet (mobile data). Try to reserve &rarr; should be blocked.</li>
            <li>Still off-campus, try to extend an existing session &rarr; should be allowed and logged.</li>
            <li>Start a session &ge; 1 hour; confirm emails at 30 min and 15 min before expiry, at expiry, then 15 and 30 min after.</li>
            <li>Start a 30-minute session; confirm email at 15 min left (and at expiry/after, if set).</li>
            <li>Start a 20-minute session; confirm email at 10 min left (and at expiry/after, if set).</li>
            <li>Put an item inside and let the session expire &rarr; the system should auto-hold the locker and notify admin.</li>
            <li>While on hold, the user cannot reserve another locker.</li>
          </ol>
        </article>
        <article class="platform-card slide-from-right" data-animate data-delay="240">
          <h3>Steps 19-20 &middot; Maintenance and Signals</h3>
          <p>Confirm dashboard controls and physical indicators align with maintenance policies.</p>
          <ol class="platform-steps" start="19">
            <li>Mark a locker Maintenance in the dashboard &rarr; users cannot open it.</li>
            <li>Review status lights (for your checks):
              <div class="status-lines">
                <span>Green = available</span>
                <span>Red = occupied (with items)</span>
                <span>Orange/Yellow = locked but empty</span>
                <span>Blue = on hold</span>
                <span>No light = maintenance / offline</span>
              </div>
            </li>
          </ol>
        </article>
      </div>
    </section>

    <section class="features section-surface" id="features">
      <div class="section-heading" data-animate>
        <h2>Capabilities that meet the moment.</h2>
        <p>Kandado synthesizes real-time data, automated policies, and meaningful insights so you can scale securely.</p>
      </div>

      <div class="feature-carousel" data-carousel data-autoplay="true" data-interval="3000">
        <div class="feature-carousel__viewport" data-carousel-viewport>
          <div class="feature-carousel__track" data-carousel-track>
            <article class="feature-slide is-active" id="feature-slide-1" data-carousel-slide data-animate>
              <div class="feature-slide__media" role="button" tabindex="0" data-lightbox-trigger aria-label="View Step 01 illustration">
                <img src="assets/image/step1.png" alt="Step 01 Connect to the MCC LAN illustration" data-lightbox-source>
              </div>
              <div class="feature-slide__content">
                <span class="feature-slide__eyebrow">Step 01</span>
                <h3 class="feature-slide__title">Connect to the MCC LAN</h3>
                <p>Join the local campus network before you start so the locker site authenticates instantly.</p>
                <ul class="feature-slide__points">
                  <li>Ensure your device is on the MCC LAN SSID.</li>
                  <li>Verify you can reach internal services.</li>
                </ul>
              </div>
            </article>

            <article class="feature-slide" id="feature-slide-2" data-carousel-slide data-animate data-delay="80">
              <div class="feature-slide__media" role="button" tabindex="0" data-lightbox-trigger aria-label="View Step 02 illustration">
                <img src="assets/image/step2.png" alt="Step 02 Open the locker portal illustration" data-lightbox-source>
              </div>
              <div class="feature-slide__content">
                <span class="feature-slide__eyebrow">Step 02</span>
                <h3 class="feature-slide__title">Open the locker portal</h3>
                <p>Scan the QR code on the locker door to launch Kandado with your device camera.</p>
                <ul class="feature-slide__points">
                  <li>Use the built-in scanner or camera app.</li>
                  <li>Confirm the secure kandado.mcc URL loads.</li>
                </ul>
              </div>
            </article>

            <article class="feature-slide" id="feature-slide-3" data-carousel-slide data-animate data-delay="120">
              <div class="feature-slide__media" role="button" tabindex="0" data-lightbox-trigger aria-label="View Step 03 illustration">
                <img src="assets/image/step3.png" alt="Step 03 Account registration illustration" data-lightbox-source>
              </div>
              <div class="feature-slide__content">
                <span class="feature-slide__eyebrow">Step 03</span>
                <h3 class="feature-slide__title">Create and verify your account</h3>
                <p>Register as a new user and finalize verification so we can assign lockers to you.</p>
                <ul class="feature-slide__points">
                  <li>Complete the sign-up form with campus email.</li>
                  <li>Activate the account via the verification link.</li>
                </ul>
              </div>
            </article>

            <article class="feature-slide" id="feature-slide-4" data-carousel-slide data-animate data-delay="160">
              <div class="feature-slide__media" role="button" tabindex="0" data-lightbox-trigger aria-label="View Step 04 illustration">
                <img src="assets/image/step4.png" alt="Step 04 Reservation illustration" data-lightbox-source>
              </div>
              <div class="feature-slide__content">
                <span class="feature-slide__eyebrow">Step 04</span>
                <h3 class="feature-slide__title">Reserve your locker</h3>
                <p>Pick an available locker and generate the QR and PIN credentials for your session.</p>
                <ul class="feature-slide__points">
                  <li>Choose an open bay from the dashboard.</li>
                  <li>Save the issued QR code and fallback PIN.</li>
                </ul>
              </div>
            </article>

            <article class="feature-slide" id="feature-slide-5" data-carousel-slide data-animate data-delay="200">
              <div class="feature-slide__media" role="button" tabindex="0" data-lightbox-trigger aria-label="View Step 05 illustration">
                <img src="assets/image/step5.png" alt="Step 05 QR unlock illustration" data-lightbox-source>
              </div>
              <div class="feature-slide__content">
                <span class="feature-slide__eyebrow">Step 05</span>
                <h3 class="feature-slide__title">Unlock with your QR</h3>
                <p>Present the Kandado QR at the shared scanner to trigger an instant unlock.</p>
                <ul class="feature-slide__points">
                  <li>Hold the code steady under the reader.</li>
                  <li>Wait for the green status light before opening.</li>
                </ul>
              </div>
            </article>

            <article class="feature-slide" id="feature-slide-6" data-carousel-slide data-animate data-delay="240">
              <div class="feature-slide__media" role="button" tabindex="0" data-lightbox-trigger aria-label="View Step 06 illustration">
                <img src="assets/image/step6.png" alt="Step 06 PIN unlock illustration" data-lightbox-source>
              </div>
              <div class="feature-slide__content">
                <span class="feature-slide__eyebrow">Step 06</span>
                <h3 class="feature-slide__title">Use the PIN keypad fallback</h3>
                <p>If the scanner is busy, enter the PIN on the keypad to release the door.</p>
                <ul class="feature-slide__points">
                  <li>Enter the four-digit PIN from your reservation.</li>
                  <li>The latch releases once the tone confirms.</li>
                </ul>
              </div>
            </article>

            <article class="feature-slide" id="feature-slide-7" data-carousel-slide data-animate data-delay="280">
              <div class="feature-slide__media" role="button" tabindex="0" data-lightbox-trigger aria-label="View Step 07 illustration">
                <img src="assets/image/step7.png" alt="Step 07 Store items illustration" data-lightbox-source>
              </div>
              <div class="feature-slide__content">
                <span class="feature-slide__eyebrow">Step 07</span>
                <h3 class="feature-slide__title">Store items and confirm status</h3>
                <p>Place your belongings, close the door, and check the dashboard for an active session.</p>
                <ul class="feature-slide__points">
                  <li>Ensure the door seals and the light shows occupied.</li>
                  <li>Refresh the app to verify the locker status.</li>
                </ul>
              </div>
            </article>

            <article class="feature-slide" id="feature-slide-8" data-carousel-slide data-animate data-delay="320">
              <div class="feature-slide__media" role="button" tabindex="0" data-lightbox-trigger aria-label="View Step 08 illustration">
                <img src="assets/image/step8.png" alt="Step 08 Door alert illustration" data-lightbox-source>
              </div>
              <div class="feature-slide__content">
                <span class="feature-slide__eyebrow">Step 08</span>
                <h3 class="feature-slide__title">Test the door alert</h3>
                <p>Leave the door ajar briefly to confirm the buzzer prompts you to close it.</p>
                <ul class="feature-slide__points">
                  <li>Listen for the alert within a few seconds.</li>
                  <li>Close the door and confirm the status resets.</li>
                </ul>
              </div>
            </article>
          </div>
        </div>

        <button class="feature-carousel__nav feature-carousel__nav--prev" type="button" aria-label="Previous step" data-carousel-prev>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 5.5 9 12l6.5 6.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button class="feature-carousel__nav feature-carousel__nav--next" type="button" aria-label="Next step" data-carousel-next>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 18.5 15 12 8.5 5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>

        <div class="feature-carousel__dots" role="tablist" aria-label="Locker platform how-to steps" data-carousel-dots>
          <button class="feature-carousel__dot is-active" type="button" role="tab" aria-selected="true" aria-controls="feature-slide-1" data-carousel-dot="0">
            <span class="sr-only">Step 01 — Connect to the MCC LAN</span>
          </button>
          <button class="feature-carousel__dot" type="button" role="tab" aria-selected="false" aria-controls="feature-slide-2" data-carousel-dot="1">
            <span class="sr-only">Step 02 — Open the locker portal</span>
          </button>
          <button class="feature-carousel__dot" type="button" role="tab" aria-selected="false" aria-controls="feature-slide-3" data-carousel-dot="2">
            <span class="sr-only">Step 03 — Create and verify your account</span>
          </button>
          <button class="feature-carousel__dot" type="button" role="tab" aria-selected="false" aria-controls="feature-slide-4" data-carousel-dot="3">
            <span class="sr-only">Step 04 — Reserve your locker</span>
          </button>
          <button class="feature-carousel__dot" type="button" role="tab" aria-selected="false" aria-controls="feature-slide-5" data-carousel-dot="4">
            <span class="sr-only">Step 05 — Unlock with your QR</span>
          </button>
          <button class="feature-carousel__dot" type="button" role="tab" aria-selected="false" aria-controls="feature-slide-6" data-carousel-dot="5">
            <span class="sr-only">Step 06 — Use the PIN keypad fallback</span>
          </button>
          <button class="feature-carousel__dot" type="button" role="tab" aria-selected="false" aria-controls="feature-slide-7" data-carousel-dot="6">
            <span class="sr-only">Step 07 — Store items and confirm status</span>
          </button>
          <button class="feature-carousel__dot" type="button" role="tab" aria-selected="false" aria-controls="feature-slide-8" data-carousel-dot="7">
            <span class="sr-only">Step 08 — Test the door alert</span>
          </button>
        </div>
      </div>
    </section>

    <div class="feature-lightbox" data-feature-lightbox aria-hidden="true" hidden>
      <div class="feature-lightbox__backdrop" data-lightbox-close aria-hidden="true"></div>
      <div class="feature-lightbox__dialog" role="dialog" aria-modal="true" aria-label="Feature slide image preview">
        <button class="feature-lightbox__close" type="button" data-lightbox-close aria-label="Close image preview">
          <span aria-hidden="true">&times;</span>
        </button>
        <img src="" alt="" data-lightbox-image>
        <p class="feature-lightbox__caption" data-lightbox-caption hidden></p>
      </div>
    </div>

    <section class="insights section-surface" id="insights" data-animate>
      <div class="section-heading">
        <h2>See what matters in seconds.</h2>
        <p>Spot surges, predict demand, and showcase the value of your locker network with reports that are presentation-ready.</p>
      </div>

      <div class="insight-panels">
        <article class="insight-card">
          <header>
            <h3>Utilization Pulse</h3>
            <span class="badge positive">+18% this quarter</span>
          </header>
          <p>Track occupancy trends and peak hours to forecast staffing, restocking, and maintenance schedules.</p>
          <div class="sparkline">
            <span style="--p:65%"></span>
            <span style="--p:52%"></span>
            <span style="--p:74%"></span>
            <span style="--p:88%"></span>
            <span style="--p:61%"></span>
            <span style="--p:92%"></span>
          </div>
        </article>
        <article class="insight-card">
          <header>
            <h3>Incident Heatmap</h3>
            <span class="badge neutral">Safe baseline</span>
          </header>
          <p>Understand where interventions are needed most with instant visibility into location-specific alerts.</p>
          <div class="heatmap">
            <span></span><span></span><span></span><span></span>
            <span></span><span class="hot"></span><span></span><span></span>
            <span></span><span></span><span class="warm"></span><span></span>
          </div>
        </article>
        <article class="insight-card">
          <header>
            <h3>Revenue Signals</h3>
            <span class="badge focus">Automated</span>
          </header>
          <p>Automate reconciliation and keep finance aligned with transparent payment trails and wallet balances.</p>
          <ul class="metrics">
            <li>
              <span>Wallet refills</span>
              <strong>&#8369;1.9M</strong>
            </li>
            <li>
              <span>Processing time</span>
              <strong>32s avg</strong>
            </li>
            <li>
              <span>Failed attempts</span>
              <strong>0.4%</strong>
            </li>
          </ul>
        </article>
      </div>
    </section>

    <section class="security section-surface" id="security">
      <div class="section-heading" data-animate>
        <h2>Security woven into every layer.</h2>
        <p>More than encryption&mdash;Kandado optimizes for prevention, detection, and response so you can prove compliance and protect your community.</p>
      </div>

      <div class="security-grid">
        <article class="security-card" data-animate>
          <h3>Zero-trust posture</h3>
          <p>Adaptive policies evaluate device posture, location, and usage history before every unlock.</p>
        </article>
        <article class="security-card" data-animate>
          <h3>Continuous monitoring</h3>
          <p>Machine learning models surface anomalies in real time and trigger smart escalations.</p>
        </article>
        <article class="security-card" data-animate>
          <h3>Audit-grade logging</h3>
          <p>Immutable event trails keep auditors and stakeholders confident in your operations.</p>
        </article>
      </div>
    </section>


  </main>

  <footer class="site-footer">
    <div class="footer-inner">
      <a class="brand" href="index.php">
        <span class="brand-icon">
          <img src="assets/icon/kandado2.png" alt="" aria-hidden="true" loading="lazy">
        </span>
        <span class="brand-text">Kandado</span>
      </a>
      <div class="footer-links">
        <div>
          <span class="footer-label">Platform</span>
          <a href="#platform">Overview</a>
          <a href="#features">Capabilities</a>
          <a href="#insights">Insights</a>
        </div>
        <div>
          <span class="footer-label">Company</span>
          <a href="public/login.php">Login</a>
          <a href="public/register.php">Register</a>
          <a href="public/forgot_password.php">Reset password</a>
        </div>
        <div>
          <span class="footer-label">Support</span>
          <a href="mailto:support@kandado.app">Email support</a>
          <a href="tel:+639171234567">+63 917 123 4567</a>
          <a href="#">Status</a>
        </div>
      </div>
    </div>
    <p class="footer-meta">&copy; <?= date('Y') ?> Kandado. Secure locker management for modern teams.</p>
  </footer>

  <script src="assets/js/home.js" defer></script>
</body>
</html>
