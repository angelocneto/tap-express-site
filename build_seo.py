#!/usr/bin/env python3
"""Fundação SEO/GEO: JSON-LD, meta OG, sitemap, robots, llms.txt e páginas por unidade. Rode antes do commit."""
import json, re, os, html, datetime
BASE="https://tap.amodesenvolvimento.com.br"  # troque para https://www.tapexpress.com.br na virada do domínio
rede=json.loads(re.search(r'window\.TAP_REDE = (\{.*\});', open('data/rede.js',encoding='utf-8').read(), re.S).group(1))
units=rede['units']; wa=rede['wa']; today=datetime.date.today().isoformat()
def esc(s): return html.escape(str(s), quote=True)
DIAS={"seg":"segunda","ter":"terça","qua":"quarta","qui":"quinta","sex":"sexta"}
def dias(d): return ", ".join(DIAS[x] for x in d.split(",")) if d else "todos os dias úteis"

# ---------- JSON-LD principal ----------
org={"@context":"https://schema.org","@type":"Organization","@id":BASE+"/#organization","name":"TAP Express","alternateName":["TAP Transportes","Tap Express Transportes"],"url":BASE+"/","logo":BASE+"/assets/logo_verde.png","foundingDate":"2001","founder":{"@type":"Person","name":"Paulo Barreto"},"slogan":"Precisão regional. Velocidade que move negócios.","description":"Transportadora de cargas e encomendas expressas com 20 unidades e 104 localidades atendidas no oeste paulista, norte do Paraná e Mato Grosso do Sul. Entrega em até 18 horas, rastreamento via satélite e seguro de carga incluso.","telephone":"+55-18-3918-7777","email":rede['email'],"areaServed":[{"@type":"State","name":"São Paulo"},{"@type":"State","name":"Paraná"},{"@type":"State","name":"Mato Grosso do Sul"}],"address":{"@type":"PostalAddress","streetAddress":"Rod. SP 270 Raposo Tavares, km 556","addressLocality":"Regente Feijó","addressRegion":"SP","addressCountry":"BR"},"contactPoint":[{"@type":"ContactPoint","telephone":"+55-18-3918-7777","contactType":"customer service","areaServed":"BR","availableLanguage":"Portuguese"},{"@type":"ContactPoint","telephone":"+"+wa,"contactType":"sales","url":f"https://wa.me/{wa}"}],"sameAs":["https://www.instagram.com/tap.transportes/","https://www.facebook.com/taptransportes/","https://www.tapexpress.com.br/"]}
website={"@context":"https://schema.org","@type":"WebSite","@id":BASE+"/#website","url":BASE+"/","name":"TAP Express","publisher":{"@id":BASE+"/#organization"},"inLanguage":"pt-BR"}
def unit_ld(u):
    d={"@type":["LocalBusiness","MovingCompany"],"@id":f"{BASE}/unidades/{u['slug']}/#local","name":f"TAP Express · {u['n']}","parentOrganization":{"@id":BASE+"/#organization"},"url":f"{BASE}/unidades/{u['slug']}/","telephone":u['tel'],"email":u['email'],"address":{"@type":"PostalAddress","streetAddress":u['addr'],"addressLocality":u['n'],"addressRegion":u['uf'],"addressCountry":"BR"},"geo":{"@type":"GeoCoordinates","latitude":u['c'][1],"longitude":u['c'][0]},"areaServed":[{"@type":"City","name":c['n']} for c in u['cities']]+[{"@type":"City","name":u['n']}],"priceRange":"$$"}
    if u.get('foto'): d["image"]=f"{BASE}/{u['foto']}"
    return d
faq={"@context":"https://schema.org","@type":"FAQPage","mainEntity":[
 {"@type":"Question","name":"Quais cidades a TAP Express atende?","acceptedAnswer":{"@type":"Answer","text":f"A TAP Express atende {sum(1+len(u['cities']) for u in units)} localidades em São Paulo, Paraná e Mato Grosso do Sul a partir de {len(units)} unidades e bases, com distribuição centralizada em Presidente Prudente (Regente Feijó). Unidades: "+", ".join(u['n'] for u in units)+"."}},
 {"@type":"Question","name":"Qual o prazo de entrega da TAP Express?","acceptedAnswer":{"@type":"Answer","text":"Entrega em até 18 horas entre as cidades atendidas pela rede, com rotas diárias e centro de distribuição operando 24 horas."}},
 {"@type":"Question","name":"Como rastrear uma encomenda da TAP Express?","acceptedAnswer":{"@type":"Answer","text":"O rastreamento é feito no portal oficial https://ssw.inf.br/2/rastreamento com o código da encomenda ou nota fiscal. Todos os veículos têm rastreamento via satélite."}},
 {"@type":"Question","name":"Como pedir uma cotação de frete?","acceptedAnswer":{"@type":"Answer","text":f"Pelo formulário de cotação em 3 passos no site (com a assistente TAPIA), pelo WhatsApp central +{wa} ou pelo telefone (18) 3918-7777. A cotação gera um protocolo e a equipe comercial responde em horário comercial."}},
 {"@type":"Question","name":"A carga é segurada?","acceptedAnswer":{"@type":"Answer","text":"Sim. O seguro de carga está incluso em todos os envios, sem custo adicional."}},
]}
ld_home=[org,website,faq,{"@context":"https://schema.org","@type":"ItemList","name":"Unidades TAP Express","itemListElement":[{"@type":"ListItem","position":i+1,"item":unit_ld(u)} for i,u in enumerate(units)]}]

