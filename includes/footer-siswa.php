
    <!-- Modal Rejection Reasoning -->
    <div id="rejectionModal" class="modal-rejection" style="display:none; position:fixed; z-index:1001; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); backdrop-filter:blur(5px); align-items:center; justify-content:center; padding:20px;" onclick="closeRejection(event)">
        <div class="modal-rejection-content" style="background:#1e293b; border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:30px; width:100%; max-width:400px; text-align:center;" onclick="event.stopPropagation()">
            <div class="modal-rejection-icon" style="font-size:40px; color:#ef4444; margin-bottom:20px;">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="modal-rejection-title" id="rejectionTitle" style="font-weight:800; font-size:20px; color:#fff; margin-bottom:10px;">Bulan Ditolak</div>
            <div class="modal-rejection-reason" id="rejectionReason" style="background:rgba(239,68,68,0.05); border:1px dashed #ef4444; padding:15px; border-radius:12px; color:#fff; margin-bottom:20px; font-size:14px; line-height:1.5;">Alasan penolakan tidak tersedia.</div>
            <button onclick="document.getElementById('rejectionModal').style.display='none'" class="btn btn-primary" style="width:100%;">Tutup</button>
        </div>
    </div>

    <script>
        function showRejection(title, alasan) {
            document.getElementById('rejectionTitle').innerText = title;
            document.getElementById('rejectionReason').innerText = alasan;
            document.getElementById('rejectionModal').style.display = 'flex';
        }

        function closeRejection(event) {
            if (event.target.id === 'rejectionModal') {
                document.getElementById('rejectionModal').style.display = 'none';
            }
        }
    </script>
</body>
</html>
