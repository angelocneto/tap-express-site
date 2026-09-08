#!/usr/bin/env python3
"""Site em PT | EN | ES | ZH. Gera /en/, /es/, /zh/ a partir das páginas em português já construídas.
Uso:  python3 build_i18n.py extract   -> i18n/strings.json (chaves PT + onde aparecem)
      python3 build_i18n.py build     -> escreve /en /es /zh, hreflang nas páginas PT, sitemap com alternates
Rode depois de build_pages.py e build_seo.py."""
import re, json, sys, os, html

BASE = "https://www.tapexpress.com.br"
LANGS = {
    "en": {"html": "en", "og": "en_US", "hreflang": "en", "name": "English", "short": "EN"},
    "es": {"html": "es", "og": "es_ES", "hreflang": "es", "name": "Español", "short": "ES"},
    "zh": {"html": "zh-Hans", "og": "zh_CN", "hreflang": "zh-Hans", "name": "中文", "short": "中文"},
}
PAGES = ["", "sobre/", "servicos/", "servicos/encomendas-expressas/", "servicos/cargas-fracionadas/", "servicos/malotes-e-documentos/",
         "rastreamento/", "cotacao/", "tapia/", "contato/", "unidades/", "carreiras/", "privacidade/"]
JS_FILES = ["cotacao.js", "rastreio.js", "carreiras.js", "map.js", "app.js"]
INLINE = {"a", "abbr", "b", "bdi", "bdo", "br", "cite", "code", "data", "dfn", "em", "i", "kbd", "mark", "q", "s", "samp", "small",
          "span", "strong", "sub", "sup", "time", "u", "var", "wbr", "svg", "img"}
VOID = {"br", "wbr", "img", "svg", "input", "hr", "meta", "link", "source"}
ATTRS = {"alt", "title", "placeholder", "aria-label", "data-hint"}
META_KEYS = {"description", "keywords", "og:title", "og:description", "twitter:title", "twitter:description", "og:image:alt"}
TOKEN = re.compile(r"(<!--.*?-->|<script\b.*?</script>|<style\b.*?</style>|<svg\b.*?</svg>|<[^>]+>)", re.S | re.I)
LETTERS = re.compile(r"[A-Za-zÀ-ÿ一-鿿]")

def tag_name(tok):
    m = re.match(r"<\s*/?\s*([a-zA-Z][a-zA-Z0-9-]*)", tok); return m.group(1).lower() if m else ""
def is_close(tok): return tok.startswith("</")
def is_selfclosing(tok): return tok.endswith("/>") or tag_name(tok) in VOID or tok.lower().startswith("<svg")

def tokenize(src): return [t for t in TOKEN.split(src) if t != ""]
def is_tag(tok): return tok.startswith("<")

# ---------- unidades de tradução ----------
def units_from_tokens(toks, depth_ok=True):
    """Divide em segmentos por tags de bloco. Devolve lista de (i_start, i_end, key, tagmap) onde key usa <0>..</0> placeholders."""
    out = []
    seg = []; seg_start = 0
    def flush(seg, start):
        if seg: out.extend(units_from_segment(seg, start))
    for i, tok in enumerate(toks):
        if is_tag(tok) and (tok.startswith("<!--") or tag_name(tok) in ("script", "style") or tag_name(tok) not in INLINE):
            flush(seg, seg_start); seg = []; seg_start = i + 1
        else:
            if not seg: seg_start = i
            seg.append(tok)
    flush(seg, seg_start)
    return out

def units_from_segment(seg, start):
    # apara espaços das pontas
    a, b = 0, len(seg)
    while a < b and not is_tag(seg[a]) and not seg[a].strip(): a += 1
    while b > a and not is_tag(seg[b-1]) and not seg[b-1].strip(): b -= 1
    seg2 = seg[a:b]
    if not seg2: return []
    if not any((not is_tag(t)) and LETTERS.search(t) for t in seg2): return []
    # texto direto no nível 0?
    depth = 0; direct = False
    for t in seg2:
        if is_tag(t):
            if is_selfclosing(t): continue
            depth += -1 if is_close(t) else 1
        elif depth == 0 and LETTERS.search(t): direct = True
    if direct or all(not is_tag(t) for t in seg2):
        key, tagmap = make_key(seg2)
        return [(start + a, start + b, key, tagmap)]
    # só elementos inline no topo: recorre para dentro de cada um
    res = []; i = 0
    while i < len(seg2):
        t = seg2[i]
        if is_tag(t) and not is_close(t) and not is_selfclosing(t):
            depth = 1; j = i + 1
            while j < len(seg2) and depth > 0:
                if is_tag(seg2[j]) and not is_selfclosing(seg2[j]): depth += -1 if is_close(seg2[j]) else 1
                j += 1
            inner = seg2[i+1:j-1]
            res.extend(units_from_segment(inner, start + a + i + 1))
            i = j
        else: i += 1
    return res

