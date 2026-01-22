<?php
// Check if user is authenticated
require_once('config/auth_check.php');
require_once('config/database.php');

// Get database connection
$pdo = getDatabaseConnection();

// Fetch dashboard statistics
$stats = [
  'total_users' => 0,
  'total_licenses' => 0,
  'active_licenses' => 0,
  'expired_licenses' => 0
];

if ($pdo) {
  try {
    // Get total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $stmt->fetch()['count'];

    // License stats will be fetched via API
    $stats['active_licenses'] = 0;
    $stats['expired_licenses'] = 0;

    // Get recent users
    $stmt = $pdo->query("SELECT id, username, full_name, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    $recent_users = $stmt->fetchAll();
  } catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
  }
}
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Dashboard</title>
  <link rel="shortcut icon" href="images/favicon.png" />
  <link rel="stylesheet" href="styles/dashboard.css">
  <link rel="stylesheet" href="styles/header-sidebar.css">
  <link rel="stylesheet" href="styles/common.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>
  <div class="dashboard-page-container page-containers">
    <!-- Welcome Section -->
    <div class="welcome-section">
      <div class="welcome-content">
        <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>!</h1>
        <p class="welcome-subtitle">Here’s what’s going on with your license system.</p>
      </div>
      <div class="welcome-date">
        <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor">
          <path
            d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
        </svg>
        <span><?php echo date('l, F j, Y'); ?></span>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <a href="users.php" class="stat-card stat-card-primary"
        style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-icon">
          <svg width="32" height="32" viewBox="0 0 16 16" fill="currentColor">
            <path
              d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816zM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275zM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4z" />
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Users</div>
          <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
          <div class="stat-change positive">
            <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z" />
            </svg>
            <span>12% from last month</span>
          </div>
        </div>
      </a>

      <a href="reports.php?status=active" class="stat-card stat-card-success"
        style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-icon">
          <svg width="32" height="32" viewBox="0 0 16 16" fill="currentColor">
            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z" />
            <path
              d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z" />
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-label">Active Licenses</div>
          <div class="stat-value" id="stat-active-licenses">-</div>
          <div class="stat-change positive">
            <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z" />
            </svg>
            <span>8% from last month</span>
          </div>
        </div>
      </a>

      <a href="reports.php?status=expiring" class="stat-card stat-card-warning"
        style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-icon">
          <svg width="32" height="32" viewBox="0 0 16 16" fill="currentColor">
            <path
              d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z" />
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-label">Expiring Soon</div>
          <div class="stat-value" id="stat-expiring-soon">-</div>
          <div class="stat-change neutral">
            <span>Next 30 days</span>
          </div>
        </div>
      </a>

      <a href="reports.php?status=expired" class="stat-card stat-card-danger"
        style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-icon">
          <svg width="32" height="32" viewBox="0 0 16 16" fill="currentColor">
            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z" />
            <path
              d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z" />
          </svg>
        </div>
        <div class="stat-content">
          <div class="stat-label">Expired Licenses</div>
          <div class="stat-value" id="stat-expired-licenses">-</div>
          <div class="stat-change negative">
            <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M8 1a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L7.5 13.293V1.5A.5.5 0 0 1 8 1z" />
            </svg>
            <span>3 this week</span>
          </div>
        </div>
      </a>
    </div>

    <!-- Charts and Activity Section -->
    <div class="dashboard-grid">
      <!-- License Trend Chart -->
      <div class="dashboard-card chart-card">
        <div class="card-header">
          <h2 class="card-title">License Trends</h2>
          <div class="card-actions">
            <button class="btn-icon" title="Refresh">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path
                  d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z" />
                <path
                  d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z" />
              </svg>
            </button>
          </div>
        </div>
        <div class="card-body">
          <canvas id="licenseChart"></canvas>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="dashboard-card activity-card">
        <div class="card-header">
          <h2 class="card-title">Recent Activity</h2>
          <a href="users.php" class="btn-link">View All</a>
        </div>
        <div class="card-body">
          <div class="activity-list">
            <?php if (!empty($recent_users)): ?>
              <?php foreach ($recent_users as $user): ?>
                <div class="activity-item">
                  <div class="activity-icon activity-icon-user">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                      <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                      <path
                        d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z" />
                    </svg>
                  </div>
                  <div class="activity-content">
                    <div class="activity-title">User Logged In</div>
                    <div class="activity-description"><?php echo htmlspecialchars($user['full_name']); ?> logged in the
                      system
                    </div>
                  </div>
                  <div class="activity-time"><?php echo date('M j', strtotime($user['created_at'])); ?></div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 16 16" fill="currentColor" opacity="0.3">
                  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z" />
                  <path
                    d="M5 6.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm9 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zM8 13c-1.493 0-2.729-.995-3.123-2.35a.5.5 0 1 1 .946-.3C6.141 11.436 6.974 12 8 12c1.026 0 1.859-.564 2.177-1.65a.5.5 0 1 1 .946.3C10.729 12.005 9.493 13 8 13z" />
                </svg>
                <p>No recent activity</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <h2 class="section-title">Quick Actions</h2>
      <div class="actions-grid">
        <a href="licenses.php" class="action-card">
          <div class="action-icon action-icon-primary">
            <svg width="24" height="24" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z" />
              <path
                d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z" />
            </svg>
          </div>
          <div class="action-content">
            <h3 class="action-title">Create License</h3>
            <p class="action-description">Generate a new license file</p>
          </div>
        </a>

        <a href="users.php" class="action-card">
          <div class="action-icon action-icon-success">
            <svg width="24" height="24" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z" />
              <path
                d="M13.5 5a.5.5 0 0 1 .5.5V7h1.5a.5.5 0 0 1 0 1H14v1.5a.5.5 0 0 1-1 0V8h-1.5a.5.5 0 0 1 0-1H13V5.5a.5.5 0 0 1 .5-.5z" />
            </svg>
          </div>
          <div class="action-content">
            <h3 class="action-title">Add User</h3>
            <p class="action-description">Create a new user account</p>
          </div>
        </a>

        <a href="reports.php" class="action-card">
          <div class="action-icon action-icon-info">
            <svg width="24" height="24" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z" />
            </svg>
          </div>
          <div class="action-content">
            <h3 class="action-title">View Reports</h3>
            <p class="action-description">Access analytics and insights</p>
          </div>
        </a>

        <a href="licenses.php" class="action-card">
          <div class="action-icon action-icon-warning">
            <svg width="24" height="24" viewBox="0 0 16 16" fill="currentColor">
              <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z" />
              <path
                d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94z" />
            </svg>
          </div>
          <div class="action-content">
            <h3 class="action-title">Manage Licenses</h3>
            <p class="action-description">View and edit all licenses</p>
          </div>
        </a>
      </div>
    </div>
  </div>

  <?php include 'components/header-sidebar.php'; ?>

  <script>
    // Fetch dashboard stats
    async function fetchDashboardStats() {
      try {
        const response = await fetch('api/reports.php?summary=true');
        const result = await response.json();

        if (result.success) {
          const stats = result.stats;

          // Animate numbers
          animateValue("stat-active-licenses", 0, stats.active_licenses, 1000);
          animateValue("stat-expiring-soon", 0, stats.expiring_soon, 1000);
          animateValue("stat-expired-licenses", 0, stats.expired_licenses, 1000);

          // Update chart if it exists
          updateChart(stats);
        }
      } catch (error) {
        console.error('Error fetching stats:', error);
      }
    }

    function animateValue(id, start, end, duration) {
      if (start === end) {
        document.getElementById(id).textContent = end;
        return;
      }
      const range = end - start;
      let current = start;
      const increment = end > start ? 1 : -1;
      const stepTime = Math.abs(Math.floor(duration / range));
      const obj = document.getElementById(id);

      // If range is large, step by more than 1
      const step = Math.max(1, Math.floor(range / (duration / 16))); // 60fps approx

      const timer = setInterval(function () {
        current += step;
        if ((step > 0 && current >= end) || (step < 0 && current <= end)) {
          current = end;
          clearInterval(timer);
        }
        obj.textContent = current.toLocaleString();
      }, 16);
    }

    function updateChart(stats) {
      const ctx = document.getElementById('licenseChart');
      if (ctx && Chart.getChart(ctx)) {
        const chart = Chart.getChart(ctx);
        // Update last data point for demo purposes or fetch real trend data
        // For now, we just update the last point of datasets to match current stats
        const lastIndex = chart.data.labels.length - 1;
        chart.data.datasets[0].data[lastIndex] = stats.active_licenses;
        chart.data.datasets[1].data[lastIndex] = stats.expired_licenses;
        chart.update();
      }
    }

    document.addEventListener('DOMContentLoaded', fetchDashboardStats);

    // License Trend Chart
    const ctx = document.getElementById('licenseChart');
    if (ctx) {
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
          datasets: [{
            label: 'Active Licenses',
            data: [12, 19, 15, 25, 22, 30, 28, 35, 32, 38, 42, 0], // Last point will be updated
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
          }, {
            label: 'Expired',
            data: [3, 5, 4, 6, 5, 7, 6, 8, 7, 9, 8, 0], // Last point will be updated
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.4,
            fill: true
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                usePointStyle: true,
                padding: 15,
                font: {
                  size: 12,
                  family: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
                }
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              padding: 12,
              cornerRadius: 8,
              titleFont: {
                size: 13,
                weight: '600'
              },
              bodyFont: {
                size: 12
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: 'rgba(0, 0, 0, 0.05)',
                drawBorder: false
              },
              ticks: {
                font: {
                  size: 11
                }
              }
            },
            x: {
              grid: {
                display: false,
                drawBorder: false
              },
              ticks: {
                font: {
                  size: 11
                }
              }
            }
          }
        }
      });
    }

    // Add animation on scroll
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, observerOptions);

    document.querySelectorAll('.stat-card, .dashboard-card, .action-card').forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      observer.observe(el);
    });
  </script>

</body>

</html>