<?php
// Gerenciador de vagas: cria, edita, ativa e desativa. Vagas ativas aparecem ao vivo em /carreiras/.
require_once __DIR__ . '/auth.php';
$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) { $err = 'Sessão expirada. Tente novamente.'; }
    else {
        $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0); $now = tap_now();
        if ($a === 'salvar') {
            $t = trim($_POST['titulo'] ?? '');
            if ($t === '') $err = 'Informe o título da vaga.';
            else {
                $vals = [$t, $_POST['area'] ?? '', $_POST['tipo'] ?? '', trim($_POST['unidade'] ?? ''), trim($_POST['cidade'] ?? ''), strtoupper(trim($_POST['uf'] ?? '')), trim($_POST['descricao'] ?? ''), trim($_POST['requisitos'] ?? ''), trim($_POST['beneficios'] ?? ''), isset($_POST['ativa']) ? 1 : 0, $now];
                if ($id) { $pdo->prepare('UPDATE vagas SET titulo=?, area=?, tipo=?, unidade=?, cidade=?, uf=?, descricao=?, requisitos=?, beneficios=?, ativa=?, atualizado_em=? WHERE id=?')->execute([...$vals, $id]); $msg = 'Vaga atualizada.'; }
                else { $pdo->prepare('INSERT INTO vagas (titulo, area, tipo, unidade, cidade, uf, descricao, requisitos, beneficios, ativa, atualizado_em, criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$vals, $now]); $msg = 'Vaga criada. Se estiver ativa, já aparece em /carreiras/.'; }
            }
        }
        if ($a === 'toggle') { $pdo->prepare('UPDATE vagas SET ativa = 1 - ativa, atualizado_em = ? WHERE id = ?')->execute([$now, $id]); $msg = 'Status da vaga alterado.'; }
        if ($a === 'excluir') { $pdo->prepare('DELETE FROM vagas WHERE id = ?')->execute([$id]); $msg = 'Vaga excluída.'; }
    }
}
$edit = null; if (isset($_GET['edit'])) { $q = $pdo->prepare('SELECT * FROM vagas WHERE id = ?'); $q->execute([(int)$_GET['edit']]); $edit = $q->fetch(); }
$vagas = $pdo->query('SELECT v.*, (SELECT COUNT(*) FROM candidaturas c WHERE c.vaga_id = v.id) AS n FROM vagas v ORDER BY ativa DESC, atualizado_em DESC')->fetchAll();
tap_admin_head('Vagas', 'vagas');
if ($msg) echo "<div class='msg ok'>{$h($msg)}</div>"; if ($err) echo "<div class='msg err'>{$h($err)}</div>";
?>
<div class="grid2">
  <div class="card" style="padding:0;overflow:hidden">
    <table><tr><th>Vaga</th><th>Local</th><th>Tipo</th><th>Status</th><th>Cand.</th><th></th></tr>
    <?php foreach ($vagas as $v): ?><tr>
      <td><b><?= $h($v['titulo']) ?></b><br/><small style="color:var(--muted)"><?= $h($v['area']) ?></small></td>
      <td><?= $h($v['unidade'] ?: $v['cidade']) ?><?= $v['uf'] ? ' · ' . $h($v['uf']) : '' ?></td>
      <td><?= $h($v['tipo']) ?></td>
      <td><span class="badge <?= $v['ativa'] ? 'b-on' : 'b-off' ?>"><?= $v['ativa'] ? 'Ativa · no site' : 'Inativa' ?></span></td>
      <td><a href="candidatos.php?vaga=<?= $v['id'] ?>" style="color:#9df0a8"><?= (int)$v['n'] ?></a></td>
      <td style="white-space:nowrap"><a class="btn sm" href="?edit=<?= $v['id'] ?>">Editar</a>
        <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="action" value="toggle"/><input type="hidden" name="id" value="<?= $v['id'] ?>"/><button class="btn sm" type="submit"><?= $v['ativa'] ? 'Desativar' : 'Ativar' ?></button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('Excluir a vaga? As candidaturas ficam salvas.')"><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="action" value="excluir"/><input type="hidden" name="id" value="<?= $v['id'] ?>"/><button class="btn sm danger" type="submit">Excluir</button></form></td>
    </tr><?php endforeach; if (!$vagas) echo '<tr><td colspan="6" class="empty">Nenhuma vaga cadastrada. Crie a primeira ao lado.</td></tr>'; ?></table>
  </div>
  <div class="card">
    <h2><?= $edit ? 'Editar vaga' : 'Nova vaga' ?></h2>
    <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="action" value="salvar"/><input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>"/>
      <label>Título</label><input name="titulo" required value="<?= $h($edit['titulo'] ?? '') ?>" placeholder="Ex.: Motorista de caminhão baú (CNH D)"/>
      <div class="row"><div><label>Área</label><select name="area"><?php foreach (TAP_AREAS as $o) echo "<option" . (($edit['area'] ?? '') === $o ? ' selected' : '') . ">$o</option>"; ?></select></div><div><label>Tipo</label><select name="tipo"><?php foreach (TAP_TIPOS as $o) echo "<option" . (($edit['tipo'] ?? '') === $o ? ' selected' : '') . ">$o</option>"; ?></select></div></div>
      <div class="row"><div><label>Unidade</label><input name="unidade" value="<?= $h($edit['unidade'] ?? '') ?>" placeholder="Ex.: Regente Feijó"/></div><div><label>Cidade / UF</label><div style="display:flex;gap:8px"><input name="cidade" value="<?= $h($edit['cidade'] ?? '') ?>" placeholder="Presidente Prudente"/><input name="uf" maxlength="2" style="width:70px" value="<?= $h($edit['uf'] ?? 'SP') ?>"/></div></div></div>
      <label>Descrição</label><textarea name="descricao" rows="4" placeholder="O que a pessoa vai fazer no dia a dia."><?= $h($edit['descricao'] ?? '') ?></textarea>
      <label>Requisitos (um por linha)</label><textarea name="requisitos" rows="4" placeholder="CNH D ou E&#10;Experiência com baú&#10;Disponibilidade para rota regional"><?= $h($edit['requisitos'] ?? '') ?></textarea>
      <label>Benefícios (um por linha)</label><textarea name="beneficios" rows="4" placeholder="Salário em dia&#10;Plano de saúde&#10;Vale-refeição"><?= $h($edit['beneficios'] ?? '') ?></textarea>
      <label style="display:flex;align-items:center;gap:10px;text-transform:none;letter-spacing:0;font-size:14px;color:var(--ink)"><input type="checkbox" name="ativa" style="width:auto" <?= (!$edit || $edit['ativa']) ? 'checked' : '' ?>/> Vaga ativa (visível em /carreiras/)</label>
      <div style="margin-top:16px;display:flex;gap:8px"><button class="btn p" type="submit"><?= $edit ? 'Salvar alterações' : 'Publicar vaga' ?></button><?php if ($edit): ?><a class="btn" href="vagas.php">Cancelar</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php tap_admin_foot();
