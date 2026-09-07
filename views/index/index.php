<?= $this->partial('common/header') ?>

<form method="get">
    經度(x): <input type="text" name="x" value="<?= $this->escape($this->x) ?>">
    緯度(y): <input type="text" name="y" value="<?= $this->escape($this->y) ?>">
    <button type="submit">搜尋</button>
</form>

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
