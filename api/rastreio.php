<?php
// TAP Express — rastreamento embutido: consulta o portal SSW no servidor e devolve o resultado limpo (JSON).
// Modo único, igual ao "Rastreamento pelo remetente" do SSW: CNPJ/CPF do remetente + números das notas (ou pedidos/coletas) + senha. Os três são obrigatórios.
declare(strict_types=1);
require_once dirname(__DIR__) . '/atendimento/db.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
function out(int $code, array $b): void { http_response_code($code); echo json_encode($b, JSON_UNESCAPED_UNICODE); exit; }

$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST) : $_GET;
$cnpj = preg_replace('/\D+/', '', (string)($in['cnpj'] ?? ''));
$senha = mb_substr((string)($in['senha'] ?? ''), 0, 58);
// notas: aceita vários números separados por vírgula, ponto e vírgula, espaço ou quebra de linha (máx. 20)
$notas = array_values(array_unique(array_filter(array_map(fn($n) => preg_replace('/\D+/', '', $n), preg_split('/[\s,;]+/u', (string)($in['notas'] ?? $in['nf'] ?? ''))), fn($n) => $n !== '' && strlen($n) <= 15)));
$notas = array_slice($notas, 0, 20);
if (!empty($in['website'])) out(200, ['ok' => false, 'erro' => 'Nada encontrado.']); // honeypot

try {
    $pdo = tap_db(); $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $pdo->prepare('DELETE FROM rate WHERE ts < ?')->execute([time() - 3600]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM rate WHERE ip = ? AND ts > ?'); $q->execute(["rastreio:$ip", time() - 600]);
    if ((int)$q->fetchColumn() >= 30) out(429, ['ok' => false, 'erro' => 'Muitas consultas em pouco tempo. Aguarde alguns minutos.']);
    $pdo->prepare('INSERT INTO rate (ip, ts) VALUES (?, ?)')->execute(["rastreio:$ip", time()]);
} catch (Throwable $e) { /* sem banco, segue sem limite */ }

function ssw_get(string $url, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TAPExpress-Rastreio/1.0; +https://www.tapexpress.com.br)', CURLOPT_HTTPHEADER => ['Accept: text/html,text/csv', 'Accept-Language: pt-BR']]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) return [0, ''];
    if (!mb_check_encoding($body, 'UTF-8')) $body = mb_convert_encoding($body, 'UTF-8', 'ISO-8859-1');
    return [$code, $body];
}
$txt = fn($x) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/iu', ' | ', $x)), ENT_QUOTES, 'UTF-8')));

// ---------- histórico detalhado de uma encomenda (id/md vêm da listagem) ----------
if (!empty($in['detalhe_id']) && !empty($in['detalhe_md'])) {
    $did = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$in['detalhe_id']); $dmd = preg_replace('/[^A-Za-z0-9+\/=_\-]/', '', (string)$in['detalhe_md']);
    [$code, $html] = ssw_get('https://ssw.inf.br/2/SSWDetalhado?id=' . $did . '&md=' . rawurlencode($dmd));
    if ($code === 0 || $code >= 500) out(502, ['ok' => false, 'erro' => 'O portal não respondeu.']);
    $main = preg_match('/<!--content-->(.*)<!--content-->/su', $html, $m) ? $m[1] : $html;
    $ev = [];
    if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/su', $main, $trs)) foreach ($trs[1] as $tr) {
        if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/su', $tr, $tds) || count($tds[1]) < 3) continue;
        $c = array_map($txt, $tds[1]); if (preg_match('/^Data\/Hora/u', $c[0]) || trim(implode('', $c)) === '') continue;
        $data = ''; $hora = ''; if (preg_match('/(\d{2}\/\d{2}\/\d{2,4})\s*\|?\s*(\d{2}:\d{2})?/u', $c[0], $d)) { $data = $d[1]; $hora = $d[2] ?? ''; }
        $sit = ''; $desc = $c[2]; if (preg_match('/<p class=titulo>(.*?)<\/p>(.*)$/su', $tds[1][2], $pm)) { $sit = trim(preg_replace('/\s*(GPS|Foto|Comprovante de Entrega)\b/u', '', $txt($pm[1]))); $desc = trim(preg_replace('/\s*Comprovante de Entrega\s*$/u', '', $txt($pm[2]))); }
        $ev[] = ['data' => $data, 'hora' => $hora, 'unidade' => trim(explode('|', $c[1])[0]), 'descricao' => $sit ?: $desc, 'detalhe' => $sit ? $desc : ''];
    }
    $links = []; if (preg_match('/href=\'(comprovante\?[^\']+)\'/u', $main, $cm)) $links['comprovante'] = 'https://ssw.inf.br/2/' . html_entity_decode($cm[1]);
    if (preg_match('/href=\'(https:\/\/ssw\.inf\.br\/cgi-local\/ssw1188\?id=[^\']+)\'>DACTE/u', $main, $dm)) $links['dacte'] = $dm[1];
    $cab = []; foreach (['Remetente', 'Destinatário', 'N Fiscal'] as $k) if (preg_match('/' . preg_quote($k, '/') . ':\s*<\/[^>]+>\s*<[^>]+>([^<]*)/u', $main, $km)) $cab[$k] = trim($km[1]);
    out(200, ['ok' => count($ev) > 0, 'modo' => 'detalhe', 'cabecalho' => $cab, 'eventos' => array_reverse($ev), 'links' => $links, 'fonte' => 'SSW', 'consultado_em' => date('d/m/Y H:i')]);
}


