'use strict';
document.querySelectorAll('[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
document.querySelectorAll('[data-copy]').forEach(button => {
  button.addEventListener('click', async () => {
    const target = document.getElementById(button.dataset.copy);
    try {
      await navigator.clipboard.writeText(target.textContent);
      button.textContent = 'Copied ✓';
    } catch {
      const range = document.createRange();
      range.selectNodeContents(target);
      const selection = window.getSelection();
      selection.removeAllRanges(); selection.addRange(range);
      button.textContent = 'Selected — copy with Ctrl/Cmd+C';
    }
  });
});
// Progressive enhancement only: preferences save normally without JavaScript.
const customize = document.querySelector('[data-customize]');
const appearanceValues = {
  theme: ['cedar','maple','terminal','coast','prairie','midnight','paper'],
  font: ['classic','sans','mono'], density: ['comfortable','compact'],
  banner: ['none','contours','grid','dots']
};
function previewAppearance() {
  if (!customize) return;
  for (const [key, values] of Object.entries(appearanceValues)) {
    const input = key === 'theme' ? customize.querySelector('[name="theme"]:checked') : customize.querySelector(`[name="${key}"]`);
    if (!input || !values.includes(input.value)) continue;
    const target = key === 'banner' ? customize.querySelector('[data-personal-preview]') : document.body;
    if (!target) continue;
    values.forEach(value => target.classList.remove(`${key}-${value}`));
    target.classList.add(`${key}-${input.value}`);
  }
}
customize?.addEventListener('change', previewAppearance);
document.querySelectorAll('[data-member-preset]').forEach(button => button.addEventListener('click', () => {
  const preset = JSON.parse(button.dataset.memberPreset);
  customize.querySelectorAll('[name="favourites[]"]').forEach(input => { input.checked = preset.favourites.includes(input.value); });
  customize.querySelectorAll('[name="theme"]').forEach(input => { input.checked = input.value === preset.theme; });
  customize.querySelector('[name="font"]').value = preset.font;
  previewAppearance();
}));
const featurePresets = {
  personal: ['post','status','bookmark','start','garden','uses','guestbook','feeds','ideas','pocket','focus'],
  maker: ['post','status','bookmark','paste','lab','archive','field','start','projects','rings','directory','garden','uses','guestbook','commons','feeds','support','funding','api','pocket','ideas','focus']
};
document.querySelectorAll('[data-feature-preset]').forEach(button => button.addEventListener('click', () => {
  const name = button.dataset.featurePreset;
  document.querySelectorAll('#feature-settings [name="enabled[]"]').forEach(input => {
    if (!input.disabled) input.checked = name === 'all' || (featurePresets[name] || []).includes(input.value);
  });
}));
const focusRoom = document.querySelector('[data-focus]');
if (focusRoom) {
  const length = focusRoom.querySelector('[data-focus-length]');
  const clock = focusRoom.querySelector('[data-focus-clock]');
  const start = focusRoom.querySelector('[data-focus-start]');
  const reset = focusRoom.querySelector('[data-focus-reset]');
  const status = focusRoom.querySelector('[data-focus-status]');
  let remaining = Number(length.value) * 1000;
  let deadline = null;
  let interval = null;
  function paint() {
    const seconds = Math.max(0, Math.ceil(remaining / 1000));
    clock.textContent = `${String(Math.floor(seconds / 60)).padStart(2,'0')}:${String(seconds % 60).padStart(2,'0')}`;
  }
  function tick() {
    if (deadline === null) return;
    remaining = Math.max(0, deadline - Date.now());
    paint();
    if (remaining === 0) {
      clearInterval(interval); interval = null; deadline = null;
      start.textContent = 'Start again'; length.disabled = false;
      status.textContent = 'Time is up. Take a breath; a little progress counts.';
    }
  }
  function restart() {
    clearInterval(interval); interval = null; deadline = null;
    remaining = Number(length.value) * 1000; length.disabled = false;
    start.textContent = 'Start'; status.textContent = 'Ready when you are.'; paint();
  }
  start.addEventListener('click', () => {
    if (deadline !== null) {
      remaining = Math.max(0, deadline - Date.now()); deadline = null;
      clearInterval(interval); interval = null; start.textContent = 'Resume';
      status.textContent = 'Paused. Your time is yours.'; paint();
    } else {
      if (remaining <= 0) remaining = Number(length.value) * 1000;
      deadline = Date.now() + remaining; length.disabled = true; start.textContent = 'Pause';
      status.textContent = 'One small thing. You can pause or stop whenever you like.';
      interval = setInterval(tick, 250); tick();
    }
  });
  reset.addEventListener('click', restart);
  length.addEventListener('change', restart);
  document.addEventListener('visibilitychange', tick);
  paint();
}
