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

$header = fgetcsv($fh, 0, ',', '"', '');
if ($header === false) {
    fwrite(STDERR, "CSV 檔案是空的\n");
    exit(1);
}

$categoryIndex = array_search('事故類別名稱', $header);
$idIndex = array_search('id', $header);
$seqIndex = array_search('當事者順位', $header);

$columns = $header;
$columnList = implode(', ', array_map(fn($name) => '"' . $name . '"', $columns));
$placeholders = implode(', ', array_fill(0, count($columns), '?'));
$stmt = $pdo->prepare("INSERT INTO accidents ($columnList) VALUES ($placeholders)");

function uuidv4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$count = 0;
$skipped = 0;
$currentUuid = null;
$seenInGroup = [];
$pdo->beginTransaction();

while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
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

    // 同一起事故的當事者會連續出現，同一組內共用一個 UUID、順位當尾碼區分。
    // 部分舊資料（如 102年交通事故資料.csv）完全沒有順位欄位（空字串），
    // 也有極少數事故的順位在同一組內重複，兩者都會讓「順位=1 才換組」的判斷失效並撞號，
    // 所以改用「這個順位在目前這組已出現過」來偵測該換一組新的 UUID，確保 id 一定不重複
    if ($idIndex !== false && $seqIndex !== false) {
        $seq = $row[$seqIndex] === '' ? 1 : (int) $row[$seqIndex];
        if ($currentUuid === null || $seq === 1 || isset($seenInGroup[$seq])) {
            $currentUuid = uuidv4();
            $seenInGroup = [];
        }
        $seenInGroup[$seq] = true;
        $row[$idIndex] = $currentUuid . '-' . $seq;
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
