(() => {
  let revisionAtLoad;
  try { revisionAtLoad = sessionStorage.getItem('dialCategoryRevision'); }
  catch (_) { return; }
  if (location.pathname.endsWith('/settings.php')) {
    const originalFetch = window.fetch.bind(window);
    window.fetch = async (input, init) => {
      const method = (init?.method || input?.method || 'GET').toUpperCase();
      const url = new URL(input?.url || input, location.href);
      const isCategoryWrite = method === 'POST' && url.origin === location.origin &&
        url.pathname.endsWith('/endpoints/categories/category.php');
      const response = await originalFetch(input, init);
      if (isCategoryWrite && response.ok) {
        try {
          if ((await response.clone().json()).success) {
            sessionStorage.setItem('dialCategoryRevision', `${Date.now()}-${Math.random()}`);
          }
        } catch (_) { /* Leave the inherited Settings response handling intact. */ }
      }
      return response;
    };
  }
  window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    const latest = sessionStorage.getItem('dialCategoryRevision');
    if (latest !== revisionAtLoad) window.location.reload();
  });
})();
