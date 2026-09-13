<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$pageTitle = 'Alat Berat';
$adminBase = '../';

$pdo = getPDO();

/* Statistik */
$totalUnit = (int)$pdo->query("
    SELECT COUNT(*) FROM alat_berat
")->fetchColumn();

$unitTersedia = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alat_berat
    WHERE UPPER(status) IN ('TERSEDIA', 'SIAP JUAL', 'READY')
")->fetchColumn();

/*
 * Nilai inventori dihitung dari komponen dan jasa yang
 * tercatat pada unit. Jika nanti aturan HPP berubah,
 * bagian ini dapat disesuaikan.
 */
$totalNilaiInventori = (float)$pdo->query("
    SELECT
        COALESCE((SELECT SUM(total) FROM alat_berat_sparepart), 0)
        +
        COALESCE((SELECT SUM(total) FROM alat_berat_jasa), 0)
")->fetchColumn();

function statusClass($status)
{
    $status = strtoupper(trim($status));

    if (in_array($status, array('TERSEDIA', 'SIAP JUAL', 'READY'), true)) {
        return 'badge-success';
    }

    if (in_array($status, array('PERBAIKAN', 'REPAIR'), true)) {
        return 'badge-warning';
    }

    if (in_array($status, array('DISEWAKAN', 'DI SEWAKAN', 'RENTAL'), true)) {
        return 'badge-info';
    }

    if (in_array($status, array('TERJUAL', 'SOLD'), true)) {
        return 'badge-danger';
    }

    return 'badge-default';
}

function rupiah($value)
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

/*
 * Mengambil seluruh kolom yang relevan dengan tampilan
 * database alat_berat.
 */
$units = $pdo->query("
    SELECT
        id,
        kode,
        tipe,
        nomor_rangka,
        tahun_pembuatan,
        kondisi,
        status,
        lokasi,
        created_at,
        updated_at
    FROM alat_berat
    ORDER BY id DESC
")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<section class="page-heading alat-heading">

    <div>
        <h1>Manajemen Alat Berat</h1>
        <p>Mengelola data inventori alat berat ASRINDO</p>
    </div>

    <a href="alat_berat_tambah.php" class="btn-primary">
        <span>+</span> Tambah Alat Berat
    </a>

</section>

<section class="alat-stat-panel">

    <div class="panel-title">
        Statistik Alat Berat
    </div>

    <div class="alat-stat-grid">

        <div class="alat-stat-item">
            <strong><?php echo number_format($totalUnit, 0, ',', '.'); ?></strong>
            <span>Total Unit</span>
        </div>

        <div class="alat-stat-item">
            <strong class="text-green"><?php echo rupiah($totalNilaiInventori); ?></strong>
            <span>Total Nilai Inventori</span>
        </div>

        <div class="alat-stat-item">
            <strong class="text-blue">0 Jam</strong>
            <span>Rerata Jam Operasional</span>
        </div>

        <div class="alat-stat-item">
            <strong class="text-orange"><?php echo number_format($unitTersedia, 0, ',', '.'); ?></strong>
            <span>Unit Tersedia</span>
        </div>

    </div>

</section>

<section class="alat-table-panel">

    <div class="table-toolbar">
        <div>
            <strong>Daftar Unit Alat Berat</strong>
            <span><?php echo count($units); ?> unit</span>
        </div>
    </div>

    <div class="table-scroll">

        <table class="alat-table" id="alatBeratTable">

            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Tipe</th>
                    <th>Nomor Rangka</th>
                    <th>Tahun Pembuatan</th>
                    <th>Kondisi</th>
                    <th>Status</th>
                    <th>Lokasi</th>
                    <th class="col-action">Aksi</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($units)): ?>

                <tr>
                    <td colspan="8" class="empty-table">
                        Belum ada data alat berat.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($units as $unit): ?>

                    <tr>

                        <td>
                            <strong class="unit-code">
                                <?php echo htmlspecialchars($unit['kode'], ENT_QUOTES, 'UTF-8'); ?>
                            </strong>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($unit['tipe'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($unit['nomor_rangka'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td>
                            <?php echo $unit['tahun_pembuatan']; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($unit['kondisi'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td>
                            <span class="unit-badge <?php echo statusClass($unit['status']); ?>">
                                <?php echo htmlspecialchars($unit['status'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($unit['lokasi'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td class="col-action">
                            <a
                                href="alat_berat_detail.php?id=<?php echo (int)$unit['id']; ?>"
                                class="btn-detail"
                            >
                                Detail
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <div class="table-footer">
        Menampilkan <?php echo count($units); ?> unit
    </div>

</section>

<script>
(function () {

    var table = document.getElementById('alatBeratTable');

    if (!table) {
        return;
    }

    var headers = table.querySelectorAll('thead th');

    headers.forEach(function (header, index) {

        if (index === headers.length - 1) {
            return;
        }

        header.style.cursor = 'pointer';

        header.addEventListener('click', function () {

            var tbody = table.querySelector('tbody');
            var rows = Array.prototype.slice.call(
                tbody.querySelectorAll('tr')
            );

            if (rows.length <= 1 || !rows[0].children[index]) {
                return;
            }

            var ascending = header.getAttribute('data-sort') !== 'asc';

            rows.sort(function (a, b) {

                var aText = a.children[index].innerText.trim();
                var bText = b.children[index].innerText.trim();

                return aText.localeCompare(
                    bText,
                    'id',
                    {
                        numeric: true,
                        sensitivity: 'base'
                    }
                ) * (ascending ? 1 : -1);
            });

            rows.forEach(function (row) {
                tbody.appendChild(row);
            });

            header.setAttribute(
                'data-sort',
                ascending ? 'asc' : 'desc'
            );
        });

    });

})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
