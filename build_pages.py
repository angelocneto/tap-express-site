#!/usr/bin/env python3
"""Subpáginas do site TAP Express (SEO/GEO). Gera /sobre, /servicos/*, /rastreamento, /cotacao, /tapia, /contato, /unidades/, /cidades/*. Rode antes de build_seo.py."""
import json, re, os, html, datetime, unicodedata
BASE="https://tap.amodesenvolvimento.com.br"
WA_TXT="Ol%C3%A1!%20Estou%20no%20site%20da%20TAP%20Express%20e%20quero%20falar%20com%20o%20atendimento."
rede=json.loads(re.search(r'window\.TAP_REDE = (\{.*\});', open('data/rede.js',encoding='utf-8').read(), re.S).group(1))
units=rede['units']; WA=rede['wa']; EMAIL=rede['email']; YEAR=datetime.date.today().year; YEARS=YEAR-2001
esc=lambda s: html.escape(str(s), quote=True)
def slug(s): return re.sub(r'[^a-z0-9]+','-',unicodedata.normalize('NFD',s).encode('ascii','ignore').decode().lower()).strip('-')
DIAS={"seg":"segunda","ter":"terça","qua":"quarta","qui":"quinta","sex":"sexta"}
def dias(d): return ", ".join(DIAS[x] for x in d.split(",")) if d else "todos os dias úteis"
N_UNITS=len(units); N_PLACES=sum(1+len(u['cities']) for u in units)
index_html=open('index.html',encoding='utf-8').read()
WIDGET=index_html[index_html.index('  <!-- ===== Tap.IA no canto + cotação ===== -->'):index_html.index('  <script src="https://unpkg.com/lenis')]
WIDGET=WIDGET.replace('src="assets/','src="/assets/').replace('poster="assets/','poster="/assets/').replace('href="#cotacao"','href="/cotacao/"')
V=re.search(r'\?v=(\d+)',index_html); V=V.group(1) if V else str(YEAR)
PAGES=[]  # (url, priority, changefreq, title)

def layout(url, title, desc, body, ld, theme="dark", og_image=BASE+"/assets/hero.jpg", crumbs=None, extra_js=""):
    ldj=json.dumps(ld,ensure_ascii=False)
    crumb_html=''.join(f'<a href="{c[1]}">{esc(c[0])}</a><span>›</span>' for c in (crumbs or [("TAP Express","/")]))
    page=f'''<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{esc(title)}</title>
  <meta name="description" content="{esc(desc)}" />
  <link rel="canonical" href="{url}" /><meta name="robots" content="index, follow, max-image-preview:large" />
  <meta property="og:type" content="website" /><meta property="og:locale" content="pt_BR" /><meta property="og:site_name" content="TAP Express" />
  <meta property="og:title" content="{esc(title)}" /><meta property="og:description" content="{esc(desc)}" /><meta property="og:url" content="{url}" /><meta property="og:image" content="{og_image}" />
  <meta name="twitter:card" content="summary_large_image" />
  <link rel="icon" href="/assets/favicon.png" />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/styles.css?v={V}" /><link rel="stylesheet" href="/theme.css?v={V}" /><link rel="stylesheet" href="/cotacao.css?v={V}" /><link rel="stylesheet" href="/pages.css?v={V}" />
  <script type="application/ld+json">{ldj}</script>
</head>
<body data-theme="{theme}">
  <header class="nav is-solid" id="nav">
    <a class="nav-logo" href="/" aria-label="TAP Express"><img class="light" src="/assets/logo_branca.png" alt="TAP Express" /><img class="dark" src="/assets/logo_cor.png" alt="TAP Express" /></a>
    <nav class="nav-links"><a href="/servicos/">Serviços</a><a href="/#jornada">Como funciona</a><a href="/#frota">Frota</a><a href="/tapia/">TAPIA</a><a href="/unidades/">Unidades</a><a href="/carreiras/">Carreiras</a><a href="/contato/">Contato</a></nav>
    <div class="nav-cta"><a class="btn btn-ghost" href="/rastreamento/">Rastrear</a><a class="btn btn-solid" href="/cotacao/" data-open-quote="nav">Cotação</a></div>
    <button class="nav-burger" id="burger" aria-label="Menu"><span></span><span></span></button>
  </header>
  <div class="mobile-menu" id="mobileMenu"><a href="/servicos/">Serviços</a><a href="/#jornada">Como funciona</a><a href="/#frota">Frota</a><a href="/tapia/">TAPIA</a><a href="/unidades/">Unidades</a><a href="/carreiras/">Carreiras</a><a href="/contato/">Contato</a><a class="btn btn-solid" href="/cotacao/">Pedir cotação</a></div>
  <main>
    <section class="page-hero"><div class="wrap"><div class="crumbs">{crumb_html}</div>{body[0]}</div></section>
    {body[1]}
    <section class="cta-band"><div class="wrap"><div class="box"><div><p class="kicker">Precisa enviar hoje?</p><h2>Peça a cotação em 3 passos <span style="color:var(--green)">com a TAPIA.</span></h2></div><div class="btn-row" style="margin:0;justify-content:flex-end"><a class="btn btn-solid" href="/cotacao/" data-open-quote="cta-band">Pedir cotação</a><a class="btn btn-ghost" href="https://wa.me/{WA}?text={WA_TXT}" target="_blank" rel="noopener">WhatsApp (18) 99109-6441</a></div></div></div></section>
  </main>
  <footer class="footer"><div class="wrap footer-grid">
    <div><img class="footer-logo" src="/assets/logo_branca.png" alt="TAP Express" /><p>Transporte rodoviário de cargas e encomendas desde 2001, a partir de Presidente Prudente, SP.</p></div>
    <div><h5>Navegação</h5><a href="/sobre/">Sobre a empresa</a><a href="/servicos/">Serviços</a><a href="/unidades/">Unidades</a><a href="/cidades/">Cidades atendidas</a><a href="/tapia/">TAPIA</a><a href="/carreiras/">Carreiras</a><a href="/contato/">Contato</a></div>
    <div><h5>Cliente</h5><a href="/rastreamento/">Rastreamento</a><a href="/cotacao/">Cotação online</a><a href="tel:+551839187777">SAC (18) 3918-7777</a><a href="https://wa.me/{WA}" target="_blank" rel="noopener">WhatsApp</a></div>
    <div><h5>Redes</h5><a href="https://www.instagram.com/tap.transportes/" target="_blank" rel="noopener">Instagram</a><a href="https://www.facebook.com/taptransportes/" target="_blank" rel="noopener">Facebook</a></div>
  </div><div class="wrap footer-bottom"><span>© {YEAR} TAP Express · Transportes</span><span>Precisão regional. Velocidade que move negócios.</span></div></footer>
{WIDGET}
  <script src="/data/rede.js?v={V}"></script>
  <script src="/cotacao.js?v={V}"></script>{extra_js}
  <script>document.getElementById("burger").addEventListener("click",()=>{{document.getElementById("burger").classList.toggle("is-open");document.getElementById("mobileMenu").classList.toggle("is-open")}});document.querySelectorAll('[data-open-quote]').forEach(a=>a.addEventListener('click',e=>e.preventDefault()));document.querySelectorAll('.years-since').forEach(e=>e.textContent={YEARS});</script>
</body>
</html>'''
    return page
