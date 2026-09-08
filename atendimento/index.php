<?php
// TAP Express — área interna: login, primeiro acesso, senha, painel de módulos e minha conta.
require_once __DIR__ . '/boot.php';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$hasUsers = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
$msg = ''; $err = ''; $action = $_POST['action'] ?? $_GET['a'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($csrf, $_POST['csrf'] ?? '')) { $err = 'Sessão expirada. Tente novamente.'; $action = ''; }

// ---------- primeiro acesso: cria o usuário master (só uma vez) ----------
if ($action === 'setup' && !$hasUsers) {
    $nome = $s_('nome', 120); $email = strtolower($s_('email', 160)); $senha = (string)($_POST['senha'] ?? '');
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8 || strlen($senha) > 200) $err = 'Informe nome, e-mail válido e uma senha com pelo menos 8 caracteres.';
    else {
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, criado_em, master, ativo) VALUES (?, ?, ?, ?, 1, 1)')->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), tap_now()]);
        session_regenerate_id(true); $_SESSION['uid'] = (int)$pdo->lastInsertId(); header('Location: index.php'); exit;
    }
}
// ---------- login com limite de tentativas ----------
if ($action === 'login') {
    $email = strtolower($s_('email', 160)); $senha = (string)($_POST['senha'] ?? '');
    if (!tap_rate("login:$ip", 12, 900) || !tap_rate("login:" . hash('sha256', $email), 6, 900)) { $err = 'Muitas tentativas. Aguarde 15 minutos ou use "Esqueci minha senha".'; usleep(600000); }
    else {
        $q = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? AND ativo = 1'); $q->execute([$email]); $u = $q->fetch();
        if ($u && password_verify($senha, $u['senha_hash'])) {
            session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; $_SESSION['csrf'] = bin2hex(random_bytes(16));
            $pdo->prepare('DELETE FROM rate WHERE ip = ?')->execute(["login:" . hash('sha256', $email)]);
            header('Location: index.php'); exit;
        }
        $err = 'E-mail ou senha incorretos.'; usleep(500000);
    }
}
// ---------- esqueci minha senha (resposta sempre igual) ----------
if ($action === 'esqueci') {
    $email = strtolower($s_('email', 160));
    if (tap_rate("reset:$ip", 5, 900)) {
        $q = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? AND ativo = 1'); $q->execute([$email]); $u = $q->fetch();
        if ($u) {
            $token = bin2hex(random_bytes(24));
            $pdo->prepare('UPDATE senha_reset SET usado = 1 WHERE email = ? AND usado = 0')->execute([$email]);
            $pdo->prepare('INSERT INTO senha_reset (email, token_hash, expira_em, usado, criado_em) VALUES (?, ?, ?, 0, ?)')->execute([$email, hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600), tap_now()]);
            $host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'www.tapexpress.com.br');
            $link = 'https://' . $host . '/atendimento/index.php?v=redefinir&t=' . $token;
            $corpo = "Olá, {$u['nome']}.\n\nRecebemos um pedido para redefinir a senha do painel interno da TAP Express.\n\nAbra o link abaixo em até 1 hora para criar uma nova senha:\n$link\n\nSe você não pediu isso, ignore este e-mail. Sua senha continua a mesma.\n\nTAP Express · Painel interno";
            $cfg = dirname(__DIR__) . '/api/config.php'; $from = '';
            if (file_exists($cfg)) { $c = include $cfg; $from = is_array($c) ? ($c['remetente'] ?? '') : ''; }
            if ($from === '') $from = 'no-reply@' . preg_replace('/^www\./', '', $host);
            @mail($email, 'TAP Express · Redefinir senha do painel interno', $corpo, "From: TAP Express <$from>\r\nReply-To: recepcao@taptransportes.com.br\r\nContent-Type: text/plain; charset=utf-8");
        }
    }
    header('Location: index.php?v=esqueci&ok=1'); exit;
}
if ($action === 'redefinir') {
    $token = (string)($_POST['t'] ?? ''); $senha = (string)($_POST['senha'] ?? ''); $senha2 = (string)($_POST['senha2'] ?? '');
    $q = $pdo->prepare('SELECT * FROM senha_reset WHERE token_hash = ? AND usado = 0 AND expira_em > ?'); $q->execute([hash('sha256', $token), date('Y-m-d H:i:s')]); $r = $q->fetch();
    if (!$r) { header('Location: index.php?v=esqueci&expirado=1'); exit; }
    if (strlen($senha) < 8 || strlen($senha) > 200) $err = 'A senha precisa ter pelo menos 8 caracteres.';
    elseif ($senha !== $senha2) $err = 'As duas senhas não conferem.';
    else {
        $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE email = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $r['email']]);
        $pdo->prepare('UPDATE senha_reset SET usado = 1 WHERE id = ?')->execute([$r['id']]);
        header('Location: index.php?redefinida=1'); exit;
    }
    $_GET['v'] = 'redefinir'; $_GET['t'] = $token;
}
if ($action === 'logout') { $_SESSION = []; session_destroy(); header('Location: index.php'); exit; }
// ---------- minha conta (logado) ----------
if ($user && $action === 'minha_senha') {
    $atual = (string)($_POST['atual'] ?? ''); $nova = (string)($_POST['nova'] ?? ''); $nova2 = (string)($_POST['nova2'] ?? '');
    if (!password_verify($atual, $user['senha_hash'])) $err = 'A senha atual não confere.';
    elseif (strlen($nova) < 8 || strlen($nova) > 200) $err = 'A nova senha precisa ter pelo menos 8 caracteres.';
    elseif ($nova !== $nova2) $err = 'As duas senhas novas não conferem.';
    else { $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $user['id']]); $msg = 'Senha alterada.'; }
}
if ($user && $action === 'meu_nome') {
    $nome = $s_('nome', 120); if ($nome === '') $err = 'Informe seu nome.'; else { $pdo->prepare('UPDATE usuarios SET nome = ? WHERE id = ?')->execute([$nome, $user['id']]); $user['nome'] = $nome; $msg = 'Nome atualizado.'; }
}

