<?php
// views/dashboard/index.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// 1. Secure the page
requireLogin();

// 2. Load Layout
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-gauge-high text-primary me-2"></i>Dashboard
        </h4>
        <span class="text-muted small"><?= date('l, F j, Y') ?></span>
    </div>

    <!-- Stats Cards Row -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-card stat-card primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label mb-1">Today's Sales</p>
                            <h3 class="stat-value text-primary mb-0" id="todaySales">₱0.00</h3>
                            <small class="text-muted" id="todayCount">0 transactions</small>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-peso-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-card stat-card success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label mb-1">Total Products</p>
                            <h3 class="stat-value text-success mb-0" id="totalProducts">0</h3>
                            <small class="text-muted">Active items</small>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-card stat-card warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label mb-1">Low Stock Alerts</p>
                            <h3 class="stat-value text-warning mb-0" id="lowStockCount">0</h3>
                            <small class="text-muted">Need restocking</small>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-card stat-card danger h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="stat-label mb-1">Receivables</p>
                            <h3 class="stat-value text-danger mb-0" id="receivablesTotal">₱0.00</h3>
                            <small class="text-muted" id="receivablesCount">0 pending</small>
                        </div>
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fa-solid fa-hand-holding-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <!-- Sales Trend Chart -->
        <div class="col-lg-8">
            <div class="card chart-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-chart-line text-primary me-2"></i>Weekly Sales Trend</h6>
                    <span class="badge bg-light text-secondary">Last 7 Days</span>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods Chart -->
        <div class="col-lg-4">
            <div class="card chart-card h-100">
                <div class="card-header">
                    <h6><i class="fa-solid fa-credit-card text-success me-2"></i>Payment Methods</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="chart-container chart-container-sm" style="max-width: 280px;">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Top Products Chart -->
        <div class="col-lg-6">
            <div class="card chart-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-trophy text-warning me-2"></i>Top Selling Products</h6>
                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="topProductMode" id="topModeQty" checked>
                            <label class="btn btn-outline-primary" for="topModeQty">By Qty</label>
                            <input type="radio" class="btn-check" name="topProductMode" id="topModeRev">
                            <label class="btn btn-outline-primary" for="topModeRev">By Sales</label>
                        </div>
                        <span class="badge bg-light text-secondary">Last 30 Days</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 400px;">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Items -->
        <div class="col-lg-6">
            <div class="card chart-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-boxes-packing text-danger me-2"></i>Low Stock Items</h6>
                    <a href="<?= BASE_URL ?>views/inventory/restock.php" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-truck-ramp-box me-1"></i>Restock
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="height: 400px; overflow-y: auto;">
                        <table class="table low-stock-table mb-0">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th class="ps-3">Product</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Threshold</th>
                                </tr>
                            </thead>
                            <tbody id="lowStockTable">
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-spinner fa-spin"></i> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Buyers Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-12">
            <div class="card chart-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-users text-info me-2"></i>Top 10 Buyers</h6>
                    <div class="d-flex align-items-center gap-2">
                         <select class="form-select form-select-sm" id="buyerMonth" style="width: auto;">
                            <!-- Populated by JS -->
                        </select>
                        <select class="form-select form-select-sm" id="buyerYear" style="width: auto;">
                            <!-- Populated by JS -->
                        </select>
                        <button class="btn btn-sm btn-outline-secondary" id="refreshBuyersBtn">
                            <i class="fa-solid fa-filter"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container" id="topBuyersContainer" style="position: relative; height: 350px;">
                        <canvas id="topBuyersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Row -->
    <div class="row g-3">
        <div class="col-md-4">
            <a href="<?= BASE_URL ?>views/pos/simple_checkout.php" class="card dashboard-card bg-white h-100 text-decoration-none">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">New Sale</h5>
                            <p class="mb-0 text-muted small">Create new transaction</p>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                            <i class="fa-solid fa-cart-shopping fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <?php if ($_SESSION['role'] !== 'cashier'): ?>
            <div class="col-md-4">
                <a href="<?= BASE_URL ?>views/inventory/index.php" class="card dashboard-card bg-white h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="fw-bold text-dark mb-1">Inventory</h5>
                                <p class="mb-0 text-muted small">View & manage stocks</p>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                <i class="fa-solid fa-boxes-stacked fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="col-md-4">
                <a href="<?= BASE_URL ?>views/pos/receipts.php" class="card dashboard-card bg-white h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="fw-bold text-dark mb-1">Sales History</h5>
                                <p class="mb-0 text-muted small">View transactions</p>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                <i class="fa-solid fa-receipt fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="<?= BASE_URL ?>assets/js/package/dist/chart.umd.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chart instances
        let salesTrendChart, paymentChart, topProductsChart, topBuyersChart;
        // Data Store
        let dashboardData = null;

        // Theme-aware color palette
        function getThemeColors() {
            const isLight = document.documentElement.getAttribute('data-theme') === 'light';
            return {
                primary: 'rgb(59, 130, 246)',
                primaryLight: isLight ? 'rgba(59, 130, 246, 0.1)' : 'rgba(59, 130, 246, 0.15)',
                success: 'rgb(34, 197, 94)',
                warning: 'rgb(234, 179, 8)',
                danger: 'rgb(239, 68, 68)',
                purple: 'rgb(139, 92, 246)',
                info: 'rgb(6, 182, 212)',
                gray: 'rgb(107, 114, 128)',
                gridColor: isLight ? 'rgba(0, 0, 0, 0.1)' : 'rgba(255, 255, 255, 0.1)',
                textColor: isLight ? '#1e293b' : '#f8fafc',
                tooltipBg: isLight ? 'rgba(30, 41, 59, 0.9)' : 'rgba(0, 0, 0, 0.8)'
            };
        }

        // Get initial colors
        const colors = getThemeColors();

        // Initialize Date Filters
        const today = new Date();
        const currentMonth = today.getMonth() + 1;
        const currentYear = today.getFullYear();
        
        // Populate filters FIRST, then fetch data
        populateDateFilters(currentMonth, currentYear);

        // Now fetch dashboard data with selected month/year
        fetchDashboardData();

        async function fetchDashboardData() {
            // Get values from dropdowns (which are now populated)
            const month = document.getElementById('buyerMonth').value;
            const year = document.getElementById('buyerYear').value;

            try {
                const response = await fetch(`<?= BASE_URL ?>api/pos/dashboard_stats.php?month=${month}&year=${year}`);
                const data = await response.json();

                if (data.success) {
                    dashboardData = data; // Store data globally
                    updateStats(data);
                    renderSalesTrendChart(data.weekly_trend);
                    renderPaymentChart(data.payment_methods);
                    renderTopProductsChart('qty'); // Default to Qty
                    renderTopBuyersChart(data.top_buyers);
                    renderLowStockTable(data.low_stock.items);
                }
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
            }
        }

        // Separate function to ONLY refresh top buyers (used by filter button)
        async function refreshTopBuyers() {
            const month = document.getElementById('buyerMonth').value;
            const year = document.getElementById('buyerYear').value;

            try {
                const response = await fetch(`<?= BASE_URL ?>api/pos/dashboard_stats.php?month=${month}&year=${year}`);
                const data = await response.json();

                if (data.success) {
                    // Only update top buyers chart
                    renderTopBuyersChart(data.top_buyers);
                }
            } catch (error) {
                console.error('Error fetching top buyers:', error);
            }
        }

        function updateStats(data) {
            document.getElementById('todaySales').textContent = '₱' + formatNumber(data.today.sales);
            if (data.today.net_profit !== undefined && data.today.net_profit !== null) {
                document.getElementById('todayCount').innerHTML = 
                    `${data.today.transactions} transactions<br>
                    <span class="text-success fw-bold d-inline-block mt-1">Net: ₱${formatNumber(data.today.net_profit)}</span> 
                    <span class="text-danger ms-1" style="font-size:0.7rem;">(Exp: ₱${formatNumber(data.today.expenses)})</span>`;
            } else {
                document.getElementById('todayCount').textContent = data.today.transactions + ' transactions';
            }
            
            document.getElementById('totalProducts').textContent = formatNumber(data.total_products);
            document.getElementById('lowStockCount').textContent = formatNumber(data.low_stock.count);
            document.getElementById('receivablesTotal').textContent = '₱' + formatNumber(data.receivables.total);
            document.getElementById('receivablesCount').textContent = data.receivables.count + ' pending';
        }

        function renderSalesTrendChart(weeklyTrend) {
            const ctx = document.getElementById('salesTrendChart').getContext('2d');

            salesTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: weeklyTrend.map(d => d.day),
                    datasets: [{
                        label: 'Sales (₱)',
                        data: weeklyTrend.map(d => d.total),
                        borderColor: colors.primary,
                        backgroundColor: colors.primaryLight,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: colors.primary,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            titleFont: {
                                size: 13
                            },
                            bodyFont: {
                                size: 12
                            },
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return '₱' + formatNumber(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: colors.gridColor
                            },
                            ticks: {
                                color: colors.textColor,
                                callback: function(value) {
                                    return '₱' + formatNumber(value);
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: colors.textColor
                            }
                        }
                    }
                }
            });
        }

        function renderPaymentChart(paymentMethods) {
            const ctx = document.getElementById('paymentChart').getContext('2d');

            const labels = paymentMethods.map(p => formatPaymentLabel(p.payment_type));
            const data = paymentMethods.map(p => parseFloat(p.total_amount));
            const bgColors = [colors.success, colors.purple, colors.warning, colors.danger, colors.gray];

            paymentChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: bgColors.slice(0, labels.length),
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                color: colors.textColor
                            }
                        },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ₱' + formatNumber(context.raw);
                                }
                            }
                        }
                    }
                }
            });
        }

        function renderTopProductsChart(mode) {
            const ctx = document.getElementById('topProductsChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (topProductsChart) {
                topProductsChart.destroy();
            }

            const isQty = mode === 'qty';
            const sourceData = isQty ? dashboardData.top_products_qty : dashboardData.top_products_revenue;

            // Prepare labels
            const fullLabels = sourceData.map(p => p.product_name);
            const displayLabels = sourceData.map(p => {
                const fullName = p.product_name;
                
                // Check if it has a variation part "(Variation)"
                const match = fullName.match(/^(.*) \((.*)\)$/);
                
                if (match) {
                    const prodName = match[1];
                    const varName = match[2];
                    // Truncate product name if too long (e.g., > 15 chars)
                    const truncProd = prodName.length > 20 ? prodName.substring(0, 18) + '...' : prodName;
                    return `${truncProd} (${varName})`;
                } else {
                    // No variation, just truncate usually
                    return fullName.length > 25 ? fullName.substring(0, 23) + '...' : fullName;
                }
            });

            const data = sourceData.map(p => isQty ? parseInt(p.total_qty) : parseFloat(p.total_revenue));

            topProductsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: displayLabels,
                    datasets: [{
                        label: isQty ? 'Units Sold' : 'Total Revenue (₱)',
                        data: data,
                        backgroundColor: [
                            colors.primary,
                            colors.success,
                            colors.warning,
                            colors.purple,
                            colors.danger
                        ],
                        borderRadius: 6,
                        barThickness: 24
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            titleFont: {
                                size: 13
                            },
                            bodyFont: {
                                size: 12
                            },
                            padding: 12,
                            callbacks: {
                                title: function(context) {
                                    // Use full label for tooltip title
                                    return fullLabels[context[0].dataIndex];
                                },
                                label: function(context) {
                                    const value = isQty ? context.raw : '₱' + formatNumber(context.raw);
                                    return `${context.dataset.label}: ${value}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: colors.gridColor
                            },
                            ticks: {
                                color: colors.textColor,
                                callback: function(value) {
                                    return isQty ? value : '₱' + formatNumber(value);
                                }
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: colors.textColor
                            }
                        }
                    }
                }
            });
        }
        
        // Event Listeners for Toggles
        document.getElementById('topModeQty').addEventListener('change', () => {
             if (dashboardData) renderTopProductsChart('qty');
        });

        document.getElementById('topModeRev').addEventListener('change', () => {
             if (dashboardData) renderTopProductsChart('revenue');
        });

        function renderLowStockTable(items) {
            const tbody = document.getElementById('lowStockTable');

            if (items.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center py-4 text-success">
                        <i class="fa-solid fa-check-circle me-2"></i>All items are well stocked!
                    </td>
                </tr>
            `;
                return;
            }

            tbody.innerHTML = items.map(item => `
            <tr>
                <td class="ps-3">
                    <div class="fw-medium">${escapeHtml(item.product_name)}</div>
                    <small class="text-muted">${escapeHtml(item.brand_name || '')}${item.variation_name ? ' • ' + escapeHtml(item.variation_name) : ''}</small>
                </td>
                <td class="text-center">
                    <span class="stock-badge ${item.current_stock == 0 ? 'out' : 'low'}">
                        ${item.current_stock == 0 ? '<i class="fa-solid fa-circle-xmark"></i> Out' : '<i class="fa-solid fa-triangle-exclamation"></i> ' + item.current_stock}
                    </span>
                </td>
                <td class="text-center text-muted">${item.low_stock_threshold}</td>
            </tr>
        `).join('');
        }

        function renderTopBuyersChart(buyers) {
            const container = document.getElementById('topBuyersContainer');
            
            if (topBuyersChart) {
                topBuyersChart.destroy();
                topBuyersChart = null;
            }

            if (!buyers || buyers.length === 0) {
                container.innerHTML = `
                    <div class="d-flex flex-column justify-content-center align-items-center h-100 text-muted">
                        <i class="fa-solid fa-folder-open fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">No data found for this period</p>
                    </div>`;
                return;
            }

            // Re-create canvas if missing
            if (!document.getElementById('topBuyersChart')) {
                container.innerHTML = '<canvas id="topBuyersChart"></canvas>';
            }

            const ctx = document.getElementById('topBuyersChart').getContext('2d');
            const labels = buyers.map(b => b.buyer_name);
            const data = buyers.map(b => parseFloat(b.total_spent));

            topBuyersChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Spent (₱)',
                        data: data,
                        backgroundColor: colors.info,
                        borderRadius: 6,
                        barThickness: 24
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            callbacks: {
                                label: function(context) {
                                    return 'Total Spent: ₱' + formatNumber(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: colors.gridColor
                            },
                            ticks: {
                                color: colors.textColor,
                                callback: function(value) {
                                    return '₱' + formatNumber(value);
                                }
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: colors.textColor
                            }
                        }
                    }
                }
            });
        }

        function populateDateFilters(selectedMonth, selectedYear) {
            const monthSelect = document.getElementById('buyerMonth');
            const yearSelect = document.getElementById('buyerYear');
            
            // Months
            const months = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            monthSelect.innerHTML = months.map((m, i) => 
                `<option value="${i + 1}" ${i + 1 === selectedMonth ? 'selected' : ''}>${m}</option>`
            ).join('');

            // Years (Current - 5)
            const currentY = new Date().getFullYear();
            let yearsHtml = '';
            for (let i = 0; i < 5; i++) {
                const y = currentY - i;
                yearsHtml += `<option value="${y}" ${y === selectedYear ? 'selected' : ''}>${y}</option>`;
            }
            yearSelect.innerHTML = yearsHtml;
        }

        // Filter Event Listener - Only refresh Top Buyers chart
        document.getElementById('refreshBuyersBtn').addEventListener('click', () => {
            refreshTopBuyers();
        });

        function formatNumber(num) {
            return Number(num).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatPaymentLabel(type) {
            const labels = {
                'cash': 'Cash',
                'gcash': 'GCash',
                'pay_later': 'Pay Later',
                'card': 'Card',
                'bank': 'Bank Transfer'
            };
            return labels[type] || type;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>