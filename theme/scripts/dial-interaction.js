(() => {
  const timeline = document.querySelector('.dial-timeline');
  if (!timeline || !window.matchMedia('(pointer: fine)').matches ||
      window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const ticks = [...timeline.querySelectorAll('.dial-tick')];
  const readout = timeline.querySelector('.dial-readout');
  if (!ticks.length || !readout) return;

  const active = new Set();
  let frame = 0;
  let pointerX = 0;
  let pointerY = 0;

  function clearDial() {
    if (frame) cancelAnimationFrame(frame);
    frame = 0;
    for (const index of active) {
      ticks[index].style.removeProperty('--dial-scale');
      ticks[index].style.removeProperty('--dial-shift');
      ticks[index].style.removeProperty('--dial-label-scale');
    }
    active.clear();
    timeline.classList.remove('is-dial-active');
  }

  function drawDial() {
    frame = 0;
    const bounds = timeline.getBoundingClientRect();
    const x = pointerX - bounds.left;
    const y = pointerY - bounds.top;
    if (x < 0 || x > bounds.width || y < 0 || y > 118) {
      clearDial();
      return;
    }

    const cell = bounds.width / ticks.length;
    const center = Math.max(0, Math.min(ticks.length - 1, Math.floor(x / cell)));
    const next = new Set();
    const reach = 5;
    for (let i = Math.max(0, center - reach); i <= Math.min(ticks.length - 1, center + reach); i++) {
      const tick = ticks[i];
      const offset = ((i + .5) * cell - x) / cell;
      const influence = Math.exp(-.5 * (offset / 1.65) ** 2);
      const major = tick.classList.contains('dial-tick--major') || tick.classList.contains('dial-tick--month');
      const today = tick.classList.contains('dial-tick--today');
      const gain = today ? .14 : major ? .28 : .88;
      const shift = Math.sign(offset) * influence * Math.min(9, cell * .38);
      tick.style.setProperty('--dial-scale', (1 + gain * influence).toFixed(3));
      tick.style.setProperty('--dial-shift', `${shift.toFixed(2)}px`);
      tick.style.setProperty('--dial-label-scale', (1 + .13 * influence).toFixed(3));
      next.add(i);
    }
    for (const index of active) {
      if (next.has(index)) continue;
      ticks[index].style.removeProperty('--dial-scale');
      ticks[index].style.removeProperty('--dial-shift');
      ticks[index].style.removeProperty('--dial-label-scale');
    }
    active.clear();
    for (const index of next) active.add(index);

    readout.textContent = ticks[center].dataset.dialDate || '';
    readout.style.left = `${Math.max(58, Math.min(bounds.width - 58, x))}px`;
    timeline.classList.add('is-dial-active');
  }

  timeline.addEventListener('pointermove', event => {
    if (event.pointerType !== 'mouse' && event.pointerType !== 'pen') return;
    pointerX = event.clientX;
    pointerY = event.clientY;
    if (!frame) frame = requestAnimationFrame(drawDial);
  });
  timeline.addEventListener('pointerleave', clearDial);
  timeline.addEventListener('pointercancel', clearDial);
  window.addEventListener('blur', clearDial);
})();
