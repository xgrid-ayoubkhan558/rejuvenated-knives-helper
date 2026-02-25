(function () {
    // ===============================
    // 🔧 CONFIG
    // ===============================
    const STORAGE_KEY = 'bricks_cart_items';
    const DEBUG_LOGS = true;

    function log(...args) {
        if (DEBUG_LOGS) console.log('[Cart Sync Inputs]', ...args);
    }

    // ===============================
    // 🧾 LOAD CART JSON
    // ===============================
    function getCartItems() {
        try {
            const items = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            log(`Loaded ${items.length} item(s) from localStorage`, items);
            return items;
        } catch (e) {
            console.warn('[Cart Sync Inputs] Failed to parse cart JSON', e);
            return [];
        }
    }

    // ===============================
    // 🔁 DEBOUNCE
    // ===============================
    function debounce(fn, delay = 150) {
        let t;
        return function () {
            clearTimeout(t);
            const args = arguments;
            t = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // ===============================
    // 🔄 SYNC INPUT QTY FROM JSON
    // ===============================
    function syncQuantities(container = document) {
        const cartItems = getCartItems();
        if (!cartItems.length) {
            log('No items to sync.');
            return;
        }

        let syncedCount = 0;

        cartItems.forEach(item => {
            const productId = String(item.productId || item.product_id || '');
            const qty = Number(item.qty || item.quantity || 0);

            if (!productId) return;

            const blocks = container.querySelectorAll(
                `.bricks-loop-multi-add-to-cart[data-product-id="${productId}"]`
            );

            blocks.forEach(block => {
                const qtyInput = block.querySelector('.quantity-input');
                if (!qtyInput) return;

                if (Number(qtyInput.value) !== qty) {
                    qtyInput.value = qty;
                    syncedCount++;
                }
            });
        });

        if (syncedCount) {
            log(`Synced ${syncedCount} input(s) in`, container);
        } else {
            log('No inputs needed syncing in', container);
        }
    }

    const debouncedSyncAll = debounce(() => syncQuantities(document), 200);

    // ===============================
    // 📑 TABS SUPPORT
    // ===============================
    function initTabs() {
        const tabMenu = document.querySelector('.order__tab-menu');
        const hasTabs = !!tabMenu;
        log(`Tabs found: ${hasTabs}`);

        if (!hasTabs) return false;

        tabMenu.querySelectorAll('.tab-title').forEach(tab => {
            if (tab.__qtyBound) return;
            tab.__qtyBound = true;

            tab.addEventListener('click', () => {
                log('Tab clicked:', tab.textContent.trim());
                const pane = document.getElementById(tab.getAttribute('aria-controls'));
                if (!pane) return;

                // Bricks sometimes renders late → double sync
                syncQuantities(pane);
                requestAnimationFrame(() => syncQuantities(pane));
                setTimeout(() => syncQuantities(pane), 150);
            });
        });

        // Active tab on load
        const activeTab = tabMenu.querySelector('.brx-open, [aria-selected="true"]');
        if (activeTab) {
            const pane = document.getElementById(activeTab.getAttribute('aria-controls'));
            log('Active tab on load:', activeTab.textContent.trim());
            if (pane) {
                syncQuantities(pane);
                requestAnimationFrame(() => syncQuantities(pane));
            }
        }

        return true;
    }

    // ===============================
    // 🖱️ UPDATE CART BUTTON (AUTO RESYNC INPUTS)
    // ===============================
    function bindUpdateCartButton() {
        function attach() {
            const btn = document.querySelector('button[name="update_cart"]');
            if (!btn) return false;
            if (btn.__qtyBound) return true;

            btn.__qtyBound = true;
            btn.addEventListener('click', () => {
                log('Update cart clicked → syncing inputs');
                debouncedSyncAll();
            });

            log('Update cart button bound for input sync');
            return true;
        }

        if (attach()) return;

        const retry = new MutationObserver(() => {
            if (attach()) retry.disconnect();
        });

        retry.observe(document.body, { childList: true, subtree: true });
    }

    // ===============================
    // 🚀 INIT
    // ===============================
    function init() {
        log('Initializing input sync logic...');
        initTabs();
        bindUpdateCartButton();

        // Initial sync
        setTimeout(() => {
            log('Initial sync');
            syncQuantities(document);
        }, 200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

(function () {
    // ===============================
    // 🔧 CONFIG
    // ===============================
    const DEBUG_LOGS = false;
    const STORAGE_KEY = 'bricks_cart_items';
    const RESYNC_DELAY = 250; // wait for Woo to finish DOM updates

    function log(...args) {
        if (DEBUG_LOGS) console.log('[Cart Sync]', ...args);
    }

    // ===============================
    // 🎯 SELECTORS
    // ===============================
    const MINI_CART_SELECTOR = '.brxe-woocommerce-mini-cart';
    const UPDATE_CART_BTN_SELECTOR = 'button[name="update_cart"]';

    // ===============================
    // 🧠 HELPERS
    // ===============================
    function debounce(fn, delay = 300) {
        let t;
        return function () {
            clearTimeout(t);
            const args = arguments;
            t = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // ===============================
    // 🧾 BUILD JSON FROM MINI CART DOM
    // ===============================
    function buildCartJSONFromMiniCart() {
        const container = document.querySelector(MINI_CART_SELECTOR);
        if (!container) {
            log('❌ Mini cart not found for JSON build');
            return;
        }

        const items = [...container.querySelectorAll('.woocommerce-mini-cart-item')].map(item => {
            const title = item.querySelector('a:not(.remove)')?.textContent?.trim() || '';
            const qtyText = item.querySelector('.quantity')?.textContent || '0';
            const qty = parseInt(qtyText, 10) || 0;
            const priceText = item.querySelector('.woocommerce-Price-amount')?.textContent || '';
            const price = parseFloat(priceText.replace(/[^\d.]/g, '')) || 0;
            const productId = item.querySelector('.remove')?.dataset?.product_id || null;

            return { title, qty, price, productId };
        });

        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        log('💾 Cart JSON regenerated & saved', items);
    }

    const debouncedResync = debounce(buildCartJSONFromMiniCart, RESYNC_DELAY);

    // ===============================
    // 🔁 CENTRAL RESYNC TRIGGER
    // ===============================
    function onCartShouldResync(source) {
        log(`♻️ Resync requested by: ${source}`);
        debouncedResync();
    }

    // ===============================
    // 👀 OBSERVE MINI CART HTML CHANGES
    // ===============================
    function observeMiniCartHTML() {
        const target = document.querySelector(MINI_CART_SELECTOR);

        if (!target) {
            log('❌ Mini cart DOM not found');
            return;
        }

        log('✅ Mini cart observer attached');

        let lastHTML = target.innerHTML;

        const observer = new MutationObserver(() => {
            const currentHTML = target.innerHTML;

            if (currentHTML !== lastHTML) {
                lastHTML = currentHTML;
                log('🔄 Mini cart HTML changed');
                onCartShouldResync('mini-cart DOM changed');
            }
        });

        observer.observe(target, {
            childList: true,
            subtree: true,
            characterData: true
        });

        window.__miniCartObserver = observer;
    }

    // ===============================
    // 🖱️ UPDATE CART BUTTON CLICK
    // ===============================
    function bindUpdateCartButton() {
        function attach() {
            const btn = document.querySelector(UPDATE_CART_BTN_SELECTOR);

            if (!btn) {
                log('❌ Update cart button not found yet');
                return false;
            }

            if (btn.__cartBound) return true;

            btn.__cartBound = true;

            btn.addEventListener('click', function () {
                log('🖱️ Update cart clicked (waiting for Woo AJAX)');
                onCartShouldResync('update cart button clicked');
            });

            log('✅ Update cart button listener attached');
            return true;
        }

        if (attach()) return;

        const retryObserver = new MutationObserver(() => {
            if (attach()) retryObserver.disconnect();
        });

        retryObserver.observe(document.body, { childList: true, subtree: true });
    }

    // ===============================
    // 🚀 INIT
    // ===============================
    function init() {
        log('🚀 Cart sync initializing...');
        observeMiniCartHTML();
        bindUpdateCartButton();

        // Initial sync on load
        setTimeout(() => {
            onCartShouldResync('initial load');
        }, 300);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
