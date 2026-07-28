document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });

        // Close when clicking outside or clicking a nav link
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('active')) {
                const isClickInside = sidebar.contains(e.target) || (menuToggle && menuToggle.contains(e.target));
                // Because the overlay is a ::before pseudo-element of .sidebar, 
                // clicking the overlay counts as clicking inside. We check clientX > 250 to fix this.
                if (!isClickInside || e.clientX > 250) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Auto close on mobile when link is clicked
        sidebar.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                }
            });
        });
    }
    
    // Auto hide alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Confirm delete
    document.querySelectorAll('.btn-delete, [data-confirm]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm(btn.dataset.confirm || 'Apakah Anda yakin ingin menghapus data ini?')) {
                e.preventDefault();
            }
        });
    });
    
    // Search
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.table tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        }, 300));
    }
});

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function printPage() { window.print(); }