# ---------- injeta no index.html ----------
h=open('index.html',encoding='utf-8').read()
h=re.sub(r'\s*<!-- seo:start -->.*?<!-- seo:end -->','',h,flags=re.S)
seo=f'''
  <!-- seo:start -->
  <link rel="canonical" href="{BASE}/" />
  <meta name="robots" content="index, follow, max-image-preview:large" />
  <meta name="keywords" content="transportadora Presidente Prudente, transporte de encomendas expressas, frete oeste paulista, transportadora Londrina Maringá, cargas fracionadas SP PR MS, TAP Express, TAP Transportes" />
  <meta name="geo.region" content="BR-SP" /><meta name="geo.placename" content="Presidente Prudente" />
  <meta property="og:type" content="website" /><meta property="og:locale" content="pt_BR" />
  <meta property="og:site_name" content="TAP Express" />
  <meta property="og:title" content="TAP Express — Onde a urgência encontra domínio regional" />
  <meta property="og:description" content="Precisão regional. Velocidade que move negócios. 20 unidades, 104 localidades em SP, PR e MS, entrega em até 18 horas." />
  <meta property="og:url" content="{BASE}/" /><meta property="og:image" content="{BASE}/assets/hero.jpg" />
  <meta name="twitter:card" content="summary_large_image" /><meta name="twitter:title" content="TAP Express — Precisão regional. Velocidade que move negócios." /><meta name="twitter:image" content="{BASE}/assets/hero.jpg" />
  <script type="application/ld+json">{json.dumps(ld_home,ensure_ascii=False)}</script>
  <!-- seo:end -->'''
h=h.replace('<link rel="icon" href="assets/favicon.png" />','<link rel="icon" href="assets/favicon.png" />'+seo,1)
open('index.html','w',encoding='utf-8').write(h)

