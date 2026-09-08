"""Aplica o WhatsApp de cada unidade (data/whatsapp.json, por slug) em data/rede.js. Rode depois de regenerar rede.js."""
import json, re, io
src = open('data/rede.js', encoding='utf-8').read()
m = re.search(r'window\.TAP_REDE = (\{.*\});', src, re.S)
rede = json.loads(m.group(1)); wa = json.load(open('data/whatsapp.json', encoding='utf-8'))
n = 0
for u in rede['units']:
    if u['slug'] in wa: u['wa'] = wa[u['slug']]; n += 1
    else: u.pop('wa', None)
open('data/rede.js', 'w', encoding='utf-8').write('window.TAP_REDE = ' + json.dumps(rede, ensure_ascii=False) + ';')
print(f'wa aplicado em {n} unidades')
