(function() {
    const helperUrl = new URL('../downloads/SIKSWhatsAppHelper.exe', document.baseURI).href;

    window.downloadWhatsAppHelper = async function(button) {
        if (!button || button.disabled) return;

        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = 'Menyiapkan download...';

        try {
            // Mengambil file sebagai data membuat extension download manager tidak
            // menangkap klik link langsung dari halaman invoice.
            const response = await fetch(helperUrl, { cache: 'no-store' });
            if (!response.ok) throw new Error('Helper tidak tersedia di server.');

            const blob = await response.blob();
            const blobUrl = URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.href = blobUrl;
            downloadLink.download = 'SIKSWhatsAppHelper.exe';
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            downloadLink.remove();
            setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);

            button.innerHTML = 'Download dimulai ✓';
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            }, 3000);
        } catch (error) {
            console.error('Gagal mengunduh WhatsApp Helper:', error);
            button.disabled = false;
            button.innerHTML = originalText;
            alert('Helper belum bisa diunduh. Coba gunakan Chrome/Edge tanpa extension download manager.');
        }
    };
})();
