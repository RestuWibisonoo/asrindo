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

function rupiah($value)
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function statusClass($status)
{
    $status = strtoupper(trim((string)$status));

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

/* Statistik */
$totalUnit = (int)$pdo->query("
    SELECT COUNT(*) FROM alat_berat
")->fetchColumn();

$unitTersedia = (int)$pdo->query("
    SELECT COUNT(*)
    FROM alat_berat
    WHERE UPPER(status) IN ('TERSEDIA', 'SIAP JUAL', 'READY')
")->fetchColumn();

$totalNilaiInventori = (float)$pdo->query("
    SELECT
        COALESCE((SELECT SUM(total) FROM alat_berat_sparepart), 0)
        +
        COALESCE((SELECT SUM(total) FROM alat_berat_jasa), 0)
")->fetchColumn();

/* Seluruh data untuk pagination/filter di browser */
$units = $pdo->query("
    SELECT
        id,
        kode,
        tipe,
        nomor_rangka,
        tahun_pembuatan,
        kondisi,
        status,
        lokasi
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
    <div class="panel-title">Statistik Alat Berat</div>

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

    <div class="table-toolbar alat-toolbar">

        <div class="table-title">
            <strong>Daftar Unit Alat Berat</strong>
        </div>

        <div class="table-controls">

            <label class="length-control">
                <span>Tampilkan</span>
                <select id="pageLength">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>data</span>
            </label>

            <label class="search-control">
                <span>Pencarian</span>
                <input
                    type="search"
                    id="globalSearch"
                    placeholder="Cari data..."
                    autocomplete="off"
                >
            </label>

        </div>
    </div>

    <div class="column-filter-row">

        <input type="text" class="column-filter" data-column="0" placeholder="Filter Kode">

        <input type="text" class="column-filter" data-column="1" placeholder="Filter Tipe">

        <input type="text" class="column-filter" data-column="2" placeholder="Filter Nomor Rangka">

        <input type="text" class="column-filter" data-column="3" placeholder="Filter Tahun">

        <input type="text" class="column-filter" data-column="4" placeholder="Filter Kondisi">

        <select class="column-filter" data-column="5">
            <option value="">Semua Status</option>
            <?php
            $statuses = array();

            foreach ($units as $unit) {
                $status = trim((string)$unit['status']);

                if ($status !== '' && !in_array($status, $statuses, true)) {
                    $statuses[] = $status;
                }
            }

            sort($statuses);

            foreach ($statuses as $status):
            ?>
                <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="text" class="column-filter" data-column="6" placeholder="Filter Lokasi">

        <div class="filter-action">
            <button type="button" id="clearFilters" class="btn-clear-filter">
                Reset
            </button>
        </div>

    </div>

    <div class="table-scroll">

        <table class="alat-table" id="alatBeratTable">

            <thead>
                <tr>
                    <th data-column="0">Kode <span class="sort-icon">↕</span></th>
                    <th data-column="1">Tipe <span class="sort-icon">↕</span></th>
                    <th data-column="2">Nomor Rangka <span class="sort-icon">↕</span></th>
                    <th data-column="3">Tahun Pembuatan <span class="sort-icon">↕</span></th>
                    <th data-column="4">Kondisi <span class="sort-icon">↕</span></th>
                    <th data-column="5">Status <span class="sort-icon">↕</span></th>
                    <th data-column="6">Lokasi <span class="sort-icon">↕</span></th>
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

                    <tr data-id="<?php echo (int)$unit['id']; ?>">

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

    <div class="pagination-area">

        <div class="pagination-info" id="paginationInfo">
            Menampilkan 0 data
        </div>

        <div class="pagination" id="pagination"></div>

    </div>

</section>

<script>
(function () {

    var table = document.getElementById('alatBeratTable');
    var globalSearch = document.getElementById('globalSearch');
    var pageLength = document.getElementById('pageLength');
    var clearFilters = document.getElementById('clearFilters');
    var pagination = document.getElementById('pagination');
    var paginationInfo = document.getElementById('paginationInfo');

    if (!table) {
        return;
    }

    var tbody = table.querySelector('tbody');

    var allRows = Array.prototype.slice.call(
        tbody.querySelectorAll('tr[data-id]')
    );

    var currentPage = 1;
    var currentSortColumn = -1;
    var currentSortDirection = 'asc';

    function getCellText(row, column) {
        if (!row.children[column]) {
            return '';
        }

        return row.children[column].innerText
            .trim()
            .replace(/\s+/g, ' ')
            .toLowerCase();
    }

    function getFilters() {
        var filters = [];

        document.querySelectorAll('.column-filter').forEach(function (input) {
            filters[parseInt(input.getAttribute('data-column'), 10)] =
                input.value.trim().toLowerCase();
        });

        return filters;
    }

    function getFilteredRows() {

        var search = globalSearch.value.trim().toLowerCase();
        var filters = getFilters();

        return allRows.filter(function (row) {

            if (search !== '') {

                var rowText = row.innerText
                    .trim()
                    .replace(/\s+/g, ' ')
                    .toLowerCase();

                if (rowText.indexOf(search) === -1) {
                    return false;
                }
            }

            for (var i = 0; i < filters.length; i++) {

                if (!filters[i]) {
                    continue;
                }

                var value = getCellText(row, i);

                if (i === 5) {

                    if (value !== filters[i]) {
                        return false;
                    }

                } else {

                    if (value.indexOf(filters[i]) === -1) {
                        return false;
                    }
                }
            }

            return true;
        });
    }

    function sortRows(rows) {

        if (currentSortColumn < 0) {
            return rows;
        }

        var column = currentSortColumn;
        var direction = currentSortDirection === 'asc' ? 1 : -1;

        return rows.sort(function (a, b) {

            var aText = getCellText(a, column);
            var bText = getCellText(b, column);

            if (column === 3) {

                var aNumber = parseInt(aText, 10) || 0;
                var bNumber = parseInt(bText, 10) || 0;

                return (aNumber - bNumber) * direction;
            }

            return aText.localeCompare(
                bText,
                'id',
                {
                    numeric: true,
                    sensitivity: 'base'
                }
            ) * direction;
        });
    }

    function render() {

        var filteredRows = getFilteredRows();
        var sortedRows = sortRows(filteredRows);

        var total = sortedRows.length;
        var perPage = parseInt(pageLength.value, 10) || 10;
        var totalPages = Math.ceil(total / perPage);

        if (totalPages < 1) {
            totalPages = 1;
        }

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        var start = (currentPage - 1) * perPage;
        var end = Math.min(start + perPage, total);

        allRows.forEach(function (row) {
            row.style.display = 'none';
        });

        for (var i = start; i < end; i++) {
            sortedRows[i].style.display = '';
            tbody.appendChild(sortedRows[i]);
        }

        if (total === 0) {
            paginationInfo.innerText = 'Tidak ada data yang sesuai';

        } else {

            paginationInfo.innerText =
                'Menampilkan ' +
                (start + 1) +
                ' sampai ' +
                end +
                ' dari ' +
                total +
                ' data';
        }

        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {

        pagination.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        var previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'page-button';
        previous.innerText = '‹ Sebelumnya';
        previous.disabled = currentPage === 1;

        previous.addEventListener('click', function () {

            if (currentPage > 1) {
                currentPage--;
                render();
            }

        });

        pagination.appendChild(previous);

        var maxButtons = 5;
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, startPage + maxButtons - 1);

        if ((endPage - startPage) < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        if (startPage > 1) {
            addPageButton(1);

            if (startPage > 2) {
                addEllipsis();
            }
        }

        for (var page = startPage; page <= endPage; page++) {
            addPageButton(page);
        }

        if (endPage < totalPages) {

            if (endPage < totalPages - 1) {
                addEllipsis();
            }

            addPageButton(totalPages);
        }

        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'page-button';
        next.innerText = 'Berikutnya ›';
        next.disabled = currentPage === totalPages;

        next.addEventListener('click', function () {

            if (currentPage < totalPages) {
                currentPage++;
                render();
            }

        });

        pagination.appendChild(next);
    }

    function addPageButton(page) {

        var button = document.createElement('button');

        button.type = 'button';
        button.className = 'page-button';

        if (page === currentPage) {
            button.className += ' active';
        }

        button.innerText = page;

        button.addEventListener('click', function () {
            currentPage = page;
            render();
        });

        pagination.appendChild(button);
    }

    function addEllipsis() {

        var span = document.createElement('span');

        span.className = 'pagination-ellipsis';
        span.innerText = '...';

        pagination.appendChild(span);
    }

    globalSearch.addEventListener('input', function () {
        currentPage = 1;
        render();
    });

    pageLength.addEventListener('change', function () {
        currentPage = 1;
        render();
    });

    document.querySelectorAll('.column-filter').forEach(function (input) {

        input.addEventListener('input', function () {
            currentPage = 1;
            render();
        });

        input.addEventListener('change', function () {
            currentPage = 1;
            render();
        });
    });

    clearFilters.addEventListener('click', function () {

        globalSearch.value = '';

        document.querySelectorAll('.column-filter').forEach(function (input) {
            input.value = '';
        });

        currentPage = 1;
        currentSortColumn = -1;
        currentSortDirection = 'asc';

        render();
    });

    table.querySelectorAll('thead th[data-column]').forEach(function (header) {

        header.addEventListener('click', function () {

            var column = parseInt(
                this.getAttribute('data-column'),
                10
            );

            if (currentSortColumn === column) {
                currentSortDirection =
                    currentSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortColumn = column;
                currentSortDirection = 'asc';
            }

            table.querySelectorAll('thead th').forEach(function (item) {
                item.classList.remove('sort-asc', 'sort-desc');
            });

            this.classList.add(
                currentSortDirection === 'asc'
                    ? 'sort-asc'
                    : 'sort-desc'
            );

            currentPage = 1;
            render();
        });

    });

    render();

})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
