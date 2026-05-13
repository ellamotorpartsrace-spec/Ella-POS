<footer class="text-center text-muted small py-3 mt-auto">
    &copy; <?= date('Y') ?> Ella Motor Parts | Developed by Benedict Ramirez
</footer>
</div>
</div>
</div>
<script src="<?= BASE_URL ?>assets/css/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/theme-toggle.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/ella-toast.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/ella-confirm.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/ella-hotkeys.js" defer></script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var sidebar = document.getElementById("sidebar");
        var sidebarOverlay = document.getElementById("sidebarOverlay");
        var toggleButton = document.getElementById("sidebarToggle");
        var closeButton = document.getElementById("sidebarClose");
        var wrapper = document.getElementById("wrapper");

        // Re-enable transitions after load
        window.addEventListener('load', function () {
            document.body.classList.remove('preload');
        });

        // Function to toggle sidebar based on screen size
        function toggleSidebar() {
            if (window.innerWidth >= 992) {
                // Desktop: Toggle class on wrapper
                wrapper.classList.toggle("toggled");
                // Save state
                const isClosed = wrapper.classList.contains("toggled");
                localStorage.setItem('sidebarState', isClosed ? 'closed' : 'open');
            } else {
                // Mobile: Use existing overlay logic
                if (sidebar.classList.contains("open")) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
            }
        }

        function openMobileSidebar() {
            sidebar.classList.add("open");
            sidebarOverlay.classList.add("active");
            document.body.style.overflow = "hidden";
        }

        function closeMobileSidebar() {
            sidebar.classList.remove("open");
            sidebarOverlay.classList.remove("active");
            document.body.style.overflow = "";
        }

        // Toggle button click
        toggleButton.onclick = function (e) {
            e.stopPropagation();
            toggleSidebar();

            // Adjust search filter visibility if needed
            if (window.innerWidth >= 992) {
                const searchInput = document.getElementById('sidebarSearch');
                if (searchInput) searchInput.dispatchEvent(new Event('input'));
            }
        };

        // Close button click (Mobile mainly)
        if (closeButton) {
            closeButton.onclick = function () {
                closeMobileSidebar();
            };
        }

        // Overlay click to close
        if (sidebarOverlay) {
            sidebarOverlay.onclick = function () {
                closeMobileSidebar();
            };
        }

        // Close sidebar when clicking on a nav link
        var navLinks = sidebar.querySelectorAll("ul.components a");
        navLinks.forEach(function (link) {
            link.addEventListener("click", function (e) {
                if (window.innerWidth < 992) {
                    closeMobileSidebar();
                } else {
                    // Automatically minimize on redirect to maximize workspace
                    localStorage.setItem('sidebarState', 'closed');
                    wrapper.classList.add("toggled");
                }
            });
        });

        // Initialize Desktop Sidebar State from localStorage
        if (window.innerWidth >= 992) {
            const savedState = localStorage.getItem('sidebarState');
            if (savedState === 'closed') {
                wrapper.classList.add("toggled");
                // Trigger an input event to update heading visibility in mini-mode
                const searchInput = document.getElementById('sidebarSearch');
                if (searchInput) searchInput.dispatchEvent(new Event('input'));
            } else {
                wrapper.classList.remove("toggled");
            }
        }

        // Close sidebar on Escape key
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                if (window.innerWidth < 992 && sidebar.classList.contains("open")) {
                    closeMobileSidebar();
                }
            }
        });
    });
</script>

</body>

</html>