<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Get current page name from URL
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Define page titles
$page_titles = [
  'dashboard' => 'Dashboard',
  'licenses' => 'Licenses',
  'users' => 'Users',
  'products' => 'Products',
  'reports' => 'Reports',
  'analytics' => 'Analytics',
  'settings' => 'Settings',
  'documentation' => 'Documentation',
  'help' => 'Help Center'
];

// Get the page title, default to the current page name with first letter capitalized
$page_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : ucfirst($current_page);

// Get user information from session
$user_full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin';
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : '';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
?>

<!-- Header -->
<header class="header-container">
  <div class="header-left">
    <button class="hamburger-menu" id="hamburgerMenu" aria-label="Toggle menu">
      <span class="hamburger-line"></span>
      <span class="hamburger-line"></span>
      <span class="hamburger-line"></span>
    </button>
    <div class="xtend-logo"></div>
  </div>

  <div class="header-right">
    <div class="header-user">
      <button class="user-menu-btn" id="userMenuBtn">
        <div class="user-avatar">
          <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor">
            <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
            <path
              d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z" />
          </svg>
        </div>
        <span class="user-name"><?php echo htmlspecialchars($user_full_name); ?></span>
        <svg class="dropdown-arrow" width="12" height="12" viewBox="0 0 16 16" fill="currentColor">
          <path
            d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z" />
        </svg>
      </button>

      <div class="user-dropdown" id="userDropdown">
        <a href="users.php?edit=<?php echo $user_id; ?>&username=<?php echo urlencode($username); ?>&fullname=<?php echo urlencode($user_full_name); ?>"
          class="dropdown-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
            <path
              d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z" />
            <path
              d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319z" />
          </svg>
          Settings
        </a>
        <div class="dropdown-divider"></div>
        <a href="#" onclick="showLogoutModal(); return false;" class="dropdown-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
            <path
              d="M12 1a1 1 0 0 1 1 1v13h1.5a.5.5 0 0 1 0 1h-13a.5.5 0 0 1 0-1H3V2a1 1 0 0 1 1-1h8zm-2 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
          </svg>
          Logout
        </a>
      </div>
    </div>
  </div>
</header>

<!-- Sidebar -->
<aside class="sidebar-container" id="sidebar">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <nav class="sidebar-nav">
    <div class="sidebar-section">
      <ul class="sidebar-menu">
        <li class="sidebar-menu-item <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
          <a href="dashboard.php" class="sidebar-link">
            <!-- <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M8 3.293l6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293l6-6zm5-.793V1.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293l-2-2z" />
            </svg> -->
            <i class="fa-solid fa-house" style="color:#d1d1d1"></i>
            <span>Dashboard</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'licenses') ? 'active' : ''; ?>">
          <a href="licenses.php" class="sidebar-link">
            <!-- <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z" />
              <path
                d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94z" />
            </svg> -->
            <i class="fa-solid fa-id-card" style="color:#71cbae"></i>
            <span>Licenses</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'users') ? 'active' : ''; ?>">
          <a href="users.php" class="sidebar-link">
            <!-- <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816zM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275zM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4z" />
            </svg> -->
            <i class="fa-solid fa-users" style="color:#668cd3"></i>
            <span>Users</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'reports') ? 'active' : ''; ?>">
          <a href="reports.php" class="sidebar-link">
            <!-- <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z" />
            </svg> -->
            <i class="fa-solid fa-chart-simple" style="color:#c19149"></i>
            <span>Reports</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'logout') ? 'active' : ''; ?>">
          <a href="#" onclick="showLogoutModal(); return false;" class="sidebar-link">
            <!-- <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd"
                d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z" />
              <path fill-rule="evenodd"
                d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z" />
            </svg> -->
            <i class="fa-solid fa-right-from-bracket" style="color:#d95555"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user-info">
      <div class="sidebar-user-avatar">
        <svg width="24" height="24" viewBox="0 0 16 16" fill="currentColor">
          <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
          <path
            d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z" />
        </svg>
      </div>
      <div class="sidebar-user-details">
        <div class="sidebar-user-name"><?php echo htmlspecialchars($user_full_name); ?></div>
        <div class="sidebar-user-role">Administrator</div>
      </div>
    </div>
  </div>
</aside>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Logout Modal -->
<div id="logoutModal"
  style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
  <div
    style="background-color:#fff; margin:15% auto; padding:30px; border-radius:12px; width:400px; max-width:90%; text-align:center; box-shadow:0 4px 8px rgba(0,0,0,0.2);">
    <h2 style="margin-bottom:20px; color:#333; font-size:24px;">Confirm Logout</h2>
    <p style="margin-bottom:30px; color:#666; font-size:16px;">Are you sure you want to logout?</p>
    <div style="display:flex; gap:10px; justify-content:center;">
      <button onclick="hideLogoutModal()"
        style="padding:10px 30px; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; background-color:#6c757d; color:white; transition:background-color 0.3s;"
        onmouseover="this.style.backgroundColor='#5a6268'"
        onmouseout="this.style.backgroundColor='#6c757d'">Cancel</button>
      <button onclick="confirmLogout()"
        style="padding:10px 30px; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; background-color:#dc3545; color:white; transition:background-color 0.3s;"
        onmouseover="this.style.backgroundColor='#c82333'"
        onmouseout="this.style.backgroundColor='#dc3545'">Logout</button>
    </div>
  </div>
</div>

<script src="plugins/jquery-3.7.1.min.js"></script>
<script>
  $(document).ready(function () {
    // Hamburger menu toggle
    $('#hamburgerMenu').on('click', function () {
      $('#sidebar').toggleClass('active');
      $('#sidebarOverlay').toggleClass('active');
      $(this).toggleClass('active');
    });

    // Close sidebar when clicking overlay
    $('#sidebarOverlay').on('click', function () {
      $('#sidebar').removeClass('active');
      $(this).removeClass('active');
      $('#hamburgerMenu').removeClass('active');
    });

    // User dropdown toggle
    $('#userMenuBtn').on('click', function (e) {
      e.stopPropagation();
      $('#userDropdown').toggleClass('active');
    });

    // Close dropdown when clicking outside
    $(document).on('click', function (e) {
      if (!$(e.target).closest('.header-user').length) {
        $('#userDropdown').removeClass('active');
      }
    });

    // Search functionality
    $('.search-btn').on('click', function () {
      var searchQuery = $('.search-input').val();
      if (searchQuery) {
        console.log('Searching for:', searchQuery);
        // Add your search logic here
      }
    });

    $('.search-input').on('keypress', function (e) {
      if (e.which === 13) { // Enter key
        $('.search-btn').click();
      }
    });

    // Notification button
    $('.notification-btn').on('click', function () {
      console.log('Show notifications');
      // Add notification panel logic here
    });
  });

  // Logout modal functions
  function showLogoutModal() {
    $('#logoutModal').fadeIn(200);
  }

  function hideLogoutModal() {
    $('#logoutModal').fadeOut(200);
  }

  function confirmLogout() {
    window.location.href = 'logout.php?confirm=yes';
  }
</script>