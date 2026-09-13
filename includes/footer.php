</main>

<footer class="admin-footer">
    <div>
        Copyright &copy; <?php echo date('Y'); ?> - ASRINDO - djayus.nur@gmail.com
    </div>
    <div>
        Version 1.0
    </div>
</footer>

<script>
(function () {
    var button = document.getElementById('mobileMenuButton');
    var nav = document.getElementById('adminNav');

    if (!button || !nav) {
        return;
    }

    button.addEventListener('click', function () {
        var open = nav.classList.toggle('mobile-open');
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    var dropdownButtons = document.querySelectorAll('.nav-dropdown-button');

    dropdownButtons.forEach(function (dropdownButton) {
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
