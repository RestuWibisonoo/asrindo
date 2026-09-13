<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pdo = getPDO();

$pageTitle = 'Pembelian Alat Berat';
$adminBase = '../';

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float)($value ?? 0), 0, ',', '.');
}

function redirectMessage(string $type, string $message): void
{
    header('Location: pembelian_alat_berat.php?' . http_build_query([
        'msg_type' => $type,
        'msg' => $message
    ]));
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $nomor = trim((string)($_POST['nomor_pembelian'] ?? ''));
            $tanggal = trim((string)($_POST['tanggal'] ?? ''));
            $supplierId = filter_var($_POST['supplier_id'] ?? null, FILTER_VALIDATE_INT);
            $estimasi = trim((string)($_POST['estimasi_kedatangan'] ?? ''));
            $kurs = (float)($_POST['kurs_pembelian'] ?? 0);
            $bea = (float)($_POST['biaya_bea_cukai'] ?? 0);
            $pengiriman = (float)($_POST['biaya_pengiriman'] ?? 0);
            $lain = (float)($_POST['biaya_lain'] ?? 0);
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($nomor === '' || $tanggal === '' || !$supplierId) {
                throw new RuntimeException('Nomor pembelian, tanggal, dan supplier wajib diisi.');
            }

            if ($kurs < 0 || $bea < 0 || $pengiriman < 0 || $lain < 0) {
                throw new RuntimeException('Nilai kurs dan biaya tidak boleh negatif.');
            }

            $stmt = $pdo->prepare("SELECT id FROM supplier WHERE id = ? LIMIT 1");
            $stmt->execute([$supplierId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Supplier tidak ditemukan.');
            }

            $stmt = $pdo->prepare("
                SELECT id FROM pembelian_alat_berat
                WHERE nomor_pembelian = ? LIMIT 1
            ");
            $stmt->execute([$nomor]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Nomor pembelian sudah digunakan. Silakan buka detail transaksi tersebut.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO pembelian_alat_berat
                    (nomor_pembelian, tanggal, supplier_id, estimasi_kedatangan,
                     kurs_pembelian, biaya_bea_cukai, biaya_pengiriman, biaya_lain,
                     total, status, keterangan, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PROSES', ?, ?)
            ");
            $stmt->execute([
                $nomor,
                $tanggal,
                $supplierId,
                $estimasi !== '' ? $estimasi : null,
                $kurs,
                $bea,
                $pengiriman,
                $lain,
                $bea + $pengiriman + $lain,
                $keterangan !== '' ? $keterangan : null,
                (int)$_SESSION['admin_id']
            ]);

            $newId = (int)$pdo->lastInsertId();

            header('Location: pembelian_alat_berat_detail.php?' . http_build_query([
                'id' => $newId,
                'msg_type' => 'success',
                'msg' => 'Header pembelian berhasil dibuat. Silakan tambahkan unit dan rencana pembayaran.'
            ]));
            exit;
        }

        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new RuntimeException('ID pembelian tidak valid.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT p.id, p.status,
                       COALESCE((SELECT SUM(nominal)
                                 FROM pembelian_pembayaran pp
                                 WHERE pp.pembelian_alat_berat_id = p.id
                                   AND pp.status = 'PAID'), 0) AS sudah_bayar
                FROM pembelian_alat_berat p
                WHERE p.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) {
                throw new RuntimeException('Pembelian tidak ditemukan.');
            }

            if ((float)$purchase['sudah_bayar'] > 0) {
                throw new RuntimeException('Pembelian yang sudah memiliki pembayaran PAID tidak dapat dihapus.');
            }

            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM pembelian_pembayaran
                WHERE pembelian_alat_berat_id = ? AND status <> 'BATAL'
            ");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('Pembelian memiliki rencana pembayaran. Hapus termin terlebih dahulu dari halaman detail.');
            }

            $stmt = $pdo->prepare("DELETE FROM pembelian_alat_berat WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);

            $pdo->commit();
            redirectMessage('success', 'Pembelian berhasil dihapus.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirectMessage('error', $e->getMessage() !== '' ? $e->getMessage() : 'Terjadi kesalahan.');
    }
}

if (isset($_GET['msg'])) {
    $message = trim((string)$_GET['msg']);
    $messageType = ($_GET['msg_type'] ?? '') === 'success' ? 'success' : 'error';
}

$suppliers = $pdo->query("
    SELECT id, kode, nama
    FROM supplier
    ORDER BY nama ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$purchases = $pdo->query("
    SELECT
        p.id,
        p.nomor_pembelian,
        p.tanggal,
        s.kode AS supplier_kode,
        s.nama AS supplier_nama,
        p.status,
        p.total,
        p.estimasi_kedatangan,
        p.kedatangan_aktual,
        COUNT(DISTINCT d.id) AS jumlah_unit,
        COALESCE(SUM(CASE WHEN pp.status = 'PAID' THEN pp.nominal ELSE 0 END), 0) AS sudah_dibayar,
        COALESCE(SUM(CASE WHEN pp.status <> 'BATAL' THEN pp.nominal ELSE 0 END), 0) AS total_terjadwal
    FROM pembelian_alat_berat p
    INNER JOIN supplier s ON s.id = p.supplier_id
    LEFT JOIN pembelian_alat_berat_detail d ON d.pembelian_id = p.id
    LEFT JOIN pembelian_pembayaran pp ON pp.pembelian_alat_berat_id = p.id
    GROUP BY
        p.id, p.nomor_pembelian, p.tanggal, s.kode, s.nama, p.status,
        p.total, p.estimasi_kedatangan, p.kedatangan_aktual
    ORDER BY p.tanggal DESC, p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalPurchase = count($purchases);
$totalValue = 0;
$totalPaid = 0;
$totalOutstanding = 0;

foreach ($purchases as $p) {
    $totalValue += (float)$p['total'];
    $totalPaid += (float)$p['sudah_dibayar'];
    $totalOutstanding += max(0, (float)$p['total'] - (float)$p['sudah_dibayar']);
}

require __DIR__ . '/../includes/header.php';
?>

<section class="page-heading">
    <div>
        <h1>Pembelian Alat Berat</h1>
        <p>Kelola transaksi pembelian unit, tagihan supplier, dan rencana pembayaran.</p>
    </div>
    <button type="button" class="btn-primary" id="openCreateModal">+ Pembelian Baru</button>
</section>

<?php if ($message !== ''): ?>
    <div class="page-alert <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
        <?php echo h($message); ?>
    </div>
<?php endif; ?>

<section class="purchase-stats">
    <div class="purchase-stat-card">
        <span>Transaksi Pembelian</span>
        <strong><?php echo number_format($totalPurchase, 0, ',', '.'); ?></strong>
    </div>
    <div class="purchase-stat-card">
        <span>Total Nilai Pembelian</span>
        <strong><?php echo rupiah($totalValue); ?></strong>
    </div>
    <div class="purchase-stat-card">
        <span>Sudah Dibayar</span>
        <strong><?php echo rupiah($totalPaid); ?></strong>
    </div>
    <div class="purchase-stat-card">
        <span>Sisa Kewajiban</span>
        <strong><?php echo rupiah($totalOutstanding); ?></strong>
    </div>
</section>

<section class="dashboard-panel purchase-panel">
    <div class="panel-heading">
        <div>
            <h2>Daftar Pembelian</h2>
            <span class="panel-subtitle">Klik Detail untuk mengelola unit dan pembayaran supplier.</span>
        </div>
    </div>

    <div class="purchase-toolbar">
        <div class="purchase-search">
            <label for="purchaseSearch">Pencarian</label>
            <input type="search" id="purchaseSearch" placeholder="Cari nomor, supplier, status..." autocomplete="off">
        </div>
        <div class="purchase-page-size">
            <label for="purchasePageSize">Tampilkan</label>
            <select id="purchasePageSize">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="dashboard-table purchase-table" id="purchaseTable">
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Tanggal</th>
                    <th>Supplier</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Terbayar</th>
                    <th>Sisa</th>
                    <th>Aksi</th>
                </tr>
                <tr class="purchase-filter-row">
                    <th><input type="text" data-filter-column="0" placeholder="Nomor"></th>
                    <th><input type="text" data-filter-column="1" placeholder="Tanggal"></th>
                    <th><input type="text" data-filter-column="2" placeholder="Supplier"></th>
                    <th><input type="text" data-filter-column="3" placeholder="Unit"></th>
                    <th><input type="text" data-filter-column="4" placeholder="Status"></th>
                    <th><input type="text" data-filter-column="5" placeholder="Total"></th>
                    <th><input type="text" data-filter-column="6" placeholder="Terbayar"></th>
                    <th><input type="text" data-filter-column="7" placeholder="Sisa"></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($purchases)): ?>
                <tr><td colspan="9" class="empty-state">Belum ada transaksi pembelian alat berat.</td></tr>
            <?php else: ?>
                <?php foreach ($purchases as $purchase): ?>
                    <?php
                    $sisa = max(0, (float)$purchase['total'] - (float)$purchase['sudah_dibayar']);
                    $status = strtoupper((string)$purchase['status']);
                    $statusClass = $status === 'SELESAI' ? 'badge-success' : ($status === 'BATAL' ? 'badge-danger' : 'badge-info');
                    ?>
                    <tr data-id="<?php echo (int)$purchase['id']; ?>">
                        <td><strong><?php echo h($purchase['nomor_pembelian']); ?></strong></td>
                        <td><?php echo h(date('d-m-Y', strtotime($purchase['tanggal']))); ?></td>
                        <td><?php echo h($purchase['supplier_nama']); ?></td>
                        <td><span class="unit-count"><?php echo number_format((int)$purchase['jumlah_unit'], 0, ',', '.'); ?> unit</span></td>
                        <td><span class="purchase-badge <?php echo $statusClass; ?>"><?php echo h($purchase['status']); ?></span></td>
                        <td class="money-cell"><?php echo rupiah($purchase['total']); ?></td>
                        <td class="money-cell paid-cell"><?php echo rupiah($purchase['sudah_dibayar']); ?></td>
                        <td class="money-cell"><?php echo rupiah($sisa); ?></td>
                        <td class="action-cell">
                            <a class="btn-small btn-detail" href="pembelian_alat_berat_detail.php?id=<?php echo (int)$purchase['id']; ?>">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="purchase-table-footer">
        <div id="purchaseInfo">Menampilkan 0 transaksi</div>
        <div class="purchase-pagination" id="purchasePagination"></div>
    </div>
</section>

<div class="purchase-modal" id="createModal" aria-hidden="true">
    <div class="purchase-modal-box">
        <div class="purchase-modal-header">
            <div>
                <h3>Pembelian Alat Berat Baru</h3>
                <p>Buat header transaksi terlebih dahulu. Unit dan termin pembayaran dikelola pada halaman Detail.</p>
            </div>
            <button type="button" class="purchase-modal-close" data-close-modal="createModal">&times;</button>
        </div>

        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="create">

            <div class="purchase-modal-body">
                <div class="purchase-section-title">Informasi Pembelian</div>
                <div class="purchase-form-grid">
                    <div class="purchase-field">
                        <label>Nomor Pembelian <span>*</span></label>
                        <input type="text" name="nomor_pembelian" maxlength="50" placeholder="PO-20260910-0001" required>
                    </div>
                    <div class="purchase-field">
                        <label>Tanggal <span>*</span></label>
                        <input type="date" name="tanggal" value="<?php echo h(date('Y-m-d')); ?>" required>
                    </div>
                    <div class="purchase-field purchase-field-full">
                        <label>Supplier <span>*</span></label>
                        <select name="supplier_id" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo (int)$supplier['id']; ?>">
                                    <?php echo h($supplier['kode'] . ' - ' . $supplier['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="purchase-field">
                        <label>Estimasi Kedatangan</label>
                        <input type="date" name="estimasi_kedatangan">
                    </div>
                    <div class="purchase-field">
                        <label>Kurs Pembelian (IDR/USD)</label>
                        <input type="number" name="kurs_pembelian" min="0" step="0.01" value="0">
                    </div>
                </div>

                <div class="purchase-section-title">Biaya Pembelian</div>
                <div class="purchase-form-grid">
                    <div class="purchase-field">
                        <label>Bea Cukai</label>
                        <input type="number" name="biaya_bea_cukai" min="0" step="0.01" value="0">
                    </div>
                    <div class="purchase-field">
                        <label>Biaya Pengiriman</label>
                        <input type="number" name="biaya_pengiriman" min="0" step="0.01" value="0">
                    </div>
                    <div class="purchase-field">
                        <label>Biaya Lain</label>
                        <input type="number" name="biaya_lain" min="0" step="0.01" value="0">
                    </div>
                    <div class="purchase-field purchase-field-full">
                        <label>Keterangan</label>
                        <textarea name="keterangan" rows="3" placeholder="Keterangan pembelian"></textarea>
                    </div>
                </div>
            </div>

            <div class="purchase-modal-footer">
                <button type="button" class="btn-secondary" data-close-modal="createModal">Batal</button>
                <button type="submit" class="btn-primary">Buat Pembelian</button>
            </div>
        </form>
    </div>
</div>

<style>
.purchase-panel{overflow:visible}
.purchase-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 16px}
.purchase-stat-card{padding:15px 16px;border:1px solid #dfe5ed;border-radius:6px;background:#fff;box-shadow:0 1px 2px rgba(23,45,78,.04)}
.purchase-stat-card span{display:block;color:#718096;font-size:11px;margin-bottom:7px}
.purchase-stat-card strong{display:block;color:#183052;font-size:17px;line-height:1.25}
.panel-subtitle{display:block;margin-top:3px;color:#718096;font-size:12px}
.purchase-toolbar{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;padding:15px 16px;border-bottom:1px solid #dfe5ed}
.purchase-search{flex:1;max-width:480px}.purchase-page-size{width:130px}
.purchase-toolbar label{display:block;margin-bottom:6px;color:#52657f;font-size:11px}
.purchase-toolbar input,.purchase-toolbar select,.purchase-field input,.purchase-field select,.purchase-field textarea{width:100%;box-sizing:border-box;border:1px solid #bdcbe0;border-radius:4px;background:#fff;color:#172d4e;padding:8px 10px;outline:none;font-size:12px}
.purchase-toolbar input,.purchase-toolbar select,.purchase-field input,.purchase-field select{height:36px}
.purchase-field textarea{resize:vertical;min-height:80px}
.purchase-table{min-width:1120px}.purchase-table th,.purchase-table td{vertical-align:middle}
.purchase-filter-row th{padding:7px;background:#f7f9fc}.purchase-filter-row input{width:100%;height:30px;box-sizing:border-box;border:1px solid #bdcbe0;border-radius:4px;padding:4px 6px;font-size:10px;color:#172d4e;outline:none}
.money-cell{text-align:right;white-space:nowrap}.paid-cell{font-weight:600}.unit-count{color:#0d6efd;font-size:12px}
.purchase-badge{display:inline-block;padding:3px 7px;border-radius:4px;font-size:10px;font-weight:600}.badge-info{background:#06b6d4;color:#fff}.badge-success{background:#10b981;color:#fff}.badge-danger{background:#ef4444;color:#fff}
.action-cell{white-space:nowrap}.btn-small{display:inline-flex;align-items:center;justify-content:center;min-width:50px;height:30px;padding:0 10px;border:1px solid #bdcbe0;border-radius:4px;background:#fff;color:#52657f;font-size:11px;cursor:pointer;text-decoration:none;box-sizing:border-box}.btn-small:hover{border-color:#0d6efd;color:#0d6efd}
.purchase-table-footer{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:12px 16px;color:#718096;font-size:12px}.purchase-pagination{display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end}.purchase-pagination button{min-width:34px;height:32px;border:1px solid #d4deeb;border-radius:4px;background:#fff;color:#52657f;cursor:pointer;font-size:11px}.purchase-pagination button.active{border-color:#0d6efd;background:#0d6efd;color:#fff}.purchase-pagination button:disabled{cursor:not-allowed;opacity:.5}
.purchase-modal{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;padding:18px;box-sizing:border-box;background:rgba(23,45,78,.55)}.purchase-modal.show{display:flex}.purchase-modal-box{width:min(760px,100%);max-height:calc(100vh - 36px);overflow-y:auto;border-radius:6px;background:#fff;box-shadow:0 15px 45px rgba(0,0,0,.18)}
.purchase-modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding:14px 16px;border-bottom:1px solid #dfe5ed}.purchase-modal-header h3{margin:0;color:#183052;font-size:15px}.purchase-modal-header p{margin:5px 0 0;color:#718096;font-size:11px}.purchase-modal-close{width:34px;height:34px;border:0;background:transparent;color:#718096;font-size:25px;cursor:pointer}
.purchase-modal-body{padding:18px 16px}.purchase-section-title{margin:2px 0 14px;padding-bottom:7px;border-bottom:1px solid #dfe5ed;color:#183052;font-size:12px;font-weight:600}.purchase-section-title:not(:first-child){margin-top:20px}
.purchase-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.purchase-field{min-width:0}.purchase-field-full{grid-column:1/-1}.purchase-field label{display:block;margin-bottom:6px;color:#183052;font-size:11px}.purchase-field label span{color:#dc3545}
.purchase-modal-footer{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid #dfe5ed}.btn-primary,.btn-secondary{min-height:36px;padding:0 15px;border-radius:4px;font-size:11px;cursor:pointer}.btn-primary{border:0;background:#0d6efd;color:#fff}.btn-secondary{border:0;background:#718096;color:#fff}
@media(max-width:1000px){.purchase-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:800px){.purchase-toolbar{align-items:stretch;flex-direction:column}.purchase-search,.purchase-page-size{width:100%;max-width:none}.purchase-table-footer{align-items:flex-start;flex-direction:column}.purchase-pagination{justify-content:flex-start}.purchase-form-grid{grid-template-columns:1fr}.purchase-field-full{grid-column:auto}.purchase-modal{padding:10px}.purchase-modal-box{max-height:calc(100vh - 20px)}}
@media(max-width:520px){.purchase-stats{grid-template-columns:1fr}}
</style>

<script>
(function(){
'use strict';
var modal=document.getElementById('createModal');
function openModal(){if(modal){modal.classList.add('show');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';}}
function closeModal(){if(modal){modal.classList.remove('show');modal.setAttribute('aria-hidden','true');document.body.style.overflow='';}}
var open=document.getElementById('openCreateModal'); if(open) open.addEventListener('click',openModal);
document.querySelectorAll('[data-close-modal]').forEach(function(btn){btn.addEventListener('click',closeModal);});
if(modal) modal.addEventListener('click',function(e){if(e.target===modal)closeModal();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal();});

var table=document.getElementById('purchaseTable'),tbody=table?table.querySelector('tbody'):null;
var search=document.getElementById('purchaseSearch'),size=document.getElementById('purchasePageSize');
var info=document.getElementById('purchaseInfo'),pagination=document.getElementById('purchasePagination');
if(!tbody||!search||!size||!info||!pagination)return;
var rows=Array.prototype.slice.call(tbody.querySelectorAll('tr[data-id]'));
var filters=Array.prototype.slice.call(document.querySelectorAll('[data-filter-column]'));
var page=1;
function norm(v){return String(v||'').toLowerCase().trim();}
function filtered(){
 var q=norm(search.value), f={};
 filters.forEach(function(x){f[x.getAttribute('data-filter-column')]=norm(x.value);});
 return rows.filter(function(r){
   if(q&&norm(r.textContent).indexOf(q)===-1)return false;
   for(var k in f){if(f[k]){var c=r.children[parseInt(k,10)];if(c&&norm(c.textContent).indexOf(f[k])===-1)return false;}}
   return true;
 });
}
function renderPages(totalPages){
 pagination.innerHTML=''; if(totalPages<=1)return;
 function add(label,p,disabled,active){var b=document.createElement('button');b.type='button';b.textContent=label;b.disabled=disabled;if(active)b.classList.add('active');b.onclick=function(){page=p;render();};pagination.appendChild(b);}
 add('‹',Math.max(1,page-1),page===1,false);
 var start=Math.max(1,page-2),end=Math.min(totalPages,page+2);
 for(var p=start;p<=end;p++)add(String(p),p,false,p===page);
 add('›',Math.min(totalPages,page+1),page===totalPages,false);
}
function render(){
 var data=filtered(), ps=parseInt(size.value,10)||10,total=data.length,tp=Math.max(1,Math.ceil(total/ps));
 if(page>tp)page=tp;
 rows.forEach(function(r){r.style.display='none';});
 var start=(page-1)*ps,end=Math.min(start+ps,total);
 for(var i=start;i<end;i++)data[i].style.display='';
 info.textContent=total?'Menampilkan '+(start+1)+' sampai '+end+' dari '+total+' transaksi':'Tidak ada data yang sesuai.';
 renderPages(tp);
}
search.addEventListener('input',function(){page=1;render();});size.addEventListener('change',function(){page=1;render();});filters.forEach(function(x){x.addEventListener('input',function(){page=1;render();});});render();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
