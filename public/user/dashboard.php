<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$TOTAL_LOCKERS = 4;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="color-scheme" content="light" />
  <meta name="theme-color" content="#ffffff" />
  <title>Locker Dashboard • Kandado</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/kandado/assets/css/users_dashboard.css">

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/kandado/includes/user_header.php'; ?>

  <main class="container" role="main">
    <header class="page-header" role="region" aria-label="Locker dashboard controls">
      <div class="title-wrap">
        <h2>Locker Dashboard</h2>
        <div class="legend" aria-hidden="true">
          <span class="pill available"><span class="dot"></span>Available</span>
          <span class="pill occupied"><span class="dot"></span>Occupied</span>
          <span class="pill hold"><span class="dot"></span>On&nbsp;Hold</span>
        </div>
      </div>
      <div class="toolbar">
        <button id="refreshBtn" class="btn" type="button" title="Refresh now" aria-controls="lockerGrid">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 5v4h4"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M4 13a8.1 8.1 0 0 0 15.5 2M20 19v-4h-4"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Refresh
        </button>
        <span id="lastUpdated" class="last-updated" aria-live="polite">—</span>
      </div>
    </header>

    <div id="offlineBanner" class="offline hidden" role="alert" aria-live="assertive">
      You’re offline. Data may be out of date.
    </div>

    <section class="overview" aria-label="Locker overview">
      <div class="overview-grid">
        <article class="kpi kpi-occupancy" aria-label="Occupancy">
          <div class="donut" id="occDonut" style="--p:0; --c: var(--occ-green);" aria-live="polite" aria-label="Occupancy 0%">
            <div class="donut-label" id="kpiOcc">0%</div>
          </div>
          <div class="kpi-meta">
            <div class="kpi-title">Occupancy</div>
            <div class="kpi-sub" id="kpiOccSub">0 in use • 0 available</div>
          </div>
        </article>

        <article class="kpi">
          <div class="kpi-title">Total Lockers</div>
          <div class="kpi-value" id="kpiTotal"><?= (int)$TOTAL_LOCKERS ?></div>
          <div class="kpi-sub">All branches</div>
        </article>

        <article class="kpi">
          <div class="kpi-title">Available</div>
          <div class="kpi-value" id="kpiAvailable">0</div>
          <div class="kpi-sub">Ready to reserve</div>
        </article>

        <article class="kpi">
          <div class="kpi-title">Occupied</div>
          <div class="kpi-value" id="kpiOccupied">0</div>
          <div class="kpi-sub">In use now</div>
        </article>

        <article class="kpi">
          <div class="kpi-title">On Hold</div>
          <div class="kpi-value" id="kpiHold">0</div>
          <div class="kpi-sub">Item inside</div>
        </article>
      </div>
    </section>

    <section class="filter-bar" aria-label="Filters">
      <div class="segmented" role="tablist" aria-label="Status filter">
        <button class="seg active" id="filterAll" data-filter="all" role="tab" aria-selected="true">All</button>
        <button class="seg" id="filterAvailable" data-filter="available" role="tab" aria-selected="false">Available</button>
        <button class="seg" id="filterOccupied" data-filter="occupied" role="tab" aria-selected="false">Occupied</button>
        <button class="seg" id="filterHold" data-filter="hold" role="tab" aria-selected="false">On Hold</button>
      </div>

      <div class="search">
        <label for="searchInput" class="sr-only">Find locker number</label>
        <input id="searchInput" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Find locker # (e.g., 3)" />
        <button id="clearSearch" type="button" class="clear" aria-label="Clear search">×</button>
      </div>
    </section>

    <section id="lockerGrid" class="grid" aria-live="polite" aria-busy="true"></section>
    <div id="emptyState" class="empty-state hidden" aria-live="polite">
      <div class="empty-icon" aria-hidden="true">🔎</div>
      <div class="empty-title">No lockers match your filters</div>
      <div class="empty-sub">Try changing the status or clearing the search.</div>
    </div>
  </main>

  <!-- Pass only what's needed from PHP to JS -->
  <script>
    window.DASHBOARD = {
      totalLockers: <?= (int)$TOTAL_LOCKERS ?>
    };
  </script>
  <!-- External JS -->
  <script src="/kandado/assets/js/user_dashboard.js?v=1"></script>

</body>
</html>
