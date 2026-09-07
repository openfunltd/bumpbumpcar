-- 交通事故資料 schema
-- 資料來源: 政府資料開放平臺「交通事故資料」CSV，每列為一起事故的一位當事者

CREATE TABLE accidents (
    id INTEGER PRIMARY KEY,
    source_id TEXT,
    "發生年度" INTEGER,
    "發生月份" INTEGER,
    "發生日期" TEXT,
    "發生時間" TEXT,
    "事故類別名稱" TEXT,
    "處理單位名稱警局層" TEXT,
    "發生地點" TEXT,
    "天候名稱" TEXT,
    "光線名稱" TEXT,
    "道路類別-第1當事者-名稱" TEXT,
    "速限-第1當事者" INTEGER,
    "道路型態大類別名稱" TEXT,
    "道路型態子類別名稱" TEXT,
    "事故位置大類別名稱" TEXT,
    "事故位置子類別名稱" TEXT,
    "路面狀況-路面鋪裝名稱" TEXT,
    "路面狀況-路面狀態名稱" TEXT,
    "路面狀況-路面缺陷名稱" TEXT,
    "道路障礙-障礙物名稱" TEXT,
    "道路障礙-視距品質名稱" TEXT,
    "道路障礙-視距名稱" TEXT,
    "號誌-號誌種類名稱" TEXT,
    "號誌-號誌動作名稱" TEXT,
    "車道劃分設施-分向設施大類別名稱" TEXT,
    "車道劃分設施-分向設施子類別名稱" TEXT,
    "車道劃分設施-分道設施-快車道或一般車道間名稱" TEXT,
    "車道劃分設施-分道設施-快慢車道間名稱" TEXT,
    "車道劃分設施-分道設施-路面邊線名稱" TEXT,
    "事故類型及型態大類別名稱" TEXT,
    "事故類型及型態子類別名稱" TEXT,
    "肇因研判大類別名稱-主要" TEXT,
    "肇因研判子類別名稱-主要" TEXT,
    "死亡受傷人數" TEXT,
    "當事者順位" INTEGER,
    "當事者區分-類別-大類別名稱-車種" TEXT,
    "當事者區分-類別-子類別名稱-車種" TEXT,
    "當事者屬-性-別名稱" TEXT,
    "當事者事故發生時年齡" INTEGER,
    "保護裝備名稱" TEXT,
    "行動電話或電腦或其他相類功能裝置名稱" TEXT,
    "當事者行動狀態大類別名稱" TEXT,
    "當事者行動狀態子類別名稱" TEXT,
    "車輛撞擊部位大類別名稱-最初" TEXT,
    "車輛撞擊部位子類別名稱-最初" TEXT,
    "車輛撞擊部位大類別名稱-其他" TEXT,
    "車輛撞擊部位子類別名稱-其他" TEXT,
    "肇因研判大類別名稱-個別" TEXT,
    "肇因研判子類別名稱-個別" TEXT,
    "肇事逃逸類別名稱-是否肇逃" TEXT,
    "經度" REAL,
    "緯度" REAL,
    "共享經濟或外送平台的名稱" TEXT
);

-- R-Tree 索引：用來快速查詢「經緯度附近範圍內」的事故（點資料 min=max）
CREATE VIRTUAL TABLE accidents_rtree USING rtree(
    id,
    min_lon, max_lon,
    min_lat, max_lat
);

CREATE TRIGGER trg_accidents_ai AFTER INSERT ON accidents
WHEN NEW."經度" IS NOT NULL AND NEW."緯度" IS NOT NULL
BEGIN
    INSERT INTO accidents_rtree (id, min_lon, max_lon, min_lat, max_lat)
    VALUES (NEW.id, NEW."經度", NEW."經度", NEW."緯度", NEW."緯度");
END;

CREATE TRIGGER trg_accidents_au AFTER UPDATE OF "經度", "緯度" ON accidents
BEGIN
    DELETE FROM accidents_rtree WHERE id = OLD.id;
    INSERT INTO accidents_rtree (id, min_lon, max_lon, min_lat, max_lat)
    SELECT NEW.id, NEW."經度", NEW."經度", NEW."緯度", NEW."緯度"
    WHERE NEW."經度" IS NOT NULL AND NEW."緯度" IS NOT NULL;
END;

CREATE TRIGGER trg_accidents_ad AFTER DELETE ON accidents
BEGIN
    DELETE FROM accidents_rtree WHERE id = OLD.id;
END;
