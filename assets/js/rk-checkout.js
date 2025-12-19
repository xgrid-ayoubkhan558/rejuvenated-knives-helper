(function () {
    'use strict';

    function init() {
        if (typeof rk_check_fields_data === 'undefined') {
            return;
        }

        let data = [];

        // Prefer reading locations JSON from the hidden checkout container
        const dataHolder = document.getElementById('rk-check-fields-data');
        if (dataHolder && dataHolder.dataset && dataHolder.dataset.regions) {
            try {
                data = JSON.parse(dataHolder.dataset.regions);
            } catch (e) {
                // fallback to localized var
                data = (typeof rk_check_fields_data !== 'undefined') ? rk_check_fields_data : [];
            }
        } else {
            // fallback: look for the attribute on any search field (prefixed or unprefixed)
            const searchSource = document.querySelector('[name="billing_rk_city_search"], [name="rk_city_search"], #billing_rk_city_search');
            if (searchSource && searchSource.dataset && searchSource.dataset.regions) {
                try {
                    data = JSON.parse(searchSource.dataset.regions);
                } catch (e) {
                    data = (typeof rk_check_fields_data !== 'undefined') ? rk_check_fields_data : [];
                }
            } else {
                data = (typeof rk_check_fields_data !== 'undefined') ? rk_check_fields_data : [];
            }
        }

        // Build flat list of cities with region attached
        let cities = [];
        function rebuildCities() {
            cities = [];
            data.forEach(region => {
                (region.cities || []).forEach(city => {
                    cities.push(Object.assign({}, city, { region: region }));
                });
            });
        }
        rebuildCities();

        // If we have no data, try to fetch it from AJAX endpoint (useful if data-regions isn't proper JSON)
        function fetchLocations() {
            if (typeof rk_check_fields_ajax === 'undefined') {
                return Promise.resolve();
            }

            return fetch(rk_check_fields_ajax.ajax_url + '?action=rk_get_locations')
                .then(response => response.json())
                .then(json => {
                    if (json && json.success && Array.isArray(json.data)) {
                        data = json.data;
                        rebuildCities();
                    }
                })
                .catch(() => { });
        }

        // If no initially available regions, attempt a fetch so search works
        if (!data || !data.length) {
            fetchLocations();
        }

        // Override with options from backend if provided
        const opts = (typeof rk_check_fields_options !== 'undefined') ? rk_check_fields_options : {};
        const ENABLE_AUTO_PAYMENT = opts.enable_auto_payment == 1 || opts.enable_auto_payment === true;
        const PAYMENT_FOUND = opts.payment_found || 'cod';
        const PAYMENT_NOT_FOUND = opts.payment_not_found || 'other_payment';
        const ADD_BODY_CLASSES = opts.add_body_classes == 1 || opts.add_body_classes === true;
        const DATE_FORMAT = opts.date_format || 'd-m-Y';
        
        // Get messages from settings
        let mailInMessage = opts.mailin_message || 
            "Good news!\n\n" +
            "While your location is outside our door-to-door coverage area, " +
            "you can mail in your knives using our premium mail-in service.\n\n" +
            "We'll send you a complete mailing kit and sharpen them to perfection.";
        
        let cityFoundMessage = opts.city_found_message || 
            "Hooray! You're within our door-to-door service area.\n" +
            "Simply pick a preferred pick-up date, and we'll take care of the rest—collecting your knives, sharpening them to perfection, and delivering them back to you promptly.";
        
        let citySelectedMessage = opts.city_selected_message || 
            "Hooray! You're within our door-to-door service area.\n" +
            "Simply pick a preferred pick-up date, and we'll take care of the rest—collecting your knives, sharpening them to perfection, and delivering them back to you promptly.";

        // Initialize region fields as read-only on page load (billing only)
        function initializeRegionFields() {
            const regionFields = document.querySelectorAll('[name="billing_rk_region"], #billing_rk_region');
            regionFields.forEach(field => {
                if (field.tagName && field.tagName.toUpperCase() !== 'SELECT') {
                    field.setAttribute('readonly', 'readonly');
                    field.style.backgroundColor = '#f5f5f5';
                    field.style.cursor = 'not-allowed';
                }
            });
        }

        // Initialize region fields immediately
        initializeRegionFields();

        // Function to update body classes based on city selection state
        function updateBodyCityClasses(searchInput, cityInput) {
            if (!ADD_BODY_CLASSES) {
                return;
            }

            try {
                const searchValue = (searchInput ? (searchInput.value || '').trim() : '');
                const cityValue = (cityInput ? (cityInput.value || '').trim() : '');
                const hasCitySelected = cityValue.length > 0; // Only consider it selected if hidden field has value

                if (hasCitySelected) {
                    // City is selected
                    document.body.classList.add('rk-city-selected');
                    document.body.classList.remove('rk-city-not-selected');
                } else {
                    // City is not selected
                    document.body.classList.add('rk-city-not-selected');
                    document.body.classList.remove('rk-city-selected');
                }
            } catch (e) {
                console.error('[RK] Error updating body classes:', e);
            }
        }

        // Initialize body class on page load - check all city fields
        function initializeBodyCityClass() {
            if (!ADD_BODY_CLASSES) {
                return;
            }

            try {
                // Check all possible city input fields (UPDATED: billing_ prefix)
                const allCityInputs = document.querySelectorAll('[name="billing_rk_city"], #billing_rk_city');
                let hasAnyCitySelected = false;

                allCityInputs.forEach(cityInput => {
                    const cityValue = (cityInput.value || '').trim();
                    if (cityValue.length > 0) {
                        hasAnyCitySelected = true;
                    }
                });

                if (hasAnyCitySelected) {
                    document.body.classList.add('rk-city-selected');
                    document.body.classList.remove('rk-city-not-selected');
                } else {
                    document.body.classList.add('rk-city-not-selected');
                    document.body.classList.remove('rk-city-selected');
                }
            } catch (e) {
                console.error('[RK] Error initializing body class:', e);
                // Default to not-selected if there's an error
                if (ADD_BODY_CLASSES) {
                    document.body.classList.add('rk-city-not-selected');
                }
            }
        }

        // Initialize body class immediately on page load
        initializeBodyCityClass();

        // Function to update city field classes based on value
        function updateCityFieldClasses(searchInput, cityInput) {
            const searchValue = (searchInput.value || '').trim();
            const cityValue = (cityInput ? cityInput.value || '' : '').trim();
            const hasValue = searchValue.length > 0 || cityValue.length > 0;
            
            // Get the field wrapper
            const fieldWrapper = searchInput.closest('.form-row, .woocommerce-input-wrapper, p, .form-row-wide');
            if (fieldWrapper) {
                if (hasValue) {
                    fieldWrapper.classList.add('rk-city-has-value');
                    fieldWrapper.classList.remove('rk-city-empty');
                } else {
                    fieldWrapper.classList.add('rk-city-empty');
                    fieldWrapper.classList.remove('rk-city-has-value');
                }
            }
            
            // Also add class to the input itself
            if (hasValue) {
                searchInput.classList.add('rk-city-has-value');
                searchInput.classList.remove('rk-city-empty');
            } else {
                searchInput.classList.add('rk-city-empty');
                searchInput.classList.remove('rk-city-has-value');
            }
        }

        // Find any search inputs (billing only now)
        const searchInputs = document.querySelectorAll('[name="billing_rk_city_search"], #billing_rk_city_search');

        console.log('[RK] Found search inputs:', searchInputs.length);

        searchInputs.forEach(searchInput => {
            // CRITICAL: Always use billing_ prefix for field selectors
            // The hidden city field is manually created with name="billing_rk_city"
            const cityInput = document.querySelector('[name="billing_rk_city"], #billing_rk_city');
            const regionInput = document.querySelector('[name="billing_rk_region"], #billing_rk_region');
            const dateInput = document.querySelector('[name="billing_rk_pickup_date"], #billing_rk_pickup_date');

            console.log('[RK] Fields found:', {
                cityInput: cityInput ? 'YES' : 'NO',
                regionInput: regionInput ? 'YES' : 'NO',
                dateInput: dateInput ? 'YES' : 'NO'
            });

            // Verify hidden city field exists
            if (!cityInput) {
                console.error('[RK] CRITICAL: Hidden city field (billing_rk_city) not found in DOM!');
                console.log('[RK] Available inputs:', Array.from(document.querySelectorAll('input[type="hidden"]')).map(i => i.name));
            } else {
                console.log('[RK] Hidden city field found:', cityInput.id, 'value:', cityInput.value);
            }

            // Create or reuse UI elements: dropdown, message, selected info, date wrapper
            let wrapper = searchInput.parentNode.querySelector('.rk-city-wrapper');
            let dropdown, message, cityInfo, dateWrapper;

            if (wrapper) {
                dropdown = wrapper.querySelector('.rk-city-dropdown') || document.createElement('div');
                message = wrapper.querySelector('.rk-message') || document.createElement('div');
                cityInfo = wrapper.querySelector('.rk-city-info') || document.createElement('div');
                dateWrapper = wrapper.querySelector('.rk-date-wrapper') || document.createElement('div');

                // ensure classes exist
                dropdown.classList.add('rk-city-dropdown');
                message.classList.add('rk-message');
                cityInfo.classList.add('rk-city-info');
                dateWrapper.classList.add('rk-date-wrapper');
            } else {
                wrapper = document.createElement('div');
                dropdown = document.createElement('div');
                message = document.createElement('div');
                cityInfo = document.createElement('div');
                dateWrapper = document.createElement('div');

                // Use CSS classes for layout and styling
                dropdown.className = 'rk-city-dropdown';
                message.className = 'rk-message';
                cityInfo.className = 'rk-city-info';
                dateWrapper.className = 'rk-date-wrapper';
                wrapper.classList.add('rk-city-wrapper');

                // insert after the search input
                searchInput.parentNode.insertBefore(wrapper, searchInput.nextSibling);
                wrapper.appendChild(dropdown);
                wrapper.appendChild(message);
                wrapper.appendChild(cityInfo);
                wrapper.appendChild(dateWrapper);
            }

            // Keep date wrapper hidden initially
            dateWrapper.style.display = 'none';
            dropdown.classList.remove('visible');
            dropdown.style.display = 'none';

            // attach flatpickr instance when needed
            let fp = null;
            if (dateInput && typeof flatpickr !== 'undefined') {
                try {
                    // Initial flatpickr setup - will be updated when city is selected
                    const minDaysAdvance = opts.min_days_advance || 0;
                    const maxDaysAdvance = opts.max_days_advance || 90;
                    
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    const minDate = new Date(today);
                    minDate.setDate(today.getDate() + minDaysAdvance);
                    const maxDate = new Date(today);
                    maxDate.setDate(today.getDate() + maxDaysAdvance);
                    
                    fp = flatpickr(dateInput, {
                        dateFormat: DATE_FORMAT,
                        clickOpens: true,
                        minDate: minDate, // Disable past dates and respect min days advance
                        maxDate: maxDaysAdvance > 0 ? maxDate : null, // Set max date if specified
                        disable: [] // Will be updated when city is selected
                    });
                } catch (err) {
                    console.error('[RK] flatpickr init error', err);
                    fp = null;
                }
            }

            // Find the visible row/container for the date input so we can show/hide it
            let dateFieldRow = null;
            if (dateInput) {
                dateFieldRow = dateInput.closest('.form-row') || dateInput.closest('p') || dateInput.parentNode;
                if (dateFieldRow) {
                    // hide initially
                    dateFieldRow.style.display = 'none';
                }
            }

            // Clear UI helper
            function clearUi() {
                dropdown.innerHTML = '';
                dropdown.classList.remove('visible');
                dropdown.style.display = 'none';
                message.textContent = '';
                cityInfo.innerHTML = '';
                if (fp) {
                    fp.clear();
                }
                if (dateFieldRow) dateFieldRow.style.display = 'none';
                if (dateInput) {
                    dateInput.required = false;
                }
                if (searchInput) {
                    searchInput.setAttribute('aria-expanded', 'false');
                }
                // Clear region and city fields when search is cleared
                if (regionInput) {
                    regionInput.value = '';
                    ['input', 'change', 'blur'].forEach(eventType => {
                        try {
                            regionInput.dispatchEvent(new Event(eventType, { bubbles: true, cancelable: true }));
                        } catch (e) {
                            if (document.createEvent) {
                                const evt = document.createEvent('HTMLEvents');
                                evt.initEvent(eventType, true, true);
                                regionInput.dispatchEvent(evt);
                            }
                        }
                    });
                }
                // CRITICAL: Clear the HIDDEN city field (billing_rk_city)
                if (cityInput) {
                    console.log('[RK] Clearing hidden city field');
                    cityInput.value = '';
                    cityInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                }
                // Update classes and required state when cleared
                updateCityFieldClasses(searchInput, cityInput);
                updateCityFieldRequired(searchInput, cityInput);
            }

            // Also listen for changes on the hidden city field
            if (cityInput) {
                cityInput.addEventListener('change', function() {
                    console.log('[RK] Hidden city field changed:', cityInput.value);
                    updateCityFieldClasses(searchInput, cityInput);
                    updateCityFieldRequired(searchInput, cityInput);
                    updateBodyCityClasses(searchInput, cityInput);
                });
            }

            // Before form submission, ensure validation passes if field has value
            const checkoutForm = document.querySelector('form.checkout');
            if (checkoutForm) {
                // Remove required attribute before form validation - use multiple event types
                const removeRequiredBeforeSubmit = function(e) {
                    const searchValue = (searchInput.value || '').trim();
                    const cityValue = (cityInput ? cityInput.value || '' : '').trim();
                    const hasValue = searchValue.length > 0 || cityValue.length > 0;
                    
                    console.log('[RK] Form submission check:', { searchValue, cityValue, hasValue });
                    
                    if (hasValue) {
                        // Remove required attribute to prevent validation error
                        searchInput.removeAttribute('required');
                        searchInput.setAttribute('data-has-value', 'true');
                        
                        // Also remove from any cloned fields (WooCommerce might clone fields)
                        const allSearchInputs = document.querySelectorAll('[name="billing_rk_city_search"]');
                        allSearchInputs.forEach(input => {
                            const inputValue = (input.value || '').trim();
                            if (inputValue.length > 0) {
                                input.removeAttribute('required');
                                input.setAttribute('data-has-value', 'true');
                            }
                        });
                        
                        // Also remove validation errors immediately
                        const fieldRow = searchInput.closest('.form-row, .woocommerce-input-wrapper, p');
                        if (fieldRow) {
                            fieldRow.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
                            const errorLabel = fieldRow.querySelector('.woocommerce-error');
                            if (errorLabel) {
                                errorLabel.remove();
                            }
                        }
                    }
                };
                
                // Attach to multiple events to catch it early
                checkoutForm.addEventListener('submit', removeRequiredBeforeSubmit, true); // Capture phase
                checkoutForm.addEventListener('click', function(e) {
                    // If clicking place order button, remove required immediately
                    if (e.target && (e.target.type === 'submit' || e.target.closest('button[type="submit"]'))) {
                        removeRequiredBeforeSubmit(e);
                    }
                }, true);
                
                // Also handle on input blur to remove required immediately when field has value
                searchInput.addEventListener('blur', function() {
                    const searchValue = (searchInput.value || '').trim();
                    const cityValue = (cityInput ? cityInput.value || '' : '').trim();
                    const hasValue = searchValue.length > 0 || cityValue.length > 0;
                    
                    if (hasValue) {
                        searchInput.removeAttribute('required');
                        // Remove any existing validation errors
                        const fieldRow = searchInput.closest('.form-row, .woocommerce-input-wrapper, p');
                        if (fieldRow) {
                            fieldRow.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
                            const errorLabel = fieldRow.querySelector('.woocommerce-error');
                            if (errorLabel) {
                                errorLabel.remove();
                            }
                        }
                    }
                });

                // Also handle checkout_place_order event (WooCommerce AJAX)
                if (typeof jQuery !== 'undefined') {
                    jQuery(checkoutForm).on('checkout_place_order', function() {
                        const searchValue = (searchInput.value || '').trim();
                        const cityValue = (cityInput ? cityInput.value || '' : '').trim();
                        const hasValue = searchValue.length > 0 || cityValue.length > 0;
                        
                        console.log('[RK] checkout_place_order:', { searchValue, cityValue, hasValue });
                        
                        if (hasValue) {
                            // Remove any validation errors
                            const fieldRow = searchInput.closest('.form-row, .woocommerce-input-wrapper, p');
                            if (fieldRow) {
                                fieldRow.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
                                const errorLabel = fieldRow.querySelector('.woocommerce-error');
                                if (errorLabel) {
                                    errorLabel.remove();
                                }
                            }
                            // Ensure required attribute is removed
                            searchInput.removeAttribute('required');
                            
                            // Remove from all search inputs
                            const allSearchInputs = document.querySelectorAll('[name="billing_rk_city_search"]');
                            allSearchInputs.forEach(input => {
                                const inputValue = (input.value || '').trim();
                                if (inputValue.length > 0) {
                                    input.removeAttribute('required');
                                }
                            });
                        }
                    });
                }
            }

            // Function to update required attribute based on value
            function updateCityFieldRequired(searchInput, cityInput) {
                const searchValue = (searchInput.value || '').trim();
                const cityValue = (cityInput ? cityInput.value || '' : '').trim();
                const hasValue = searchValue.length > 0 || cityValue.length > 0;
                
                // Remove required attribute if field has a value (to prevent validation error)
                // WooCommerce will still validate it, but we'll handle the error removal
                if (hasValue) {
                    searchInput.removeAttribute('required');
                    searchInput.setAttribute('data-has-value', 'true');
                } else {
                    // Only add required if it was originally required
                    if (searchInput.hasAttribute('data-originally-required')) {
                        searchInput.setAttribute('required', 'required');
                    }
                    searchInput.removeAttribute('data-has-value');
                }
            }

            // Store original required state
            if (searchInput.hasAttribute('required') || searchInput.required) {
                searchInput.setAttribute('data-originally-required', 'true');
            }

            // Initialize classes and required state on page load
            updateCityFieldClasses(searchInput, cityInput);
            updateCityFieldRequired(searchInput, cityInput);
            updateBodyCityClasses(searchInput, cityInput);
            
            // Disable submit button initially if city is not selected
            function updateSubmitButton() {
                const searchValue = (searchInput.value || '').trim();
                const cityValue = (cityInput ? cityInput.value || '' : '').trim();
                const hasCitySelected = cityValue.length > 0; // Only consider selected if hidden field has value
                
                const submitButton = document.querySelector('button[name="woocommerce_checkout_place_order"], #place_order');
                if (submitButton) {
                    if (hasCitySelected) {
                        submitButton.disabled = false;
                        submitButton.removeAttribute('disabled');
                    } else {
                        submitButton.disabled = true;
                        submitButton.setAttribute('disabled', 'disabled');
                    }
                }
            }
            
            // Check on page load
            updateSubmitButton();
            
            // Update when city field changes
            if (cityInput) {
                cityInput.addEventListener('change', updateSubmitButton);
            }

            searchInput.addEventListener('input', function () {
                const q = searchInput.value.toLowerCase().trim();
                dropdown.innerHTML = '';
                dropdown.style.display = 'none';
                cityInfo.innerHTML = '';
                if (fp && typeof fp.clear === 'function') {
                    try { fp.clear(); } catch (err) { console.error('[RK] flatpickr.clear error', err); }
                }
                if (dateWrapper) dateWrapper.style.display = 'none';

                // Update classes and required state based on current value
                updateCityFieldClasses(searchInput, cityInput);
                updateCityFieldRequired(searchInput, cityInput);

                console.log('[RK] input:', { q: q, citiesCount: cities.length });

                if (!q) {
                    message.textContent = '';
                    try {
                        if ( ADD_BODY_CLASSES ) {
                            document.body.classList.remove('rk-city-found', 'rk-city-not-found', 'rk-city-selected');
                            document.body.classList.add('rk-city-not-selected');
                        }
                    } catch (e) {}
                    // CRITICAL: Clear the HIDDEN city field when search is cleared
                    if (cityInput) {
                        console.log('[RK] Search cleared - clearing hidden city field');
                        cityInput.value = '';
                        cityInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    }
                    updateCityFieldClasses(searchInput, cityInput);
                    updateCityFieldRequired(searchInput, cityInput);
                    updateBodyCityClasses(searchInput, cityInput);
                    
                    // Disable submit button when city is cleared
                    const submitButton = document.querySelector('button[name="woocommerce_checkout_place_order"], #place_order');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.setAttribute('disabled', 'disabled');
                    }
                    return;
                }

                // If cities list is empty attempt to fetch synchronously before searching
                const doSearch = () => {
                    const matches = cities.filter(c => (`${c.city_name} ${c.region.region_name}`).toLowerCase().includes(q));

                    if (!matches.length) {
                        dropdown.classList.remove('visible');
                        dropdown.style.display = 'none';
                        message.textContent = mailInMessage;
                        console.log('[RK] no matches for', q);
                        
                        // CRITICAL: Clear the HIDDEN city field when no matches
                        if (cityInput) {
                            console.log('[RK] No matches - clearing hidden city field');
                            cityInput.value = '';
                            cityInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                        }
                        if (regionInput) {
                            regionInput.value = '';
                            // Trigger multiple events to ensure validation updates
                            ['input', 'change', 'blur'].forEach(eventType => {
                                try {
                                    regionInput.dispatchEvent(new Event(eventType, { bubbles: true, cancelable: true }));
                                } catch (e) {
                                    if (document.createEvent) {
                                        const evt = document.createEvent('HTMLEvents');
                                        evt.initEvent(eventType, true, true);
                                        regionInput.dispatchEvent(evt);
                                    }
                                }
                            });
                        }
                        // Update classes and required state when no matches
                        updateCityFieldClasses(searchInput, cityInput);
                        updateCityFieldRequired(searchInput, cityInput);
                        if (fp && typeof fp.clear === 'function') {
                            try { fp.clear(); } catch (err) { console.error('[RK] flatpickr.clear error', err); }
                        }
                        if (dateFieldRow) {
                            dateFieldRow.style.display = 'none';
                        }
                        if (dateInput) {
                            dateInput.required = false;
                        }
                        searchInput.setAttribute('aria-expanded', 'false');

                        // Body classes and select fallback payment
                        try {
                            if ( ADD_BODY_CLASSES ) {
                                document.body.classList.add('rk-city-not-found', 'rk-city-not-selected');
                                document.body.classList.remove('rk-city-found', 'rk-city-selected');
                            }
                        } catch (e) {}
                        // Select fallback payment method (configured via settings)
                        if ( ENABLE_AUTO_PAYMENT && PAYMENT_NOT_FOUND ) {
                            const fallback = document.querySelector('input[name="payment_method"][value="' + PAYMENT_NOT_FOUND + '"], input#payment_method_' + PAYMENT_NOT_FOUND);
                            if (fallback) {
                                try {
                                    fallback.checked = true;
                                    fallback.dispatchEvent(new Event('change'));
                                } catch (e) {}
                            }
                        }

                        return;
                    }

                    message.textContent = cityFoundMessage;

                    console.log('[RK] matches', matches);
                    dropdown.classList.add('visible');
                    dropdown.style.display = 'block';
                    searchInput.setAttribute('aria-expanded', 'true');
                        // Mark body that matches exist (not necessarily selected yet)
                        try {
                            if ( ADD_BODY_CLASSES ) {
                                document.body.classList.add('rk-city-found');
                                document.body.classList.remove('rk-city-not-found', 'rk-city-selected');
                                // Keep rk-city-not-selected since no city is actually selected yet
                                if (!document.body.classList.contains('rk-city-not-selected')) {
                                    document.body.classList.add('rk-city-not-selected');
                                }
                            }
                        } catch (e) {}

                    matches.forEach(c => {
                        const div = document.createElement('div');
                        div.className = 'rk-city-option';
                        div.textContent = `${c.city_name} (${c.region.region_name})`;

                        div.onclick = () => {
                            // Hide dropdown completely and update aria
                            dropdown.classList.remove('visible');
                            dropdown.style.display = 'none';
                            searchInput.setAttribute('aria-expanded', 'false');

                            // set visible search value and blur input
                            searchInput.value = c.city_name;
                            try { searchInput.blur(); } catch (e) { }

                            // Trigger validation events on search input to clear any errors
                            ['input', 'change', 'blur'].forEach(eventType => {
                                try {
                                    searchInput.dispatchEvent(new Event(eventType, { bubbles: true, cancelable: true }));
                                } catch (e) {
                                    if (document.createEvent) {
                                        const evt = document.createEvent('HTMLEvents');
                                        evt.initEvent(eventType, true, true);
                                        searchInput.dispatchEvent(evt);
                                    }
                                }
                            });

                            // Remove validation errors from search field
                            const searchFieldRow = searchInput.closest('.form-row, .woocommerce-input-wrapper, p');
                            if (searchFieldRow) {
                                searchFieldRow.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
                                const errorLabel = searchFieldRow.querySelector('.woocommerce-error');
                                if (errorLabel) {
                                    errorLabel.remove();
                                }
                            }

                            // ============================================
                            // CRITICAL: Update the HIDDEN city field
                            // This is what gets saved to the order
                            // ============================================
                            if (cityInput) {
                                console.log('[RK] City selected - updating hidden field:', c.city_name);
                                cityInput.value = c.city_name;
                                cityInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                                
                                // Verify the value was set
                                console.log('[RK] Hidden field value after update:', cityInput.value);
                                
                                // Remove validation errors from city field
                                const cityFieldRow = cityInput.closest('.form-row, .woocommerce-input-wrapper, p');
                                if (cityFieldRow) {
                                    cityFieldRow.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
                                    const errorLabel = cityFieldRow.querySelector('.woocommerce-error');
                                    if (errorLabel) {
                                        errorLabel.remove();
                                    }
                                }
                                
                                // Also remove errors from search field
                                const searchFieldRow = searchInput.closest('.form-row, .woocommerce-input-wrapper, p');
                                if (searchFieldRow) {
                                    searchFieldRow.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
                                    const searchErrorLabel = searchFieldRow.querySelector('.woocommerce-error');
                                    if (searchErrorLabel) {
                                        searchErrorLabel.remove();
                                    }
                                }
                                
                                // Enable submit button now that city is selected
                                const submitButton = document.querySelector('button[name="woocommerce_checkout_place_order"], #place_order');
                                if (submitButton) {
                                    submitButton.disabled = false;
                                    submitButton.removeAttribute('disabled');
                                }
                            } else {
                                console.error('[RK] CRITICAL: cityInput not found when trying to set value!');
                            }

                            // Update classes and required state after city selection
                            updateCityFieldClasses(searchInput, cityInput);
                            updateCityFieldRequired(searchInput, cityInput);

                            // populate region field — supports <select> or text input
                            // Region field is read-only and auto-populated
                            if (regionInput) {
                                const tag = (regionInput.tagName || '').toUpperCase();
                                const regionName = c.region.region_name;

                                console.log('[RK] Updating region field:', regionName);

                                // Make sure region field is read-only
                                if (tag !== 'SELECT') {
                                    regionInput.setAttribute('readonly', 'readonly');
                                    regionInput.style.backgroundColor = '#f5f5f5';
                                    regionInput.style.cursor = 'not-allowed';
                                }

                                if (tag === 'SELECT') {
                                    let matched = Array.from(regionInput.options).find(opt => opt.value === regionName || opt.text === regionName);
                                    if (matched) {
                                        regionInput.value = matched.value;
                                    } else {
                                        // If no matching option, try to add one and select it so the UI reflects the choice
                                        try {
                                            const opt = new Option(regionName, regionName, true, true);
                                            regionInput.add(opt);
                                            regionInput.value = regionName;
                                        } catch (e) {
                                            regionInput.value = regionName;
                                        }
                                    }
                                } else {
                                    regionInput.value = regionName;
                                }

                                // Trigger multiple events to ensure WooCommerce validation recognizes the change
                                ['input', 'change', 'blur'].forEach(eventType => {
                                    try {
                                        regionInput.dispatchEvent(new Event(eventType, { bubbles: true, cancelable: true }));
                                    } catch (e) {
                                        // Fallback for older browsers
                                        if (document.createEvent) {
                                            const evt = document.createEvent('HTMLEvents');
                                            evt.initEvent(eventType, true, true);
                                            regionInput.dispatchEvent(evt);
                                        }
                                    }
                                });

                                // Remove any validation errors from the region field
                                const regionFieldRow = regionInput.closest('.form-row, .woocommerce-input-wrapper, p');
                                if (regionFieldRow) {
                                    regionFieldRow.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
                                    const errorLabel = regionFieldRow.querySelector('.woocommerce-error');
                                    if (errorLabel) {
                                        errorLabel.remove();
                                    }
                                }
                            }

                            // Trigger WooCommerce checkout validation update
                            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.trigger !== 'undefined') {
                                try {
                                    jQuery('body').trigger('update_checkout');
                                } catch (e) {
                                    // Fallback: trigger change on checkout form
                                    const checkoutForm = document.querySelector('form.checkout');
                                    if (checkoutForm) {
                                        checkoutForm.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                }
                            }

                            // show info and delivery days
                            const deliveryDays = (c.region.region_delivery_days && c.region.region_delivery_days.length) ? c.region.region_delivery_days.join(', ') : '';
                            message.textContent = citySelectedMessage;

                            cityInfo.innerHTML = `<strong>${c.city_name}</strong><br>Region: ${c.region.region_name}<br>Delivery Days: ${deliveryDays}`;
                            cityInfo.style.display = 'block';

                            // mark selected and prefer configured payment method
                            try {
                                if ( ADD_BODY_CLASSES ) {
                                    document.body.classList.add('rk-city-selected', 'rk-city-found');
                                    document.body.classList.remove('rk-city-not-found', 'rk-city-not-selected');
                                }
                            } catch (e) {}
                            
                            // Update body classes
                            updateBodyCityClasses(searchInput, cityInput);

                            if ( ENABLE_AUTO_PAYMENT && PAYMENT_FOUND ) {
                                const foundMethod = document.querySelector('input[name="payment_method"][value="' + PAYMENT_FOUND + '"], input#payment_method_' + PAYMENT_FOUND);
                                if ( foundMethod ) {
                                    try {
                                        foundMethod.checked = true;
                                        foundMethod.dispatchEvent(new Event('change'));
                                    } catch (e) {}
                                }
                            }

                            // Prepare allowed days for date picker based on pickup schedule
                            const enabledDays = [];
                            if (c.region.pickup) {
                                Object.entries(c.region.pickup).forEach(([day, val], i) => {
                                    if (val && val.enabled) enabledDays.push(i);
                                });
                            }

                            // ensure fp is initialized (in case it was not when script loaded)
                            if (!fp && dateInput && typeof flatpickr !== 'undefined') {
                                try {
                                    const minDaysAdvance = opts.min_days_advance || 0;
                                    const maxDaysAdvance = opts.max_days_advance || 90;
                                    
                                    const today = new Date();
                                    today.setHours(0, 0, 0, 0);
                                    const minDate = new Date(today);
                                    minDate.setDate(today.getDate() + minDaysAdvance);
                                    const maxDate = new Date(today);
                                    maxDate.setDate(today.getDate() + maxDaysAdvance);
                                    
                                    fp = flatpickr(dateInput, {
                                        dateFormat: DATE_FORMAT,
                                        clickOpens: true,
                                        minDate: minDate,
                                        maxDate: maxDaysAdvance > 0 ? maxDate : null,
                                        disable: [] // Will be set below
                                    });
                                } catch (err) {
                                    console.error('[RK] flatpickr init error (on-demand)', err);
                                    fp = null;
                                }
                            }

                            if (fp && typeof fp.set === 'function') {
                                // Update disable function to only enable pickup days from today onwards
                                try {
                                    // Get min/max days advance from options
                                    const minDaysAdvance = opts.min_days_advance || 0;
                                    const maxDaysAdvance = opts.max_days_advance || 90;
                                    
                                    // Calculate min and max dates
                                    const today = new Date();
                                    today.setHours(0, 0, 0, 0);
                                    const minDate = new Date(today);
                                    minDate.setDate(today.getDate() + minDaysAdvance);
                                    const maxDate = new Date(today);
                                    maxDate.setDate(today.getDate() + maxDaysAdvance);
                                    
                                    fp.set('minDate', minDate); // Set minimum date based on min_days_advance
                                    if (maxDaysAdvance > 0) {
                                        fp.set('maxDate', maxDate); // Set maximum date
                                    }
                                    
                                    // The disable function should only check for pickup days
                                    // minDate and maxDate are already handled by flatpickr's minDate/maxDate
                                    fp.set('disable', [date => {
                                        const dateToCheck = new Date(date);
                                        dateToCheck.setHours(0, 0, 0, 0);
                                        
                                        // If no enabled days specified, allow all dates (minDate/maxDate will handle range)
                                        if (!enabledDays.length) {
                                            return false; // Don't disable - let minDate/maxDate handle it
                                        }
                                        
                                        // Only disable days that are NOT in the enabledDays array
                                        // getDay() returns 0 for Sunday, 1 for Monday, etc.
                                        const dayOfWeek = dateToCheck.getDay();
                                        return !enabledDays.includes(dayOfWeek);
                                    }]);
                                } catch (err) {
                                    console.error('[RK] flatpickr.set error', err);
                                }

                                if (typeof fp.clear === 'function') {
                                    try { fp.clear(); } catch (err) { console.error('[RK] flatpickr.clear error', err); }
                                }

                                if (dateFieldRow) {
                                    dateFieldRow.style.display = '';
                                }

                                // make date required now that a city was selected
                                if (dateInput) {
                                    dateInput.required = true;
                                }
                            } else {
                                // If flatpickr not available, still reveal the date field row
                                if (dateFieldRow) {
                                    dateFieldRow.style.display = '';
                                }
                            }
                        };

                        dropdown.appendChild(div);

                        // Accessibility: close dropdown when clicking outside
                        document.addEventListener('click', function docClick(e) {
                            if (!wrapper.contains(e.target)) {
                                dropdown.classList.remove('visible');
                                dropdown.style.display = 'none';
                                searchInput.setAttribute('aria-expanded', 'false');
                                document.removeEventListener('click', docClick);
                            }
                        });

                    });
                };

                if (!cities.length) {
                    fetchLocations().then(doSearch);
                } else {
                    doSearch();
                }


            });

        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-initialize region fields when WooCommerce updates checkout (AJAX)
    if (typeof jQuery !== 'undefined') {
        jQuery(document.body).on('updated_checkout', function() {
            console.log('[RK] Checkout updated - re-initializing fields');
            
            const regionFields = document.querySelectorAll('[name="billing_rk_region"], #billing_rk_region');
            regionFields.forEach(field => {
                if (field.tagName && field.tagName.toUpperCase() !== 'SELECT') {
                    field.setAttribute('readonly', 'readonly');
                    field.style.backgroundColor = '#f5f5f5';
                    field.style.cursor = 'not-allowed';
                }
            });

            // CRITICAL: Check if hidden city field exists after checkout update
            const hiddenCityField = document.querySelector('[name="billing_rk_city"], #billing_rk_city');
            if (!hiddenCityField) {
                console.error('[RK] CRITICAL: Hidden city field not found after checkout update!');
            } else {
                console.log('[RK] Hidden city field exists, value:', hiddenCityField.value);
            }

            // Re-check and remove required attribute from city search fields if they have values
            const allSearchInputs = document.querySelectorAll('[name="billing_rk_city_search"]');
            allSearchInputs.forEach(searchInput => {
                const cityInput = document.querySelector('[name="billing_rk_city"], #billing_rk_city');
                const searchValue = (searchInput.value || '').trim();
                const cityValue = (cityInput ? cityInput.value || '' : '').trim();
                const hasValue = searchValue.length > 0 || cityValue.length > 0;
                
                console.log('[RK] After checkout update - field values:', { searchValue, cityValue, hasValue });
                
                if (hasValue) {
                    searchInput.removeAttribute('required');
                    // Remove validation errors
                    const fieldRow = searchInput.closest('.form-row, .woocommerce-input-wrapper, p');
                    if (fieldRow) {
                        fieldRow.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
                        const errorLabel = fieldRow.querySelector('.woocommerce-error');
                        if (errorLabel) {
                            errorLabel.remove();
                        }
                    }
                }
            });
        });

        // Intercept checkout validation errors and remove city search field errors if field has value
        jQuery(document.body).on('checkout_error', function() {
            console.log('[RK] Checkout error - checking for field errors');
            
            const allSearchInputs = document.querySelectorAll('[name="billing_rk_city_search"]');
            allSearchInputs.forEach(searchInput => {
                const cityInput = document.querySelector('[name="billing_rk_city"], #billing_rk_city');
                const searchValue = (searchInput.value || '').trim();
                const cityValue = (cityInput ? cityInput.value || '' : '').trim();
                const hasValue = searchValue.length > 0 || cityValue.length > 0;
                
                console.log('[RK] Checkout error - field values:', { searchValue, cityValue, hasValue });
                
                if (hasValue) {
                    // Remove validation errors from this field
                    const fieldRow = searchInput.closest('.form-row, .woocommerce-input-wrapper, p');
                    if (fieldRow) {
                        fieldRow.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
                        const errorLabel = fieldRow.querySelector('.woocommerce-error');
                        if (errorLabel) {
                            // Check if error message is about this field being required
                            const errorText = errorLabel.textContent || '';
                            if (errorText.toLowerCase().includes('city') && errorText.toLowerCase().includes('required')) {
                                console.log('[RK] Removing city required error');
                                errorLabel.remove();
                            }
                        }
                    }
                    
                    // Remove error from notices list
                    const errorNotices = document.querySelectorAll('.woocommerce-error li');
                    errorNotices.forEach(notice => {
                        const noticeText = notice.textContent || '';
                        if (noticeText.toLowerCase().includes('city') && noticeText.toLowerCase().includes('required') && 
                            (noticeText.includes('search') || noticeText.includes('rk_city_search'))) {
                            console.log('[RK] Removing city error from notices');
                            notice.remove();
                        }
                    });
                }
            });
        });
    }

    // Global form submission handler to remove required attribute before validation
    document.addEventListener('DOMContentLoaded', function() {
        const checkoutForm = document.querySelector('form.checkout');
        if (checkoutForm) {
            // Use capture phase to run before WooCommerce validation
            checkoutForm.addEventListener('submit', function(e) {
                console.log('[RK] Form submitting - final check');
                
                const allSearchInputs = document.querySelectorAll('[name="billing_rk_city_search"]');
                allSearchInputs.forEach(searchInput => {
                    const cityInput = document.querySelector('[name="billing_rk_city"], #billing_rk_city');
                    const searchValue = (searchInput.value || '').trim();
                    const cityValue = (cityInput ? cityInput.value || '' : '').trim();
                    const hasValue = searchValue.length > 0 || cityValue.length > 0;
                    
                    console.log('[RK] Form submit - final values:', { 
                        searchValue, 
                        cityValue, 
                        hasValue,
                        hiddenFieldExists: !!cityInput 
                    });
                    
                    if (hasValue) {
                        searchInput.removeAttribute('required');
                        searchInput.setAttribute('data-has-value', 'true');
                    }
                });
            }, true);
        }
    });
})();