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
  window.pfShrink = shrink; // shared with bulk upload

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

// Publish page: show a spinner while publishing
document.querySelectorAll('form[data-publish]').forEach(f => f.addEventListener('submit', () => {
  f.querySelector('.publish-wait').hidden = false;
  setTimeout(() => f.querySelectorAll('button').forEach(b => { b.disabled = true; }), 0);
}));


/* ===================== v4 admin features ===================== */
(() => {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];

  // Drag-and-drop reordering for every list
  $$('tbody[data-sortable]').forEach(tbody => {
    if (typeof window.Sortable !== 'function') return;
    window.Sortable.create(tbody, {
      handle: '.drag-handle', animation: 150, ghostClass: 'drag-ghost',
      onEnd: async () => {
        const fd = new FormData();
        fd.append('csrf', tbody.dataset.csrf);
        fd.append('op', 'reorder');
        $$('tr[data-id]', tbody).forEach(tr => fd.append('ids[]', tr.dataset.id));
        try {
          const r = await fetch(tbody.dataset.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
          if (!r.ok) throw new Error();
          tbody.classList.add('saved');
          setTimeout(() => tbody.classList.remove('saved'), 900);
        } catch { alert('Could not save the new order. Please refresh and try again.'); }
      },
    });
  });

  // Bulk gallery upload: one request per photo, with progress
  $$('[data-bulk-upload]').forEach(form => {
    const input = form.querySelector('input[type=file]');
    const label = form.querySelector('[data-dz-label]');
    const zone = form.querySelector('.dropzone');
    input.addEventListener('change', () => {
      label.textContent = input.files.length ? `${input.files.length} photo${input.files.length === 1 ? '' : 's'} selected` : 'Choose photos or drag them here';
    });
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, () => zone.classList.add('over')));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, () => zone.classList.remove('over')));
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const files = [...input.files];
      if (!files.length) return;
      const btn = form.querySelector('button[type=submit]');
      const box = form.querySelector('.album-progress');
      const bar = box.querySelector('.progress span');
      const txt = box.querySelector('p');
      btn.disabled = true; box.hidden = false;
      const errors = [];
      for (let i = 0; i < files.length; i++) {
        txt.textContent = `Uploading ${i + 1} of ${files.length}: ${files[i].name}`;
        const fd = new FormData();
        fd.append('csrf', form.elements.csrf.value);
        fd.append('op', 'bulk_upload');
        fd.append('category', form.elements.category.value);
        fd.append('visible', form.querySelector('input[type=checkbox][name=visible]').checked ? '1' : '0');
        try {
          fd.append('photo', window.pfShrink ? await window.pfShrink(files[i]) : files[i]);
          const res = await fetch(form.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
          const data = await res.json().catch(() => ({ ok: false, error: `${files[i].name}: server error (${res.status})` }));
          if (!data.ok) errors.push(data.error || files[i].name + ': failed');
        } catch { errors.push(files[i].name + ': network error'); }
        bar.style.width = `${Math.round(((i + 1) / files.length) * 100)}%`;
      }
      if (errors.length) alert('Some photos could not be uploaded:\n\n' + errors.slice(0, 10).join('\n'));
      location.reload();
    });
  });

  // QR codes (dashboard + 2-step login setup)
  $$('[data-qr]').forEach(box => {
    if (typeof window.QRCode !== 'function') { box.textContent = 'QR code could not load (offline?).'; return; }
    new window.QRCode(box, { text: box.dataset.qr, width: Number(box.dataset.size || 180), height: Number(box.dataset.size || 180), colorDark: '#111111', colorLight: '#ffffff', correctLevel: window.QRCode.CorrectLevel.M });
  });
  $$('[data-qr-download]').forEach(btn => btn.addEventListener('click', () => {
    const box = document.getElementById(btn.dataset.qrDownload);
    const canvas = box?.querySelector('canvas');
    const img = box?.querySelector('img');
    const src = canvas ? canvas.toDataURL('image/png') : img?.src;
    if (!src) return;
    const a = Object.assign(document.createElement('a'), { href: src, download: 'portfolio-qr.png' });
    document.body.appendChild(a); a.click(); a.remove();
  }));
})();


