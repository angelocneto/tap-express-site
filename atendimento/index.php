<?php
// TAP Express — Área interna de atendimento e cotação
declare(strict_types=1);
require_once __DIR__ . '/db.php';
session_name('tapatend');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

$pdo = tap_db();
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
$hasUsers = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
$user = $_SESSION['user'] ?? null;
$msg = ''; $err = '';
$action = $_POST['action'] ?? $_GET['a'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($csrf, $_POST['csrf'] ?? '')) { $err = 'Sessão expirada. Tente novamente.'; $action = ''; }

// ---------- primeiro acesso: cria o usuário administrador ----------
if ($action === 'setup' && !$hasUsers) {
    $nome = trim($_POST['nome'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $senha = $_POST['senha'] ?? '';
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8) $err = 'Informe nome, e-mail válido e uma senha com pelo menos 8 caracteres.';
    else {
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, criado_em) VALUES (?, ?, ?, ?)')->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), tap_now()]);
        $hasUsers = true; $_SESSION['user'] = ['nome' => $nome, 'email' => $email]; $user = $_SESSION['user'];
        header('Location: index.php'); exit;
    }
}
if ($action === 'login') {
    $email = strtolower(trim($_POST['email'] ?? '')); $senha = $_POST['senha'] ?? '';
    $q = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?'); $q->execute([$email]); $u = $q->fetch();
    if ($u && password_verify($senha, $u['senha_hash'])) { session_regenerate_id(true); $_SESSION['user'] = ['nome' => $u['nome'], 'email' => $u['email']]; header('Location: index.php'); exit; }
    $err = 'E-mail ou senha incorretos.'; usleep(400000);
}
if ($action === 'logout') { session_destroy(); header('Location: index.php'); exit; }

