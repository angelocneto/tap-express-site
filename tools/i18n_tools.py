#!/usr/bin/env python3
"""Apoio à tradução: `todo` lista chaves numeradas ainda sem tradução; `merge <lang> <arquivo>` lê linhas "NNN<TAB>texto" e grava em i18n/<lang>.json."""
import json, re, sys
S = json.load(open("i18n/strings.json", encoding="utf-8")); KEYS = list(S)
rede = json.loads(re.search(r'window\.TAP_REDE = (\{.*\});', open('data/rede.js', encoding='utf-8').read(), re.S).group(1))
UNITS = {u["n"] for u in rede["units"]}
EXACT = {"SP","PR","MS","B","C","D","E","×","TAP.IA","TAP-0000-00000","Web","BusinessApplication","customer service","sales","TAP Express","TAP Transportes","Tap Express Transportes","Paulo Barreto","Instagram","Facebook","WhatsApp","<0/>WhatsApp","<0/>Google Maps","<0/>Waze","Website","recepcao@taptransportes.com.br","voce@empresa.com.br","Ex.: 12,5","Menu","TAPIA","PT","EN","ES","中文","Portuguese","English","Spanish","Chinese","Instagram @tap.transportes","WhatsApp (18) 99109-6441","LAT <0>-22.218</0> · LNG <1>-51.305</1>","JSON inválido","Método não permitido","Ourinhos","Regente Feijó","Frota TAP Express","TAP Express · Andradina"}
def keep(k):
    if k in EXACT or k in UNITS: return True
    if re.match(r"^(Rua|Av\.|R\.|Rod\.|Avenida) ", k) and "<0>" not in k: return True
    if re.match(r"^TAP Express · ", k): return True
    if re.match(r"^\(?\d[\d\s().-]*$", k): return True
    return False
def todo(lang=None):
    tr = json.load(open(f"i18n/{lang}.json", encoding="utf-8")) if lang else {}
    n = 0
    for i, k in enumerate(KEYS):
        if keep(k) or k in tr: continue
        print(f"{i:03d}\t{k}"); n += 1
    print(f"# {n} para traduzir", file=sys.stderr)
def merge(lang, path):
    f = f"i18n/{lang}.json"
    try: tr = json.load(open(f, encoding="utf-8"))
    except FileNotFoundError: tr = {}
    bad = 0
    for line in open(path, encoding="utf-8"):
        line = line.rstrip("\n")
        if not line.strip() or line.startswith("#"): continue
        mk = re.match(r"^K\|(.*?)\|(.*)$", line)
        if mk: tr[mk.group(1)] = mk.group(2).strip(); continue
        m = re.match(r"^(\d{3})[\t|] ?(.*)$", line)
        if not m: print("linha inválida:", line[:80]); bad += 1; continue
        i, v = int(m.group(1)), m.group(2).strip()
        k = KEYS[i]
        # placeholders devem bater
        ph = lambda s: sorted(re.findall(r"<(/?\d+/?)>", s))
        if ph(k) != ph(v): print(f"placeholders diferentes em {i}: {k[:60]} -> {v[:60]}"); bad += 1; continue
        for tok in re.findall(r"\{[a-z0-9]+\}", k):
            if tok not in v: print(f"falta {tok} em {i}"); bad += 1
        tr[k] = v
    for k in KEYS:
        if keep(k): tr[k] = k
    json.dump(tr, open(f, "w", encoding="utf-8"), ensure_ascii=False, indent=0)
    print(f"{lang}: {len(tr)} traduções, {bad} problemas")
if __name__ == "__main__":
    if sys.argv[1] == "todo": todo(sys.argv[2] if len(sys.argv) > 2 else None)
    else: merge(sys.argv[2], sys.argv[3])
