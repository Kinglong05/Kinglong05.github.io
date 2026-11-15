(() => {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- Navbar: mobile toggle, scrolled state, back-to-top ---- */
  const nav = $('#nav');
  const toggle = $('#navToggle');
  toggle?.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  $$('#navLinks a').forEach(a => a.addEventListener('click', () => {
    nav.classList.remove('open');
    toggle?.setAttribute('aria-expanded', 'false');
  }));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') nav.classList.remove('open'); });

  const onScroll = () => nav.classList.toggle('scrolled', scrollY > 10);
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---- Active nav link while scrolling ---- */
  // Picks the section whose top has passed ~35% of the screen. Sections that sit side by side
  // (About + Skills) share the same top, so the one you clicked last wins, otherwise the first.
  const links = $$('#navLinks a[href^="#"]');
  const sections = links.map(a => document.getElementById(a.hash.slice(1))).filter(Boolean);
  let clickedId = null, locked = false, unlockTimer = null;
  const setActive = id => links.forEach(l => l.classList.toggle('active', l.hash.slice(1) === id));
  const spyUpdate = () => {
    if (locked || !sections.length) return;
    const line = innerHeight * 0.35;
    let current = sections[0], bestTop = -Infinity;
    for (const s of sections) {
      const t = s.getBoundingClientRect().top;
      if (t > line) continue;
      if (t > bestTop + 2) { bestTop = t; current = s; }
      else if (Math.abs(t - bestTop) <= 2 && s.id === clickedId) current = s; // side-by-side tie
    }
    if (innerHeight + scrollY >= document.documentElement.scrollHeight - 4) current = sections[sections.length - 1];
    setActive(current.id);
  };
  const unlock = () => { locked = false; clearTimeout(unlockTimer); };
  links.forEach(a => a.addEventListener('click', () => {
    clickedId = a.hash.slice(1);
    setActive(clickedId);
    locked = true; // keep the clicked item highlighted until the page finishes scrolling there
    clearTimeout(unlockTimer);
    unlockTimer = setTimeout(unlock, 1500); // fallback for browsers without "scrollend"
  }));
  addEventListener('scrollend', () => { if (locked) unlock(); });
  addEventListener('scroll', () => { if (!locked) spyUpdate(); }, { passive: true });
  addEventListener('resize', spyUpdate);
  spyUpdate();

  /* ---- Reveal on scroll ---- */
  const rv = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (en.isIntersecting) { en.target.classList.add('in'); rv.unobserve(en.target); }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  $$('.reveal').forEach(el => rv.observe(el));

  /* ---- Count-up stats ("20+" -> animates 0..20, keeps "+") ---- */
  const counter = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (!en.isIntersecting) return;
      counter.unobserve(en.target);
      const el = en.target;
      const m = /^(\D*)(\d+)(.*)$/.exec(el.dataset.count || '');
      if (!m || reduceMotion) return;
      const [, pre, num, post] = m;
      const end = parseInt(num, 10);
      const start = performance.now();
      const dur = 1400;
      const tick = now => {
        const p = Math.min(1, (now - start) / dur);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = pre + Math.round(end * eased) + post;
        if (p < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });
  }, { threshold: 0.6 });
  $$('[data-count]').forEach(el => counter.observe(el));

  /* ---- Testimonials carousel ---- */
  const track = $('#tTrack');
  if (track) {
    const prev = $('#tPrev');
    const next = $('#tNext');
    const step = () => track.querySelector('.testimonial')?.getBoundingClientRect().width + 18 || track.clientWidth;
    const update = () => {
      const max = track.scrollWidth - track.clientWidth - 2;
      prev.disabled = track.scrollLeft <= 2;
      next.disabled = track.scrollLeft >= max;
    };
    prev.addEventListener('click', () => track.scrollBy({ left: -step() }));
    next.addEventListener('click', () => track.scrollBy({ left: step() }));
    track.addEventListener('scroll', update, { passive: true });
    addEventListener('resize', update);
    update();
  }

  /* ---- Projects: view all ---- */
  const viewAll = $('#viewAll');
  viewAll?.addEventListener('click', () => {
    const extras = $$('.project.is-extra');
    const show = viewAll.getAttribute('aria-expanded') !== 'true';
    extras.forEach(p => { p.hidden = !show; if (show) p.classList.add('in'); });
    viewAll.setAttribute('aria-expanded', String(show));
    viewAll.innerHTML = show ? 'Show less <i class="fa-solid fa-arrow-up"></i>' : 'View all projects <i class="fa-solid fa-arrow-right"></i>';
  });

  /* ---- Hero: typing role rotator ---- */
  const rot = $('.rotator');
  if (rot && !reduceMotion) {
    let words = [];
    try { words = JSON.parse(rot.dataset.words || '[]'); } catch { /* ignore */ }
    if (words.length > 1) {
      let w = 0, i = words[0].length, deleting = true;
      const tick = () => {
        const word = words[w];
        if (deleting) {
          i--;
          if (i <= 0) { deleting = false; w = (w + 1) % words.length; }
        } else {
          i++;
          if (i >= words[w].length) { deleting = true; rot.textContent = words[w]; return setTimeout(tick, 1800); }
        }
        rot.textContent = (deleting ? word : words[w]).slice(0, Math.max(0, i));
        setTimeout(tick, deleting ? 45 : 85);
      };
      setTimeout(tick, 2200);
    }
  }

  /* ---- Gallery: filters ---- */
  const grid = $('#galleryGrid');
  $$('.filters .chip').forEach(chip => chip.addEventListener('click', () => {
    $$('.filters .chip').forEach(c => { c.classList.toggle('active', c === chip); c.setAttribute('aria-selected', String(c === chip)); });
    const f = chip.dataset.filter;
    $$('.g-item', grid).forEach(it => {
      const show = f === 'all' || it.dataset.cat === f;
      it.classList.toggle('is-off', !show);
      if (show) it.classList.add('in');
    });
  }));

  /* ---- Gallery: lightbox (photos, YouTube/Vimeo/Drive, mp4) ---- */
  const lb = $('#lightbox');
  if (lb && grid) {
    const stage = $('#lbStage');
    const thumbs = $('#lbThumbs');
    const counter = $('#lbCount');
    const linkBtn = $('#lbLink');
    let list = [], idx = 0, p = 0, lastFocus = null, thumbsFor = null;
    const el = (tag, attrs = {}) => Object.assign(document.createElement(tag), attrs);
    const photosOf = it => {
      if (!it || it.dataset.kind !== 'photos') return [];
      try { return JSON.parse(it.dataset.photos || '[]'); } catch { return []; }
    };

    const buildThumbs = (it, photos) => {
      thumbsFor = it;
      thumbs.replaceChildren();
      thumbs.hidden = photos.length < 2;
      lb.classList.toggle('has-thumbs', photos.length > 1);
      if (photos.length < 2) return;
      photos.forEach((src, n) => {
        const b = el('button', { type: 'button', className: 'lb-thumb', ariaLabel: `Photo ${n + 1}` });
        b.append(el('img', { src, alt: '', loading: 'lazy', decoding: 'async' }));
        b.addEventListener('click', () => { p = n; render(); });
        thumbs.append(b);
      });
    };

    const render = () => {
      const it = list[idx];
      const { kind, src, icon, title, caption } = it.dataset;
      const photos = photosOf(it);
      if (thumbsFor !== it) buildThumbs(it, photos);
      stage.replaceChildren();
      stage.classList.toggle('vertical', it.dataset.vertical === '1' && it.dataset.post !== '1' && !photos.length);
      stage.classList.toggle('post', it.dataset.post === '1' && !photos.length);

      if (photos.length) {
        p = Math.min(Math.max(0, p), photos.length - 1);
        stage.append(el('img', { src: photos[p], alt: `${title} (photo ${p + 1} of ${photos.length})` }));
        [photos[p + 1], photos[p - 1]].forEach(u => { if (u) { const i = new Image(); i.src = u; } }); // preload neighbours
        counter.hidden = photos.length < 2;
        counter.textContent = `${p + 1} / ${photos.length}`;
        [...thumbs.children].forEach((b, n) => b.classList.toggle('active', n === p));
        thumbs.children[p]?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
      } else if (kind === 'image') {
        stage.append(el('img', { src, alt: title }));
      } else if (kind === 'external') {
        // Facebook blocks playing this video on other websites → cover + "Watch on Facebook"
        const box = el('div', { className: 'lb-ext' });
        if (it.dataset.cover) box.style.backgroundImage = `url(${JSON.stringify(it.dataset.cover)})`;
        const play = el('a', { href: src, target: '_blank', rel: 'noopener', className: 'lb-ext-play', ariaLabel: 'Watch on Facebook' });
        play.append(el('i', { className: 'fa-solid fa-play' }));
        const note = el('div', { className: 'lb-ext-note' });
        note.append(el('b', { textContent: 'This video plays on Facebook' }),
          el('span', { textContent: 'Facebook only allows it to be watched on Facebook. Tap play to open it there.' }));
        box.append(play, note);
        stage.append(box);
      } else if (kind === 'iframe') {
        const f = el('iframe', { src, title, allowFullscreen: true });
        f.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
        stage.append(f);
      } else if (kind === 'file') {
        stage.append(el('video', { src, controls: true, autoplay: true, playsInline: true }));
      } else {
        const ph = el('div', { className: 'lb-ph' });
        ph.append(el('i', { className: icon || 'fa-solid fa-image' }));
        if (kind === 'link') {
          ph.append(el('a', { href: src, target: '_blank', rel: 'noopener', className: 'btn btn-gold', textContent: 'Watch video ↗' }));
        } else {
          ph.append(el('p', { textContent: 'Photo coming soon' }));
        }
        stage.append(ph);
      }
      if (!photos.length) counter.hidden = true;
      $('#lbTitle').textContent = title || '';
      $('#lbCaption').textContent = caption || '';
      // "View on Facebook" (or YouTube/Vimeo...) button
      if (it.dataset.link) {
        linkBtn.href = it.dataset.link;
        linkBtn.replaceChildren(el('i', { className: it.dataset.linkIcon || 'fa-solid fa-arrow-up-right-from-square' }), document.createTextNode(' ' + (it.dataset.linkLabel || 'Open original')));
        linkBtn.classList.toggle('fb', (it.dataset.linkIcon || '').includes('facebook'));
        linkBtn.hidden = false;
      } else {
        linkBtn.hidden = true;
      }
      const multi = list.length > 1 || photos.length > 1;
      $('.lb-prev', lb).hidden = !multi;
      $('.lb-next', lb).hidden = !multi;
    };
    const open = item => {
      list = $$('.g-item:not(.is-off)', grid);
      idx = Math.max(0, list.indexOf(item));
      p = 0;
      thumbsFor = null;
      lastFocus = document.activeElement;
      lb.hidden = false;
      document.body.classList.add('lb-open');
      render();
      $('.lb-close', lb).focus();
    };
    const close = () => {
      lb.hidden = true;
      stage.replaceChildren(); // stops any playing video
      thumbs.replaceChildren();
      thumbsFor = null;
      document.body.classList.remove('lb-open');
      lastFocus?.focus();
    };
    // Next/prev walks through an album's photos first, then moves on to the next gallery item
    const go = d => {
      const photos = photosOf(list[idx]);
      if (photos.length > 1 && p + d >= 0 && p + d < photos.length) {
        p += d;
      } else {
        if (list.length < 2 && photos.length > 1) { p = (p + d + photos.length) % photos.length; return render(); }
        idx = (idx + d + list.length) % list.length;
        const n = photosOf(list[idx]).length;
        p = d > 0 ? 0 : Math.max(0, n - 1);
      }
      render();
    };

    grid.addEventListener('click', e => { const btn = e.target.closest('.g-open'); if (btn) open(btn.closest('.g-item')); });
    $('.lb-close', lb).addEventListener('click', close);
    $('.lb-prev', lb).addEventListener('click', () => go(-1));
    $('.lb-next', lb).addEventListener('click', () => go(1));
    lb.addEventListener('click', e => { if (e.target === lb) close(); });
    document.addEventListener('keydown', e => {
      if (lb.hidden) return;
      if (e.key === 'Escape') close();
      else if (e.key === 'ArrowLeft') go(-1);
      else if (e.key === 'ArrowRight') go(1);
    });
    // Swipe on touch screens
    let x0 = null;
    lb.addEventListener('touchstart', e => { x0 = e.target.closest('.lb-thumbs, iframe') ? null : e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', e => {
      if (x0 === null) return;
      const dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 50 && (list.length > 1 || photosOf(list[idx]).length > 1)) go(dx < 0 ? 1 : -1);
      x0 = null;
    });
  }

  /* ---- Contact form (AJAX) ---- */
  const form = $('#contactForm');
  if (form) {
    const status = $('#formStatus');
    const btn = form.querySelector('button[type="submit"]');
    const setStatus = (type, msg) => {
      status.innerHTML = '';
      const span = document.createElement('span');
      span.className = type;
      span.textContent = msg;
      status.appendChild(span);
    };
    form.addEventListener('submit', async e => {
      e.preventDefault();
      let firstBad = null;
      $$('input[required], textarea[required], input[type="email"]', form).forEach(f => {
        const bad = !f.checkValidity();
        f.classList.toggle('invalid', bad);
        if (bad && !firstBad) firstBad = f;
      });
      if (firstBad) {
        setStatus('error', firstBad.name === 'email' ? 'Please enter a valid email address.'
          : firstBad.name === 'message' ? 'Your message should be at least 10 characters.' : 'Please fill in the required fields.');
        firstBad.focus();
        return;
      }
      btn.disabled = true;
      const label = btn.querySelector('span');
      const original = label.textContent;
      label.textContent = 'Sending…';
      try {
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const data = await res.json().catch(() => ({ ok: false, message: 'Something went wrong. Please try again.' }));
        setStatus(data.ok ? 'success' : 'error', data.message);
        if (data.ok) form.reset();
      } catch {
        setStatus('error', 'Network error. Please check your connection and try again.');
      } finally {
        btn.disabled = false;
        label.textContent = original;
      }
    });
    form.addEventListener('input', e => e.target.classList.remove('invalid'));
  }
})();