def write(path, url, title, desc, hero, body, ld, prio="0.7", freq="monthly", **kw):
    os.makedirs(path, exist_ok=True); open(os.path.join(path,'index.html'),'w',encoding='utf-8').write(layout(url,title,desc,(hero,body),ld,**kw)); PAGES.append((url,prio,freq,title))
def faq_ld(items): return {"@context":"https://schema.org","@type":"FAQPage","mainEntity":[{"@type":"Question","name":q,"acceptedAnswer":{"@type":"Answer","text":re.sub('<[^>]+>','',a)}} for q,a in items]}
def faq_html(items): return '<div class="faq">'+''.join(f'<details><summary>{esc(q)}</summary><p>{a}</p></details>' for q,a in items)+'</div>'
def bc(*items): return {"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":i+1,"name":n,"item":u} for i,(n,u) in enumerate(items)]}
ORG={"@id":BASE+"/#organization"}
def service_ld(name,desc,url,typ="Service"): return {"@context":"https://schema.org","@type":typ,"name":name,"description":desc,"url":url,"provider":{"@type":"Organization","name":"TAP Express","@id":BASE+"/#organization"},"areaServed":[{"@type":"State","name":s} for s in ("São Paulo","Paraná","Mato Grosso do Sul")],"serviceType":name}
hub=[u for u in units if u.get('hub')][0]

# ---------------- SOBRE ----------------
faq_sobre=[("Desde quando a TAP Express existe?",f"A TAP Transportes foi fundada em 2001 em Presidente Prudente, por Paulo Barreto. São {YEARS} anos de estrada."),
 ("Onde fica a matriz?","Na Rodovia SP 270 Raposo Tavares, km 556, em Regente Feijó, na região de Presidente Prudente. É de lá que sai a distribuição para toda a rede."),
 ("Quantas unidades a TAP tem?",f"{N_UNITS} unidades e bases em São Paulo, Paraná e Mato Grosso do Sul, que atendem {N_PLACES} localidades."),
 ("A TAP transporta para fora dessas regiões?","A rede cobre o oeste paulista, o norte do Paraná e o Mato Grosso do Sul. Para outros destinos, fale com a central pelo telefone (18) 3918-7777 e a equipe avalia caso a caso.")]
hero=f'<p class="kicker">Sobre a TAP Express</p><h1>{YEARS} anos ligando cidades do interior <span>no menor tempo possível.</span></h1><p class="sub">A TAP começou em 2001 em Presidente Prudente e hoje distribui para {N_PLACES} localidades a partir de {N_UNITS} unidades e bases. Frota própria, parceiros homologados e gente que conhece cada estrada da região.</p>'
body=f'''<section><div class="wrap two-col">
<div class="prose">
<h2>Como tudo começou</h2>
<p>Paulo Barreto fundou a TAP Transportes em 2001, em Presidente Prudente, com uma ideia simples: transporte rodoviário rápido e confiável para pessoas e empresas do oeste paulista. Os primeiros caminhões faziam a ponte entre a região e o restante do estado.</p>
<p>A operação cresceu para o norte do Paraná e para o Mato Grosso do Sul. Hoje a distribuição sai da matriz em Regente Feijó, na rodovia Raposo Tavares, e chega às {N_UNITS} unidades e bases da rede, cada uma responsável por um grupo de cidades da sua região.</p>
<div class="facts"><div><b>{YEARS}+</b><span>anos de estrada</span></div><div><b>{N_UNITS}</b><span>unidades e bases</span></div><div><b>{N_PLACES}</b><span>localidades</span></div></div>
<h2>Missão, visão e valores</h2>
<p><strong>Missão:</strong> melhorar processos e tecnologia todos os anos e cumprir o que foi combinado com o cliente.</p>
<p><strong>Visão:</strong> crescer de forma sustentável, ligando as maiores distâncias no menor tempo possível e com respeito ao meio ambiente.</p>
<ul><li><strong>Inovação</strong> em processos e tecnologia, como o rastreamento via satélite em toda a frota e a assistente <a href="/tapia/">TAPIA</a>.</li><li><strong>Rapidez</strong> com segurança: entrega em até 18 horas entre as cidades da rede.</li><li><strong>Capital humano:</strong> equipes locais que conhecem os clientes pelo nome.</li><li><strong>Responsabilidade social e ambiental</strong> na operação e na escolha de parceiros.</li></ul>
<h2>Como a rede funciona</h2>
<p>Cada unidade atende as cidades vizinhas em rotas fixas. Cidades menores têm dias de atendimento definidos (por exemplo, terça e quinta), o que mantém o prazo previsível e o custo baixo. Veja a lista completa em <a href="/cidades/">cidades atendidas</a> ou explore o <a href="/#unidades">mapa da rede</a>.</p>
<h2>Perguntas frequentes</h2>{faq_html(faq_sobre)}
</div>
<figure class="side-photo"><img src="/assets/pessoas/rotas-escritorio.jpg" alt="Equipe da TAP Express planejando rotas sobre o mapa, com a frota ao fundo" /><figcaption>Planejamento de rotas no pátio da matriz</figcaption></figure>
</div></section>
<section class="section-pad" data-theme="light"><div class="wrap"><p class="kicker">Frota</p><h2>Caminhões baú, utilitários e carretas <span>com rastreamento via satélite.</span></h2><div class="page-photo-strip"><img src="/assets/frota_01.jpg" alt="Caminhão baú TAP Express no pátio" /><img src="/assets/frota_06.jpg" alt="Frota TAP Express alinhada no centro de distribuição" /><img src="/assets/frota_03.jpg" alt="Carretas TAP Express nas docas" /><img src="/assets/frota_12.jpg" alt="Caminhões TAP Express prontos para rota" /></div></div></section>'''
write('sobre',BASE+'/sobre/',"Sobre a TAP Express | Transportadora desde 2001 em Presidente Prudente",f"História, missão e valores da TAP Express: fundada em 2001 por Paulo Barreto, com {N_UNITS} unidades e {N_PLACES} localidades atendidas em SP, PR e MS.",hero,body,[{"@context":"https://schema.org","@type":"AboutPage","name":"Sobre a TAP Express","url":BASE+"/sobre/","about":ORG},bc(("TAP Express",BASE+"/"),("Sobre",BASE+"/sobre/")),faq_ld(faq_sobre)],prio="0.8",crumbs=[("TAP Express","/"),("Sobre","/sobre/")])

