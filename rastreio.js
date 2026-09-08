/* TAP Express — rastreamento embutido (consulta o SSW pela nossa API e mostra com a cara da TAP) */
(function () {
  const form = document.getElementById("trackForm"); if (!form) return;
  const out = document.getElementById("trackResult"), tabs = form.querySelectorAll("[data-mode]"), panes = form.querySelectorAll("[data-pane]");
  let mode = "danfe";
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
        html += '<div class="ship-list">' + j.encomendas.map(e => `<article class="ship"><header><b>${esc(e.situacao || "Sem situação")}</b><span>${esc(e.ocorrencia)}</span></header><div class="ship-grid">${e.nf ? `<div><small>Nota / coleta</small>${esc(e.nf)}</div>` : ""}${e.ctrc ? `<div><small>CTRC</small>${esc(e.ctrc)}</div>` : ""}${e.destinatario ? `<div><small>Destinatário</small>${esc(e.destinatario)}</div>` : ""}${e.destino ? `<div><small>Destino</small>${esc(e.destino)}</div>` : ""}${e.unidade ? `<div><small>Unidade</small>${esc(e.unidade)}</div>` : ""}${e.previsao ? `<div><small>Previsão de entrega</small>${esc(e.previsao)}</div>` : ""}${e.entrega ? `<div><small>Entregue em</small>${esc(e.entrega)}</div>` : ""}${e.inclusao ? `<div><small>Coletado em</small>${esc(e.inclusao)}</div>` : ""}</div>${e.detalhe ? `<p>${esc(e.detalhe)}</p>` : ""}</article>`).join("") + "</div>";
      }
      if (!j.ok && !(j.eventos && j.eventos.length) && !(j.encomendas && j.encomendas.length)) html += `<div class="track-msg err">${esc(j.motivo || "Não encontramos essa encomenda.")} Confira os dados ou fale com a unidade pelo WhatsApp.</div>`;
      if (j.html) html += `<details class="track-raw"${j.eventos && j.eventos.length ? "" : " open"}><summary>Resposta completa do portal</summary><div class="track-raw-body">${j.html}</div></details>`;
      html += `<p class="track-foot">Fonte: portal SSW · consultado em ${esc(j.consultado_em)} · <a href="https://ssw.inf.br/2/rastreamento" target="_blank" rel="noopener">abrir no portal</a></p>`;
      show(html);
    } catch (err) { show('<div class="track-msg err">Falha de conexão. Tente novamente.</div>'); }
  });
  function show(h) { out.innerHTML = h; out.hidden = false; out.scrollIntoView({ behavior: "smooth", block: "nearest" }); }
  function esc(s) { return String(s ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }
})();
