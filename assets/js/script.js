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

// FCM Token Handler from Flutter WebView
window.onFcmToken = function(token) {
    if (!token) return;
    console.log("FCM Token received from Flutter:", token);
    
    // Find the base URL, assuming script is loaded from /assets/js/script.js
    const scriptTag = document.querySelector('script[src*="assets/js/script.js"]');
    let baseUrl = '/';
    if (scriptTag) {
        const src = scriptTag.getAttribute('src');
        baseUrl = src.replace('assets/js/script.js', '');
    }
    
    fetch(baseUrl + 'fcm/register_token.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ fcm_token: token })
    })
    .then(response => response.json())
    .then(data => console.log("FCM Token Registration:", data))
    .catch(error => console.error("Error registering FCM Token:", error));
};
