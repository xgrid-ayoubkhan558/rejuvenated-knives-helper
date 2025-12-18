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
            const searchSource = document.querySelector('[name="shipping_rk_city_search"], [name="billing_rk_city_search"], [name="rk_city_search"], #rk_city_search');
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

        // Find any search inputs (shipping, billing, or generic unprefixed)
        const searchInputs = document.querySelectorAll('[name="shipping_rk_city_search"], [name="billing_rk_city_search"], [name="rk_city_search"], #rk_city_search');

        searchInputs.forEach(searchInput => {
            // base may be 'shipping_' or 'billing_' or empty string for unprefixed
            const base = (searchInput.name || '').replace(/rk_city_search$/, '');

            const cityInput = document.querySelector('[name="' + base + 'rk_city"]');
            const regionInput = document.querySelector('[name="' + base + 'rk_region"]');
            const dateInput = document.querySelector('[name="' + base + 'rk_pickup_date"]');

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
                    fp = flatpickr(dateInput, {
                        dateFormat: DATE_FORMAT,
                        clickOpens: true,
                        disable: [date => date < new Date().setHours(0, 0, 0, 0)]
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

                console.log('[RK] input:', { q: q, citiesCount: cities.length });

                if (!q) {
                    message.textContent = '';
                    try {
                        if ( ADD_BODY_CLASSES ) {
                            document.body.classList.remove('rk-city-found', 'rk-city-not-found', 'rk-city-selected');
                        }
                    } catch (e) {}
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
                        if (cityInput) {
                            cityInput.value = '';
                            cityInput.dispatchEvent(new Event('change'));
                        }
                        if (regionInput) {
                            regionInput.value = '';
                            regionInput.dispatchEvent(new Event('change'));
                        }
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
                                document.body.classList.add('rk-city-not-found');
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

                            // populate hidden city field and trigger change so checkout updates
                            if (cityInput) {
                                cityInput.value = c.city_name;
                                cityInput.dispatchEvent(new Event('change'));
                            }

                            // populate region field — supports <select> or text input
                            if (regionInput) {
                                const tag = (regionInput.tagName || '').toUpperCase();
                                const regionName = c.region.region_name;

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
                                    regionInput.dispatchEvent(new Event('change'));
                                } else {
                                    regionInput.value = regionName;
                                    regionInput.dispatchEvent(new Event('change'));
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
                                    document.body.classList.remove('rk-city-not-found');
                                }
                            } catch (e) {}

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
                                    fp = flatpickr(dateInput, {
                                        dateFormat: DATE_FORMAT,
                                        clickOpens: true,
                                        disable: [date => date < new Date().setHours(0, 0, 0, 0)]
                                    });
                                } catch (err) {
                                    console.error('[RK] flatpickr init error (on-demand)', err);
                                    fp = null;
                                }
                            }

                            if (fp && typeof fp.set === 'function') {
                                // set disable function
                                try {
                                    fp.set('disable', [date => {
                                        const today = new Date().setHours(0, 0, 0, 0);
                                        if (date < today) return true;
                                        if (!enabledDays.length) return false; // no restriction
                                        return !enabledDays.includes(date.getDay());
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
})();