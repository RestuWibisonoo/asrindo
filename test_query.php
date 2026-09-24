<?php
require_once __DIR__ . '/config/koneksi.php';
$pdo = getPDO();
$today = date('Y-m-d');
try {
$sqlJatuhTempo = "SELECT SUM(nominal) FROM (
                      SELECT nominal FROM pembelian_pembayaran WHERE status = 'BELUM_BAYAR' AND tanggal_jatuh_tempo <= ?
                      UNION ALL
                      SELECT (total - COALESCE((SELECT SUM(nominal) FROM pengeluaran WHERE utang_lainnya_id = ul.id), 0)) as nominal
                      FROM utang_lainnya ul
                      WHERE ul.status = 'BELUM_LUNAS' AND ul.jatuh_tempo <= ?
                  ) as gabungan_jt";
$stmtJT = $pdo->prepare($sqlJatuhTempo);
$stmtJT->execute([$today, $today]);
echo "JT: " . (float) $stmtJT->fetchColumn() . "\n";

$sql = "SELECT * FROM (
            SELECT 
                'Alat Berat' AS jenis,
                pab.id,
                pab.nomor_pembelian AS nomor,
                pab.tanggal,
                s.nama AS nama_supplier,
                pab.total,
                COALESCE((SELECT SUM(nominal) FROM pembelian_pembayaran WHERE pembelian_alat_berat_id = pab.id AND status = 'PAID'), 0) AS terbayar,
                (SELECT MIN(tanggal_jatuh_tempo) FROM pembelian_pembayaran WHERE pembelian_alat_berat_id = pab.id AND status = 'BELUM_BAYAR') AS jatuh_tempo_terdekat
            FROM pembelian_alat_berat pab
            LEFT JOIN supplier s ON pab.supplier_id = s.id
            WHERE pab.status != 'BATAL'

            UNION ALL

            SELECT 
                'Sparepart' AS jenis,
                psp.id,
                psp.nomor_pembelian AS nomor,
                psp.tanggal,
                s.nama AS nama_supplier,
                psp.total,
                COALESCE((SELECT SUM(nominal) FROM pengeluaran WHERE pembelian_sparepart_id = psp.id), 0) AS terbayar,
                (SELECT MIN(tanggal_jatuh_tempo) FROM pembelian_pembayaran WHERE pembelian_sparepart_id = psp.id AND status = 'BELUM_BAYAR') AS jatuh_tempo_terdekat
            FROM pembelian_sparepart psp
            LEFT JOIN supplier s ON psp.supplier_id = s.id
            WHERE psp.status != 'BATAL'

            UNION ALL

            SELECT 
                'Restorasi' AS jenis,
                pr.id,
                pr.nomor_pembelian AS nomor,
                pr.tanggal,
                s.nama AS nama_supplier,
                pr.total,
                COALESCE((SELECT SUM(nominal) FROM pengeluaran WHERE pembelian_restorasi_id = pr.id), 0) AS terbayar,
                NULL AS jatuh_tempo_terdekat
            FROM pembelian_restorasi pr
            LEFT JOIN supplier s ON pr.supplier_id = s.id
            WHERE pr.status != 'BATAL'

            UNION ALL

            SELECT 
                'Utang Lainnya' AS jenis,
                ul.id,
                ul.nomor_referensi AS nomor,
                ul.tanggal,
                ul.kreditur AS nama_supplier,
                ul.total,
                COALESCE((SELECT SUM(nominal) FROM pengeluaran WHERE utang_lainnya_id = ul.id), 0) AS terbayar,
                ul.jatuh_tempo AS jatuh_tempo_terdekat
            FROM utang_lainnya ul
            WHERE ul.status != 'BATAL'
        ) AS gabungan
        WHERE (total - terbayar) > 0
        ORDER BY COALESCE(jatuh_tempo_terdekat, '9999-12-31') ASC, tanggal ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
echo "ROWS: " . count($stmt->fetchAll(PDO::FETCH_ASSOC)) . "\n";
} catch (Exception $e) { echo $e->getMessage(); }
