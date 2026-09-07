<?php

// 用法: php script/search.php <經度x> <緯度y> <半徑z公里>
// 例如: php script/search.php 121.433604 25.174412 1

if ($argc < 4) {
    fwrite(STDERR, "用法: php script/search.php <經度x> <緯度y> <半徑z公里>\n");
    exit(1);
}

$x = (float) $argv[1]; // 經度
$y = (float) $argv[2]; // 緯度
$z = (float) $argv[3]; // 公里

$dbPath = __DIR__ . '/../data/bumpbumpcar.sqlite';
if (!is_file($dbPath)) {
    fwrite(STDERR, "找不到資料庫: $dbPath\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const EARTH_RADIUS_KM = 6371.0;

function haversineKm($lat1, $lon1, $lat2, $lon2)
{
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$start = microtime(true);

// 用經緯度換算出方圓 z 公里的外接方框，先靠 rtree 篩出候選範圍
$latDelta = $z / 111.32;
$lonDelta = $z / (111.32 * cos(deg2rad($y)));

$stmt = $pdo->prepare('
    SELECT a.id, a."經度", a."緯度", a."發生日期", a."發生地點"
    FROM accidents_rtree r
    JOIN accidents a ON a.id = r.id
    WHERE r.min_lon <= :maxLon AND r.max_lon >= :minLon
      AND r.min_lat <= :maxLat AND r.max_lat >= :minLat
');
$stmt->execute([
    'minLon' => $x - $lonDelta,
    'maxLon' => $x + $lonDelta,
    'minLat' => $y - $latDelta,
    'maxLat' => $y + $latDelta,
]);

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $distance = haversineKm($y, $x, (float) $row['緯度'], (float) $row['經度']);
    if ($distance <= $z) {
        $row['距離_km'] = round($distance, 3);
        $results[] = $row;
    }
}

usort($results, fn($a, $b) => $a['距離_km'] <=> $b['距離_km']);

$elapsed = microtime(true) - $start;

foreach ($results as $row) {
    echo "[{$row['距離_km']}km] {$row['發生日期']} {$row['發生地點']}\n";
}

echo "\n共找到 " . count($results) . " 筆事故（座標 $x, $y 方圓 {$z}km 內）\n";
echo "查詢時間: " . round($elapsed * 1000, 1) . " ms\n";
