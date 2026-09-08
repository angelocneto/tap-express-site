<?php
// TAP Express — rastreamento embutido: consulta o portal SSW no servidor e devolve o resultado limpo (JSON).
// Aceita: danfe (chave da NF-e, 44 dígitos) OU cnpj (remetente/destinatário/pagador) + chave.
declare(strict_types=1);
require_once dirname(__DIR__) . '/atendimento/db.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
function out(int $code, array $b): void { http_response_code($code); echo json_encode($b, JSON_UNESCAPED_UNICODE); exit; }

$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST) : $_GET;
$danfe = preg_replace('/\D+/', '', (string)($in['danfe'] ?? ''));
$cnpj = preg_replace('/\D+/', '', (string)($in['cnpj'] ?? ''));
$chave = mb_substr(preg_replace('/[^\w\-\/.]/u', '', (string)($in['chave'] ?? '')), 0, 58);
$senha = mb_substr((string)($in['senha'] ?? ''), 0, 58);
if (!empty($in['website'])) out(200, ['ok' => false, 'erro' => 'Nada encontrado.']); // honeypot

try {
    $pdo = tap_db(); $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $pdo->prepare('DELETE FROM rate WHERE ts < ?')->execute([time() - 3600]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM rate WHERE ip = ? AND ts > ?'); $q->execute(["rastreio:$ip", time() - 600]);
    if ((int)$q->fetchColumn() >= 30) out(429, ['ok' => false, 'erro' => 'Muitas consultas em pouco tempo. Aguarde alguns minutos.']);
    $pdo->prepare('INSERT INTO rate (ip, ts) VALUES (?, ?)')->execute(["rastreio:$ip", time()]);
} catch (Throwable $e) { /* sem banco, segue sem limite */ }

if (strlen($danfe) === 44) { $url = 'https://ssw.inf.br/2/rastreamento_danfe'; $post = ['urlori' => '', 'danfe' => $danfe]; $modo = 'danfe'; }
elseif (strlen($cnpj) === 14 && $chave !== '') { $url = 'https://ssw.inf.br/2/resultSSW'; $post = ['cnpj' => $cnpj, 'chave' => $chave]; $modo = 'chave'; }
elseif (strlen($cnpj) === 14 && $senha !== '') { $url = 'https://ssw.inf.br/2/resultSSW'; $post = ['cnpj' => $cnpj, 'chave' => $senha, 'pwd' => '1']; $modo = 'senha'; } // o SSW valida a senha do remetente no campo chave
else out(422, ['ok' => false, 'erro' => 'Informe a chave da NF-e (44 números), ou o CNPJ com a chave de rastreio, ou o CNPJ com a senha de remetente.']);

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post), CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TAPExpress-Rastreio/1.0; +https://www.tapexpress.com.br)', CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: pt-BR']]);
$html = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch);
if ($html === false || $code >= 500) out(502, ['ok' => false, 'erro' => 'O portal de rastreamento não respondeu. Tente de novo em instantes.', 'detalhe' => $cerr]);
if (!mb_check_encoding($html, 'UTF-8')) $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');

// recorta o bloco de conteúdo do SSW (entre os marcadores <!--content-->)
$main = $html;
if (preg_match('/<!--content-->(.*)<!--content-->/su', $html, $m)) $main = $m[1];
elseif (preg_match('/<body[^>]*>(.*)<\/body>/su', $html, $m)) $main = $m[1];
$main = preg_replace('/<(script|style|form|select|button|svg|iframe|noscript)\b.*?<\/\1>/su', ' ', $main);
$txt = fn($x) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' | ', $x)), ENT_QUOTES, 'UTF-8')));

