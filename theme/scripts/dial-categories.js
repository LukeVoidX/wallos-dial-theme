(() => {
  document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('main[data-testid="dial-category-manager"]');
    const copySource = document.getElementById('dial-category-copy');
    if (!root || !copySource) return; // The public Demo renders this page without writes.
    const copy = JSON.parse(copySource.textContent);
    const list = root.querySelector('#dial-category-list');
    const status = root.querySelector('#dial-category-status');
    const addForm = root.querySelector('#dial-category-add-form');
    const newName = root.querySelector('[data-testid="dial-category-new-name"]');
    const mergeDialog = root.querySelector('[data-testid="dial-category-merge-dialog"]');
    const deleteDialog = root.querySelector('[data-testid="dial-category-delete-dialog"]');
    const starterDialog = root.querySelector('[data-testid="dial-category-starter-dialog"]');
    const starterNames = ['AI', 'Infra', 'Domains', 'Media'];
    let dialogTrigger = null;
    let activeRow = null;
    let dragOrder = null;

    const rows = () => [...list.querySelectorAll('[data-testid="dial-category-row"]')];
    const movable = () => rows().filter(row => !row.classList.contains('is-fixed'));
    const rowName = row => row.querySelector('[data-testid="dial-category-name"]')?.value ||
      row.querySelector('.dial-category-label')?.textContent.trim() || '';
    function announce(message, error = false) {
      status.textContent = message;
      status.dataset.state = error ? 'error' : 'ok';
    }
    function showError(error) {
      const code = error?.message || 'write_failed';
      announce(copy[code] || code, true);
    }
    async function postCategory(endpoint, fields) {
      const response = await fetch(endpoint, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-Token': window.csrfToken, Accept: 'application/json' },
        body: fields instanceof URLSearchParams ? fields : new URLSearchParams(fields),
      });
      const result = await response.json().catch(() => { throw new Error('write_failed'); });
      if (!result.success) throw new Error(result.code || 'write_failed');
      sessionStorage.setItem('dialCategoryRevision', String(Date.now()));
      return result;
    }
    function finish(message, focusId, focusSelector) {
      sessionStorage.setItem('dialCategoryFlash', message);
      sessionStorage.setItem('dialCategoryFocus', JSON.stringify({ id: focusId, selector: focusSelector }));
      window.location.reload();
    }
    function updateMoveButtons() {
      const current = movable();
      current.forEach((row, index) => {
        row.querySelector('[data-testid="dial-category-move-up"]').disabled = index === 0;
        row.querySelector('[data-testid="dial-category-move-down"]').disabled = index === current.length - 1;
      });
    }
    function sortablePayload() {
      const body = new URLSearchParams({ action: 'sort' });
      movable().forEach(row => body.append('categoryIds[]', row.dataset.categoryId));
      return body;
    }
    async function saveOrder(previous, focusId, focusSelector) {
      try {
        await postCategory('endpoints/categories/category.php', sortablePayload());
        finish(copy.saved, focusId, focusSelector);
      } catch (error) {
        previous.forEach(row => list.appendChild(row));
        updateMoveButtons();
        showError(error);
        list.querySelector(`[data-category-id="${focusId}"]`)?.querySelector(focusSelector)?.focus();
      }
    }
    function openDialog(dialog, trigger) {
      dialogTrigger = trigger;
      dialog.showModal();
      dialog.querySelector('select, input, button')?.focus();
    }
    [mergeDialog, deleteDialog, starterDialog].forEach(dialog => {
      dialog.addEventListener('close', () => {
        dialogTrigger?.focus();
        dialogTrigger = null;
      });
      dialog.querySelectorAll('[data-dialog-cancel]').forEach(button =>
        button.addEventListener('click', () => dialog.close()));
    });

    const flash = sessionStorage.getItem('dialCategoryFlash');
    if (flash) { announce(flash); sessionStorage.removeItem('dialCategoryFlash'); }
    const focus = sessionStorage.getItem('dialCategoryFocus');
    if (focus) {
      sessionStorage.removeItem('dialCategoryFocus');
      try {
        const target = JSON.parse(focus);
        const row = rows().find(item => item.dataset.categoryId === String(target.id));
        (row?.querySelector(target.selector) || newName)?.focus();
      } catch (_) { newName?.focus(); }
    }
    updateMoveButtons();

    addForm.addEventListener('submit', async event => {
      event.preventDefault();
      const name = newName.value.trim();
      if (!name) { announce(copy.invalid_name, true); newName.focus(); return; }
      const button = addForm.querySelector('[data-testid="dial-category-add"]');
      button.disabled = true;
      try {
        const result = await postCategory('endpoints/categories/category.php', { action: 'add', name });
        finish(copy.saved, result.categoryId, '[data-testid="dial-category-name"]');
      } catch (error) { showError(error); newName.focus(); button.disabled = false; }
    });

    list.addEventListener('click', async event => {
      const button = event.target.closest('button[data-testid]');
      const row = button?.closest('[data-testid="dial-category-row"]');
      if (!button || !row || button.disabled) return;
      const id = row.dataset.categoryId;
      const action = button.dataset.testid;
      activeRow = row;
      if (action === 'dial-category-save') {
        const input = row.querySelector('[data-testid="dial-category-name"]');
        button.disabled = true;
        try {
          await postCategory('endpoints/categories/category.php', { action: 'edit', categoryId: id, name: input.value });
          finish(copy.saved, id, '[data-testid="dial-category-name"]');
        } catch (error) { showError(error); input.focus(); button.disabled = false; }
      } else if (action === 'dial-category-move-up' || action === 'dial-category-move-down') {
        const before = rows();
        const siblings = movable();
        const index = siblings.indexOf(row);
        if (action === 'dial-category-move-up' && index > 0) list.insertBefore(row, siblings[index - 1]);
        if (action === 'dial-category-move-down' && index < siblings.length - 1) list.insertBefore(siblings[index + 1], row);
        updateMoveButtons();
        await saveOrder(before, id, `[data-testid="${action}"]`);
      } else if (action === 'dial-category-merge') {
        const target = mergeDialog.querySelector('[data-testid="dial-category-merge-target"]');
        target.replaceChildren();
        rows().filter(item => item !== row).forEach(item =>
          target.add(new Option(rowName(item), item.dataset.categoryId)));
        mergeDialog.querySelector('#dial-category-merge-help').textContent =
          `${row.dataset.categoryCount} ${copy.count}. ${copy.merge_help}`;
        openDialog(mergeDialog, button);
      } else if (action === 'dial-category-delete') {
        deleteDialog.querySelector('#dial-category-delete-name').textContent = rowName(row);
        openDialog(deleteDialog, button);
      }
    });

    mergeDialog.querySelector('[data-testid="dial-category-merge-confirm"]').addEventListener('click', async event => {
      const targetId = mergeDialog.querySelector('[data-testid="dial-category-merge-target"]').value;
      if (!activeRow || !targetId || targetId === activeRow.dataset.categoryId) {
        announce(copy.same_category, true); return;
      }
      event.currentTarget.disabled = true;
      try {
        await postCategory('endpoints/categories/dial_manage.php', {
          action: 'merge', sourceId: activeRow.dataset.categoryId, targetId,
        });
        mergeDialog.close();
        finish(copy.saved, targetId, '[data-testid="dial-category-merge"]');
      } catch (error) { showError(error); event.currentTarget.disabled = false; }
    });
    deleteDialog.querySelector('[data-testid="dial-category-delete-confirm"]').addEventListener('click', async event => {
      if (!activeRow) return;
      event.currentTarget.disabled = true;
      try {
        await postCategory('endpoints/categories/category.php', { action: 'delete', categoryId: activeRow.dataset.categoryId });
        deleteDialog.close();
        finish(copy.saved, null, '[data-testid="dial-category-new-name"]');
      } catch (error) { showError(error); event.currentTarget.disabled = false; }
    });

    const starterButton = root.querySelector('[data-testid="dial-category-starter"]');
    const starterChoices = [...starterDialog.querySelectorAll('[data-testid="dial-category-starter-delete"]')];
    function updateStarterPreview() {
      const removed = new Set(starterChoices.filter(input => input.checked).map(input => input.dataset.categoryId));
      const present = new Set(rows().filter(row => !removed.has(row.dataset.categoryId))
        .map(row => row.dataset.categoryName));
      const missing = starterNames.filter(name => !present.has(name));
      const box = starterDialog.querySelector('#dial-category-starter-missing');
      box.replaceChildren();
      (missing.length ? missing : [copy.starter_none]).forEach(name => {
        const li = document.createElement('li');
        li.textContent = name;
        box.appendChild(li);
      });
    }
    starterButton.addEventListener('click', () => {
      starterChoices.forEach(input => { input.checked = false; });
      updateStarterPreview();
      openDialog(starterDialog, starterButton);
    });
    starterChoices.forEach(input => input.addEventListener('change', updateStarterPreview));
    starterDialog.querySelector('[data-testid="dial-category-starter-confirm"]').addEventListener('click', async event => {
      const body = new URLSearchParams({ action: 'starter', confirm: '1' });
      starterChoices.filter(input => input.checked).forEach(input => {
        body.append('deleteIds[]', input.dataset.categoryId);
        body.append(`expectedNames[${input.dataset.categoryId}]`, input.dataset.storedName);
      });
      event.currentTarget.disabled = true;
      try {
        await postCategory('endpoints/categories/dial_manage.php', body);
        starterDialog.close();
        finish(copy.saved, null, '[data-testid="dial-category-new-name"]');
      } catch (error) { showError(error); event.currentTarget.disabled = false; }
    });

    if (typeof Sortable !== 'undefined') {
      new Sortable(list, {
        animation: 140, handle: '.dial-category-drag', draggable: '.dial-category-row:not(.is-fixed)',
        forceFallback: true, fallbackOnBody: true, fallbackTolerance: 3,
        ghostClass: 'is-dragging',
        onStart: () => { dragOrder = rows(); },
        onMove: event => !(event.related?.classList.contains('is-fixed') && !event.willInsertAfter),
        onEnd: async event => {
          if (!dragOrder) return;
          const previous = dragOrder;
          dragOrder = null;
          if (rows()[0]?.classList.contains('is-fixed') === false && previous[0]?.classList.contains('is-fixed')) {
            list.insertBefore(previous[0], list.firstElementChild);
          }
          if (rows().every((row, index) => row === previous[index])) return;
          updateMoveButtons();
          await saveOrder(previous, event.item.dataset.categoryId, '[data-testid="dial-category-move-up"]');
        },
      });
    }
  });
})();
