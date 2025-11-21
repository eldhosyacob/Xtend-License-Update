<?php
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

  <div class="header-center">
    <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
  </div>

  <div class="header-right">
    <!-- <div class="header-search">
      <input type="text" placeholder="Search..." class="search-input">
      <button class="search-btn">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
          <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
        </svg>
      </button>
    </div> -->

    <!-- <div class="header-notifications">
      <button class="notification-btn" aria-label="Notifications">
        <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor">
          <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zm.995-14.901a1 1 0 1 0-1.99 0A5.002 5.002 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901z"/>
        </svg>
        <span class="notification-badge">3</span>
      </button>
    </div> -->

    <div class="header-user">
      <button class="user-menu-btn" id="userMenuBtn">
        <div class="user-avatar">
          <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor">
            <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
            <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
          </svg>
        </div>
        <span class="user-name">Admin</span>
        <svg class="dropdown-arrow" width="12" height="12" viewBox="0 0 16 16" fill="currentColor">
          <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
        </svg>
      </button>

      <div class="user-dropdown" id="userDropdown">
        <a href="#profile" class="dropdown-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
            <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
            <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
          </svg>
          Profile
        </a>
        <a href="#settings" class="dropdown-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
            <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
            <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319z"/>
          </svg>
          Settings
        </a>
        <div class="dropdown-divider"></div>
        <a href="logout.php" class="dropdown-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
            <path d="M12 1a1 1 0 0 1 1 1v13h1.5a.5.5 0 0 1 0 1h-13a.5.5 0 0 1 0-1H3V2a1 1 0 0 1 1-1h8zm-2 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
          </svg>
          Logout
        </a>
      </div>
    </div>
  </div>
</header>

<!-- Sidebar -->
<aside class="sidebar-container" id="sidebar">
  <nav class="sidebar-nav">
    <div class="sidebar-section">
      <!-- <h3 class="sidebar-section-title">Main</h3> -->
      <ul class="sidebar-menu">
        <li class="sidebar-menu-item <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
          <a href="dashboard.php" class="sidebar-link">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path d="M8 3.293l6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293l6-6zm5-.793V1.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293l-2-2z"/>
            </svg>
            <span>Dashboard</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'licenses') ? 'active' : ''; ?>">
          <a href="licenses.php" class="sidebar-link">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
              <path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94z"/>
            </svg>
            <span>Licenses</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'reports') ? 'active' : ''; ?>">
          <a href="reports.php" class="sidebar-link">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z"/>
            </svg>
            <span>Reports</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'users') ? 'active' : ''; ?>">
          <a href="users.php" class="sidebar-link">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816zM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275zM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
            </svg>
            <span>Users</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'logout') ? 'active' : ''; ?>">
          <a href="logout.php" class="sidebar-link">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
              <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
            </svg>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>

    <!-- <div class="sidebar-section">
      <h3 class="sidebar-section-title">Management</h3>
      <ul class="sidebar-menu">
        <li class="sidebar-menu-item <?php echo ($current_page == 'reports') ? 'active' : ''; ?>">
          <a href="reports.php" class="sidebar-link">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z"/>
            </svg>
            <span>Reports</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'analytics') ? 'active' : ''; ?>">
          <a href="analytics.php" class="sidebar-link">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path d="M7.5 1.018a7 7 0 0 0-4.79 11.566L7.5 7.793V1.018zm1 0V7.5h6.482A7.001 7.001 0 0 0 8.5 1.018zM14.982 8.5H8.207l-4.79 4.79A7 7 0 0 0 14.982 8.5zM0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8z"/>
            </svg>
            <span>Analytics</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'settings') ? 'active' : ''; ?>">
          <a href="settings.php" class="sidebar-link">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
              <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319z"/>
            </svg>
            <span>Settings</span>
          </a>
        </li>
      </ul>
    </div> -->

    <!-- <div class="sidebar-section">
      <h3 class="sidebar-section-title">Support</h3>
      <ul class="sidebar-menu">
        <li class="sidebar-menu-item <?php echo ($current_page == 'documentation') ? 'active' : ''; ?>">
          <a href="documentation.php" class="sidebar-link">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
            </svg>
            <span>Documentation</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'help') ? 'active' : ''; ?>">
          <a href="help.php" class="sidebar-link">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.496 6.033h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286a.237.237 0 0 0 .241.247zm2.325 6.443c.61 0 1.029-.394 1.029-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94 0 .533.425.927 1.01.927z"/>
            </svg>
            <span>Help Center</span>
          </a>
        </li>
      </ul>
    </div> -->
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user-info">
      <div class="sidebar-user-avatar">
        <svg width="24" height="24" viewBox="0 0 16 16" fill="currentColor">
          <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
          <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
        </svg>
      </div>
      <div class="sidebar-user-details">
        <div class="sidebar-user-name">Admin User</div>
        <div class="sidebar-user-role">Administrator</div>
      </div>
    </div>
  </div>
</aside>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script src="plugins/jquery-3.7.1.min.js"></script>
<script>
  $(document).ready(function() {
    // Hamburger menu toggle
    $('#hamburgerMenu').on('click', function() {
      $('#sidebar').toggleClass('active');
      $('#sidebarOverlay').toggleClass('active');
      $(this).toggleClass('active');
    });

    // Close sidebar when clicking overlay
    $('#sidebarOverlay').on('click', function() {
      $('#sidebar').removeClass('active');
      $(this).removeClass('active');
      $('#hamburgerMenu').removeClass('active');
    });

    // User dropdown toggle
    $('#userMenuBtn').on('click', function(e) {
      e.stopPropagation();
      $('#userDropdown').toggleClass('active');
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.header-user').length) {
        $('#userDropdown').removeClass('active');
      }
    });

    // Search functionality
    $('.search-btn').on('click', function() {
      var searchQuery = $('.search-input').val();
      if (searchQuery) {
        console.log('Searching for:', searchQuery);
        // Add your search logic here
      }
    });

    $('.search-input').on('keypress', function(e) {
      if (e.which === 13) { // Enter key
        $('.search-btn').click();
      }
    });

    // Notification button
    $('.notification-btn').on('click', function() {
      console.log('Show notifications');
      // Add notification panel logic here
    });
  });
</script>