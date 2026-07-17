// GALLETITAS | Interacciones básicas
document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.querySelector('[data-toggle-filters]');
  const filters = document.querySelector('[data-filters]');

  if (toggle && filters) {
    toggle.addEventListener('click', function () {
      filters.classList.toggle('open');
      toggle.textContent = filters.classList.contains('open')
        ? '✕ Ocultar filtros'
        : '☰ Mostrar filtros';
    });
  }

  const fileInput = document.querySelector('input[type="file"][name="image"]');
  const preview = document.querySelector('.preview');

  if (fileInput && preview) {
    fileInput.addEventListener('change', function () {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function () {
        preview.innerHTML = '<img src="' + reader.result + '" alt="Vista previa">';
      };
      reader.readAsDataURL(file);
    });
  }
});
