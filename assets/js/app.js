// BRENAN BOUTIQUE | Interacciones del catálogo y panel administrativo
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

  const lightbox = document.querySelector('[data-lightbox]');
  const lightboxImage = document.querySelector('[data-lightbox-image]');
  const lightboxTitle = document.querySelector('[data-lightbox-title]');
  const lightboxStage = document.querySelector('[data-lightbox-stage]');
  const zoomLevel = document.querySelector('[data-zoom-reset]');
  const zoomButtons = document.querySelectorAll('[data-lightbox-src]');
  const closeButtons = document.querySelectorAll('[data-lightbox-close]');
  const zoomInButton = document.querySelector('[data-zoom-in]');
  const zoomOutButton = document.querySelector('[data-zoom-out]');

  if (lightbox && lightboxImage && lightboxStage && zoomButtons.length) {
    let scale = 1;
    let translateX = 0;
    let translateY = 0;
    let dragging = false;
    let startX = 0;
    let startY = 0;
    let startTranslateX = 0;
    let startTranslateY = 0;
    let lastTrigger = null;

    const clamp = function (value, min, max) {
      return Math.min(Math.max(value, min), max);
    };

    const renderTransform = function () {
      scale = clamp(scale, 1, 4);
      if (scale === 1) {
        translateX = 0;
        translateY = 0;
      }

      const maxX = lightboxStage.clientWidth * (scale - 1) / (2 * scale);
      const maxY = lightboxStage.clientHeight * (scale - 1) / (2 * scale);
      translateX = clamp(translateX, -maxX, maxX);
      translateY = clamp(translateY, -maxY, maxY);

      lightboxImage.style.transform = 'translate3d(' + translateX + 'px,' + translateY + 'px,0) scale(' + scale + ')';
      lightboxStage.classList.toggle('is-zoomed', scale > 1);
      if (zoomLevel) zoomLevel.textContent = Math.round(scale * 100) + '%';
    };

    const setScale = function (nextScale) {
      scale = clamp(nextScale, 1, 4);
      renderTransform();
    };

    const resetZoom = function () {
      scale = 1;
      translateX = 0;
      translateY = 0;
      renderTransform();
    };

    const openLightbox = function (button) {
      lastTrigger = button;
      lightboxImage.src = button.dataset.lightboxSrc || '';
      lightboxImage.alt = button.dataset.lightboxAlt || 'Vista ampliada de la prenda';
      if (lightboxTitle) lightboxTitle.textContent = button.dataset.lightboxAlt || 'Detalle de la prenda';
      resetZoom();
      lightbox.hidden = false;
      lightbox.setAttribute('aria-hidden', 'false');
      document.body.classList.add('lightbox-open');
      const closeButton = lightbox.querySelector('.lightbox-control.close');
      if (closeButton) closeButton.focus();
    };

    const closeLightbox = function () {
      lightbox.hidden = true;
      lightbox.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('lightbox-open');
      lightboxImage.src = '';
      resetZoom();
      if (lastTrigger) lastTrigger.focus();
    };

    zoomButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        openLightbox(button);
      });
    });

    closeButtons.forEach(function (button) {
      button.addEventListener('click', closeLightbox);
    });

    if (zoomInButton) {
      zoomInButton.addEventListener('click', function () {
        setScale(scale + 0.25);
      });
    }

    if (zoomOutButton) {
      zoomOutButton.addEventListener('click', function () {
        setScale(scale - 0.25);
      });
    }

    if (zoomLevel) {
      zoomLevel.addEventListener('click', resetZoom);
    }

    lightboxStage.addEventListener('wheel', function (event) {
      event.preventDefault();
      setScale(scale + (event.deltaY < 0 ? 0.25 : -0.25));
    }, { passive: false });

    lightboxStage.addEventListener('dblclick', function () {
      setScale(scale > 1 ? 1 : 2);
    });

    lightboxStage.addEventListener('pointerdown', function (event) {
      if (scale <= 1) return;
      dragging = true;
      startX = event.clientX;
      startY = event.clientY;
      startTranslateX = translateX;
      startTranslateY = translateY;
      lightboxStage.classList.add('is-dragging');
      lightboxStage.setPointerCapture(event.pointerId);
    });

    lightboxStage.addEventListener('pointermove', function (event) {
      if (!dragging) return;
      translateX = startTranslateX + (event.clientX - startX) / scale;
      translateY = startTranslateY + (event.clientY - startY) / scale;
      renderTransform();
    });

    const stopDragging = function (event) {
      if (!dragging) return;
      dragging = false;
      lightboxStage.classList.remove('is-dragging');
      if (event.pointerId !== undefined && lightboxStage.hasPointerCapture(event.pointerId)) {
        lightboxStage.releasePointerCapture(event.pointerId);
      }
    };

    lightboxStage.addEventListener('pointerup', stopDragging);
    lightboxStage.addEventListener('pointercancel', stopDragging);

    document.addEventListener('keydown', function (event) {
      if (lightbox.hidden) return;
      if (event.key === 'Escape') closeLightbox();
      if (event.key === '+' || event.key === '=') setScale(scale + 0.25);
      if (event.key === '-') setScale(scale - 0.25);
      if (event.key === '0') resetZoom();
    });

    window.addEventListener('resize', renderTransform);
  }
});
