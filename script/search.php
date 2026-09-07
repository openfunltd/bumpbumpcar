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

// id 格式為 {uuid}-{當事者順位}，同一場事故的多位當事者共用同一組 uuid（前 36 碼），
// 用 GROUP BY 讓每場事故只輸出一列（同一場事故的經緯度、事故分類皆相同，取任一列即可）
$stmt = $pdo->prepare('
    SELECT substr(a.id, 1, 36) AS uuid, a."經度" AS lon, a."緯度" AS lat, a."事故類別名稱" AS category
    FROM accidents_rtree r
    JOIN accidents a ON a.rowid = r.id
    WHERE r.min_lon <= :maxLon AND r.max_lon >= :minLon
      AND r.min_lat <= :maxLat AND r.max_lat >= :minLat
    GROUP BY uuid
');
$stmt->execute([
    'minLon' => $x - $lonDelta,
    'maxLon' => $x + $lonDelta,
    'minLat' => $y - $latDelta,
    'maxLat' => $y + $latDelta,
]);

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $distance = haversineKm($y, $x, (float) $row['lat'], (float) $row['lon']);
    if ($distance <= $z) {
        $row['距離_km'] = round($distance, 3);
        $results[] = $row;
    }
}

usort($results, fn($a, $b) => $a['距離_km'] <=> $b['距離_km']);

$elapsed = microtime(true) - $start;

foreach ($results as $row) {
    echo "{$row['uuid']}\t{$row['lon']}\t{$row['lat']}\t{$row['category']}\n";
}

echo "\n共找到 " . count($results) . " 筆事故（座標 $x, $y 方圓 {$z}km 內）\n";
echo "查詢時間: " . round($elapsed * 1000, 1) . " ms\n";