# ---------------- SERVIÇOS ----------------
SERV=[
 ("encomendas-expressas","Encomendas expressas","Caixas e pacotes de pequeno e médio porte com coleta no mesmo dia e entrega em até 18 horas entre as cidades da rede.",
  '''<h2>Para quem é</h2><p>Lojas, e-commerces regionais, indústrias com peças de reposição, clínicas e escritórios que precisam que algo chegue amanhã cedo em outra cidade do interior. A encomenda expressa é o serviço mais usado da TAP.</p>
<h2>Como funciona</h2><ul><li>Você pede a cotação pelo site, pela <a href="/tapia/">TAPIA</a> ou pelo WhatsApp e informa origem, destino, volumes e peso.</li><li>A unidade da sua cidade agenda a coleta ou você entrega no balcão.</li><li>A encomenda segue na rota do dia até o centro de distribuição em Regente Feijó e de lá para a unidade de destino.</li><li>Entrega em até 18 horas nas cidades da rede, com comprovante e rastreamento pelo portal.</li></ul>
<h2>O que está incluso</h2><ul><li>Seguro de carga sem custo adicional.</li><li>Rastreamento via satélite do veículo.</li><li>Atendimento pela unidade local e pelo SAC 24 horas.</li></ul>''',
  [("Qual o peso máximo de uma encomenda expressa?","Não há limite fixo: pacotes até 30 kg seguem como encomenda; acima disso a equipe cota como carga fracionada, no mesmo pedido."),("Posso enviar para uma cidade que não está na lista?","A rede cobre 104 localidades em SP, PR e MS. Para outros destinos, a central avalia parceiros de conexão. Pergunte na cotação."),("Como sei que chegou?","Pelo portal de rastreamento, com o código da encomenda ou a nota fiscal, e pelo comprovante de entrega assinado.")]),
 ("malotes-e-documentos","Malotes e documentos","Malotes corporativos, contratos e documentos entre matriz, filiais e clientes, com controle de quem enviou e quem recebeu.",
  '''<h2>Para quem é</h2><p>Bancos e cooperativas, escritórios de contabilidade e advocacia, redes de lojas, cartórios e empresas com filiais em cidades diferentes da região.</p>
<h2>Como funciona</h2><ul><li>Malotes lacrados são coletados em horário combinado e seguem na rota diária.</li><li>Cada malote tem registro de remetente, destinatário e horário de entrega.</li><li>Rotas fixas permitem contratos mensais com coleta programada em dias definidos.</li></ul>
<h2>O que está incluso</h2><ul><li>Lacre e conferência no balcão.</li><li>Comprovante de entrega com assinatura.</li><li>Seguro incluso e rastreamento do veículo.</li></ul>''',
  [("Vocês fazem coleta programada de malotes?","Sim. Contratos mensais têm coleta em dias fixos na sua empresa. Escreva a frequência desejada nas observações da cotação."),("Documentos chegam no mesmo dia?","Entre cidades da mesma unidade, muitas vezes sim. Entre unidades diferentes o prazo é de até 18 horas."),("Como funciona o lacre?","O malote é lacrado no envio e o lacre é conferido na entrega. Qualquer divergência é registrada no comprovante.")]),
 ("cargas-fracionadas","Cargas fracionadas","Paletes, volumes maiores e cargas de vários clientes no mesmo veículo, com frota própria e parceiros homologados em rotas diárias.",
  '''<h2>Para quem é</h2><p>Indústrias, distribuidores, autopeças, agronegócio e comércio que despacham volumes acima do tamanho de encomenda, mas não fecham um caminhão inteiro.</p>
<h2>Como funciona</h2><ul><li>A cotação considera peso, dimensões, valor da mercadoria e a rota entre origem e destino.</li><li>A carga é consolidada com outras da mesma rota no centro de distribuição.</li><li>Entrega na unidade de destino ou no endereço do cliente, conforme combinado.</li></ul>
<h2>O que está incluso</h2><ul><li>Seguro de carga pelo valor declarado.</li><li>Conferência de volumes na coleta e na entrega.</li><li>Acompanhamento pela área do cliente e pelo SAC.</li></ul>''',
  [("Qual o tamanho máximo de uma carga fracionada?","Paletes padrão e volumes até a capacidade dos caminhões baú da frota. Para cargas especiais, a equipe avalia na cotação."),("Vocês fazem carga fechada (lotação)?","Sim, sob consulta. Informe o volume total e a rota na cotação."),("Como é calculado o frete?","Pelo peso ou pela cubagem, o que for maior, mais o valor declarado para o seguro e a distância da rota.")])]
