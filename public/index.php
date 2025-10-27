<?php
session_start();

$role = $_SESSION['role'] ?? null;
$isAdmin = $role === 'admin';
$isUser = $role === 'user';

$primaryCtaHref = $role
  ? ($isAdmin ? 'admin/dashboard.php' : 'user/dashboard.php')
  : 'register.php';
$primaryCtaLabel = $role ? 'Go to Dashboard' : 'Create an Account';

$secondaryCtaHref = $role ? ($isAdmin ? 'admin/dashboard.php#modules' : 'user/dashboard.php#modules') : 'login.php';
$secondaryCtaLabel = $role ? 'Explore Modules' : 'Sign in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kandado | Secure Locker Management Platform</title>
  <link rel="icon" href="../assets/icon/icon_tab.png" sizes="any">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/home.css">
</head>
<body class="home-body">
  <div class="home-layers" aria-hidden="true">
    <span class="home-layers__orb home-layers__orb--one"></span>
    <span class="home-layers__orb home-layers__orb--two"></span>
    <span class="home-layers__grid"></span>
  </div>

  <header class="site-header" data-animate>
    <div class="header-inner container">
      <a class="brand" href="index.php">
        <span class="brand-icon">
          <img src="../assets/icon/kandado2.png" alt="Kandado logo" loading="lazy">
        </span>
        <span class="brand-text">Kandado</span>
      </a>

      <nav class="site-nav" aria-label="Primary">
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
          <span class="nav-toggle-bar"></span>
          <span class="nav-toggle-bar"></span>
          <span class="nav-toggle-bar"></span>
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
          <a class="btn ghost" href="login.php">Login</a>
          <a class="btn primary" href="register.php">Register</a>
        <?php else: ?>
          <a class="btn ghost" href="<?= htmlspecialchars($secondaryCtaHref) ?>">Modules</a>
          <a class="btn primary" href="<?= htmlspecialchars($primaryCtaHref) ?>">Dashboard</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main>
    <section class="hero" id="hero">
      <div class="hero-backdrop" aria-hidden="true">
        <span class="hero-backdrop__glow hero-backdrop__glow--one"></span>
        <span class="hero-backdrop__glow hero-backdrop__glow--two"></span>
        <span class="hero-backdrop__ring"></span>
      </div>
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
          <div class="mockup-card" data-parallax-depth="28" data-animate data-delay="200">
            <header>
              <span class="status-pill">Today</span>
              <span class="status-value">32 scheduled unlocks</span>
            </header>
            <ul>
              <li>
                <span class="avatar soft-blue">EV</span>
                <span class="meta">
                  <strong>Elena Vargas</strong>
                  <small>Locker B12 - Verified</small>
                </span>
                <span class="tag success">In</span>
              </li>
              <li>
                <span class="avatar soft-cyan">MK</span>
                <span class="meta">
                  <strong>Mark Kato</strong>
                  <small>Locker C07 - Pending ID</small>
                </span>
                <span class="tag warning">Action</span>
              </li>
              <li>
                <span class="avatar soft-slate">SP</span>
                <span class="meta">
                  <strong>Security Patrol</strong>
                  <small>Alert review 14:30</small>
                </span>
                <span class="tag neutral">Audit</span>
              </li>
            </ul>
          </div>
          <div class="floating-card access hero-float" data-animate data-delay="220" data-parallax-depth="36">
            <span class="icon">
              <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 4a9 9 0 0 0-9 9v5.35A4.65 4.65 0 0 0 11.65 23h.72a2.63 2.63 0 0 0 5.26 0h1.62A4.75 4.75 0 0 0 24 18.25V13a8 8 0 0 0-8-9Zm2.73 19h-5.46a2.73 2.73 0 0 1-2.72-2.73V13a6.73 6.73 0 0 1 13.46 0v7.27A2.73 2.73 0 0 1 18.73 23Z" fill="currentColor"/></svg>
            </span>
            <div>
              <strong>Adaptive Access</strong>
              <p>Context-aware validation secures every unlock.</p>
            </div>
          </div>
          <div class="floating-card analytics hero-float" data-animate data-delay="260" data-parallax-depth="42">
            <span class="icon">
              <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M6 6v20h20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/><path d="M10 19l4-5 4 3 5-9" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div>
              <strong>Predictive insights</strong>
              <p>Spot anomalies before they impact your floor.</p>
            </div>
          </div>
        </div>
      </div>

      <a href="#platform" class="hero-scroll" data-animate data-delay="320" aria-label="Scroll to platform overview">
        <span class="hero-scroll__dot"></span>
        <span class="hero-scroll__label">Discover Kandado</span>
      </a>
    </section>

    <section class="logos" aria-label="Trusted by teams" data-animate>
      <p>Trusted by operations, IT, and security teams to orchestrate high-volume locker ecosystems.</p>
      <div class="logo-row">
        <span class="logo-pill">Campus Ops</span>
        <span class="logo-pill">Workplace HQ</span>
        <span class="logo-pill">ParcelFlow</span>
        <span class="logo-pill">Metro Storage</span>
        <span class="logo-pill">SecureSpace</span>
      </div>
    </section>

    <section class="platform" id="platform" data-animate>
      <div class="section-heading">
        <h2>One platform built for the entire locker lifecycle.</h2>
        <p>From provisioning to real-time incident response, Kandado brings every workflow into one intuitive surface so your team can move faster with confidence.</p>
      </div>

      <div class="platform-grid">
        <article class="platform-card focus" data-animate>
          <h3>Unified Command Center</h3>
          <p>Monitor locker performance, reservations, maintenance tickets, and alerts across every location with a crystal-clear dashboard.</p>
          <ul>
            <li>Instant visibility into occupancy and usage spikes.</li>
            <li>Smart segments for VIP, recurring, or flagged users.</li>
            <li>Live notifications to keep your team aligned.</li>
          </ul>
        </article>
        <article class="platform-card" data-animate>
          <h3>End-to-end User Journeys</h3>
          <p>Deliver smooth digital experiences with email verification, secure top-ups, and responsive support channels out of the box.</p>
          <ul>
            <li>Personalized self-service dashboards.</li>
            <li>Automated receipts, reminders, and nudges.</li>
            <li>Integrated payments and wallet controls.</li>
          </ul>
        </article>
        <article class="platform-card" data-animate>
          <h3>Operations Automation</h3>
          <p>Automate repetitive workflows and enforce policy with intelligent triggers that keep your locker network compliant 24/7.</p>
          <ul>
            <li>Configurable escalation paths for violations.</li>
            <li>Audit-ready trails for every action.</li>
            <li>Scheduled maintenance and smart assignments.</li>
          </ul>
        </article>
      </div>
    </section>

    <section class="features" id="features">
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

    <section class="insights" id="insights" data-animate>
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

    <section class="security" id="security">
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

    <section class="testimonial" data-animate>
      <figure>
        <blockquote>
          "Kandado reduced our access exceptions by 76% in the first month while giving our team a dashboard that feels effortless to live in every day."
        </blockquote>
        <figcaption>
          <strong>Mae Alvarez</strong>
          <span>Head of Campus Operations, Metro University</span>
        </figcaption>
      </figure>
    </section>

    <section class="cta" id="cta" data-animate>
      <div class="cta-card">
        <div>
          <h2>Bring clarity to your locker network.</h2>
          <p>Launch Kandado in days, not months. Configure policies, onboard teams, and automate safeguards with support from our specialists.</p>
        </div>
        <div class="cta-actions">
          <a class="btn primary" href="<?= htmlspecialchars($primaryCtaHref) ?>"><?= htmlspecialchars($primaryCtaLabel) ?></a>
          <?php if (!$role): ?>
            <a class="btn ghost" href="login.php">Talk to support</a>
          <?php else: ?>
            <a class="btn ghost" href="../auth/logout.php">Log out</a>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="footer-inner">
      <a class="brand" href="index.php">
        <span class="brand-icon">
          <img src="../assets/icon/kandado2.png" alt="" aria-hidden="true" loading="lazy">
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
          <a href="../public/login.php">Login</a>
          <a href="../public/register.php">Register</a>
          <a href="../public/forgot_password.php">Reset password</a>
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

  <script src="../assets/js/home.js" defer></script>
</body>
</html>

