/* ===== Mapa interativo da rede TAP Express (MapLibre GL) ===== */
(function () {
  const REDE = window.TAP_REDE, ESTADOS = window.TAP_ESTADOS;
  const el = document.getElementById("map");
  if (!el || !REDE || typeof maplibregl === "undefined") return;

  const HUB = REDE.hub, UNITS = REDE.units;
  const GREEN = "#39b54a", LIME = "#9df0a8";
  const $ = (s) => document.querySelector(s);
  const detail = $("#mapDetail"), suggest = $("#citySuggest"), search = $("#citySearch");
  const note = $("#mapNote"), coordsHud = $("#mapCoords"), tourBtn = $("#tourBtn"), resetBtn = $("#resetBtn");

  /* ---------- helpers ---------- */
  const R = 6371;
  function km(a, b) {
    const dLat = (b[1] - a[1]) * Math.PI / 180, dLng = (b[0] - a[0]) * Math.PI / 180;
    const s = Math.sin(dLat / 2) ** 2 + Math.cos(a[1] * Math.PI / 180) * Math.cos(b[1] * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(s));
  }
  function arc(a, b, n = 40) {
    // gentle curved route (quadratic bezier bulging perpendicular to the segment)
    const mx = (a[0] + b[0]) / 2, my = (a[1] + b[1]) / 2;
    const dx = b[0] - a[0], dy = b[1] - a[1], len = Math.hypot(dx, dy) || 1;
    const cx = mx - dy / len * len * 0.18, cy = my + dx / len * len * 0.18;
    const pts = [];
    for (let i = 0; i <= n; i++) {
      const t = i / n, u = 1 - t;
      pts.push([u * u * a[0] + 2 * u * t * cx + t * t * b[0], u * u * a[1] + 2 * u * t * cy + t * t * b[1]]);
    }
    return pts;
  }
  const norm = (s) => s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

  /* ---------- data ---------- */
  const routes = UNITS.filter(u => !u.hub).map(u => ({ id: u.id, uf: u.uf, pts: arc(HUB, u.c), km: km(HUB, u.c) }));
  const totalKm = Math.round(routes.reduce((s, r) => s + r.km, 0));
  const places = [];
  UNITS.forEach(u => {
    places.push({ n: u.n, uf: u.uf, c: u.c, unit: u, isUnit: true });
    u.cities.forEach(c => { if (norm(c.n) !== norm(u.n)) places.push({ n: c.n, uf: u.uf, c: c.c, unit: u, isUnit: false }); });
  });
  const citiesFC = () => ({ type: "FeatureCollection", features: places.filter(p => !p.isUnit).map(p => ({ type: "Feature", properties: { n: p.n, uf: p.uf, unit: p.unit.id, unitName: p.unit.n }, geometry: { type: "Point", coordinates: p.c } })) });
  const routesFC = (t = 1) => ({ type: "FeatureCollection", features: routes.map(r => {
    const n = Math.max(2, Math.round(r.pts.length * t));
    return { type: "Feature", properties: { unit: r.id, uf: r.uf }, geometry: { type: "LineString", coordinates: r.pts.slice(0, n) } };
  }) });

  $("#mUnits").textContent = UNITS.length;
  $("#mCities").textContent = places.length;
  $("#mKm").textContent = totalKm.toLocaleString("pt-BR");

  /* ---------- map ---------- */
  const FALLBACK = { version: 8, sources: {}, layers: [{ id: "bg", type: "background", paint: { "background-color": "#07140e" } }] };
  const HOME = { center: [-50.6, -22.4], zoom: 6.1, pitch: 45, bearing: -12 };
  const map = new maplibregl.Map({
    container: el, style: "https://tiles.openfreemap.org/styles/dark", ...HOME,
    cooperativeGestures: true, attributionControl: { compact: true }, maxZoom: 13, minZoom: 4.5,
    locale: { "CooperativeGesturesHandler.WindowsHelpText": "Use Ctrl + rolagem para dar zoom no mapa", "CooperativeGesturesHandler.MacHelpText": "Use ⌘ + rolagem para dar zoom no mapa", "CooperativeGesturesHandler.MobileHelpText": "Use dois dedos para mover o mapa" }
  });
  map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), "bottom-right");
  let usingFallback = false;
  map.on("error", (e) => {
    const msg = (e && e.error && e.error.message) || "";
    if (!usingFallback && /style|tiles\.openfreemap|Failed to fetch|NetworkError/i.test(msg)) {
      usingFallback = true; note.classList.add("is-on");
      map.setStyle(FALLBACK);
    }
  });

  let layersReady = false;
  function addLayers() {
    if (map.getSource("estados")) return;
    if (ESTADOS) {
      map.addSource("estados", { type: "geojson", data: ESTADOS });
      map.addLayer({ id: "estados-fill", type: "fill", source: "estados", paint: { "fill-color": GREEN, "fill-opacity": 0.07 } });
      map.addLayer({ id: "estados-line", type: "line", source: "estados", paint: { "line-color": GREEN, "line-width": 1.2, "line-opacity": 0.55 } });
    }
    map.addSource("routes", { type: "geojson", data: routesFC(0) });
    map.addLayer({ id: "routes-glow", type: "line", source: "routes", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-color": GREEN, "line-width": 7, "line-opacity": 0.18, "line-blur": 4 } });
    map.addLayer({ id: "routes-line", type: "line", source: "routes", layout: { "line-cap": "round", "line-join": "round" }, paint: { "line-color": LIME, "line-width": 1.6, "line-opacity": 0.9 } });
    map.addLayer({ id: "routes-dash", type: "line", source: "routes", layout: { "line-cap": "round" }, paint: { "line-color": "#ffffff", "line-width": 2.2, "line-opacity": 0.9, "line-dasharray": [0, 4, 3] } });
    map.addSource("cities", { type: "geojson", data: citiesFC() });
    map.addLayer({ id: "cities-halo", type: "circle", source: "cities", paint: { "circle-radius": 9, "circle-color": GREEN, "circle-opacity": 0.14, "circle-blur": 0.6 } });
    map.addLayer({ id: "cities-dot", type: "circle", source: "cities", paint: { "circle-radius": 3.2, "circle-color": LIME, "circle-opacity": 0.95, "circle-stroke-color": "#05150b", "circle-stroke-width": 1 } });
    map.addLayer({ id: "cities-label", type: "symbol", source: "cities", minzoom: 7.5, layout: { "text-field": ["get", "n"], "text-size": 11, "text-offset": [0, 1.1], "text-anchor": "top", "text-font": ["Noto Sans Regular"] }, paint: { "text-color": "rgba(242,247,243,0.85)", "text-halo-color": "#05150b", "text-halo-width": 1.2 } });
    layersReady = true;
    animateRoutes(); animateDash();
  }
  map.on("load", addLayers);
  map.on("style.load", () => { if (usingFallback) addLayers(); });

  /* ---------- markers ---------- */
  const markers = {};
  UNITS.forEach(u => {
    const d = document.createElement("div");
    d.className = "mk" + (u.hub ? " hub" : "");
    d.innerHTML = `<span class="lbl">${u.hub ? "HUB · " : ""}${u.n}</span>`;
    d.addEventListener("click", (ev) => { ev.stopPropagation(); stopTour(); focusUnit(u, true); });
    markers[u.id] = new maplibregl.Marker({ element: d, anchor: "center" }).setLngLat(u.c).addTo(map);
  });

  /* ---------- animations ---------- */
  function animateRoutes() {
    const t0 = performance.now(), dur = 2600;
    function step(t) {
      const k = Math.min((t - t0) / dur, 1), e = 1 - Math.pow(1 - k, 3);
      map.getSource("routes").setData(routesFC(e));
      if (k < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  const DASH = [[0, 4, 3], [0.5, 4, 2.5], [1, 4, 2], [1.5, 4, 1.5], [2, 4, 1], [2.5, 4, 0.5], [3, 4, 0], [0, 0.5, 3, 3.5], [0, 1, 3, 3], [0, 1.5, 3, 2.5], [0, 2, 3, 2], [0, 2.5, 3, 1.5], [0, 3, 3, 1], [0, 3.5, 3, 0.5]];
  let dashStep = 0;
  function animateDash() {
    let last = 0;
    function frame(t) {
      if (t - last > 60) { last = t; dashStep = (dashStep + 1) % DASH.length; if (map.getLayer("routes-dash")) map.setPaintProperty("routes-dash", "line-dasharray", DASH[dashStep]); }
      requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  /* ---------- interaction ---------- */
  let activeUnit = null, popup = null;
  function cityChips(u) { return u.cities.length ? `<div class="cities">${u.cities.map(c => `<span data-lng="${c.c[0]}" data-lat="${c.c[1]}">${c.n}</span>`).join("")}</div>` : ""; }
  function renderDetail(u, city) {
    const d = u.hub ? "Centro de distribuição · matriz" : `${Math.round(km(HUB, u.c))} km do hub · Presidente Prudente`;
    detail.innerHTML = `
      <p class="kicker">${city ? "Cidade atendida" : (u.hub ? "Hub de distribuição" : "Unidade " + u.uf)}</p>
      <h4>${city ? city + " <small style='color:var(--green);font-size:12px'>· via " + u.n + "</small>" : u.n}</h4>
      <p>${u.addr}</p>
      <a class="phone" href="tel:${u.tel}">${u.phone}</a>
      ${cityChips(u)}
      <div class="dist">${d}</div>`;
    detail.querySelectorAll(".cities span").forEach(s => s.addEventListener("click", () => {
      const c = [parseFloat(s.dataset.lng), parseFloat(s.dataset.lat)];
      flyTo(c, 9.2); showPopup(c, `<b>${s.textContent}</b><br/>Atendida pela unidade ${u.n}`);
    }));
  }
  function showPopup(c, html) {
    if (popup) popup.remove();
    popup = new maplibregl.Popup({ offset: 14, closeButton: true }).setLngLat(c).setHTML(html).addTo(map);
  }
  function flyTo(c, zoom = 8.6) { map.flyTo({ center: c, zoom, pitch: 50, bearing: -12, speed: 0.9, curve: 1.4, essential: true }); }
  function focusUnit(u, fly) {
    activeUnit = u;
    Object.values(markers).forEach(m => m.getElement().classList.remove("is-active"));
    markers[u.id].getElement().classList.add("is-active");
    if (layersReady) {
      map.setPaintProperty("routes-line", "line-opacity", ["case", ["==", ["get", "unit"], u.id], 1, 0.25]);
      map.setPaintProperty("routes-dash", "line-opacity", ["case", ["==", ["get", "unit"], u.id], 1, 0.15]);
      map.setPaintProperty("cities-dot", "circle-opacity", ["case", ["==", ["get", "unit"], u.id], 1, 0.3]);
      map.setPaintProperty("cities-halo", "circle-opacity", ["case", ["==", ["get", "unit"], u.id], 0.35, 0.08]);
    }
    renderDetail(u);
    if (fly) flyTo(u.c, u.hub ? 8.2 : 8.8);
  }
  function clearFocus() {
    activeUnit = null;
    Object.values(markers).forEach(m => m.getElement().classList.remove("is-active"));
    if (layersReady) {
      map.setPaintProperty("routes-line", "line-opacity", 0.9);
      map.setPaintProperty("routes-dash", "line-opacity", 0.9);
      map.setPaintProperty("cities-dot", "circle-opacity", 0.95);
      map.setPaintProperty("cities-halo", "circle-opacity", 0.14);
    }
    renderDetail(UNITS[0]);
  }
  map.on("click", "cities-dot", (e) => {
    const p = e.features[0].properties, u = UNITS.find(x => x.id === p.unit);
    stopTour(); focusUnit(u, false); renderDetail(u, p.n);
    showPopup(e.features[0].geometry.coordinates, `<b>${p.n}</b><br/>Atendida pela unidade ${p.unitName}`);
  });
  map.on("mouseenter", "cities-dot", () => map.getCanvas().style.cursor = "pointer");
  map.on("mouseleave", "cities-dot", () => map.getCanvas().style.cursor = "");
  map.on("mousemove", (e) => { if (coordsHud) coordsHud.innerHTML = `LAT <b>${e.lngLat.lat.toFixed(3)}</b> · LNG <b>${e.lngLat.lng.toFixed(3)}</b>`; });
  map.on("zoom", () => el.parentElement.classList.toggle("show-labels", map.getZoom() > 7.2));

  /* ---------- UF filter ---------- */
  let uf = "all";
  document.querySelectorAll(".map-chips button").forEach(b => b.addEventListener("click", () => {
    stopTour();
    document.querySelectorAll(".map-chips button").forEach(x => x.classList.toggle("is-active", x === b));
    uf = b.dataset.uf;
    const f = uf === "all" ? null : ["==", ["get", "uf"], uf];
    if (layersReady) ["routes-glow", "routes-line", "routes-dash", "cities-halo", "cities-dot", "cities-label"].forEach(id => map.setFilter(id, f));
    UNITS.forEach(u => markers[u.id].getElement().classList.toggle("is-dim", uf !== "all" && u.uf !== uf && !u.hub));
    const sel = places.filter(p => uf === "all" || p.uf === uf);
    $("#mUnits").textContent = UNITS.filter(u => uf === "all" || u.uf === uf).length;
    $("#mCities").textContent = sel.length;
    $("#mKm").textContent = Math.round(routes.filter(r => uf === "all" || r.uf === uf).reduce((s, r) => s + r.km, 0)).toLocaleString("pt-BR");
    if (uf === "all") { clearFocus(); map.flyTo({ ...HOME, essential: true }); return; }
    const b = new maplibregl.LngLatBounds(); sel.forEach(p => b.extend(p.c)); b.extend(HUB);
    map.fitBounds(b, { padding: { top: 80, bottom: 80, left: 400, right: 80 }, pitch: 40, bearing: -12, maxZoom: 8.5 });
  }));

  /* ---------- search ---------- */
  let sugIdx = -1, sugItems = [];
  function renderSuggest(q) {
    const nq = norm(q);
    sugItems = nq.length < 2 ? [] : places.filter(p => norm(p.n).includes(nq)).sort((a, b) => norm(a.n).indexOf(nq) - norm(b.n).indexOf(nq) || a.n.localeCompare(b.n)).slice(0, 7);
    if (nq.length < 2) { suggest.classList.remove("is-open"); return; }
    suggest.innerHTML = sugItems.length ? sugItems.map((p, i) => `<button data-i="${i}">${p.n} <small>${p.isUnit ? "Unidade · " + p.uf : "via " + p.unit.n + " · " + p.uf}</small></button>`).join("") : `<div class="empty">Ainda não atendemos “${q}”. Fale com a TAP: (18) 3918-7777</div>`;
    suggest.classList.add("is-open"); sugIdx = -1;
    suggest.querySelectorAll("button").forEach(b => b.addEventListener("click", () => pick(sugItems[+b.dataset.i])));
  }
  function pick(p) {
    if (!p) return;
    stopTour(); search.value = p.n; suggest.classList.remove("is-open");
    if (p.isUnit) { focusUnit(p.unit, true); return; }
    focusUnit(p.unit, false); renderDetail(p.unit, p.n);
    flyTo(p.c, 9.4); showPopup(p.c, `<b>${p.n}</b><br/>Atendida pela unidade ${p.unit.n}`);
  }
  search.addEventListener("input", () => renderSuggest(search.value));
  search.addEventListener("keydown", (e) => {
    const btns = suggest.querySelectorAll("button");
    if (e.key === "ArrowDown") { sugIdx = Math.min(sugIdx + 1, btns.length - 1); e.preventDefault(); }
    else if (e.key === "ArrowUp") { sugIdx = Math.max(sugIdx - 1, 0); e.preventDefault(); }
    else if (e.key === "Enter") { e.preventDefault(); pick(sugItems[sugIdx >= 0 ? sugIdx : 0]); return; }
    else if (e.key === "Escape") { suggest.classList.remove("is-open"); return; }
    btns.forEach((b, i) => b.classList.toggle("is-active", i === sugIdx));
  });
  document.addEventListener("click", (e) => { if (!e.target.closest(".map-search")) suggest.classList.remove("is-open"); });

  /* ---------- tour ---------- */
  let tourTimer = null, tourI = 0;
  function stopTour() { if (tourTimer) { clearTimeout(tourTimer); tourTimer = null; tourBtn.textContent = "▶ Tour pelas unidades"; tourBtn.classList.remove("is-on"); } }
  function tourStep() {
    const u = UNITS[tourI % UNITS.length]; tourI++;
    focusUnit(u, true);
    tourTimer = setTimeout(tourStep, 3200);
  }
  tourBtn.addEventListener("click", () => {
    if (tourTimer) { stopTour(); return; }
    tourBtn.textContent = "■ Parar tour"; tourBtn.classList.add("is-on"); tourI = 0; tourStep();
  });
  resetBtn.addEventListener("click", () => { stopTour(); if (popup) popup.remove(); clearFocus(); search.value = ""; map.flyTo({ ...HOME, essential: true }); });
  ["dragstart", "wheel"].forEach(ev => map.on(ev, stopTour));

  renderDetail(UNITS[0]);
})();
