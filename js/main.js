/* =====================================================================
   main.js — layout & interactions.
   You normally DON'T need to edit this file: content lives in data.js.
   ===================================================================== */
(() => {
  'use strict';

  /* ---------- helpers ---------- */
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const esc = (v = '') => String(v).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const store = {
    get(k, s = 'localStorage') { try { return window[s].getItem(k); } catch (e) { return null; } },
    set(k, v, s = 'localStorage') { try { window[s].setItem(k, v); } catch (e) { /* storage unavailable */ } }
  };

  const ICONS = {
    play: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v16l13-8z"/></svg>',
    expand: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>',
    github: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>',
    instagram: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37zM17.5 6.5h.01"/></svg>',
    facebook: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
    linkedin: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>',
    youtube: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><path d="M9.75 15.02l5.75-3.27-5.75-3.27z"/></svg>',
    mail: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>',
    phone: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
    pin: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
    external: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/></svg>'
  };

  /* Turn a YouTube / Facebook / .mp4 link into something we can embed */
  function parseVideo(url) {
    if (!url) return null;
    const yt = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/))([\w-]{11})/);
    if (yt) {
      return {
        type: 'youtube',
        thumb: `https://i.ytimg.com/vi/${yt[1]}/hqdefault.jpg`,
        src: auto => `https://www.youtube-nocookie.com/embed/${yt[1]}?rel=0&playsinline=1${auto ? '&autoplay=1' : ''}`
      };
    }
    if (/(facebook\.com|fb\.watch)/i.test(url)) {
      return {
        type: 'facebook',
        thumb: null,
        src: auto => `https://www.facebook.com/plugins/video.php?href=${encodeURIComponent(url)}&show_text=false&width=1280${auto ? '&autoplay=true' : ''}`
      };
    }
    return { type: 'file', thumb: null, src: () => url };
  }

  function videoFrame(item, autoplay) {
    const v = parseVideo(item.video);
    if (!v) return '';
    const title = esc(item.alt || item.caption || item.title || 'Video');
    if (v.type === 'file') {
      return `<video src="${esc(v.src())}" controls playsinline preload="metadata" ${autoplay ? 'autoplay' : ''} ${item.cover ? `poster="${esc(item.cover)}"` : ''} aria-label="${title}"></video>`;
    }
    return `<iframe src="${esc(v.src(autoplay))}" title="${title}" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share; fullscreen"></iframe>`;
  }

  /* ---------- toast ---------- */
  let toastTimer;
  function toast(msg, isError = false) {
    const t = $('#toast');
    t.textContent = msg;
    t.classList.toggle('is-error', isError);
    t.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('is-visible'), 4500);
  }

  /* ---------- theme ---------- */
  function initTheme() {
    const btn = $('#theme-toggle');
    const meta = $('meta[name="theme-color"]');
    const apply = theme => {
      document.documentElement.setAttribute('data-theme', theme);
      btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
      meta.setAttribute('content', theme === 'dark' ? '#0d0d0f' : '#f5f3ee');
    };
    apply(document.documentElement.getAttribute('data-theme') || 'dark');
    btn.addEventListener('click', () => {
      const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      apply(next);
      store.set('theme', next);
    });
  }

  /* ---------- loader ---------- */
  function initLoader() {
    const loader = $('#loader');
    const reveal = () => {
      loader.classList.add('is-done');
      document.body.classList.add('is-ready');
      setTimeout(() => loader.remove(), 900);
    };
    // Play the full sketch animation once per visit, then skip it for faster reloads
    if (reduceMotion || store.get('seenLoader', 'sessionStorage')) { reveal(); return; }
    store.set('seenLoader', '1', 'sessionStorage');
    const minTime = new Promise(r => setTimeout(r, 1900));
    const loaded = new Promise(r => (document.readyState === 'complete' ? r() : window.addEventListener('load', r, { once: true })));
    const maxTime = new Promise(r => setTimeout(r, 3500));
    Promise.race([Promise.all([minTime, loaded]), maxTime]).then(reveal);
  }

  /* ---------- navbar ---------- */
  function initNav() {
    const nav = $('#nav');
    const menu = $('#nav-menu');
    const burger = $('#nav-burger');

    const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 40);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    const setMenu = open => {
      menu.classList.toggle('is-open', open);
      nav.classList.toggle('menu-open', open);
      burger.setAttribute('aria-expanded', String(open));
      burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
      document.body.classList.toggle('no-scroll', open);
    };
    burger.addEventListener('click', () => setMenu(!menu.classList.contains('is-open')));
    $$('a', menu).forEach(a => a.addEventListener('click', () => setMenu(false)));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && menu.classList.contains('is-open')) { setMenu(false); burger.focus(); }
    });
    window.matchMedia('(min-width: 901px)').addEventListener('change', e => { if (e.matches) setMenu(false); });

    // Highlight the link of the section in view
    const links = $$('a', menu);
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        links.forEach(l => {
          const active = l.getAttribute('href') === '#' + en.target.id;
          l.classList.toggle('is-active', active);
          if (active) l.setAttribute('aria-current', 'true'); else l.removeAttribute('aria-current');
        });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    $$('main section[id]').forEach(s => io.observe(s));

    $('#to-top').addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
      $('.nav__logo').focus({ preventScroll: true });
    });
  }

  /* ---------- typing effect ---------- */
  function initTyping() {
    const el = $('#typed');
    const words = SITE.roles || [];
    if (!el || !words.length) return;
    if (reduceMotion) {
      let i = 0;
      el.textContent = words[0];
      setInterval(() => { i = (i + 1) % words.length; el.textContent = words[i]; }, 2500);
      return;
    }
    let w = 0, c = 0, deleting = false;
    const tick = () => {
      const word = words[w];
      c += deleting ? -1 : 1;
      el.textContent = word.slice(0, c);
      let delay = deleting ? 45 : 90;
      if (!deleting && c === word.length) { deleting = true; delay = 1600; }
      else if (deleting && c === 0) { deleting = false; w = (w + 1) % words.length; delay = 350; }
      setTimeout(tick, delay);
    };
    setTimeout(tick, 900);
  }

  /* ---------- scroll reveal ---------- */
  let revealIO;
  function observeReveals(root = document) {
    const els = $$('.reveal:not(.is-visible)', root);
    if (reduceMotion || !('IntersectionObserver' in window)) { els.forEach(e => e.classList.add('is-visible')); return; }
    if (!revealIO) {
      revealIO = new IntersectionObserver(entries => {
        entries.forEach(en => {
          if (en.isIntersecting) { en.target.classList.add('is-visible'); revealIO.unobserve(en.target); }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    }
    els.forEach(e => revealIO.observe(e));
  }

  /* ---------- about: counters ---------- */
  function renderStats() {
    const box = $('#stats');
    box.innerHTML = STATS.map((s, i) => `
      <div class="stat reveal" style="--d:${i * 0.08}s">
        <dt>${esc(s.label)}</dt>
        <dd><span class="count" data-to="${Number(s.value) || 0}">0</span><span class="plus">${esc(s.suffix || '')}</span></dd>
      </div>`).join('');

    const run = el => {
      const to = +el.dataset.to;
      if (reduceMotion) { el.textContent = to; return; }
      const dur = 1600, t0 = performance.now();
      const step = now => {
        const p = Math.min((now - t0) / dur, 1);
        el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => { if (en.isIntersecting) { run(en.target); io.unobserve(en.target); } });
    }, { threshold: 0.6 });
    $$('.count', box).forEach(c => io.observe(c));
  }

  /* ---------- skills ---------- */
  function renderSkills() {
    const tabs = $('#skill-tabs');
    const panels = $('#skill-panels');
    const icon = i => (/^devicon-/.test(i) ? `<i class="${esc(i)}" aria-hidden="true"></i>` : `<span aria-hidden="true">${esc(i)}</span>`);

    tabs.innerHTML = SKILLS.map((g, i) => `
      <button class="tab" role="tab" type="button" id="tab-${i}" aria-controls="panel-${i}" aria-selected="${i === 0}" tabindex="${i === 0 ? 0 : -1}">${esc(g.category)}</button>`).join('');

    panels.innerHTML = SKILLS.map((g, i) => `
      <div class="skill-panel" role="tabpanel" id="panel-${i}" aria-labelledby="tab-${i}" tabindex="0" ${i === 0 ? '' : 'hidden'}>
        ${g.items.map((s, j) => {
          const lvl = Math.max(0, Math.min(100, Number(s.level) || 0));
          return `
          <div class="skill" style="animation-delay:${j * 0.05}s">
            <div class="skill__top">
              <div class="skill__icon">${icon(s.icon || '•')}</div>
              <span class="skill__name">${esc(s.name)}</span>
              <span class="skill__pct">${lvl}%</span>
            </div>
            <div class="skill__bar" role="progressbar" aria-label="${esc(s.name)} proficiency" aria-valuenow="${lvl}" aria-valuemin="0" aria-valuemax="100"><span style="--level:${lvl}%"></span></div>
          </div>`;
        }).join('')}
      </div>`).join('');

    const tabEls = $$('.tab', tabs);
    let sectionSeen = false;
    const animate = panel => {
      if (!sectionSeen) return;
      requestAnimationFrame(() => requestAnimationFrame(() => panel.classList.add('is-animated')));
    };
    const select = idx => {
      tabEls.forEach((t, i) => {
        const on = i === idx;
        t.setAttribute('aria-selected', on);
        t.tabIndex = on ? 0 : -1;
        const p = $('#panel-' + i);
        p.hidden = !on;
        p.classList.remove('is-animated', 'is-entering');
        if (on) { void p.offsetWidth; p.classList.add('is-entering'); animate(p); }
      });
      tabEls[idx].scrollIntoView({ block: 'nearest', inline: 'nearest' });
    };
    tabEls.forEach((t, i) => {
      t.addEventListener('click', () => select(i));
      t.addEventListener('keydown', e => {
        let n = null;
        if (e.key === 'ArrowRight') n = (i + 1) % tabEls.length;
        if (e.key === 'ArrowLeft') n = (i - 1 + tabEls.length) % tabEls.length;
        if (e.key === 'Home') n = 0;
        if (e.key === 'End') n = tabEls.length - 1;
        if (n !== null) { e.preventDefault(); select(n); tabEls[n].focus(); }
      });
    });
    const io = new IntersectionObserver(entries => {
      if (entries.some(en => en.isIntersecting)) {
        sectionSeen = true;
        animate($('.skill-panel:not([hidden])', panels));
        io.disconnect();
      }
    }, { threshold: 0.25 });
    io.observe(panels);
  }

  /* ---------- projects ---------- */
  const CAT_LABEL = { web: 'Web', ai: 'AI', desktop: 'Desktop', creative: 'Creative' };

  function renderProjects() {
    const grid = $('#projects-grid');
    grid.innerHTML = PROJECTS.map((p, i) => `
      <article class="project reveal" style="--d:${(i % 3) * 0.08}s" data-cats="${esc((p.categories || []).join(' '))}" data-id="${esc(p.id)}">
        <div class="project__thumb">
          <img src="${esc(p.thumb)}" alt="${esc(p.title)} preview" width="800" height="500" loading="lazy" decoding="async">
          <div class="project__cats">${(p.categories || []).map(c => `<span>${esc(CAT_LABEL[c] || c)}</span>`).join('')}</div>
        </div>
        <div class="project__body">
          ${p.subtitle ? `<p class="project__sub">${esc(p.subtitle)}</p>` : ''}
          <h3 class="project__title">${esc(p.title)}</h3>
          <p class="project__desc">${esc(p.summary)}</p>
          <div class="tags">${(p.tech || []).map(t => `<span class="tag">${esc(t)}</span>`).join('')}</div>
          <div class="project__actions">
            <button class="btn btn--primary btn--sm" type="button" data-open="${esc(p.id)}">View Details</button>
            ${p.github ? `<a class="btn btn--outline btn--sm" href="${esc(p.github)}" target="_blank" rel="noopener">${ICONS.github} GitHub<span class="sr-only"> (opens in a new tab)</span></a>` : ''}
          </div>
        </div>
      </article>`).join('');

    // open modal: button, or click anywhere on the card that isn't a link
    grid.addEventListener('click', e => {
      if (e.target.closest('a')) return;
      const card = e.target.closest('.project');
      if (card) openProject(card.dataset.id, e.target.closest('button') || card.querySelector('[data-open]'));
    });

    setupFilters('#project-filters', f => {
      $$('.project', grid).forEach(card => { card.hidden = !(f === 'all' || card.dataset.cats.split(' ').includes(f)); });
    });
  }

  function setupFilters(sel, onChange) {
    const group = $(sel);
    group.addEventListener('click', e => {
      const btn = e.target.closest('.filter');
      if (!btn) return;
      $$('.filter', group).forEach(b => { const on = b === btn; b.classList.toggle('is-active', on); b.setAttribute('aria-pressed', on); });
      onChange(btn.dataset.filter);
    });
  }

  /* generic <dialog> handling: focus return, close on backdrop / button */
  function wireDialog(dlg, onClose) {
    let opener = null;
    dlg.addEventListener('click', e => { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });
    dlg.addEventListener('close', () => {
      document.body.classList.remove('no-scroll');
      if (onClose) onClose();
      if (opener && opener.isConnected) opener.focus();
    });
    return trigger => { opener = trigger || document.activeElement; dlg.showModal(); document.body.classList.add('no-scroll'); };
  }

  let showProjectModal;
  function initProjectModal() {
    const dlg = $('#project-modal');
    showProjectModal = wireDialog(dlg, () => { $('#pm-content').innerHTML = ''; });
  }

  function openProject(id, trigger) {
    const p = PROJECTS.find(x => x.id === id);
    if (!p) return;
    let hero;
    if (p.video) hero = `<div class="pm__hero">${videoFrame({ ...p, alt: p.title }, false)}</div>`;
    else if (p.categories && p.categories.includes('creative') && 'video' in p) hero = `<div class="pm__hero"><div class="pm__placeholder">🎬 Video coming soon. Add the link in data.js.</div></div>`;
    else hero = `<div class="pm__hero"><img src="${esc(p.thumb)}" alt="${esc(p.title)} preview" width="800" height="500"></div>`;

    $('#pm-content').innerHTML = `
      ${hero}
      <div class="pm__body">
        <div>
          ${p.subtitle ? `<p class="project__sub">${esc(p.subtitle)}</p>` : ''}
          <h2 id="pm-title">${esc(p.title)}</h2>
        </div>
        <div class="tags">${(p.tech || []).map(t => `<span class="tag">${esc(t)}</span>`).join('')}</div>
        ${(p.description || []).map(d => `<p>${esc(d)}</p>`).join('')}
        ${p.features && p.features.length ? `<p class="pm__label">Key features</p><ul class="pm__features">${p.features.map(f => `<li>${esc(f)}</li>`).join('')}</ul>` : ''}
        ${p.screenshots && p.screenshots.length ? `<p class="pm__label">Screenshots</p><div class="pm__shots">${p.screenshots.map((s, i) => `<img src="${esc(s)}" alt="${esc(p.title)} screenshot ${i + 1}" loading="lazy" width="1200" height="750">`).join('')}</div>` : ''}
        <div class="project__actions">
          ${p.github ? `<a class="btn btn--outline" href="${esc(p.github)}" target="_blank" rel="noopener">${ICONS.github} View on GitHub</a>` : ''}
          ${p.live ? `<a class="btn btn--primary" href="${esc(p.live)}" target="_blank" rel="noopener">Live demo ${ICONS.external}</a>` : ''}
        </div>
      </div>`;
    showProjectModal(trigger);
    $('#project-modal').scrollTop = 0;
  }

  /* ---------- gallery + favorites + lightbox ---------- */
  const isVideo = g => g.type === 'video';
  const catOf = g => (isVideo(g) ? 'video' : g.category);
  const coverOf = g => g.cover || (isVideo(g) ? (parseVideo(g.video) || {}).thumb : g.thumb || g.src);

  function renderGallery() {
    const gallery = $('#gallery');
    const favs = $('#favs');
    const playBadge = `<span class="play-badge">${ICONS.play}</span>`;

    gallery.innerHTML = GALLERY.map((g, i) => {
      const alt = esc(g.alt || g.caption || 'Gallery item');
      const cap = g.caption ? esc(g.caption) : '';
      const dims = g.w && g.h ? `width="${g.w}" height="${g.h}"` : '';
      const cover = coverOf(g);

      // Video with no cover (e.g. Facebook): embed the player itself → shows FB's own cover, plays inline
      if (isVideo(g) && !cover) {
        return `
        <figure class="g-item g-item--embed" data-cat="video" data-index="${i}">
          <div class="g-embed ${g.vertical ? 'g-embed--vertical' : ''}">${videoFrame(g, false)}</div>
          <figcaption><span>${cap || alt}</span>
            <button class="icon-btn g-expand" type="button" data-lb="${i}" aria-label="Open ${alt} in large player">${ICONS.expand}</button>
          </figcaption>
        </figure>`;
      }
      return `
        <figure class="g-item" data-cat="${esc(catOf(g))}" data-index="${i}">
          <button class="g-btn" type="button" data-lb="${i}" aria-label="${isVideo(g) ? 'Play video' : 'View photo'}: ${alt}">
            <img src="${esc(cover)}" alt="${alt}" ${dims} loading="lazy" decoding="async">
            ${isVideo(g) ? `<span class="video-chip">Video</span>${playBadge}` : ''}
          </button>
          ${cap ? `<figcaption>${cap}</figcaption>` : ''}
        </figure>`;
    }).join('');

    const favList = GALLERY.map((g, i) => ({ g, i })).filter(x => x.g.fav);
    if (!favList.length) { favs.previousElementSibling.hidden = true; favs.hidden = true; }
    favs.innerHTML = favList.map(({ g, i }) => `
      <div class="fav">
        <button type="button" data-fav="${i}" aria-label="${isVideo(g) ? 'Play video' : 'View photo'}: ${esc(g.alt || g.caption || '')}">
          <img src="${esc(coverOf(g) || '')}" alt="${esc(g.alt || '')}" width="400" height="500" loading="lazy" decoding="async">
          ${isVideo(g) ? `<span class="play-badge">${ICONS.play}</span>` : ''}
          ${g.caption ? `<span class="fav__cap">${esc(g.caption)}</span>` : ''}
        </button>
        <span class="fav__star" aria-hidden="true">★</span>
      </div>`).join('');

    // lightbox list = whatever is currently visible in the clicked area
    gallery.addEventListener('click', e => {
      const b = e.target.closest('[data-lb]');
      if (!b) return;
      const visible = $$('.g-item:not([hidden])', gallery).map(f => +f.dataset.index);
      openLightbox(visible, +b.dataset.lb, b);
    });
    favs.addEventListener('click', e => {
      const b = e.target.closest('[data-fav]');
      if (b) openLightbox(favList.map(x => x.i), +b.dataset.fav, b);
    });

    setupFilters('#gallery-filters', f => {
      $$('.g-item', gallery).forEach(el => {
        const show = f === 'all' || el.dataset.cat === f;
        el.hidden = !show;
        if (show && !reduceMotion) { el.style.animation = 'none'; void el.offsetWidth; el.style.animation = ''; }
      });
    });
  }

  let lb = { list: [], pos: 0 }, showLightbox;
  function initLightbox() {
    const dlg = $('#lightbox');
    const media = $('#lb-media');
    showLightbox = wireDialog(dlg, () => { media.innerHTML = ''; });

    $('.lightbox__prev', dlg).addEventListener('click', () => step(-1));
    $('.lightbox__next', dlg).addEventListener('click', () => step(1));
    dlg.addEventListener('keydown', e => {
      if (e.key === 'ArrowLeft') { e.preventDefault(); step(-1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); step(1); }
    });
    // swipe
    let x0 = null, y0 = null;
    media.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
    media.addEventListener('touchend', e => {
      if (x0 === null) return;
      const dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) step(dx < 0 ? 1 : -1);
      x0 = y0 = null;
    }, { passive: true });
  }

  function openLightbox(list, index, trigger) {
    lb.list = list;
    lb.pos = Math.max(0, list.indexOf(index));
    showLightbox(trigger);
    drawLightbox();
  }
  function step(d) {
    if (lb.list.length < 2) return;
    lb.pos = (lb.pos + d + lb.list.length) % lb.list.length;
    drawLightbox();
  }
  function drawLightbox() {
    const g = GALLERY[lb.list[lb.pos]];
    const media = $('#lb-media');
    if (isVideo(g)) {
      media.innerHTML = `<div class="lb-frame ${g.vertical ? 'lb-frame--vertical' : ''}">${videoFrame(g, true)}</div>`;
    } else {
      media.innerHTML = `<img src="${esc(g.src)}" alt="${esc(g.alt || '')}">`;
    }
    $('#lb-caption').textContent = g.caption || g.alt || '';
    $('#lb-count').textContent = `${lb.pos + 1} / ${lb.list.length}`;
    $$('.lightbox__nav').forEach(b => { b.hidden = lb.list.length < 2; });
    // preload neighbours
    [1, -1].forEach(d => {
      const n = GALLERY[lb.list[(lb.pos + d + lb.list.length) % lb.list.length]];
      if (n && !isVideo(n)) { const im = new Image(); im.src = n.src; }
    });
  }

  /* ---------- timeline & certifications ---------- */
  function renderTimeline() {
    $('#timeline').innerHTML = TIMELINE.map((t, i) => `
      <li class="tl-item reveal" style="--d:${i * 0.05}s">
        <span class="tl-item__dot" aria-hidden="true"></span>
        <article class="tl-card">
          <div class="tl-card__head">
            <span class="tl-card__period">${esc(t.period)}</span>
            ${t.status ? `<span class="status status--${esc(t.status)}">${esc(t.status)}</span>` : ''}
          </div>
          <h3><span aria-hidden="true">${esc(t.icon || '')}</span> ${esc(t.title)}</h3>
          <p class="tl-card__org">${esc(t.org)}</p>
          ${t.description ? `<p>${esc(t.description)}</p>` : ''}
        </article>
      </li>`).join('');

    $('#certs').innerHTML = CERTIFICATIONS.map((c, i) => `
      <article class="cert reveal" style="--d:${(i % 4) * 0.06}s">
        <div class="cert__badge" aria-hidden="true">${esc(c.icon || '🏅')}</div>
        <div>
          <h4>${esc(c.title)}</h4>
          <p>${esc(c.issuer)}${c.year ? ` · ${esc(c.year)}` : ''}</p>
          ${c.link ? `<a class="link" href="${esc(c.link)}" target="_blank" rel="noopener">View certificate ↗</a>` : ''}
        </div>
      </article>`).join('');
  }

  /* ---------- contact ---------- */
  function renderContact() {
    const items = [
      SITE.email && `<li><a href="mailto:${esc(SITE.email)}"><span class="ci">${ICONS.mail}</span>${esc(SITE.email)}</a></li>`,
      SITE.phone && `<li><a href="tel:${esc(SITE.phone.replace(/\s/g, ''))}"><span class="ci">${ICONS.phone}</span>${esc(SITE.phone)}</a></li>`,
      SITE.location && `<li><span><span class="ci">${ICONS.pin}</span>${esc(SITE.location)}</span></li>`
    ].filter(Boolean);
    $('#contact-list').innerHTML = items.join('');

    const names = { instagram: 'Instagram', facebook: 'Facebook', github: 'GitHub', linkedin: 'LinkedIn', youtube: 'YouTube' };
    $('#socials').innerHTML = Object.entries(SITE.socials || {})
      .filter(([, url]) => url)
      .map(([k, url]) => `<li><a class="icon-btn" href="${esc(url)}" target="_blank" rel="noopener" aria-label="${names[k] || k} (opens in a new tab)">${ICONS[k] || ''}</a></li>`).join('');

    $$('a[href$="resume.pdf"]').forEach(a => { a.href = SITE.resume; });
  }

  function initForm() {
    const form = $('#contact-form');
    const rules = {
      name: v => (v.trim().length >= 2 ? '' : 'Please enter your name.'),
      email: v => (/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()) ? '' : 'Please enter a valid email address.'),
      subject: v => (v.trim().length >= 3 ? '' : 'Please add a short subject.'),
      message: v => (v.trim().length >= 10 ? '' : 'Your message should be at least 10 characters.')
    };
    const check = input => {
      const msg = rules[input.name] ? rules[input.name](input.value) : '';
      input.setAttribute('aria-invalid', msg ? 'true' : 'false');
      $('#e-' + input.name).textContent = msg;
      return !msg;
    };
    const fields = $$('input[name]:not(.hp), textarea', form);
    fields.forEach(f => {
      f.addEventListener('blur', () => { if (f.value) check(f); });
      f.addEventListener('input', () => { if (f.getAttribute('aria-invalid') === 'true') check(f); });
    });

    form.addEventListener('submit', async e => {
      e.preventDefault();
      const bad = fields.filter(f => !check(f));
      if (bad.length) { bad[0].focus(); toast('Please fix the highlighted fields.', true); return; }

      if (!SITE.formspreeId || SITE.formspreeId === 'FORM_ID') {
        toast('The form isn’t connected yet. Add your Formspree ID in js/data.js.', true);
        return;
      }
      const btn = $('button[type="submit"]', form);
      const label = $('.btn__label', btn);
      btn.disabled = true; label.textContent = 'Sending…';
      try {
        const res = await fetch(`https://formspree.io/f/${encodeURIComponent(SITE.formspreeId)}`, {
          method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }
        });
        if (!res.ok) throw new Error('Request failed');
        form.reset();
        fields.forEach(f => f.removeAttribute('aria-invalid'));
        toast('Thanks! Your message was sent. I’ll get back to you soon. ✉️');
      } catch (err) {
        toast('Sorry, your message couldn’t be sent. Please email me directly.', true);
      } finally {
        btn.disabled = false; label.textContent = 'Send Message';
      }
    });
  }

  /* ---------- custom cursor (desktop only) ---------- */
  function initCursor() {
    if (reduceMotion || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
    const dot = document.createElement('div'); dot.className = 'cursor-dot';
    const ring = document.createElement('div'); ring.className = 'cursor-ring';
    document.body.append(dot, ring);
    document.documentElement.classList.add('has-cursor');
    let mx = -100, my = -100, rx = -100, ry = -100;
    window.addEventListener('mousemove', e => {
      mx = e.clientX; my = e.clientY;
      dot.style.transform = `translate3d(${mx}px, ${my}px, 0)`;
    }, { passive: true });
    const loop = () => {
      rx += (mx - rx) * 0.18; ry += (my - ry) * 0.18;
      ring.style.transform = `translate3d(${rx}px, ${ry}px, 0)`;
      requestAnimationFrame(loop);
    };
    loop();
    const hoverSel = 'a, button, [role="tab"], .project, input, textarea';
    document.addEventListener('mouseover', e => { if (e.target.closest(hoverSel)) ring.classList.add('is-hover'); });
    document.addEventListener('mouseout', e => { if (e.target.closest(hoverSel)) ring.classList.remove('is-hover'); });
    document.addEventListener('mouseleave', () => document.documentElement.classList.add('cursor-hidden'));
    document.addEventListener('mouseenter', () => document.documentElement.classList.remove('cursor-hidden'));
    // inside dialogs the top layer would hide our cursor; fall back to the system cursor
    ['project-modal', 'lightbox'].forEach(id => {
      const d = document.getElementById(id);
      new MutationObserver(() => document.documentElement.classList.toggle('has-cursor', !d.open && !$('dialog[open]')))
        .observe(d, { attributes: true, attributeFilter: ['open'] });
    });
  }

  /* ---------- boot ---------- */
  function init() {
    $('#year').textContent = new Date().getFullYear();
    initTheme();
    initLoader();
    initNav();
    initTyping();
    renderStats();
    renderSkills();
    renderProjects();
    initProjectModal();
    renderGallery();
    initLightbox();
    renderTimeline();
    renderContact();
    initForm();
    initCursor();
    observeReveals();
  }

  if (typeof SITE === 'undefined') {
    console.warn('data.js failed to load. Check js/data.js for a syntax error (a missing comma is the usual culprit).');
    const l = document.getElementById('loader'); if (l) l.remove();
    document.body.classList.add('is-ready');
    return;
  }
  init();
})();
