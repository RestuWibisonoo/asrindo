</main>

<footer class="admin-footer">
    <div>
        Copyright &copy; <?= date('Y') ?> - ASRINDO
    </div>
    <div>
        Version 1.0
    </div>
</footer>

<script>
(function () {
    const button = document.getElementById('mobileMenuButton');
    const nav = document.getElementById('adminNav');

    if (!button || !nav) return;

    button.addEventListener('click', function () {
        const open = nav.classList.toggle('mobile-open');
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.querySelectorAll('.nav-dropdown-button').forEach(function (dropdownButton) {
        dropdownButton.addEventListener('click', function () {
            if (window.innerWidth <= 900) {
                this.parentElement.classList.toggle('open');
            }
        });
    });
})();
</script>

</body>
</html>
