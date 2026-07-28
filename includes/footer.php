            </div><!-- end content-wrapper -->
        </main>
    </div>
    
    <script src="<?= BASE_URL ?>assets/js/script.js"></script>
    <script>
        // Ultimate fallback: Force click listener on the document level
        document.addEventListener('click', function(e) {
            var toggleWrap = e.target.closest('.theme-switch-wrap') || e.target.closest('.theme-switch');
            if (toggleWrap) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof toggleSiksTheme === 'function') {
                    toggleSiksTheme();
                } else {
                    var isLight = document.body.classList.toggle('light-mode');
                    localStorage.setItem('siks-theme', isLight ? 'light' : 'dark');
                }
            }
        }, true); // use capture phase to intercept before anything else blocks it!
    </script>
</body>
</html>