tiles=''.join(f'<a class="tile" href="/servicos/{s}/"><h3>{esc(n)}</h3><p>{esc(d)}</p><small>Saiba mais →</small></a>' for s,n,d,_,_ in SERV)
hero=f'<p class="kicker">Serviços</p><h1>Transporte de encomendas, malotes e cargas <span>com entrega em até 18 horas.</span></h1><p class="sub">Três serviços, uma mesma rede: {N_UNITS} unidades em SP, PR e MS, frota própria rastreada por satélite e seguro incluso em todo envio.</p>'
body=f'<section class="section-pad"><div class="wrap"><div class="grid-3">{tiles}</div></div></section><section class="section-pad" data-theme="light"><div class="wrap two-col"><div class="prose"><h2>O que vale para todos os serviços</h2><ul><li><strong>Entrega em até 18 horas</strong> entre as cidades da rede, com rotas diárias.</li><li><strong>Seguro de carga incluso</strong>, sem custo extra.</li><li><strong>Rastreamento via satélite</strong> em todos os veículos e portal de rastreamento para o cliente.</li><li><strong>Atendimento local</strong> na unidade da sua cidade e SAC 24 horas.</li><li><strong>Cotação com protocolo</strong> pelo site, pela TAPIA ou pelo WhatsApp.</li></ul></div><figure class="side-photo"><img src="/assets/pessoas/entrega.jpg" alt="Motorista da TAP Express entregando uma caixa na porta de uma loja" /><figcaption>Entrega no balcão do cliente</figcaption></figure></div></section>'
write('servicos',BASE+'/servicos/',"Serviços de transporte | Encomendas, malotes e cargas fracionadas | TAP Express","Encomendas expressas, malotes e documentos, cargas fracionadas. Entrega em até 18 horas em 104 localidades de SP, PR e MS, seguro incluso e rastreamento via satélite.",hero,body,[{"@context":"https://schema.org","@type":"CollectionPage","name":"Serviços TAP Express","url":BASE+"/servicos/"},bc(("TAP Express",BASE+"/"),("Serviços",BASE+"/servicos/"))],prio="0.9",crumbs=[("TAP Express","/"),("Serviços","/servicos/")])
photos={"encomendas-expressas":("/assets/pessoas/recepcao.jpg","Registro de uma encomenda na recepção da unidade"),"malotes-e-documentos":("/assets/pessoas/conferencia.jpg","Conferência de documentos no balcão"),"cargas-fracionadas":("/assets/frota_06.jpg","Frota TAP Express no centro de distribuição")}
for s,n,d,txt,fq in SERV:
    hero=f'<p class="kicker">Serviço</p><h1>{esc(n)} <span>com a TAP Express.</span></h1><p class="sub">{esc(d)}</p>'
    body=f'<section><div class="wrap two-col"><div class="prose">{txt}<h2>Perguntas frequentes</h2>{faq_html(fq)}<h2>Outros serviços</h2><ul>'+''.join(f'<li><a href="/servicos/{s2}/">{esc(n2)}</a></li>' for s2,n2,_,_,_ in SERV if s2!=s)+f'</ul></div><figure class="side-photo"><img src="{photos[s][0]}" alt="{esc(photos[s][1])}" /><figcaption>{esc(photos[s][1])}</figcaption></figure></div></section>'
    write(f'servicos/{s}',f'{BASE}/servicos/{s}/',f"{n} | TAP Express",d,hero,body,[service_ld(n,d,f'{BASE}/servicos/{s}/'),bc(("TAP Express",BASE+"/"),("Serviços",BASE+"/servicos/"),(n,f'{BASE}/servicos/{s}/')),faq_ld(fq)],prio="0.8",crumbs=[("TAP Express","/"),("Serviços","/servicos/"),(n,f"/servicos/{s}/")])

# ---------------- RASTREAMENTO ----------------
faq_r=[("Onde encontro o código de rastreio?","No comprovante de coleta ou no e-mail/WhatsApp enviado pela unidade. A nota fiscal também serve para localizar a encomenda."),("O status não atualiza. O que fazer?","O portal atualiza a cada leitura nas unidades. Se passaram mais de 24 horas sem mudança, fale com a unidade de destino ou com o SAC (18) 3918-7777."),("Posso rastrear pelo WhatsApp?","Sim. Envie o código para o WhatsApp central e a equipe responde com a posição atual.")]
hero='<p class="kicker">Rastreamento</p><h1>Onde está a minha <span>encomenda?</span></h1><p class="sub">O rastreamento é feito no portal oficial da TAP. Informe o código da encomenda ou a nota fiscal e veja em que etapa a entrega está.</p>'
body=f'<section><div class="wrap two-col"><div class="prose"><form class="track-form" action="https://ssw.inf.br/2/rastreamento" method="get" target="_blank" onsubmit="window.open(\'https://ssw.inf.br/2/rastreamento\',\'_blank\');return false;"><input type="text" placeholder="Código de rastreio ou nota fiscal" aria-label="Código de rastreio" /><button class="btn btn-solid" type="submit">Rastrear no portal</button></form><h2>Como funciona</h2><ul><li>Cada encomenda recebe um código na coleta.</li><li>O veículo é rastreado por satélite e a encomenda é lida na saída da origem, na chegada ao centro de distribuição e na unidade de destino.</li><li>Na entrega, o comprovante assinado fica disponível para consulta.</li></ul><h2>Perguntas frequentes</h2>{faq_html(faq_r)}</div><figure class="side-photo"><img src="/assets/pessoas/conferencia.jpg" alt="Cliente e atendente conferindo o documento de entrega" /><figcaption>Conferência na entrega</figcaption></figure></div></section>'
write('rastreamento',BASE+'/rastreamento/',"Rastreamento de encomendas | TAP Express","Rastreie sua encomenda TAP Express pelo código ou nota fiscal no portal oficial. Veículos rastreados via satélite e comprovante de entrega disponível.",hero,body,[{"@context":"https://schema.org","@type":"WebPage","name":"Rastreamento TAP Express","url":BASE+"/rastreamento/"},bc(("TAP Express",BASE+"/"),("Rastreamento",BASE+"/rastreamento/")),faq_ld(faq_r)],prio="0.8",crumbs=[("TAP Express","/"),("Rastreamento","/rastreamento/")])

