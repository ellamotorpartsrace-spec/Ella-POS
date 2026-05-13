/* =====================================================
   PRODUCT SEARCH MODULE (REFINED)
   Handles Grid/Compact/List Display, Categories & Infinite Scroll
===================================================== */

const ProductSearch = {
  viewMode: 'grid', // 'grid', 'compact', 'list'
  activeCategory: 'all',
  currentOffset: 0,
  isLoading: false,
  hasMore: true,
  sentinel: null,
  observer: null,

  init() {
    const input = document.getElementById("product-search");
    if (!input) return;

    // Load initial categories
    this.loadCategories();

    // View Toggles
    document.getElementById('view-grid')?.addEventListener('click', () => this.setViewMode('grid'));
    document.getElementById('view-compact')?.addEventListener('click', () => this.setViewMode('compact'));
    document.getElementById('view-list')?.addEventListener('click', () => this.setViewMode('list'));

    // Infinite Scroll Sentinel
    this.initInfiniteScroll();

    let debounce = null;
    input.addEventListener("input", (e) => {
      clearTimeout(debounce);
      if (!ScannerMode.enabled) {
        debounce = setTimeout(() => {
          this.currentOffset = 0;
          this.hasMore = true;
          this.performSearch(e.target.value.trim(), false, false);
        }, 300);
      }
    });

    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        if (ScannerMode.enabled) return;
        const query = e.target.value.trim();
        if (query) {
          this.currentOffset = 0;
          this.hasMore = true;
          this.performSearch(query, true, false);
        }
      }
    });

    // No preload — wait for user input
    // The empty state placeholder shows until user types/scans
  },

  initInfiniteScroll() {
    const container = document.getElementById('search-results');
    if (!container) return;

    this.sentinel = document.createElement('div');
    this.sentinel.id = 'infinite-scroll-sentinel';
    this.sentinel.style.height = '10px';
    this.sentinel.style.width = '100%';

    this.observer = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting && !this.isLoading && this.hasMore) {
        this.loadMore();
      }
    }, { root: container.parentElement, threshold: 0.1 });
  },

  loadMore() {
    const query = document.getElementById('product-search').value.trim();
    this.currentOffset += 50;
    this.performSearch(query, false, true);
  },

  async loadCategories() {
    const tabsContainer = document.getElementById('category-tabs');
    if (!tabsContainer) return;

    try {
      const res = await fetch('../../api/pos/get_categories.php');
      const categories = await res.json();

      categories.forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'btn btn-sm btn-outline-primary rounded-pill px-3';
        btn.dataset.categoryId = cat.category_id;
        btn.textContent = cat.category_name;
        btn.onclick = () => this.setCategory(cat.category_id, btn);
        tabsContainer.appendChild(btn);
      });

      const allBtn = tabsContainer.querySelector('[data-category-id="all"]');
      if (allBtn) {
        allBtn.onclick = () => this.setCategory('all', allBtn);
      }
    } catch (err) {
      console.error('Failed to load categories:', err);
    }
  },

  setCategory(catId, btn) {
    this.activeCategory = catId;
    this.currentOffset = 0;
    this.hasMore = true;

    document.querySelectorAll('#category-tabs .btn').forEach(b => {
      b.classList.remove('btn-primary', 'active-category');
      b.classList.add('btn-outline-primary');
    });
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-primary', 'active-category');

    const query = document.getElementById('product-search').value.trim();
    this.performSearch(query, false, false);
  },

  setViewMode(mode) {
    this.viewMode = mode;
    document.querySelectorAll('.view-toggle-group .view-btn').forEach(b => {
      b.classList.remove('active', 'text-primary', 'shadow-sm', 'bg-white');
      b.classList.add('text-muted');
    });
    const activeBtn = document.getElementById(`view-${mode}`);
    if (activeBtn) {
      activeBtn.classList.remove('text-muted');
      activeBtn.classList.add('active', 'text-primary', 'shadow-sm', 'bg-white');
    }

    const container = document.getElementById('search-results');
    if (container) {
      container.classList.remove('grid-mode', 'compact-mode', 'list-mode');
      container.classList.add(`${mode}-mode`);
    }

    const query = document.getElementById('product-search').value.trim();
    this.currentOffset = 0;
    this.hasMore = true;

    // Adapt to scanner mode if inventory is not explicitly being searched
    if (ScannerMode.enabled && !query) {
      this.renderScannerPlaceholder();
      return;
    }

    this.performSearch(query, false, false);
  },

  renderScannerPlaceholder() {
    const container = document.getElementById('search-results');
    if (!container) return;

    container.innerHTML = `
      <div class="text-center text-muted py-5 w-100" style="grid-column: 1 / -1;" id="catalog-empty-state">
          <div class="mb-4">
              <i class="fa-solid fa-barcode fa-4x opacity-25 animated pulse infinite" style="color: var(--pos-primary);"></i>
          </div>
          <h5 class="fw-bold text-dark mb-1">Scanner Mode Active</h5>
          <p class="text-muted">Item will be automatically added once scanned.</p>
          <div class="mt-4 pt-3 border-top mx-auto" style="max-width: 200px; opacity: 0.5;">
              <small class="d-block mb-1 text-uppercase fw-bold" style="font-size: 10px;">Shortcut</small>
              <kbd class="bg-dark text-white px-2 py-1">Enter</kbd> to manually add
          </div>
      </div>
    `;
  },

  renderDefaultPlaceholder() {
    const container = document.getElementById('search-results');
    if (!container) return;

    container.innerHTML = `
      <div class="text-center text-muted mt-5 pt-5 w-100" style="grid-column: 1 / -1;" id="catalog-empty-state">
          <i class="fa-solid fa-magnifying-glass fa-3x mb-3 opacity-25"></i>
          <h6>Search Catalog</h6>
          <p class="small mb-0 text-muted">Type to search or select a category to view items</p>
      </div>
    `;
  },

  performSearch(query, isScan, append = false) {
    // If scanner mode is on and both query AND category are empty/default, show placeholder
    if (ScannerMode.enabled && !query && !append && this.activeCategory === 'all') {
      this.renderScannerPlaceholder();
      return;
    }

    // If no query and category is all, show default placeholder (No Preload)
    if (!query && !append && this.activeCategory === 'all') {
      this.renderDefaultPlaceholder();
      return;
    }

    this.isLoading = true;
    fetch(`../../api/pos/simple_search.php?q=${encodeURIComponent(query)}&category_id=${this.activeCategory}&offset=${this.currentOffset}`)
      .then((res) => res.json())
      .then((data) => {
        this.isLoading = false;
        if (data.length < 50) this.hasMore = false;
        this.renderCards(data, isScan, query, append);
      })
      .catch((err) => {
        this.isLoading = false;
        console.error("Search API Error:", err);
      });
  },

  renderCards(items, isScan, originalQuery, append = false) {
    const container = document.getElementById("search-results");
    const searchInput = document.getElementById("product-search");

    // Remove empty state placeholder when results arrive
    const emptyState = document.getElementById('catalog-empty-state');
    if (!append && emptyState) emptyState.remove();

    if (!append) {
      container.innerHTML = "";
      container.scrollTop = 0;
      container.className = `search-results-grid pt-2 ${this.viewMode}-mode`;
    } else {
      this.sentinel?.remove();
    }

    // Auto-add (only on first batch)
    if (!append) {
      const exactMatch = items ? items.find((i) => i.barcode === originalQuery && parseInt(i.stock) > 0) : null;
      if (isScan && exactMatch) {
        window.CartManager.addToCart(exactMatch, true);
        if (searchInput) searchInput.value = "";
        container.innerHTML = `<div class="text-center py-4 w-100"><div class="fs-5 fw-bold text-success">✓ Added: ${exactMatch.product_name}</div></div>`;
        setTimeout(() => { if (searchInput && searchInput.value === "") container.innerHTML = ""; }, 1000);
        return;
      }
    }

    if (!items || items.length === 0) {
      if (!append) {
        container.innerHTML = `
          <div class="text-center py-5 w-100">
            <i class="fa-solid fa-box-open fa-3x mb-3" style="color: var(--pos-primary); opacity: 0.25;"></i>
            <h6 class="fw-bold mb-1" style="color: #64748b;">No products found</h6>
            <p class="small mb-0" style="color: #94a3b8;">Try a different search term or category</p>
          </div>`;
      }
      return;
    }

    items.forEach((item) => {
      const stock = parseInt(item.stock);
      const buyer = window.POS_BUYER;
      let displayPrice = parseFloat(item.price_retail || 0);

      // Highlight helper
      const safeQuery = originalQuery ? originalQuery.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&').split(/\\s+/).filter(Boolean) : [];
      const highlight = (text) => {
        if (!text || safeQuery.length === 0) return text;
        let hlText = text;
        safeQuery.forEach(q => {
          const regex = new RegExp(`(${q})`, 'gi');
          hlText = hlText.replace(regex, '<mark class="bg-warning bg-opacity-50 px-1 rounded text-dark">$1</mark>');
        });
        return hlText;
      };

      if (buyer && buyer.price_tier) {
        const tierKey = "price_" + buyer.price_tier;
        const tierVal = parseFloat(item[tierKey] || 0);
        if (tierVal > 0) displayPrice = tierVal;
      }

      const imgUrl = item.image_path ? `../../${item.image_path}` : POS.config.defaultImage;
      const itemJson = JSON.stringify(item).replace(/'/g, "&apos;").replace(/"/g, "&quot;");

      // Full name tooltip content
      const fullName = `${item.product_name} ${item.brand_name ? '| ' + item.brand_name : ''} ${item.variation_name ? '(' + item.variation_name + ')' : ''}`.trim();
      const safeFullName = fullName.replace(/"/g, '&quot;');

      const col = document.createElement("div");
      col.className = "product-item";

      const desc = item.description || '';
      const safeDesc = desc.replace(/"/g, '&quot;');
      const descIconHtml = desc ? `<button type="button" class="btn btn-link btn-sm p-0 ms-1 search-desc-btn" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="bottom" data-bs-content="${safeDesc}" data-bs-title="Description" style="font-size:10px; color:var(--pos-primary);" onclick="event.stopPropagation()"><i class='fa-solid fa-circle-info'></i></button>` : '';

      col.innerHTML = `
                <div class="card h-100 border-0 shadow-sm product-card ${stock <= 0 ? "opacity-50" : ""}"
                     onclick='CartManager.addToCart(${itemJson})'
                     title="${safeFullName}"
                     style="cursor: pointer; border-radius: var(--pos-radius-sm);">
                    <div class="card-body d-flex align-items-center gap-3">
                        <img src="${imgUrl}" class="rounded bg-light flex-shrink-0"
                             width="48" height="48" loading="lazy"
                             style="width: 48px; height: 48px; object-fit: cover; border: 1px solid #e2e8f0;"
                             onerror="this.src='${POS.config.defaultImage}'">
                        <div class="flex-grow-1 overflow-hidden" style="min-width: 0;">
                            <div class="fw-bold text-truncate">
                                <span>${highlight(item.product_name)}</span>${descIconHtml}
                            </div>
                            <div class="text-truncate" style="font-size: 10px; line-height: 1.2; color: #475569; font-weight: 600;">
                                ${item.brand_name ? `<span style="color:#3b82f6;">${highlight(item.brand_name)}</span><span class="mx-1 opacity-50">|</span>` : ""}${highlight(item.variation_name || "")}
                            </div>
                            <div class="font-monospace d-flex align-items-center" style="font-size: 9px; line-height: 1.1; color: #94a3b8;">
                                ${ScannerMode.enabled ? '<i class="fa-solid fa-barcode me-1 text-primary"></i>' : ''}
                                ${highlight(item.barcode || item.sku || "")}
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1 gap-1">
                                <div class="d-flex align-items-center gap-1">
                                    <span class="fw-bold" style="font-size: 12px; color: #0f172a;">${POS.config.currency}${displayPrice.toFixed(2)}</span>
                                    ${item.unit_type ? `<span class="badge text-white" style="font-size: 9px; padding: 2px 5px; background: #64748b;">${item.unit_type}</span>` : ""}
                                </div>
                                <span class="badge" style="font-size: 8px; padding: 2px 7px; background: ${stock > 5 ? "#dcfce7; color:#166534" : "#fee2e2; color:#991b1b"};">
                                    ${stock}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
      container.appendChild(col);
      if (desc) {
        const btn = col.querySelector('.search-desc-btn');
        if (btn) new bootstrap.Popover(btn, { boundary: 'window', trigger: 'hover focus', html: false });
      }
    });

    if (this.hasMore) {
      container.appendChild(this.sentinel);
      this.observer.observe(this.sentinel);
    } else if (items.length > 0) {
      const endMsg = document.createElement("div");
      endMsg.className = "text-center text-muted small py-3 w-100";
      endMsg.innerHTML = `<i class="fa-solid fa-check-circle me-1"></i>All products loaded`;
      container.appendChild(endMsg);
    }
  },
};