// cabeçalho: remetente/destinatário e a tabela de ocorrências (N Fiscal | Unidade + Data/hora | Situação)
$quem = ''; if (preg_match('/<span[^>]*>\s*(Remetente|Destinat[aá]rio|Pagador):\s*<\/span>.*?<span[^>]*>([^<]*)<\/span>\s*<span[^>]*>([^<]*)<\/span>/su', $main, $q)) $quem = trim($q[1] . ': ' . trim($q[2]) . ' ' . trim($q[3]));
$eventos = []; $cabecalho = false;
if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/su', $main, $trs)) {
    foreach ($trs[1] as $tr) {
        if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/su', $tr, $tds) || count($tds[1]) < 3) continue;
        $c = array_map($txt, $tds[1]);
        if (preg_match('/Situa/u', $c[2]) && preg_match('/Fiscal|Coleta/u', $c[0])) { $cabecalho = true; continue; }
        if (!$cabecalho || trim(implode('', $c)) === '') continue;
        $data = ''; $hora = ''; $unid = $c[1];
        if (preg_match('/(\d{2}\/\d{2}\/\d{2,4})\s*(\d{2}:\d{2})?/u', $c[1], $d)) { $data = $d[1]; $hora = $d[2] ?? ''; $unid = trim(str_replace([$d[0], '|'], ['', ''], $c[1])); }
        $nf = trim(str_replace('|', ' · ', $c[0]));
        $eventos[] = ['data' => $data, 'hora' => $hora, 'descricao' => $c[2], 'detalhe' => trim(($unid ? $unid : '') . ($nf ? ' · NF/Coleta ' . $nf : ''), ' ·'), 'unidade' => $unid, 'nf' => $nf];
    }
}
// mais recente primeiro
usort($eventos, function ($x, $y) { $k = fn($e) => preg_replace('/(\d{2})\/(\d{2})\/(\d{2,4})/', '$3$2$1', $e['data']) . str_replace(':', '', $e['hora']); return strcmp($k($y), $k($x)); });

// fallback: HTML limpo do bloco (sem atributos), útil quando o layout do SSW mudar
$clean = strip_tags(preg_replace('/<(input|img|hr)\b[^>]*>/iu', '', $main), '<table><tr><td><th><p><b><strong><br><div><span><ul><li><h1><h2><h3><h4>');
$clean = preg_replace('/<(\w+)\s+[^>]*>/u', '<$1>', $clean); $clean = preg_replace('/\s{2,}/u', ' ', $clean);
foreach (['Digite ou capture-a com leitor de código de barras.', 'Rastreamento das mercadorias transportadas por todas as transportadoras usuárias do SSW podem ser realizadas por até 90 dias após ocorrida a entrega.', 'As informações resultantes são anonimizadas, conforme LGPD.', 'Download em CSV', 'Voltar', 'Para clientes'] as $mn) $clean = str_ireplace($mn, '', $clean);
$clean = preg_replace('/<(div|span|p|li|td|tr|table|ul|b|strong)>\s*(&nbsp;|\s)*<\/\1>/u', '', $clean); $clean = preg_replace('/<(div|span|p|li|td|tr|table|ul|b|strong)>\s*(&nbsp;|\s)*<\/\1>/u', '', $clean);
$txtLower = mb_strtolower($txt($main));
$formDeNovo = (bool)preg_match('/digite ou capture|chave da nf-e|name="danfe"|id="chave"/iu', $txtLower . ' ' . mb_strtolower($html));
$naoEncontrado = $formDeNovo || !$eventos && (bool)preg_match('/n[aã]o (foi )?encontrad|nenhum (registro|resultado)|inv[aá]lid|n[aã]o localiz|sem informa/u', $txtLower);
$motivo = ''; $mascarado = str_contains($quem, '*');
if ($mascarado && !$eventos) { $clean = ''; $naoEncontrado = true; $motivo = 'CNPJ ou senha não reconhecidos pelo portal.'; }
elseif ($formDeNovo) { $clean = ''; $naoEncontrado = true; $motivo = $modo === 'danfe' ? 'Chave não localizada no portal.' : 'CNPJ ou chave/senha não reconhecidos pelo portal.'; }
elseif (!$eventos && $cabecalho) { $clean = ''; $naoEncontrado = true; $motivo = $modo === 'senha' ? 'Nenhuma encomenda deste remetente nos últimos 30 dias no portal, ou a senha não confere.' : 'Nenhuma ocorrência registrada para esta chave.'; }
out(200, ['ok' => !$naoEncontrado, 'modo' => $modo, 'quem' => $quem, 'motivo' => $motivo, 'eventos' => $eventos, 'html' => mb_substr($clean, 0, 20000), 'fonte' => 'SSW', 'consultado_em' => date('d/m/Y H:i')]);
