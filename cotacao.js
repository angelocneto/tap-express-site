/* ===== Tap.IA no canto + cotação em 3 passos ===== */
(function () {
  const body = document.body, sheet = document.getElementById("quoteSheet"), backdrop = document.getElementById("quoteBackdrop");
  if (!sheet) return;
  const fab = document.getElementById("tapiaFab"), bubble = document.getElementById("tapiaBubble"), avatarVideo = document.getElementById("tapiaWave");
  const steps = [...sheet.querySelectorAll(".quote-step")], prog = sheet.querySelector(".quote-progress i");
  const btnPrev = document.getElementById("qPrev"), btnNext = document.getElementById("qNext"), errEl = document.getElementById("qErr");
  const form = document.getElementById("quoteForm");
  let step = 0, sent = false;

  /* ---- Tap.IA bubble ---- */
  setTimeout(() => { if (!sessionStorage.getItem("tapiaBubbleClosed")) bubble.classList.add("is-on"); }, 2800);
  bubble.querySelector(".x").addEventListener("click", (e) => { e.stopPropagation(); bubble.classList.remove("is-on"); sessionStorage.setItem("tapiaBubbleClosed", "1"); });
  if (avatarVideo) {
    // Safari toca HEVC com alpha (.mov); os demais, WebM VP9 com alpha
    const ua = navigator.userAgent, isSafari = /safari/i.test(ua) && !/chrome|chromium|crios|android|edg/i.test(ua);
    const src = document.createElement("source");
    src.src = isSafari ? "assets/tapia-wave.mov" : "assets/tapia-wave.webm";
    src.type = isSafari ? 'video/mp4; codecs="hvc1"' : "video/webm";
    avatarVideo.appendChild(src);
    avatarVideo.muted = true; avatarVideo.loop = true;
    avatarVideo.addEventListener("error", () => { avatarVideo.hidden = true; const img = new Image(); img.src = "assets/tapia-wave-poster.png"; img.alt = "Tap.IA"; avatarVideo.parentElement.insertBefore(img, avatarVideo); }, { once: true });
    // se o navegador tocar sem transparência (canto opaco), troca pela imagem recortada
    avatarVideo.addEventListener("loadeddata", () => {
      try {
        const c = document.createElement("canvas"); c.width = 8; c.height = 8;
        const ctx = c.getContext("2d", { willReadFrequently: true });
        ctx.drawImage(avatarVideo, 0, 0, 40, 40, 0, 0, 8, 8);
        const a = ctx.getImageData(0, 0, 1, 1).data[3];
        if (a > 200) { avatarVideo.pause(); avatarVideo.hidden = true; const img = new Image(); img.src = "assets/tapia-wave-poster.png"; img.alt = "Tap.IA"; avatarVideo.parentElement.insertBefore(img, avatarVideo); }
      } catch (e) {}
    }, { once: true });
    avatarVideo.play().catch(() => {});
  }

  /* ---- open / close ---- */
  function open(source) {
    body.classList.add("quote-open"); bubble.classList.remove("is-on");
    if (window.__lenis) window.__lenis.stop();
    document.getElementById("qPagina").value = source || location.hash || "site";
    setTimeout(() => sheet.querySelector(".quote-step.is-on input")?.focus(), 500);
  }
  function close() { body.classList.remove("quote-open"); if (window.__lenis) window.__lenis.start(); }
  document.querySelectorAll("[data-open-quote], .tapia-avatar, .tapia-bubble").forEach(el => el.addEventListener("click", (e) => { if (e.target.classList.contains("x")) return; e.preventDefault(); open(el.dataset.openQuote || el.id); }));
  backdrop.addEventListener("click", close);
  sheet.querySelector(".close").addEventListener("click", close);
  document.addEventListener("keydown", (e) => { if (e.key === "Escape" && body.classList.contains("quote-open")) close(); });

  /* ---- city autocomplete (rede TAP) ---- */
  const norm = (s) => s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
  const places = [];
  if (window.TAP_REDE) window.TAP_REDE.units.forEach(u => { places.push({ n: u.n, uf: u.uf, via: u.n }); u.cities.forEach(c => { if (norm(c.n) !== norm(u.n)) places.push({ n: c.n, uf: u.uf, via: u.n }); }); });
  sheet.querySelectorAll(".ac").forEach(box => {
    const input = box.querySelector("input"), list = box.querySelector(".ac-list"), hint = box.querySelector(".hint");
    let items = [], idx = -1;
    function choose(p) { input.value = p.n; list.classList.remove("is-open"); input.classList.add("ok"); hint.textContent = "✓ Cidade atendida pela rede TAP" + (p.via !== p.n ? " · via " + p.via : "") + " · " + p.uf; }
    function render() {
      const q = norm(input.value.trim()); hint.textContent = ""; input.classList.remove("ok"); idx = -1;
      if (q.length < 1) { list.classList.remove("is-open"); items = []; return; }
      items = places.filter(p => norm(p.n).includes(q)).sort((a, b) => norm(a.n).indexOf(q) - norm(b.n).indexOf(q) || a.n.localeCompare(b.n)).slice(0, 6);
      if (!items.length) { list.innerHTML = ""; list.classList.remove("is-open"); hint.textContent = "Não achamos essa cidade na rede, mas pode continuar: nossa equipe verifica a rota."; return; }
      list.innerHTML = items.map((p, i) => `<button type="button" data-i="${i}">${p.n} <small>${p.uf}${p.via !== p.n ? " · via " + p.via : ""}</small></button>`).join("");
      list.classList.add("is-open");
      list.querySelectorAll("button").forEach(b => b.addEventListener("mousedown", (e) => { e.preventDefault(); choose(items[+b.dataset.i]); }));
      if (items.length === 1 && norm(items[0].n) === q) choose(items[0]);
    }
    input.addEventListener("input", render);
    input.addEventListener("focus", () => { if (input.value && !input.classList.contains("ok")) render(); });
    input.addEventListener("keydown", (e) => {
      if (!list.classList.contains("is-open")) return;
      const btns = list.querySelectorAll("button");
      if (e.key === "ArrowDown") { idx = Math.min(idx + 1, btns.length - 1); e.preventDefault(); }
      else if (e.key === "ArrowUp") { idx = Math.max(idx - 1, 0); e.preventDefault(); }
      else if (e.key === "Enter" || e.key === "Tab") { if (items.length) { e.preventDefault(); choose(items[idx >= 0 ? idx : 0]); if (e.key === "Tab") { const next = input.closest(".qf").querySelectorAll("input")[1]; if (next && next !== input) next.focus(); } } return; }
      else if (e.key === "Escape") { list.classList.remove("is-open"); return; }
      btns.forEach((b, i) => b.classList.toggle("is-active", i === idx));
    });
    input.addEventListener("blur", () => setTimeout(() => list.classList.remove("is-open"), 150));
  });

  /* ---- máscara de telefone (aceita colar com +55) ---- */
  const tel = form.elements["telefone"];
  function fmtTel(v) {
    let d = v.replace(/\D/g, "");
    if (d.startsWith("55") && d.length > 11) d = d.slice(2);
    if (d.startsWith("0") && d.length > 11) d = d.slice(1);
    d = d.slice(0, 11);
    if (d.length <= 2) return d.length ? "(" + d : "";
    if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
    if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
    return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
  }
  if (tel) {
    tel.addEventListener("input", () => { tel.value = fmtTel(tel.value); });
    tel.addEventListener("paste", (e) => { e.preventDefault(); tel.value = fmtTel((e.clipboardData || window.clipboardData).getData("text")); });
    tel.addEventListener("blur", () => { const d = tel.value.replace(/\D/g, ""); tel.style.borderColor = d.length && d.length < 10 ? "#ff6b5a" : ""; });
  }

  /* ---- steps ---- */
  function show(i) {
    step = i; steps.forEach((s, k) => s.classList.toggle("is-on", k === i));
    prog.style.width = ((i + 1) / steps.length * 100) + "%";
    btnPrev.style.visibility = i === 0 ? "hidden" : "visible";
    btnNext.textContent = i === steps.length - 1 ? "Enviar cotação" : "Continuar →";
    errEl.textContent = "";
    if (i === steps.length - 1) summary();
  }
  function validate(i) {
    const s = steps[i]; let ok = true;
    s.querySelectorAll("[required]").forEach(f => { const bad = f.type === "radio" ? !s.querySelector(`[name="${f.name}"]:checked`) : !f.value.trim(); f.style.borderColor = bad ? "#ff6b5a" : ""; if (bad) ok = false; });
    const email = s.querySelector('input[type="email"]');
    if (email && email.value && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value)) { email.style.borderColor = "#ff6b5a"; ok = false; }
    const t = s.querySelector('input[name="telefone"]');
    if (t && t.value && t.value.replace(/\D/g, "").length < 10) { t.style.borderColor = "#ff6b5a"; ok = false; }
    if (!ok) errEl.textContent = "Preencha os campos destacados.";
    return ok;
  }
  const val = (n) => { const f = form.elements[n]; if (!f) return ""; return f.type === "radio" || (f.length && f[0] && f[0].type === "radio") ? (form.querySelector(`[name="${n}"]:checked`)?.value || "") : f.value; };
  function summary() {
    const box = document.getElementById("qSummary");
    const rows = [["Origem", val("origem")], ["Destino", val("destino")], ["Tipo", val("tipo")], ["Volumes", val("volumes")], ["Peso", val("peso") ? val("peso") + " kg" : ""], ["Valor", val("valor") ? "R$ " + val("valor") : ""]];
    box.innerHTML = rows.filter(r => r[1]).map(r => `<div><span>${r[0]}</span>${r[1]}</div>`).join("");
  }
  btnPrev.addEventListener("click", () => show(step - 1));
  btnNext.addEventListener("click", async () => {
    if (!validate(step)) return;
    if (step < steps.length - 1) { show(step + 1); return; }
    if (sent) return;
    btnNext.disabled = true; btnNext.textContent = "Enviando…";
    const data = {}; ["origem", "destino", "tipo", "volumes", "peso", "dimensoes", "valor", "nome", "empresa", "email", "telefone", "observacoes", "website"].forEach(k => data[k] = val(k));
    data.pagina = document.getElementById("qPagina").value;
    try {
      const r = await fetch("api/cotacao.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(data) });
      const j = await r.json();
      if (!r.ok || !j.ok) throw new Error(j.erro || "Falha ao enviar");
      sent = true;
      document.getElementById("qProto").textContent = j.protocolo;
      document.getElementById("qDoneName").textContent = data.nome.split(" ")[0];
      sheet.querySelector(".quote-steps").hidden = true; sheet.querySelector(".quote-foot").hidden = true; sheet.querySelector(".quote-progress").hidden = true;
      document.getElementById("quoteDone").hidden = false;
      try { localStorage.setItem("tapUltimaCotacao", j.protocolo); } catch (e) {}
    } catch (e) {
      errEl.textContent = e.message + " · ou ligue (18) 3918-7777";
      btnNext.disabled = false; btnNext.textContent = "Tentar novamente";
    }
  });
  form.addEventListener("submit", (e) => { e.preventDefault(); btnNext.click(); });
  form.addEventListener("keydown", (e) => { if (e.key === "Enter" && e.target.tagName !== "TEXTAREA" && !e.target.closest(".ac")?.querySelector(".ac-list.is-open")) { e.preventDefault(); btnNext.click(); } });
  show(0);
})();