def make_key(seg):
    """Substitui tags por placeholders numerados. Devolve (key, [tags na ordem])."""
    parts = []; tags = []; stack = []
    for t in seg:
        if is_tag(t):
            if is_selfclosing(t): parts.append(f"<{len(tags)}/>"); tags.append(t)
            elif is_close(t): n = stack.pop() if stack else len(tags); parts.append(f"</{n}>")
            else: parts.append(f"<{len(tags)}>"); stack.append(len(tags)); tags.append(t)
        else: parts.append(t)
    key = re.sub(r"\s+", " ", "".join(parts)).strip()
    return key, tags

def render_key(text, tags):
    """Volta placeholders para as tags originais."""
    def rep(m):
        n = int(m.group(2)); 
        if n >= len(tags): return ""
        if m.group(1) == "/": return closing_of(tags[n])
        return tags[n]
    return re.sub(r"<(/?)(\d+)/?>", rep, text)
def closing_of(tok): return f"</{tag_name(tok)}>"

# ---------- atributos / meta / json-ld ----------
ATTR_RE = re.compile(r'\s([a-zA-Z:-]+)="([^"]*)"')
def attr_units(tok):
    """Devolve lista de (attr, value) traduzíveis num token de tag."""
    if not is_tag(tok) or tok.startswith("</"): return []
    name = tag_name(tok); attrs = dict(ATTR_RE.findall(tok)); res = []
    if name == "meta":
        k = attrs.get("name") or attrs.get("property")
        if k in META_KEYS and attrs.get("content"): res.append(("content", attrs["content"]))
    elif name == "input" and attrs.get("type") in ("submit", "button") and attrs.get("value"): res.append(("value", attrs["value"]))
    for a in ATTRS:
        v = attrs.get(a)
        if v and LETTERS.search(v) and a != "content": res.append((a, v))
    return res

def ld_strings(obj, acc):
    if isinstance(obj, str):
        if LETTERS.search(obj) and not obj.startswith("http") and len(obj) > 2: acc.add(obj)
    elif isinstance(obj, list): [ld_strings(x, acc) for x in obj]
    elif isinstance(obj, dict): [ld_strings(v, acc) for k, v in obj.items() if k not in ("@type", "@id", "@context", "url", "sameAs", "telephone", "email", "logo", "image", "contentUrl", "priceCurrency", "addressCountry", "postalCode", "openingHours", "dayOfWeek", "latitude", "longitude", "inLanguage", "areaServed")]
def ld_translate(obj, tr):
    if isinstance(obj, str): return tr.get(obj, obj)
    if isinstance(obj, list): return [ld_translate(x, tr) for x in obj]
    if isinstance(obj, dict): return {k: (ld_translate(v, tr) if k not in ("@type", "@id", "@context", "url", "sameAs") else v) for k, v in obj.items()}
    return obj

def js_keys():
    keys = set()
    for f in JS_FILES:
        s = open(f, encoding="utf-8").read()
        for m in re.finditer(r'\bt\(\s*("((?:[^"\\]|\\.)*)"|\'((?:[^\'\\]|\\.)*)\')', s):
            k = m.group(2) if m.group(2) is not None else m.group(3)
            keys.add(k.replace('\\"', '"').replace("\\'", "'"))
    return keys
EXTRA_JS = {"{n} encomendas encontradas", "{n} encomenda encontrada", "Notas sem informação no portal: {n}", "Nota sem informação no portal: {n}", "Nenhuma encomenda encontrada para esses dados. Confira o CNPJ, o número da nota e a senha de rastreio.", "seg", "ter", "qua", "qui", "sex", "Encomenda expressa", "Malote / documentos", "Carga fracionada", "Remetente", "Destinatário"}
def php_keys():
    keys = set()
    for f in os.listdir("api"):
        if f.endswith(".php"):
            s = open("api/" + f, encoding="utf-8").read()
            for m in re.finditer(r"'(?:erro|motivo)'\s*=>\s*'([^']+)'", s): keys.add(m.group(1))
            for m in re.finditer(r"\$motivo\s*=\s*'([^']+)'", s): keys.add(m.group(1))
    return keys

# ---------- extract ----------
def page_file(p): return (p + "index.html") if p else "index.html"
def collect(src):
    toks = tokenize(src); found = []  # (key, kind)
    for (i0, i1, key, tags) in units_from_tokens(toks): found.append(key)
    for t in toks:
        if is_tag(t) and not t.startswith("<!--") and tag_name(t) not in ("script", "style"):
            for a, v in attr_units(t): found.append(html.unescape(v))
        elif t.lower().startswith("<script") and "ld+json" in t[:80]:
            try:
                body = re.sub(r"^<script[^>]*>|</script>$", "", t, flags=re.I | re.S); acc = set(); ld_strings(json.loads(body), acc); found.extend(sorted(acc))
            except Exception as e: print("ld+json parse fail", e)
    return found

