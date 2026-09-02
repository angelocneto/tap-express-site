<?php
// Sessão e login compartilhados pelas páginas da área interna.
declare(strict_types=1);
require_once __DIR__ . '/db.php';
session_name('tapatend');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
$pdo = tap_db();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: index.php'); exit; }
$fmtData = fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '';
function tap_admin_head(string $titulo, string $ativo): void {
    global $h, $user;
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><meta name="robots" content="noindex,nofollow" /><title>TAP Express · ' . $h($titulo) . '</title><link rel="icon" href="../assets/favicon.png" /><link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" /><link rel="stylesheet" href="admin.css" /></head><body><div class="wrap">';
    echo '<div class="top"><a href="index.php"><img src="../assets/logo_branca.png" alt="TAP Express"/></a><nav class="menu">'
        . '<a class="' . ($ativo === 'cotacoes' ? 'on' : '') . '" href="index.php">Cotações</a>'
        . '<a class="' . ($ativo === 'vagas' ? 'on' : '') . '" href="vagas.php">Vagas</a>'
        . '<a class="' . ($ativo === 'candidatos' ? 'on' : '') . '" href="candidatos.php">Candidatos</a>'
        . '<a class="' . ($ativo === 'usuarios' ? 'on' : '') . '" href="index.php?v=usuarios">Usuários</a></nav>'
        . '<div class="who">' . $h($user['nome']) . ' · <a href="index.php?a=logout">Sair</a> · <a href="../" target="_blank">Ver site</a></div></div>';
}
function tap_admin_foot(): void { echo '</div></body></html>'; }