// compatibilidade com links antigos (?v=ver&id=…)
if ($user && (($_GET['v'] ?? '') === 'ver' || ($_GET['v'] ?? '') === 'lista' || ($_GET['a'] ?? '') === 'csv')) { header('Location: cotacoes.php?' . http_build_query($_GET)); exit; }

$view = $user ? (($_GET['v'] ?? '') === 'conta' ? 'conta' : 'painel') : ($hasUsers ? (in_array($_GET['v'] ?? '', ['esqueci', 'redefinir'], true) ? $_GET['v'] : 'login') : 'setup');
if (($_GET['redefinida'] ?? '') === '1') $msg = 'Senha redefinida. Entre com a nova senha.';
if ($view === 'redefinir' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $q = $pdo->prepare('SELECT id FROM senha_reset WHERE token_hash = ? AND usado = 0 AND expira_em > ?'); $q->execute([hash('sha256', (string)($_GET['t'] ?? '')), date('Y-m-d H:i:s')]);
    if (!$q->fetch()) { header('Location: index.php?v=esqueci&expirado=1'); exit; }
}

if (!$user): ?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><meta name="robots" content="noindex,nofollow" /><title>TAP Express · Painel interno</title><link rel="icon" href="../assets/favicon.png" /><link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" /><link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>" /></head>
<body><div class="wrap">
<?php if ($view === 'setup'): ?>
  <div class="auth card"><img src="../assets/logo_cor.png" alt="TAP Express"/>
    <h1>Primeiro acesso</h1><p>Crie o usuário administrador (master) do painel interno. Só é possível uma vez.</p>
    <?php tap_msgs($msg, $err); ?>
    <form method="post" autocomplete="off"><input type="hidden" name="action" value="setup"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/>
      <label>Nome</label><input name="nome" maxlength="120" required/><label>E-mail</label><input type="email" name="email" maxlength="160" required/><label>Senha (mín. 8 caracteres)</label><div class="pw"><input type="password" name="senha" minlength="8" maxlength="200" required/><?= tap_eye() ?></div>
      <div style="margin-top:18px"><button class="btn p" type="submit">Criar acesso</button></div></form></div>
