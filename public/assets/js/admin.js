/**
 * SAMS Admin Dashboard JavaScript
 * Handles UI interactions and AJAX requests
 */

// Global Configuration
const API_BASE_URL = '/api/';
const ITEMS_PER_PAGE = 20;

// Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                // Mobile: Toggle sidebar visibility
                sidebar.classList.toggle('mobile-open');
                // Close sidebar when clicking outside
                if (sidebar.classList.contains('mobile-open')) {
                    document.addEventListener('click', closeSidebarOnOutsideClick);
                }
            } else {
                // Desktop: Toggle collapsed state
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
        });
    }

    // Close sidebar on outside click (mobile)
    function closeSidebarOnOutsideClick(e) {
        if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
            sidebar.classList.remove('mobile-open');
            document.removeEventListener('click', closeSidebarOnOutsideClick);
        }
    }

    // Close sidebar when clicking on a nav link (mobile)
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        const text = link.querySelector('span')?.textContent || '';
        link.setAttribute('data-tooltip', text);
        
        // Close sidebar on mobile when clicking a link
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('mobile-open');
                document.removeEventListener('click', closeSidebarOnOutsideClick);
            }
        });
    });

    // Active menu item
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || href === 'index.php' && currentPage === '') {
            link.classList.add('active');
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open', 'collapsed');
            mainContent.classList.remove('expanded');
        }
    });
});

// API Helper Functions
async function apiRequest(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include'
    };

    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(API_BASE_URL + endpoint, options);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Request failed');
        }

        return result;
    } catch (error) {
        console.error('API Error:', error);
        showAlert('error', error.message);
        throw error;
    }
}

// Alert System
function showAlert(type, message, duration = 5000) {
    const alertContainer = document.getElementById('alertContainer') || createAlertContainer();
    
    const alertTypes = {
        success: 'alert-success',
        error: 'alert-danger',
        warning: 'alert-warning',
        info: 'alert-info'
    };

    const icons = {
        success: 'bi-check-circle',
        error: 'bi-exclamation-circle',
        warning: 'bi-exclamation-triangle',
        info: 'bi-info-circle'
    };

    const alert = document.createElement('div');
    alert.className = `alert ${alertTypes[type]} alert-dismissible fade show d-flex align-items-center`;
    alert.innerHTML = `
        <i class="bi ${icons[type]} me-2 fs-5"></i>
        <div>${message}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    alertContainer.appendChild(alert);

    // Auto dismiss
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 150);
    }, duration);
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alertContainer';
    container.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; max-width: 400px;';
    document.body.appendChild(container);
    return container;
}

// Loading Spinner
function showLoading() {
    const spinner = document.createElement('div');
    spinner.id = 'loadingSpinner';
    spinner.className = 'spinner-overlay';
    spinner.innerHTML = `
        <div class="spinner-border spinner-border-lg text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    `;
    document.body.appendChild(spinner);
}

function hideLoading() {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.remove();
    }
}

// Confirmation Dialog
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Data Table Functions
function initDataTable(tableId, options = {}) {
    const table = document.getElementById(tableId);
    if (!table) return;

    // Add search functionality
    if (options.searchable) {
        const searchInput = document.getElementById(options.searchInputId || 'tableSearch');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const filter = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        }
    }

    // Add sorting
    if (options.sortable) {
        const headers = table.querySelectorAll('thead th[data-sortable]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                sortTable(table, this.cellIndex);
            });
        });
    }
}

function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const sortedRows = rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        return aValue.localeCompare(bValue, undefined, { numeric: true });
    });

    tbody.innerHTML = '';
    sortedRows.forEach(row => tbody.appendChild(row));
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }

        // Email validation
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                input.classList.add('is-invalid');
                isValid = false;
            }
        }
    });

    return isValid;
}

// Clear Form
function clearForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }
}

// Format Date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Format Time
function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

// Export Table to CSV
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    const csv = [];

    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        
        cols.forEach(col => {
            // Skip action columns
            if (!col.classList.contains('action-column')) {
                rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
            }
        });
        
        csv.push(rowData.join(','));
    });

    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}

// Print Page
function printPage() {
    window.print();
}

// Debounce Function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Logout Function
async function logout() {
    if (!confirm('Are you sure you want to logout?')) return;

    try {
        showLoading();
        await apiRequest('public/logout.php', 'POST');
        window.location.href = '../login.php';
    } catch (error) {
        hideLoading();
    }
}

// Image Preview
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview || !input.files || !input.files[0]) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}

// Copy to Clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showAlert('success', 'Copied to clipboard!', 2000);
    }).catch(err => {
        showAlert('error', 'Failed to copy');
    });
}

// Generate Random Password
function generatePassword(length = 12) {
    const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return password;
}

// Initialize Tooltips
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
});
