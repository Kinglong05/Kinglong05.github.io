(() => {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];

  // Mobile sidebar
  $('#menuBtn')?.addEventListener('click', () => document.body.classList.add('menu-open'));
  $('#scrim')?.addEventListener('click', () => document.body.classList.remove('menu-open'));

  // Confirm before destructive form submits
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => {
    if (!confirm(f.dataset.confirm)) e.preventDefault();
  }));
  $$('[data-confirm-click]').forEach(b => b.addEventListener('click', e => {
    if (!confirm(b.dataset.confirmClick)) e.preventDefault();
  }));

  // Select all checkboxes (messages)
  $('[data-check-all]')?.addEventListener('change', e => {
    $$('input[name="ids[]"]').forEach(c => { c.checked = e.target.checked; });
  });

  // Live image preview on file select
  $$('input[type=file][data-preview]').forEach(input => input.addEventListener('change', () => {
    const file = input.files?.[0];
    if (!file || !file.type.startsWith('image/')) return;
    const wrap = input.closest('.upload');
    let img = wrap.querySelector('img.upload-preview');
    if (!img) {
      img = document.createElement('img');
      img.className = 'upload-preview';
      wrap.querySelector('.upload-preview')?.replaceWith(img);
    }
    img.src = URL.createObjectURL(file);
  }));

  // Live icon preview
  $$('[data-icon-input]').forEach(input => input.addEventListener('input', () => {
    const i = input.parentElement.querySelector('.icon-preview i');
    if (i) i.className = input.value.trim();
  }));

  // Show/hide password
  $$('[data-pw-toggle]').forEach(btn => btn.addEventListener('click', () => {
    const input = btn.parentElement.querySelector('input');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
  }));

  // Album upload: shrink big photos in the browser, then send one at a time with progress
  const shrink = async file => {
    const MAX_DIM = 2560, MAX_BYTES = 4.5 * 1024 * 1024;
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !window.createImageBitmap) return file;
    let bmp;
    try { bmp = await createImageBitmap(file); } catch { return file; }
    const scale = Math.min(1, MAX_DIM / Math.max(bmp.width, bmp.height));
    if (scale === 1 && file.size <= MAX_BYTES) { bmp.close?.(); return file; }
    const c = document.createElement('canvas');
    c.width = Math.round(bmp.width * scale);
    c.height = Math.round(bmp.height * scale);
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    bmp.close?.();
    const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.88));
    return blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
  };
  $$('[data-album-upload]').forEach(form => {
    const input = form.querySelector('input[type=file]');
    const label = form.querySelector('[data-dz-label]');
    const zone = form.querySelector('.dropzone');
    input.addEventListener('change', () => {
      label.textContent = input.files.length ? `${input.files.length} photo${input.files.length === 1 ? '' : 's'} selected` : 'Choose photos or drag them here';
    });
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, () => zone.classList.add('over')));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, () => zone.classList.remove('over')));

    form.addEventListener('submit', async e => {
      const files = [...input.files];
      if (!files.length || !window.fetch) return; // plain form post fallback
      e.preventDefault();
      const btn = form.querySelector('button[type=submit]');
      const box = form.querySelector('.album-progress');
      const bar = box.querySelector('.progress span');
      const txt = box.querySelector('p');
      btn.disabled = true; box.hidden = false;
      const errors = [];
      for (let i = 0; i < files.length; i++) {
        txt.textContent = `Uploading ${i + 1} of ${files.length}: ${files[i].name}`;
        const fd = new FormData();
        ['csrf', 'op', 'id'].forEach(n => fd.append(n, form.elements[n].value));
        try {
          fd.append('photos[]', await shrink(files[i]));
          const res = await fetch(form.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
          const data = await res.json().catch(() => ({ ok: false, errors: [`${files[i].name}: server error (${res.status})`] }));
          if (!data.ok) errors.push(...(data.errors || [files[i].name + ': failed']));
        } catch {
          errors.push(files[i].name + ': network error');
        }
        bar.style.width = `${Math.round(((i + 1) / files.length) * 100)}%`;
      }
      txt.textContent = errors.length ? `Done with ${errors.length} problem(s).` : 'All photos uploaded!';
      if (errors.length) alert('Some photos could not be uploaded:\n\n' + errors.slice(0, 10).join('\n'));
      location.hash = 'album';
      location.reload();
    });
  });

  // Auto-hide success alerts
  $$('.content > .alert-success').forEach(a => setTimeout(() => {
    a.style.transition = 'opacity .4s'; a.style.opacity = '0';
    setTimeout(() => a.remove(), 400);
  }, 3500));
})();
