<?php
require_once('config/auth_check.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>License Reports</title>
  <link rel="shortcut icon" href="images/favicon.png" />
  <link rel="stylesheet" href="styles/header-sidebar.css">
  <link rel="stylesheet" href="styles/common.css">
  <link rel="stylesheet" href="styles/reports.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>
  <div class="page-containers reports-page-container">
    <!-- Header Section -->
    <div class="report-header">
      <div class="header-left">
        <div class="page-title">License Reports</div>
        <!-- <p class="page-subtitle">Comprehensive overview of all generated licenses and their current status</p> -->
      </div>
      <div class="header-right">
        <select id="searchType" class="search-input" style="width: auto; cursor: pointer;">
          <option value="serial_id">Search Serial ID</option>
          <option value="unique_id">Search Unique ID</option>
          <option value="location_code">Search Location Code</option>
          <option value="date_range">Search Date Range</option>
        </select>

        <input type="text" id="searchValue" placeholder="Search Serial ID" class="search-input">

        <div id="dateRangeInputs" style="display: none; gap: 8px; align-items: center;">
          <input type="date" id="searchFromDate" class="search-input" style="width: 140px;">
          <span style="color: var(--text-dark);">-</span>
          <input type="date" id="searchToDate" class="search-input" style="width: 140px;">
        </div>

        <button onclick="searchReports()" class="btn-search">Search</button>
      </div>
    </div>

    <!-- Reports Table Card -->
    <div class="report-card">
      <div class="table-scroll-wrapper">
        <button class="scroll-btn scroll-left" id="btnScrollLeft">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </button>
        <div class="table-container" id="tableContainer">
          <table id="reportsTable">
            <thead>
              <tr>
                <th>Sl No</th>
                <th>Date Created</th>
                <th>Dealer</th>
                <!-- <th>Location Name</th> -->
                <th>Location Code</th>
                <th>License Validity</th>
                <th>Serial ID</th>
                <th>Unique ID</th>
                <th>Grace Period</th>
                <th>Device Status</th>
                <th>Lic. Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <!-- <tbody>
            <tr>
              <td colspan="8" class="loading-state">
                <div class="loading-spinner"></div>
                Loading reports...
              </td>
            </tr>
          </tbody> -->
            <tbody>
              <tr>
                <td colspan="12" class="loading-cell">
                  <div class="loading-state">
                    <div class="loading-spinner"></div>
                    Loading reports...
                  </div>
                </td>
              </tr>
            </tbody>

          </table>
        </div>
        <button class="scroll-btn scroll-right" id="btnScrollRight">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </button>
      </div>

      <!-- Pagination Controls -->
      <div class="pagination-container" id="paginationControls" style="display: none;">
        <div class="pagination-info">
          Showing <span id="startRange">0</span> to <span id="endRange">0</span> of <span id="totalRecords">0</span>
          entries
        </div>
        <div class="pagination-buttons">
          <button id="prevBtn" onclick="changePage(-1)" disabled>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd"
                d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z" />
            </svg>
            Previous
          </button>
          <span class="page-number" id="pageIndicator">Page 1</span>
          <button id="nextBtn" onclick="changePage(1)" disabled>
            Next
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd"
                d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'components/header-sidebar.php'; ?>

  <script>
    const userRole = "<?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'Limited Access'; ?>";
    let currentPage = 1;
    let totalPages = 1;
    const limit = 10;
    let currentStatus = '';
    let currentClient = '';

    document.addEventListener('DOMContentLoaded', function () {
      const urlParams = new URLSearchParams(window.location.search);
      currentStatus = urlParams.get('status') || '';
      currentClient = urlParams.get('client') || '';

      // Initialize search dropdown handler
      const searchType = document.getElementById('searchType');
      const searchValue = document.getElementById('searchValue');
      const dateRangeInputs = document.getElementById('dateRangeInputs');

      searchType.addEventListener('change', function () {
        // Reset values
        searchValue.value = '';

        if (this.value === 'date_range') {
          searchValue.style.display = 'none';
          dateRangeInputs.style.display = 'flex';
        } else {
          searchValue.style.display = 'block';
          dateRangeInputs.style.display = 'none';

          // Update placeholder based on selection
          const selectedOption = this.options[this.selectedIndex].text;
          searchValue.placeholder = selectedOption;
        }
      });

      fetchReports(currentPage);
      initScrollButtons();
    });

    function initScrollButtons() {
      const container = document.getElementById('tableContainer');
      const leftBtn = document.getElementById('btnScrollLeft');
      const rightBtn = document.getElementById('btnScrollRight');

      if (!container || !leftBtn || !rightBtn) return;

      leftBtn.addEventListener('click', () => {
        const scrollAmount = container.clientWidth * 0.75;
        container.scrollBy({
          left: -scrollAmount,
          behavior: 'smooth'
        });
      });

      rightBtn.addEventListener('click', () => {
        const scrollAmount = container.clientWidth * 0.75;
        container.scrollBy({
          left: scrollAmount,
          behavior: 'smooth'
        });
      });

      container.addEventListener('scroll', updateScrollButtons);
      window.addEventListener('resize', updateScrollButtons);
    }

    function updateScrollButtons() {
      const container = document.getElementById('tableContainer');
      const leftBtn = document.getElementById('btnScrollLeft');
      const rightBtn = document.getElementById('btnScrollRight');

      if (!container || !leftBtn || !rightBtn) return;

      const {
        scrollLeft,
        scrollWidth,
        clientWidth
      } = container;
      const maxScroll = scrollWidth - clientWidth;

      // Show left button if we've scrolled right
      if (scrollLeft > 10) {
        leftBtn.classList.add('visible');
      } else {
        leftBtn.classList.remove('visible');
      }

      // Show right button if there's more content to scroll to
      if (maxScroll > 0 && scrollLeft < maxScroll - 10) {
        rightBtn.classList.add('visible');
      } else {
        rightBtn.classList.remove('visible');
      }
    }

    function searchReports() {
      currentPage = 1;
      // Clear status filter on new search to allow searching all records
      currentStatus = '';
      fetchReports(currentPage);
    }

    async function fetchReports(page) {
      try {
        const tbody = document.querySelector('#reportsTable tbody');
        if (page !== 1) {
          document.querySelector('#reportsTable').style.opacity = '0.6';
        }

        const searchType = document.getElementById('searchType').value;
        const searchValue = document.getElementById('searchValue').value;
        const fromDate = document.getElementById('searchFromDate').value;
        const toDate = document.getElementById('searchToDate').value;

        let url = `api/reports.php?page=${page}&limit=${limit}&_t=${new Date().getTime()}`;

        if (searchType === 'serial_id') {
          url += `&serial_id=${encodeURIComponent(searchValue)}`;
        } else if (searchType === 'unique_id') {
          url += `&unique_id=${encodeURIComponent(searchValue)}`;
        } else if (searchType === 'location_code') {
          url += `&location_code=${encodeURIComponent(searchValue)}`;
        } else if (searchType === 'date_range' && fromDate && toDate) {
          url += `&from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}`;
        }

        if (currentStatus) {
          url += `&status=${encodeURIComponent(currentStatus)}`;
        }

        if (currentClient) {
          url += `&client_name=${encodeURIComponent(currentClient)}`;
        }

        const response = await fetch(url);
        const result = await response.json();

        document.querySelector('#reportsTable').style.opacity = '1';

        if (result.success) {
          renderTable(result.data, page);
          updatePagination(result.pagination);
          // Small delay to allow layout to settle before checking scroll
          setTimeout(updateScrollButtons, 100);
        } else {
          showError(result.message || 'Failed to load reports');
        }
      } catch (error) {
        console.error('Error fetching reports:', error);
        showError('An error occurred while loading reports');
      }
    }

    function renderTable(data, page) {
      const tbody = document.querySelector('#reportsTable tbody');
      tbody.innerHTML = '';

      if (data.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="11">
              <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 16 16" fill="currentColor">
                  <path
                    d="M5 10.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5zm0-2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5z" />
                  <path
                    d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1h1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v1H1V2a2 2 0 0 1 2-2z" />
                  <path
                    d="M1 5v-.5a.5.5 0 0 1 1 0V5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1zm0 3v-.5a.5.5 0 0 1 1 0V8h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1z" />
                </svg>
                <p>No reports found</p>
              </div>
            </td>
          </tr>
        `;
        document.getElementById('paginationControls').style.display = 'none';
        return;
      }

      const startCount = (page - 1) * limit;

      data.forEach((row, index) => {
        const tr = document.createElement('tr');

        // Format date (YYYYMMDD -> DD-MM-YYYY)
        let dateDisplay = row.created_on;
        if (dateDisplay && dateDisplay.length === 8) {
          dateDisplay = `${dateDisplay.substring(6, 8)}-${dateDisplay.substring(4, 6)}-${dateDisplay.substring(0, 4)}`;
        }

        // Format Validity & Status
        let validTill = row.licensee_validtill;
        let statusClass = '';
        let statusText = '';
        let formattedValidTill = validTill;
        let expiryDate = null;

        // Parse validTill
        if (validTill) {
          // Check for YYYY-MM-DD or other standard formats
          if (validTill.includes('-') || validTill.includes('/')) {
            expiryDate = new Date(validTill);
            // Format for display if needed, or keep raw
            formattedValidTill = validTill;
          }
          // Check for YYYYMMDD
          else if (validTill.length === 8 && !isNaN(validTill)) {
            const year = parseInt(validTill.substring(0, 4));
            const month = parseInt(validTill.substring(4, 6)) - 1;
            const day = parseInt(validTill.substring(6, 8));
            expiryDate = new Date(year, month, day);
            formattedValidTill = `${validTill.substring(6, 8)}-${validTill.substring(4, 6)}-${validTill.substring(0, 4)}`;
          }
        }

        if (expiryDate && !isNaN(expiryDate.getTime())) {
          const today = new Date();
          today.setHours(0, 0, 0, 0);

          // Reset expiry date time to 00:00:00 for accurate comparison
          expiryDate.setHours(0, 0, 0, 0);

          if (expiryDate < today) {
            statusClass = 'status-expired';
            statusText = 'Expired';
          } else {
            statusClass = 'status-active';
            statusText = 'Active';
          }
        } else {
          // Default to Active if validity is missing/unknown/invalid
          statusClass = 'status-active';
          statusText = 'Active';
          // Show raw value if it exists, otherwise '-'
          formattedValidTill = validTill ? validTill : '-';
        }

        // Handle Unique ID display
        const uniqueIdDisplay = (row.system_uniqueid && row.system_uniqueid !== 'UNKNOWN') ? row.system_uniqueid : '-';


        const viewLink = `licenses.php?edit_id=${row.id}&mode=view`;
        const editLink = `licenses.php?edit_id=${row.id}`;

        const viewBtn = `
          <a href="${viewLink}" class="btn-icon" style="margin-right: 8px;" title="View">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
              <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
              <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
            </svg>
          </a>
        `;

        const editBtn = `
          <a href="${editLink}" class="btn-icon" title="Edit">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
              <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
            </svg>
          </a>
        `;

        let actionButtons = viewBtn;
        // userRole is defined globally in the script tag below/above
        if (typeof userRole !== 'undefined') {
          if (userRole === 'Administrator') {
            actionButtons += ' ' + editBtn;
          } else if (userRole === 'Limited Access') {
            // Only show edit if NOT Sharekhan
            if (row.client_name !== 'Sharekhan') {
              actionButtons += ' ' + editBtn;
            }
          }
        }

        tr.innerHTML = `
          <td class="text-center">${startCount + index + 1}</td>
          <td>${dateDisplay || '-'}</td>
          <td>${row.licensee_dealer || '-'}</td>
          <td>${row.location_code || '-'}</td>
          <td class="validity-cell">${formattedValidTill}</td>
          <td class="code-cell">${row.system_serialid || '-'}</td>
          <td class="code-cell">${uniqueIdDisplay}</td>
          <td class="text-center">${row.engine_graceperiod || '-'}</td>
          <td>${row.device_status || '-'}</td>
          <td><span class="status-badge ${statusClass}">${statusText}</span></td>
          <td class="action-cell">
            ${actionButtons}
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    function updatePagination(pagination) {
      currentPage = pagination.current_page;
      totalPages = pagination.total_pages;

      const container = document.getElementById('paginationControls');

      if (pagination.total_records > 0) {
        container.style.display = 'flex';

        const start = (currentPage - 1) * limit + 1;
        const end = Math.min(currentPage * limit, pagination.total_records);

        document.getElementById('startRange').textContent = start;
        document.getElementById('endRange').textContent = end;
        document.getElementById('totalRecords').textContent = pagination.total_records;

        document.getElementById('prevBtn').disabled = currentPage <= 1;
        document.getElementById('nextBtn').disabled = currentPage >= totalPages;

        document.getElementById('pageIndicator').textContent = `Page ${currentPage} of ${totalPages}`;
      } else {
        container.style.display = 'none';
      }
    }

    function changePage(delta) {
      const newPage = currentPage + delta;
      if (newPage >= 1 && newPage <= totalPages) {
        fetchReports(newPage);
      }
    }

    function showError(message) {
      const tbody = document.querySelector('#reportsTable tbody');
      tbody.innerHTML = `
        <tr>
          <td colspan="12" class="error-state">${message}</td>
        </tr>
      `;
      document.getElementById('paginationControls').style.display = 'none';
    }
  </script>
</body>

</html>