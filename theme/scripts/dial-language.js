(() => {
  document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('dial-language');
    if (!select) return;
    let saved = select.value;

    select.addEventListener('change', async () => {
      const target = select.value;
      select.disabled = true;
      try {
        const response = await fetch('endpoints/user/set_dial_language.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'X-CSRF-Token': window.csrfToken,
            'Accept': 'application/json',
          },
          body: new URLSearchParams({ language: target }),
        });
        const result = await response.json();
        if (!response.ok || !result.success || result.language !== target) {
          throw new Error(result.message || 'Could not change language');
        }
        saved = target;
        window.location.reload();
      } catch (error) {
        select.value = saved;
        if (typeof showErrorMessage === 'function') {
          showErrorMessage(error.message || 'Could not change language');
        }
      } finally {
        select.disabled = false;
      }
    });
  });
})();
