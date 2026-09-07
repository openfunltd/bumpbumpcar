<?= $this->partial('common/header') ?>

<form method="get" id="search-form">
    經度(x): <input type="text" name="x" id="input-x" value="<?= $this->escape($this->x) ?>">
    緯度(y): <input type="text" name="y" id="input-y" value="<?= $this->escape($this->y) ?>">
    <button type="submit">搜尋</button>
    <button type="button" id="use-my-location">使用我現在的位置</button>
</form>
<p id="geo-error" style="color: red;"></p>

<script>
document.getElementById('use-my-location').addEventListener('click', function () {
    var errorEl = document.getElementById('geo-error');
    errorEl.textContent = '';

    if (!navigator.geolocation) {
        errorEl.textContent = '這個瀏覽器不支援定位功能';
        return;
    }

    navigator.geolocation.getCurrentPosition(function (position) {
        document.getElementById('input-x').value = position.coords.longitude;
        document.getElementById('input-y').value = position.coords.latitude;
        document.getElementById('search-form').submit();
    }, function (error) {
        if (error.code === error.PERMISSION_DENIED) {
            errorEl.textContent = '請允許瀏覽器存取你的位置';
        } else {
            errorEl.textContent = '無法取得目前位置，請稍後再試';
        }
    });
});
</script>

<div>
<?php if (is_array($this->results)): ?>
    <p>方圓 1km 內共找到 <?= count($this->results) ?> 場事故（花了 <?= $this->escape($this->elapsed_seconds) ?> 秒）</p>
    <div id="map" style="height: 500px;"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
    var map = L.map('map').setView([<?= (float) $this->y ?>, <?= (float) $this->x ?>], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var markers = L.markerClusterGroup();
    var accidents = <?= json_encode($this->results, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    accidents.forEach(function (row) {
        var marker = L.marker([row.lat, row.lon]);
        marker.bindPopup(row.category + ' - ' + row.distance_km + 'km');
        markers.addLayer(marker);
    });

    map.addLayer(markers);
    </script>
<?php endif ?>
</div>

<?= $this->partial('common/footer') ?>
