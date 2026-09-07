<?php

// 用法: php dump.php <csv路徑> <sqlite路徑>
// 例如: php dump.php data/raw/交通事故資料.csv data/bumpbumpcar.sqlite

if ($argc < 3) {
    fwrite(STDERR, "用法: php dump.php <csv路徑> <sqlite路徑>\n");
    exit(1);
}

$csvPath = $argv[1];
$dbPath = $argv[2];
$batchSize = 5000;

if (!is_file($csvPath)) {
    fwrite(STDERR, "找不到 CSV 檔案: $csvPath\n");
    exit(1);
}

$isNewDb = !is_file($dbPath);
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec('PRAGMA synchronous = OFF');

if ($isNewDb) {
    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    echo "已建立資料庫: $dbPath\n";
}

$totalLines = (int) exec('wc -l < ' . escapeshellarg($csvPath));
$totalRows = max($totalLines - 1, 0);

$fh = fopen($csvPath, 'r');
if ($fh === false) {
    fwrite(STDERR, "無法開啟 CSV 檔案: $csvPath\n");
    exit(1);
}

$header = fgetcsv($fh);
if ($header === false) {
    fwrite(STDERR, "CSV 檔案是空的\n");
    exit(1);
}

$categoryIndex = array_search('事故類別名稱', $header);

// CSV 的 "id" 欄位對應到資料表的 source_id，其餘欄名照抄
$columns = array_map(fn($name) => $name === 'id' ? 'source_id' : $name, $header);
$columnList = implode(', ', array_map(fn($name) => '"' . $name . '"', $columns));
$placeholders = implode(', ', array_fill(0, count($columns), '?'));
$stmt = $pdo->prepare("INSERT INTO accidents ($columnList) VALUES ($placeholders)");

$count = 0;
$skipped = 0;
$pdo->beginTransaction();

while (($row = fgetcsv($fh)) !== false) {
    if (count($row) !== count($columns)) {
        fwrite(STDERR, "跳過欄位數不符的資料列（第 " . ($count + $skipped + 2) . " 行）\n");
        $skipped++;
        continue;
    }

    // A3（僅財損）事故沒有經緯度資料，排除不匯入
    if ($categoryIndex !== false && $row[$categoryIndex] === 'A3') {
        $skipped++;
        continue;
    }

    $stmt->execute($row);
    $count++;

    if ($count % $batchSize === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        $processed = $count + $skipped;
        $percent = $totalRows > 0 ? round($processed / $totalRows * 100, 1) : 0;
        echo "已匯入 $count 筆（跳過 $skipped 筆，進度 {$percent}%）\n";
    }
}

$pdo->commit();
fclose($fh);

echo "完成，共匯入 $count 筆資料到 {$dbPath}（跳過 $skipped 筆）\n";
