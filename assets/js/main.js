(() => {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- Visitor stats: record what visitors do (only if GA4 / GoatCounter is turned on) ---- */
  const trackEvent = (name, label = '') => {
    try {
      if (typeof window.gtag === 'function') window.gtag('event', name, label ? { item_name: label } : {});
      if (window.goatcounter && typeof window.goatcounter.count === 'function') {
        window.goatcounter.count({ path: name + (label ? '/' + label : ''), title: label || name, event: true });
      }
    } catch { /* stats must never break the site */ }
  };
  document.addEventListener('click', e => {
    const a = e.target.closest('a');
    if (!a) return;
    if (a.hasAttribute('download')) trackEvent('cv_download');
    else if (a.classList.contains('lb-link')) trackEvent('view_original', $('#lbTitle')?.textContent || '');
    else if (a.closest('.social-row')) trackEvent('social_click', a.getAttribute('aria-label') || a.href);
    else if (/^(mailto|tel):/.test(a.getAttribute('href') || '')) trackEvent('contact_click', a.getAttribute('href').split(':')[0]);
    else if (a.classList.contains('btn-gold') && a.hash === '#contact') trackEvent('hire_me_click');
  });

  /* ---- Navbar: mobile toggle, scrolled state, back-to-top ---- */
  const nav = $('#nav');
  const toggle = $('#navToggle');
  toggle?.addEventListener('click', () => {
    const open = nav?.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  $$('#navLinks a').forEach(a => a.addEventListener('click', () => {
    nav?.classList.remove('open');
    toggle?.setAttribute('aria-expanded', 'false');
  }));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') nav?.classList.remove('open'); });

  const bar = document.getElementById('scrollBar');
  const onScroll = () => {
    nav?.classList.toggle('scrolled', scrollY > 10);
    if (bar) {
      const max = document.documentElement.scrollHeight - innerHeight;
      bar.style.transform = `scaleX(${max > 0 ? Math.min(1, scrollY / max) : 0})`;
    }
  };
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
      trackEvent('gallery_open', item.dataset.title || '');
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
      // Static (GitHub Pages) version without a form service: hand the message to the visitor's email app
      if (form.dataset.mailto !== undefined) {
        const f = form.elements;
        const to = form.dataset.mailto;
        if (!to) { setStatus('error', 'The contact form is not set up yet.'); return; }
        const subject = [f.service?.value, f.subject?.value].filter(Boolean).join(' – ') || 'Hello from your portfolio';
        const body = `${f.message.value}\n\n— ${f.name.value} (${f.email.value})`;
        trackEvent('contact_sent', 'email app');
        location.href = `mailto:${to}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
        setStatus('success', `Opening your email app… If nothing happens, email ${to} directly.`);
        return;
      }
      btn.disabled = true;
      const label = btn.querySelector('span');
      const original = label.textContent;
      label.textContent = 'Sending…';
      try {
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const data = await res.json().catch(() => ({ ok: false }));
        // Works with contact.php ({ok, message}) and Formspree ({ok} / {errors: [...]})
        const msg = data.message
          || (Array.isArray(data.errors) && data.errors.map(x => x.message).join(' '))
          || (data.ok ? "Thanks! Your message has been sent. I'll get back to you soon." : 'Something went wrong. Please try again.');
        setStatus(data.ok ? 'success' : 'error', msg);
        if (data.ok) { trackEvent('contact_sent', form.elements.service?.value || ''); form.reset(); }
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

/* ===================== v3 features ===================== */
(() => {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const root = document.documentElement;
  const store = {
    get: k => { try { return localStorage.getItem(k); } catch { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch { /* private mode */ } },
  };
  const gaEvent = (name, label) => {
    try { if (typeof window.gtag === 'function') window.gtag('event', name, label ? { item_name: label } : {}); } catch { /* ignore */ }
  };

  /* ---- Light / dark mode ---- */
  const themeBtn = $('#themeToggle');
  const paintThemeBtn = () => {
    if (!themeBtn) return;
    const light = root.getAttribute('data-theme') === 'light';
    themeBtn.innerHTML = light ? '<i class="fa-solid fa-moon"></i>' : '<i class="fa-solid fa-sun"></i>';
    themeBtn.setAttribute('aria-label', light ? 'Switch to dark mode' : 'Switch to light mode');
    const meta = $('meta[name="theme-color"]');
    if (meta) meta.content = light ? '#f6f4ef' : '#0a0a0a';
  };
  themeBtn?.addEventListener('click', () => {
    const light = root.getAttribute('data-theme') !== 'light';
    if (light) root.setAttribute('data-theme', 'light'); else root.removeAttribute('data-theme');
    store.set('theme', light ? 'light' : 'dark');
    paintThemeBtn();
    gaEvent('theme_switch', light ? 'light' : 'dark');
  });
  paintThemeBtn();

  /* ---- English / Filipino (site buttons & labels; your own text stays as written) ---- */
  const FIL = {
    'nav.home': 'Home', 'nav.about': 'Tungkol', 'nav.services': 'Serbisyo', 'nav.packages': 'Presyo', 'nav.projects': 'Proyekto',
    'nav.gallery': 'Galeriya', 'nav.experience': 'Karanasan', 'nav.testimonials': 'Review', 'nav.stories': 'Kuwento', 'nav.contact': 'Kontak',
    'nav.cta': 'Mag-usap Tayo',
    'hero.im': 'Ako ay isang', 'hero.hire': 'I-hire Ako', 'hero.work': 'Tingnan ang Gawa Ko', 'hero.reel': 'Panoorin ang Showreel', 'hero.follow': 'Sundan ako sa',
    'sec.about': 'Tungkol sa Akin', 'sec.skills': 'Kasanayan at Tools', 'sec.services': 'Mga Serbisyo', 'sec.packages': 'Mga Package at Presyo',
    'sec.projects': 'Mga Proyekto', 'sec.gallery': 'Litrato, Video at Drone', 'sec.before-after': 'Bago at Pagkatapos', 'sec.experience': 'Karanasan',
    'sec.certificates': 'Mga Sertipiko at Parangal', 'sec.gear': 'Mga Gamit Ko', 'sec.testimonials': 'Mga Review', 'sec.stories': 'Mga Kuwento',
    'sec.contact': 'Makipag-ugnayan',
    'sk.what': 'Ang Ginagawa Ko', 'sk.cta': 'Makipagtulungan sa Akin',
    'btn.more': 'Alamin pa', 'btn.book': 'I-book ito', 'pkg.book': 'I-book ang package', 'pkg.popular': 'Pinakasikat',
    'ba.hint': 'I-drag para ikumpara', 'ba.before': 'Bago', 'ba.after': 'Pagkatapos',
    'g.all': 'Lahat', 'story.read': 'Basahin', 'ig.text': 'Mas maraming litrato, reels at behind-the-scenes sa Instagram.', 'ig.follow': 'Sundan sa Instagram',
    'f.name': 'Pangalan Mo', 'f.email': 'Email Mo', 'f.need': 'Ano ang kailangan mo?', 'f.subject': 'Paksa', 'f.message': 'Mensahe Mo', 'f.send': 'Ipadala',
    'f.date': 'Petsa ng event / proyekto', 'f.where': 'Lugar', 'f.wherePh': 'Lungsod / venue', 'f.budget': 'Budget', 'f.budgetPick': 'Pumili…', 'f.notSure': 'Hindi pa sigurado',
    'sec.process': 'Paano Ako Magtrabaho', 'sec.faq': 'Mga Madalas Itanong', 'cl.label': 'Pinagkakatiwalaan ng', 'pr.case': 'Basahin ang case study',
    'ab.call': 'Tawag', 'ab.msg': 'Mensahe', 'ab.book': 'Mag-book',
  };
  const langBtn = $('#langToggle');
  const applyLang = lang => {
    $$('[data-i18n]').forEach(el => {
      if (el.dataset.en === undefined) el.dataset.en = el.textContent;
      el.textContent = lang === 'fil' && FIL[el.dataset.i18n] ? FIL[el.dataset.i18n] : el.dataset.en;
    });
    $$('[data-i18n-ph]').forEach(el => {
      if (el.dataset.enPh === undefined) el.dataset.enPh = el.placeholder;
      el.placeholder = lang === 'fil' && FIL[el.dataset.i18nPh] ? FIL[el.dataset.i18nPh] : el.dataset.enPh;
    });
    root.lang = lang === 'fil' ? 'fil' : 'en';
    if (langBtn) {
      langBtn.textContent = lang === 'fil' ? 'EN' : 'FIL';
      langBtn.setAttribute('aria-label', lang === 'fil' ? 'Switch language to English' : 'Switch language to Filipino');
    }
  };
  if (langBtn) {
    if (store.get('lang') === 'fil') applyLang('fil');
    langBtn.addEventListener('click', () => {
      const next = root.lang === 'fil' ? 'en' : 'fil';
      store.set('lang', next);
      applyLang(next);
      gaEvent('language_switch', next);
    });
  }

  /* ---- "Book this" buttons pre-fill the contact form ---- */
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-book]');
    if (!b) return;
    const form = $('#contactForm');
    if (!form) return;
    const sel = form.elements.service;
    const want = b.dataset.service || '';
    if (sel && want && [...sel.options].some(o => o.value === want)) sel.value = want;
    if (form.elements.subject) form.elements.subject.value = b.dataset.book;
    gaEvent('book_click', b.dataset.book);
    setTimeout(() => form.elements.name?.focus({ preventScroll: true }), 650);
  });

  /* ---- Before / after sliders ---- */
  $$('.ba-frame').forEach(frame => {
    const range = $('.ba-range', frame);
    range?.addEventListener('input', () => frame.style.setProperty('--pos', range.value + '%'));
  });

  /* ---- Showreel ---- */
  const reelBtn = $('#reelBtn');
  const reelBox = $('#reelBox');
  if (reelBtn && reelBox) {
    const stage = $('#reelStage');
    let last = null;
    const close = () => {
      reelBox.hidden = true;
      stage.replaceChildren(); // stops playback
      document.body.classList.remove('lb-open');
      last?.focus();
    };
    reelBtn.addEventListener('click', () => {
      last = document.activeElement;
      const { kind, src } = reelBtn.dataset;
      stage.classList.toggle('vertical', reelBtn.dataset.vertical === '1');
      if (kind === 'file') {
        stage.replaceChildren(Object.assign(document.createElement('video'), { src, controls: true, autoplay: true, playsInline: true }));
      } else {
        const f = Object.assign(document.createElement('iframe'), { src, title: 'Showreel', allowFullscreen: true });
        f.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
        stage.replaceChildren(f);
      }
      reelBox.hidden = false;
      document.body.classList.add('lb-open');
      $('#reelClose').focus();
      gaEvent('showreel_play');
    });
    $('#reelClose').addEventListener('click', close);
    reelBox.addEventListener('click', e => { if (e.target === reelBox) close(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !reelBox.hidden) close(); });
  }

  /* ---- Story page: copy link / native share ---- */
  const copyBtn = $('#copyLink');
  copyBtn?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(copyBtn.dataset.url);
      copyBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
    } catch {
      window.prompt('Copy this link:', copyBtn.dataset.url);
    }
    setTimeout(() => { copyBtn.innerHTML = '<i class="fa-solid fa-link"></i>'; }, 1800);
  });
  const shareBtn = $('#nativeShare');
  if (shareBtn && navigator.share) {
    shareBtn.hidden = false;
    shareBtn.addEventListener('click', () => navigator.share({ title: shareBtn.dataset.title, url: shareBtn.dataset.url }).catch(() => {}));
  }
})();


/* ===================== v4: motion & polish ===================== */
(() => {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const finePointer = matchMedia('(hover: hover) and (pointer: fine)').matches;
  const ga = (n, l) => { try { if (typeof window.gtag === 'function') window.gtag('event', n, l ? { item_name: l } : {}); } catch { /* ignore */ } };

  /* ---- Intro: logo draws itself, once per visit ---- */
  const intro = $('#intro');
  if (intro) {
    document.body.classList.add('intro-on');
    const done = () => {
      intro.classList.add('out');
      document.body.classList.remove('intro-on');
      try { sessionStorage.setItem('introSeen', '1'); } catch { /* ignore */ }
      setTimeout(() => intro.remove(), 700);
    };
    setTimeout(done, 1500);
    intro.addEventListener('click', done);
  }

  /* ---- Gold cursor + "View / Play" labels (computers only) ---- */
  const cursor = $('#cursor');
  if (cursor && finePointer && !reduce) {
    document.documentElement.classList.add('cursor-on');
    const label = $('.cursor-label', cursor);
    let x = innerWidth / 2, y = innerHeight / 2, cx = x, cy = y;
    addEventListener('mousemove', e => { x = e.clientX; y = e.clientY; cursor.classList.add('show'); }, { passive: true });
    document.addEventListener('mouseleave', () => cursor.classList.remove('show'));
    const loop = () => {
      cx += (x - cx) * 0.22; cy += (y - cy) * 0.22;
      cursor.style.transform = `translate(${cx}px, ${cy}px)`;
      requestAnimationFrame(loop);
    };
    loop();
    document.addEventListener('mouseover', e => {
      const t = e.target.closest('.g-item, .ba-frame, .story, .project-thumb, a, button, summary, input, select, textarea, label');
      cursor.classList.remove('hover', 'media', 'text');
      label.textContent = '';
      if (!t) return;
      if (t.matches('input, textarea, select')) { cursor.classList.add('text'); return; }
      if (t.closest('.ba-frame')) { cursor.classList.add('media'); label.textContent = 'Drag'; return; }
      if (t.closest('.g-item')) {
        const it = t.closest('.g-item');
        cursor.classList.add('media');
        label.textContent = it.dataset.kind === 'photos' || it.dataset.kind === 'image' ? 'View' : (it.dataset.kind === 'none' ? '' : 'Play');
        return;
      }
      if (t.closest('.story, .project-thumb')) { cursor.classList.add('media'); label.textContent = 'Open'; return; }
      cursor.classList.add('hover');
    });
  }

  /* ---- Interactive hero (3D tilt / parallax) + magnetic buttons ---- */
  const visual = $('.hero-visual');
  if (visual && finePointer && !reduce) {
    const hero = $('.hero');
    hero.addEventListener('mousemove', e => {
      const r = hero.getBoundingClientRect();
      const dx = (e.clientX - r.left) / r.width - 0.5;
      const dy = (e.clientY - r.top) / r.height - 0.5;
      visual.style.setProperty('--mx', dx.toFixed(3));
      visual.style.setProperty('--my', dy.toFixed(3));
    });
    hero.addEventListener('mouseleave', () => { visual.style.setProperty('--mx', 0); visual.style.setProperty('--my', 0); });
  }
  if (finePointer && !reduce) {
    $$('[data-magnetic], .nav-cta, .btn-play, .pkg.popular .btn').forEach(b => {
      b.addEventListener('mousemove', e => {
        const r = b.getBoundingClientRect();
        b.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * 0.18}px, ${(e.clientY - r.top - r.height / 2) * 0.28}px)`;
      });
      b.addEventListener('mouseleave', () => { b.style.transform = ''; });
    });
  }

  /* ---- Gallery: play uploaded videos silently on hover ---- */
  if (finePointer) {
    $$('.g-item[data-preview]').forEach(it => {
      let v = null;
      it.addEventListener('mouseenter', () => {
        if (!v) {
          v = Object.assign(document.createElement('video'), { src: it.dataset.preview, muted: true, loop: true, playsInline: true, preload: 'auto', className: 'g-hover-video' });
          v.setAttribute('aria-hidden', 'true');
          it.insertBefore(v, it.querySelector('.play') || null);
        }
        v.play().catch(() => {});
        it.classList.add('previewing');
      });
      it.addEventListener('mouseleave', () => { if (v) { v.pause(); v.currentTime = 0; } it.classList.remove('previewing'); });
    });
  }

  /* ---- Images fade in when loaded (no harsh pop-in) ---- */
  $$('.g-item img, .story-img img, .project-thumb img, .cert-img img').forEach(img => {
    if (img.complete) return;
    img.classList.add('fade-img');
    img.addEventListener('load', () => img.classList.add('loaded'), { once: true });
    img.addEventListener('error', () => img.classList.add('loaded'), { once: true });
  });

  /* ---- FAQ: only one open at a time ---- */
  $$('.faq').forEach(d => d.addEventListener('toggle', () => {
    if (d.open) { $$('.faq').forEach(o => { if (o !== d) o.open = false; }); ga('faq_open', $('summary span', d)?.textContent || ''); }
  }));

  /* ---- QR codes (link-in-bio page) ---- */
  const drawQr = () => $$('.qr[data-qr]').forEach(box => {
    if (box.dataset.done || typeof window.QRCode !== 'function') return;
    box.dataset.done = '1';
    new window.QRCode(box, { text: box.dataset.qr, width: 180, height: 180, colorDark: '#111111', colorLight: '#ffffff' });
  });
  addEventListener('load', drawQr);
  $$('details.links-qr').forEach(d => d.addEventListener('toggle', drawQr));

  /* ---- Simple click stats for any element with data-track ---- */
  document.addEventListener('click', e => {
    const t = e.target.closest('[data-track]');
    if (t) ga(t.dataset.track, t.textContent.trim().slice(0, 60));
  });

  /* ---- Phone action bar: hide while the contact form is on screen ---- */
  const bar = $('.action-bar');
  const contact = $('#contact');
  if (bar && contact && 'IntersectionObserver' in window) {
    new IntersectionObserver(([en]) => bar.classList.toggle('away', en.isIntersecting), { threshold: 0.15 }).observe(contact);
  }
})();