# ---------------- COTAÇÃO ----------------
hero='<p class="kicker">Cotação</p><h1>Cotação de frete em <span>3 passos.</span></h1><p class="sub">Origem e destino, o que vai viajar e seus dados. A TAPIA registra o pedido com protocolo e a equipe comercial responde em horário comercial.</p>'
body=f'<section><div class="wrap two-col"><div class="prose"><p><a class="btn btn-solid" href="#" data-open-quote="pagina-cotacao">Abrir a cotação agora</a></p><h2>O que você precisa informar</h2><ul><li>Cidade de origem e de destino (o formulário mostra na hora se a cidade está na rede).</li><li>Tipo: encomenda, malote ou carga fracionada.</li><li>Volumes, peso e, se souber, dimensões e valor da mercadoria para o seguro.</li><li>Nome, WhatsApp e e-mail para retorno.</li></ul><h2>Outros canais</h2><ul><li>WhatsApp central: <a href="https://wa.me/{WA}?text={WA_TXT}" target="_blank" rel="noopener">(18) 99109-6441</a></li><li>Telefone: <a href="tel:+551839187777">(18) 3918-7777</a></li><li>E-mail: <a href="mailto:{EMAIL}">{EMAIL}</a></li></ul></div><figure class="side-photo"><img src="/assets/pessoas/recepcao.jpg" alt="Atendente registrando um pedido de cotação" /><figcaption>Cada pedido vira um protocolo</figcaption></figure></div></section><script>window.addEventListener("load",()=>setTimeout(()=>{{const a=document.querySelector("[data-open-quote=\'pagina-cotacao\']");if(a&&!location.hash.includes("nao"))a.click();}},900));</script>'
write('cotacao',BASE+'/cotacao/',"Cotação de frete online | TAP Express","Peça sua cotação de frete em 3 passos com a TAPIA: origem, destino, carga e contato. Retorno pela equipe comercial em horário comercial.",hero,body,[{"@context":"https://schema.org","@type":"ContactPage","name":"Cotação TAP Express","url":BASE+"/cotacao/"},bc(("TAP Express",BASE+"/"),("Cotação",BASE+"/cotacao/"))],prio="0.9",crumbs=[("TAP Express","/"),("Cotação","/cotacao/")])

# ---------------- TAPIA ----------------
faq_t=[("A TAPIA dá o preço do frete?","Não. Ela registra o pedido com protocolo e a equipe comercial confirma preço e prazo. A TAPIA nunca inventa valores."),("A TAPIA funciona no WhatsApp?","Hoje ela orienta no site e encaminha para o WhatsApp central com o contexto da conversa. A integração direta está no roteiro."),("Quem responde depois da TAPIA?","Pessoas da unidade responsável pela sua cidade ou da central em Presidente Prudente.")]
hero='<img class="tapia-logo" src="/assets/tapia-logo.png" alt="TAP.IA, inteligência que move" /><h1>Conheça a <span class="gold-text">TAPIA.</span></h1><p class="sub">A inteligência da TAP Express que orienta cada conversa: cotação, rastreamento, cobertura por cidade e atendimento humano, sempre com contexto.</p>'
body=f'''<section><div class="wrap two-col"><div class="prose"><h2>O que ela faz hoje</h2><ul><li>Abre a cotação em 3 passos e confere se a cidade está na rede.</li><li>Leva você ao portal de rastreamento.</li><li>Mostra no mapa qual unidade atende cada cidade e em que dias.</li><li>Encaminha ao WhatsApp da equipe com o resumo da conversa.</li></ul>
<h2>O que ela nunca faz</h2><ul><li>Inventar preço, prazo, status de entrega ou unidade.</li><li>Prometer integrações que ainda não existem.</li><li>Substituir a pessoa que fecha o atendimento.</li></ul>
<h2>Perguntas frequentes</h2>{faq_html(faq_t)}</div><figure class="side-photo"><img src="/assets/tapia-poster.jpg" alt="TAPIA, assistente virtual da TAP Express" /><figcaption>TAPIA</figcaption></figure></div></section>'''
write('tapia',BASE+'/tapia/',"TAPIA, a assistente inteligente da TAP Express","TAPIA orienta cotação, rastreamento e cobertura por cidade no site da TAP Express e encaminha ao atendimento humano com contexto. Inteligência que move.",hero,body,[{"@context":"https://schema.org","@type":"WebPage","name":"TAPIA","url":BASE+"/tapia/","about":{"@type":"SoftwareApplication","name":"TAPIA","applicationCategory":"BusinessApplication","operatingSystem":"Web","provider":ORG}},bc(("TAP Express",BASE+"/"),("TAPIA",BASE+"/tapia/")),faq_ld(faq_t)],prio="0.7",crumbs=[("TAP Express","/"),("TAPIA","/tapia/")])

