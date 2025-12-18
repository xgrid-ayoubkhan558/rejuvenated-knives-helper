(function(){
    'use strict';

    function init() {
        if ( typeof rk_check_fields_data === 'undefined' ) {
            return;
        }

        let data = [];

        // Prefer reading locations JSON from the hidden checkout container
        const dataHolder = document.getElementById('rk-check-fields-data');
        if ( dataHolder && dataHolder.dataset && dataHolder.dataset.regions ) {
            try {
                data = JSON.parse( dataHolder.dataset.regions );
            } catch ( e ) {
                // fallback to localized var
                data = ( typeof rk_check_fields_data !== 'undefined' ) ? rk_check_fields_data : [];
            }
        } else {
            // fallback: look for the attribute on the search field
            const searchSource = document.querySelector('[name="shipping_rk_city_search"], [name="billing_rk_city_search"]');
            if ( searchSource && searchSource.dataset && searchSource.dataset.regions ) {
                try {
                    data = JSON.parse( searchSource.dataset.regions );
                } catch ( e ) {
                    data = ( typeof rk_check_fields_data !== 'undefined' ) ? rk_check_fields_data : [];
                }
            } else {
                data = ( typeof rk_check_fields_data !== 'undefined' ) ? rk_check_fields_data : [];
            }
        }

        // Build flat list of cities with region attached
        const cities = [];
        data.forEach(region => {
            (region.cities || []).forEach(city => {
                cities.push( Object.assign({}, city, { region: region }) );
            });
        });

        const mailInMessage = "Good news!\n\nWhile your location is outside our door-to-door coverage area, you can mail in your items using our mail-in service.";

        const sections = [ 'shipping', 'billing' ];

        sections.forEach(prefix => {
            const searchInput = document.querySelector('[name="' + prefix + '_rk_city_search"]');
            if ( ! searchInput ) return;

            const cityInput = document.querySelector('[name="' + prefix + '_rk_city"]');
            const regionInput = document.querySelector('[name="' + prefix + '_rk_region"]');
            const dateInput = document.querySelector('[name="' + prefix + '_rk_pickup_date"]');

            // Create UI elements: dropdown, message, selected info, date wrapper
            const wrapper = document.createElement('div');
            const dropdown = document.createElement('div');
            const message = document.createElement('div');
            const cityInfo = document.createElement('div');
            const dateWrapper = document.createElement('div');

            // Use CSS classes for layout and styling
            dropdown.className = 'rk-city-dropdown';
            message.className = 'rk-message';
            cityInfo.className = 'rk-city-info';
            dateWrapper.className = 'rk-date-wrapper';
            wrapper.classList.add('rk-city-wrapper');

            // Keep date wrapper hidden initially
            dateWrapper.style.display = 'none';
            dropdown.classList.remove('visible');

            // insert after the search input
            searchInput.parentNode.insertBefore(wrapper, searchInput.nextSibling);
            wrapper.appendChild(dropdown);
            wrapper.appendChild(message);
            wrapper.appendChild(cityInfo);
            wrapper.appendChild(dateWrapper);

            // attach flatpickr instance when needed
            let fp = null;
            if ( dateInput && typeof flatpickr !== 'undefined' ) {
                fp = flatpickr( dateInput, {
                    dateFormat: 'd-m-Y',
                    clickOpens: true,
                    disable: [ date => date < new Date().setHours(0,0,0,0) ]
                });
            }

            // Find the visible row/container for the date input so we can show/hide it
            let dateFieldRow = null;
            if ( dateInput ) {
                dateFieldRow = dateInput.closest('.form-row') || dateInput.closest('p') || dateInput.parentNode;
                if ( dateFieldRow ) {
                    // hide initially
                    dateFieldRow.style.display = 'none';
                }
            }

            // Clear UI helper
            function clearUi() {
                dropdown.innerHTML = '';
                dropdown.classList.remove('visible');
                message.textContent = '';
                cityInfo.innerHTML = '';
                if ( fp ) {
                    fp.clear();
                }
                if ( dateFieldRow ) dateFieldRow.style.display = 'none';
                if ( dateInput ) {
                    dateInput.required = false;
                }
                if ( searchInput ) {
                    searchInput.setAttribute('aria-expanded', 'false');
                }
            }

            searchInput.addEventListener('input', function() {
                const q = searchInput.value.toLowerCase().trim();
                dropdown.innerHTML = '';
                dropdown.style.display = 'none';
                cityInfo.innerHTML = '';
                if ( fp ) fp.clear();
                if ( dateWrapper ) dateWrapper.style.display = 'none';

                if ( ! q ) {
                    message.textContent = '';
                    return;
                }

                const matches = cities.filter(c => (`${c.city_name} ${c.region.region_name}`).toLowerCase().includes(q));

                if ( ! matches.length ) {
                    dropdown.classList.remove('visible');
                    message.textContent = mailInMessage;
                    if ( cityInput ) {
                        cityInput.value = '';
                        cityInput.dispatchEvent(new Event('change'));
                    }
                    if ( regionInput ) {
                        regionInput.value = '';
                        regionInput.dispatchEvent(new Event('change'));
                    }
                    if ( fp ) {
                        fp.clear();
                    }
                    if ( dateFieldRow ) {
                        dateFieldRow.style.display = 'none';
                    }
                    if ( dateInput ) {
                        dateInput.required = false;
                    }
                    searchInput.setAttribute('aria-expanded', 'false');
                    return;
                }

                message.textContent = "Hooray! You're within our door-to-door service area.";

                dropdown.classList.add('visible');
                searchInput.setAttribute('aria-expanded', 'true');

                matches.forEach(c => {
                    const div = document.createElement('div');
                    div.className = 'rk-city-option';
                    div.textContent = `${c.city_name} (${c.region.region_name})`;

                    div.onclick = () => {
                        dropdown.style.display = 'none';
                        searchInput.value = c.city_name;

                        // populate fields
                        if ( cityInput ) {
                            cityInput.value = c.city_name;
                            // trigger change event so checkout updates
                            cityInput.dispatchEvent(new Event('change'));
                        }

                        if ( regionInput ) {
                            regionInput.value = c.region.region_name;
                            regionInput.dispatchEvent(new Event('change'));
                        }

                        // show info
                        const deliveryDays = (c.region.region_delivery_days && c.region.region_delivery_days.length) ? c.region.region_delivery_days.join(', ') : '';
                        message.textContent = "Hooray! You're within our door-to-door service area.";
                        cityInfo.innerHTML = `<strong>${c.city_name}</strong><br>Region: ${c.region.region_name}<br>Delivery Days: ${deliveryDays}`;

                        // Prepare allowed days for date picker based on pickup schedule
                        const enabledDays = [];
                        if ( c.region.pickup ) {
                            Object.entries( c.region.pickup ).forEach(([day,val], i) => {
                                if ( val && val.enabled ) enabledDays.push(i);
                            });
                        }

                        if ( fp ) {
                            // set disable function
                            fp.set('disable', [ date => {
                                const today = new Date().setHours(0,0,0,0);
                                if ( date < today ) return true;
                                if ( ! enabledDays.length ) return false; // no restriction
                                return ! enabledDays.includes( date.getDay() );
                            }]);

                            fp.clear();
                            if ( dateFieldRow ) {
                                dateFieldRow.style.display = '';
                            }

                            // make date required now that a city was selected
                            if ( dateInput ) {
                                dateInput.required = true;
                            }
                        }
                    };

                    dropdown.appendChild(div);

                    // Accessibility: close dropdown when clicking outside
                    document.addEventListener('click', function docClick(e){
                        if ( ! wrapper.contains(e.target) ) {
                            dropdown.classList.remove('visible');
                            searchInput.setAttribute('aria-expanded', 'false');
                            document.removeEventListener('click', docClick);
                        }
                    });
                });

            });

        });
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();