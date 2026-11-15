/* =====================================================================
   preview.js — lets admin.html preview unsaved edits.
   Only does anything when the page is opened as  index.html?preview
   AND the admin has a draft in this browser. Visitors never see it.
   ===================================================================== */
(() => {
  if (!/[?&]preview\b/.test(location.search)) return;
  let draft = null;
  try { draft = JSON.parse(localStorage.getItem('portfolio-admin-draft') || 'null'); } catch (e) { /* ignore */ }
  if (!draft || !draft.data || typeof SITE === 'undefined') return;

  const d = draft.data;
  const swapArr = (target, src) => { if (Array.isArray(src)) target.splice(0, target.length, ...src); };
  if (d.SITE) { Object.keys(SITE).forEach(k => delete SITE[k]); Object.assign(SITE, d.SITE); }
  swapArr(STATS, d.STATS);
  swapArr(SKILLS, d.SKILLS);
  swapArr(PROJECTS, d.PROJECTS);
  swapArr(GALLERY, d.GALLERY);
  swapArr(TIMELINE, d.TIMELINE);
  swapArr(CERTIFICATIONS, d.CERTIFICATIONS);

  // Images uploaded in the admin but not saved yet live only in the admin tab,
  // so they can't show here. Everything else renders exactly as it will look.
  try { sessionStorage.setItem('seenLoader', '1'); } catch (e) { /* ignore */ }
  document.addEventListener('DOMContentLoaded', () => {
    const b = document.createElement('div');
    b.textContent = 'PREVIEW — unsaved changes from Admin';
    b.style.cssText = 'position:fixed;left:50%;bottom:14px;transform:translateX(-50%);z-index:99999;background:#e63946;color:#fff;font:600 12px/1 Inter,system-ui,sans-serif;letter-spacing:.06em;padding:9px 14px;border-radius:999px;box-shadow:0 8px 24px rgba(0,0,0,.4);pointer-events:none';
    document.body.appendChild(b);
  });
})();
