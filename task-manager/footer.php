</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showToast(message) {
  const toastEl = document.getElementById('gm-toast');
  if (!toastEl) return;
  toastEl.querySelector('.toast-body').textContent = message;
  const toast = new bootstrap.Toast(toastEl);
  toast.show();
}
</script>
</body>
</html>
