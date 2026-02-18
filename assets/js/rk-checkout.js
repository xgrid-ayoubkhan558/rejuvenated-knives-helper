(function () {
    'use strict';

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
            mailIn: opts.mailin_message || "Outside coverage area. Mail-in available.",
            cityFound: opts.city_found_message || "Within service area.",
            citySelected: opts.city_selected_message || "Service area selected."
        }
    };

    console.log('[RK Debug] Date format from backend:', opts.date_format);
    console.log('[RK Debug] Date format being used:', config.dateFormat);

    let regionsData = [];
    let cities = [];
    let hasUserInteracted = false;

    const trigger = (el, evt) => {
        if (!el) return;
        el.dispatchEvent(new Event(evt, { bubbles: true, cancelable: true }));
        if (evt === 'change' && el.name === 'payment_method') el.click();
    };

    function buildCitiesList() {
        cities = [];
        regionsData.forEach(region => {
            (region.cities || []).forEach(city => {
                cities.push({ ...city, region });
            });
        });
    }

    function loadData() {
        const holder = document.getElementById('rk-check-fields-data');
        if (holder?.dataset?.regions) {
            try {
                regionsData = JSON.parse(holder.dataset.regions);
                buildCitiesList();
            } catch (e) {
                console.error('[RK] Region data error', e);
            }
        }
    }

    function updateBodyClass(hasCity, state = null) {
        if (!config.addBodyClasses) return;

        document.body.classList.remove(
            'rk-city-selected',
            'rk-city-not-selected',
            'rk-city-found',
            'rk-city-not-found'
        );

        document.body.classList.add(hasCity ? 'rk-city-selected' : 'rk-city-not-selected');
        if (state) document.body.classList.add(`rk-city-${state}`);
    }

    function selectPaymentMethod(id) {
        const method = document.querySelector(
            `input[name="payment_method"][value="${id}"]`
        );
        if (method) {
            method.checked = true;
            method.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function initDatePicker(input, region) {
        if (!input || typeof flatpickr === 'undefined') return null;

        const enabledDays = [];
        if (region.pickup) {
            Object.values(region.pickup).forEach((day, i) => {
                if (day?.enabled) enabledDays.push(i);
            });
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        console.log('[RK Debug] Initializing Flatpickr with dateFormat:', config.dateFormat);

        return flatpickr(input, {
            // dateFormat: config.dateFormat,
            dateFormat: "m-d-Y",
            allowInput: false,
            minDate: new Date(today.getTime() + config.minDaysAdvance * 86400000),
            maxDate: config.maxDaysAdvance
                ? new Date(today.getTime() + config.maxDaysAdvance * 86400000)
                : null,
            disable: enabledDays.length
                ? [date => !enabledDays.includes(date.getDay())]
                : []
        });
    }

    function initCitySearch() {
        const searchInput = document.querySelector('#billing_rk_city_search');
        const cityInput = document.querySelector('#billing_rk_city');
        const regionInput = document.querySelector('#billing_rk_region');
        const dateInput = document.querySelector('#billing_rk_pickup_date');
        const serviceTypeInput = document.querySelector('#billing_rk_service_type');

        if (!searchInput || !cityInput) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'rk-city-wrapper';
        searchInput.after(wrapper);

        const dropdown = document.createElement('div');
        dropdown.className = 'rk-city-dropdown';
        dropdown.style.display = 'none';
        wrapper.appendChild(dropdown);

        const message = document.createElement('div');
        message.className = 'rk-message';
        wrapper.appendChild(message);

        let datePicker = null;
        const dateRow = dateInput?.closest('.form-row');

        function selectCity(city, updateSearchInput = true) {
            if (!city) return;

            if (updateSearchInput) {
                searchInput.value = `${city.city_name} `;
            }
            cityInput.value = city.city_name;
            // REMOVED: Auto-population of region field
            // if (regionInput) regionInput.value = city.region.region_name;

            trigger(cityInput, 'change');
            dropdown.style.display = 'none';
            message.textContent = config.messages.citySelected;

            updateBodyClass(true, 'selected');

            if (serviceTypeInput) serviceTypeInput.value = 'door-to-door';

            if (config.enableAutoPayment) {
                selectPaymentMethod(config.paymentFound);
            }

            if (dateInput) {
                if (datePicker) datePicker.destroy();
                datePicker = initDatePicker(dateInput, city.region);
                if (dateRow) dateRow.style.display = '';
            }
        }

        searchInput.addEventListener('input', function () {
            hasUserInteracted = true;

            const q = this.value.toLowerCase().trim();
            dropdown.innerHTML = '';

            // Always sync hidden city field with search input as a baseline
            cityInput.value = this.value.trim();

            // Auto-select if exact match (and only one such city exists)
            const exactMatches = cities.filter(c => c.city_name.toLowerCase() === q);
            if (exactMatches.length === 1) {
                selectCity(exactMatches[0], false);
                return;
            }

            if (!q) {
                dropdown.style.display = 'none';
                message.textContent = '';
                updateBodyClass(false, null);

                cityInput.value = '';
                // REMOVED: Clearing region field
                // if (regionInput) regionInput.value = '';
                if (dateRow) dateRow.style.display = 'none';
                if (serviceTypeInput) serviceTypeInput.value = 'mail-in';
                return;
            }

            const matches = cities.filter(c =>
                `${c.city_name} ${c.region.region_name}`.toLowerCase().includes(q)
            );

            if (!matches.length) {
                dropdown.style.display = 'none';
                message.textContent = config.messages.mailIn;
                updateBodyClass(false, 'not-found');

                // REMOVED: Clearing region field
                // if (regionInput) regionInput.value = '';
                if (config.enableAutoPayment) {
                    selectPaymentMethod(config.paymentNotFound);
                }
                if (dateRow) dateRow.style.display = 'none';
                if (serviceTypeInput) serviceTypeInput.value = 'mail-in';
                return;
            }

            message.textContent = config.messages.cityFound;
            updateBodyClass(false, 'found');
            dropdown.style.display = 'block';

            if (serviceTypeInput) serviceTypeInput.value = 'door-to-door';

            matches.forEach(city => {
                const opt = document.createElement('div');
                opt.className = 'rk-city-option';
                opt.textContent = `${city.city_name} `;
                // opt.textContent = `${city.city_name} (${city.region.region_name})`;
                opt.onclick = () => selectCity(city);
                dropdown.appendChild(opt);
            });
        });

        searchInput.addEventListener('blur', () => {
            setTimeout(() => (dropdown.style.display = 'none'), 200);
        });
        console.log('[RK] City input on init:', cityInput.value);

        const checkInitialState = () => {
            console.log('[RK] checkInitialState fired');
            console.log('[RK] searchInput value:', searchInput.value);

            if (hasUserInteracted) return;

            const searchVal = searchInput.value?.trim();

            if (!searchVal) {
                updateBodyClass(false, null);
                return;
            }

            // Try to match using city name inside searchInput
            const match = cities.find(c => {
                const cityName = c.city_name.toLowerCase();
                const sVal = searchVal.toLowerCase();
                return sVal === cityName || sVal.startsWith(cityName + ',') || sVal.startsWith(cityName + ' ');
            });

            if (match) {
                console.log('[RK] City FOUND on load/autofill:', match.city_name);
                selectCity(match, false);
            } else {
                console.log('[RK] City NOT FOUND on load');

                message.textContent = config.messages.mailIn;
                updateBodyClass(false, 'not-found');

                // Sync hidden city field with search field value even if not found
                cityInput.value = searchVal;

                if (config.enableAutoPayment) {
                    selectPaymentMethod(config.paymentNotFound);
                }

                if (serviceTypeInput) serviceTypeInput.value = 'mail-in';
            }
        };



        searchInput.addEventListener('change', checkInitialState);

        setTimeout(checkInitialState, 500);
        setTimeout(checkInitialState, 2000); // Second check for slow autofill

        document.body.addEventListener('updated_checkout', () => {
            console.log('[RK] updated_checkout event fired');
            setTimeout(checkInitialState, 300);
        });

    }

    function init() {
        loadData();
        if (regionsData.length) initCitySearch();
    }

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init)
        : init();

})();