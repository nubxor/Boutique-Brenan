// BRENAN BOUTIQUE | Interacciones del catálogo y panel administrativo
document.addEventListener('DOMContentLoaded', function () {
  const activeCategoryChip = document.querySelector('[data-category-chips] .category-chip.is-active');
  if (activeCategoryChip) {
    window.requestAnimationFrame(function () {
      const strip = activeCategoryChip.closest('[data-category-chips]');
      if (!strip) return;
      const chipRect = activeCategoryChip.getBoundingClientRect();
      const stripRect = strip.getBoundingClientRect();
      if (chipRect.left < stripRect.left || chipRect.right > stripRect.right) {
        activeCategoryChip.scrollIntoView({ block: 'nearest', inline: 'center' });
      }
    });
  }

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

  const parseImageFallbacks = function (value) {
    if (!value) return [];

    try {
      const parsed = JSON.parse(value);
      return Array.isArray(parsed)
        ? parsed.filter(function (url) { return typeof url === 'string' && url !== ''; })
        : [];
    } catch (error) {
      return [];
    }
  };

  const absoluteUrl = function (url) {
    try {
      return new URL(url, window.location.href).href;
    } catch (error) {
      return url;
    }
  };

  const installImageFallback = function (image) {
    const candidates = parseImageFallbacks(image.dataset.imageFallbacks);
    const attempted = new Set();
    let finished = false;

    const removeResponsiveSources = function () {
      const picture = image.closest('picture');
      if (picture) {
        picture.querySelectorAll('source').forEach(function (source) {
          source.remove();
        });
      }

      image.removeAttribute('srcset');
      image.removeAttribute('sizes');
    };

    const showUnavailableState = function () {
      finished = true;
      image.classList.add('image-load-failed');
      const holder = image.closest('.photo-zoom, .preview, td, .photo, .new-arrival-photo, .related-photo, .product-main-photo');
      if (holder) {
        holder.classList.add('image-unavailable');
        holder.classList.remove('image-pending');
      }
    };

    const tryNext = function () {
      if (finished) return;

      removeResponsiveSources();
      attempted.add(absoluteUrl(image.currentSrc || image.src || ''));

      while (candidates.length) {
        const next = candidates.shift();
        const normalized = absoluteUrl(next);
        if (!normalized || attempted.has(normalized)) continue;

        attempted.add(normalized);
        image.src = next;
        return;
      }

      showUnavailableState();
    };

    image.addEventListener('load', function () {
      image.classList.remove('image-load-failed');
      image.classList.add('is-image-ready');
      const holder = image.closest('.photo-zoom, .preview, td, .photo, .new-arrival-photo, .related-photo, .product-main-photo');
      if (holder) {
        holder.classList.remove('image-unavailable');
        holder.classList.remove('image-pending');
        holder.classList.add('is-image-ready');
      }
    });

    image.addEventListener('error', tryNext);

    if (image.complete && image.naturalWidth === 0) {
      window.setTimeout(tryNext, 0);
    }
  };

  document.querySelectorAll('img[data-image-fallbacks]').forEach(installImageFallback);

  document.querySelectorAll('.image-pending img').forEach(function (image) {
    if (image.complete && image.naturalWidth > 0) {
      image.classList.add('is-image-ready');
      const holder = image.closest('.image-pending');
      if (holder) {
        holder.classList.remove('image-pending');
        holder.classList.add('is-image-ready');
      }
    }
  });

  document.querySelectorAll('[data-focus-search]').forEach(function (button) {
    button.addEventListener('click', function () {
      const search = document.querySelector('#busqueda-prendas');
      if (!search) return;
      search.scrollIntoView({ behavior: 'smooth', block: 'center' });
      window.setTimeout(function () { search.focus({ preventScroll: true }); }, 350);
    });
  });

  const fileInput = document.querySelector('input[type="file"][name="image"]');
  const preview = document.querySelector('.preview');
  const uploadStatus = document.querySelector('[data-upload-status]');

  if (fileInput && preview) {
    const uploadForm = fileInput.closest('form');
    const submitButton = uploadForm ? uploadForm.querySelector('button[type="submit"]') : null;
    const MAX_CLIENT_EDGE = 2000;
    const CLIENT_QUALITY = 0.84;
    let previewUrl = '';
    let processingToken = 0;

    const formatMegabytes = function (bytes) {
      return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    };

    const setUploadState = function (message, busy) {
      if (uploadStatus) {
        uploadStatus.hidden = false;
        uploadStatus.textContent = message;
        uploadStatus.classList.toggle('is-processing', Boolean(busy));
      }

      if (submitButton) {
        submitButton.disabled = Boolean(busy);
        submitButton.textContent = busy ? 'Preparando imagen…' : 'Guardar vestido';
      }
    };

    const loadLocalImage = function (file) {
      return new Promise(function (resolve, reject) {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = function () {
          resolve({ image: image, url: url });
        };

        image.onerror = function () {
          URL.revokeObjectURL(url);
          reject(new Error('No se pudo leer la imagen.'));
        };

        image.src = url;
      });
    };

    const canvasToBlob = function (canvas, type, quality) {
      return new Promise(function (resolve) {
        canvas.toBlob(resolve, type, quality);
      });
    };

    const optimizeBeforeUpload = async function (file) {
      const canOptimize =
        typeof DataTransfer === 'function' &&
        typeof HTMLCanvasElement !== 'undefined' &&
        typeof HTMLCanvasElement.prototype.toBlob === 'function' &&
        file.type !== 'image/gif';

      const loaded = await loadLocalImage(file);
      const image = loaded.image;
      const width = image.naturalWidth;
      const height = image.naturalHeight;

      if (!canOptimize || !width || !height) {
        return { file: file, width: width, height: height, optimized: false, sourceUrl: loaded.url };
      }

      const ratio = Math.min(MAX_CLIENT_EDGE / width, MAX_CLIENT_EDGE / height, 1);
      const targetWidth = Math.max(1, Math.round(width * ratio));
      const targetHeight = Math.max(1, Math.round(height * ratio));
      const shouldCompress = ratio < 1 || file.size > 900 * 1024;

      if (!shouldCompress) {
        return { file: file, width: width, height: height, optimized: false, sourceUrl: loaded.url };
      }

      const canvas = document.createElement('canvas');
      canvas.width = targetWidth;
      canvas.height = targetHeight;

      const context = canvas.getContext('2d', { alpha: false });
      if (!context) {
        return { file: file, width: width, height: height, optimized: false, sourceUrl: loaded.url };
      }

      context.fillStyle = '#ffffff';
      context.fillRect(0, 0, targetWidth, targetHeight);
      context.drawImage(image, 0, 0, targetWidth, targetHeight);

      const blob = await canvasToBlob(canvas, 'image/jpeg', CLIENT_QUALITY);
      const extension = 'jpg';

      if (!blob || (ratio === 1 && blob.size >= file.size)) {
        return { file: file, width: width, height: height, optimized: false, sourceUrl: loaded.url };
      }

      URL.revokeObjectURL(loaded.url);
      const originalStem = file.name.replace(/\.[^.]+$/, '') || 'fotografia';
      const optimizedFile = new File(
        [blob],
        originalStem + '-optimizada.' + extension,
        { type: blob.type, lastModified: Date.now() }
      );

      return {
        file: optimizedFile,
        width: targetWidth,
        height: targetHeight,
        optimized: true,
        sourceUrl: URL.createObjectURL(optimizedFile),
        originalSize: file.size
      };
    };

    fileInput.addEventListener('change', async function () {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;

      const token = ++processingToken;
      setUploadState('Preparando una versión ligera antes de subirla…', true);

      try {
        const result = await optimizeBeforeUpload(file);
        if (token !== processingToken) {
          URL.revokeObjectURL(result.sourceUrl);
          return;
        }

        if (result.optimized) {
          const transfer = new DataTransfer();
          transfer.items.add(result.file);
          fileInput.files = transfer.files;
        }

        if (previewUrl) {
          URL.revokeObjectURL(previewUrl);
        }
        previewUrl = result.sourceUrl;

        const image = document.createElement('img');
        image.src = previewUrl;
        image.alt = 'Vista previa de la fotografía seleccionada';
        image.decoding = 'async';
        preview.replaceChildren(image);

        if (result.optimized) {
          setUploadState(
            'Lista para subir: ' + result.width + ' × ' + result.height + ' px. ' +
            formatMegabytes(result.originalSize) + ' → ' + formatMegabytes(result.file.size) +
            '. El servidor creará además las resoluciones del catálogo.',
            false
          );
        } else {
          setUploadState(
            'Imagen seleccionada: ' + result.width + ' × ' + result.height + ' px, ' +
            formatMegabytes(result.file.size) +
            '. El servidor la optimizará al guardar.',
            false
          );
        }
      } catch (error) {
        setUploadState('La vista previa no pudo optimizarse; el servidor intentará procesarla al guardar.', false);
      }
    });

    window.addEventListener('beforeunload', function () {
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
      }
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
    let lightboxFallbacks = [];
    let lightboxFallbackIndex = 0;

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

      const primaryUrl = button.dataset.lightboxSrc || '';
      const extraFallbacks = parseImageFallbacks(button.dataset.lightboxFallbacks);
      lightboxFallbacks = Array.from(new Set([primaryUrl].concat(extraFallbacks).filter(Boolean)));
      lightboxFallbackIndex = 0;

      if (lightboxFallbacks.length) {
        lightboxImage.src = lightboxFallbacks[0];
      } else {
        setLoading(true, 'No hay una fotografía disponible para esta prenda.');
      }

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
      lightboxFallbacks = [];
      lightboxFallbackIndex = 0;
      lightboxStage.scrollLeft = 0;
      lightboxStage.scrollTop = 0;
      setLoading(false);
      if (lastTrigger) lastTrigger.focus({ preventScroll: true });
    };

    lightboxImage.addEventListener('load', function () {
      requestAnimationFrame(fitImageToScreen);
    });

    lightboxImage.addEventListener('error', function () {
      lightboxFallbackIndex += 1;

      if (lightboxFallbackIndex < lightboxFallbacks.length) {
        setLoading(true, 'Cargando una versión compatible…');
        lightboxImage.src = lightboxFallbacks[lightboxFallbackIndex];
        return;
      }

      setLoading(true, 'No fue posible cargar la fotografía. Repárala desde el panel administrativo.');
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

  // Favoritos locales: no requiere cuenta y no guarda información personal.
  const favoriteButtons = document.querySelectorAll('[data-favorite-toggle]');
  const favoriteCountLabels = document.querySelectorAll('[data-favorites-count]');
  const favoritesBanner = document.querySelector('[data-favorites-banner]');
  const favoritesEmpty = document.querySelector('[data-favorites-empty]');
  const FAVORITES_KEY = 'brenan_boutique_favorites_v1';

  const readFavorites = function () {
    try {
      const stored = JSON.parse(window.localStorage.getItem(FAVORITES_KEY) || '[]');
      return new Set(Array.isArray(stored) ? stored.map(String) : []);
    } catch (error) {
      return new Set();
    }
  };

  let favorites = readFavorites();

  const saveFavorites = function () {
    try {
      window.localStorage.setItem(FAVORITES_KEY, JSON.stringify(Array.from(favorites)));
    } catch (error) {
      // El catálogo continúa funcionando aunque el navegador bloquee el almacenamiento local.
    }
  };

  const updateFavoriteButton = function (button) {
    const id = String(button.dataset.productId || '');
    const selected = id !== '' && favorites.has(id);
    button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    button.classList.toggle('is-favorite', selected);

    const icon = button.querySelector('span[aria-hidden="true"]');
    if (icon) icon.textContent = selected ? '♥' : '♡';

    const label = button.querySelector('[data-favorite-label]');
    if (label) label.textContent = selected ? 'Guardada en favoritos' : 'Guardar en favoritos';

    const name = button.dataset.productName || 'esta prenda';
    button.setAttribute(
      'aria-label',
      selected ? 'Quitar ' + name + ' de favoritos' : 'Guardar ' + name + ' en favoritos'
    );
  };

  const updateFavoriteUI = function () {
    favoriteButtons.forEach(updateFavoriteButton);
    favoriteCountLabels.forEach(function (label) {
      label.textContent = String(favorites.size);
      label.hidden = favorites.size === 0;
    });
  };

  favoriteButtons.forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      const id = String(button.dataset.productId || '');
      if (!id) return;

      if (favorites.has(id)) favorites.delete(id);
      else favorites.add(id);

      saveFavorites();
      updateFavoriteUI();

      if (document.body.classList.contains('favorites-view')) {
        window.setTimeout(applyFavoritesView, 0);
      }
    });
  });

  const applyFavoritesView = function () {
    const cards = Array.from(document.querySelectorAll('[data-product-card]'));
    let visible = 0;

    cards.forEach(function (card) {
      const id = String(card.dataset.favoriteItem || '');
      const show = favorites.has(id);
      card.hidden = !show;
      if (show) visible += 1;
    });

    document.querySelectorAll('[data-product-section]').forEach(function (section) {
      section.hidden = !section.querySelector('[data-product-card]:not([hidden])');
    });

    const newArrivals = document.querySelector('.new-arrivals');
    if (newArrivals) newArrivals.hidden = true;
    if (favoritesBanner) favoritesBanner.hidden = false;
    if (favoritesEmpty) favoritesEmpty.hidden = visible > 0;
  };

  const params = new URLSearchParams(window.location.search);
  if (params.get('view') === 'favorites') {
    document.body.classList.add('favorites-view');
    applyFavoritesView();
  }

  updateFavoriteUI();

  // Botón para compartir cada prenda.
  const shareButtons = document.querySelectorAll('[data-share-product]');

  if (shareButtons.length) {
    let toastTimer = null;
    const shareToast = document.createElement('div');
    shareToast.className = 'share-toast';
    shareToast.setAttribute('role', 'status');
    shareToast.setAttribute('aria-live', 'polite');
    shareToast.setAttribute('aria-atomic', 'true');
    document.body.appendChild(shareToast);

    const showShareToast = function (message) {
      shareToast.textContent = message;
      shareToast.classList.add('is-visible');
      window.clearTimeout(toastTimer);
      toastTimer = window.setTimeout(function () {
        shareToast.classList.remove('is-visible');
      }, 2600);
    };

    const buildProductUrl = function (button) {
      const directUrl = button.dataset.productUrl || '';
      if (directUrl) {
        return new URL(directUrl, window.location.href).href;
      }

      const productId = button.dataset.productId || '';
      const url = new URL(window.location.href);
      url.hash = productId ? 'prenda-' + productId : 'catalogo';
      return url.href;
    };

    const buildShareText = function (button) {
      const name = button.dataset.productName || 'Prenda';
      const category = button.dataset.productCategory || '';
      const size = button.dataset.productSize || '';
      const price = button.dataset.productPrice || '';
      const status = button.dataset.productStatus || '';
      const details = [];

      if (category) details.push(category);
      if (size) details.push('Talla ' + size);
      if (price) details.push(price);
      if (status) details.push(status);

      return 'Mira esta prenda en Brenan Boutique: ' + name +
        (details.length ? ' · ' + details.join(' · ') : '') + '.';
    };

    const copyShareContent = async function (text, url) {
      const content = text + '\n' + url;

      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(content);
        return;
      }

      const textarea = document.createElement('textarea');
      textarea.value = content;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      textarea.style.pointerEvents = 'none';
      document.body.appendChild(textarea);
      textarea.select();
      textarea.setSelectionRange(0, textarea.value.length);
      const copied = document.execCommand('copy');
      textarea.remove();

      if (!copied) {
        throw new Error('No fue posible copiar el enlace.');
      }
    };

    shareButtons.forEach(function (button) {
      button.addEventListener('click', async function () {
        if (button.classList.contains('is-sharing')) return;

        const url = buildProductUrl(button);
        const text = buildShareText(button);
        const title = 'Brenan Boutique · ' + (button.dataset.productName || 'Prenda');
        button.classList.add('is-sharing');
        button.disabled = true;

        try {
          if (typeof navigator.share === 'function') {
            await navigator.share({ title: title, text: text, url: url });
            showShareToast('Prenda compartida');
          } else {
            await copyShareContent(text, url);
            showShareToast('Información y enlace copiados');
          }
        } catch (error) {
          if (error && error.name === 'AbortError') {
            return;
          }

          try {
            await copyShareContent(text, url);
            showShareToast('Información y enlace copiados');
          } catch (copyError) {
            showShareToast('No fue posible compartir esta prenda');
          }
        } finally {
          button.disabled = false;
          button.classList.remove('is-sharing');
        }
      });
    });

    const targetHash = decodeURIComponent(window.location.hash || '');
    if (/^#prenda-\d+$/.test(targetHash)) {
      const sharedCard = document.querySelector(targetHash);
      if (sharedCard) {
        window.setTimeout(function () {
          sharedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
          sharedCard.classList.add('is-shared-target');
          window.setTimeout(function () {
            sharedCard.classList.remove('is-shared-target');
          }, 3200);
        }, 350);
      }
    }
  }

});