# ---------------- CONTATO ----------------
hero='<p class="kicker">Contato</p><h1>Fale com a TAP, <span>de gente para gente.</span></h1><p class="sub">Central em Presidente Prudente e unidades em cada região. Escolha o canal que preferir.</p>'
body=f'<section><div class="wrap two-col"><div class="prose"><div class="facts"><div><b><a href="tel:+551839187777" style="color:inherit">(18) 3918-7777</a></b><span>Telefone central · SAC 24h</span></div><div><b><a href="https://wa.me/{WA}?text={WA_TXT}" target="_blank" rel="noopener" style="color:inherit">WhatsApp</a></b><span>(18) 99109-6441</span></div><div><b><a href="mailto:{EMAIL}" style="color:inherit">E-mail</a></b><span>{EMAIL}</span></div></div><h2>Matriz e centro de distribuição</h2><p>Rod. SP 270 Raposo Tavares, km 556, Regente Feijó, região de Presidente Prudente, SP. <a href="https://www.google.com/maps/place/22%C2%B009\'12.5%22S+51%C2%B023\'28.2%22W/@-22.1534593,-51.393727,17z" target="_blank" rel="noopener">Como chegar</a>.</p><h2>Unidades</h2><p>Cada unidade tem telefone próprio. Veja endereços e cidades atendidas em <a href="/unidades/">unidades</a> ou procure sua cidade em <a href="/cidades/">cidades atendidas</a>.</p><h2>Redes</h2><p><a href="https://www.instagram.com/tap.transportes/" target="_blank" rel="noopener">Instagram @tap.transportes</a> · <a href="https://www.facebook.com/taptransportes/" target="_blank" rel="noopener">Facebook</a></p></div><figure class="side-photo"><img src="/assets/pessoas/recepcao.jpg" alt="Atendimento na recepção da unidade TAP Express" /><figcaption>Atendimento na unidade</figcaption></figure></div></section>'
write('contato',BASE+'/contato/',"Contato | TAP Express","Telefone (18) 3918-7777, WhatsApp (18) 99109-6441 e e-mail recepcao@taptransportes.com.br. Matriz na Rod. SP 270, km 556, Regente Feijó, Presidente Prudente.",hero,body,[{"@context":"https://schema.org","@type":"ContactPage","name":"Contato TAP Express","url":BASE+"/contato/","about":ORG},bc(("TAP Express",BASE+"/"),("Contato",BASE+"/contato/"))],prio="0.8",crumbs=[("TAP Express","/"),("Contato","/contato/")])

# ---------------- UNIDADES (índice) ----------------
def unit_tiles(uf): return ''.join(f'<a class="tile" href="/unidades/{u["slug"]}/"><h3>{esc(u["n"])}</h3><p>{esc(u["addr"])}<br/>{esc(u["phone"])}</p><small>{1+len(u["cities"])} localidades →</small></a>' for u in units if u['uf']==uf)
hero=f'<p class="kicker">Unidades</p><h1>{N_UNITS} unidades e bases <span>em três estados.</span></h1><p class="sub">Distribuição a partir de Regente Feijó (Presidente Prudente) para o oeste paulista, o norte do Paraná e o Mato Grosso do Sul. Toque em uma unidade para ver endereço, telefone, foto e cidades atendidas.</p>'
body=''.join(f'<section class="section-pad"><div class="wrap"><h2>{nm}</h2><div class="grid-3" style="margin-top:18px">{unit_tiles(uf)}</div></div></section>' for uf,nm in (("SP","São Paulo"),("PR","Paraná"),("MS","Mato Grosso do Sul")))
write('unidades',BASE+'/unidades/',"Unidades TAP Express | Endereços e telefones em SP, PR e MS",f"Endereços, telefones e cidades atendidas das {N_UNITS} unidades e bases da TAP Express em São Paulo, Paraná e Mato Grosso do Sul.",hero,body,[{"@context":"https://schema.org","@type":"CollectionPage","name":"Unidades TAP Express","url":BASE+"/unidades/"},bc(("TAP Express",BASE+"/"),("Unidades",BASE+"/unidades/"))],prio="0.9",crumbs=[("TAP Express","/"),("Unidades","/unidades/")])

# ---------------- CIDADES ----------------
cities=[]
for u in units:
    cities.append({"n":u['n'],"uf":u['uf'],"unit":u,"d":"","is_unit":True,"c":u['c']})
    for c in u['cities']:
        if slug(c['n'])!=slug(u['n']): cities.append({"n":c['n'],"uf":u['uf'],"unit":u,"d":c.get('d',''),"is_unit":False,"c":c['c']})
cities.sort(key=lambda x:(x['uf'],unicodedata.normalize('NFD',x['n'])))
import math
def km(a,b):
    R=6371; dlat=math.radians(b[1]-a[1]); dlng=math.radians(b[0]-a[0]); s=math.sin(dlat/2)**2+math.cos(math.radians(a[1]))*math.cos(math.radians(b[1]))*math.sin(dlng/2)**2; return 2*R*math.asin(math.sqrt(s))
