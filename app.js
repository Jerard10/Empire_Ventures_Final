/* app.js - replaces main.js. Everything works without JS except live totals; JS only adds convenience. */
document.addEventListener('DOMContentLoaded', () => {
  // Mobile menu
  const toggle = document.querySelector('.nav-toggle'), menu = document.getElementById('nav-menu');
  if (toggle && menu) toggle.addEventListener('click', () => {
    const open = menu.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
  });

  // User dropdown (disclosure): click, Escape to close, click-away to close
  const btn = document.querySelector('.user-btn'), drop = document.getElementById('user-drop');
  if (btn && drop) {
    const setOpen = (open, refocus) => {
      drop.hidden = !open; btn.setAttribute('aria-expanded', String(open));
      if (!open && refocus) btn.focus();
    };
    btn.addEventListener('click', () => setOpen(drop.hidden));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !drop.hidden) setOpen(false, true); });
    document.addEventListener('click', e => { if (!drop.hidden && !drop.contains(e.target) && !btn.contains(e.target)) setOpen(false); });
    drop.addEventListener('focusout', e => { if (!drop.contains(e.relatedTarget) && e.relatedTarget !== btn) setOpen(false); });
  }

  // "Show password" checkboxes: <input type="checkbox" data-show-password="id1 id2">
  document.querySelectorAll('[data-show-password]').forEach(cb => cb.addEventListener('change', () => {
    cb.dataset.showPassword.split(' ').forEach(id => { const i = document.getElementById(id); if (i) i.type = cb.checked ? 'text' : 'password'; });
  }));

  // Scrollable tables must be reachable by keyboard
  document.querySelectorAll('.table-wrap').forEach(w => { w.tabIndex = 0; w.setAttribute('role', 'region'); });

  // Order calculator
  const form = document.getElementById('orderForm');
  if (form) {
    const $ = id => document.getElementById(id);
    const variety = $('rice_variety_id'), pack = $('pack_kg'), qty = $('quantity'), note = $('limitNote');
    const partner = form.dataset.partner === '1', bulkMin = +form.dataset.bulkMin;
    const money = n => '₱' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const update = (clamp) => {
      const opt = variety.options[variety.selectedIndex];
      const kg = +pack.value || 0;
      const unit = opt && opt.dataset.price ? (+opt.dataset.price * kg / (+opt.dataset.weight || 50)) : 0;
      const max = partner ? 500 : Math.max(1, Math.ceil(bulkMin / (kg || 1)) - 1);
      qty.max = max;
      let n = parseInt(qty.value, 10) || 1;
      note.textContent = '';
      if (n > max) { if (clamp) { n = max; qty.value = max; } note.textContent = partner ? '' : 'Orders of ' + bulkMin + ' kg or more are for approved wholesale partners. Quantity limited to ' + max + '.'; }
      $('sumUnit').textContent = kg ? money(unit) + ' per ' + kg + ' kg pack' : 'Choose a pack size';
      $('sumQty').textContent = n + (n > 1 ? ' packs' : ' pack') + ' · ' + (n * kg) + ' kg total';
      $('sumTotal').textContent = money(unit * n);
    };
    [variety, pack].forEach(el => el.addEventListener('change', () => update(true)));
    qty.addEventListener('input', () => update(true));
    update(false);
  }

  // Settings > Display preferences (stored on this device only)
  const size = document.getElementById('pref-size'), contrast = document.getElementById('pref-contrast');
  if (size && contrast) {
    const root = document.documentElement;
    try { size.value = localStorage.getItem('ev-size') || 'normal'; contrast.checked = localStorage.getItem('ev-contrast') === 'high'; } catch (e) {}
    size.addEventListener('change', () => { root.dataset.size = size.value; try { localStorage.setItem('ev-size', size.value); } catch (e) {} });
    contrast.addEventListener('change', () => { root.dataset.contrast = contrast.checked ? 'high' : 'normal'; try { localStorage.setItem('ev-contrast', root.dataset.contrast); } catch (e) {} });
  }
});
