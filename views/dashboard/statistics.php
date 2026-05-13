<?php
// views/dashboard/statistics.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Secure the page — admin/manager only
requireLogin();

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/statistics.css">

<div class="container-fluid p-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark mb-0">
            <i class="fa-solid fa-chart-pie text-primary me-2"></i>Statistics
        </h4>
        <span class="text-muted small">
            <?= date('l, F j, Y') ?>
        </span>
    </div>

    <!-- Date Range Toolbar -->
    <div class="stats-toolbar mb-4">
        <button class="preset-btn" data-preset="today">Today</button>
        <button class="preset-btn" data-preset="yesterday">Yesterday</button>
        <button class="preset-btn" data-preset="7d">Last 7 Days</button>
        <button class="preset-btn active" data-preset="30d">Last 30 Days</button>
        <button class="preset-btn" data-preset="this_month">This Month</button>
        <button class="preset-btn" data-preset="last_month">Last Month</button>
        <button class="preset-btn" data-preset="this_year">This Year</button>
        <div class="divider"></div>
        <input type="date" class="date-input" id="startDate">
        <span class="text-muted small">to</span>
        <input type="date" class="date-input" id="endDate">
        <button class="preset-btn" id="customRangeBtn"><i class="fa-solid fa-filter me-1"></i>Apply</button>
    </div>

    <!-- Summary Cards (6 cards) -->
    <div class="row g-3 mb-4" id="summaryCards">
        <div class="col-sm-6 col-xl-2">
            <div class="summary-card sc-revenue">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="sc-label">Total Revenue</p>
                        <h3 class="sc-value" id="scRevenue">—</h3>
                        <span class="sc-change neutral" id="scRevenueChange">—</span>
                    </div>
                    <div class="sc-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-peso-sign"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="summary-card sc-transactions">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="sc-label">Transactions</p>
                        <h3 class="sc-value" id="scTransactions">—</h3>
                        <span class="sc-change neutral" id="scTransactionsChange">—</span>
                    </div>
                    <div class="sc-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="summary-card sc-avgorder">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="sc-label">Avg Order</p>
                        <h3 class="sc-value" id="scAvgOrder">—</h3>
                        <span class="sc-change neutral" id="scAvgOrderChange">—</span>
                    </div>
                    <div class="sc-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="summary-card sc-profit">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="sc-label">Net Profit</p>
                        <h3 class="sc-value" id="scNetProfit">—</h3>
                        <span class="sc-change neutral" id="scNetProfitChange">—</span>
                    </div>
                    <div class="sc-icon bg-purple bg-opacity-10"
                        style="color: #8b5cf6; background: rgba(139,92,246,0.1) !important;">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="summary-card sc-expenses">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="sc-label">Total Expenses</p>
                        <h3 class="sc-value" id="scExpenses">—</h3>
                        <span class="sc-change neutral" id="scExpensesChange">—</span>
                    </div>
                    <div class="sc-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="summary-card sc-customers">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="sc-label">Customers</p>
                        <h3 class="sc-value" id="scCustomers">—</h3>
                        <span class="sc-change neutral" id="scCustomersChange">—</span>
                    </div>
                    <div class="sc-icon bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="summary-card sc-items">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="sc-label">Items Sold</p>
                        <h3 class="sc-value" id="scItemsSold">—</h3>
                        <span class="sc-change neutral" id="scItemsChange">—</span>
                    </div>
                    <div class="sc-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row: Sales Trend + Profit Trend -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card stats-chart-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-chart-area text-primary me-2"></i>Sales Trend</h6>
                    <div class="period-toggle">
                        <button class="pt-btn active" data-period="day">Daily</button>
                        <button class="pt-btn" data-period="week">Weekly</button>
                        <button class="pt-btn" data-period="month">Monthly</button>
                    </div>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 320px;">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card stats-chart-card h-100">
                <div class="card-header">
                    <h6><i class="fa-solid fa-tags text-success me-2"></i>Sales by Category</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div style="position: relative; max-width: 280px; width: 100%; height: 280px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row: Profit Trend + Payment Methods -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card stats-chart-card h-100">
                <div class="card-header">
                    <h6><i class="fa-solid fa-arrow-trend-up me-2" style="color: #8b5cf6;"></i>Revenue vs Profit</h6>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 320px;">
                        <canvas id="profitTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card stats-chart-card h-100">
                <div class="card-header">
                    <h6><i class="fa-solid fa-credit-card text-warning me-2"></i>Payment Methods</h6>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 320px;">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row: Top Products + Top Buyers -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card stats-chart-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-trophy text-warning me-2"></i>Top 10 Products</h6>
                    <div class="btn-group btn-group-sm">
                        <input type="radio" class="btn-check" name="topProdMode" id="tpQty" checked>
                        <label class="btn btn-outline-primary btn-sm" for="tpQty">By Qty</label>
                        <input type="radio" class="btn-check" name="topProdMode" id="tpRev">
                        <label class="btn btn-outline-primary btn-sm" for="tpRev">By Revenue</label>
                    </div>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 400px;">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card stats-chart-card h-100">
                <div class="card-header">
                    <h6><i class="fa-solid fa-users text-info me-2"></i>Top 10 Buyers</h6>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 400px;">
                        <canvas id="topBuyersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row: Cashier Performance + Inventory Value -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card stats-chart-card h-100">
                <div class="card-header">
                    <h6><i class="fa-solid fa-user-tie text-primary me-2"></i>Cashier Performance</h6>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 320px;">
                        <canvas id="cashierChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="inv-value-card h-100">
                <h6 class="fw-bold mb-3">
                    <i class="fa-solid fa-warehouse text-success me-2"></i>Inventory Value
                </h6>
                <div class="row g-0">
                    <div class="col-12">
                        <div class="inv-metric">
                            <p class="inv-label">Retail Value</p>
                            <p class="inv-val text-primary" id="invRetail">—</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="inv-metric" style="border-top: 1px solid var(--border-color);">
                            <p class="inv-label">Cost Value</p>
                            <p class="inv-val" style="font-size: 1.1rem;" id="invCost">—</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="inv-metric" style="border-top: 1px solid var(--border-color);">
                            <p class="inv-label">Total Units</p>
                            <p class="inv-val" style="font-size: 1.1rem;" id="invUnits">—</p>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="inv-metric" style="border-top: 1px solid var(--border-color);">
                            <p class="inv-label">Potential Profit</p>
                            <p class="inv-val text-success" style="font-size: 1.1rem;" id="invProfit">—</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row: Heatmap -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card stats-chart-card h-100">
                <div class="card-header">
                    <h6><i class="fa-solid fa-fire text-danger me-2"></i>Sales Heatmap — Day of Week × Hour</h6>
                </div>
                <div class="card-body">
                    <div class="heatmap-grid" id="heatmapGrid">
                        <!-- Generated by JS -->
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card stats-chart-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fa-solid fa-file-invoice-dollar text-danger me-2"></i>Recent Expenses</h6>
                    <a href="<?= BASE_URL ?>views/expenses/" class="btn btn-sm btn-link text-decoration-none">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Date</th>
                                    <th>Category</th>
                                    <th class="text-end pe-3">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="recentExpensesTable">
                                <!-- Generated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="<?= BASE_URL ?>assets/js/package/dist/chart.umd.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // -------------------------------------------------------
        // Theme Colors
        // -------------------------------------------------------
        function getColors() {
            const isLight = document.documentElement.getAttribute('data-theme') === 'light';
            return {
                primary: 'rgb(59, 130, 246)', primaryBg: 'rgba(59, 130, 246, 0.12)',
                success: 'rgb(34, 197, 94)', successBg: 'rgba(34, 197, 94, 0.12)',
                warning: 'rgb(234, 179, 8)', warningBg: 'rgba(234, 179, 8, 0.12)',
                danger: 'rgb(239, 68, 68)', dangerBg: 'rgba(239, 68, 68, 0.12)',
                purple: 'rgb(139, 92, 246)', purpleBg: 'rgba(139, 92, 246, 0.12)',
                info: 'rgb(6, 182, 212)', infoBg: 'rgba(6, 182, 212, 0.12)',
                gray: 'rgb(107, 114, 128)',
                gridColor: isLight ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.08)',
                textColor: isLight ? '#374151' : '#e5e7eb',
                tooltipBg: isLight ? 'rgba(30,41,59,0.92)' : 'rgba(0,0,0,0.85)',
                chartColors: [
                    'rgb(59,130,246)', 'rgb(34,197,94)', 'rgb(234,179,8)',
                    'rgb(239,68,68)', 'rgb(139,92,246)', 'rgb(6,182,212)',
                    'rgb(249,115,22)', 'rgb(236,72,153)', 'rgb(20,184,166)', 'rgb(168,85,247)'
                ]
            };
        }
        let C = getColors();

        // -------------------------------------------------------
        // State
        // -------------------------------------------------------
        let currentPeriod = 'day';
        let currentStart = '';
        let currentEnd = '';
        let statsData = null;

        // Chart instances
        let salesTrendChart, profitTrendChart, categoryChart, paymentChart;
        let topProductsChart, topBuyersChart, cashierChart;

        // -------------------------------------------------------
        // Date Presets
        // -------------------------------------------------------
        function getPresetDates(preset) {
            const today = new Date();
            const fmt = d => d.toISOString().slice(0, 10);
            switch (preset) {
                case 'today':
                    return { start: fmt(today), end: fmt(today) };
                case 'yesterday': {
                    const y = new Date(today); y.setDate(y.getDate() - 1);
                    return { start: fmt(y), end: fmt(y) };
                }
                case '7d': {
                    const s = new Date(today); s.setDate(s.getDate() - 6);
                    return { start: fmt(s), end: fmt(today) };
                }
                case '30d': {
                    const s = new Date(today); s.setDate(s.getDate() - 29);
                    return { start: fmt(s), end: fmt(today) };
                }
                case 'this_month':
                    return { start: today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-01', end: fmt(today) };
                case 'last_month': {
                    const first = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    const last = new Date(today.getFullYear(), today.getMonth(), 0);
                    return { start: fmt(first), end: fmt(last) };
                }
                case 'this_year':
                    return { start: today.getFullYear() + '-01-01', end: fmt(today) };
                default:
                    return { start: fmt(today), end: fmt(today) };
            }
        }

        // Set initial preset
        const initial = getPresetDates('30d');
        currentStart = initial.start;
        currentEnd = initial.end;
        document.getElementById('startDate').value = currentStart;
        document.getElementById('endDate').value = currentEnd;

        // Preset buttons
        document.querySelectorAll('.preset-btn[data-preset]').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.preset-btn[data-preset]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const dates = getPresetDates(this.dataset.preset);
                currentStart = dates.start;
                currentEnd = dates.end;
                document.getElementById('startDate').value = currentStart;
                document.getElementById('endDate').value = currentEnd;
                fetchStats();
            });
        });

        // Custom range
        document.getElementById('customRangeBtn').addEventListener('click', () => {
            document.querySelectorAll('.preset-btn[data-preset]').forEach(b => b.classList.remove('active'));
            currentStart = document.getElementById('startDate').value;
            currentEnd = document.getElementById('endDate').value;
            if (currentStart && currentEnd) fetchStats();
        });

        // Period toggle
        document.querySelectorAll('.period-toggle .pt-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.period-toggle .pt-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentPeriod = this.dataset.period;
                fetchStats();
            });
        });

        // -------------------------------------------------------
        // Fetch Statistics
        // -------------------------------------------------------
        async function fetchStats() {
            try {
                const url = `<?= BASE_URL ?>api/pos/statistics.php?start_date=${currentStart}&end_date=${currentEnd}&period=${currentPeriod}`;
                const resp = await fetch(url);
                const data = await resp.json();
                if (data.success) {
                    statsData = data;
                    renderSummaryCards(data.summary);
                    renderSalesTrend(data.sales_over_time);
                    renderProfitTrend(data.profit_over_time);
                    renderCategoryChart(data.categories);
                    renderPaymentChart(data.payment_methods);
                    renderTopProducts('qty');
                    renderTopBuyers(data.top_buyers);
                    renderCashierChart(data.cashier_performance);
                    renderHeatmap(data.heatmap);
                    renderInventoryValue(data.inventory_value);
                    renderExpensesTable(data.recent_expenses);
                }
            } catch (err) {
                console.error('Failed to fetch statistics:', err);
            }
        }

        // -------------------------------------------------------
        // Helpers
        // -------------------------------------------------------
        function fmt(num) {
            return Number(num).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function fmtInt(num) {
            return Number(num).toLocaleString('en-PH');
        }

        function changeHtml(pct) {
            if (pct > 0) return `<i class="fa-solid fa-arrow-up"></i> ${pct}%`;
            if (pct < 0) return `<i class="fa-solid fa-arrow-down"></i> ${Math.abs(pct)}%`;
            return `<i class="fa-solid fa-minus"></i> 0%`;
        }

        function changeClass(pct) {
            if (pct > 0) return 'up';
            if (pct < 0) return 'down';
            return 'neutral';
        }

        function truncLabel(name, max) {
            return name.length > max ? name.substring(0, max - 2) + '…' : name;
        }

        function paymentLabel(type) {
            const map = { 'cash': 'Cash', 'gcash': 'GCash', 'bank_transfer': 'Bank Transfer', 'pay_later': 'Pay Later', 'card': 'Card', 'check': 'Check', 'home_credit': 'Home Credit', 'financing': 'Financing', 'terms': 'Terms', 'mix': 'Mix' };
            return map[type] || type;
        }

        // Common chart options
        function baseOpts(showLegend = false) {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: showLegend, labels: { color: C.textColor, usePointStyle: true, pointStyle: 'circle', padding: 12 } },
                    tooltip: { backgroundColor: C.tooltipBg, titleFont: { size: 13 }, bodyFont: { size: 12 }, padding: 12 }
                }
            };
        }

        // -------------------------------------------------------
        // Render Summary Cards
        // -------------------------------------------------------
        function renderSummaryCards(s) {
            document.getElementById('scRevenue').textContent = '₱' + fmt(s.total_revenue);
            document.getElementById('scTransactions').textContent = fmtInt(s.total_transactions);
            document.getElementById('scAvgOrder').textContent = '₱' + fmt(s.avg_order_value);
            document.getElementById('scNetProfit').textContent = '₱' + fmt(s.net_profit || 0);
            document.getElementById('scExpenses').textContent = '₱' + fmt(s.total_expenses || 0);
            document.getElementById('scCustomers').textContent = fmtInt(s.unique_customers);
            document.getElementById('scItemsSold').textContent = fmtInt(s.items_sold);

            const ch = s.changes;
            setChange('scRevenueChange', ch.revenue);
            setChange('scTransactionsChange', ch.transactions);
            setChange('scAvgOrderChange', ch.avg_order);
            setChange('scNetProfitChange', ch.net_profit);
            setChange('scExpensesChange', ch.expenses);
            setChange('scCustomersChange', ch.customers);
            setChange('scItemsChange', ch.items);
        }

        function setChange(id, pct) {
            const el = document.getElementById(id);
            el.className = 'sc-change ' + changeClass(pct);
            el.innerHTML = changeHtml(pct);
        }

        // -------------------------------------------------------
        // Sales Trend Chart
        // -------------------------------------------------------
        function renderSalesTrend(data) {
            const ctx = document.getElementById('salesTrendChart').getContext('2d');
            if (salesTrendChart) salesTrendChart.destroy();

            salesTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: data.map(d => parseFloat(d.revenue)),
                        borderColor: C.primary,
                        backgroundColor: C.primaryBg,
                        fill: true, tension: 0.4, pointRadius: 3, pointHoverRadius: 6,
                        pointBackgroundColor: C.primary, pointBorderColor: '#fff', pointBorderWidth: 2
                    }]
                },
                options: {
                    ...baseOpts(),
                    scales: {
                        y: { beginAtZero: true, grid: { color: C.gridColor }, ticks: { color: C.textColor, callback: v => '₱' + fmt(v) } },
                        x: { grid: { display: false }, ticks: { color: C.textColor, maxRotation: 45 } }
                    },
                    plugins: {
                        ...baseOpts().plugins,
                        tooltip: { ...baseOpts().plugins.tooltip, callbacks: { label: ctx => '₱' + fmt(ctx.raw) } }
                    }
                }
            });
        }

        // -------------------------------------------------------
        // Profit Trend (Revenue + Profit layered)
        // -------------------------------------------------------
        function renderProfitTrend(data) {
            const ctx = document.getElementById('profitTrendChart').getContext('2d');
            if (profitTrendChart) profitTrendChart.destroy();

            profitTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [
                        {
                            label: 'Revenue (₱)',
                            data: data.map(d => parseFloat(d.revenue)),
                            borderColor: C.primary, backgroundColor: C.primaryBg,
                            fill: true, tension: 0.4, pointRadius: 3, borderWidth: 2
                        },
                        {
                            label: 'Profit (₱)',
                            data: data.map(d => parseFloat(d.profit)),
                            borderColor: C.purple, backgroundColor: C.purpleBg,
                            fill: true, tension: 0.4, pointRadius: 3, borderWidth: 2
                        }
                    ]
                },
                options: {
                    ...baseOpts(true),
                    scales: {
                        y: { beginAtZero: true, grid: { color: C.gridColor }, ticks: { color: C.textColor, callback: v => '₱' + fmt(v) } },
                        x: { grid: { display: false }, ticks: { color: C.textColor, maxRotation: 45 } }
                    },
                    plugins: {
                        ...baseOpts(true).plugins,
                        tooltip: { ...baseOpts(true).plugins.tooltip, callbacks: { label: ctx => ctx.dataset.label + ': ₱' + fmt(ctx.raw) } }
                    }
                }
            });
        }

        // -------------------------------------------------------
        // Category Doughnut
        // -------------------------------------------------------
        function renderCategoryChart(data) {
            const ctx = document.getElementById('categoryChart').getContext('2d');
            if (categoryChart) categoryChart.destroy();

            const colors = data.map(d => d.color || '#6b7280');

            categoryChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.category_name),
                    datasets: [{
                        data: data.map(d => parseFloat(d.revenue)),
                        backgroundColor: colors,
                        borderWidth: 0, hoverOffset: 6
                    }]
                },
                options: {
                    ...baseOpts(true),
                    cutout: '62%',
                    plugins: {
                        ...baseOpts(true).plugins,
                        legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, pointStyle: 'circle', color: C.textColor, font: { size: 11 } } },
                        tooltip: { ...baseOpts(true).plugins.tooltip, callbacks: { label: ctx => ctx.label + ': ₱' + fmt(ctx.raw) } }
                    }
                }
            });
        }

        // -------------------------------------------------------
        // Payment Methods Bar Chart
        // -------------------------------------------------------
        function renderPaymentChart(data) {
            const ctx = document.getElementById('paymentChart').getContext('2d');
            if (paymentChart) paymentChart.destroy();

            paymentChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => paymentLabel(d.payment_type)),
                    datasets: [{
                        label: 'Amount (₱)',
                        data: data.map(d => parseFloat(d.total_amount)),
                        backgroundColor: C.chartColors.slice(0, data.length),
                        borderRadius: 6, barThickness: 22
                    }]
                },
                options: {
                    ...baseOpts(),
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true, grid: { color: C.gridColor }, ticks: { color: C.textColor, callback: v => '₱' + fmt(v) } },
                        y: { grid: { display: false }, ticks: { color: C.textColor } }
                    },
                    plugins: {
                        ...baseOpts().plugins,
                        tooltip: { ...baseOpts().plugins.tooltip, callbacks: { label: ctx => '₱' + fmt(ctx.raw) + ' (' + (data[ctx.dataIndex]?.count || 0) + ' txns)' } }
                    }
                }
            });
        }

        // -------------------------------------------------------
        // Top Products
        // -------------------------------------------------------
        function renderTopProducts(mode) {
            const ctx = document.getElementById('topProductsChart').getContext('2d');
            if (topProductsChart) topProductsChart.destroy();

            const isQty = mode === 'qty';
            const src = isQty ? statsData.top_products_qty : statsData.top_products_revenue;
            if (!src || src.length === 0) return;

            const fullLabels = src.map(p => p.product_name);
            const labels = src.map(p => truncLabel(p.product_name, 28));
            const values = src.map(p => isQty ? parseInt(p.total_qty) : parseFloat(p.total_revenue));

            topProductsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: isQty ? 'Units Sold' : 'Revenue (₱)',
                        data: values,
                        backgroundColor: C.chartColors.slice(0, src.length),
                        borderRadius: 6, barThickness: 22
                    }]
                },
                options: {
                    ...baseOpts(),
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true, grid: { color: C.gridColor }, ticks: { color: C.textColor, callback: v => isQty ? v : '₱' + fmt(v) } },
                        y: { grid: { display: false }, ticks: { color: C.textColor } }
                    },
                    plugins: {
                        ...baseOpts().plugins,
                        tooltip: {
                            ...baseOpts().plugins.tooltip,
                            callbacks: {
                                title: ctx => fullLabels[ctx[0].dataIndex],
                                label: ctx => isQty ? ctx.raw + ' units' : '₱' + fmt(ctx.raw)
                            }
                        }
                    }
                }
            });
        }

        // Toggle events
        document.getElementById('tpQty').addEventListener('change', () => { if (statsData) renderTopProducts('qty'); });
        document.getElementById('tpRev').addEventListener('change', () => { if (statsData) renderTopProducts('revenue'); });

        // -------------------------------------------------------
        // Top Buyers
        // -------------------------------------------------------
        function renderTopBuyers(data) {
            const ctx = document.getElementById('topBuyersChart').getContext('2d');
            if (topBuyersChart) topBuyersChart.destroy();

            if (!data || data.length === 0) {
                topBuyersChart = null;
                return;
            }

            topBuyersChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => truncLabel(d.buyer_name, 25)),
                    datasets: [{
                        label: 'Total Spent (₱)',
                        data: data.map(d => parseFloat(d.total_spent)),
                        backgroundColor: C.info, borderRadius: 6, barThickness: 22
                    }]
                },
                options: {
                    ...baseOpts(),
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true, grid: { color: C.gridColor }, ticks: { color: C.textColor, callback: v => '₱' + fmt(v) } },
                        y: { grid: { display: false }, ticks: { color: C.textColor } }
                    },
                    plugins: {
                        ...baseOpts().plugins,
                        tooltip: {
                            ...baseOpts().plugins.tooltip,
                            callbacks: {
                                title: ctx => data[ctx[0].dataIndex].buyer_name,
                                label: ctx => '₱' + fmt(ctx.raw) + ' (' + data[ctx.dataIndex].transaction_count + ' txns)'
                            }
                        }
                    }
                }
            });
        }

        // -------------------------------------------------------
        // Cashier Performance
        // -------------------------------------------------------
        function renderCashierChart(data) {
            const ctx = document.getElementById('cashierChart').getContext('2d');
            if (cashierChart) cashierChart.destroy();

            if (!data || data.length === 0) return;

            cashierChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.cashier_name),
                    datasets: [
                        {
                            label: 'Sales (₱)',
                            data: data.map(d => parseFloat(d.total_sales)),
                            backgroundColor: C.primary, borderRadius: 6, barThickness: 28
                        }
                    ]
                },
                options: {
                    ...baseOpts(),
                    scales: {
                        y: { beginAtZero: true, grid: { color: C.gridColor }, ticks: { color: C.textColor, callback: v => '₱' + fmt(v) } },
                        x: { grid: { display: false }, ticks: { color: C.textColor } }
                    },
                    plugins: {
                        ...baseOpts().plugins,
                        tooltip: {
                            ...baseOpts().plugins.tooltip,
                            callbacks: {
                                label: ctx => '₱' + fmt(ctx.raw) + ' (' + data[ctx.dataIndex].transaction_count + ' txns)'
                            }
                        }
                    }
                }
            });
        }

        // -------------------------------------------------------
        // Heatmap
        // -------------------------------------------------------
        function renderHeatmap(data) {
            const grid = document.getElementById('heatmapGrid');
            grid.innerHTML = '';

            // Find max for color intensity
            let maxVal = 0;
            data.forEach(row => row.hours.forEach(v => { if (v > maxVal) maxVal = v; }));
            if (maxVal === 0) maxVal = 1;

            // Header row: empty corner + hour labels
            grid.appendChild(createEl('div', 'hm-label', ''));
            for (let h = 0; h < 24; h++) {
                const lbl = h === 0 ? '12a' : h < 12 ? h + 'a' : h === 12 ? '12p' : (h - 12) + 'p';
                grid.appendChild(createEl('div', 'hm-label hm-hour', lbl));
            }

            // Data rows
            data.forEach(row => {
                grid.appendChild(createEl('div', 'hm-label', row.day));
                row.hours.forEach((val, h) => {
                    const intensity = val / maxVal;
                    const cell = document.createElement('div');
                    cell.className = 'hm-cell';
                    cell.style.background = heatColor(intensity);
                    cell.title = `${row.day} ${h}:00 — ₱${fmt(val)}`;
                    grid.appendChild(cell);
                });
            });
        }

        function createEl(tag, cls, text) {
            const el = document.createElement(tag);
            el.className = cls;
            el.textContent = text;
            return el;
        }

        function heatColor(intensity) {
            if (intensity === 0) {
                const isLight = document.documentElement.getAttribute('data-theme') === 'light';
                return isLight ? 'rgba(0,0,0,0.04)' : 'rgba(255,255,255,0.04)';
            }
            // Green gradient: from light to saturated
            const r = Math.round(34 + (1 - intensity) * 200);
            const g = Math.round(197 - intensity * 50);
            const b = Math.round(94 - intensity * 60);
            const a = 0.2 + intensity * 0.8;
            return `rgba(${r}, ${g}, ${b}, ${a})`;
        }

        // -------------------------------------------------------
        // Recent Expenses Table
        // -------------------------------------------------------
        function renderExpensesTable(expenses) {
            const tbody = document.getElementById('recentExpensesTable');
            tbody.innerHTML = '';

            if (!expenses || expenses.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No expenses found</td></tr>';
                return;
            }

            expenses.forEach(e => {
                const tr = document.createElement('tr');
                const date = new Date(e.expense_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                tr.innerHTML = `
                    <td class="ps-3 text-muted">${date}</td>
                    <td>
                        <div class="fw-medium text-dark">${truncLabel(e.category, 15)}</div>
                        <div class="text-muted small">${truncLabel(e.description || '', 20)}</div>
                    </td>
                    <td class="text-end pe-3 fw-bold text-danger">₱${fmt(e.amount)}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // -------------------------------------------------------
        // Inventory Value
        // -------------------------------------------------------
        function renderInventoryValue(inv) {
            document.getElementById('invRetail').textContent = '₱' + fmt(inv.retail_value);
            document.getElementById('invCost').textContent = '₱' + fmt(inv.cost_value);
            document.getElementById('invUnits').textContent = fmtInt(inv.total_units);
            document.getElementById('invProfit').textContent = '₱' + fmt(inv.retail_value - inv.cost_value);
        }

        // -------------------------------------------------------
        // Initial Load
        // -------------------------------------------------------
        fetchStats();
    });
</script>

<?php require_once '../../includes/footer.php'; ?>