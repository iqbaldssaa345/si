/**
 * Main JavaScript for Sistem Informasi Wisata
 */

document.addEventListener('DOMContentLoaded', () => {
  // Mobile Nav Toggle
  const mobileToggle = document.getElementById('mobileMenuToggle');
  const navLinks = document.getElementById('navLinks');
  if (mobileToggle && navLinks) {
    mobileToggle.addEventListener('click', () => {
      navLinks.classList.toggle('active');
    });
  }

  // Booking Calculator
  const tiketCountInput = document.getElementById('jumlah_tiket');
  const tipeRombonganSelect = document.getElementById('tipe_rombongan');
  const displaySubtotal = document.getElementById('displaySubtotal');
  const displayDiskon = document.getElementById('displayDiskon');
  const displayTotal = document.getElementById('displayTotal');
  const diskonRow = document.getElementById('diskonRow');

  if (tiketCountInput) {
    function calculateTicket() {
      const hargaSatuan = parseInt(tiketCountInput.dataset.harga || '0', 10);
      const diskonPersen = parseFloat(tiketCountInput.dataset.diskon || '0');
      const minRombongan = parseInt(tiketCountInput.dataset.minRombongan || '10', 10);
      let qty = parseInt(tiketCountInput.value || '1', 10);
      if (qty < 1) qty = 1;

      const isRombongan = (tipeRombonganSelect && tipeRombonganSelect.value === 'rombongan') || qty >= minRombongan;
      
      let subtotal = qty * hargaSatuan;
      let diskonNominal = 0;

      if (isRombongan && qty >= minRombongan) {
        diskonNominal = Math.round((subtotal * diskonPersen) / 100);
        if (diskonRow) diskonRow.style.display = 'flex';
      } else {
        if (diskonRow) diskonRow.style.display = 'none';
      }

      let total = subtotal - diskonNominal;

      if (displaySubtotal) displaySubtotal.innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
      if (displayDiskon) displayDiskon.innerText = '- Rp ' + diskonNominal.toLocaleString('id-ID') + ` (${diskonPersen}%)`;
      if (displayTotal) displayTotal.innerText = 'Rp ' + total.toLocaleString('id-ID');
    }

    tiketCountInput.addEventListener('input', calculateTicket);
    if (tipeRombonganSelect) {
      tipeRombonganSelect.addEventListener('change', () => {
        const minRombongan = parseInt(tiketCountInput.dataset.minRombongan || '10', 10);
        if (tipeRombonganSelect.value === 'rombongan' && parseInt(tiketCountInput.value, 10) < minRombongan) {
          tiketCountInput.value = minRombongan;
        }
        calculateTicket();
      });
    }
    calculateTicket();
  }

  // Quick Auto-Dismiss Flash Alert
  const alerts = document.querySelectorAll('.alert-dismissible');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transition = 'opacity 0.5s ease';
      setTimeout(() => alert.remove(), 500);
    }, 5000);
  });
});

// Helper for Image Preview before upload
function previewImage(input, previewElementId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.getElementById(previewElementId);
      if (img) {
        img.src = e.target.result;
        img.style.display = 'block';
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
