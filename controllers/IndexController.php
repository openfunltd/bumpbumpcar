<?php

class IndexController extends MiniEngine_Controller
{
    const SEARCH_RADIUS_KM = 1;
    const EARTH_RADIUS_KM = 6371.0;

    public function indexAction()
    {
        $this->view->app_name = getenv('APP_NAME');

        $x = $_GET['x'] ?? ''; // 經度
        $y = $_GET['y'] ?? ''; // 緯度
        $this->view->x = $x;
        $this->view->y = $y;

        if ($x === '' || $y === '' || !is_numeric($x) || !is_numeric($y)) {
            return;
        }

        $start = microtime(true);
        $results = $this->searchAccidents((float) $x, (float) $y, self::SEARCH_RADIUS_KM);
        $this->view->elapsed_seconds = round(microtime(true) - $start, 3);
        $this->view->results = $results;
        $this->view->a1_list = array_values(array_filter($results, fn($row) => $row['category'] === 'A1'));
        $this->view->a1_count = count($this->view->a1_list);
        $this->view->a2_count = count(array_filter($results, fn($row) => $row['category'] === 'A2'));
    }

    protected function searchAccidents($x, $y, $z)
    {
        $pdo = MiniEngine::getDb();

        // 用經緯度換算出方圓 z 公里的外接方框，先靠 rtree 篩出候選範圍
        $latDelta = $z / 111.32;
        $lonDelta = $z / (111.32 * cos(deg2rad($y)));

        // id 格式為 {uuid}-{當事者順位}，同一場事故的多位當事者共用同一組 uuid（前 36 碼），
        // 用 GROUP BY 讓每場事故只輸出一列
        $stmt = $pdo->prepare('
            SELECT substr(a.id, 1, 36) AS uuid, a."經度" AS lon, a."緯度" AS lat, a."事故類別名稱" AS category,
                   a."發生地點" AS location, a."發生日期" AS date, a."發生時間" AS time, a."死亡受傷人數" AS casualties,
                   a."事故類型及型態大類別名稱" AS collision_type, a."事故類型及型態子類別名稱" AS collision_subtype,
                   a."肇因研判大類別名稱-主要" AS cause, a."肇因研判子類別名稱-主要" AS cause_detail,
                   group_concat(DISTINCT a."當事者區分-類別-大類別名稱-車種") AS vehicle_types
            FROM accidents_rtree r
            JOIN accidents a ON a.rowid = r.id
            WHERE r.min_lon <= :maxLon AND r.max_lon >= :minLon
              AND r.min_lat <= :maxLat AND r.max_lat >= :minLat
              AND a."發生年度" = :year
            GROUP BY uuid
        ');
        $stmt->execute([
            'minLon' => $x - $lonDelta,
            'maxLon' => $x + $lonDelta,
            'minLat' => $y - $latDelta,
            'maxLat' => $y + $latDelta,
            'year' => (int) date('Y'),
        ]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $distance = $this->haversineKm($y, $x, (float) $row['lat'], (float) $row['lon']);
            if ($distance <= $z) {
                $row['distance_km'] = round($distance, 3);
                $row['date'] = substr($row['date'], 0, 4) . '-' . substr($row['date'], 4, 2) . '-' . substr($row['date'], 6, 2);
                $row['time'] = substr($row['time'], 0, 2) . ':' . substr($row['time'], 2, 2);
                $results[] = $row;
            }
        }

        usort($results, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return $results;
    }

    protected function haversineKm($lat1, $lon1, $lat2, $lon2)
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function robotsAction()
    {
        header('Content-Type: text/plain');
        echo "#\n";
        return $this->noview();
    }
}
