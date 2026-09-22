<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    // header('Location: index.php');
    // exit;
}

$pageTitle = 'Manajemen Rekening';

// Dummy data rekening
$rekeningList = [
    [
        'id' => 1,
        'nama_rekening' => 'Rekening Penjualan',
        'nama_bank' => 'BCA',
        'nomor_rekening' => '1234567890',
        'atas_nama' => 'PT Asrindo',
        'saldo' => 150500000,
        'status' => 'Aktif'
    ],
    [
        'id' => 2,
        'nama_rekening' => 'Rekening Pembelian',
        'nama_bank' => 'Mandiri',
        'nomor_rekening' => '0987654321',
        'atas_nama' => 'PT Asrindo',
        'saldo' => 75000000,
        'status' => 'Aktif'
    ],
    [
        'id' => 3,
        'nama_rekening' => 'Kas Kecil',
        'nama_bank' => 'Tunai',
        'nomor_rekening' => '-',
        'atas_nama' => 'Admin',
        'saldo' => 5000000,
        'status' => 'Aktif'
    ]
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

.card {
    background: #fff;
    border: 1px solid #dbe2ec;
    border-radius: 4px;
    overflow: hidden;
}

.card-header {
    padding: 16px 18px;
    border-bottom: 1px solid #dbe2ec;
    color: #0b2853;
}

.card-header > div {
    display: flex;
    align-items: center;
    gap: 10px;
}

.muted {
    color: #8290a7;
    font-size: 12px;
}

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
    padding: 12px 10px;
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

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.status-success {
    background: #10b95d;
    color: #fff;
}

.status-danger {
    background: #e74c3c;
    color: #fff;
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

.btn-outline {
    background: #fff;
    border-color: #7890b1;
    color: #58708f;
}

.btn-outline:hover {
    background: #f4f6f9;
}

.btn-danger-outline {
    background: #fff;
    border-color: #ff5a64;
    color: #f04450;
}

.btn-danger-outline:hover {
    background: #fff0f1;
}

.btn-info-outline {
    background: #fff;
    border-color: #0db6d1;
    color: #0ba3bc;
}

.btn-info-outline:hover {
    background: #e6f9fc;
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(8, 19, 37, .58);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    box-sizing: border-box;
}

.modal-backdrop.show {
    display: flex;
}

.modal {
    width: min(500px, 100%);
    max-height: calc(100vh - 36px);
    background: #fff;
    border-radius: 6px;
    overflow-y: auto;
    overflow-x: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    display: block;
}

.modal-header {
    padding: 16px 18px;
    border-bottom: 1px solid #dbe2ec;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.modal-header h2 {
    margin: 0;
    color: #0a2347;
    font-size: 18px;
}

.modal-close {
    border: 0;
    background: transparent;
    color: #7b899e;
    font-size: 26px;
    cursor: pointer;
    line-height: 1;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    border-top: 1px solid #dbe2ec;
    padding: 13px 18px;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    color: #263d60;
    font-size: 12px;
}

.form-group input,
.form-group select {
    width: 100%;
    box-sizing: border-box;
    padding: 9px 10px;
    border: 1px solid #bdcadc;
    border-radius: 4px;
    background: #fff;
    color: #183457;
    outline: none;
    font: inherit;
    font-size: 13px;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #1473e6;
    box-shadow: 0 0 0 2px rgba(20, 115, 230, .08);
}
</style>
HTML;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="page-heading">
        <div>
            <h1>Manajemen Rekening</h1>
            <p>Kelola rekening bank perusahaan untuk memisahkan transaksi penjualan, pembelian, dll.</p>
        </div>
        <button type="button" class="btn btn-primary" onclick="openModal('modalFormRekening', 'Tambah Rekening')">
            + Tambah Rekening
        </button>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <strong>Daftar Rekening</strong>
                <span class="muted">
                    <?php echo count($rekeningList); ?> rekening
                </span>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Rekening (Alias)</th>
                        <th>Bank</th>
                        <th>No. Rekening</th>
                        <th>Atas Nama</th>
                        <th class="money-cell">Saldo Saat Ini</th>
                        <th>Status</th>
                        <th style="width: 220px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekeningList as $index => $rek): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo h($rek['nama_rekening']); ?></strong></td>
                            <td><?php echo h($rek['nama_bank']); ?></td>
                            <td><?php echo h($rek['nomor_rekening']); ?></td>
                            <td><?php echo h($rek['atas_nama']); ?></td>
                            <td class="money-cell">Rp <?php echo rupiah($rek['saldo']); ?></td>
                            <td>
                                <?php if ($rek['status'] === 'Aktif'): ?>
                                    <span class="status-badge status-success">Aktif</span>
                                <?php else: ?>
                                    <span class="status-badge status-danger">Tidak Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="rekening_detail.php?id=<?php echo $rek['id']; ?>" class="btn btn-sm btn-info-outline">
                                    Mutasi
                                </a>
                                <button type="button" class="btn btn-sm btn-outline" onclick="openModal('modalFormRekening', 'Edit Rekening')">
                                    Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-danger-outline" onclick="openModal('modalDeleteRekening')">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form Rekening -->
<div class="modal-backdrop" id="modalFormRekening">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalFormTitle">Tambah Rekening</h2>
            <button type="button" class="modal-close" onclick="closeModal('modalFormRekening')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nama Rekening / Alias</label>
                <input type="text" placeholder="Contoh: Rekening Penjualan BCA" />
            </div>
            <div class="form-group">
                <label>Nama Bank</label>
                <input type="text" placeholder="Contoh: BCA, Mandiri, Tunai, dll" />
            </div>
            <div class="form-group">
                <label>Nomor Rekening</label>
                <input type="text" placeholder="Masukkan nomor rekening" />
            </div>
            <div class="form-group">
                <label>Atas Nama</label>
                <input type="text" placeholder="Nama pemilik rekening" />
            </div>
            <div class="form-group">
                <label>Saldo Awal (Rp)</label>
                <input type="number" placeholder="0" />
            </div>
            <div class="form-group">
                <label>Status</label>
                <select>
                    <option value="Aktif">Aktif</option>
                    <option value="Tidak Aktif">Tidak Aktif</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('modalFormRekening')">Batal</button>
            <button type="button" class="btn btn-primary" onclick="closeModal('modalFormRekening')">Simpan</button>
        </div>
    </div>
</div>

<!-- Modal Hapus Rekening -->
<div class="modal-backdrop" id="modalDeleteRekening">
    <div class="modal" style="width: 400px;">
        <div class="modal-header">
            <h2>Hapus Rekening</h2>
            <button type="button" class="modal-close" onclick="closeModal('modalDeleteRekening')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin: 0; color: #4a5b77; font-size: 13px;">
                Apakah Anda yakin ingin menghapus rekening ini? Data transaksi yang terkait mungkin akan terpengaruh.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('modalDeleteRekening')">Batal</button>
            <button type="button" class="btn btn-danger-outline" style="background: #e74c3c; color: #fff;" onclick="closeModal('modalDeleteRekening')">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
function openModal(id, title = '') {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('show');
        if (title && id === 'modalFormRekening') {
            document.getElementById('modalFormTitle').innerText = title;
        }
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('show');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