/* ===================== Photo crop tool ===================== */
(() => {
  if (typeof window.Cropper !== 'function' || typeof DataTransfer !== 'function') return; // old browser: plain upload
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];

  const RATIOS = [
    ['Free', NaN], ['Original', 'orig'], ['1:1', 1], ['4:5', 4 / 5], ['2:3', 2 / 3], ['3:2', 3 / 2], ['16:9', 16 / 9], ['16:10', 16 / 10],
  ];
  const parseRatio = s => {
    if (!s) return null;
    const [w, h] = String(s).split('/').map(Number);
    return w > 0 && h > 0 ? w / h : null;
  };

  // Build the modal once
  const modal = document.createElement('div');
  modal.className = 'crop-modal';
  modal.hidden = true;
  modal.innerHTML = `
    <div class="crop-dialog" role="dialog" aria-modal="true" aria-label="Crop photo">
      <div class="crop-head"><h3><i class="fa-solid fa-crop-simple"></i> Crop photo</h3><span class="crop-size muted"></span></div>
      <div class="crop-stage"><img alt=""></div>
      <div class="crop-tools">
        <div class="crop-ratios"></div>
        <div class="crop-actions-mini">
          <button type="button" class="icon-btn" data-act="rotL" title="Rotate left"><i class="fa-solid fa-rotate-left"></i></button>
          <button type="button" class="icon-btn" data-act="rotR" title="Rotate right"><i class="fa-solid fa-rotate-right"></i></button>
          <button type="button" class="icon-btn" data-act="flip" title="Flip"><i class="fa-solid fa-left-right"></i></button>
          <button type="button" class="icon-btn" data-act="reset" title="Reset"><i class="fa-solid fa-arrows-rotate"></i></button>
        </div>
      </div>
      <div class="crop-foot">
        <button type="button" class="btn btn-ghost" data-act="skip">Use without cropping</button>
        <button type="button" class="btn btn-gold" data-act="apply"><i class="fa-solid fa-check"></i> Crop &amp; use</button>
      </div>
    </div>`;
  document.body.appendChild(modal);
  const img = $('.crop-stage img', modal);
  const ratioBox = $('.crop-ratios', modal);
  const sizeLbl = $('.crop-size', modal);
  let cropper = null, input = null, file = null, suggested = null, flipped = 1, url = null;

  const setRatioButtons = active => {
    ratioBox.innerHTML = '';
    RATIOS.forEach(([label, val]) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'chip-btn' + (label === active ? ' active' : '');
      const isSuggested = suggested && typeof val === 'number' && Math.abs(val - suggested) < 0.01;
      b.innerHTML = label + (isSuggested ? ' <i class="fa-solid fa-star" title="Best for this spot"></i>' : '');
      b.addEventListener('click', () => {
        const r = val === 'orig' ? img.naturalWidth / img.naturalHeight : val;
        cropper.setAspectRatio(r);
        $$('.chip-btn', ratioBox).forEach(x => x.classList.toggle('active', x === b));
      });
      ratioBox.appendChild(b);
    });
    // The field's own shape (e.g. 1200/630) if it isn't one of the presets
    if (suggested && !RATIOS.some(([, v]) => typeof v === 'number' && Math.abs(v - suggested) < 0.01)) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'chip-btn' + (active === 'suggested' ? ' active' : '');
      b.innerHTML = 'Best fit <i class="fa-solid fa-star"></i>';
      b.addEventListener('click', () => { cropper.setAspectRatio(suggested); $$('.chip-btn', ratioBox).forEach(x => x.classList.toggle('active', x === b)); });
      ratioBox.prepend(b);
    }
  };

  const close = () => {
    modal.hidden = true;
    document.body.classList.remove('crop-open');
    if (cropper) { cropper.destroy(); cropper = null; }
    if (url) { URL.revokeObjectURL(url); url = null; }
  };

  const open = (theInput, theFile) => {
    input = theInput; file = theFile; flipped = 1;
    suggested = parseRatio(input.dataset.aspect);
    url = URL.createObjectURL(file);
    img.src = url;
    modal.hidden = false;
    document.body.classList.add('crop-open');
    img.onload = () => {
      cropper = new window.Cropper(img, {
        viewMode: 1, autoCropArea: 1, background: false, responsive: true, checkOrientation: true,
        aspectRatio: suggested || NaN,
        crop: e => { sizeLbl.textContent = `${Math.round(e.detail.width)} × ${Math.round(e.detail.height)} px`; },
      });
      let active = 'Free';
      if (suggested) {
        const preset = RATIOS.find(([, v]) => typeof v === 'number' && Math.abs(v - suggested) < 0.01);
        active = preset ? preset[0] : 'suggested';
      }
      setRatioButtons(active);
    };
  };

  const useFile = f => {
    const dt = new DataTransfer();
    dt.items.add(f);
    input.files = dt.files;
    input._skipCrop = true;
    input.dispatchEvent(new Event('change', { bubbles: true })); // refreshes the preview
    // remember it was cropped (the "Remove current" box should not stay ticked)
    const rm = input.closest('.upload-controls')?.querySelector('input[name^="remove_"]');
    if (rm) rm.checked = false;
  };

  modal.addEventListener('click', async e => {
    const act = e.target.closest('[data-act]')?.dataset.act;
    if (!act) { if (e.target === modal) close(); return; }
    if (act === 'rotL') cropper.rotate(-90);
    if (act === 'rotR') cropper.rotate(90);
    if (act === 'flip') { flipped *= -1; cropper.scaleX(flipped); }
    if (act === 'reset') { cropper.reset(); flipped = 1; }
    if (act === 'skip') { if (input.dataset.fromExisting) { input.value = ''; } close(); }
    if (act === 'apply') {
      const btn = e.target.closest('button');
      btn.disabled = true;
      // Keep transparency for PNG/WebP; photos become high-quality JPEG
      const type = /png|webp/.test(file.type) ? 'image/png' : 'image/jpeg';
      const canvas = cropper.getCroppedCanvas({ maxWidth: 2600, maxHeight: 2600, imageSmoothingQuality: 'high', fillColor: type === 'image/jpeg' ? '#fff' : 'transparent' });
      canvas.toBlob(blob => {
        btn.disabled = false;
        if (!blob) { alert('Could not crop this photo. It will be uploaded as it is.'); close(); return; }
        const name = file.name.replace(/\.\w+$/, '') + (type === 'image/png' ? '.png' : '.jpg');
        useFile(new File([blob], name, { type }));
        close();
      }, type, 0.92);
    }
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) close(); });

  // New photo chosen -> open the crop tool
  $$('input[type=file][data-crop]').forEach(inp => inp.addEventListener('change', () => {
    if (inp._skipCrop) { inp._skipCrop = false; return; }
    const f = inp.files?.[0];
    if (!f || !/^image\/(jpeg|png|webp)$/.test(f.type)) return; // GIFs upload as they are
    delete inp.dataset.fromExisting;
    open(inp, f);
  }));

  // "Crop current photo" -> load the uploaded photo into the crop tool
  $$('[data-crop-existing]').forEach(btn => btn.addEventListener('click', async () => {
    const inp = document.getElementById(btn.dataset.for);
    if (!inp) return;
    btn.disabled = true;
    try {
      const res = await fetch(btn.dataset.cropExisting, { cache: 'no-store' });
      const blob = await res.blob();
      const name = btn.dataset.cropExisting.split('/').pop().split('?')[0] || 'photo.jpg';
      inp.dataset.fromExisting = '1';
      open(inp, new File([blob], name, { type: blob.type || 'image/jpeg' }));
    } catch {
      alert('Could not load the current photo.');
    } finally {
      btn.disabled = false;
    }
  }));
})();
