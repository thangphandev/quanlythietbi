<?php
require_once 'config.php';

$teachers = [
    82  => 'Châu Công Hậu',
    237 => 'Nguyễn Hoàng Nhân'
];

foreach ($teachers as $id_gv => $name_gv) {
    echo "\n=== $name_gv (ID: $id_gv) ===\n";
    $stmt = $db->prepare("
        SELECT lgdct.ngay_day, lgdct.tuan, lgd.thu, lgd.tiet_bd, lgd.tiet_kt, lhp.ma_lop_hp, lhp.ten_hoc_phan, lgd.id AS lgd_id
        FROM lich_giang_day_chi_tiet lgdct
        JOIN lich_giang_day lgd ON lgdct.id_lich_giang_day = lgd.id
        JOIN lop_hoc_phan lhp ON lgd.id_lop_hoc_phan = lhp.id_lop_hoc_phan
        WHERE lgd.id_giang_vien = :id_gv
          AND lgd.id_hocky_namhoc = 89
          AND LOWER(substring(lhp.ma_lop_hp, 4, 1)) = 'b'
        ORDER BY lgdct.ngay_day ASC, lgd.thu ASC, lgd.tiet_bd ASC
    ");
    $stmt->execute(['id_gv' => $id_gv]);
    $sessions = $stmt->fetchAll();
    
    echo "Total sessions in DB: " . count($sessions) . "\n";
    foreach ($sessions as $s) {
        echo "Date: {$s['ngay_day']} | Week: {$s['tuan']} | Day: {$s['thu']} | Periods: {$s['tiet_bd']}-{$s['tiet_kt']} | Class: {$s['ma_lop_hp']} ({$s['ten_hoc_phan']}) | LGD ID: {$s['lgd_id']}\n";
    }
}