<?php elseif ($view === 'login'): ?>
  <div class="auth card"><img src="../assets/logo_cor.png" alt="TAP Express"/>
    <h1>Painel interno</h1><p>Entre com seu e-mail e senha.</p>
    <?php tap_msgs($msg, $err); ?>
    <form method="post"><input type="hidden" name="action" value="login"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/>
      <label>E-mail</label><input type="email" name="email" maxlength="160" required autofocus autocomplete="username"/><label>Senha</label><div class="pw"><input type="password" name="senha" maxlength="200" required autocomplete="current-password"/><?= tap_eye() ?></div>
      <div style="margin-top:18px;display:flex;align-items:center;gap:16px"><button class="btn p" type="submit">Entrar</button><a class="link" href="index.php?v=esqueci">Esqueci minha senha</a></div></form></div>
<?php elseif ($view === 'esqueci'): ?>
  <div class="auth card"><img src="../assets/logo_cor.png" alt="TAP Express"/>
    <h1>Esqueci minha senha</h1>
    <?php if (($_GET['ok'] ?? '') === '1'): ?><div class="msg ok">Se este e-mail tiver acesso, enviamos um link para criar uma nova senha. Vale por 1 hora. Confira também a caixa de spam.</div><?php endif; ?>
    <?php if (($_GET['expirado'] ?? '') === '1'): ?><div class="msg err">Esse link expirou ou já foi usado. Peça um novo.</div><?php endif; ?>
    <p>Informe o e-mail do seu acesso. Enviamos um link para você definir uma nova senha.</p>
    <form method="post"><input type="hidden" name="action" value="esqueci"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/>
      <label>E-mail</label><input type="email" name="email" maxlength="160" required autofocus/>
      <div style="margin-top:18px;display:flex;align-items:center;gap:16px"><button class="btn p" type="submit">Enviar link</button><a class="link" href="index.php">Voltar</a></div></form></div>
<?php elseif ($view === 'redefinir'): ?>
  <div class="auth card"><img src="../assets/logo_cor.png" alt="TAP Express"/>
    <h1>Nova senha</h1><p>Escolha uma senha com pelo menos 8 caracteres.</p>
    <?php tap_msgs('', $err); ?>
    <form method="post"><input type="hidden" name="action" value="redefinir"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="t" value="<?= $h($_GET['t'] ?? '') ?>"/>
      <label>Nova senha</label><div class="pw"><input type="password" name="senha" minlength="8" maxlength="200" required autofocus autocomplete="new-password"/><?= tap_eye() ?></div>
      <label>Repita a nova senha</label><div class="pw"><input type="password" name="senha2" minlength="8" maxlength="200" required autocomplete="new-password"/><?= tap_eye() ?></div>
      <div style="margin-top:18px"><button class="btn p" type="submit">Salvar nova senha</button></div></form></div>
<?php endif; ?>
</div><script>document.addEventListener("click",function(e){const b=e.target.closest("[data-eye]");if(!b)return;const i=b.parentElement.querySelector("input");const show=i.type==="password";i.type=show?"text":"password";b.querySelector(".e-shut").style.display=show?"":"none";i.focus();});</script></body></html>
<?php exit; endif;

// ---------- logado: painel de módulos ou minha conta ----------
if ($view === 'conta') {
    tap_admin_head('Minha conta', ''); tap_msgs($msg, $err); ?>
    <h1>Minha conta</h1><p class="sub">Dados do seu acesso. Suas permissões são definidas pelo administrador.</p>
    <div class="grid2" style="margin-top:20px">
      <div class="card"><h2>Alterar senha</h2>
        <form method="post"><input type="hidden" name="action" value="minha_senha"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/>
          <label>Senha atual</label><div class="pw"><input type="password" name="atual" maxlength="200" required autocomplete="current-password"/><?= tap_eye() ?></div>
          <div class="row"><div><label>Nova senha</label><div class="pw"><input type="password" name="nova" minlength="8" maxlength="200" required autocomplete="new-password"/><?= tap_eye() ?></div></div><div><label>Repita a nova senha</label><div class="pw"><input type="password" name="nova2" minlength="8" maxlength="200" required autocomplete="new-password"/><?= tap_eye() ?></div></div></div>
          <div style="margin-top:16px"><button class="btn p" type="submit">Salvar senha</button></div></form></div>
      <div><div class="card"><h2>Perfil</h2>
        <form method="post"><input type="hidden" name="action" value="meu_nome"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><label>Nome</label><input name="nome" maxlength="120" value="<?= $h($user['nome']) ?>" required/><label>E-mail</label><input value="<?= $h($user['email']) ?>" disabled/><div style="margin-top:14px"><button class="btn" type="submit">Salvar nome</button></div></form>
        <h3>Suas permissões</h3><div class="kv"><?php foreach (TAP_MODULOS as $k => $lab) { $nv = (int)$user['master'] === 1 ? 'Acesso total' : (TAP_NIVEIS[$user['perms'][$k] ?? ''] ?? 'Sem acesso'); echo "<div>{$h($lab)}</div><div>{$h($nv)}</div>"; } ?></div></div></div>
    </div>
<?php tap_admin_foot(); exit; }

