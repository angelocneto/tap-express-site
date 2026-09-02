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
  if (avatarVideo) { avatarVideo.muted = true; avatarVideo.loop = true; avatarVideo.play().catch(() => {}); }

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
    input.addEventListener("input", () => {
      const q = norm(input.value); hint.textContent = "";
      if (q.length < 2) { list.classList.remove("is-open"); return; }
      const m = places.filter(p => norm(p.n).includes(q)).slice(0, 6);
      list.innerHTML = m.map(p => `<button type="button" data-n="${p.n}" data-via="${p.via}">${p.n} <small>${p.uf}</small></button>`).join("");
      list.classList.toggle("is-open", m.length > 0);
      list.querySelectorAll("button").forEach(b => b.addEventListener("click", () => { input.value = b.dataset.n; list.classList.remove("is-open"); hint.textContent = "✓ Cidade atendida pela rede TAP" + (b.dataset.via !== b.dataset.n ? " · via " + b.dataset.via : ""); }));
    });
    input.addEventListener("blur", () => setTimeout(() => list.classList.remove("is-open"), 150));
  });

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
  form.addEventListener("keydown", (e) => { if (e.key === "Enter" && e.target.tagName !== "TEXTAREA") { e.preventDefault(); btnNext.click(); } });
  show(0);
})();
