// HAMS JavaScript - Mobile menu, form validation, and interactive features

// Prevent sidebar flash by applying styles immediately
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .sidebar { 
            background-color: #07bbc1 !important; 
            color: #FFFFFF !important; 
            opacity: 1 !important; 
            visibility: visible !important;
        }
    `;
    document.head.appendChild(style);
})();

document.addEventListener('DOMContentLoaded', function() {
    // Show body after styles are loaded to prevent flash
    document.body.style.visibility = 'visible';
    // Mobile menu toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileMenuBtn && sidebar) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && 
                !sidebar.contains(e.target) && 
                !mobileMenuBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    field.style.borderColor = '#ddd';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showAlert('Please fill in all required fields', 'error');
            }
        });
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.btn-danger, [data-action="delete"]');
    deleteButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-calculate totals in forms
    const quantityInputs = document.querySelectorAll('input[name*="quantity"]');
    quantityInputs.forEach(function(input) {
        input.addEventListener('input', calculateTotals);
    });
    
    // Search functionality
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = this.closest('.card').querySelector('table');
            if (table) {
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            }
        });
    });
    
    // Stock level warnings
    checkStockLevels();
    
    // Session timeout warning
    let sessionWarningShown = false;
    setInterval(function() {
        // Check if session is about to expire (55 minutes)
        if (!sessionWarningShown) {
            sessionWarningShown = true;
            setTimeout(function() {
                if (confirm('Your session will expire in 5 minutes. Do you want to stay logged in?')) {
                    // Make a request to refresh session
                    fetch('refresh_session.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                sessionWarningShown = false;
                            }
                        });
                }
            }, 3300000); // 55 minutes
        }
    }, 60000); // Check every minute
});

// Utility functions
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    const container = document.querySelector('.main-content');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            alertDiv.style.opacity = '0';
            setTimeout(function() {
                alertDiv.remove();
            }, 300);
        }, 5000);
    }
}

function calculateTotals() {
    const quantityInputs = document.querySelectorAll('input[name*="quantity"]');
    let total = 0;
    
    quantityInputs.forEach(function(input) {
        const value = parseFloat(input.value) || 0;
        total += value;
    });
    
    const totalDisplay = document.querySelector('.total-display');
    if (totalDisplay) {
        totalDisplay.textContent = total.toFixed(2);
    }
}

function checkStockLevels() {
    const stockCells = document.querySelectorAll('.stock-level');
    stockCells.forEach(function(cell) {
        const current = parseFloat(cell.dataset.current) || 0;
        const minimum = parseFloat(cell.dataset.minimum) || 0;
        
        if (current <= minimum) {
            cell.style.color = '#dc3545';
            cell.style.fontWeight = 'bold';
            cell.title = 'Low stock warning';
        } else if (current <= minimum * 1.5) {
            cell.style.color = '#856404';
            cell.title = 'Stock running low';
        }
    });
}

function formatNumber(num, decimals = 0) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Export functions for CSV downloads
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    const csvContent = [];
    
    rows.forEach(function(row) {
        const cells = row.querySelectorAll('th, td');
        const rowData = [];
        cells.forEach(function(cell) {
            // Clean up cell content and escape quotes
            let content = cell.textContent.trim().replace(/"/g, '""');
            rowData.push(`"${content}"`);
        });
        csvContent.push(rowData.join(','));
    });
    
    const csvString = csvContent.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'export.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Print functionality
function printReport() {
    window.print();
}

// Offline functionality for mobile users
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js')
        .then(function(registration) {
            console.log('Service Worker registered successfully');
        })
        .catch(function(error) {
            console.log('Service Worker registration failed');
        });
}
