// assets/js/pos.js

/* =========================================
   1. GLOBAL STATE & DRAFT MANAGEMENT
   ========================================= */

// Initialize the Draft State (Shared with buyer-search.js)
if (typeof window.POS_STATE === 'undefined') {
    window.POS_STATE = {
        buyer: null, // Managed by buyer-search.js
        cart: [],    // Managed here
        payment: null // Managed by checkout logic later
    };
}

// Shortcut references for internal use
let currentSearchResults = [];
let searchTimeout = null;
let isScanMode = false;

// DOM ELEMENTS
const searchInput = document.getElementById('product-search');
const resultsContainer = document.getElementById('product-results');
const toggleSwitch = document.getElementById('scan-mode-toggle');
const modeLabel = document.getElementById('mode-label');
const searchIcon = document.getElementById('search-icon');

// CART DOM
const cartTableBody = document.getElementById('cart-table-body');
const subtotalEl = document.getElementById('cart-subtotal');
const taxEl = document.getElementById('cart-tax');
const totalEl = document.getElementById('cart-total');

/* =========================================
   2. MODE SWITCHING (Search vs Scanner)
   ========================================= */
if (toggleSwitch) {
    toggleSwitch.addEventListener('change', function () {
        isScanMode = this.checked;
        searchInput.value = '';
        searchInput.focus();
        resultsContainer.innerHTML = '';

        if (isScanMode) {
            modeLabel.innerText = "SCANNER MODE (AUTO-ADD)";
            modeLabel.className = "small fw-bold text-success text-uppercase";
            searchInput.placeholder = "Scan Barcode Now...";
            searchIcon.innerHTML = '<i class="fa-solid fa-barcode text-success"></i>';
            searchIcon.className = "input-group-text bg-success-subtle border-end-0";
            searchInput.className = "form-control border-start-0 bg-success-subtle";
        } else {
            modeLabel.innerText = "SEARCH MODE";
            modeLabel.className = "small fw-bold text-muted text-uppercase";
            searchInput.placeholder = "Type Product Name...";
            searchIcon.innerHTML = '<i class="fa-solid fa-magnifying-glass text-secondary"></i>';
            searchIcon.className = "input-group-text bg-white border-end-0";
            searchInput.className = "form-control border-start-0 bg-white";
        }
    });
}

/* =========================================
   3. INPUT HANDLING
   ========================================= */
if (searchInput) {
    searchInput.addEventListener('keyup', function (e) {
        const query = this.value.trim();
        clearTimeout(searchTimeout);

        // --- SCANNER MODE ---
        if (isScanMode) {
            if (e.key === 'Enter' && query.length > 0) {
                handleBarcodeScan(query);
                return;
            }
            // Auto-Enter logic (wait 300ms for scanner to finish)
            if (query.length > 0) {
                searchTimeout = setTimeout(() => {
                    handleBarcodeScan(query);
                }, 300);
            }
            return;
        }

        // --- SEARCH MODE ---
        if (query.length < 2) {
            resultsContainer.innerHTML = '<div class="text-center text-muted mt-5 opacity-50"><i class="fa-solid fa-keyboard fa-3x mb-3"></i><h5>Type to Search</h5></div>';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetchProducts(query);
        }, 300);
    });
}

/* =========================================
   4. API CALLS
   ========================================= */

function handleBarcodeScan(barcode) {
    searchInput.disabled = true;
    const apiUrl = '../../api/pos/search_product.php';

    fetch(`${apiUrl}?q=${encodeURIComponent(barcode)}`)
        .then(response => response.json())
        .then(data => {
            searchInput.disabled = false;
            searchInput.focus();

            const exactMatch = data.find(p => p.barcode === barcode || p.sku === barcode);

            if (exactMatch) {
                currentSearchResults = [exactMatch];
                addToCart(exactMatch.variation_id);
                searchInput.value = '';
            } else if (data.length === 1) {
                currentSearchResults = data;
                addToCart(data[0].variation_id);
                searchInput.value = '';
            } else {
                alert("❌ Product not found or multiple matches.");
                searchInput.select();
            }
        })
        .catch(error => {
            searchInput.disabled = false;
            console.error("Scan Error:", error);
        });
}