$cnt = fn($sql) => (int)$pdo->query($sql)->fetchColumn();
$cards = [
    ['atendimento', 'cotacoes.php', 'Atendimento', 'Pedidos de cotação do site em kanban: novo, em atendimento, cotado, fechado, perdido. Notas internas, WhatsApp e exportação.', $cnt("SELECT COUNT(*) FROM cotacoes WHERE status IN ('novo','atendimento','cotado')") . ' em aberto', '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M8 9h8M8 13h5"/></svg>'],
    ['clientes', 'clientes.php', 'Clientes', 'Base de clientes com CNPJ, contatos, histórico de pedidos e observações comerciais.', file_exists(__DIR__ . '/clientes.php') ? $cnt('SELECT COUNT(*) FROM clientes') . ' cadastrados' : 'Em construção', '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4a3.5 3.5 0 0 1 0 7M21.5 20a6 6 0 0 0-5-6"/></svg>'],
    ['rh', 'candidatos.php', 'RH · Candidatos', 'Kanban de quem se candidatou pelo site: novos, triagem, entrevista, aprovados. Currículos e notas.', $cnt("SELECT COUNT(*) FROM candidaturas WHERE status IN ('novo','triagem','entrevista')") . ' em processo', '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>'],
    ['rh', 'vagas.php', 'RH · Vagas', 'Cria, edita, ativa e desativa vagas. As vagas ativas aparecem ao vivo em Trabalhe conosco.', $cnt('SELECT COUNT(*) FROM vagas WHERE ativa = 1') . ' ativas', '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v13H4zM9 7V4h6v3M4 12h16"/></svg>'],
    ['assinaturas', 'assinaturas.php', 'Assinaturas de e-mail', 'Gera a assinatura padrão TAP com nome, cargo, unidade e telefones, pronta para colar no Gmail ou Outlook.', file_exists(__DIR__ . '/assinaturas.php') ? 'Abrir' : 'Em construção', '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20h16M6 16c3-8 6-8 9-3 1 2 3 1 4-1"/></svg>'],
    ['usuarios', 'usuarios.php', 'Usuários e permissões', 'Cadastra quem acessa o painel e define, por módulo, quem só vê e quem também edita.', $cnt('SELECT COUNT(*) FROM usuarios WHERE ativo = 1') . ' ativos', '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>'],
];
tap_admin_head('Painel', ''); tap_msgs($msg, $err); ?>
<h1>Olá, <?= $h(explode(' ', $user['nome'])[0]) ?>.</h1><p class="sub">Escolha um módulo. Você só vê o que tem permissão para acessar.</p>
<div class="modules">
<?php $shown = 0; foreach ($cards as [$mod, $file, $t, $d, $n, $ic]) { if (!tap_can($mod)) continue; $ok = file_exists(__DIR__ . '/' . $file); $shown++;
  echo '<a class="module' . ($ok ? '' : ' soon') . '" href="' . ($ok ? $file : '#') . '"><div class="ic">' . $ic . '</div><b>' . $h($t) . '</b><p>' . $h($d) . '</p><span class="n">' . $h($ok ? $n : 'Em construção') . ' →</span></a>'; }
  if (!$shown) echo '<div class="card empty">Seu acesso ainda não tem módulos liberados. Fale com o administrador.</div>'; ?>
</div>
<?php tap_admin_foot();
