<?php
// Usuários e permissões: exclusivo do usuário master (super administrador).
require_once __DIR__ . '/auth.php';
if ((int)$user['master'] !== 1) { tap_require('usuarios', 'editar'); }
$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) $err = 'Sessão expirada. Tente novamente.';
    else {
        $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
        $perms = []; foreach (TAP_MODULOS as $k => $lab) { $v = $_POST['perm'][$k] ?? ''; if ($k !== 'usuarios' && in_array($v, ['ver', 'editar'], true)) $perms[$k] = $v; }
        if ($a === 'novo') {
            $nome = $s_('nome', 120); $email = strtolower($s_('email', 160)); $senha = (string)($_POST['senha'] ?? '');
            if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8 || strlen($senha) > 200) $err = 'Informe nome, e-mail válido e senha provisória com pelo menos 8 caracteres.';
            elseif (!tap_rate('novo_usuario:' . $user['id'], 20, 3600)) $err = 'Muitos cadastros em pouco tempo. Aguarde.';
            else { try { $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, criado_em, master, permissoes, ativo) VALUES (?, ?, ?, ?, 0, ?, 1)')->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), tap_now(), json_encode($perms)]); $msg = 'Usuário criado. Passe a senha provisória para a pessoa e peça para trocar em Minha conta.'; } catch (Throwable $e) { $err = 'Já existe um usuário com esse e-mail.'; } }
        }
        if ($a === 'perms' && $id && $id !== (int)$user['id']) {
            $q = $pdo->prepare('SELECT master FROM usuarios WHERE id = ?'); $q->execute([$id]);
            if ((int)$q->fetchColumn() === 1) $err = 'O usuário master não tem permissões editáveis.';
            else { $pdo->prepare('UPDATE usuarios SET permissoes = ? WHERE id = ?')->execute([json_encode($perms), $id]); $msg = 'Permissões atualizadas.'; }
        }
        if ($a === 'toggle' && $id && $id !== (int)$user['id']) { $pdo->prepare('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ? AND master = 0')->execute([$id]); $msg = 'Acesso alterado.'; }
        if ($a === 'senha' && $id) {
            $senha = (string)($_POST['senha'] ?? '');
            if (strlen($senha) < 8 || strlen($senha) > 200) $err = 'A senha provisória precisa ter pelo menos 8 caracteres.';
            else { $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ? AND master = 0')->execute([password_hash($senha, PASSWORD_DEFAULT), $id]); $msg = 'Senha provisória definida.'; }
        }
        if ($a === 'excluir' && $id && $id !== (int)$user['id']) { $pdo->prepare('DELETE FROM usuarios WHERE id = ? AND master = 0')->execute([$id]); $msg = 'Usuário removido.'; }
    }
}
$lista = $pdo->query('SELECT * FROM usuarios ORDER BY master DESC, nome')->fetchAll();
tap_admin_head('Usuários', 'usuarios'); tap_msgs($msg, $err); ?>
<h1>Usuários e permissões</h1><p class="sub">Só o usuário master vê esta tela. Para cada pessoa, escolha por módulo: sem acesso, só vê ou vê e edita.</p>
<div class="grid2" style="margin-top:20px">
  <div>
  <?php foreach ($lista as $u): $pu = json_decode($u['permissoes'] ?: '{}', true) ?: []; $isMaster = (int)$u['master'] === 1; $me = (int)$u['id'] === (int)$user['id']; ?>
    <div class="card" style="margin-bottom:14px<?= $u['ativo'] ? '' : ';opacity:.6' ?>">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><h2 style="margin:0"><?= $h($u['nome']) ?></h2><?= $isMaster ? '<span class="badge" style="background:#fdf3dc;color:#7a5a00">Master · acesso total</span>' : ($u['ativo'] ? '<span class="badge b-on">Ativo</span>' : '<span class="badge b-off">Desativado</span>') ?><span class="sub" style="margin-left:auto"><?= $h($u['email']) ?> · desde <?= $fmtData($u['criado_em']) ?></span></div>
      <?php if (!$isMaster): ?>
      <form method="post" style="margin-top:14px"><input type="hidden" name="action" value="perms"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $u['id'] ?>"/>
        <div class="perm"><?php foreach (TAP_MODULOS as $k => $lab) { if ($k === 'usuarios') continue; echo "<span>{$h($lab)}</span><select name='perm[$k]'>"; foreach (TAP_NIVEIS as $nk => $nl) echo "<option value='$nk'" . (($pu[$k] ?? '') === $nk ? ' selected' : '') . ">$nl</option>"; echo '</select>'; } ?></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px"><button class="btn p sm" type="submit">Salvar permissões</button>
          <button class="btn sm" type="submit" form="t<?= $u['id'] ?>"><?= $u['ativo'] ? 'Desativar acesso' : 'Reativar acesso' ?></button>
          <details style="display:inline-block"><summary style="margin:0;padding:7px 12px;border:1px solid var(--line2);border-radius:999px;font-family:var(--d);font-weight:600;font-size:12px;color:var(--ink)">Senha provisória</summary>
            <form method="post" style="display:flex;gap:8px;margin-top:8px"><input type="hidden" name="action" value="senha"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $u['id'] ?>"/><div class="pw" style="flex:1"><input type="password" name="senha" minlength="8" maxlength="200" placeholder="Nova senha provisória" required/><?= tap_eye() ?></div><button class="btn sm" type="submit">Definir</button></form></details>
          <button class="btn sm danger" type="submit" form="x<?= $u['id'] ?>" onclick="return confirm('Remover o acesso de <?= $h($u['nome']) ?>?')">Remover</button></div>
      </form>
      <form method="post" id="t<?= $u['id'] ?>"><input type="hidden" name="action" value="toggle"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $u['id'] ?>"/></form>
      <form method="post" id="x<?= $u['id'] ?>"><input type="hidden" name="action" value="excluir"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $u['id'] ?>"/></form>
      <?php else: ?><p class="sub" style="margin-top:10px">Vê e edita todos os módulos e é o único que administra usuários. Não pode ser desativado nem removido por aqui.</p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
  <div class="card"><h2>Novo usuário</h2><p class="sub">A pessoa entra com a senha provisória e troca em "Minha conta".</p>
    <form method="post" autocomplete="off"><input type="hidden" name="action" value="novo"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/>
      <label>Nome</label><input name="nome" maxlength="120" required/><label>E-mail</label><input type="email" name="email" maxlength="160" required/><label>Senha provisória (mín. 8)</label><div class="pw"><input type="password" name="senha" minlength="8" maxlength="200" required autocomplete="new-password"/><?= tap_eye() ?></div>
      <h3>Permissões</h3><div class="perm"><?php foreach (TAP_MODULOS as $k => $lab) { if ($k === 'usuarios') continue; echo "<span>{$h($lab)}</span><select name='perm[$k]'>"; foreach (TAP_NIVEIS as $nk => $nl) echo "<option value='$nk'>$nl</option>"; echo '</select>'; } ?></div>
      <div style="margin-top:18px"><button class="btn p" type="submit">Criar usuário</button></div></form></div>
</div>
<?php tap_admin_foot();
