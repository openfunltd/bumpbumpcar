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
    <p>方圓 1km 內共找到 <?= count($this->results) ?> 場事故</p>
    <ul>
    <?php foreach ($this->results as $row): ?>
        <li>
            <?= $this->escape($row['uuid']) ?>
            - 經度: <?= $this->escape($row['lon']) ?>
            緯度: <?= $this->escape($row['lat']) ?>
            分類: <?= $this->escape($row['category']) ?>
            (<?= $this->escape($row['distance_km']) ?>km)
        </li>
    <?php endforeach ?>
    </ul>
<?php endif ?>
</div>

<?= $this->partial('common/footer') ?>