def extract():
    strings = {}
    for p in PAGES:
        f = page_file(p)
        if not os.path.exists(f): print("faltando", f); continue
        for k in collect(open(f, encoding="utf-8").read()):
            d = strings.setdefault(k, {"pages": [], "n": 0}); d["n"] += 1
            if p not in d["pages"]: d["pages"].append(p or "/")
    for k in sorted(js_keys()): strings.setdefault(k, {"pages": [], "n": 0})["pages"].append("js")
    for k in sorted(php_keys()): strings.setdefault(k, {"pages": [], "n": 0})["pages"].append("api")
    json.dump(strings, open("i18n/strings.json", "w", encoding="utf-8"), ensure_ascii=False, indent=0)
    words = sum(len(re.sub(r"<[^>]+>", " ", k).split()) for k in strings)
    print(f"{len(strings)} strings, ~{words} palavras")
    for lang in LANGS:
        f = f"i18n/{lang}.json"; tr = json.load(open(f, encoding="utf-8")) if os.path.exists(f) else {}
        missing = [k for k in strings if k not in tr]
        open(f"i18n/missing.{lang}.json", "w", encoding="utf-8").write(json.dumps(missing, ensure_ascii=False, indent=0))
        print(f"  {lang}: {len(tr)} traduzidas, {len(missing)} faltando")

# ---------- build ----------
def prefix_links(src, lang):
    tr_paths = set(PAGES)
    def fix_href(m):
        attr, val = m.group(1), m.group(2)
        if val.startswith(("http", "mailto:", "tel:", "data:", "javascript:", "#", "//")): return m.group(0)
        if val.startswith("/"):
            path = val[1:]
            core = path.split("#")[0].split("?")[0]
            if core in tr_paths and attr == "href": return f'{attr}="/{lang}/{path}"'
            return m.group(0)
        # relativo (index.html usa assets/, styles.css, frames/...)
        return f'{attr}="/{val}"'
    src = re.sub(r'\b(href|src|poster)="([^"]*)"', fix_href, src)
    src = src.replace("`frames/", "`/frames/")
    return src

def translate_html(src, tr, lang, page):
    L = LANGS[lang]; missing = set()
    def T(k):
        v = tr.get(k)
        if v is None: missing.add(k); return k
        return v
    toks = tokenize(src)
    units = units_from_tokens(toks)
    # substitui unidades (de trás para frente)
    for (i0, i1, key, tags) in sorted(units, key=lambda u: -u[0]):
        new = render_key(T(key), tags)
        lead = toks[i0] if (i0 < len(toks) and not is_tag(toks[i0]) and not toks[i0].strip()) else ""
        toks[i0:i1] = [new]
    for i, t in enumerate(toks):
        if is_tag(t) and not t.startswith("<!--") and tag_name(t) not in ("script", "style"):
            for a, v in attr_units(t):
                nv = html.escape(T(html.unescape(v)), quote=True)
                t = t.replace(f'{a}="{v}"', f'{a}="{nv}"')
            toks[i] = t
        elif t.lower().startswith("<script") and "ld+json" in t[:80]:
            head = re.match(r"^<script[^>]*>", t, re.I).group(0); body = t[len(head):-len("</script>")]
            try:
                obj = ld_translate(json.loads(body), {k: v for k, v in tr.items()})
                toks[i] = head + json.dumps(obj, ensure_ascii=False) + "</script>"
            except Exception: pass
    out = "".join(toks)
    # lang / locale / canonical / hreflang
    out = re.sub(r'<html lang="[^"]*"', f'<html lang="{L["html"]}"', out, count=1)
    out = out.replace('content="pt_BR"', f'content="{L["og"]}"')
    url_pt = f"{BASE}/{page}"; url_l = f"{BASE}/{lang}/{page}"
    out = out.replace(f'<link rel="canonical" href="{url_pt}" />', f'<link rel="canonical" href="{url_l}" />')
    out = re.sub(r'<meta property="og:url" content="[^"]*" />', f'<meta property="og:url" content="{url_l}" />', out)
    out = re.sub(r'\s*<link rel="alternate" hreflang="[^"]*" href="[^"]*" />', "", out)
    out = out.replace('<meta name="robots"', hreflang_block(page) + '<meta name="robots"', 1)
    out = prefix_links(out, lang)
    # seletor de idioma: refeito com os links certos e o idioma ativo
    out = re.sub(r'<div class="lang( lang-mobile)?" aria-label="[^"]*">.*?</div>', lambda m: switcher(page, lang, T("Idioma"), m.group(1) or ""), out, flags=re.S)
    # dicionário para JS + idioma
    jsd = {k: tr[k] for k in JS_KEYS | PHP_KEYS | EXTRA_JS if k in tr}
    inject = f'<script>window.TAP_LANG="{lang}";window.TAP_T={json.dumps(jsd, ensure_ascii=False)};</script>'
    if lang == "zh": inject = '<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet" />' + inject
    out = out.replace("</head>", inject + "\n</head>", 1)
    return out, missing

