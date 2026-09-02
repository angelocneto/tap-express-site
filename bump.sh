#!/usr/bin/env bash
# Atualiza o ?v= dos CSS/JS locais no index.html (cache busting). Rode antes de cada commit/deploy.
cd "$(dirname "$0")"
V=$(date +%Y%m%d%H%M)
sed -i '' -E "s/((href|src)=\"(styles|theme|map|cotacao)\.(css|js)|src=\"(app|map|cotacao)\.js|src=\"data\/(rede|estados)\.js)(\?v=[0-9]+)?\"/\1?v=$V\"/g" index.html
grep -oE '(styles|theme|map|cotacao|app|rede|estados)\.(css|js)\?v=[0-9]+' index.html | sort -u
