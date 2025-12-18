<?php
$regions = get_terms([
    'taxonomy'   => 'locations',
    'hide_empty' => false,
    'parent'     => 0,
]);

$data = [];

foreach ($regions as $region) {

    $regionData = [
        "region_name" => $region->name,
        "region_id"   => get_field('region_id', $region),
        "region_delivery_days" => get_field('region_delivery_days', $region),
        "pickup" => [],
        "cities" => []
    ];

    $days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
    foreach ($days as $day) {
        $regionData['pickup'][$day] = [
            "enabled" => get_field("region_pickup_{$day}_enabled", $region),
            "start"   => get_field("region_pickup_{$day}_start_time", $region),
            "end"     => get_field("region_pickup_{$day}_end_time", $region),
        ];
    }

    $cities = get_terms([
        'taxonomy'   => 'locations',
        'hide_empty' => false,
        'parent'     => $region->term_id,
    ]);

    foreach ($cities as $city) {
        $regionData['cities'][] = [
            "city_name" => $city->name,
            "city_id"   => $city->term_id,
        ];
    }

    $data[] = $regionData;
}
?>

<div class="location-search">
    <input type="text" id="locationInput" placeholder="Search your city">

    <div id="cityDropdown" class="city-dropdown"></div>

    <div id="message" class="message"></div>

    <div id="selectedCityData"></div>

    <div id="dateWrapper" style="display:none;">
        <input type="text" id="datePicker" placeholder="Select pickup date" readonly>
    </div>

    <div id="regionCityData"
         data-regions='<?= htmlspecialchars(json_encode($data), ENT_QUOTES, "UTF-8"); ?>'
         style="display:none;"></div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>



<script>
document.addEventListener("DOMContentLoaded", () => {

    const input = document.getElementById("locationInput");
    const dropdown = document.getElementById("cityDropdown");
    const message = document.getElementById("message");
    const cityInfo = document.getElementById("selectedCityData");
    const dateWrapper = document.getElementById("dateWrapper");
    const data = JSON.parse(document.getElementById("regionCityData").dataset.regions);

    const cities = [];
    data.forEach(region => {
        region.cities.forEach(city => {
            cities.push({...city, region});
        });
    });

    const mailInMessage =
        "Good news!\n\n" +
        "While your location is outside our door-to-door coverage area, " +
        "you can mail in your knives using our premium mail-in service.\n\n" +
        "We’ll send you a complete mailing kit and sharpen them to perfection.";

    const fp = flatpickr("#datePicker", {
        dateFormat: "d-m-Y",
        clickOpens: true,
        disable: [date => date < new Date().setHours(0,0,0,0)]
    });

    input.addEventListener("input", () => {
        const q = input.value.toLowerCase().trim();
        dropdown.innerHTML = "";
        dropdown.style.display = "none";
        cityInfo.innerHTML = "";
        dateWrapper.style.display = "none";

        if (!q) {
            message.textContent = "";
            return;
        }

        const matches = cities.filter(c =>
            `${c.city_name} ${c.region.region_name}`.toLowerCase().includes(q)
        );

        if (!matches.length) {
            message.textContent = mailInMessage;
            return;
        }

        message.textContent =
            "Hooray!\nYou're within our door-to-door service area.";

        dropdown.style.display = "block";

        matches.forEach(c => {
            const div = document.createElement("div");
            div.className = "city-option";
            div.textContent = `${c.city_name} (${c.region.region_name})`;

            div.onclick = () => {
                dropdown.style.display = "none";
                input.value = c.city_name;

                cityInfo.innerHTML = `
                    <strong>${c.city_name}</strong><br>
                    Region: ${c.region.region_name}<br>
                    Delivery Days: ${c.region.region_delivery_days.join(", ")}
                `;

                const enabledDays = [];
                Object.entries(c.region.pickup).forEach(([day,val],i)=>{
                    if(val.enabled) enabledDays.push(i);
                });

                fp.set("disable", [
                    date => {
                        const today = new Date().setHours(0,0,0,0);
                        return date < today || !enabledDays.includes(date.getDay());
                    }
                ]);

                fp.clear();
                dateWrapper.style.display = "block";
            };

            dropdown.appendChild(div);
        });
    });

});
</script>
