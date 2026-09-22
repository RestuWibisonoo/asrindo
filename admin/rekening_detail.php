<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    // header('Location: index.php');
    // exit;
}

$pageTitle = 'Detail Mutasi Rekening';

// Dummy data rekening
$rekeningInfo = [
    'id' => 1,
    'nama_rekening' => 'Rekening Penjualan',
    'nama_bank' => 'BCA',
    'nomor_rekening' => '1234567890',
    'atas_nama' => 'PT Asrindo'
];

// Dummy data mutasi (transaksi)
$mutasiList = [
    [
        'id' => 1,
        'tanggal' => '2026-09-20',
        'keterangan' => 'Saldo Awal',
        'jenis' => 'MASUK',
        'nominal' => 100000000,
        'saldo_berjalan' => 100000000
    ],
    [
        'id' => 2,
        'tanggal' => '2026-09-21',
        'keterangan' => 'Pembayaran Invoice INV-20260901',
        'jenis' => 'MASUK',
        'nominal' => 55500000,
        'saldo_berjalan' => 155500000
    ],
    [
        'id' => 3,
        'tanggal' => '2026-09-22',
        'keterangan' => 'Pembayaran Pembelian Sparepart SP-001',
        'jenis' => 'KELUAR',
        'nominal' => 5000000,
        'saldo_berjalan' => 150500000
    ]
];

$summary = [
    'saldo_awal' => 0,
    'total_masuk' => 155500000,
    'total_keluar' => 5000000,
    'saldo_akhir' => 150500000
];

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return number_format((float) ($value ?? 0), 0, ',', '.');
}

$extraHead = <<<'HTML'
<style>
.page-content {
    padding: 26px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 22px;
}

.page-heading h1 {
    margin: 0 0 4px;
    font-size: 25px;
    color: #071b3a;
}

.page-heading p {
    margin: 0;
    color: #71809a;
    font-size: 13px;
}

.btn {
    border: 1px solid transparent;
    border-radius: 4px;
    padding: 9px 15px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
    box-sizing: border-box;
}

.btn-sm {
    padding: 7px 12px;
    font-size: 12px;
}

.btn-primary {
    background: #086cff;
    color: #fff;
}

.btn-primary:hover {
    background: #0058d8;
}

.btn-light {
    background: #eef1f5;
    border-color: #d8dfe8;
    color: #52647d;
}

.btn-light:hover {
    background: #e4e9f0;
}

.card {
    background: #fff;
    border: 1px solid #dbe2ec;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 20px;
}

.card-header {
    padding: 16px 18px;
    border-bottom: 1px solid #dbe2ec;
    color: #0b2853;
}

.card-body {
    padding: 16px 18px;
}

.detail-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    border: 1px solid #dbe2ec;
    border-radius: 4px;
}

.summary-item {
    padding: 15px;
    border-right: 1px solid #dbe2ec;
}

.summary-item:last-child {
    border-right: 0;
}

.summary-item span {
    display: block;
    color: #7a889d;
    font-size: 11px;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-item strong {
    color: #09264d;
    font-size: 16px;
    display: block;
}

.summary-item .text-success { color: #10b95d; }
.summary-item .text-danger { color: #e74c3c; }

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.data-table th,
.data-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #dbe2ec;
    text-align: left;
    font-size: 13px;
    color: #0b2853;
    vertical-align: middle;
}

.data-table thead th {
    background: #f5f7fa;
    font-weight: 600;
    white-space: nowrap;
}

.data-table tbody tr:hover {
    background: #fafcff;
}

.money-cell {
    text-align: right !important;
    white-space: nowrap;
}

.text-success { color: #10b95d; }
.text-danger { color: #e74c3c; }

.info-grid {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 8px 15px;
    font-size: 13px;
    color: #263d60;
}
.info-label {
    color: #7a889d;
}

@media (max-width: 768px) {
    .detail-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .summary-item:nth-child(2) {
        border-right: 0;
    }
    .summary-item:nth-child(1),
    .summary-item:nth-child(2) {
        border-bottom: 1px solid #dbe2ec;
    }
}
</style>
HTML;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="page-heading">
        <div>
            <h1>Detail Mutasi Rekening</h1>
            <p>Riwayat transaksi dan mutasi saldo untuk rekening terpilih.</p>
        </div>
        <a href="rekening.php" class="btn btn-light">
            &larr; Kembali ke Rekening
        </a>
    </div>

    <!-- Info Rekening -->
    <div class="card">
        <div class="card-header">
            <strong>Informasi Rekening</strong>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-label">Nama/Alias</div>
                <div><strong><?php echo h($rekeningInfo['nama_rekening']); ?></strong></div>
                
                <div class="info-label">Bank</div>
                <div><?php echo h($rekeningInfo['nama_bank']); ?></div>
                
                <div class="info-label">No. Rekening</div>
                <div><?php echo h($rekeningInfo['nomor_rekening']); ?></div>
                
                <div class="info-label">Atas Nama</div>
                <div><?php echo h($rekeningInfo['atas_nama']); ?></div>
            </div>
        </div>
    </div>

    <!-- Summary Saldo -->
    <div class="detail-summary" style="margin-bottom: 20px; background: #fff;">
        <div class="summary-item">
            <span>Saldo Awal (Periode)</span>
            <strong>Rp <?php echo rupiah($summary['saldo_awal']); ?></strong>
        </div>
        <div class="summary-item">
            <span>Total Pemasukan</span>
            <strong class="text-success">Rp <?php echo rupiah($summary['total_masuk']); ?></strong>
        </div>
        <div class="summary-item">
            <span>Total Pengeluaran</span>
            <strong class="text-danger">Rp <?php echo rupiah($summary['total_keluar']); ?></strong>
        </div>
        <div class="summary-item" style="background: #f8fbff;">
            <span>Saldo Akhir</span>
            <strong style="color: #086cff;">Rp <?php echo rupiah($summary['saldo_akhir']); ?></strong>
        </div>
    </div>

    <!-- Tabel Mutasi -->
    <div class="card">
        <div class="card-header">
            <div>
                <strong>Riwayat Transaksi</strong>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th class="money-cell">Masuk (Debit)</th>
                        <th class="money-cell">Keluar (Kredit)</th>
                        <th class="money-cell" style="background: #f8fbff;">Saldo Berjalan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mutasiList)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px; color: #8a96aa;">Belum ada riwayat transaksi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mutasiList as $mutasi): ?>
                            <tr>
                                <td><?php echo h($mutasi['tanggal']); ?></td>
                                <td><?php echo h($mutasi['keterangan']); ?></td>
                                <td class="money-cell">
                                    <?php if ($mutasi['jenis'] === 'MASUK'): ?>
                                        <span class="text-success">Rp <?php echo rupiah($mutasi['nominal']); ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="money-cell">
                                    <?php if ($mutasi['jenis'] === 'KELUAR'): ?>
                                        <span class="text-danger">Rp <?php echo rupiah($mutasi['nominal']); ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="money-cell" style="background: #fdfdff; font-weight: 500;">
                                    Rp <?php echo rupiah($mutasi['saldo_berjalan']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
