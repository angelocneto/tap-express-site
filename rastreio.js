/* TAP Express — rastreamento embutido (consulta o SSW pela nossa API e mostra com a cara da TAP) */
(function () {
  const form = document.getElementById("trackForm"); if (!form) return;
  const out = document.getElementById("trackResult"), tabs = form.querySelectorAll("[data-mode]"), panes = form.querySelectorAll("[data-pane]");
  let mode = "senha";
  tabs.forEach(b => b.addEventListener("click", () => { mode = b.dataset.mode; tabs.forEach(x => x.classList.toggle("is-on", x === b)); panes.forEach(p => p.hidden = p.dataset.pane !== mode); }));
  const danfe = form.querySelector("[name=danfe]"); if (danfe) danfe.addEventListener("input", () => { danfe.value = danfe.value.replace(/\D+/g, "").slice(0, 44); });
  const maskCnpj = (cnpj) => cnpj && cnpj.addEventListener("input", () => { let d = cnpj.value.replace(/\D+/g, "").slice(0, 14); cnpj.value = d.replace(/^(\d{2})(\d)/, "$1.$2").replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3").replace(/\.(\d{3})(\d)/, ".$1/$2").replace(/(\d{4})(\d)/, "$1-$2"); });
  const cnpj = form.querySelector("[name=cnpj]"); maskCnpj(cnpj); maskCnpj(form.querySelector("[name=cnpj2]"));
  const q = new URLSearchParams(location.search); if (q.get("danfe") && danfe) { danfe.value = q.get("danfe"); setTimeout(() => form.requestSubmit(), 300); }
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const body = mode === "danfe" ? { danfe: danfe.value } : mode === "senha" ? { cnpj: form.querySelector("[name=cnpj2]").value, senha: form.querySelector("[name=senha]").value } : { cnpj: cnpj.value, chave: form.querySelector("[name=chave]").value.trim() };
    if (mode === "danfe" && body.danfe.length !== 44) { show(`<div class="track-msg err">A chave da NF-e tem 44 números. Você digitou ${body.danfe.length}.</div>`); return; }
    if (mode === "chave" && (body.cnpj.replace(/\D/g, "").length !== 14 || !body.chave)) { show('<div class="track-msg err">Informe o CNPJ completo e a chave de rastreio.</div>'); return; }
    if (mode === "senha" && (body.cnpj.replace(/\D/g, "").length !== 14 || !body.senha)) { show('<div class="track-msg err">Informe o CNPJ do remetente e a senha.</div>'); return; }
    show('<div class="track-msg">Consultando o rastreamento…</div>');
    try {
      const r = await fetch("/api/rastreio.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });
      const j = await r.json();
      if (!r.ok) { show(`<div class="track-msg err">${esc(j.erro || "Não foi possível consultar agora.")}</div>`); return; }
      let html = j.quem ? `<p class="track-quem">${esc(j.quem)}</p>` : "";
      if (j.eventos && j.eventos.length) {
        html += '<ol class="timeline">' + j.eventos.map((ev, i) => `<li class="${i === 0 ? "is-last" : ""}"><time>${esc(ev.data)}${ev.hora ? " · " + esc(ev.hora) : ""}</time><b>${esc(ev.descricao)}</b>${ev.detalhe ? `<small>${esc(ev.detalhe)}</small>` : ""}</li>`).join("") + "</ol>";
      }
      if (j.encomendas && j.encomendas.length) {
        const st = (s) => /ENTREGUE/i.test(s) ? "ok" : /SAIDA PARA ENTREGA/i.test(s) ? "go" : /EMITIDO|COLET/i.test(s) ? "new" : "";
        html += `<p class="track-total">${j.total} encomenda${j.total > 1 ? "s" : ""} nos últimos 30 dias</p><div class="ship-list">` + j.encomendas.map((e, i) => `<article class="ship ${st(e.situacao)}" data-i="${i}"><header><div><b>${esc(e.situacao || "Sem situação")}</b><time>${esc(e.data)}${e.hora ? " · " + e.hora : ""}${e.unidade ? " · " + esc(e.unidade) : ""}</time></div>${e.link ? `<button type="button" class="ship-more" data-link="${esc(e.link)}">Ver histórico</button>` : ""}</header><div class="ship-grid">${e.destinatario ? `<div><small>Destinatário</small>${esc(e.destinatario)}</div>` : ""}${e.destino ? `<div><small>Destino</small>${esc(e.destino)}</div>` : ""}${e.nf ? `<div><small>Nota / coleta</small>${esc(e.nf)}</div>` : ""}${e.ctrc ? `<div><small>CTRC</small>${esc(e.ctrc)}</div>` : ""}${e.previsao ? `<div><small>Previsão de entrega</small>${esc(e.previsao)}</div>` : ""}${e.entrega ? `<div><small>Entregue em</small>${esc(e.entrega)}</div>` : ""}</div>${e.detalhe ? `<p>${esc(e.detalhe)}</p>` : ""}<div class="ship-hist" hidden></div></article>`).join("") + "</div>";
      }
      if (!j.ok && !(j.eventos && j.eventos.length) && !(j.encomendas && j.encomendas.length)) html += `<div class="track-msg err">${esc(j.motivo || "Não encontramos essa encomenda.")} Confira os dados ou fale com a unidade pelo WhatsApp.</div>`;
      if (j.html) html += `<details class="track-raw"${j.eventos && j.eventos.length ? "" : " open"}><summary>Resposta completa do portal</summary><div class="track-raw-body">${j.html}</div></details>`;
      html += `<p class="track-foot">Fonte: portal SSW · consultado em ${esc(j.consultado_em)} · <a href="https://ssw.inf.br/2/rastreamento" target="_blank" rel="noopener">abrir no portal</a></p>`;
      const ctx = mode === "danfe" ? "NF-e " + body.danfe : (mode === "senha" ? "Remetente " + body.cnpj : "CNPJ " + body.cnpj + " chave " + body.chave);
      show(html, (j.ok || (j.encomendas && j.encomendas.length)) ? ctx : null);
    } catch (err) { show('<div class="track-msg err">Falha de conexão. Tente novamente.</div>'); }
  });
  function show(h, ctx) {
    out.innerHTML = h + (ctx ? leadBlock(ctx) : ""); out.hidden = false; out.scrollIntoView({ behavior: "smooth", block: "nearest" });
    out.querySelectorAll(".ship-more").forEach(b => b.addEventListener("click", () => loadHist(b)));
    const lf = out.querySelector("#leadForm"); if (lf) lf.addEventListener("submit", sendLead);
  }
  async function loadHist(b) {
    const art = b.closest(".ship"), box = art.querySelector(".ship-hist"); if (!box.hidden) { box.hidden = true; b.textContent = "Ver histórico"; return; }
    const [id, md] = b.dataset.link.split("|"); b.disabled = true; b.textContent = "Carregando…";
    try {
      const r = await fetch("/api/rastreio.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ detalhe_id: id, detalhe_md: md }) });
      const j = await r.json(); b.disabled = false; b.textContent = "Ocultar histórico";
      let h = j.eventos && j.eventos.length ? '<ol class="timeline">' + j.eventos.map((ev, i) => `<li class="${i === 0 ? "is-last" : ""}"><time>${esc(ev.data)}${ev.hora ? " · " + ev.hora : ""}${ev.unidade ? " · " + esc(ev.unidade) : ""}</time><b>${esc(ev.descricao)}</b>${ev.detalhe ? `<small>${esc(ev.detalhe)}</small>` : ""}</li>`).join("") + "</ol>" : '<div class="track-msg err">Histórico indisponível no momento.</div>';
      if (j.links && j.links.comprovante) h += `<p class="track-foot"><a href="${esc(j.links.comprovante)}" target="_blank" rel="noopener">Ver comprovante de entrega</a></p>`;
      box.innerHTML = h; box.hidden = false;
    } catch (e) { b.disabled = false; b.textContent = "Ver histórico"; box.innerHTML = '<div class="track-msg err">Falha ao carregar o histórico.</div>'; box.hidden = false; }
  }
  function leadBlock(ctx) {
    return `<aside class="lead-box"><h3>Quer receber as atualizações desta entrega?</h3><p>Deixe seu contato e a TAP avisa por WhatsApp ou e‑mail a cada nova etapa.</p>
      <form id="leadForm" class="lead-form" autocomplete="on"><input type="hidden" name="origem" value="rastreamento"/><input type="hidden" name="contexto" value="${esc(ctx)}"/><input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true"/>
        <div class="row3"><div><label>Nome</label><input name="nome" maxlength="120" required placeholder="Seu nome"/></div><div><label>WhatsApp</label><input name="whatsapp" type="tel" inputmode="tel" maxlength="20" required placeholder="(18) 99999-9999"/></div><div><label>E‑mail</label><input name="email" type="email" maxlength="160" placeholder="voce@empresa.com.br"/></div></div>
        <label class="lead-check"><input type="checkbox" name="aceite" value="1" required/> <span>Autorizo a TAP Express a me enviar atualizações desta encomenda e comunicações por WhatsApp e e‑mail. Li a <a href="/privacidade/" target="_blank" rel="noopener">política de privacidade</a>.</span></label>
        <div class="track-actions"><button class="btn btn-solid" type="submit">Quero receber</button><span class="lead-status"></span></div></form></aside>`;
  }
  async function sendLead(e) {
    e.preventDefault(); const f = e.target, st = f.querySelector(".lead-status"), data = Object.fromEntries(new FormData(f).entries());
    if (!f.querySelector("[name=aceite]").checked) { st.textContent = "Marque o aceite para continuar."; return; }
    st.textContent = "Enviando…";
    try { const r = await fetch("/api/lead.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(data) }); const j = await r.json();
      if (r.ok && j.ok) { f.innerHTML = '<div class="track-msg">Pronto! Você vai receber as atualizações desta entrega. Obrigado.</div>'; } else st.textContent = j.erro || "Não foi possível enviar agora."; }
    catch (err) { st.textContent = "Falha de conexão. Tente novamente."; }
  }
  function esc(s) { return String(s ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }
})();
