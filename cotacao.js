/* ===== Tap.IA no canto + cotação em 3 passos ===== */
(function () {
  const body = document.body, sheet = document.getElementById("quoteSheet"), backdrop = document.getElementById("quoteBackdrop");
  if (!sheet) return;
  const fab = document.getElementById("tapiaFab"), bubble = document.getElementById("tapiaBubble"), avatarVideo = document.getElementById("tapiaWave");
  document.addEventListener("click", (e) => { const ch = document.getElementById("tapiaChat"); if (ch && ch.classList.contains("is-on") && !e.target.closest(".tapia-fab")) ch.classList.remove("is-on"); });
  const steps = [...sheet.querySelectorAll(".quote-step")], prog = sheet.querySelector(".quote-progress i");
  const btnPrev = document.getElementById("qPrev"), btnNext = document.getElementById("qNext"), errEl = document.getElementById("qErr");
  const form = document.getElementById("quoteForm");
  let step = 0, sent = false;

  /* ---- Tap.IA bubble ---- */
  setTimeout(() => { if (!sessionStorage.getItem("tapiaBubbleClosed")) bubble.classList.add("is-on"); }, 2800);
  bubble.querySelector(".x").addEventListener("click", (e) => { e.stopPropagation(); bubble.classList.remove("is-on"); sessionStorage.setItem("tapiaBubbleClosed", "1"); });
  if (avatarVideo) {
    // Chrome/Firefox/Edge: WebM VP9 com alpha. Safari/iOS: WebP animado com alpha (HEVC alpha não é confiável no iOS).
    const ua = navigator.userAgent, isSafari = (/safari/i.test(ua) && !/chrome|chromium|crios|android|edg|fxios/i.test(ua)) || /iphone|ipad|ipod/i.test(ua);
    const usePng = () => { avatarVideo.pause(); avatarVideo.hidden = true; if (!avatarVideo.parentElement.querySelector("img.wave")) { const img = new Image(); img.className = "wave"; img.src = "/assets/tapia-wave-2.webp"; img.alt = "TAPIA"; img.onerror = () => { img.src = "/assets/tapia-wave-2-poster.png"; }; avatarVideo.parentElement.insertBefore(img, avatarVideo); } };
    if (isSafari) { usePng(); }
    else {
      const src = document.createElement("source"); src.src = "/assets/tapia-wave-2.webm"; src.type = "video/webm"; avatarVideo.appendChild(src);
      avatarVideo.muted = true; avatarVideo.loop = true;
      avatarVideo.addEventListener("error", usePng, { once: true });
      avatarVideo.addEventListener("loadeddata", () => {
        try { const c = document.createElement("canvas"); c.width = 8; c.height = 8; const ctx = c.getContext("2d", { willReadFrequently: true }); ctx.drawImage(avatarVideo, 0, 0, 40, 40, 0, 0, 8, 8); if (ctx.getImageData(0, 0, 1, 1).data[3] > 200) usePng(); } catch (e) {}
      }, { once: true });
      avatarVideo.play().catch(usePng);
    }
  }

  /* ---- open / close ---- */
  function open(source) {
    body.classList.add("quote-open"); bubble.classList.remove("is-on");
    if (window.__lenis) window.__lenis.stop();
    document.getElementById("qPagina").value = source || location.hash || "site";
    setTimeout(() => sheet.querySelector(".quote-step.is-on input")?.focus(), 500);
  }
  function close() { body.classList.remove("quote-open"); if (window.__lenis) window.__lenis.start(); }
  document.querySelectorAll("[data-open-quote]").forEach(el => el.addEventListener("click", (e) => { e.preventDefault(); open(el.dataset.openQuote); }));

  /* ---- chat TAPIA (launcher) ---- */
  const chat = document.getElementById("tapiaChat"), chatBody = document.getElementById("tapiaChatBody"), chatForm = document.getElementById("tapiaChatForm"), chatText = document.getElementById("tapiaChatText");
  const WA = (window.TAP_REDE && window.TAP_REDE.wa) || "5518991096441";
  const L = (p) => (window.TAP_LANG ? "/" + window.TAP_LANG : "") + p; // link na versão do idioma atual
  const LANG_TAG = () => window.TAP_LANG ? " [" + ({ en: "Contato em inglês", es: "Contato em espanhol", zh: "Contato em chinês" }[window.TAP_LANG] || window.TAP_LANG) + "]" : "";
  function say(html, me) { const d = document.createElement("div"); d.className = "tc-msg" + (me ? " me" : ""); d.innerHTML = html; chatBody.appendChild(d); chatBody.scrollTop = chatBody.scrollHeight; }
  function toggleChat(force) { const on = force !== undefined ? force : !chat.classList.contains("is-on"); chat.classList.toggle("is-on", on); bubble.classList.remove("is-on"); sessionStorage.setItem("tapiaBubbleClosed", "1"); if (on) setTimeout(() => chatText.focus(), 300); }
  document.querySelector(".tapia-avatar").addEventListener("click", () => toggleChat());
  bubble.addEventListener("click", (e) => { if (e.target.classList.contains("x")) return; toggleChat(true); });
  document.getElementById("tapiaChatClose").addEventListener("click", () => toggleChat(false));
  chatBody.addEventListener("click", (e) => {
    const b = e.target.closest("[data-tapia]"); if (!b) return;
    const a = b.dataset.tapia;
    if (a === "cotar") { say(t("Quero cotar um envio."), true); say(t("Perfeito! Vou abrir a cotação em 3 passos. Me diga de onde para onde vai a sua encomenda.")); setTimeout(() => { toggleChat(false); open("tapia-chat"); }, 700); }
    if (a === "rastrear") { say(t("Quero rastrear uma encomenda."), true); say(t("Você rastreia aqui no site com três dados: CNPJ do remetente, número da nota fiscal e senha de rastreio. Abrindo a página…")); setTimeout(() => { toggleChat(false); location.href = L("/rastreamento/"); }, 900); return; }
    if (a === "especialista") { say(t("Quero falar com um especialista."), true); say(t("Claro! Nossa equipe atende pelo WhatsApp central em horário comercial. Vou te levar para lá com o contexto desta conversa.")); setTimeout(() => window.open(`https://wa.me/${WA}?text=${encodeURIComponent("Olá! Vim pelo site da TAP Express, falando com a TAPIA, e quero falar com um especialista." + LANG_TAG())}`, "_blank", "noopener"), 900); }
    if (a === "coleta") { say(t("Quero programar coletas."), true); say(t("Coletas programadas são combinadas com a unidade da sua região. Vou abrir a cotação: escreva a frequência desejada nas observações que a equipe monta a rotina com você.")); setTimeout(() => { toggleChat(false); open("tapia-coleta"); const obs = form.elements["observacoes"]; if (obs && !obs.value) obs.value = "Coleta programada: "; }, 900); }
  });
  chatForm.addEventListener("submit", (e) => {
    e.preventDefault(); const txt = chatText.value.trim(); if (!txt) return; chatText.value = "";
    say(txt.replace(/</g, "&lt;"), true);
    const n = txt.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
    if (/cota|preco|preço|valor|frete|envio|enviar|mandar|quote|price|ship|send|cotiz|precio|env[ií]|报价|价格|运费|寄/.test(n)) { say(t("Posso preparar a cotação agora mesmo. Abrindo os 3 passos…")); setTimeout(() => { toggleChat(false); open("tapia-chat"); }, 800); return; }
    if (/rastre|onde esta|cadê|cade|encomenda|nota|track|where is|parcel|package|shipment|seguimiento|paquete|dónde|donde|追踪|查询|包裹|货物/.test(n)) { say(t("Para rastrear você precisa do CNPJ do remetente, do número da nota e da senha de rastreio. Abrindo a página…")); setTimeout(() => { location.href = L("/rastreamento/"); }, 800); return; }
    if (/unidade|cidade|atende|cobertura|regiao|região|city|cities|coverage|branch|deliver to|ciudad|sucursal|cobertura|城市|网点|覆盖/.test(n)) { say(t("Nossa rede tem 20 unidades e 104 localidades. Vou te mostrar no mapa: digite a cidade na busca para ver quem atende.")); setTimeout(() => { toggleChat(false); const mw = document.querySelector(".mapwrap"); if (!mw) { location.href = L("/#unidades"); return; } if (window.__lenis) window.__lenis.scrollTo(mw, { offset: -90 }); else mw.scrollIntoView({ behavior: "smooth" }); const s = document.getElementById("citySearch"); if (s) { s.value = ""; setTimeout(() => s.focus(), 1200); } }, 800); return; }
    if (/mudanca|mudança|mudar de casa|residencial|moving|household|furniture|mudanza|搬家/.test(n)) { say(t("A TAP não faz mudanças. Trabalhamos com encomendas, malotes e cargas fracionadas entre as cidades da rede. Se for uma carga desse tipo, posso cotar agora.")); return; }
    if (/trabalh|curricul|vaga|emprego|contrat|rh\b|job|career|resume|hiring|work for|empleo|trabajo|招聘|工作|简历/.test(n)) { say(t("Que bom! As vagas abertas ficam na página Trabalhe conosco, e o currículo vai direto para a nossa central de candidatos. Abrindo…")); setTimeout(() => { location.href = L("/carreiras/"); }, 900); return; }
    if (/pagador|tomador|reverter|quem paga|inverter|payer|who pays|pagador|quién paga|付款方|谁付/.test(n)) { say(t("Trocar quem paga o frete só é possível enquanto ele ainda não foi pago. O tomador atual precisa registrar a manifestação de desacordo no prazo da SEFAZ. A unidade que emitiu o CT-e orienta o procedimento. Quer que eu te leve ao WhatsApp dela?")); return; }
    if (/prazo|quanto tempo|demora|chega|dias?\b|horas?\b|how long|delivery time|transit|plazo|cuánto tarda|cuanto tarda|时效|多久|几天/.test(n)) { say(t("O prazo é de até 18 horas para as cidades atendidas. Algumas cidades menores têm dia fixo de entrega, como terça e quinta. Os dias aparecem ao lado de cada cidade em <0>cidades atendidas</0>.").replace("<0>", '<a href="/cidades/">').replace("</0>", "</a>")); return; }
    say(t("Anotei. Para não te deixar sem resposta, vou encaminhar sua mensagem ao atendimento humano no WhatsApp com todo o contexto."));
    setTimeout(() => window.open(`https://wa.me/${WA}?text=${encodeURIComponent("Olá! Vim pelo site da TAP Express (TAPIA)." + LANG_TAG() + " Minha mensagem: " + txt)}`, "_blank", "noopener"), 1200);
  });
  backdrop.addEventListener("click", close);
  sheet.querySelector(".close").addEventListener("click", close);
  document.addEventListener("keydown", (e) => { if (e.key === "Escape" && body.classList.contains("quote-open")) close(); });

  /* ---- city autocomplete (rede TAP) ---- */
  const norm = (s) => s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
  const places = [];
  if (window.TAP_REDE) window.TAP_REDE.units.forEach(u => { places.push({ n: u.n, uf: u.uf, via: u.n }); u.cities.forEach(c => { if (norm(c.n) !== norm(u.n)) places.push({ n: c.n, uf: u.uf, via: u.n, d: c.d }); }); });
  sheet.querySelectorAll(".ac").forEach(box => {
    const input = box.querySelector("input"), list = box.querySelector(".ac-list"), hint = box.querySelector(".hint");
    let items = [], idx = -1;
    function choose(p) { input.value = p.n; list.classList.remove("is-open"); input.classList.add("ok"); hint.textContent = t("✓ Cidade atendida pela rede TAP") + (p.via !== p.n ? " · via " + p.via : "") + " · " + p.uf + (p.d ? " · " + t("atendimento") + " " + p.d.split(",").map(x => t(x)).join("/") : ""); }
    function render() {
      const q = norm(input.value.trim()); hint.textContent = ""; input.classList.remove("ok"); idx = -1;
      if (q.length < 1) { list.classList.remove("is-open"); items = []; return; }
      items = places.filter(p => norm(p.n).includes(q)).sort((a, b) => norm(a.n).indexOf(q) - norm(b.n).indexOf(q) || a.n.localeCompare(b.n)).slice(0, 6);
      if (!items.length) { list.innerHTML = ""; list.classList.remove("is-open"); hint.textContent = t("Não achamos essa cidade na rede, mas pode continuar: nossa equipe verifica a rota."); return; }
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
    btnNext.textContent = i === steps.length - 1 ? t("Enviar cotação") : t("Continuar →");
    errEl.textContent = "";
    if (i === steps.length - 1) summary();
  }
  // medidas do volume: três campos compõem "C × L × A cm" e mostram volume e cubagem (300 kg/m³)
  const dimIn = ["dim_c", "dim_l", "dim_a"].map(n => form.querySelector(`[name="${n}"]`));
  if (dimIn.every(Boolean)) {
    const hidden = form.querySelector('[name="dimensoes"]'), hint = document.getElementById("dimsHint");
    const lab = { dimC: "C", dimL: "L", dimA: "A" };
    const upd = () => {
      const v = dimIn.map(i => parseFloat(String(i.value).replace(",", ".")) || 0);
      hidden.value = v.every(x => x > 0) ? `${v[0]} × ${v[1]} × ${v[2]} cm` : "";
      Object.entries(lab).forEach(([id, k], idx) => { const el = document.getElementById(id); if (el) el.textContent = v[idx] > 0 ? `${k} ${v[idx]}` : k; });
      if (hint) { if (v.every(x => x > 0)) { const m3 = v[0] * v[1] * v[2] / 1e6; hint.textContent = t("Volume {m3} m³ · cubagem {kg} kg (300 kg/m³)").replace("{m3}", m3.toFixed(3).replace(".", ",")).replace("{kg}", (m3 * 300).toFixed(1).replace(".", ",")); } else hint.textContent = t("Preencha as três medidas do maior volume."); }
    };
    dimIn.forEach(i => i.addEventListener("input", upd)); upd();
  }
  function validate(i) {
    const s = steps[i]; let ok = true;
    s.querySelectorAll("[required]").forEach(f => { const bad = f.type === "radio" ? !s.querySelector(`[name="${f.name}"]:checked`) : !f.value.trim(); f.style.borderColor = bad ? "#ff6b5a" : ""; if (bad) ok = false; });
    const email = s.querySelector('input[type="email"]');
    if (email && email.value && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value)) { email.style.borderColor = "#ff6b5a"; ok = false; }
    const tf = s.querySelector('input[name="telefone"]');
    if (tf && tf.value && tf.value.replace(/\D/g, "").length < 10) { tf.style.borderColor = "#ff6b5a"; ok = false; }
    if (!ok) errEl.textContent = t("Preencha os campos destacados.");
    return ok;
  }
  const val = (n) => { const f = form.elements[n]; if (!f) return ""; return f.type === "radio" || (f.length && f[0] && f[0].type === "radio") ? (form.querySelector(`[name="${n}"]:checked`)?.value || "") : f.value; };
  function summary() {
    const box = document.getElementById("qSummary");
    const rows = [[t("Origem"), val("origem")], [t("Destino"), val("destino")], [t("Tipo"), t(val("tipo"))], [t("Volumes"), val("volumes")], [t("Peso"), val("peso") ? val("peso") + " kg" : ""], [t("Dimensões"), val("dimensoes")], [t("Valor da NF"), val("valor") ? "R$ " + val("valor") : ""], [t("Frete pago por"), t(val("pagador"))], [t("Coleta"), val("coleta_data") ? val("coleta_data").split("-").reverse().join("/") : ""]];
    box.innerHTML = rows.filter(r => r[1]).map(r => `<div><span>${r[0]}</span>${r[1]}</div>`).join("");
  }
  btnPrev.addEventListener("click", () => show(step - 1));
  btnNext.addEventListener("click", async () => {
    if (!validate(step)) return;
    if (step < steps.length - 1) { show(step + 1); return; }
    if (sent) return;
    btnNext.disabled = true; btnNext.textContent = t("Enviando…");
    const data = {}; ["origem", "destino", "tipo", "volumes", "peso", "dimensoes", "valor", "pagador", "coleta_data", "cnpj", "nome", "empresa", "email", "telefone", "observacoes", "website"].forEach(k => data[k] = val(k));
    data.pagina = document.getElementById("qPagina").value;
    try {
      const r = await fetch("/api/cotacao.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(data) });
      const j = await r.json();
      if (!r.ok || !j.ok) throw new Error(t(j.erro || "Falha ao enviar"));
      sent = true;
      document.getElementById("qProto").textContent = j.protocolo;
      document.getElementById("qDoneName").textContent = data.nome.split(" ")[0];
      sheet.querySelector(".quote-steps").hidden = true; sheet.querySelector(".quote-foot").hidden = true; sheet.querySelector(".quote-progress").hidden = true;
      document.getElementById("quoteDone").hidden = false;
      try { localStorage.setItem("tapUltimaCotacao", j.protocolo); } catch (e) {}
    } catch (e) {
      errEl.textContent = e.message + " · " + t("ou ligue (18) 3918-7777");
      btnNext.disabled = false; btnNext.textContent = t("Tentar novamente");
    }
  });
  form.addEventListener("submit", (e) => { e.preventDefault(); btnNext.click(); });
  form.addEventListener("keydown", (e) => { if (e.key === "Enter" && e.target.tagName !== "TEXTAREA" && !e.target.closest(".ac")?.querySelector(".ac-list.is-open")) { e.preventDefault(); btnNext.click(); } });
  show(0);
})();
