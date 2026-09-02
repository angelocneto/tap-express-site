/* Carreiras: vagas ao vivo + candidatura */
(function () {
  const list = document.getElementById("vagasList"), sel = document.getElementById("cVaga"), form = document.getElementById("candForm"), msg = document.getElementById("candMsg"), done = document.getElementById("candDone");
  if (!list) return;
  const esc = (s) => String(s || "").replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  const lines = (t) => String(t || "").split(/\n+/).map(x => x.trim()).filter(Boolean);
  fetch("/api/vagas.php").then(r => r.json()).then(j => {
    const v = (j && j.vagas) || [];
    if (!v.length) { list.innerHTML = '<div class="tile" style="grid-column:1/-1"><h3>Nenhuma vaga aberta neste momento</h3><p>Deixe seu currículo no banco de talentos abaixo. Quando abrir uma vaga na sua região, a equipe entra em contato.</p></div>'; return; }
    list.innerHTML = v.map(x => `<article class="vaga tile" id="vaga-${x.id}"><small>${esc(x.area)}${x.tipo ? " · " + esc(x.tipo) : ""}</small><h3>${esc(x.titulo)}</h3><p class="loc">${esc(x.unidade || x.cidade)}${x.uf ? " · " + esc(x.uf) : ""}</p>${x.descricao ? `<p>${esc(x.descricao)}</p>` : ""}${lines(x.requisitos).length ? `<h4>Requisitos</h4><ul>${lines(x.requisitos).map(l => `<li>${esc(l)}</li>`).join("")}</ul>` : ""}${lines(x.beneficios).length ? `<h4>Benefícios</h4><ul>${lines(x.beneficios).map(l => `<li>${esc(l)}</li>`).join("")}</ul>` : ""}<a class="btn btn-solid" href="#candidatura" data-vaga="${x.id}">Candidatar-se</a></article>`).join("");
    v.forEach(x => { const o = document.createElement("option"); o.value = x.id; o.textContent = x.titulo + (x.unidade ? " · " + x.unidade : ""); sel.appendChild(o); });
    list.querySelectorAll("[data-vaga]").forEach(a => a.addEventListener("click", () => { sel.value = a.dataset.vaga; }));
  }).catch(() => { list.innerHTML = '<div class="tile" style="grid-column:1/-1"><h3>Não foi possível carregar as vagas agora</h3><p>Envie seu currículo pelo formulário abaixo.</p></div>'; });
  const tel = form.elements["telefone"];
  tel.addEventListener("input", () => { let d = tel.value.replace(/\D/g, ""); if (d.startsWith("55") && d.length > 11) d = d.slice(2); d = d.slice(0, 11); tel.value = d.length <= 2 ? (d ? "(" + d : "") : d.length <= 6 ? `(${d.slice(0, 2)}) ${d.slice(2)}` : d.length <= 10 ? `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}` : `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`; });
  form.addEventListener("submit", async (e) => {
    e.preventDefault(); msg.textContent = "";
    const btn = form.querySelector("button[type=submit]"); btn.disabled = true; btn.textContent = "Enviando…";
    try {
      const r = await fetch("/api/candidatura.php", { method: "POST", body: new FormData(form) });
      const j = await r.json(); if (!r.ok || !j.ok) throw new Error(j.erro || "Falha ao enviar");
      form.hidden = true; done.hidden = false; done.scrollIntoView({ behavior: "smooth", block: "center" });
    } catch (err) { msg.textContent = err.message; btn.disabled = false; btn.textContent = "Enviar candidatura"; }
  });
})();