// ---------- ações autenticadas ----------
if ($user) {
    if ($action === 'status') {
        $st = $_POST['status'] ?? ''; $id = (int)($_POST['id'] ?? 0);
        if (isset(TAP_STATUS[$st])) { $pdo->prepare('UPDATE cotacoes SET status = ?, atualizado_em = ? WHERE id = ?')->execute([$st, tap_now(), $id]); $msg = 'Status atualizado.'; }
    }
    if ($action === 'nota') {
        $id = (int)($_POST['id'] ?? 0); $t = trim($_POST['texto'] ?? '');
        if ($t !== '') { $pdo->prepare('INSERT INTO notas (cotacao_id, criado_em, autor, texto) VALUES (?, ?, ?, ?)')->execute([$id, tap_now(), $user['nome'], mb_substr($t, 0, 3000)]); $pdo->prepare('UPDATE cotacoes SET atualizado_em = ? WHERE id = ?')->execute([tap_now(), $id]); $msg = 'Nota registrada.'; }
    }
    if ($action === 'novo_usuario') {
        $nome = trim($_POST['nome'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $senha = $_POST['senha'] ?? '';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8) $err = 'Dados do novo usuário inválidos (senha mínima de 8 caracteres).';
        else { try { $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, criado_em) VALUES (?, ?, ?, ?)')->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), tap_now()]); $msg = 'Usuário criado.'; } catch (Throwable $e) { $err = 'E-mail já cadastrado.'; } }
    }
    if ($action === 'csv') {
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="cotacoes-' . date('Y-m-d') . '.csv"');
        $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
        $cols = ['protocolo','criado_em','status','nome','empresa','telefone','email','origem','destino','tipo','volumes','peso','dimensoes','valor_mercadoria','observacoes'];
        fputcsv($o, $cols, ';');
        foreach ($pdo->query('SELECT * FROM cotacoes ORDER BY id DESC') as $r) fputcsv($o, array_map(fn($c) => $r[$c], $cols), ';');
        exit;
    }
}

$view = $user ? ($_GET['v'] ?? 'lista') : ($hasUsers ? 'login' : 'setup');
$filtro = $_GET['s'] ?? 'abertas'; $busca = trim($_GET['q'] ?? '');
$statusBadge = fn($s) => '<span class="badge b-' . $h($s) . '">' . $h(TAP_STATUS[$s] ?? $s) . '</span>';
$digits = fn($t) => preg_replace('/\D+/', '', (string)$t);
$fmtData = fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><meta name="robots" content="noindex,nofollow" />
<title>TAP Express · Atendimento</title>
<link rel="icon" href="../assets/favicon.png" />
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
<style>
:root{--bg:#061410;--bg2:#0a1f17;--ink:#f2f7f3;--muted:rgba(242,247,243,.62);--green:#39b54a;--line:rgba(242,247,243,.1);--d:"Sora",system-ui,sans-serif;--b:"Inter",system-ui,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}body{background:var(--bg);color:var(--ink);font-family:var(--b);min-height:100vh}
a{color:inherit;text-decoration:none}.wrap{width:min(1240px,calc(100% - 32px));margin:0 auto}
.top{display:flex;align-items:center;justify-content:space-between;padding:16px 0;border-bottom:1px solid var(--line);margin-bottom:24px;gap:16px;flex-wrap:wrap}
.top img{height:34px}.top .who{color:var(--muted);font-size:13px}.top .who b{color:var(--ink)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:999px;border:1px solid rgba(242,247,243,.22);background:transparent;color:var(--ink);font-family:var(--d);font-weight:600;font-size:13px;cursor:pointer}
.btn.p{background:var(--green);color:#05150b;border-color:var(--green)}.btn:hover{border-color:var(--green)}
.card{background:linear-gradient(180deg,rgba(255,255,255,.045),rgba(255,255,255,.015));border:1px solid var(--line);border-radius:18px;padding:22px}
.auth{max-width:420px;margin:10vh auto}.auth h1{font-family:var(--d);font-size:1.5rem;margin-bottom:6px}.auth p{color:var(--muted);font-size:14px;margin-bottom:18px}
label{display:block;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin:12px 0 6px}
input,select,textarea{width:100%;padding:12px 14px;border-radius:12px;border:1px solid rgba(242,247,243,.18);background:rgba(255,255,255,.04);color:var(--ink);font-family:var(--b);font-size:14px;outline:none}
input:focus,select:focus,textarea:focus{border-color:var(--green)}select option{background:#0a1f17}
.msg{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:14px}.msg.ok{background:rgba(57,181,74,.15);border:1px solid rgba(57,181,74,.4)}.msg.err{background:rgba(255,107,90,.15);border:1px solid rgba(255,107,90,.4)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}.stats .card{padding:16px}.stats b{display:block;font-family:var(--d);font-size:2rem;font-weight:800;letter-spacing:-.03em}.stats span{font-size:12px;color:var(--muted);letter-spacing:.1em;text-transform:uppercase}
.tools{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px}.tools form{display:flex;gap:8px;flex:1;min-width:260px}.tools input{flex:1}
.chips a{display:inline-block;padding:8px 14px;border-radius:999px;border:1px solid var(--line);font-size:12px;font-family:var(--d);font-weight:600;color:var(--muted);margin-right:4px}.chips a.on{background:var(--green);color:#05150b;border-color:var(--green)}
table{width:100%;border-collapse:collapse;font-size:14px}th{text-align:left;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);padding:10px 12px;border-bottom:1px solid var(--line)}td{padding:12px;border-bottom:1px solid var(--line);vertical-align:top}tr:hover td{background:rgba(255,255,255,.025)}
td .p{font-family:var(--d);font-weight:700;color:#9df0a8}td small{display:block;color:var(--muted);font-size:12px;margin-top:2px}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase}
.b-novo{background:rgba(57,181,74,.2);color:#9df0a8}.b-atendimento{background:rgba(255,196,0,.18);color:#ffd866}.b-cotado{background:rgba(94,234,212,.18);color:#5eead4}.b-fechado{background:rgba(242,247,243,.14);color:#fff}.b-perdido{background:rgba(255,107,90,.18);color:#ff9b8d}
.grid2{display:grid;grid-template-columns:1.2fr .8fr;gap:18px}.kv{display:grid;grid-template-columns:150px 1fr;gap:8px 14px;font-size:14px}.kv div:nth-child(odd){color:var(--muted);font-size:12px;letter-spacing:.06em;text-transform:uppercase;padding-top:2px}
.kv a{color:#9df0a8;border-bottom:1px solid rgba(57,181,74,.5)}h2{font-family:var(--d);font-size:1.4rem;letter-spacing:-.02em;margin-bottom:12px}h3{font-family:var(--d);font-size:1rem;margin:18px 0 10px}
.nota{padding:12px 14px;border-left:2px solid var(--green);background:rgba(255,255,255,.03);border-radius:0 12px 12px 0;margin-bottom:8px;font-size:14px}.nota small{display:block;color:var(--muted);font-size:11px;margin-bottom:4px}
.quick{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.empty{padding:40px;text-align:center;color:var(--muted)}
details summary{cursor:pointer;color:var(--muted);font-size:13px;margin-top:20px}
@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}.grid2{grid-template-columns:1fr}.kv{grid-template-columns:1fr}table{display:block;overflow-x:auto}}
</style>
</head>
<body><div class="wrap">
<?php if ($view === 'setup'): ?>
  <div class="auth card"><img src="../assets/logo_branca.png" alt="TAP Express" style="height:40px;margin-bottom:18px"/>
    <h1>Primeiro acesso</h1><p>Crie o usuário administrador da área de atendimento. Só é possível uma vez.</p>
    <?php if ($err) echo "<div class='msg err'>$h($err)</div>"; ?>
    <form method="post"><input type="hidden" name="action" value="setup"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/>
      <label>Nome</label><input name="nome" required/><label>E-mail</label><input type="email" name="email" required/><label>Senha (mín. 8 caracteres)</label><input type="password" name="senha" minlength="8" required/>
      <div style="margin-top:18px"><button class="btn p" type="submit">Criar acesso</button></div></form></div>
<?php elseif ($view === 'login'): ?>
  <div class="auth card"><img src="../assets/logo_branca.png" alt="TAP Express" style="height:40px;margin-bottom:18px"/>
    <h1>Área de atendimento</h1><p>Entre para ver e responder as cotações do site.</p>
    <?php if ($err) echo "<div class='msg err'>$h($err)</div>"; ?>
    <form method="post"><input type="hidden" name="action" value="login"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/>
      <label>E-mail</label><input type="email" name="email" required autofocus/><label>Senha</label><input type="password" name="senha" required/>
      <div style="margin-top:18px"><button class="btn p" type="submit">Entrar</button></div></form></div>
<?php else: ?>
  <div class="top"><a href="index.php"><img src="../assets/logo_branca.png" alt="TAP Express"/></a>
    <div class="who">Atendimento · <b><?= $h($user['nome']) ?></b></div>
    <div><a class="btn" href="../" target="_blank">Ver site</a> <a class="btn" href="?a=csv">Exportar CSV</a> <a class="btn" href="?v=usuarios">Usuários</a> <a class="btn" href="?a=logout">Sair</a></div></div>
  <?php if ($msg) echo "<div class='msg ok'>$h($msg)</div>"; if ($err) echo "<div class='msg err'>$h($err)</div>"; ?>

  <?php if ($view === 'ver'): $id = (int)($_GET['id'] ?? 0); $q = $pdo->prepare('SELECT * FROM cotacoes WHERE id = ?'); $q->execute([$id]); $c = $q->fetch(); if (!$c) { echo '<div class="empty">Cotação não encontrada.</div>'; } else {
    $n = $pdo->prepare('SELECT * FROM notas WHERE cotacao_id = ? ORDER BY id DESC'); $n->execute([$id]); $notas = $n->fetchAll(); $tel = $digits($c['telefone']); if (strlen($tel) <= 11) $tel = '55' . $tel; ?>
    <a href="index.php" style="color:var(--muted);font-size:13px">← Voltar para a lista</a>
    <div class="grid2" style="margin-top:12px">
      <div class="card"><h2><span style="color:#9df0a8"><?= $h($c['protocolo']) ?></span> · <?= $h($c['nome']) ?></h2><?= $statusBadge($c['status']) ?>
        <div class="quick"><a class="btn p" href="https://wa.me/<?= $tel ?>?text=<?= rawurlencode("Olá {$c['nome']}, aqui é da TAP Express sobre a cotação {$c['protocolo']} ({$c['origem']} → {$c['destino']}).") ?>" target="_blank">WhatsApp</a><a class="btn" href="tel:+<?= $tel ?>">Ligar</a><?php if ($c['email']): ?><a class="btn" href="mailto:<?= $h($c['email']) ?>?subject=<?= rawurlencode('Cotação ' . $c['protocolo'] . ' — TAP Express') ?>">E-mail</a><?php endif; ?></div>
        <div class="kv">
          <div>Recebida</div><div><?= $fmtData($c['criado_em']) ?></div><div>Origem</div><div><?= $h($c['origem']) ?></div><div>Destino</div><div><?= $h($c['destino']) ?></div>
          <div>Tipo</div><div><?= $h($c['tipo']) ?></div><div>Volumes</div><div><?= $h($c['volumes']) ?></div><div>Peso</div><div><?= $c['peso'] !== null ? $h($c['peso']) . ' kg' : '' ?></div>
          <div>Dimensões</div><div><?= $h($c['dimensoes']) ?></div><div>Valor mercadoria</div><div><?= $c['valor_mercadoria'] !== null ? 'R$ ' . number_format((float)$c['valor_mercadoria'], 2, ',', '.') : '' ?></div>
          <div>Empresa</div><div><?= $h($c['empresa']) ?></div><div>Telefone</div><div><a href="tel:+<?= $tel ?>"><?= $h($c['telefone']) ?></a></div><div>E-mail</div><div><?= $h($c['email']) ?></div>
          <div>Observações</div><div><?= nl2br($h($c['observacoes'])) ?></div><div>Página</div><div><?= $h($c['origem_pagina']) ?></div></div></div>
      <div><div class="card"><h3 style="margin-top:0">Status</h3>
        <form method="post"><input type="hidden" name="action" value="status"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $id ?>"/>
          <select name="status"><?php foreach (TAP_STATUS as $k => $v) echo "<option value='$k'" . ($c['status'] === $k ? ' selected' : '') . ">$v</option>"; ?></select>
          <div style="margin-top:10px"><button class="btn p" type="submit">Salvar status</button></div></form>
        <h3>Notas internas</h3>
        <form method="post"><input type="hidden" name="action" value="nota"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $id ?>"/>
          <textarea name="texto" rows="3" placeholder="Ex.: Cotado R$ 148,00, prazo 18h. Cliente vai confirmar amanhã."></textarea><div style="margin-top:8px"><button class="btn" type="submit">Adicionar nota</button></div></form>
        <div style="margin-top:12px"><?php foreach ($notas as $nt) echo "<div class='nota'><small>{$fmtData($nt['criado_em'])} · {$h($nt['autor'])}</small>" . nl2br($h($nt['texto'])) . "</div>"; if (!$notas) echo "<p style='color:var(--muted);font-size:13px'>Nenhuma nota ainda.</p>"; ?></div></div></div></div>
  <?php } elseif ($view === 'usuarios'): ?>
    <div class="grid2"><div class="card"><h2>Usuários com acesso</h2><table><tr><th>Nome</th><th>E-mail</th><th>Desde</th></tr><?php foreach ($pdo->query('SELECT * FROM usuarios ORDER BY id') as $u) echo "<tr><td>{$h($u['nome'])}</td><td>{$h($u['email'])}</td><td>{$fmtData($u['criado_em'])}</td></tr>"; ?></table></div>
    <div class="card"><h2>Novo usuário</h2><form method="post"><input type="hidden" name="action" value="novo_usuario"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><label>Nome</label><input name="nome" required/><label>E-mail</label><input type="email" name="email" required/><label>Senha provisória (mín. 8)</label><input type="password" name="senha" minlength="8" required/><div style="margin-top:14px"><button class="btn p" type="submit">Criar usuário</button></div></form></div></div>
  <?php else:
    $hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    $cnt = fn($sql, $p = []) => (function() use ($pdo, $sql, $p) { $q = $pdo->prepare($sql); $q->execute($p); return (int)$q->fetchColumn(); })();
    $where = 'WHERE 1=1'; $p = [];
    if ($filtro === 'abertas') $where .= " AND status IN ('novo','atendimento','cotado')"; elseif (isset(TAP_STATUS[$filtro])) { $where .= ' AND status = ?'; $p[] = $filtro; }
    if ($busca !== '') { $where .= ' AND (protocolo LIKE ? OR nome LIKE ? OR empresa LIKE ? OR telefone LIKE ? OR email LIKE ? OR origem LIKE ? OR destino LIKE ?)'; $p = array_merge($p, array_fill(0, 7, "%$busca%")); }
    $q = $pdo->prepare("SELECT * FROM cotacoes $where ORDER BY CASE status WHEN 'novo' THEN 0 WHEN 'atendimento' THEN 1 WHEN 'cotado' THEN 2 ELSE 3 END, id DESC LIMIT 300"); $q->execute($p); $rows = $q->fetchAll(); ?>
    <div class="stats"><div class="card"><b><?= $cnt("SELECT COUNT(*) FROM cotacoes WHERE status='novo'") ?></b><span>Novas</span></div><div class="card"><b><?= $cnt("SELECT COUNT(*) FROM cotacoes WHERE criado_em LIKE ?", ["$hoje%"]) ?></b><span>Recebidas hoje</span></div><div class="card"><b><?= $cnt("SELECT COUNT(*) FROM cotacoes WHERE status IN ('atendimento','cotado')") ?></b><span>Em andamento</span></div><div class="card"><b><?= $cnt("SELECT COUNT(*) FROM cotacoes WHERE status='fechado'") ?></b><span>Fechadas</span></div></div>
    <div class="tools"><form method="get"><input type="hidden" name="s" value="<?= $h($filtro) ?>"/><input name="q" value="<?= $h($busca) ?>" placeholder="Buscar por protocolo, nome, empresa, telefone, cidade…"/><button class="btn" type="submit">Buscar</button></form>
      <div class="chips"><a class="<?= $filtro === 'abertas' ? 'on' : '' ?>" href="?s=abertas">Abertas</a><?php foreach (TAP_STATUS as $k => $v) echo "<a class='" . ($filtro === $k ? 'on' : '') . "' href='?s=$k'>$v</a>"; ?><a class="<?= $filtro === 'todas' ? 'on' : '' ?>" href="?s=todas">Todas</a></div></div>
    <div class="card" style="padding:0;overflow:hidden"><table><tr><th>Protocolo</th><th>Cliente</th><th>Rota</th><th>Carga</th><th>Status</th><th>Recebida</th></tr>
      <?php foreach ($rows as $r): ?><tr><td><a class="p" href="?v=ver&id=<?= $r['id'] ?>"><?= $h($r['protocolo']) ?></a></td><td><a href="?v=ver&id=<?= $r['id'] ?>"><?= $h($r['nome']) ?></a><small><?= $h($r['empresa'] ?: $r['telefone']) ?></small></td><td><?= $h($r['origem']) ?> → <?= $h($r['destino']) ?></td><td><?= $h($r['tipo']) ?><small><?= $r['volumes'] ? $h($r['volumes']) . ' vol · ' : '' ?><?= $r['peso'] !== null ? $h($r['peso']) . ' kg' : '' ?></small></td><td><?= $statusBadge($r['status']) ?></td><td><?= $fmtData($r['criado_em']) ?></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="6" class="empty">Nenhuma cotação neste filtro.</td></tr>'; ?></table></div>
  <?php endif; ?>
<?php endif; ?>
</div></body></html>
