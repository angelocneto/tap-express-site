# Site TAP Express — scroll cinematográfico

Site estático (HTML + CSS + JS + Lenis). Não precisa de build.

## Rodar localmente
Dê dois cliques em `Abrir site local.command` (abre http://localhost:8090) ou rode:

    python3 -m http.server 8090

## Publicar no HostGator (subdomínio tap.amodesenvolvimento.com.br)
1. cPanel → **Gerenciador de Arquivos** → entre na pasta raiz do subdomínio
   (normalmente `public_html/tap.amodesenvolvimento.com.br` ou `public_html/tap`).
2. **Carregar** o arquivo `tap-express-site.zip`.
3. Clique com o botão direito no zip → **Extract** (extrair na própria pasta).
4. Confirme que `index.html` ficou na raiz do subdomínio (não dentro de uma subpasta).
5. Apague o zip. Acesse https://tap.amodesenvolvimento.com.br.

## Estrutura
- `index.html` — conteúdo e config das cenas (`SCRUB_SECTIONS` no fim do arquivo)
- `styles.css` — identidade (verde #39b54a) e layout
- `app.js` — engine de scroll (canvas frame scrub + Lenis + reveals)
- `frames/hero` (179 quadros) e `frames/frota` (180 quadros) — clipes fatiados
- `assets/` — logo, favicon, fotos da frota, vídeo da Tap.IA

## Como trocar um clipe
    ffmpeg -i novo.mp4 -vf fps=30 -q:v 3 frames/hero/frame_%04d.jpg
Depois ajuste `frameCount` em `SCRUB_SECTIONS` no `index.html`.
