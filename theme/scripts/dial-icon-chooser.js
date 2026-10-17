/* One account-scoped icon chooser for add, edit, and quick Change icon. */
(() => {
  'use strict';

  const dialog = document.getElementById('dial-icon-chooser');
  if (!dialog) return;

  const copy = window.DialIconCopy || {};
  const readonly = dialog.dataset.demoMode === '1';
  const blank = () => ({
    source: 'original', catalog_key: '', asset_id: '',
    presentation: 'plain', treatment: 'color', scale: 100, explicit: false,
  });
  let committed = blank();
  let draft = blank();
  let original = { src: '', variant_src: '', native_text_color: null, fallback_text: '?' };
  let activeTab = 'original';
  let assets = [];
  let openedBy = null;
  let onSelect = null;
  let onCancel = null;
  let accepted = false;
  let busy = false;
  let libraryVersion = 0;

  const status = document.getElementById('dial-icon-status');
  const library = document.getElementById('dial-icon-library-options');
  const scaleInput = document.getElementById('dial-icon-scale');
  const catalogButtons = [...dialog.querySelectorAll('[data-catalog-key]')];
  const sourceButtons = [...dialog.querySelectorAll('[data-dial-source]')];

  function setStatus(message = '', isError = false, isWarning = false) {
    status.textContent = message;
    status.dataset.state = isError ? 'error' : isWarning ? 'warning' : 'ready';
    status.setAttribute('role', isError ? 'alert' : 'status');
  }

  function formatMiB(bytes) {
    return (bytes / 1048576).toFixed(2).replace(/\.?0+$/, '');
  }

  function iconUsage(data) {
    const count = Number(data?.count);
    const bytes = Number(data?.bytes);
    const limitCount = Number(data?.limit_count);
    const limitBytes = Number(data?.limit_bytes);
    if (![count, bytes, limitCount, limitBytes].every(Number.isSafeInteger) ||
        count < 0 || bytes < 0 || limitCount <= 0 || limitBytes <= 0) return null;
    return {
      count: String(count), limitCount: String(limitCount),
      usedMib: formatMiB(bytes), limitMib: formatMiB(limitBytes),
    };
  }

  function formatUsage(template, values) {
    return template.replace(/\{(count|limitCount|usedMib|limitMib)\}/g,
      (_, key) => values[key]);
  }

  function renderUsage(data) {
    const values = iconUsage(data);
    for (const id of ['dial-icon-library-usage', 'dial-icon-upload-usage']) {
      const label = document.getElementById(id);
      label.textContent = values ? formatUsage(
        copy.usage || 'My icons: {count}/{limitCount} · {usedMib}/{limitMib} MiB used', values) : '';
    }
    return values;
  }

  function showQuota(data) {
    const values = renderUsage(data);
    if (!values) {
      setStatus(copy.uploadError || 'Could not upload the icon.', true);
      return;
    }
    const message = data.code === 'icon_quota_count'
      ? (copy.quotaCount || 'My icons is full ({count}/{limitCount}). Delete an unused icon.')
      : (copy.quotaBytes || 'My icons storage is full ({usedMib}/{limitMib} MiB). Delete an unused icon.');
    setStatus(formatUsage(message, values), true);
    status.append(document.createTextNode(' '));
    const manage = document.createElement('button');
    manage.id = 'dial-icon-quota-manage';
    manage.type = 'button';
    manage.className = 'dial-icon-quota-manage';
    manage.textContent = copy.manageIcons || 'Manage my icons';
    manage.addEventListener('click', async () => {
      activeTab = 'library';
      sync();
      sourceButtons.find(button => button.dataset.dialSource === 'library')?.focus();
      await loadLibrary();
      library.querySelector('.dial-icon-delete')?.focus();
    });
    status.append(manage);
    status.scrollIntoView({ block: 'nearest' });
    manage.focus();
  }

  function safeImageName(value) {
    if (typeof value !== 'string') return '';
    return value.split(/[\\/]/).pop() || '';
  }

  function lowResolutionWarning(asset) {
    const width = Number(asset?.width);
    const height = Number(asset?.height);
    if (!Number.isInteger(width) || !Number.isInteger(height) ||
        width < 1 || height < 1 || Math.max(width, height) >= 128) return '';
    return `${copy.lowResolution || 'Low resolution; may look blurry.'} ${width} × ${height} px`;
  }

  function normalize(input, explicit = true) {
    const next = blank();
    if (!input || typeof input !== 'object') return next;
    next.source = ['original', 'catalog', 'asset'].includes(input.source) ? input.source : 'original';
    next.catalog_key = next.source === 'catalog' && typeof input.catalog_key === 'string' ? input.catalog_key : '';
    next.asset_id = next.source === 'asset' && Number.isSafeInteger(Number(input.asset_id)) && Number(input.asset_id) > 0
      ? String(Number(input.asset_id)) : '';
    next.presentation = input.presentation === 'framed' ? 'framed' : 'plain';
    next.treatment = input.treatment === 'mono' ? 'mono' : 'color';
    const scale = Number(input.scale);
    next.scale = Number.isInteger(scale) && scale >= 80 && scale <= 120 ? scale : 100;
    next.explicit = explicit;
    if (next.source === 'catalog' && !catalogButtons.some(button => button.dataset.catalogKey === next.catalog_key)) {
      next.source = 'original';
      next.catalog_key = '';
    }
    return next;
  }

  function setFromSubscription(subscription) {
    if (!subscription || typeof subscription !== 'object') return;
    const logo = safeImageName(subscription.logo);
    const variant = safeImageName(subscription.logo_variant);
    const name = typeof subscription.name === 'string' ? subscription.name.trim() : '';
    original = {
      src: logo ? `images/uploads/logos/${encodeURIComponent(logo)}` : '',
      variant_src: logo && variant ? `images/uploads/logos/${encodeURIComponent(variant)}` : '',
      native_text_color: ['dark', 'light'].includes(subscription.logo_text_color)
        ? subscription.logo_text_color : null,
      fallback_text: Array.from(name)[0] || '?',
    };
    const preference = subscription.dial_icon_preference || null;
    committed = preference ? normalize(preference) : blank();
    draft = { ...committed };
    activeTab = committed.source === 'asset' ? 'library' : committed.source;
    sync();
  }

  function reset() {
    if (dialog.open) dialog.close('reset');
    libraryVersion++;
    committed = blank();
    draft = blank();
    original = { src: '', variant_src: '', native_text_color: null, fallback_text: '?' };
    activeTab = 'original';
    assets = [];
    setStatus();
    renderUsage(null);
    sync();
  }

  function formFields() {
    const fields = {
      dial_icon_source: '', dial_icon_catalog_key: '', dial_icon_asset_id: '',
      dial_icon_presentation: '', dial_icon_treatment: '', dial_icon_scale: '',
    };
    if (!committed.explicit) return fields;
    fields.dial_icon_source = committed.source;
    fields.dial_icon_catalog_key = committed.source === 'catalog' ? committed.catalog_key : '';
    fields.dial_icon_asset_id = committed.source === 'asset' ? committed.asset_id : '';
    fields.dial_icon_presentation = committed.presentation;
    fields.dial_icon_treatment = committed.treatment;
    fields.dial_icon_scale = String(committed.scale);
    return fields;
  }

  function iconSource(tone) {
    if (draft.source === 'catalog') {
      const button = catalogButtons.find(item => item.dataset.catalogKey === draft.catalog_key);
      return button ? {
        src: button.querySelector('img')?.getAttribute('src') || '',
        optical: Number(button.dataset.opticalScale) || 1,
      } : { src: '', optical: 1 };
    }
    if (draft.source === 'asset') {
      return assets.some(item => String(item.id) === draft.asset_id)
        ? { src: `/endpoints/dial/icon.php?id=${draft.asset_id}`, optical: 1 }
        : { src: '', optical: 1 };
    }
    let src = original.src;
    if (original.variant_src && original.native_text_color) {
      const nativeTone = original.native_text_color === 'dark' ? 'warm' : 'dark';
      if (tone !== nativeTone) src = original.variant_src;
    }
    return { src, optical: 1 };
  }

  function paintPreview() {
    for (const surface of dialog.querySelectorAll('[data-preview-tone]')) {
      const tone = surface.dataset.previewTone;
      const box = surface.querySelector('.dial-icon-preview-art');
      const { src, optical } = iconSource(tone);
      box.replaceChildren();
      box.dataset.source = draft.source;
      box.dataset.presentation = draft.presentation;
      box.dataset.treatment = draft.treatment;
      box.style.setProperty('--dial-preview-scale', String((draft.scale / 100) * Math.max(.8, Math.min(1.2, optical))));
      if (src) {
        const image = document.createElement('img');
        image.src = src;
        image.alt = '';
        image.decoding = 'async';
        box.append(image);
      } else {
        const initial = document.createElement('span');
        initial.className = 'dial-icon-preview-fallback';
        initial.textContent = original.fallback_text;
        box.append(initial);
      }
    }
    const originalChoice = document.getElementById('dial-icon-original-choice');
    originalChoice.replaceChildren();
    if (original.src) {
      const image = document.createElement('img');
      image.src = original.src;
      image.alt = '';
      originalChoice.append(image);
    } else {
      const letter = document.createElement('span');
      letter.textContent = original.fallback_text;
      originalChoice.append(letter);
    }
  }

  function sync() {
    for (const button of sourceButtons) {
      button.setAttribute('aria-pressed', String(button.dataset.dialSource === activeTab));
    }
    for (const panel of dialog.querySelectorAll('.dial-icon-panel')) {
      panel.hidden = panel.id !== `dial-icon-panel-${activeTab}`;
    }
    for (const button of catalogButtons) {
      button.setAttribute('aria-pressed', String(draft.source === 'catalog' && button.dataset.catalogKey === draft.catalog_key));
    }
    for (const button of dialog.querySelectorAll('[data-asset-id]')) {
      button.setAttribute('aria-pressed', String(draft.source === 'asset' && button.dataset.assetId === draft.asset_id));
    }
    for (const button of dialog.querySelectorAll('[data-dial-presentation]')) {
      button.setAttribute('aria-pressed', String(button.dataset.dialPresentation === draft.presentation));
    }
    for (const button of dialog.querySelectorAll('[data-dial-treatment]')) {
      button.setAttribute('aria-pressed', String(button.dataset.dialTreatment === draft.treatment));
    }
    scaleInput.value = String(draft.scale);
    document.getElementById('dial-icon-scale-value').value = `${draft.scale}%`;
    const summary = document.getElementById('dial-icon-current-label');
    if (summary) {
      let label = copy.original || 'Original';
      if (committed.source === 'catalog') {
        const selected = catalogButtons.find(button => button.dataset.catalogKey === committed.catalog_key);
        label = selected?.querySelector('span:last-child')?.textContent || label;
      } else if (committed.source === 'asset') {
        label = assets.find(item => String(item.id) === committed.asset_id)?.label || copy.library || 'My icons';
      }
      summary.textContent = label;
    }
    paintPreview();
  }

  function renderLibrary() {
    library.replaceChildren();
    if (!assets.length) {
      const empty = document.createElement('p');
      empty.className = 'dial-icon-library-empty';
      empty.textContent = copy.emptyLibrary || 'No icons uploaded yet.';
      library.append(empty);
    }
    for (const asset of assets) {
      const row = document.createElement('div');
      row.className = 'dial-icon-library-row';
      const choice = document.createElement('button');
      choice.type = 'button';
      choice.className = 'dial-icon-option';
      choice.dataset.assetId = String(asset.id);
      choice.dataset.testid = `dial-icon-library-${asset.id}`;
      choice.setAttribute('aria-pressed', 'false');
      const art = document.createElement('span');
      art.className = 'dial-icon-option-art';
      art.setAttribute('aria-hidden', 'true');
      const image = document.createElement('img');
      image.src = `/endpoints/dial/icon.php?id=${Number(asset.id)}`;
      image.alt = '';
      art.append(image);
      const name = document.createElement('span');
      name.textContent = asset.label;
      const caption = document.createElement('span');
      caption.className = 'dial-icon-option-copy';
      caption.append(name);
      const warning = lowResolutionWarning(asset);
      if (warning) {
        const quality = document.createElement('small');
        quality.className = 'dial-icon-library-quality';
        quality.textContent = warning;
        caption.append(quality);
      }
      choice.append(art, caption);
      choice.addEventListener('click', () => {
        draft.source = 'asset';
        draft.asset_id = String(asset.id);
        draft.catalog_key = '';
        draft.explicit = true;
        sync();
      });
      row.append(choice);
      if (!readonly) {
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'dial-icon-delete';
        remove.textContent = copy.delete || 'Delete';
        remove.setAttribute('aria-label', `${copy.delete || 'Delete'}: ${asset.label}`);
        remove.addEventListener('click', () => deleteAsset(asset.id));
        row.append(remove);
      }
      library.append(row);
    }
    sync();
  }

  async function loadLibrary() {
    const version = ++libraryVersion;
    assets = [];
    library.textContent = copy.loading || 'Loading your icons…';
    renderUsage(null);
    try {
      const response = await fetch('endpoints/dial/icons.php', { credentials: 'same-origin', cache: 'no-store' });
      const data = await response.json();
      if (!response.ok || data.success !== true || !Array.isArray(data.assets)) throw new Error('library_load_failed');
      if (version !== libraryVersion) return;
      assets = data.assets.filter(item => Number.isSafeInteger(Number(item.id)) && Number(item.id) > 0 && typeof item.label === 'string');
      renderUsage(data.usage);
      renderLibrary();
    } catch (error) {
      if (version !== libraryVersion) return;
      library.replaceChildren();
      setStatus(copy.loadError || 'Could not load your icons.', true);
    }
  }

  function setBusy(value) {
    busy = value;
    document.getElementById('dial-icon-upload-action').disabled = readonly || value;
    document.getElementById('dial-icon-save').disabled = readonly || value;
  }

  async function uploadAsset() {
    if (readonly || busy) return;
    const fileInput = document.getElementById('dial-icon-upload-file');
    const file = fileInput.files?.[0];
    if (!file) {
      setStatus(copy.selectFile || 'Choose an image first.', true);
      fileInput.focus();
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      setStatus(copy.fileLimit || 'Image exceeds the upload limit.', true);
      fileInput.focus();
      return;
    }
    const body = new FormData();
    body.set('action', 'upload');
    body.set('icon', file);
    body.set('label', document.getElementById('dial-icon-upload-label').value.trim() || file.name.replace(/\.[^.]+$/, ''));
    setBusy(true);
    setStatus(copy.uploadBusy || 'Uploading icon…');
    try {
      const response = await fetch('endpoints/dial/icons.php', {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'X-CSRF-Token': window.csrfToken || '' }, body,
      });
      const data = await response.json();
      if (!response.ok && ['icon_quota_count', 'icon_quota_bytes'].includes(data.code)) {
        showQuota(data);
        return;
      }
      if (!response.ok || data.success !== true || !Number.isSafeInteger(Number(data.id))) throw new Error(data.code || 'upload_failed');
      await loadLibrary();
      if (!assets.some(item => Number(item.id) === Number(data.id))) throw new Error('library_load_failed');
      draft.source = 'asset';
      draft.asset_id = String(data.id);
      draft.catalog_key = '';
      draft.explicit = true;
      activeTab = 'library';
      fileInput.value = '';
      document.getElementById('dial-icon-upload-label').value = '';
      const uploaded = assets.find(item => Number(item.id) === Number(data.id));
      const warning = lowResolutionWarning(uploaded);
      setStatus(`${copy.uploadSuccess || 'Icon added.'}${warning ? ` ${warning}` : ''}`, false, Boolean(warning));
      sync();
    } catch (error) {
      setStatus(copy.uploadError || 'Could not upload the icon.', true);
    } finally {
      setBusy(false);
    }
  }

  async function deleteAsset(id) {
    if (readonly || busy || !window.confirm(copy.deleteConfirm || 'Delete this icon?')) return;
    setBusy(true);
    const body = new FormData();
    body.set('action', 'delete');
    body.set('id', String(id));
    try {
      const response = await fetch('endpoints/dial/icons.php', {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'X-CSRF-Token': window.csrfToken || '' }, body,
      });
      const data = await response.json();
      if (!response.ok || data.success !== true) throw new Error(data.code || 'delete_failed');
      if (draft.source === 'asset' && draft.asset_id === String(id)) {
        draft.source = 'original';
        draft.asset_id = '';
        draft.explicit = true;
      }
      await loadLibrary();
      setStatus(copy.deleteSuccess || 'Icon deleted.');
    } catch (error) {
      setStatus(copy.deleteError || 'This icon cannot be deleted while in use.', true);
    } finally {
      setBusy(false);
    }
  }

  function open(options = {}) {
    if (dialog.open) return;
    if (options.currentIcon && typeof options.currentIcon === 'object') {
      if ('logo' in options.currentIcon || 'dial_icon_preference' in options.currentIcon) {
        setFromSubscription(options.currentIcon);
      } else if ('source' in options.currentIcon) {
        committed = normalize(options.currentIcon);
        draft = { ...committed };
      }
    }
    openedBy = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    onSelect = typeof options.onSelect === 'function' ? options.onSelect : null;
    onCancel = typeof options.onCancel === 'function' ? options.onCancel : null;
    accepted = false;
    draft = { ...committed };
    activeTab = draft.source === 'asset' ? 'library' : draft.source;
    setStatus();
    sync();
    dialog.showModal();
    sourceButtons.find(button => button.dataset.dialSource === activeTab)?.focus();
    loadLibrary();
  }

  function save() {
    if (readonly || busy) return;
    if (activeTab === 'upload') {
      setStatus(copy.selectFile || 'Choose an image first.', true);
      document.getElementById('dial-icon-upload-file').focus();
      return;
    }
    if ((activeTab === 'library' && draft.source !== 'asset') ||
        (activeTab === 'catalog' && draft.source !== 'catalog')) {
      setStatus(copy.sourceMissing || 'Choose an icon from this source.', true);
      return;
    }
    if ((draft.source === 'catalog' && !catalogButtons.some(button => button.dataset.catalogKey === draft.catalog_key)) ||
        (draft.source === 'asset' && !assets.some(item => String(item.id) === draft.asset_id))) {
      setStatus(copy.sourceMissing || 'Choose an icon from this source.', true);
      return;
    }
    committed = { ...draft };
    accepted = true;
    sync();
    dialog.close('save');
    if (onSelect) onSelect(formFields());
  }

  sourceButtons.forEach(button => button.addEventListener('click', () => {
    activeTab = button.dataset.dialSource;
    if (activeTab === 'original') {
      draft.source = 'original';
      draft.catalog_key = '';
      draft.asset_id = '';
      draft.explicit = true;
    } else if (activeTab === 'catalog' && draft.source !== 'catalog' && catalogButtons.length) {
      draft.source = 'catalog';
      draft.catalog_key = catalogButtons[0].dataset.catalogKey;
      draft.asset_id = '';
      draft.explicit = true;
    } else if (activeTab === 'library' && draft.source !== 'asset' && assets.length) {
      draft.source = 'asset';
      draft.asset_id = String(assets[0].id);
      draft.catalog_key = '';
      draft.explicit = true;
    }
    setStatus();
    sync();
  }));

  catalogButtons.forEach(button => button.addEventListener('click', () => {
    draft.source = 'catalog';
    draft.catalog_key = button.dataset.catalogKey;
    draft.asset_id = '';
    draft.explicit = true;
    sync();
  }));

  dialog.querySelectorAll('[data-dial-presentation]').forEach(button => button.addEventListener('click', () => {
    draft.presentation = button.dataset.dialPresentation;
    draft.explicit = true;
    sync();
  }));
  dialog.querySelectorAll('[data-dial-treatment]').forEach(button => button.addEventListener('click', () => {
    draft.treatment = button.dataset.dialTreatment;
    draft.explicit = true;
    sync();
  }));
  scaleInput.addEventListener('input', () => {
    draft.scale = Math.max(80, Math.min(120, Number(scaleInput.value) || 100));
    draft.explicit = true;
    sync();
  });

  document.getElementById('dial-icon-open-form').addEventListener('click', () => open());
  document.getElementById('dial-icon-upload-action').addEventListener('click', uploadAsset);
  document.getElementById('dial-icon-save').addEventListener('click', save);
  document.getElementById('dial-icon-cancel').addEventListener('click', () => dialog.close('cancel'));
  document.getElementById('dial-icon-close').addEventListener('click', () => dialog.close('cancel'));
  dialog.addEventListener('close', () => {
    const wasAccepted = accepted;
    accepted = false;
    if (!wasAccepted) {
      draft = { ...committed };
      sync();
      if (onCancel) onCancel();
    }
    if (openedBy?.isConnected) openedBy.focus();
    openedBy = null;
    onSelect = null;
    onCancel = null;
  });

  window.DialIconChooser = { open, reset, setFromSubscription, formFields };
  document.getElementById('dial-icon-upload-file').disabled = readonly;
  document.getElementById('dial-icon-upload-label').disabled = readonly;
  setBusy(false);
  sync();
})();