function fetchProducts(query) {
    resultsContainer.innerHTML = '<div class="text-center mt-5"><div class="spinner-border text-primary" role="status"></div></div>';
    const apiUrl = '../../api/pos/search_product.php';

    fetch(`${apiUrl}?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            currentSearchResults = data;
            renderProducts(data);
        })
        .catch(error => {
            resultsContainer.innerHTML = '<div class="alert alert-danger">Error loading products.</div>';
        });
}

function renderProducts(products) {
    resultsContainer.innerHTML = '';

    if (products.length === 0) {
        resultsContainer.innerHTML = '<div class="text-center text-muted mt-5"><h5>No products found</h5></div>';
        return;
    }

    const row = document.createElement('div');
    row.className = 'row g-2';

    products.forEach(p => {
        const col = document.createElement('div');
        col.className = 'col-md-4 col-sm-6';

        const imgHtml = p.image_path
            ? `<img src="../../${p.image_path}" style="width:100%; height:100%; object-fit:cover">`
            : '<i class="fa-solid fa-box text-secondary"></i>';

        col.innerHTML = `
            <div class="card product-card h-100 shadow-sm border-0" onclick="addToCart(${p.variation_id})">
                <div class="card-body p-2">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-light rounded d-flex align-items-center justify-content-center me-2 overflow-hidden" style="width: 40px; height: 40px;">
                             ${imgHtml}
                        </div>
                        <div style="overflow:hidden;">
                            <div class="fw-bold text-dark text-truncate">${p.product_name}</div>
                            <small class="text-muted text-truncate d-block" style="font-size: 0.8rem;">${p.variation_name}</small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-end mt-2">
                        <span class="badge bg-light text-secondary border">${p.current_stock} ${p.unit_type}</span>
                        <div class="fw-bold text-primary">₱${parseFloat(p.price_retail).toFixed(2)}</div>
                    </div>
                </div>
            </div>
        `;
        row.appendChild(col);
    });

    resultsContainer.appendChild(row);
}

/* =========================================
   5. DRAFT & CART LOGIC
   ========================================= */

function addToCart(id) {
    const cart = window.POS_STATE.cart;
    const existingItem = cart.find(item => item.variation_id == id);

    if (existingItem) {
        existingItem.qty++;
    } else {
        const product = currentSearchResults.find(p => p.variation_id == id);

        if (!product) { alert("Error: Product not found."); return; }
        if (product.current_stock <= 0) alert("Warning: Out of Stock!");

        // Store ALL prices so we can switch tiers later
        cart.push({
            variation_id: product.variation_id,
            product_name: product.product_name,
            variation_name: product.variation_name,

            // Current Active Price
            price: 0,       // Will be set by getPriceForBuyer() below
            price_tier: '', // Will be set by getPriceForBuyer() below

            // Stored Prices for switching
            rates: {
                retail: parseFloat(product.price_retail),
                wholesale: parseFloat(product.price_wholesale),
                dealer: parseFloat(product.price_dealer)
            },

            qty: 1,
            stock: product.current_stock
        });
    }

    // Apply the correct price based on the CURRENT buyer
    recalculateCart();
}

function recalculateCart() {
    const cart = window.POS_STATE.cart;
    const buyer = window.POS_STATE.buyer;

    // Determine target tier
    let targetTier = 'retail'; // Default
    if (buyer) {
        targetTier = buyer.price_tier; // 'wholesale' or 'dealer'
    }

    // Loop through every item and update price
    cart.forEach(item => {
        if (targetTier === 'wholesale') {
            item.price = item.rates.wholesale;
            item.price_tier = 'WHSL';
        } else if (targetTier === 'dealer') {
            item.price = item.rates.dealer;
            item.price_tier = 'DLR';
        } else {
            // Default to Retail/Walk-in
            item.price = item.rates.retail;
            item.price_tier = 'SRP';
        }
    });

    renderCart(); // Refresh the visual table
}
window.recalculateCart = recalculateCart;

function renderCart() {
    const cart = window.POS_STATE.cart; // Always read from global state
    cartTableBody.innerHTML = '';
    let subtotal = 0;

    cart.forEach((item, index) => {
        const itemTotal = item.price * item.qty;
        subtotal += itemTotal;

        // Badge Color for Tier
        let badgeClass = 'text-secondary border-secondary';
        if (item.price_tier === 'WHSL') badgeClass = 'text-info-emphasis border-info';
        if (item.price_tier === 'DLR') badgeClass = 'text-warning-emphasis border-warning';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="ps-3">
                <div class="fw-bold text-dark text-truncate" style="max-width: 170px;" title="${item.product_name}">
                    ${item.product_name}
                </div>
                <div class="small text-primary fw-bold" style="font-size: 0.85rem;">
                    ${item.variation_name}
                </div>
                <small class="text-muted d-flex align-items-center mt-1">
                    <span class="badge bg-light border p-0 px-1 me-1 ${badgeClass}" style="font-size: 0.7em;">${item.price_tier}</span> 
                    ₱${item.price.toFixed(2)}
                </small>
            </td>
            <td class="text-center align-middle">
                <div class="input-group input-group-sm justify-content-center" style="width: 80px; margin: 0 auto;">
                    <button class="btn btn-outline-secondary px-1" type="button" onclick="updateQty(${index}, ${item.qty - 1})">-</button>
                    <input type="text" class="form-control text-center px-1" value="${item.qty}" readonly style="background:white;">
                    <button class="btn btn-outline-secondary px-1" type="button" onclick="updateQty(${index}, ${item.qty + 1})">+</button>
                </div>
            </td>
            <td class="text-end pe-3 align-middle fw-bold">₱${itemTotal.toFixed(2)}</td>
            <td class="align-middle text-center">
                <button class="btn btn-link text-danger p-0" onclick="removeFromCart(${index})">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </td>
        `;
        cartTableBody.appendChild(row);
    });

    updateTotals(subtotal);
}