for c in cities:
    u=c['unit']; s=slug(c['n']); url=f"{BASE}/cidades/{s}/"; c['slug']=s
    others=[x for x in u['cities'] if slug(x['n'])!=s][:12]
    dias_txt=dias(c['d'])
    if c['is_unit']: serv=f"{c['n']} tem uma unidade própria da TAP Express: {u['addr']}, telefone {u['phone']}. Atendimento em todos os dias úteis, com coleta e entrega no balcão da unidade."
    else: serv=f"{c['n']} é atendida pela unidade TAP Express de {u['n']} ({u['addr']}, telefone {u['phone']}), a {round(km(c['c'],u['c']))} km, com atendimento {('às ' + dias_txt) if c['d'] else 'em todos os dias úteis'}."
    fq=[(f"Qual unidade da TAP Express atende {c['n']}?",f"A unidade de {u['n']}, {u['uf']}: {u['addr']}, telefone {u['phone']}."+("" if c['is_unit'] else f" Fica a cerca de {round(km(c['c'],u['c']))} km.")),(f"Em que dias a TAP passa em {c['n']}?",("Todos os dias úteis, porque a cidade tem unidade própria." if c['is_unit'] else (f"Atendimento às {dias_txt}." if c['d'] else "Todos os dias úteis, em rota diária."))),(f"Qual o prazo de entrega para {c['n']}?","Até 18 horas a partir da coleta entre as cidades da rede, respeitando os dias de atendimento da cidade."),("Como peço uma cotação?","Pelo formulário em 3 passos no site, pela TAPIA ou pelo WhatsApp (18) 99109-6441. Você recebe um protocolo e a equipe responde em horário comercial.")]
    hero=f'<p class="kicker">{c["uf"]} · {"Unidade própria" if c["is_unit"] else "Cidade atendida via " + esc(u["n"])}</p><h1>Transportadora em <span>{esc(c["n"])}</span>, {c["uf"]}</h1><p class="sub">{esc(serv)} Encomendas expressas, malotes e cargas fracionadas com entrega em até 18 horas e seguro incluso.</p>'
    body=f'''<section><div class="wrap two-col"><div class="prose">
<div class="facts"><div><b>{("Diário" if (c["is_unit"] or not c["d"]) else esc(c["d"].upper().replace(",", "/")))}</b><span>atendimento</span></div><div><b>18h</b><span>prazo de entrega</span></div><div><b>{esc(u["phone"])}</b><span>unidade {esc(u["n"])}</span></div></div>
<h2>Como enviar de {esc(c['n'])} ou para {esc(c['n'])}</h2><ul><li>Peça a <a href="/cotacao/">cotação</a> informando {esc(c['n'])} como origem ou destino.</li><li>A unidade de {esc(u['n'])} confirma coleta{"" if c["is_unit"] else " no dia de atendimento da cidade"} ou você entrega no balcão.</li><li>Rastreie pelo <a href="/rastreamento/">portal</a> e receba o comprovante na entrega.</li></ul>
<h2>Serviços disponíveis</h2><ul>{"".join(f'<li><a href="/servicos/{s2}/">{esc(n2)}</a>: {esc(d2)}</li>' for s2,n2,d2,_,_ in SERV)}</ul>
{("<h2>Outras cidades atendidas pela unidade de " + esc(u["n"]) + "</h2><p>" + ", ".join(f'<a href="/cidades/{slug(x["n"])}/">{esc(x["n"])}</a>' for x in others) + ".</p>") if others else ""}
<h2>Perguntas frequentes</h2>{faq_html(fq)}</div>
<figure class="side-photo"><img src="{('/'+u['foto']) if u.get('foto') else '/assets/frota.jpg'}" alt="{esc('Unidade TAP Express em ' + u['n'])}" /><figcaption>Unidade TAP Express em {esc(u['n'])} · <a href="/unidades/{u['slug']}/" style="color:var(--green)">ver unidade</a></figcaption></figure></div></section>'''
    ld=[service_ld(f"Transporte de encomendas em {c['n']}",serv,url),{"@context":"https://schema.org","@type":"LocalBusiness","@id":f"{BASE}/unidades/{u['slug']}/#local","name":f"TAP Express · {u['n']}","url":f"{BASE}/unidades/{u['slug']}/","telephone":u['tel'],"address":{"@type":"PostalAddress","streetAddress":u['addr'],"addressLocality":u['n'],"addressRegion":u['uf'],"addressCountry":"BR"},"areaServed":{"@type":"City","name":c['n']}},bc(("TAP Express",BASE+"/"),("Cidades",BASE+"/cidades/"),(c['n'],url)),faq_ld(fq)]
    write(f'cidades/{s}',url,f"Transportadora em {c['n']} {c['uf']} | TAP Express",f"Transporte de encomendas, malotes e cargas em {c['n']}, {c['uf']}, pela unidade TAP Express de {u['n']}. {('Atendimento às ' + dias_txt + '. ') if c['d'] else ''}Entrega em até 18 horas e seguro incluso.",hero,body,ld,prio="0.6",crumbs=[("TAP Express","/"),("Cidades","/cidades/"),(c['n'],f"/cidades/{s}/")],og_image=(BASE+'/'+u['foto']) if u.get('foto') else BASE+'/assets/hero.jpg')
# índice de cidades
def city_links(uf): return ''.join(f'<a class="tile" href="/cidades/{c["slug"]}/"><h3>{esc(c["n"])}</h3><p>via {esc(c["unit"]["n"])}{(" · " + esc(c["d"].replace(",", "/"))) if c["d"] else ""}</p></a>' for c in cities if c['uf']==uf)
hero=f'<p class="kicker">Cidades atendidas</p><h1>{len(cities)} localidades <span>na rede TAP.</span></h1><p class="sub">Encontre sua cidade e veja qual unidade atende, em que dias e como pedir a cotação. Prefere o mapa? <a href="/#unidades" style="color:var(--green)">Abra o mapa interativo</a>.</p>'
body=''.join(f'<section class="section-pad"><div class="wrap"><h2>{nm}</h2><div class="grid-4" style="margin-top:18px">{city_links(uf)}</div></div></section>' for uf,nm in (("SP","São Paulo"),("PR","Paraná"),("MS","Mato Grosso do Sul")))
write('cidades',BASE+'/cidades/',"Cidades atendidas pela TAP Express em SP, PR e MS",f"Lista das {len(cities)} localidades atendidas pela TAP Express, com a unidade responsável e os dias de atendimento de cada cidade.",hero,body,[{"@context":"https://schema.org","@type":"CollectionPage","name":"Cidades atendidas","url":BASE+"/cidades/"},bc(("TAP Express",BASE+"/"),("Cidades",BASE+"/cidades/"))],prio="0.9",crumbs=[("TAP Express","/"),("Cidades","/cidades/")])

