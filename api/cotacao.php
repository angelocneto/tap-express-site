<?php
// TAP Express — recebe cotações do site (JSON) e grava no banco.
declare(strict_types=1);
require_once dirname(__DIR__) . '/atendimento/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(int $code, array $body): void { http_response_code($code); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'erro' => 'Método não permitido']);

$raw = file_get_contents('php://input');
$d = json_decode($raw ?: '', true);
if (!is_array($d)) out(400, ['ok' => false, 'erro' => 'JSON inválido']);
if (!empty($d['website'])) out(200, ['ok' => true, 'protocolo' => 'TAP-0000-00000']); // honeypot

$s = fn($k, $max = 300) => isset($d[$k]) ? mb_substr(trim(strip_tags((string)$d[$k])), 0, $max) : '';
$f = fn($k) => isset($d[$k]) && $d[$k] !== '' ? (float)str_replace(',', '.', (string)$d[$k]) : null;

$nome = $s('nome', 120); $email = $s('email', 160); $tel = $s('telefone', 40); $origem = $s('origem', 120); $destino = $s('destino', 120);
if ($nome === '' || $tel === '' || $origem === '' || $destino === '') out(422, ['ok' => false, 'erro' => 'Preencha nome, telefone, origem e destino.']);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) out(422, ['ok' => false, 'erro' => 'E-mail inválido.']);

try {
    $pdo = tap_db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo->prepare('DELETE FROM rate WHERE ts < ?')->execute([time() - 600]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM rate WHERE ip = ?'); $q->execute([$ip]);
    if ((int)$q->fetchColumn() >= 8) out(429, ['ok' => false, 'erro' => 'Muitas solicitações. Tente novamente em alguns minutos ou ligue (18) 3918-7777.']);
    $pdo->prepare('INSERT INTO rate (ip, ts) VALUES (?, ?)')->execute([$ip, time()]);

    $protocolo = tap_protocolo($pdo); $now = tap_now();
    $st = $pdo->prepare('INSERT INTO cotacoes (protocolo, criado_em, atualizado_em, status, origem, destino, tipo, volumes, peso, dimensoes, valor_mercadoria, nome, empresa, email, telefone, observacoes, origem_pagina, ip, user_agent)
        VALUES (?, ?, ?, "novo", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([$protocolo, $now, $now, $origem, $destino, $s('tipo', 40), isset($d['volumes']) ? (int)$d['volumes'] : null, $f('peso'), $s('dimensoes', 120), $f('valor'),
        $nome, $s('empresa', 120), $email, $tel, $s('observacoes', 2000), $s('pagina', 200), $ip, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250)]);

    $cfg = __DIR__ . '/config.php'; $notify = '';
    if (file_exists($cfg)) { $c = include $cfg; $notify = is_array($c) ? ($c['notificar_email'] ?? '') : ''; }
    if ($notify) {
        $body = "Nova cotação $protocolo\n\nNome: $nome\nEmpresa: {$s('empresa')}\nTelefone: $tel\nE-mail: $email\nOrigem: $origem\nDestino: $destino\nTipo: {$s('tipo')}\nVolumes: " . ($d['volumes'] ?? '') . "\nPeso: " . ($d['peso'] ?? '') . " kg\nValor: " . ($d['valor'] ?? '') . "\n\nObs: {$s('observacoes', 2000)}\n\nAcesse a área de atendimento para responder.";
        @mail($notify, "[TAP Express] Nova cotação $protocolo — $nome", $body, "From: site@" . ($_SERVER['HTTP_HOST'] ?? 'tapexpress.com.br') . "\r\nContent-Type: text/plain; charset=utf-8");
    }
    out(200, ['ok' => true, 'protocolo' => $protocolo]);
} catch (Throwable $e) {
    error_log('cotacao.php: ' . $e->getMessage());
    out(500, ['ok' => false, 'erro' => 'Não foi possível registrar agora. Ligue (18) 3918-7777.']);
}