def hreflang_block(page):
    links = [f'<link rel="alternate" hreflang="pt-BR" href="{BASE}/{page}" />', f'<link rel="alternate" hreflang="x-default" href="{BASE}/{page}" />']
    links += [f'<link rel="alternate" hreflang="{L["hreflang"]}" href="{BASE}/{l}/{page}" />' for l, L in LANGS.items()]
    return "".join(links)

def switcher(page, lang, label="Idioma", extra=""):
    cur = "PT" if lang == "pt" else LANGS[lang]["short"]
    items = [f'<span class="lang-cur" tabindex="0">{cur}</span>', f'<a class="lang-item{" is-on" if lang == "pt" else ""}" href="/{page}" hreflang="pt-BR" lang="pt-BR">PT</a>']
    for l, L in LANGS.items():
        items.append(f'<a class="lang-item{" is-on" if lang == l else ""}" href="/{l}/{page}" hreflang="{L["hreflang"]}" lang="{L["html"]}">{L["short"]}</a>')
    return f'<div class="lang{extra}" aria-label="{label}">' + "".join(items) + "</div>"

def build():
    global JS_KEYS, PHP_KEYS
    JS_KEYS, PHP_KEYS = js_keys(), php_keys()
    trs = {l: json.load(open(f"i18n/{l}.json", encoding="utf-8")) for l in LANGS if os.path.exists(f"i18n/{l}.json")}
    allmissing = {l: set() for l in trs}
    for p in PAGES:
        f = page_file(p)
        if not os.path.exists(f): continue
        src = open(f, encoding="utf-8").read()
        # páginas PT: hreflang + seletor com PT ativo
        src2 = re.sub(r'\s*<link rel="alternate" hreflang="[^"]*" href="[^"]*" />', "", src)
        src2 = src2.replace('<meta name="robots"', hreflang_block(p) + '<meta name="robots"', 1)
        src2 = re.sub(r'<div class="lang( lang-mobile)?" aria-label="[^"]*">.*?</div>', lambda m: switcher(p, "pt", "Idioma", m.group(1) or ""), src2, flags=re.S)
        if src2 != src: open(f, "w", encoding="utf-8").write(src2)
        for l, tr in trs.items():
            out, missing = translate_html(src2, tr, l, p)
            allmissing[l] |= missing
            d = f"{l}/{p}"; os.makedirs(d, exist_ok=True)
            open(d + "index.html", "w", encoding="utf-8").write(out)
    for l, m in allmissing.items():
        open(f"i18n/missing.{l}.json", "w", encoding="utf-8").write(json.dumps(sorted(m), ensure_ascii=False, indent=0))
        print(f"{l}: {len(m)} strings sem tradução (fallback PT)")
    sitemap()

def sitemap():
    if not os.path.exists("sitemap.xml"): return
    s = open("sitemap.xml", encoding="utf-8").read()
    s = s.replace('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">')
    s = re.sub(r'  <url><loc>[^<]*/(?:en|es|zh)/[^<]*</loc>.*?</url>\n', "", s)
    def alt(page): return "".join(f'<xhtml:link rel="alternate" hreflang="{h}" href="{u}" />' for h, u in [("pt-BR", f"{BASE}/{page}"), ("x-default", f"{BASE}/{page}")] + [(L["hreflang"], f"{BASE}/{l}/{page}") for l, L in LANGS.items()])
    def rep(m):
        loc = m.group(1); page = loc[len(BASE) + 1:]
        if page not in PAGES: return m.group(0)
        rest = m.group(2)
        pt = f"  <url><loc>{loc}</loc>{alt(page)}{rest}</url>\n"
        return pt + "".join(f"  <url><loc>{BASE}/{l}/{page}</loc>{alt(page)}{rest}</url>\n" for l in LANGS)
    s = re.sub(r'  <url><loc>([^<]*)</loc>(.*?)</url>\n', rep, s)
    open("sitemap.xml", "w", encoding="utf-8").write(s)
    print("sitemap:", s.count("<url>"), "urls")

if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "build"
    JS_KEYS, PHP_KEYS = js_keys(), php_keys()
    extract() if cmd == "extract" else build()