function updateQty(index, newQty) {
    if (newQty < 1) newQty = 1;
    window.POS_STATE.cart[index].qty = parseInt(newQty);
    renderCart();
}

function removeFromCart(index) {
    window.POS_STATE.cart.splice(index, 1);
    renderCart();
}

function clearCart() {
    if (confirm("Clear current cart?")) {
        window.POS_STATE.cart = [];
        renderCart();
    }
}

function updateTotals(subtotal) {
    const vat = (subtotal / 1.12) * 0.12;
    const grandTotal = subtotal;

    subtotalEl.innerText = '₱' + (grandTotal - vat).toFixed(2);
    taxEl.innerText = '₱' + vat.toFixed(2);
    totalEl.innerText = '₱' + grandTotal.toFixed(2);
}

/* =========================================
   6. INTEGRATIONS (Preview & Payment)
   ========================================= */

function showReceiptPreview() {
    const cart = window.POS_STATE.cart;

    if (cart.length === 0) {
        alert("Cart is empty!");
        return;
    }

    // Prepare data for ReceiptPreview
    const receiptData = {
        cart: cart,
        buyer: window.POS_STATE.buyer, // Read from shared state
        payment: window.POS_STATE.payment || { method: 'Pending' },
        user: window.ACTIVE_USER?.name || 'Admin'
    };

    // Call the external ReceiptPreview Logic
    const html = ReceiptPreview.generateHTML(receiptData);

    // Inject and Show
    document.getElementById('receipt-content').innerHTML = html;
    const modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
    modal.show();
}

function printFromPreview() {
    const receiptData = {
        cart: window.POS_STATE.cart,
        buyer: window.POS_STATE.buyer,
        payment: window.POS_STATE.payment || { method: 'Pending' },
        user: window.ACTIVE_USER?.name || 'Admin'
    };

    const html = ReceiptPreview.generateHTML(receiptData);
    ReceiptPreview.print(html);
}

function showPaymentModal() {
    if (window.POS_STATE.cart.length === 0) {
        alert("Cart is empty!");
        return;
    }
    const total = document.getElementById('cart-total').innerText;
    alert("Proceeding to payment for " + total + "\n(Payment Module coming next)");
}