# ---------- páginas por unidade ----------
os.makedirs('unidades',exist_ok=True)
tpl=open('unidades/_template.html',encoding='utf-8').read()
links=''.join(f'<a href="/unidades/{x["slug"]}/">{esc(x["n"])} · {x["uf"]}</a>' for x in units)
for u in units:
    cities_html=''.join(f'<li><b>{esc(c["n"])}</b><span>{esc(dias(c.get("d","")))}</span></li>' for c in u['cities']) or '<li><b>Consulte a cobertura da região</b><span>pela central (18) 3918-7777</span></li>'
    n_c=1+len(u['cities'])
    title=f"Transportadora em {u['n']} · TAP Express {u['uf']}"
    desc=f"Unidade TAP Express em {u['n']} ({u['uf']}): {u['addr']}. Atende {n_c} localidades: {', '.join(c['n'] for c in u['cities'][:8])}{'…' if len(u['cities'])>8 else ''}. Encomendas expressas, malotes e cargas fracionadas com entrega em até 18 horas."
    ld=[{"@context":"https://schema.org",**unit_ld(u)},{"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"TAP Express","item":BASE+"/"},{"@type":"ListItem","position":2,"name":"Unidades","item":BASE+"/#unidades"},{"@type":"ListItem","position":3,"name":u['n'],"item":f"{BASE}/unidades/{u['slug']}/"}]}]
    page=(tpl.replace('{{TITLE}}',esc(title)).replace('{{DESC}}',esc(desc)).replace('{{URL}}',f"{BASE}/unidades/{u['slug']}/").replace('{{NAME}}',esc(u['n'])).replace('{{UF}}',u['uf'])
        .replace('{{ADDR}}',esc(u['addr'])).replace('{{PHONE}}',esc(u['phone'])).replace('{{TEL}}',u['tel']).replace('{{EMAIL}}',u['email']).replace('{{WA}}',wa)
        .replace('{{FOTO}}',('/'+u['foto']) if u.get('foto') else '/assets/frota.jpg').replace('{{FOTOCAP}}','Vista territorial da região' if u.get('fotoTipo')=='aerea' else f"Unidade TAP Express em {esc(u['n'])}")
        .replace('{{CITIES}}',cities_html).replace('{{NC}}',str(n_c)).replace('{{KIND}}','Hub de distribuição · matriz' if u.get('hub') else ('Base de atendimento' if u['slug']=='ourinhos' else f"Unidade TAP Express · {u['uf']}"))
        .replace('{{LD}}',json.dumps(ld,ensure_ascii=False)).replace('{{LINKS}}',links).replace('{{LAT}}',str(u['c'][1])).replace('{{LNG}}',str(u['c'][0])).replace('{{YEAR}}',str(datetime.date.today().year)))
    os.makedirs(f"unidades/{u['slug']}",exist_ok=True); open(f"unidades/{u['slug']}/index.html",'w',encoding='utf-8').write(page)

# ---------- sitemap / robots / llms.txt ----------
urls=[(BASE+'/','1.0','weekly')]+[(f"{BASE}/unidades/{u['slug']}/",'0.8','monthly') for u in units]
import os
if os.path.exists('data/pages.json'):
    for u_,p_,f_,t_ in json.load(open('data/pages.json',encoding='utf-8')): urls.append((u_,p_,f_))
open('sitemap.xml','w',encoding='utf-8').write('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'+''.join(f'  <url><loc>{l}</loc><lastmod>{today}</lastmod><changefreq>{f}</changefreq><priority>{p}</priority></url>\n' for l,p,f in urls)+'</urlset>\n')
open('robots.txt','w').write(f"User-agent: *\nAllow: /\nDisallow: /atendimento/\nDisallow: /api/\n\n# Crawlers de IA: liberados (GEO)\nUser-agent: GPTBot\nAllow: /\nUser-agent: OAI-SearchBot\nAllow: /\nUser-agent: ChatGPT-User\nAllow: /\nUser-agent: ClaudeBot\nAllow: /\nUser-agent: anthropic-ai\nAllow: /\nUser-agent: PerplexityBot\nAllow: /\nUser-agent: Google-Extended\nAllow: /\nUser-agent: Applebot-Extended\nAllow: /\nUser-agent: Bytespider\nAllow: /\n\nSitemap: {BASE}/sitemap.xml\n")
llms=f"""# TAP Express

> Transportadora de cargas e encomendas expressas fundada em 2001 em Presidente Prudente (SP). Slogan: "Precisão regional. Velocidade que move negócios." Atende {sum(1+len(u['cities']) for u in units)} localidades em São Paulo, Paraná e Mato Grosso do Sul a partir de {len(units)} unidades e bases. Entrega em até 18 horas, rastreamento via satélite, seguro de carga incluso, centro de distribuição 24h.

## Contato
- Telefone central: (18) 3918-7777
- WhatsApp central: +{wa}
- E-mail: {rede['email']}
- Rastreamento (portal oficial): https://ssw.inf.br/2/rastreamento
- Cotação online: {BASE}/#cotacao
- Assistente virtual: TAPIA ("Inteligência que move") — orienta cotação, rastreamento e atendimento humano.

## Serviços
- Encomendas expressas (coleta ágil, entrega em até 18h)
- Malotes e documentos corporativos
- Cargas fracionadas (frota própria e parceiros homologados)
- Rastreamento via satélite em todos os veículos
- Seguro de carga incluso
- Área do cliente (acompanhamento e boletos), SAC 24h

## Unidades e cobertura
""" + "".join(f"- [{u['n']} · {u['uf']}]({BASE}/unidades/{u['slug']}/): {u['addr']} · {u['phone']} · atende " + (", ".join(c['n']+(f" ({dias(c['d'])})" if c.get('d') else '') for c in u['cities']) or "consulte a central") + "\n" for u in units) + f"""
## Páginas
- [Início]({BASE}/)
- [Sobre]({BASE}/sobre/) · [Serviços]({BASE}/servicos/) · [Rastreamento]({BASE}/rastreamento/) · [Cotação]({BASE}/cotacao/) · [TAPIA]({BASE}/tapia/) · [Contato]({BASE}/contato/)
- [Unidades]({BASE}/unidades/) · [Cidades atendidas]({BASE}/cidades/) (uma página por cidade: {BASE}/cidades/<cidade>/)
- [Mapa da rede]({BASE}/#unidades)
- [Sitemap]({BASE}/sitemap.xml)
"""
open('llms.txt','w',encoding='utf-8').write(llms)
print(f"seo ok: {len(units)} páginas de unidade, sitemap {len(urls)} urls")
