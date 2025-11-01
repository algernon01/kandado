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
      <div class="feature-grid">
        <article class="feature-card" data-animate>
          <span class="feature-icon soft-blue">
            <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3 3 11.5v1.5l13 8 13-8v-1.5L16 3Zm0 4.43L23.55 11 16 15.57 8.45 11 16 7.43ZM7 14.43v6.94L16 27l9-5.63v-6.94l-9 5.63-9-5.63Z" fill="currentColor"/></svg>
          </span>
          <h3>Role-aware access policies</h3>
          <p>Map users to lockers using rules that consider identity, schedule, and compliance requirements.</p>
        </article>
        <article class="feature-card" data-animate>
          <span class="feature-icon soft-cyan">
            <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M6 6h20v20H6z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M11 21h10M11 16h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="20" cy="16" r="2" fill="currentColor"/></svg>
          </span>
          <h3>Real-time auditing</h3>
          <p>Every access, top-up, and violation is recorded with context so you can answer “who, what, when” instantly.</p>
        </article>
        <article class="feature-card" data-animate>
          <span class="feature-icon soft-indigo">
            <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M7 9h18M7 16h10M7 23h7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M21 23.5 26 19l-5-4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <h3>Guided workflows</h3>
          <p>Built-in nudges help your team progress from detection to resolution without breaking focus.</p>
        </article>
        <article class="feature-card" data-animate>
          <span class="feature-icon soft-green">
            <svg viewBox="0 0 32 32" aria-hidden="true"><path d="m6 16 6 6 14-14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <h3>Intelligent automation</h3>
          <p>Trigger escalations, notify stakeholders, and lock down assets automatically when patterns deviate.</p>
        </article>
      </div>
    </section>

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
