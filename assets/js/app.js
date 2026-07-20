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
  const lightboxContent = document.querySelector('[data-lightbox-content]');
  const lightboxCanvas = document.querySelector('[data-lightbox-canvas]');
  const lightboxLoading = document.querySelector('[data-lightbox-loading]');
  const zoomLevel = document.querySelector('[data-zoom-level]');
  const zoomButtons = document.querySelectorAll('[data-lightbox-src]');
  const closeButtons = document.querySelectorAll('[data-lightbox-close]');
  const zoomInButton = document.querySelector('[data-zoom-in]');
  const zoomOutButton = document.querySelector('[data-zoom-out]');
  const zoomResetButton = document.querySelector('[data-zoom-reset]');

  if (
    lightbox &&
    lightboxImage &&
    lightboxStage &&
    lightboxContent &&
    lightboxCanvas &&
    zoomButtons.length
  ) {
    const MIN_SCALE = 1;
    const MAX_SCALE = 3;
    const SCALE_STEP = 0.5;
    let scale = MIN_SCALE;
    let baseWidth = 1;
    let baseHeight = 1;
    let lastTrigger = null;
    let resizeTimer = null;

    const clamp = function (value, min, max) {
      return Math.min(Math.max(value, min), max);
    };

    const setLoading = function (isLoading, message) {
      if (!lightboxLoading) return;
      lightboxLoading.textContent = message || 'Cargando fotografía…';
      lightboxLoading.classList.toggle('is-hidden', !isLoading);
    };

    const updateControls = function () {
      if (zoomLevel) {
        zoomLevel.textContent = scale === MIN_SCALE
          ? 'Completa'
          : Math.round(scale * 100) + '%';
      }
      if (zoomOutButton) zoomOutButton.disabled = scale <= MIN_SCALE;
      if (zoomInButton) zoomInButton.disabled = scale >= MAX_SCALE;
    };

    const setCanvasSize = function (width, height) {
      const safeWidth = Math.max(1, Math.round(width));
      const safeHeight = Math.max(1, Math.round(height));
      lightboxContent.style.setProperty('--viewer-width', safeWidth + 'px');
      lightboxContent.style.setProperty('--viewer-height', safeHeight + 'px');
      lightboxCanvas.style.width = safeWidth + 'px';
      lightboxCanvas.style.height = safeHeight + 'px';
    };

    const applyZoom = function (nextScale, preserveCenter) {
      const previousScrollWidth = Math.max(lightboxStage.scrollWidth, 1);
      const previousScrollHeight = Math.max(lightboxStage.scrollHeight, 1);
      const centerRatioX = (lightboxStage.scrollLeft + lightboxStage.clientWidth / 2) / previousScrollWidth;
      const centerRatioY = (lightboxStage.scrollTop + lightboxStage.clientHeight / 2) / previousScrollHeight;

      scale = clamp(nextScale, MIN_SCALE, MAX_SCALE);
      setCanvasSize(baseWidth * scale, baseHeight * scale);
      updateControls();

      requestAnimationFrame(function () {
        if (scale === MIN_SCALE || preserveCenter === false) {
          lightboxStage.scrollLeft = 0;
          lightboxStage.scrollTop = 0;
          return;
        }

        lightboxStage.scrollLeft = Math.max(
          0,
          centerRatioX * lightboxStage.scrollWidth - lightboxStage.clientWidth / 2
        );
        lightboxStage.scrollTop = Math.max(
          0,
          centerRatioY * lightboxStage.scrollHeight - lightboxStage.clientHeight / 2
        );
      });
    };

    const fitImageToScreen = function () {
      if (!lightboxImage.naturalWidth || !lightboxImage.naturalHeight) return;

      const compact = window.matchMedia('(max-width: 600px)').matches;
      const padding = compact ? 16 : 32;
      const availableWidth = Math.max(1, lightboxStage.clientWidth - padding);
      const availableHeight = Math.max(1, lightboxStage.clientHeight - padding);
      const fitRatio = Math.min(
        availableWidth / lightboxImage.naturalWidth,
        availableHeight / lightboxImage.naturalHeight,
        1
      );

      baseWidth = Math.max(1, lightboxImage.naturalWidth * fitRatio);
      baseHeight = Math.max(1, lightboxImage.naturalHeight * fitRatio);
      applyZoom(MIN_SCALE, false);
      setLoading(false);
    };

    const openLightbox = function (button) {
      lastTrigger = button;
      scale = MIN_SCALE;
      baseWidth = 1;
      baseHeight = 1;
      lightboxImage.alt = button.dataset.lightboxAlt || 'Fotografía completa de la prenda';
      if (lightboxTitle) {
        lightboxTitle.textContent = button.dataset.lightboxAlt || 'Detalle de la prenda';
      }

      setCanvasSize(1, 1);
      setLoading(true);
      lightbox.hidden = false;
      lightbox.setAttribute('aria-hidden', 'false');
      document.body.classList.add('lightbox-open');
      updateControls();

      lightboxImage.src = button.dataset.lightboxSrc || '';
      if (lightboxImage.complete && lightboxImage.naturalWidth) {
        requestAnimationFrame(fitImageToScreen);
      }

      const closeButton = lightbox.querySelector('.lightbox-control.close');
      if (closeButton) closeButton.focus({ preventScroll: true });
    };

    const closeLightbox = function () {
      lightbox.hidden = true;
      lightbox.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('lightbox-open');
      lightboxImage.removeAttribute('src');
      lightboxStage.scrollLeft = 0;
      lightboxStage.scrollTop = 0;
      setLoading(false);
      if (lastTrigger) lastTrigger.focus({ preventScroll: true });
    };

    lightboxImage.addEventListener('load', function () {
      requestAnimationFrame(fitImageToScreen);
    });

    lightboxImage.addEventListener('error', function () {
      setLoading(true, 'No fue posible cargar la fotografía.');
    });

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
        applyZoom(scale + SCALE_STEP, true);
      });
    }

    if (zoomOutButton) {
      zoomOutButton.addEventListener('click', function () {
        applyZoom(scale - SCALE_STEP, true);
      });
    }

    if (zoomResetButton) {
      zoomResetButton.addEventListener('click', function () {
        applyZoom(MIN_SCALE, false);
      });
    }

    document.addEventListener('keydown', function (event) {
      if (lightbox.hidden) return;

      if (event.key === 'Escape') {
        closeLightbox();
        return;
      }

      if (event.key === '+' || event.key === '=') {
        event.preventDefault();
        applyZoom(scale + SCALE_STEP, true);
      }

      if (event.key === '-') {
        event.preventDefault();
        applyZoom(scale - SCALE_STEP, true);
      }

      if (event.key === '0') {
        event.preventDefault();
        applyZoom(MIN_SCALE, false);
      }
    });

    window.addEventListener('resize', function () {
      if (lightbox.hidden) return;
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(fitImageToScreen, 120);
    });
  }
});
