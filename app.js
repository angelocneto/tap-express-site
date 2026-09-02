/* TAP Express — scroll engine (canvas frame scrub + Lenis + reveals) */
function initScrub(cfg) {
  const section = document.querySelector(cfg.section);
  const canvas  = section.querySelector("canvas");
  const ctx     = canvas.getContext("2d", { alpha: false });
  const lines   = [...section.querySelectorAll(".reveal-line")];
  const fill    = section.querySelector(".progress-fill");
  const readout = section.querySelector(".frame-readout");
  const bgFill  = cfg.bg || "#07140e";
  const images  = [];
  let firstDrawn = false, current = -1;

  for (let i = 0; i < cfg.frameCount; i++) {
    const img = new Image();
    img.src = cfg.framePath(i + 1);
    img.onload = () => { if (!firstDrawn) { firstDrawn = true; draw(0); } };
    images[i] = img;
  }
  function draw(index) {
    const img = images[index];
    if (!img || !img.complete || !img.naturalWidth) return;
    const cw = canvas.clientWidth, ch = canvas.clientHeight;
    const ir = img.naturalWidth / img.naturalHeight, cr = cw / ch;
    let dw, dh, dx, dy;
    if (ir > cr) { dh = ch; dw = ch * ir; dx = (cw - dw) / 2; dy = 0; }
    else         { dw = cw; dh = cw / ir; dx = 0; dy = (ch - dh) / 2; }
    ctx.fillStyle = bgFill; ctx.fillRect(0, 0, cw, ch);
    ctx.drawImage(img, dx, dy, dw, dh);
  }
  function resize() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width  = canvas.clientWidth  * dpr;
    canvas.height = canvas.clientHeight * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    draw(current < 0 ? 0 : current);
  }
  function update() {
    const rect = section.getBoundingClientRect();
    if (rect.bottom < -window.innerHeight || rect.top > window.innerHeight) return;
    const scrollable = rect.height - window.innerHeight;
    const p = Math.min(Math.max(-rect.top / scrollable, 0), 1);
    const idx = Math.min(cfg.frameCount - 1, Math.floor(p * (cfg.frameCount - 1)));
    if (idx !== current) {
      current = idx; draw(idx);
      if (fill) fill.style.width = (p * 100).toFixed(2) + "%";
      if (readout) readout.textContent = `CENA ${String(idx + 1).padStart(3, "0")} / ${String(cfg.frameCount).padStart(3, "0")}`;
    }
    for (const el of lines) {
      const a = parseFloat(el.dataset.in), b = parseFloat(el.dataset.out);
      const mid = (a + b) / 2, half = (b - a) / 2;
      let o = 1 - Math.abs(p - mid) / half;
      o = Math.max(0, Math.min(1, o * 1.6));
      el.style.opacity = o.toFixed(3);
      const base = el.classList.contains("line") ? "translate(-50%, -50%)" : "translateX(-50%)";
      el.style.transform = `${base} translateY(${(1 - o) * 26}px)`;
    }
  }
  window.addEventListener("resize", resize);
  resize();
  return { update, resize };
}

function animateCount(el) {
  const target = parseFloat(el.dataset.count), suffix = el.dataset.suffix || "";
  const dur = 1400, t0 = performance.now();
  function step(t) {
    const k = Math.min((t - t0) / dur, 1), eased = 1 - Math.pow(1 - k, 3);
    el.textContent = Math.round(target * eased) + suffix;
    if (k < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

document.addEventListener("DOMContentLoaded", () => {
  const scrubs = (window.SCRUB_SECTIONS || [])
    .filter(c => document.querySelector(c.section))
    .map(initScrub);

  const lenis = new Lenis({ lerp: 0.085, smoothWheel: true });
  window.__lenis = lenis;
  function raf(t) { lenis.raf(t); scrubs.forEach(s => s.update()); requestAnimationFrame(raf); }
  requestAnimationFrame(raf);

  // Reveals + counters
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      e.target.classList.add("in");
      if (e.target.classList.contains("stat-num")) animateCount(e.target);
      io.unobserve(e.target);
    });
  }, { threshold: 0.2 });
  document.querySelectorAll(".reveal, .stat-num").forEach((el) => io.observe(el));

  // Nav state + scroll hint
  const nav = document.getElementById("nav");
  lenis.on("scroll", ({ scroll }) => {
    nav.classList.toggle("is-solid", scroll > 40);
    document.querySelectorAll(".scroll-hint").forEach(h => h.style.opacity = scroll > 60 ? "0" : "1");
  });

  // Anchor links via Lenis
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener("click", (ev) => {
      const id = a.getAttribute("href");
      const target = id === "#top" ? 0 : document.querySelector(id);
      if (target === null) return;
      ev.preventDefault();
      closeMenu();
      lenis.scrollTo(target, { offset: 0, duration: 1.4 });
    });
  });

  // Mobile menu
  const burger = document.getElementById("burger"), menu = document.getElementById("mobileMenu");
  function closeMenu() { burger.classList.remove("is-open"); menu.classList.remove("is-open"); lenis.start(); }
  burger.addEventListener("click", () => {
    const open = !menu.classList.contains("is-open");
    burger.classList.toggle("is-open", open); menu.classList.toggle("is-open", open);
    open ? lenis.stop() : lenis.start();
  });

  // Units tabs
  document.querySelectorAll(".tab").forEach(tab => {
    tab.addEventListener("click", () => {
      document.querySelectorAll(".tab").forEach(t => t.classList.toggle("is-active", t === tab));
      document.querySelectorAll(".tab-panel").forEach(p => p.classList.toggle("is-active", p.dataset.panel === tab.dataset.tab));
    });
  });

  // Tap.IA video
  const video = document.getElementById("tapiaVideo"), playBtn = document.getElementById("playBtn"), box = video.parentElement;
  playBtn.addEventListener("click", () => { video.muted = false; video.play(); });
  video.addEventListener("play", () => box.classList.add("is-playing"));
  video.addEventListener("pause", () => box.classList.remove("is-playing"));
  video.addEventListener("ended", () => { box.classList.remove("is-playing"); video.currentTime = 0; });
  video.addEventListener("click", () => { video.paused ? video.play() : video.pause(); });

  // Tracking form → opens the tracking portal in a new tab
  document.getElementById("trackForm").addEventListener("submit", (ev) => {
    ev.preventDefault();
    window.open("https://ssw.inf.br/2/rastreamento", "_blank", "noopener");
  });
});
