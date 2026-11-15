/* =====================================================================
   admin.js — the editor behind admin.html
   Reads js/data.js, gives you forms for every section, and saves a new
   data.js (plus any uploaded images) to your folder, GitHub, or a download.
   ===================================================================== */
(() => {
  'use strict';

  const KEYS = ['SITE', 'STATS', 'SKILLS', 'PROJECTS', 'GALLERY', 'TIMELINE', 'CERTIFICATIONS'];
  const DRAFT_KEY = 'portfolio-admin-draft';   // also read by js/preview.js
  const GH_KEY = 'portfolio-admin-github';
  const MODE_KEY = 'portfolio-admin-mode';
  const DATA_PATH = 'js/data.js';

  /* ---------- tiny helpers ---------- */
  const $ = (s, c = document) => c.querySelector(s);
  const ls = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* full or blocked */ } },
    del(k) { try { localStorage.removeItem(k); } catch (e) { /* blocked */ } }
  };
  let uidN = 0;
  const uid = () => 'f' + (++uidN);
  const cap = s => s.charAt(0).toUpperCase() + s.slice(1);
  const hash = s => { let h = 0; for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0; return h; };

  function el(tag, attrs = {}, ...kids) {
    const e = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (v == null || v === false) continue;
      if (k === 'class') e.className = v;
      else if (k === 'text') e.textContent = v;
      else if (k === 'value') e.value = v;
      else if (k.startsWith('on') && typeof v === 'function') e.addEventListener(k.slice(2), v);
      else if (typeof v === 'boolean') e[k] = v;
      else e.setAttribute(k, v);
    }
    for (const kid of kids.flat()) if (kid != null && kid !== false) e.append(kid.nodeType ? kid : String(kid));
    return e;
  }

  let toastTimer;
  function toast(msg, isErr = false, ms = 4200) {
    const t = $('#toast');
    t.textContent = msg;
    t.classList.toggle('err', isErr);
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), ms);
  }

  /* ---------- state ---------- */
  if (typeof SITE === 'undefined') {
    $('#editor').append(el('div', { class: 'empty', text: 'js/data.js could not be loaded. It probably has a syntax error (a missing comma is the usual culprit). Fix it, then reload this page.' }));
    return;
  }
  const fromGlobals = () => JSON.parse(JSON.stringify({ SITE, STATS, SKILLS, PROJECTS, GALLERY, TIMELINE, CERTIFICATIONS }));

  let data = fromGlobals();
  let savedJSON = JSON.stringify(data);        // what's on disk / GitHub right now
  const pending = new Map();                   // path -> File waiting to be saved
  const blobURLs = new Map();                  // path -> object URL for thumbnails
  const openSet = new WeakSet();               // which cards are expanded
  let current = KEYS.includes(location.hash.slice(1)) ? location.hash.slice(1) : 'SITE';

  const conn = { mode: ls.get(MODE_KEY) || 'none', dir: null, gh: null, ghSha: null };
  try { conn.gh = JSON.parse(ls.get(GH_KEY) || 'null'); } catch (e) { conn.gh = null; }

  /* ---------- schema: what each section looks like ---------- */
  const PROJECT_CATS = ['web', 'ai', 'desktop', 'creative'];
  const GALLERY_CATS = ['portraits', 'kids', 'drone', 'events'];
  const isVideo = g => g.type === 'video';
  const isPhoto = g => g.type !== 'video';

  const SECTIONS = {
    SITE: {
      label: 'Site & Contact', icon: '⚙️',
      intro: 'Your name, contact details, résumé and social links.',
      schema: {
        type: 'object', fields: [
          { k: 'name', label: 'Full name' },
          { k: 'brand', label: 'Brand name' },
          { k: 'email', label: 'Public email', input: 'email' },
          { k: 'phone', label: 'Phone', help: 'Leave empty to hide it.' },
          { k: 'location', label: 'Location' },
          { k: 'formspreeId', label: 'Formspree form ID', help: 'From formspree.io: the part after /f/ in your form endpoint.' },
          { k: 'resume', label: 'Résumé (PDF)', type: 'file', folder: 'assets', fixedName: 'resume.pdf', accept: 'application/pdf', wide: true },
          { k: 'roles', label: 'Typing words in the hero', type: 'lines', help: 'One per line, in the order they appear.', wide: true },
          {
            k: 'socials', label: 'Social links', type: 'object', wide: true,
            fields: ['instagram', 'facebook', 'github', 'linkedin', 'youtube'].map(s => ({ k: s, label: cap(s), input: 'url', help: '' }))
          }
        ]
      }
    },

    STATS: {
      label: 'Counters', icon: '🔢',
      intro: 'The animated numbers in the About section.',
      schema: {
        type: 'list', addLabel: 'Add counter',
        title: s => s.label || 'New counter', sub: s => `${s.value ?? 0}${s.suffix || ''}`,
        blank: () => ({ label: '', value: 0, suffix: '+' }),
        fields: [
          { k: 'label', label: 'Label' },
          { k: 'value', label: 'Number', input: 'number' },
          { k: 'suffix', label: 'Suffix', help: 'e.g. + or %. Can be empty.' }
        ]
      }
    },

    SKILLS: {
      label: 'Skills', icon: '🧠',
      intro: 'Each group is a tab on the site. Each skill has an icon and a level bar.',
      schema: {
        type: 'list', addLabel: 'Add skill group',
        title: g => g.category || 'New group', sub: g => `${(g.items || []).length} skills`,
        blank: () => ({ category: '', items: [] }),
        fields: [
          { k: 'category', label: 'Group name', wide: true },
          {
            k: 'items', label: 'Skills', type: 'list', wide: true, addLabel: 'Add skill',
            title: i => i.name || 'New skill', sub: i => `${i.level ?? 0}%`, icon: i => i.icon,
            blank: () => ({ name: '', icon: '⭐', level: 75 }),
            fields: [
              { k: 'name', label: 'Name' },
              { k: 'icon', label: 'Icon', help: 'An emoji, or a Devicon class like "devicon-python-plain colored" from devicon.dev.' },
              { k: 'level', label: 'Level', type: 'range' }
            ]
          }
        ]
      }
    },

    PROJECTS: {
      label: 'Projects', icon: '💻',
      intro: 'Project cards and their detail popups.',
      schema: {
        type: 'list', addLabel: 'Add project',
        title: p => p.title || 'New project', sub: p => (p.categories || []).join(' · ') || 'no category', thumb: p => p.thumb,
        blank: () => ({ id: '', title: '', categories: [], thumb: '', summary: '', description: [], features: [], tech: [], screenshots: [], video: '', github: '', live: '' }),
        fields: [
          { k: 'title', label: 'Title' },
          { k: 'subtitle', label: 'Subtitle (optional)' },
          { k: 'id', label: 'ID', help: 'Unique, no spaces. Leave empty and it is made from the title.' },
          { k: 'categories', label: 'Categories (used by the filter buttons)', type: 'checks', options: PROJECT_CATS, wide: true },
          { k: 'thumb', label: 'Card image', type: 'image', folder: 'assets/projects', wide: true, help: 'About 800×500.' },
          { k: 'summary', label: 'Card summary', type: 'textarea', wide: true },
          { k: 'description', label: 'Full description', type: 'paras', wide: true, help: 'Leave a blank line between paragraphs.' },
          { k: 'features', label: 'Key features', type: 'lines', wide: true, help: 'One per line.' },
          { k: 'tech', label: 'Tech used', type: 'tags', wide: true, help: 'Separate with commas.' },
          { k: 'screenshots', label: 'Screenshots', type: 'images', folder: 'assets/projects', wide: true },
          { k: 'video', label: 'Video link (optional)', input: 'url', help: 'YouTube or Facebook. Plays inside the popup.' },
          { k: 'github', label: 'GitHub link', input: 'url', help: 'Empty hides the button.' },
          { k: 'live', label: 'Live demo link', input: 'url', help: 'Empty hides the button.' }
        ]
      }
    },

    GALLERY: {
      label: 'Gallery', icon: '📷',
      intro: 'Photos and video edits. ★ Favorites also appear in the Favorite Frames strip.',
      schema: {
        type: 'list', addLabel: 'Add item',
        bulk: {
          label: 'Upload photos', folder: 'assets/photos',
          make: (path, d) => ({ type: 'photo', category: 'portraits', src: path, w: d ? d.w : undefined, h: d ? d.h : undefined, alt: '', caption: '' })
        },
        title: g => g.caption || g.alt || (isVideo(g) ? 'Untitled video' : 'Untitled photo'),
        sub: g => (isVideo(g) ? '🎬 video' : g.category || 'no category') + (g.fav ? ' · ★ favorite' : ''),
        thumb: g => (isVideo(g) ? g.cover : (g.thumb || g.src)),
        blank: () => ({ type: 'photo', category: 'portraits', src: '', alt: '', caption: '' }),
        fields: [
          { k: 'type', label: 'Type', type: 'select', options: [['photo', 'Photo'], ['video', 'Video']], rerender: true, onChange: cleanGalleryType },
          { k: 'category', label: 'Category', type: 'select', options: GALLERY_CATS.map(c => [c, cap(c)]), when: isPhoto },
          { k: 'src', label: 'Photo', type: 'image', folder: 'assets/photos', dims: true, wide: true, when: isPhoto, help: 'WebP or JPG, about 1600–2000 px on the long edge, under 500 KB.' },
          { k: 'video', label: 'Video link', input: 'url', wide: true, when: isVideo, help: 'Facebook (set to Public), YouTube, or assets/videos/your-file.mp4' },
          { k: 'cover', label: 'Cover image (optional)', type: 'image', folder: 'assets/photos', dims: true, wide: true, when: isVideo, help: 'Without a cover, Facebook shows its own and YouTube is automatic.' },
          { k: 'alt', label: 'Alt text', wide: true, help: 'Describe it for screen readers and Google.' },
          { k: 'caption', label: 'Caption' },
          { k: 'w', label: 'Width', input: 'number', optional: true, help: 'Filled in when you upload.' },
          { k: 'h', label: 'Height', input: 'number', optional: true },
          { k: 'fav', label: '★ Favorite Frames', type: 'checkbox' },
          { k: 'vertical', label: 'Vertical (9:16 reel)', type: 'checkbox', when: isVideo },
          { k: 'thumb', label: 'Smaller grid version (optional)', type: 'image', folder: 'assets/photos', wide: true, when: isPhoto, help: 'About 600 px wide. The lightbox still shows the full photo.' }
        ]
      }
    },

    TIMELINE: {
      label: 'Timeline', icon: '🎓',
      intro: 'Education and experience, newest first.',
      schema: {
        type: 'list', addLabel: 'Add entry',
        title: t => t.title || 'New entry', sub: t => [t.period, t.status].filter(Boolean).join(' · '), icon: t => t.icon,
        blank: () => ({ icon: '📌', title: '', org: '', period: '', status: '', description: '' }),
        fields: [
          { k: 'icon', label: 'Icon (emoji)' },
          { k: 'title', label: 'Title' },
          { k: 'org', label: 'Organization' },
          { k: 'period', label: 'Period', help: 'e.g. 2022 – Present' },
          { k: 'status', label: 'Badge', type: 'select', options: [['', 'None'], ['ongoing', 'Ongoing'], ['upcoming', 'Upcoming']] },
          { k: 'description', label: 'Description', type: 'textarea', wide: true }
        ]
      }
    },

    CERTIFICATIONS: {
      label: 'Certificates', icon: '🏆',
      intro: 'Certificates and awards.',
      schema: {
        type: 'list', addLabel: 'Add certificate',
        title: c => c.title || 'New certificate', sub: c => [c.issuer, c.year].filter(Boolean).join(' · '), icon: c => c.icon,
        blank: () => ({ icon: '📜', title: '', issuer: '', year: '', link: '' }),
        fields: [
          { k: 'icon', label: 'Icon (emoji)' },
          { k: 'title', label: 'Title' },
          { k: 'issuer', label: 'Issuer' },
          { k: 'year', label: 'Year' },
          { k: 'link', label: 'Link (optional)', input: 'url', wide: true, help: 'A link to the certificate. Can be an uploaded PDF or image path.' }
        ]
      }
    }
  };

  function cleanGalleryType(g) {
    if (isVideo(g)) { delete g.src; delete g.thumb; delete g.category; if (g.video == null) g.video = ''; }
    else { delete g.video; delete g.cover; delete g.vertical; if (!g.category) g.category = 'portraits'; if (g.src == null) g.src = ''; }
  }

  /* ---------- change tracking & drafts ---------- */
  let draftTimer;
  function writeDraft() {
    clearTimeout(draftTimer);
    if (isDirty()) ls.set(DRAFT_KEY, JSON.stringify({ data, base: hash(savedJSON), at: Date.now() }));
    else ls.del(DRAFT_KEY);
  }
  const isDirty = () => pending.size > 0 || JSON.stringify(data) !== savedJSON;

  function changed() {
    clearTimeout(draftTimer);
    draftTimer = setTimeout(writeDraft, 250);
    paintStatus();
  }

  function paintStatus() {
    const dirty = isDirty();
    const s = $('#status');
    s.textContent = dirty ? `Unsaved changes${pending.size ? ` · ${pending.size} new file${pending.size > 1 ? 's' : ''}` : ''}` : 'All changes saved';
    s.classList.toggle('dirty', dirty);
    $('#discardBtn').hidden = !dirty;
    paintNav();
  }

  function restoreDraft() {
    let d = null;
    try { d = JSON.parse(ls.get(DRAFT_KEY) || 'null'); } catch (e) { d = null; }
    if (!d || !d.data || JSON.stringify(d.data) === savedJSON) return;
    const when = new Date(d.at).toLocaleString();
    if (d.base !== hash(savedJSON) &&
        !confirm(`You have unsaved admin edits from ${when}, but data.js has changed since then.\n\nOK = restore those edits (overwrites the newer data.js when you save)\nCancel = throw them away`)) {
      ls.del(DRAFT_KEY);
      return;
    }
    data = d.data;
    toast(`Restored your unsaved edits from ${when}. Images you picked before reloading need to be uploaded again.`, false, 6500);
  }

  /* ---------- sidebar ---------- */
  function paintNav() {
    const nav = $('#sidebar');
    nav.replaceChildren(...KEYS.map(key => {
      const sec = SECTIONS[key];
      const n = Array.isArray(data[key]) ? data[key].length : null;
      return el('button', {
        class: 'nav-item', type: 'button', 'aria-current': String(key === current),
        onclick: () => { current = key; history.replaceState(null, '', '#' + key); renderSection(); window.scrollTo(0, 0); $('#editor').focus({ preventScroll: true }); }
      }, el('span', { class: 'ico', text: sec.icon }), sec.label, n != null && el('span', { class: 'count', text: n }));
    }));
  }

  /* ---------- rendering ---------- */
  function renderSection() {
    const key = current;
    const sec = SECTIONS[key];
    const ctx = { rerender: renderSection };
    const head = el('div', { class: 'sec-head' }, el('div', {}, el('h1', { text: sec.label }), el('p', { text: sec.intro })));
    const body = sec.schema.type === 'object'
      ? el('div', { class: 'card' }, el('div', { class: 'card-body', style: 'border:0' }, renderFields(sec.schema.fields, data[key], ctx)))
      : listControl(sec.schema, data[key], ctx);
    $('#editor').replaceChildren(head, body);
    paintNav();
  }

  function renderFields(fields, obj, ctx) {
    const box = el('div', { class: 'fields cols' });
    for (const def of fields) {
      if (def.when && !def.when(obj)) continue;
      box.append(renderField(def, obj, ctx));
    }
    return box;
  }

  function fieldWrap(def, control, forId) {
    return el('div', { class: 'field' + (def.wide ? ' wide' : '') },
      def.label && el('label', { for: forId, text: def.label }),
      control,
      def.help && el('div', { class: 'help', text: def.help }));
  }

  function renderField(def, obj, ctx) {
    const id = uid();
    const k = def.k;
    const done = () => { changed(); ctx.refresh && ctx.refresh(); };

    switch (def.type) {
      case 'object': {
        if (!obj[k] || typeof obj[k] !== 'object') obj[k] = {};
        const fs = el('fieldset', { class: 'group' }, el('legend', { text: def.label }));
        def.fields.forEach(f => fs.append(renderField(f, obj[k], ctx)));
        return el('div', { class: 'field wide' }, fs, def.help && el('div', { class: 'help', text: def.help }));
      }
      case 'list': {
        if (!Array.isArray(obj[k])) obj[k] = [];
        return el('div', { class: 'field wide' }, el('div', { class: 'field-label', text: def.label }), listControl({ ...def, nested: true }, obj[k], ctx));
      }
      case 'textarea':
        return fieldWrap(def, el('textarea', { id, value: obj[k] || '', oninput: e => { obj[k] = e.target.value; done(); } }), id);
      case 'lines':
      case 'paras':
      case 'tags': {
        const sep = { lines: '\n', paras: '\n\n', tags: ', ' }[def.type];
        const split = { lines: /\n/, paras: /\n\s*\n/, tags: /,/ }[def.type];
        const ta = el('textarea', {
          id, value: (obj[k] || []).join(sep),
          rows: def.type === 'tags' ? 2 : Math.min(10, Math.max(3, (obj[k] || []).length + (def.type === 'paras' ? 4 : 1))),
          oninput: e => { obj[k] = e.target.value.split(split).map(s => s.trim()).filter(Boolean); done(); }
        });
        return fieldWrap(def, ta, id);
      }
      case 'range': {
        const out = el('output', { text: (obj[k] ?? 0) + '%' });
        const r = el('input', { id, type: 'range', min: '0', max: '100', step: '5', value: String(obj[k] ?? 0), oninput: e => { obj[k] = +e.target.value; out.textContent = obj[k] + '%'; done(); } });
        return fieldWrap(def, el('div', { class: 'range-row' }, r, out), id);
      }
      case 'select': {
        const opts = def.options.map(o => Array.isArray(o) ? o : [o, o]);
        const s = el('select', {
          id, onchange: e => {
            obj[k] = e.target.value;
            if (def.onChange) def.onChange(obj);
            done();
            if (def.rerender) ctx.rerender();
          }
        }, opts.map(([v, t]) => el('option', { value: v, text: t })));
        s.value = obj[k] ?? opts[0][0];
        return fieldWrap(def, s, id);
      }
      case 'checkbox': {
        const c = el('input', { type: 'checkbox', id, checked: !!obj[k], onchange: e => { if (e.target.checked) obj[k] = true; else delete obj[k]; done(); } });
        return el('div', { class: 'field' + (def.wide ? ' wide' : '') }, el('div', { class: 'field-label', text: ' ' }), el('label', { class: 'toggle', for: id }, c, def.label));
      }
      case 'checks': {
        if (!Array.isArray(obj[k])) obj[k] = [];
        const box = el('div', { class: 'checks' }, def.options.map(o => el('label', { class: 'chip' },
          el('input', {
            type: 'checkbox', checked: obj[k].includes(o),
            onchange: e => {
              const set = new Set(obj[k]);
              e.target.checked ? set.add(o) : set.delete(o);
              obj[k] = def.options.filter(x => set.has(x));
              done();
            }
          }), o)));
        return el('div', { class: 'field wide' }, el('div', { class: 'field-label', text: def.label }), box, def.help && el('div', { class: 'help', text: def.help }));
      }
      case 'image':
      case 'file': {
        const ctl = fileControl({
          id, folder: def.folder, accept: def.accept, fixedName: def.fixedName, isFile: def.type === 'file',
          get: () => obj[k], set: v => { obj[k] = v; done(); },
          onDims: def.dims ? d => { obj.w = d.w; obj.h = d.h; changed(); ctx.rerender(); } : null
        });
        return fieldWrap(def, ctl, id);
      }
      case 'images': {
        if (!Array.isArray(obj[k])) obj[k] = [];
        const arr = obj[k];
        const list = el('div', { class: 'img-list' });
        const paint = () => {
          list.replaceChildren(...arr.map((p, i) => el('div', { class: 'img-row' },
            fileControl({ folder: def.folder, get: () => arr[i], set: v => { arr[i] = v; done(); } }),
            el('button', { type: 'button', class: 'icon-btn danger', title: 'Remove', 'aria-label': 'Remove image', text: '✕', onclick: () => { arr.splice(i, 1); done(); paint(); } }))),
          el('button', {
            type: 'button', class: 'btn btn-sm', text: '+ Add images', style: 'justify-self:start',
            onclick: () => pickFiles('image/*', true, files => {
              files.forEach(f => arr.push(stagePending(def.folder, f)));
              done(); paint();
            })
          }));
        };
        paint();
        return el('div', { class: 'field wide' }, el('div', { class: 'field-label', text: def.label }), list, def.help && el('div', { class: 'help', text: def.help }));
      }
      default: {
        const type = def.input || 'text';
        const val = obj[k] ?? '';
        const inp = el('input', {
          id, type, value: String(val), autocomplete: 'off', spellcheck: type === 'text' ? 'true' : 'false',
          oninput: e => {
            const v = e.target.value;
            if (type === 'number') {
              if (v === '') { if (def.optional) delete obj[k]; else obj[k] = 0; }
              else if (!isNaN(+v)) obj[k] = +v;
            } else obj[k] = v;
            done();
          }
        });
        return fieldWrap(def, inp, id);
      }
    }
  }

  /* ---------- list of cards ---------- */
  function iconNode(icon) {
    if (!icon) return null;
    return /^devicon-/.test(icon) ? el('i', { class: icon + ' ic' }) : el('span', { class: 'ic', text: icon });
  }

  function listControl(def, arr, parentCtx) {
    const list = el('div', { class: def.nested ? 'sub-list' : 'list' });
    if (!arr.length) list.append(el('div', { class: 'empty', text: 'Nothing here yet.' }));
    arr.forEach((item, i) => list.append(cardFor(def, arr, i, parentCtx)));

    const add = el('button', {
      type: 'button', class: 'btn btn-dashed', text: '+ ' + (def.addLabel || 'Add'),
      onclick: () => { const it = def.blank(); arr.push(it); openSet.add(it); changed(); parentCtx.rerender(); }
    });
    const actions = el('div', { class: 'list-actions' }, add);
    if (def.bulk) {
      actions.append(el('button', {
        type: 'button', class: 'btn btn-dashed', text: '⬆ ' + def.bulk.label,
        onclick: () => pickFiles('image/*', true, async files => {
          for (const f of files) {
            const path = stagePending(def.bulk.folder, f);
            const item = def.bulk.make(path, await imgSize(f));
            Object.keys(item).forEach(x => item[x] === undefined && delete item[x]);
            arr.push(item);
            openSet.add(item);
          }
          changed(); parentCtx.rerender();
          toast(`Added ${files.length} photo${files.length > 1 ? 's' : ''} at the bottom. Fill in alt text and category, then Save.`);
        })
      }));
    }
    return el('div', { style: 'display:grid;gap:10px' }, list, actions);
  }

  function cardFor(def, arr, i, parentCtx) {
    const item = arr[i];
    const card = el('div', { class: 'card' + (openSet.has(item) ? ' open' : '') });
    const toggle = el('button', { type: 'button', class: 'card-toggle', 'aria-expanded': String(openSet.has(item)) });
    const body = el('div', { class: 'card-body' });

    const paintHead = () => {
      let visual = null;
      if (def.thumb) {
        const p = def.thumb(item);
        visual = p ? el('img', { class: 'mini', src: srcFor(p), alt: '', loading: 'lazy' }) : el('span', { class: 'mini' });
      } else if (def.icon) visual = iconNode(def.icon(item));
      toggle.replaceChildren(...[
        el('span', { class: 'chev', text: '›', 'aria-hidden': 'true' }), visual,
        el('span', { class: 'card-text' }, el('span', { class: 'card-title', text: def.title(item) }), def.sub && el('span', { class: 'card-sub', text: def.sub(item) }))
      ].filter(Boolean));
    };
    const ctx = { rerender: parentCtx.rerender, refresh: () => { paintHead(); parentCtx.refresh && parentCtx.refresh(); } };
    const open = yes => {
      yes ? openSet.add(item) : openSet.delete(item);
      card.classList.toggle('open', yes);
      toggle.setAttribute('aria-expanded', String(yes));
      body.hidden = !yes;
      if (yes && !body.firstChild) body.append(renderFields(def.fields, item, ctx));
    };
    toggle.addEventListener('click', () => open(!openSet.has(item)));
    paintHead();

    const move = d => { arr.splice(i + d, 0, arr.splice(i, 1)[0]); changed(); parentCtx.rerender(); };
    const tools = el('div', { class: 'card-tools' },
      el('button', { type: 'button', class: 'icon-btn', title: 'Move up', 'aria-label': 'Move up', text: '↑', disabled: i === 0, onclick: () => move(-1) }),
      el('button', { type: 'button', class: 'icon-btn', title: 'Move down', 'aria-label': 'Move down', text: '↓', disabled: i === arr.length - 1, onclick: () => move(1) }),
      el('button', {
        type: 'button', class: 'icon-btn', title: 'Duplicate', 'aria-label': 'Duplicate', text: '⧉',
        onclick: () => { const c = JSON.parse(JSON.stringify(item)); if ('id' in c && c.id) c.id += '-copy'; arr.splice(i + 1, 0, c); openSet.add(c); changed(); parentCtx.rerender(); }
      }),
      el('button', {
        type: 'button', class: 'icon-btn danger', title: 'Delete', 'aria-label': 'Delete', text: '🗑',
        onclick: () => { if (confirm(`Delete "${def.title(item)}"?`)) { arr.splice(i, 1); changed(); parentCtx.rerender(); } }
      }));

    card.append(el('div', { class: 'card-head' }, toggle, tools), body);
    open(openSet.has(item));
    return card;
  }

  /* ---------- files & images ---------- */
  const srcFor = p => blobURLs.get(p) || p;

  function safeName(name) {
    const dot = name.lastIndexOf('.');
    const base = (dot > 0 ? name.slice(0, dot) : name).toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'file';
    const ext = dot > 0 ? name.slice(dot + 1).toLowerCase().replace(/[^a-z0-9]/g, '') : '';
    return { base, ext: ext === 'jpeg' ? 'jpg' : ext };
  }

  // Register a picked file under a path that doesn't clash with anything already used.
  function stagePending(folder, file, fixedName) {
    let path;
    if (fixedName) path = `${folder}/${fixedName}`;
    else {
      const { base, ext } = safeName(file.name);
      const used = JSON.stringify(data);
      let n = 1;
      do { path = `${folder}/${base}${n > 1 ? '-' + n : ''}${ext ? '.' + ext : ''}`; n++; }
      while ((pending.has(path) && pending.get(path) !== file) || used.includes(`"${path}"`));
    }
    pending.set(path, file);
    if (blobURLs.has(path)) URL.revokeObjectURL(blobURLs.get(path));
    blobURLs.set(path, URL.createObjectURL(file));
    return path;
  }

  function pickFiles(accept, multiple, cb) {
    const inp = el('input', { type: 'file', accept, multiple });
    inp.addEventListener('change', () => { if (inp.files.length) cb(Array.from(inp.files)); });
    inp.click();
  }

  function imgSize(file) {
    return new Promise(res => {
      if (!file.type.startsWith('image/')) return res(null);
      const img = new Image();
      const url = URL.createObjectURL(file);
      img.onload = () => { res({ w: img.naturalWidth, h: img.naturalHeight }); URL.revokeObjectURL(url); };
      img.onerror = () => { res(null); URL.revokeObjectURL(url); };
      img.src = url;
    });
  }

  function fileControl(o) {
    const thumb = el('div', { class: 'thumb' });
    const tag = el('span', { class: 'badge-new' });
    const input = el('input', { id: o.id, type: 'text', value: o.get() || '', placeholder: o.folder + '/…', spellcheck: 'false', autocomplete: 'off' });
    const paint = () => {
      const p = input.value.trim();
      thumb.replaceChildren();
      if (!p) thumb.textContent = 'none';
      else if (o.isFile) thumb.textContent = (p.split('.').pop() || 'file').toUpperCase();
      else {
        const img = el('img', { src: srcFor(p), alt: '', loading: 'lazy' });
        img.onerror = () => { thumb.replaceChildren(); thumb.textContent = 'not found'; };
        thumb.append(img);
      }
      tag.textContent = pending.has(p) ? 'New · uploads when you Save' : '';
    };
    const take = async file => {
      const path = stagePending(o.folder, file, o.fixedName);
      input.value = path;
      o.set(path);
      paint();
      if (o.onDims) { const d = await imgSize(file); if (d) o.onDims(d); }
    };
    input.addEventListener('input', () => { o.set(input.value.trim()); paint(); });
    const box = el('div', { class: 'img-field' }, thumb,
      el('div', { class: 'grow' }, input, el('div', { class: 'acts' },
        el('button', { type: 'button', class: 'btn btn-sm', text: 'Upload…', onclick: () => pickFiles(o.accept || 'image/*', false, f => take(f[0])) }),
        tag)));
    box.addEventListener('dragover', e => { e.preventDefault(); box.classList.add('drop'); });
    box.addEventListener('dragleave', () => box.classList.remove('drop'));
    box.addEventListener('drop', e => { e.preventDefault(); box.classList.remove('drop'); const f = e.dataTransfer.files[0]; if (f) take(f); });
    paint();
    return box;
  }

  /* ---------- writing data.js ---------- */
  const IDENT = /^[A-Za-z_$][\w$]*$/;
  const keyLit = k => (IDENT.test(k) ? k : JSON.stringify(k));
  const entries = v => Object.entries(v).filter(([, x]) => x !== undefined);
  function flatLit(v) {
    if (v === null || typeof v !== 'object') return JSON.stringify(v) ?? 'null';
    if (Array.isArray(v)) return v.length ? '[' + v.map(flatLit).join(', ') + ']' : '[]';
    const e = entries(v);
    return e.length ? '{ ' + e.map(([k, x]) => keyLit(k) + ': ' + flatLit(x)).join(', ') + ' }' : '{}';
  }
  function lit(v, ind = '') {
    const flat = flatLit(v);
    if (v === null || typeof v !== 'object' || ind.length + flat.length <= 120) return flat;
    const inner = ind + '  ';
    if (Array.isArray(v)) return '[\n' + v.map(x => inner + lit(x, inner)).join(',\n') + '\n' + ind + ']';
    return '{\n' + entries(v).map(([k, x]) => inner + keyLit(k) + ': ' + lit(x, inner)).join(',\n') + '\n' + ind + '}';
  }

  const NOTES = {
    SITE: '1. SITE / CONTACT INFO',
    STATS: '2. ABOUT — animated counters',
    SKILLS: '3. SKILLS\n   level: 0–100 (bar %)  ·  icon: a Devicon class (https://devicon.dev) or any emoji',
    PROJECTS: '4. PROJECTS\n   categories: "web" | "ai" | "desktop" | "creative"\n   video: optional YouTube/Facebook link  ·  github / live: "" hides the button',
    GALLERY: '5. PHOTOGRAPHY & VIDEO GALLERY\n   photo: { type: "photo", category: "portraits"|"kids"|"drone"|"events", src, thumb?, w, h, alt, caption, fav? }\n   video: { type: "video", video: "<Facebook/YouTube link or assets/videos/x.mp4>", cover?, vertical?, alt, caption, fav? }\n   Facebook videos must be Public.',
    TIMELINE: '6. EDUCATION & EXPERIENCE (newest first)\n   status: "ongoing" | "upcoming" | ""',
    CERTIFICATIONS: '7. CERTIFICATIONS & ACHIEVEMENTS\n   link: optional URL to the certificate'
  };

  function normalize() {
    const seen = new Set();
    data.PROJECTS.forEach(p => {
      let id = (p.id || '').trim() || safeName(p.title || 'project').base;
      id = id.replace(/\s+/g, '-');
      let n = 2; const root = id;
      while (seen.has(id)) id = `${root}-${n++}`;
      seen.add(id); p.id = id;
    });
  }

  function buildDataJs() {
    normalize();
    const bar = '/* ' + '-'.repeat(69);
    let out = '/* =====================================================================\n' +
      '   data.js — ALL OF YOUR CONTENT LIVES HERE\n' +
      '   ---------------------------------------------------------------------\n' +
      '   Edit it with admin.html (recommended) or by hand.\n' +
      '   Keep the commas between { … } blocks if you edit by hand.\n' +
      '   ===================================================================== */\n';
    for (const k of KEYS) {
      out += `\n${bar}\n   ${NOTES[k]}\n   ${'-'.repeat(69)} */\nconst ${k} = ${lit(data[k])};\n`;
    }
    return out;
  }

  function parseDataJs(code) {
    // data.js is your own file: run it in isolation and grab the seven constants.
    const r = new Function(code + `\n;return { ${KEYS.join(', ')} };`)();
    KEYS.forEach(k => { if (r[k] == null) throw new Error(`data.js is missing ${k}`); });
    return JSON.parse(JSON.stringify(r));
  }

  function adoptRemote(code, from) {
    let incoming;
    try { incoming = parseDataJs(code); } catch (e) { toast(`Couldn't read data.js from ${from}: ${e.message}`, true, 8000); return; }
    const json = JSON.stringify(incoming);
    if (json === savedJSON) return;                       // same as what we started from
    if (JSON.stringify(data) === json) { savedJSON = json; paintStatus(); return; }
    if (isDirty() && !confirm(`data.js in ${from} is different from the copy this page loaded.\n\nOK = load it (your unsaved edits here are thrown away)\nCancel = keep editing; Save will overwrite it`)) return;
    data = incoming; savedJSON = json;
    pending.clear(); ls.del(DRAFT_KEY);
    renderSection(); paintStatus();
    toast(`Loaded the latest content from ${from}.`);
  }

  /* ---------- connections: local folder ---------- */
  function idb(mode, fn) {
    return new Promise((res, rej) => {
      const o = indexedDB.open('portfolio-admin', 1);
      o.onupgradeneeded = () => o.result.createObjectStore('kv');
      o.onerror = () => rej(o.error);
      o.onsuccess = () => {
        const tx = o.result.transaction('kv', mode);
        const r = fn(tx.objectStore('kv'));
        tx.oncomplete = () => res(r && r.result);
        tx.onerror = () => rej(tx.error);
      };
    });
  }

  async function ensurePerm(dir) {
    const opt = { mode: 'readwrite' };
    if ((await dir.queryPermission(opt)) === 'granted') return true;
    return (await dir.requestPermission(opt)) === 'granted';
  }

  async function walk(path, create) {
    const parts = path.split('/');
    const name = parts.pop();
    let d = conn.dir;
    for (const p of parts) d = await d.getDirectoryHandle(p, { create });
    return d.getFileHandle(name, { create });
  }
  const readLocal = async path => (await (await walk(path, false)).getFile()).text();
  async function writeLocal(path, content) {
    const w = await (await walk(path, true)).createWritable();
    await w.write(content);
    await w.close();
  }

  async function pickFolder() {
    const st = $('#folderState');
    if (!window.showDirectoryPicker) { st.className = 'hint err'; st.textContent = 'This browser can\'t open folders. Use Chrome or Edge, or GitHub below.'; return; }
    let dir;
    try { dir = await window.showDirectoryPicker({ id: 'portfolio', mode: 'readwrite' }); } catch (e) { return; }
    conn.dir = dir;
    try { await readLocal(DATA_PATH); } catch (e) {
      conn.dir = null; st.className = 'hint err'; st.textContent = `"${dir.name}" has no js/data.js. Pick the PORTFOLIO folder itself.`; return;
    }
    try { await idb('readwrite', s => s.put(dir, 'dir')); } catch (e) { /* remembered for this session only */ }
    setMode('local');
    $('#connDialog').close();
    toast(`Connected to folder "${dir.name}". Save now writes there.`);
    adoptRemote(await readLocal(DATA_PATH), 'your folder');
  }

  /* ---------- connections: GitHub ---------- */
  async function gh(path, opts = {}) {
    const g = conn.gh;
    const r = await fetch(`https://api.github.com/repos/${encodeURIComponent(g.owner)}/${encodeURIComponent(g.repo)}${path}`, {
      method: opts.method || 'GET',
      cache: 'no-store',
      headers: {
        Authorization: 'Bearer ' + g.token,
        Accept: 'application/vnd.github+json',
        'X-GitHub-Api-Version': '2022-11-28',
        ...(opts.body ? { 'Content-Type': 'application/json' } : {})
      },
      body: opts.body ? JSON.stringify(opts.body) : undefined
    });
    if (!r.ok) {
      let m = r.statusText;
      try { m = (await r.json()).message || m; } catch (e) { /* not json */ }
      const hint = r.status === 401 ? ' (token wrong or expired)' : r.status === 404 ? ' (repo/branch not found, or the token can\'t see it)' : r.status === 403 ? ' (token needs Contents: Read and write)' : '';
      throw new Error(`GitHub ${r.status}: ${m}${hint}`);
    }
    return r.status === 204 ? null : r.json();
  }

  const b64ToText = b64 => new TextDecoder().decode(Uint8Array.from(atob(b64.replace(/\s/g, '')), c => c.charCodeAt(0)));
  const fileB64 = file => new Promise((res, rej) => {
    const fr = new FileReader();
    fr.onload = () => res(String(fr.result).split(',')[1]);
    fr.onerror = () => rej(fr.error);
    fr.readAsDataURL(file);
  });

  async function ghReadData() {
    const meta = await gh(`/contents/${DATA_PATH}?ref=${encodeURIComponent(conn.gh.branch)}`);
    conn.ghSha = meta.sha;
    return b64ToText(meta.content);
  }

  async function connectGitHub() {
    const st = $('#ghState');
    const g = {
      owner: $('#ghOwner').value.trim(), repo: $('#ghRepo').value.trim(),
      branch: $('#ghBranch').value.trim(), token: $('#ghToken').value.trim()
    };
    if (!g.owner || !g.repo || !g.token) { st.className = 'hint err'; st.textContent = 'Fill in owner, repository and token.'; return; }
    conn.gh = g;
    st.className = 'hint'; st.textContent = 'Checking…';
    try {
      const repo = await gh('');
      if (!g.branch) { g.branch = repo.default_branch; $('#ghBranch').value = g.branch; }
      if (repo.permissions && !repo.permissions.push) throw new Error('This token can read but not write to the repo.');
      const code = await ghReadData();
      ls.set(GH_KEY, JSON.stringify(g));
      setMode('github');
      st.className = 'hint ok'; st.textContent = 'Connected.';
      $('#connDialog').close();
      toast(`Connected to ${g.owner}/${g.repo}. Save now publishes to the live site.`);
      adoptRemote(code, 'GitHub');
    } catch (e) {
      st.className = 'hint err'; st.textContent = e.message;
    }
  }

  async function saveGitHub(js, files) {
    const g = conn.gh;
    const branch = encodeURIComponent(g.branch);
    const cur = await gh(`/contents/${DATA_PATH}?ref=${branch}`);
    if (conn.ghSha && cur.sha !== conn.ghSha &&
        !confirm('data.js on GitHub was changed somewhere else since you opened it here.\n\nOK = overwrite it with your version\nCancel = stop (reconnect GitHub to load the newer one)')) {
      throw new Error('Save cancelled. Nothing was changed.');
    }
    const ref = await gh(`/git/ref/heads/${branch}`);
    const head = await gh(`/git/commits/${ref.object.sha}`);
    const tree = [];
    for (const [path, file] of files) {
      setSaving(`Uploading ${path.split('/').pop()}…`);
      const blob = await gh('/git/blobs', { method: 'POST', body: { content: await fileB64(file), encoding: 'base64' } });
      tree.push({ path, mode: '100644', type: 'blob', sha: blob.sha });
    }
    tree.push({ path: DATA_PATH, mode: '100644', type: 'blob', content: js });
    setSaving('Committing…');
    const t = await gh('/git/trees', { method: 'POST', body: { base_tree: head.tree.sha, tree } });
    const c = await gh('/git/commits', { method: 'POST', body: { message: commitMessage(files), tree: t.sha, parents: [ref.object.sha] } });
    await gh(`/git/refs/heads/${branch}`, { method: 'PATCH', body: { sha: c.sha } });
    try { conn.ghSha = (await gh(`/contents/${DATA_PATH}?ref=${branch}`)).sha; } catch (e) { conn.ghSha = null; }
  }

  function commitMessage(files) {
    const before = JSON.parse(savedJSON);
    const changedSecs = KEYS.filter(k => JSON.stringify(before[k]) !== JSON.stringify(data[k])).map(k => SECTIONS[k].label);
    let msg = 'Update ' + (changedSecs.length ? changedSecs.join(', ') : 'files') + ' via admin';
    if (files.size) msg += `\n\nAdded: ${[...files.keys()].join(', ')}`;
    return msg;
  }

  /* ---------- connection mode ---------- */
  function setMode(mode) {
    conn.mode = mode;
    ls.set(MODE_KEY, mode);
    const b = $('#connBtn');
    b.dataset.mode = mode;
    $('#connLabel').textContent =
      mode === 'local' ? `Folder: ${conn.dir ? conn.dir.name : 'PORTFOLIO'}` :
      mode === 'github' && conn.gh ? `GitHub: ${conn.gh.owner}/${conn.gh.repo}` :
      mode === 'download' ? 'Saving as download' : 'Not connected';
    $('#saveBtn').textContent = mode === 'local' ? 'Save' : mode === 'github' ? 'Publish' : mode === 'download' ? 'Download' : 'Save';
    document.querySelectorAll('.opt').forEach((o, i) => o.classList.toggle('active', ['local', 'github', 'download'][i] === mode));
  }

  /* ---------- save ---------- */
  let saving = false;
  function setSaving(label) {
    const b = $('#saveBtn');
    b.disabled = !!label;
    if (label) b.textContent = label; else setMode(conn.mode);
  }

  function download(name, blob) {
    const a = el('a', { href: URL.createObjectURL(blob), download: name });
    document.body.append(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
  }

  async function save() {
    if (saving) return;
    if (conn.mode === 'none') { openConn(); toast('Choose where Save should go first.'); return; }
    if (!isDirty()) { toast('Nothing to save. Everything is up to date.'); return; }
    saving = true;
    setSaving('Saving…');
    const js = buildDataJs();
    const files = new Map(pending);
    try {
      parseDataJs(js);   // sanity check: never write a file the site can't load
      if (conn.mode === 'local') {
        if (!conn.dir) throw new Error('Pick your PORTFOLIO folder again (click the connection button).');
        if (!(await ensurePerm(conn.dir))) throw new Error('Permission to write to the folder was denied.');
        for (const [path, file] of files) { setSaving(`Writing ${path.split('/').pop()}…`); await writeLocal(path, file); }
        await writeLocal(DATA_PATH, js);
        toast(`Saved to your folder${files.size ? ` with ${files.size} new file${files.size > 1 ? 's' : ''}` : ''}. Commit and push to update the live site.`, false, 6000);
      } else if (conn.mode === 'github') {
        await saveGitHub(js, files);
        toast('Published! GitHub Pages updates the live site in about a minute.', false, 6000);
      } else {
        download('data.js', new Blob([js], { type: 'text/javascript' }));
        for (const [path, file] of files) download(path.split('/').pop(), file);
        toast(files.size
          ? `Downloaded data.js and ${files.size} file(s). Put data.js in js/, and each image in: ${[...new Set([...files.keys()].map(p => p.slice(0, p.lastIndexOf('/'))))].join(', ')}`
          : 'Downloaded data.js. Replace js/data.js in your project with it.', false, 9000);
      }
      savedJSON = JSON.stringify(data);
      for (const p of files.keys()) pending.delete(p);
      ls.del(DRAFT_KEY);
    } catch (e) {
      toast(e.message || String(e), true, 9000);
    } finally {
      saving = false;
      setSaving(null);
      paintStatus();
    }
  }

  /* ---------- dialog ---------- */
  function openConn() {
    const g = conn.gh || {};
    $('#ghOwner').value = g.owner || '';
    $('#ghRepo').value = g.repo || '';
    $('#ghBranch').value = g.branch || '';
    $('#ghToken').value = g.token || '';
    $('#folderState').textContent = conn.dir ? `Connected: ${conn.dir.name}` : '';
    $('#folderState').className = 'hint' + (conn.dir ? ' ok' : '');
    $('#ghState').textContent = conn.mode === 'github' ? 'Connected.' : '';
    $('#ghState').className = 'hint' + (conn.mode === 'github' ? ' ok' : '');
    $('#connDialog').showModal();
  }

  /* ---------- wire up ---------- */
  $('#connBtn').addEventListener('click', openConn);
  $('#pickFolderBtn').addEventListener('click', pickFolder);
  $('#ghConnectBtn').addEventListener('click', connectGitHub);
  $('#useDownloadBtn').addEventListener('click', () => { setMode('download'); $('#connDialog').close(); });
  $('#saveBtn').addEventListener('click', save);
  $('#previewBtn').addEventListener('click', () => {
    writeDraft();
    window.open('index.html?preview', 'portfolio-preview');
    if (pending.size) toast('New images show in the preview after you Save.');
  });
  $('#discardBtn').addEventListener('click', () => {
    if (!confirm('Throw away all unsaved changes?')) return;
    data = JSON.parse(savedJSON);
    pending.clear();
    ls.del(DRAFT_KEY);
    renderSection(); paintStatus();
    toast('Changes discarded.');
  });
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); }
  });
  window.addEventListener('beforeunload', e => {
    if (isDirty()) { writeDraft(); e.preventDefault(); e.returnValue = ''; }
  });

  async function boot() {
    restoreDraft();
    setMode(conn.mode === 'github' && !conn.gh ? 'none' : conn.mode);
    renderSection();
    paintStatus();

    if (conn.mode === 'github') {
      try { adoptRemote(await ghReadData(), 'GitHub'); }
      catch (e) { toast(`Couldn't reach GitHub: ${e.message}`, true, 8000); }
    } else if (conn.mode === 'local') {
      try {
        conn.dir = await idb('readonly', s => s.get('dir'));
        setMode('local');
        if (conn.dir && (await conn.dir.queryPermission({ mode: 'readwrite' })) === 'granted') adoptRemote(await readLocal(DATA_PATH), 'your folder');
      } catch (e) { conn.dir = null; }
    } else if (conn.mode === 'none') {
      openConn();
    }
  }
  boot();
})();
