<?php
// TAP Express — captura de leads do site (rastreamento e futuras ações). Grava no banco interno com aceite LGPD.
declare(strict_types=1);
require_once dirname(__DIR__) . '/atendimento/db.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
function out(int $code, array $b): void { http_response_code($code); echo json_encode($b, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'erro' => 'Método não permitido']);
$d = json_decode(file_get_contents('php://input') ?: '', true); if (!is_array($d)) $d = $_POST;
if (!empty($d['website'])) out(200, ['ok' => true]); // honeypot
$s = fn($k, $max) => mb_substr(trim(strip_tags((string)($d[$k] ?? ''))), 0, $max);
$nome = $s('nome', 120); $email = strtolower($s('email', 160)); $wa = preg_replace('/\D+/', '', $s('whatsapp', 25)); $origem = $s('origem', 40) ?: 'site'; $ctx = $s('contexto', 200);
if ($nome === '' || strlen($wa) < 10 || strlen($wa) > 13) out(422, ['ok' => false, 'erro' => 'Informe seu nome e um WhatsApp válido com DDD.']);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) out(422, ['ok' => false, 'erro' => 'E-mail inválido.']);
if (empty($d['aceite'])) out(422, ['ok' => false, 'erro' => 'É preciso aceitar receber as comunicações.']);
if (strlen($wa) <= 11) $wa = '55' . $wa;
try {
    $pdo = tap_db(); $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo->prepare('DELETE FROM rate WHERE ts < ?')->execute([time() - 3600]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM rate WHERE ip = ? AND ts > ?'); $q->execute(["lead:$ip", time() - 600]);
    if ((int)$q->fetchColumn() >= 6) out(429, ['ok' => false, 'erro' => 'Muitos envios em pouco tempo. Tente mais tarde.']);
    $pdo->prepare('INSERT INTO rate (ip, ts) VALUES (?, ?)')->execute(["lead:$ip", time()]);
    // mesmo WhatsApp em 24 h: atualiza em vez de duplicar
    $q = $pdo->prepare('SELECT id FROM leads WHERE whatsapp = ? AND criado_em > ?'); $q->execute([$wa, date('Y-m-d H:i:s', time() - 86400)]); $ex = $q->fetchColumn();
    if ($ex) $pdo->prepare('UPDATE leads SET nome = ?, email = COALESCE(NULLIF(?, ""), email), contexto = ? WHERE id = ?')->execute([$nome, $email, $ctx, $ex]);
    else $pdo->prepare('INSERT INTO leads (nome, whatsapp, email, origem, contexto, aceite, aceite_em, ip, user_agent, status, criado_em) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, "novo", ?)')->execute([$nome, $wa, $email, $origem, $ctx, tap_now(), $ip, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250), tap_now()]);
    out(200, ['ok' => true]);
} catch (Throwable $e) { error_log('lead.php: ' . $e->getMessage()); out(500, ['ok' => false, 'erro' => 'Não foi possível registrar agora.']); }