# ---------------- CARREIRAS ----------------
faq_c=[("Preciso de experiência para ser motorista na TAP?","Para caminhão baú e carreta pedimos CNH C, D ou E e experiência com carga. Para utilitários, CNH B e boa vontade de aprender: a unidade treina na rota."),("Como funciona a seleção?","Sua candidatura entra no painel da equipe de gente da TAP. Se o perfil combina com uma vaga, ligamos ou chamamos no WhatsApp para uma conversa na unidade mais próxima."),("Posso me candidatar sem vaga aberta?","Sim. Escolha \"Banco de talentos\" no formulário. Quando abrir uma vaga na sua região, a equipe entra em contato."),("As rotas são regionais?","Sim. A rede cobre o oeste paulista, o norte do Paraná e o Mato Grosso do Sul, com rotas diárias entre as unidades e as cidades atendidas.")]
hero='<p class="kicker">Carreiras · Trabalhe conosco</p><h1>Quem move a TAP <span>é gente da região.</span></h1><p class="sub">Motoristas, equipes de pátio, atendimento e comercial: são as pessoas das nossas 20 unidades que fazem a urgência virar entrega no prazo. Veja as vagas abertas ou deixe seu currículo no banco de talentos.</p>'
body=f'''<section class="section-pad"><div class="wrap two-col"><div class="prose">
<h2>Valorização de quem está na estrada</h2>
<p>Motorista na TAP não é só quem dirige. É quem representa a empresa na porta do cliente, conhece cada cidade da rota e cuida da carga como se fosse sua. Por isso a operação é pensada para quem dirige:</p>
<ul><li><strong>Rotas regionais e fixas.</strong> A rede cobre SP, PR e MS com rotas diárias entre unidades vizinhas. Você conhece o caminho, os clientes e volta para a sua base.</li><li><strong>Frota cuidada e rastreada.</strong> Caminhões revisados e monitorados via satélite, com apoio da central 24 horas em qualquer ponto da rota.</li><li><strong>Voz na operação.</strong> Quem está na estrada ajuda a desenhar a rota. Sugestão de motorista vira melhoria de processo.</li><li><strong>Crescimento por dentro.</strong> Ajudantes que viram motoristas, motoristas que viram líderes de pátio e de unidade. A preferência é sempre de quem já está na casa.</li></ul>
<h2>Cultura TAP</h2>
<p>Desde 2001 a TAP cresce com um jeito simples: cumprir o combinado, tratar bem quem está do outro lado do balcão e resolver o problema sem empurrar para o próximo. Nossos valores são <strong>inovação</strong>, <strong>rapidez com segurança</strong>, <strong>capital humano</strong> e <strong>responsabilidade social</strong>. Na prática isso quer dizer equipe pequena por unidade, chefia que conhece cada pessoa pelo nome e decisão rápida.</p>
<h2>Benefícios</h2>
<p>Os benefícios variam por vaga e por unidade e aparecem descritos em cada anúncio abaixo. Em todas as contratações CLT você encontra registro em carteira, salário em dia e os benefícios legais. As vagas de motorista informam também o modelo de remuneração e a rota.</p>
<h2>Perguntas frequentes</h2>{faq_html(faq_c)}
</div><figure class="side-photo"><img src="/assets/pessoas/motorista.jpg" alt="Motorista da TAP Express ao lado do caminhão no pátio da unidade" /><figcaption>Motorista da TAP no pátio da unidade</figcaption></figure></div></section>
<section class="section-pad" data-theme="light"><div class="wrap"><p class="kicker">Vagas abertas</p><h2>Oportunidades <span>agora.</span></h2><p class="sub">As vagas abaixo são publicadas pela equipe da TAP e atualizadas em tempo real.</p><div class="grid-3" id="vagasList" style="margin-top:24px"><div class="tile"><h3>Carregando vagas…</h3></div></div></div></section>
<section class="section-pad" id="candidatura"><div class="wrap two-col"><div class="prose"><p class="kicker">Candidatura</p><h2>Envie seu currículo</h2><p>Leva dois minutos. Se preferir, mande o currículo em PDF ou Word.</p>
<form id="candForm" class="qf" style="margin-top:18px" enctype="multipart/form-data" novalidate>
<div class="hp"><label>Website</label><input type="text" name="website" tabindex="-1" autocomplete="off" /></div>
<div class="full"><label>Vaga</label><select name="vaga_id" id="cVaga"><option value="">Banco de talentos (sem vaga específica)</option></select></div>
<div><label>Nome completo</label><input name="nome" required autocomplete="name" /></div><div><label>WhatsApp / telefone</label><input name="telefone" type="tel" required placeholder="(18) 99999-9999" /></div>
<div><label>E-mail</label><input name="email" type="email" autocomplete="email" /></div><div><label>Cidade onde mora</label><input name="cidade" required placeholder="Ex.: Presidente Prudente" /></div>
<div><label>Categoria da CNH</label><select name="cnh"><option value="">Não tenho / não se aplica</option><option>B</option><option>C</option><option>D</option><option>E</option></select></div><div><label>Experiência na área</label><select name="experiencia"><option>Sem experiência</option><option>Até 1 ano</option><option>1 a 3 anos</option><option>3 a 5 anos</option><option>Mais de 5 anos</option></select></div>
<div class="full"><label>Currículo (PDF ou Word, até 5 MB)</label><input name="cv" type="file" accept=".pdf,.doc,.docx" /></div>
<div class="full"><label>Conte um pouco sobre você</label><textarea name="mensagem" rows="3" placeholder="Rotas que já fez, tipo de veículo, disponibilidade, o que procura."></textarea></div>
<div class="full" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"><button class="btn btn-solid" type="submit">Enviar candidatura</button><span id="candMsg" style="color:#ff9b8d;font-size:13px"></span></div>
</form>
<div id="candDone" hidden class="tile" style="margin-top:18px"><h3>Candidatura recebida. Obrigado!</h3><p>A equipe da TAP analisa o perfil e entra em contato pelo WhatsApp ou e-mail informado. Boa sorte!</p></div>
</div><figure class="side-photo"><img src="/assets/pessoas/equipe.jpg" alt="Equipe da TAP Express em frente à frota no pátio da unidade" /><figcaption>Equipe da TAP no pátio</figcaption></figure></div></section>'''
write('carreiras',BASE+'/carreiras/',"Trabalhe conosco | Vagas para motoristas e equipe | TAP Express","Vagas abertas na TAP Express para motoristas, operação, atendimento e comercial em SP, PR e MS. Rotas regionais, frota rastreada e crescimento por dentro. Envie seu currículo.",hero,body,[{"@context":"https://schema.org","@type":"WebPage","name":"Carreiras TAP Express","url":BASE+"/carreiras/","about":ORG},bc(("TAP Express",BASE+"/"),("Carreiras",BASE+"/carreiras/")),faq_ld(faq_c)],prio="0.8",freq="weekly",crumbs=[("TAP Express","/"),("Carreiras","/carreiras/")],og_image=BASE+"/assets/pessoas/equipe.jpg",extra_js=f'<script src="/carreiras.js?v={V}"></script>')

json.dump(PAGES,open('data/pages.json','w',encoding='utf-8'),ensure_ascii=False)
print(f"páginas geradas: {len(PAGES)}")
