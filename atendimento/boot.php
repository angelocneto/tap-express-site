<?php
// Base compartilhada da área interna: sessão, banco, helpers e permissões. Não redireciona.
declare(strict_types=1);
require_once __DIR__ . '/db.php';
session_name('tapatend');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('X-Frame-Options: SAMEORIGIN'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin'); header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; frame-ancestors 'self'");
$pdo = tap_db();
// limite de tentativas por chave (ip, e-mail…): true = pode seguir
function tap_rate(string $key, int $max, int $janela): bool {
    global $pdo; $pdo->prepare('DELETE FROM rate WHERE ts < ?')->execute([time() - 3600]);
    $q = $pdo->prepare('SELECT COUNT(*) FROM rate WHERE ip = ? AND ts > ?'); $q->execute([$key, time() - $janela]);
    if ((int)$q->fetchColumn() >= $max) return false;
    $pdo->prepare('INSERT INTO rate (ip, ts) VALUES (?, ?)')->execute([$key, time()]); return true;
}
$s_ = fn($k, $max = 200, $src = null) => mb_substr(trim(strip_tags((string)(($src ?? $_POST)[$k] ?? ''))), 0, $max);
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
$fmtData = fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '';
$digits = fn($t) => preg_replace('/\D+/', '', (string)$t);

// usuário logado sempre relido do banco (permissões valem na hora)
$user = null;
if (!empty($_SESSION['uid'])) {
    $q = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? AND ativo = 1'); $q->execute([(int)$_SESSION['uid']]); $u = $q->fetch();
    if ($u) { $u['perms'] = json_decode($u['permissoes'] ?: '{}', true) ?: []; $user = $u; }
    else { unset($_SESSION['uid']); }
}
function tap_can(string $mod, string $nivel = 'ver'): bool {
    global $user;
    if (!$user) return false;
    if ((int)$user['master'] === 1) return true;
    $p = $user['perms'][$mod] ?? '';
    return $nivel === 'ver' ? in_array($p, ['ver', 'editar'], true) : $p === 'editar';
}
function tap_require(string $mod, string $nivel = 'ver'): void {
    global $user, $h;
    if (!$user) { header('Location: index.php'); exit; }
    if (!tap_can($mod, $nivel)) {
        http_response_code(403); tap_admin_head('Sem permissão', '');
        echo '<div class="card empty"><h2>Sem permissão</h2><p>Seu acesso não inclui o módulo <b>' . $h(TAP_MODULOS[$mod] ?? $mod) . '</b>' . ($nivel === 'editar' ? ' com edição' : '') . '. Fale com o administrador do painel.</p><p style="margin-top:14px"><a class="btn" href="index.php">Voltar</a></p></div>';
        tap_admin_foot(); exit;
    }
}
function tap_home(): string {
    foreach (['atendimento' => 'cotacoes.php', 'clientes' => 'clientes.php', 'rh' => 'candidatos.php', 'assinaturas' => 'assinaturas.php', 'usuarios' => 'usuarios.php'] as $m => $f) if (tap_can($m)) return $f;
    return 'index.php?v=conta';
}
function tap_admin_head(string $titulo, string $ativo): void {
    global $h, $user;
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><meta name="robots" content="noindex,nofollow" /><title>TAP Express · ' . $h($titulo) . '</title><link rel="icon" href="../assets/favicon.png" /><link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" /><link rel="stylesheet" href="admin.css?v=' . filemtime(__DIR__ . '/admin.css') . '" /></head><body><div class="wrap">';
    $links = [
        ['atendimento', 'cotacoes.php', 'Atendimento'], ['atendimento', 'leads.php', 'Leads'], ['clientes', 'clientes.php', 'Clientes'], ['rh', 'candidatos.php', 'Candidatos'], ['rh', 'vagas.php', 'Vagas'], ['assinaturas', 'assinaturas.php', 'Assinaturas'], ['usuarios', 'usuarios.php', 'Usuários'],
    ];
    echo '<header class="top"><a class="brand" href="index.php"><img src="../assets/logo_cor.png" alt="TAP Express"/><span>Painel interno</span></a><nav class="menu">';
    foreach ($links as [$mod, $file, $label]) if (tap_can($mod) && file_exists(__DIR__ . '/' . $file)) echo '<a class="' . ($ativo === basename($file, '.php') ? 'on' : '') . '" href="' . $file . '">' . $label . '</a>';
    echo '</nav><div class="who"><a href="index.php?v=conta" title="Minha conta">' . $h($user['nome'] ?? '') . ((int)($user['master'] ?? 0) === 1 ? ' <em>master</em>' : '') . '</a> · <a href="../" target="_blank">Ver site</a> · <a href="index.php?a=logout">Sair</a></div></header>';
}
function tap_admin_foot(): void {
    echo '</div><script>document.addEventListener("click",function(e){const b=e.target.closest("[data-eye]");if(!b)return;const i=b.parentElement.querySelector("input");const show=i.type==="password";i.type=show?"text":"password";b.querySelector(".e-shut").style.display=show?"":"none";i.focus();});document.querySelectorAll("[data-open]").forEach(a=>a.addEventListener("click",e=>{e.preventDefault();document.getElementById("m"+a.dataset.open).classList.add("on")}));document.querySelectorAll("[data-close]").forEach(b=>b.addEventListener("click",()=>b.closest(".modal").classList.remove("on")));document.querySelectorAll(".modal").forEach(m=>m.addEventListener("click",e=>{if(e.target===m)m.classList.remove("on")}));</script></body></html>';
}
function tap_eye(): string {
    return '<button type="button" class="eye" aria-label="Mostrar ou ocultar a senha" data-eye><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 12s3.8-7 10.5-7 10.5 7 10.5 7-3.8 7-10.5 7S1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/><path class="e-shut" d="M3 3l18 18" style="display:none"/></svg></button>';
}
function tap_msgs(string $msg, string $err): void { global $h; if ($msg) echo "<div class='msg ok'>{$h($msg)}</div>"; if ($err) echo "<div class='msg err'>{$h($err)}</div>"; }

// cubo com as medidas (C × L × A) a partir do texto salvo em dimensoes
function tap_cubo(?string $dim, int $w = 92): string {
    if (!$dim || !preg_match('/(\d+(?:[.,]\d+)?)\D+(\d+(?:[.,]\d+)?)\D+(\d+(?:[.,]\d+)?)/u', $dim, $m)) return '';
    [$c, $l, $a] = [$m[1], $m[2], $m[3]]; $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $m3 = ((float)str_replace(',', '.', $c) * (float)str_replace(',', '.', $l) * (float)str_replace(',', '.', $a)) / 1e6;
    return '<span class="cubo" title="' . $h("$c × $l × $a cm · " . number_format($m3, 3, ',', '.') . ' m³ · cubagem ' . number_format($m3 * 300, 1, ',', '.') . ' kg') . '"><svg viewBox="0 0 170 140" width="' . $w . '" height="' . (int)($w * 140 / 170) . '"><g fill="none" stroke="#1f8f36" stroke-width="2" stroke-linejoin="round"><path d="M30 45 L95 20 L150 45 L85 70 Z" fill="#e6f4ea"/><path d="M30 45 L85 70 L85 130 L30 105 Z" fill="#f3f6f3"/><path d="M85 70 L150 45 L150 105 L85 130 Z" fill="#dcefe1"/></g><g font-family="Inter,system-ui" font-size="17" font-weight="700" fill="#0b1a12"><text x="38" y="138">' . $h($c) . '</text><text x="112" y="138">' . $h($l) . '</text><text x="0" y="82">' . $h($a) . '</text></g></svg><em>' . $h(number_format($m3, 3, ',', '.')) . ' m³</em></span>';
}
