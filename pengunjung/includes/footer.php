  </div><!-- End .admin-layout -->

  <!-- Toast Element -->
  <div id="luxury-toast">
    <i class="fa-solid fa-circle-check text-emerald-400"></i>
    <span id="luxury-toast-msg">Notifikasi berhasil!</span>
  </div>

  <script src="<?= BASE_URL ?>assets/js/main.js"></script>
  <script>
    function openLuxuryModal(id) {
      const modal = document.getElementById(id);
      if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    }

    function closeLuxuryModal(id) {
      const modal = document.getElementById(id);
      if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
      }
    }

    // Close on backdrop click
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('luxury-modal-backdrop')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
      }
    });

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.luxury-modal-backdrop.active').forEach(m => {
          m.classList.remove('active');
        });
        document.body.style.overflow = '';
      }
    });

    // Copy to clipboard with toast notification
    function copyToClipboard(text, label = 'Teks') {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
          showToast(label + ' berhasil disalin!');
        }).catch(() => {
          fallbackCopy(text, label);
        });
      } else {
        fallbackCopy(text, label);
      }
    }

    function fallbackCopy(text, label) {
      const input = document.createElement('input');
      input.value = text;
      document.body.appendChild(input);
      input.select();
      document.execCommand('copy');
      document.body.removeChild(input);
      showToast(label + ' berhasil disalin!');
    }

    function showToast(message) {
      const toast = document.getElementById('luxury-toast');
      const msg = document.getElementById('luxury-toast-msg');
      if (toast && msg) {
        msg.innerHTML = message;
        toast.classList.add('show');
        setTimeout(() => {
          toast.classList.remove('show');
        }, 2800);
      }
    }
  </script>
</body>
</html>