if (!in_array(strlen($cnpj), [11, 14], true) || !$notas || $senha === '') out(422, ['ok' => false, 'erro' => 'Informe o CNPJ ou CPF do remetente, o número da nota fiscal e a senha de rastreio.']);

// mesma consulta do portal (POST /2/resultSSW): cnpj, NR (uma nota por linha, CRLF) e chave = senha
[$code, $html] = ssw_get('https://ssw.inf.br/2/resultSSW', ['cnpj' => $cnpj, 'NR' => implode("\r\n", $notas), 'chave' => $senha]);
if ($code === 0 || $code >= 500) out(502, ['ok' => false, 'erro' => 'O portal de rastreamento não respondeu. Tente de novo em instantes.']);
$main = preg_match('/<!--content-->(.*)<!--content-->/su', $html, $m) ? $m[1] : $html;
$quem = ''; if (preg_match('/Remetente:<\/span>.*?<span[^>]*>([^<]*)<\/span>\s*<span[^>]*>([^<]*)<\/span>/su', $main, $q)) $quem = trim(trim($q[2]) . ' · ' . trim($q[1]), ' ·');
$mascarado = str_contains($main, '**.***') || str_contains($quem, '*');
$enc = []; $naoDisp = [];
if (preg_match_all('/<tr[^>]*?(?:onclick="opx\(\'([^\']*)\'\)")?[^>]*>(.*?)<\/tr>/su', $main, $rows, PREG_SET_ORDER)) foreach ($rows as $r) {
    $inner = $r[2];
    if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/su', $inner, $tds) || count($tds[1]) < 3) continue;
    $c = array_map($txt, $tds[1]); if (preg_match('/Fiscal|Coleta/u', $c[0]) && preg_match('/Situa/u', $c[2])) continue; if (trim(implode('', $c)) === '') continue;
    if (preg_match('/Informa\S{0,3}o n\S{0,3}o dispon/iu', $c[2])) { $naoDisp[] = trim(str_replace('|', ' ', $c[0])); continue; }
    $data = ''; $hora = ''; $unid = $c[1]; if (preg_match('/(\d{2}\/\d{2}\/\d{2,4})\s*\|?\s*(\d{2}:\d{2})?/u', $c[1], $d)) { $data = $d[1]; $hora = $d[2] ?? ''; $unid = trim(str_replace([$d[0], '|'], '', $c[1])); }
    $sit = ''; $desc = $c[2]; if (preg_match('/<p class=titulo>(.*?)<\/p>(.*)$/su', $tds[1][2], $pm)) { $sit = preg_replace('/\s*\([A-Z0-9 ]{5,}\)$/u', '', $txt(preg_replace('/<font.*?<\/font>/su', '', $pm[1]))); $desc = $txt(preg_replace('/Mais detalhes/u', '', $pm[2])); }
    $link = ''; if (preg_match('/opx\(\'\/2\/(?:ssw_)?SSWDetalhado\?id=([^&\']+)&md=([^\']+)\'\)/u', $r[0], $lk)) $link = $lk[1] . '|' . $lk[2];
    $nfp = array_map('trim', explode('|', $c[0]));
    $enc[] = ['nf' => $nfp[0], 'pedido' => $nfp[1] ?? '', 'unidade' => $unid, 'data' => $data, 'hora' => $hora, 'situacao' => $sit ?: $desc, 'detalhe' => $sit ? $desc : '', 'link' => $link];
}
// enriquecimento pelo CSV do próprio resultado: remetente, destinatário, destino, previsão, entrega, CTRC, chave do CT-e
if ($enc && preg_match('/href="(\/2\/resultSSW\?cnpj=[^"]*output=csv[^"]*)"/u', $main, $cl)) {
    [$c3, $csv] = ssw_get('https://ssw.inf.br' . html_entity_decode($cl[1]));
    if ($c3 === 200 && stripos($csv, 'CNPJ/CPF Remetente') === 0) {
        $linhas = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $csv)))); $head = array_map('trim', str_getcsv(array_shift($linhas), ';')); $byNf = [];
        foreach ($linhas as $ln) { $cols = str_getcsv($ln, ';'); if (count($cols) < 10) continue; $row = []; foreach ($head as $i => $k) $row[$k] = trim((string)($cols[$i] ?? '')); $byNf[preg_replace('/\D+/', '', $row['Nota Fiscal/Nro Coleta'] ?? '')][] = $row; if (!$quem && !empty($row['Remetente'])) $quem = $row['Remetente'] . ' · ' . ($row['CNPJ/CPF Remetente'] ?? ''); }
        foreach ($enc as &$e) { $rows = $byNf[preg_replace('/\D+/', '', $e['nf'])] ?? []; $row = $rows[0] ?? null; foreach ($rows as $cand) if (($cand['Data/Hora da Ocorrencia'] ?? '') === trim($e['data'] . ' ' . $e['hora'])) { $row = $cand; break; }
            if ($row) { $e['destinatario'] = $row['Destinatario'] ?? ''; $e['ctrc'] = $row['CTRC'] ?? ''; $e['destino'] = trim(($row['Cidade Destino'] ?? '') . ' / ' . ($row['UF Destino'] ?? ''), ' /'); $e['previsao'] = $row['Previsao de Entrega'] ?? ''; $e['entrega'] = $row['Data Entrega'] ?? ''; $e['inclusao'] = $row['Data Inclusao'] ?? ''; $e['cte'] = preg_replace('/\D/', '', $row['CTe'] ?? ''); if (!$e['pedido']) $e['pedido'] = $row['Nro Pedido'] ?? ''; } }
        unset($e);
    }
}
$motivo = '';
if (!$enc) $motivo = $mascarado ? 'CNPJ ou senha não conferem. Confira os dados fornecidos pela TAP.' : 'Nenhuma encomenda encontrada para esses dados. Confira o CNPJ, o número da nota e a senha de rastreio.';
out(200, ['ok' => count($enc) > 0, 'modo' => 'remetente', 'quem' => $quem, 'motivo' => $motivo, 'total' => count($enc), 'nao_encontradas' => $naoDisp, 'encomendas' => $enc, 'eventos' => [], 'html' => '', 'fonte' => 'SSW', 'consultado_em' => date('d/m/Y H:i')]);
