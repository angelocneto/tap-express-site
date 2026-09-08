/* TAP Express — rastreamento embutido (modo único, igual ao "Rastreamento pelo remetente" do SSW: CNPJ/CPF + notas + senha) */
(function () {
  const form = document.getElementById("trackForm"); if (!form) return;
  const out = document.getElementById("trackResult"), cnpj = form.querySelector("[name=cnpj]"), notas = form.querySelector("[name=notas]"), senha = form.querySelector("[name=senha]");
  const L = (p) => (window.TAP_LANG ? "/" + window.TAP_LANG : "") + p;
  // máscara inteligente: aceita colar com pontos/barra/traço; formata como CNPJ (14) ou CPF (11)
  const fmtDoc = (v) => { const d = v.replace(/\D+/g, "").slice(0, 14); if (d.length <= 11) return d.replace(/^(\d{3})(\d)/, "$1.$2").replace(/^(\d{3})\.(\d{3})(\d)/, "$1.$2.$3").replace(/\.(\d{3})(\d)/, ".$1-$2"); return d.replace(/^(\d{2})(\d)/, "$1.$2").replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3").replace(/\.(\d{3})(\d)/, ".$1/$2").replace(/(\d{4})(\d)/, "$1-$2"); };
  cnpj.addEventListener("input", () => { cnpj.value = fmtDoc(cnpj.value); });
  cnpj.addEventListener("paste", (e) => { e.preventDefault(); cnpj.value = fmtDoc((e.clipboardData || window.clipboardData).getData("text")); });
  // vindo da home (?nf=), preenche o número da nota
  const q = new URLSearchParams(location.search); if (q.get("nf") && notas) { notas.value = q.get("nf").replace(/[^\d\s,;]+/g, ""); cnpj.focus(); }
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const doc = cnpj.value.replace(/\D/g, ""), nrs = notas.value.split(/[\s,;]+/).map(x => x.replace(/\D/g, "")).filter(Boolean);
    [cnpj, notas, senha].forEach(f => f.classList.remove("is-bad"));
    let bad = false;
    if (doc.length !== 14 && doc.length !== 11) { cnpj.classList.add("is-bad"); bad = true; }
    if (!nrs.length) { notas.classList.add("is-bad"); bad = true; }
    if (!senha.value) { senha.classList.add("is-bad"); bad = true; }
    if (bad) { show('<div class="track-msg err">' + t("Preencha os três campos: CNPJ ou CPF do remetente, número da nota e senha de rastreio.") + '</div>'); return; }
    show('<div class="track-msg">' + t("Consultando o rastreamento…") + '</div>');
    const body = { cnpj: doc, notas: nrs.join("\n"), senha: senha.value };
    try {
      const r = await fetch("/api/rastreio.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });
      const j = await r.json();
      if (!r.ok) { show(`<div class="track-msg err">${esc(t(j.erro || "Não foi possível consultar agora."))}</div>`); return; }
      let html = j.quem ? `<p class="track-quem">${t("Remetente")}: ${esc(j.quem)}</p>` : "";
      if (j.encomendas && j.encomendas.length) {
        const st = (s) => /ENTREGUE/i.test(s) ? "ok" : /SAIDA PARA ENTREGA/i.test(s) ? "go" : /EMITIDO|COLET/i.test(s) ? "new" : "";
        html += `<p class="track-total">${t(j.total > 1 ? "{n} encomendas encontradas" : "{n} encomenda encontrada").replace("{n}", j.total)}</p><div class="ship-list">` + j.encomendas.map((e, i) => `<article class="ship ${st(e.situacao)}" data-i="${i}"><header><div><b>${esc(e.situacao || t("Sem situação"))}</b><time>${esc(e.data)}${e.hora ? " · " + e.hora : ""}${e.unidade ? " · " + esc(e.unidade) : ""}</time></div>${e.link ? `<button type="button" class="ship-more" data-link="${esc(e.link)}">${t("Ver histórico")}</button>` : ""}</header><div class="ship-grid">${e.nf ? `<div><small>${t("Nota / coleta")}</small>${esc(e.nf)}</div>` : ""}${e.pedido ? `<div><small>${t("Pedido")}</small>${esc(e.pedido)}</div>` : ""}${e.destinatario ? `<div><small>${t("Destinatário")}</small>${esc(e.destinatario)}</div>` : ""}${e.destino ? `<div><small>${t("Destino")}</small>${esc(e.destino)}</div>` : ""}${e.ctrc ? `<div><small>CTRC</small>${esc(e.ctrc)}</div>` : ""}${e.previsao ? `<div><small>${t("Previsão de entrega")}</small>${esc(e.previsao)}</div>` : ""}${e.entrega ? `<div><small>${t("Entregue em")}</small>${esc(e.entrega)}</div>` : ""}</div>${e.detalhe ? `<p>${esc(e.detalhe)}</p>` : ""}<div class="ship-hist" hidden></div></article>`).join("") + "</div>";
      }
      if (j.nao_encontradas && j.nao_encontradas.length) html += `<div class="track-msg${j.encomendas && j.encomendas.length ? "" : " err"}">${esc(t(j.nao_encontradas.length > 1 ? "Notas sem informação no portal: {n}" : "Nota sem informação no portal: {n}").replace("{n}", j.nao_encontradas.join(", ")))}${j.encomendas && j.encomendas.length ? "" : " " + esc(t("Confira o CNPJ, o número da nota e a senha de rastreio."))}</div>`;
      if (!j.ok && !(j.encomendas && j.encomendas.length) && !(j.nao_encontradas && j.nao_encontradas.length)) html += `<div class="track-msg err">${esc(t(j.motivo || "Não encontramos essa encomenda."))}</div>`;
      html += `<p class="track-foot">${t("Fonte: portal SSW · consultado em")} ${esc(j.consultado_em)} · <a href="https://ssw.inf.br/2/rastreamento" target="_blank" rel="noopener">${t("abrir no portal")}</a></p>`;
      show(html, j.ok ? "Remetente " + body.cnpj + " NF " + nrs.join(",") : null);
    } catch (err) { show('<div class="track-msg err">' + t("Falha de conexão. Tente novamente.") + '</div>'); }
  });
  function show(h, ctx) {
    out.innerHTML = h + (ctx ? leadBlock(ctx) : ""); out.hidden = false; out.scrollIntoView({ behavior: "smooth", block: "nearest" });
    out.querySelectorAll(".ship-more").forEach(b => b.addEventListener("click", () => loadHist(b)));
    const lf = out.querySelector("#leadForm"); if (lf) lf.addEventListener("submit", sendLead);
  }
  async function loadHist(b) {
    const art = b.closest(".ship"), box = art.querySelector(".ship-hist"); if (!box.hidden) { box.hidden = true; b.textContent = t("Ver histórico"); return; }
    const [id, md] = b.dataset.link.split("|"); b.disabled = true; b.textContent = t("Carregando…");
    try {
      const r = await fetch("/api/rastreio.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ detalhe_id: id, detalhe_md: md }) });
      const j = await r.json(); b.disabled = false; b.textContent = t("Ocultar histórico");
      let h = j.eventos && j.eventos.length ? '<ol class="timeline">' + j.eventos.map((ev, i) => `<li class="${i === 0 ? "is-last" : ""}"><time>${esc(ev.data)}${ev.hora ? " · " + ev.hora : ""}${ev.unidade ? " · " + esc(ev.unidade) : ""}</time><b>${esc(ev.descricao)}</b>${ev.detalhe ? `<small>${esc(ev.detalhe)}</small>` : ""}</li>`).join("") + "</ol>" : '<div class="track-msg err">' + t("Histórico indisponível no momento.") + '</div>';
      if (j.links && j.links.comprovante) h += `<p class="track-foot"><a href="${esc(j.links.comprovante)}" target="_blank" rel="noopener">${t("Ver comprovante de entrega")}</a></p>`;
      box.innerHTML = h; box.hidden = false;
    } catch (e) { b.disabled = false; b.textContent = t("Ver histórico"); box.innerHTML = '<div class="track-msg err">' + t("Falha ao carregar o histórico.") + '</div>'; box.hidden = false; }
  }
  function leadBlock(ctx) {
    return `<aside class="lead-box"><h3>${t("Quer receber as atualizações desta entrega?")}</h3><p>${t("Deixe seu contato e a TAP avisa por WhatsApp ou e‑mail a cada nova etapa.")}</p>
      <form id="leadForm" class="lead-form" autocomplete="on"><input type="hidden" name="origem" value="rastreamento"/><input type="hidden" name="contexto" value="${esc(ctx)}"/><input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true"/>
        <div class="row3"><div><label>${t("Nome")}</label><input name="nome" maxlength="120" required placeholder="${t("Seu nome")}"/></div><div><label>WhatsApp</label><input name="whatsapp" type="tel" inputmode="tel" maxlength="20" required placeholder="(18) 99999-9999"/></div><div><label>E‑mail</label><input name="email" type="email" maxlength="160" placeholder="voce@empresa.com.br"/></div></div>
        <label class="lead-check"><input type="checkbox" name="aceite" value="1" required/> <span>${t("Autorizo a TAP Express a me enviar atualizações desta encomenda e comunicações por WhatsApp e e‑mail. Li a <0>política de privacidade</0>.").replace("<0>", '<a href="' + L("/privacidade/") + '" target="_blank" rel="noopener">').replace("</0>", "</a>")}</span></label>
        <div class="track-actions"><button class="btn btn-solid" type="submit">${t("Quero receber")}</button><span class="lead-status"></span></div></form></aside>`;
  }
  async function sendLead(e) {
    e.preventDefault(); const f = e.target, st = f.querySelector(".lead-status"), data = Object.fromEntries(new FormData(f).entries());
    if (!f.querySelector("[name=aceite]").checked) { st.textContent = t("Marque o aceite para continuar."); return; }
    st.textContent = t("Enviando…");
    try { const r = await fetch("/api/lead.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(data) }); const j = await r.json();
      if (r.ok && j.ok) { f.innerHTML = '<div class="track-msg">' + t("Pronto! Você vai receber as atualizações desta entrega. Obrigado.") + '</div>'; } else st.textContent = t(j.erro || "Não foi possível enviar agora."); }
    catch (err) { st.textContent = t("Falha de conexão. Tente novamente."); }
  }
  function esc(s) { return String(s ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }
})();
