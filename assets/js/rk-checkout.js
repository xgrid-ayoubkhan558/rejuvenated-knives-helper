(function () {
    'use strict';

    // Configuration
    const opts = window.rk_check_fields_options || {};
    const config = {
        enableAutoPayment: opts.enable_auto_payment == 1,
        paymentFound: opts.payment_found || 'cod',
        paymentNotFound: opts.payment_not_found || 'other_payment',
        addBodyClasses: opts.add_body_classes == 1,
        dateFormat: opts.date_format || 'd-m-Y',
        minDaysAdvance: opts.min_days_advance || 0,
        maxDaysAdvance: opts.max_days_advance || 90,
        messages: {
            mailIn: opts.mailin_message || "Good news! While your location is outside our door-to-door coverage area, you can mail in your knives using our premium mail-in service.",
            cityFound: opts.city_found_message || "Hooray! You're within our door-to-door service area.",
            citySelected: opts.city_selected_message || "Hooray! You're within our door-to-door service area."
        }
    };

    // Data management
    let regionsData = [];
    let cities = [];

    function loadData() {
        const dataHolder = document.getElementById('rk-check-fields-data');
        if (dataHolder?.dataset?.regions) {
            try {
                regionsData = JSON.parse(dataHolder.dataset.regions);
                buildCitiesList();
            } catch (e) {
                console.error('[RK] Failed to parse regions data');
            }
        }
    }

    function buildCitiesList() {
        cities = [];
        regionsData.forEach(region => {
            (region.cities || []).forEach(city => {
                cities.push({ ...city, region });
            });
        });
    }

    // DOM helpers
    const $ = (selector, parent = document) => parent.querySelector(selector);
    const $$ = (selector, parent = document) => parent.querySelectorAll(selector);

    function trigger(element, eventName) {
        element.dispatchEvent(new Event(eventName, { bubbles: true, cancelable: true }));
    }

    function removeError(element) {
        const row = element.closest('.form-row');
        if (row) {
            row.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
            const error = $('.woocommerce-error', row);
            if (error) error.remove();
        }
    }

    // Body class management
    function updateBodyClass(hasCity, state = null) {
        if (!config.addBodyClasses) return;
        
        // Remove all state classes
        document.body.classList.remove('rk-city-selected', 'rk-city-not-selected', 'rk-city-found', 'rk-city-not-found');
        
        // Add appropriate class
        if (hasCity) {
            document.body.classList.add('rk-city-selected');
        } else {
            document.body.classList.add('rk-city-not-selected');
        }
        
        // Add state class if provided
        if (state) {
            document.body.classList.add(`rk-city-${state}`);
        }
        
        // Also set data attribute for CSS targeting
        if (state === 'found' || hasCity) {
            document.body.setAttribute('data-payment-method', config.paymentFound);
        } else if (state === 'not-found') {
            document.body.setAttribute('data-payment-method', config.paymentNotFound);
        } else {
            document.body.removeAttribute('data-payment-method');
        }
    }

    // Payment method selection
    function selectPaymentMethod(methodId) {
        const method = $(`input[name="payment_method"][value="${methodId}"]`);
        if (method) {
            method.checked = true;
            trigger(method, 'change');
        }
    }

    // Date picker initialization
    function initDatePicker(dateInput, region) {
        if (!dateInput || typeof flatpickr === 'undefined') return null;

        const enabledDays = [];
        if (region.pickup) {
            Object.values(region.pickup).forEach((day, i) => {
                if (day?.enabled) enabledDays.push(i);
            });
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const minDate = new Date(today);
        minDate.setDate(today.getDate() + config.minDaysAdvance);
        const maxDate = new Date(today);
        maxDate.setDate(today.getDate() + config.maxDaysAdvance);

        return flatpickr(dateInput, {
            dateFormat: config.dateFormat,
            minDate: minDate,
            maxDate: config.maxDaysAdvance > 0 ? maxDate : null,
            disable: enabledDays.length > 0 ? [
                date => !enabledDays.includes(date.getDay())
            ] : []
        });
    }

    // Main city search handler
    function initCitySearch() {
        const searchInput = $('#billing_rk_city_search');
        const cityInput = $('#billing_rk_city');
        const regionInput = $('#billing_rk_region');
        const dateInput = $('#billing_rk_pickup_date');

        if (!searchInput || !cityInput) {
            console.error('[RK] Required fields not found');
            return;
        }

        // Make region readonly
        if (regionInput) {
            regionInput.readOnly = true;
            regionInput.style.cssText = 'background-color: #f5f5f5; cursor: not-allowed;';
        }

        // Create UI elements
        const wrapper = document.createElement('div');
        wrapper.className = 'rk-city-wrapper';
        searchInput.parentNode.insertBefore(wrapper, searchInput.nextSibling);

        const dropdown = document.createElement('div');
        dropdown.className = 'rk-city-dropdown';
        wrapper.appendChild(dropdown);

        const message = document.createElement('div');
        message.className = 'rk-message';
        wrapper.appendChild(message);

        let datePicker = null;
        const dateRow = dateInput?.closest('.form-row');
        if (dateRow) dateRow.style.display = 'none';

        // Clear function
        function clear() {
            dropdown.innerHTML = '';
            dropdown.style.display = 'none';
            message.textContent = '';
            cityInput.value = '';
            if (regionInput) regionInput.value = '';
            if (datePicker) datePicker.clear();
            if (dateRow) dateRow.style.display = 'none';
            updateBodyClass(false, null);
            trigger(cityInput, 'change');
        }

        // Search handler
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            if (!query) {
                clear();
                return;
            }

            const matches = cities.filter(c => 
                `${c.city_name} ${c.region.region_name}`.toLowerCase().includes(query)
            );

            if (matches.length === 0) {
                dropdown.innerHTML = '';
                dropdown.style.display = 'none';
                message.textContent = config.messages.mailIn;
                cityInput.value = '';
                if (regionInput) regionInput.value = '';
                updateBodyClass(false, 'not-found');
                
                if (config.enableAutoPayment) {
                    selectPaymentMethod(config.paymentNotFound);
                }
                return;
            }

            // Show matches
            message.textContent = config.messages.cityFound;
            dropdown.innerHTML = '';
            dropdown.style.display = 'block';
            
            updateBodyClass(false, 'found');

            matches.forEach(city => {
                const option = document.createElement('div');
                option.className = 'rk-city-option';
                option.textContent = `${city.city_name} (${city.region.region_name})`;
                
                option.onclick = () => selectCity(city);
                dropdown.appendChild(option);
            });
        });

        // City selection handler
        function selectCity(city) {
            // Update fields
            searchInput.value = city.city_name;
            cityInput.value = city.city_name;
            if (regionInput) regionInput.value = city.region.region_name;
            
            // Trigger events
            trigger(cityInput, 'change');
            if (regionInput) trigger(regionInput, 'change');
            
            // Hide dropdown
            dropdown.style.display = 'none';
            message.textContent = config.messages.citySelected;
            
            // Remove errors
            removeError(searchInput);
            removeError(cityInput);
            if (regionInput) removeError(regionInput);
            
            // Update classes
            updateBodyClass(true, 'selected');
            
            // Payment method
            if (config.enableAutoPayment) {
                selectPaymentMethod(config.paymentFound);
            }
            
            // Date picker
            if (dateInput) {
                if (datePicker) datePicker.destroy();
                datePicker = initDatePicker(dateInput, city.region);
                if (dateRow) dateRow.style.display = '';
                dateInput.required = true;
            }
            
            // Enable submit button
            const submitBtn = $('#place_order');
            if (submitBtn) submitBtn.disabled = false;
        }

        // Close dropdown on outside click
        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target) && !searchInput.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Form validation
        const form = $('form.checkout');
        if (form) {
            form.addEventListener('submit', () => {
                if (cityInput.value || searchInput.value) {
                    searchInput.removeAttribute('required');
                    removeError(searchInput);
                }
            }, true);
        }

        // Initialize state
        if (cityInput.value) {
            updateBodyClass(true, 'selected');
            if (dateInput) {
                const city = cities.find(c => c.city_name === cityInput.value);
                if (city) {
                    datePicker = initDatePicker(dateInput, city.region);
                    if (dateRow) dateRow.style.display = '';
                }
            }
        } else {
            updateBodyClass(false, null);
        }
    }

    // Initialize
    function init() {
        loadData();
        if (regionsData.length > 0) {
            initCitySearch();
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-initialize on WooCommerce AJAX updates (if jQuery is available)
    if (window.jQuery) {
        jQuery(document.body).on('updated_checkout', () => {
            const regionInput = $('#billing_rk_region');
            if (regionInput) {
                regionInput.readOnly = true;
                regionInput.style.cssText = 'background-color: #f5f5f5; cursor: not-allowed;';
            }
        });
    }

})();