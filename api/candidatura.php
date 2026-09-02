<?php
// Recebe candidaturas (multipart) com currículo opcional em PDF.
declare(strict_types=1);
require_once dirname(__DIR__) . '/atendimento/db.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
function out(int $c, array $b): void { http_response_code($c); echo json_encode($b, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'erro' => 'Método não permitido']);
if (!empty($_POST['website'])) out(200, ['ok' => true]);
$s = fn($k, $max = 300) => isset($_POST[$k]) ? mb_substr(trim(strip_tags((string)$_POST[$k])), 0, $max) : '';
$nome = $s('nome', 120); $tel = $s('telefone', 40); $email = $s('email', 160); $cidade = $s('cidade', 120);
if ($nome === '' || $tel === '' || $cidade === '') out(422, ['ok' => false, 'erro' => 'Preencha nome, telefone e cidade.']);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) out(422, ['ok' => false, 'erro' => 'E-mail inválido.']);
try {
    $pdo = tap_db(); $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo->prepare('DELETE FROM rate WHERE ts < ?')->execute([time() - 600]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM rate WHERE ip = ?'); $q->execute([$ip]);
    if ((int)$q->fetchColumn() >= 8) out(429, ['ok' => false, 'erro' => 'Muitas tentativas. Aguarde alguns minutos.']);
    $pdo->prepare('INSERT INTO rate (ip, ts) VALUES (?, ?)')->execute([$ip, time()]);
    $vagaId = isset($_POST['vaga_id']) && ctype_digit((string)$_POST['vaga_id']) ? (int)$_POST['vaga_id'] : null;
    if ($vagaId) { $v = $pdo->prepare('SELECT id FROM vagas WHERE id = ? AND ativa = 1'); $v->execute([$vagaId]); if (!$v->fetchColumn()) $vagaId = null; }
    $cvFile = null; $cvNome = null;
    if (!empty($_FILES['cv']['tmp_name']) && is_uploaded_file($_FILES['cv']['tmp_name'])) {
        $f = $_FILES['cv'];
        if ($f['size'] > 5 * 1024 * 1024) out(422, ['ok' => false, 'erro' => 'O currículo deve ter até 5 MB.']);
        $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : ($f['type'] ?? '');
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $okExt = ['pdf', 'doc', 'docx']; $okMime = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($ext, $okExt, true) || ($mime && !in_array($mime, $okMime, true))) out(422, ['ok' => false, 'erro' => 'Envie o currículo em PDF ou Word.']);
        $cvFile = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $ext; $cvNome = mb_substr($f['name'], 0, 120);
        if (!move_uploaded_file($f['tmp_name'], tap_cv_dir() . '/' . $cvFile)) out(500, ['ok' => false, 'erro' => 'Não foi possível salvar o currículo.']);
    }
    $now = tap_now();
    $pdo->prepare('INSERT INTO candidaturas (vaga_id, status, nome, email, telefone, cidade, cnh, experiencia, mensagem, cv_arquivo, cv_nome, ip, criado_em, atualizado_em) VALUES (?, "novo", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$vagaId, $nome, $email, $tel, $cidade, $s('cnh', 20), $s('experiencia', 60), $s('mensagem', 3000), $cvFile, $cvNome, $ip, $now, $now]);
    $cfg = __DIR__ . '/config.php';
    if (file_exists($cfg)) { $c = include $cfg; $to = is_array($c) ? ($c['notificar_email'] ?? '') : ''; if ($to) @mail($to, "[TAP Express] Nova candidatura: $nome", "Nome: $nome\nTelefone: $tel\nE-mail: $email\nCidade: $cidade\nVaga: " . ($vagaId ?: 'banco de talentos') . "\n\nAcesse a área interna > Candidatos.", "From: site@" . ($_SERVER['HTTP_HOST'] ?? 'tapexpress.com.br')); }
    out(200, ['ok' => true]);
} catch (Throwable $e) { error_log('candidatura.php: ' . $e->getMessage()); out(500, ['ok' => false, 'erro' => 'Não foi possível registrar agora. Tente novamente ou fale no WhatsApp (18) 99109-6441.']); }
