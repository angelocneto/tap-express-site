<?php
// Kanban de candidatos: arraste entre colunas para mudar o status. Detalhe com notas e currículo.
require_once __DIR__ . '/auth.php';
$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) { http_response_code(403); if (isset($_POST['ajax'])) { echo json_encode(['ok' => false]); exit; } $err = 'Sessão expirada.'; }
    else {
        $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0); $now = tap_now();
        if ($a === 'status' && isset(TAP_CAND_STATUS[$_POST['status'] ?? ''])) { $pdo->prepare('UPDATE candidaturas SET status = ?, atualizado_em = ? WHERE id = ?')->execute([$_POST['status'], $now, $id]); if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; } $msg = 'Status atualizado.'; }
        if ($a === 'nota') { $t = trim($_POST['texto'] ?? ''); if ($t !== '') { $pdo->prepare('INSERT INTO candidatura_notas (candidatura_id, criado_em, autor, texto) VALUES (?,?,?,?)')->execute([$id, $now, $user['nome'], mb_substr($t, 0, 3000)]); $msg = 'Nota registrada.'; } }
        if ($a === 'excluir') { $q = $pdo->prepare('SELECT cv_arquivo FROM candidaturas WHERE id = ?'); $q->execute([$id]); $cv = $q->fetchColumn(); if ($cv) @unlink(tap_cv_dir() . '/' . basename($cv)); $pdo->prepare('DELETE FROM candidatura_notas WHERE candidatura_id = ?')->execute([$id]); $pdo->prepare('DELETE FROM candidaturas WHERE id = ?')->execute([$id]); header('Location: candidatos.php?msg=excluida'); exit; }
    }
}
if (isset($_GET['cv'])) { // download do currículo
    $q = $pdo->prepare('SELECT cv_arquivo, cv_nome FROM candidaturas WHERE id = ?'); $q->execute([(int)$_GET['cv']]); $c = $q->fetch();
    $f = $c && $c['cv_arquivo'] ? tap_cv_dir() . '/' . basename($c['cv_arquivo']) : '';
    if (!$f || !is_file($f)) { http_response_code(404); echo 'Currículo não encontrado.'; exit; }
    header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w.\-]/', '_', $c['cv_nome'] ?: 'curriculo') . '"'); header('Content-Length: ' . filesize($f)); readfile($f); exit;
}
if (($_GET['msg'] ?? '') === 'excluida') $msg = 'Candidatura excluída.';
$vagaF = isset($_GET['vaga']) ? (int)$_GET['vaga'] : 0;
$vagas = $pdo->query('SELECT id, titulo FROM vagas ORDER BY titulo')->fetchAll(); $vmap = []; foreach ($vagas as $v) $vmap[$v['id']] = $v['titulo'];
$sql = 'SELECT * FROM candidaturas' . ($vagaF ? ' WHERE vaga_id = ' . $vagaF : '') . ' ORDER BY atualizado_em DESC'; $cands = $pdo->query($sql)->fetchAll();
$digits = fn($t) => preg_replace('/\D+/', '', (string)$t);
tap_admin_head('Candidatos', 'candidatos');
if ($msg) echo "<div class='msg ok'>{$h($msg)}</div>"; if ($err) echo "<div class='msg err'>{$h($err)}</div>";
?>
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
  <h2 style="margin:0">Candidatos <span style="color:var(--muted);font-size:14px;font-family:var(--b);font-weight:400">· arraste os cartões entre as colunas</span></h2>
  <form method="get" style="margin-left:auto;display:flex;gap:8px;align-items:center"><select name="vaga" onchange="this.form.submit()" style="width:auto"><option value="0">Todas as vagas</option><?php foreach ($vagas as $v) echo "<option value='{$v['id']}'" . ($vagaF === (int)$v['id'] ? ' selected' : '') . ">{$h($v['titulo'])}</option>"; ?></select><a class="btn sm" href="vagas.php">Gerenciar vagas</a></form>
</div>
<div class="kanban" id="kanban">
<?php foreach (TAP_CAND_STATUS as $k => $label): $col = array_values(array_filter($cands, fn($c) => $c['status'] === $k)); ?>
  <div class="col" data-status="<?= $k ?>"><h4><?= $label ?> <span><?= count($col) ?></span></h4>
  <?php foreach ($col as $c): $tel = $digits($c['telefone']); if (strlen($tel) <= 11) $tel = '55' . $tel; ?>
    <div class="kcard" draggable="true" data-id="<?= $c['id'] ?>">
      <b><?= $h($c['nome']) ?></b><small><?= $h($c['cidade']) ?><?= $c['cnh'] ? ' · CNH ' . $h($c['cnh']) : '' ?><?= $c['experiencia'] ? ' · ' . $h($c['experiencia']) : '' ?></small><small><?= $fmtData($c['criado_em']) ?></small>
      <span class="vaga"><?= $h($c['vaga_id'] && isset($vmap[$c['vaga_id']]) ? $vmap[$c['vaga_id']] : 'Banco de talentos') ?></span>
      <div class="acts"><a href="#" data-open="<?= $c['id'] ?>">Detalhes</a><a href="https://wa.me/<?= $tel ?>" target="_blank" rel="noopener">WhatsApp</a><?php if ($c['cv_arquivo']): ?><a href="?cv=<?= $c['id'] ?>">Currículo</a><?php endif; ?></div>
    </div>
    <div class="modal" id="m<?= $c['id'] ?>"><div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center"><h2 style="margin:0"><?= $h($c['nome']) ?></h2><button class="btn sm" data-close>Fechar</button></div>
      <div class="kv" style="margin-top:14px"><div>Vaga</div><div><?= $h($c['vaga_id'] && isset($vmap[$c['vaga_id']]) ? $vmap[$c['vaga_id']] : 'Banco de talentos') ?></div><div>Telefone</div><div><a href="tel:+<?= $tel ?>" style="color:#9df0a8"><?= $h($c['telefone']) ?></a></div><div>E-mail</div><div><?= $h($c['email']) ?></div><div>Cidade</div><div><?= $h($c['cidade']) ?></div><div>CNH</div><div><?= $h($c['cnh']) ?></div><div>Experiência</div><div><?= $h($c['experiencia']) ?></div><div>Mensagem</div><div><?= nl2br($h($c['mensagem'])) ?></div><div>Currículo</div><div><?= $c['cv_arquivo'] ? "<a href='?cv={$c['id']}' style='color:#9df0a8'>Baixar {$h($c['cv_nome'])}</a>" : 'Não enviado' ?></div><div>Recebida</div><div><?= $fmtData($c['criado_em']) ?></div></div>
      <h3>Status</h3><form method="post" style="display:flex;gap:8px"><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="action" value="status"/><input type="hidden" name="id" value="<?= $c['id'] ?>"/><select name="status"><?php foreach (TAP_CAND_STATUS as $sk => $sl) echo "<option value='$sk'" . ($c['status'] === $sk ? ' selected' : '') . ">$sl</option>"; ?></select><button class="btn p" type="submit">Salvar</button></form>
      <h3>Notas internas</h3><form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="action" value="nota"/><input type="hidden" name="id" value="<?= $c['id'] ?>"/><textarea name="texto" rows="2" placeholder="Ex.: Entrevista marcada para quinta às 14h na unidade."></textarea><div style="margin-top:8px"><button class="btn" type="submit">Adicionar nota</button></div></form>
      <div style="margin-top:12px"><?php $n = $pdo->prepare('SELECT * FROM candidatura_notas WHERE candidatura_id = ? ORDER BY id DESC'); $n->execute([$c['id']]); foreach ($n->fetchAll() as $nt) echo "<div class='nota'><small>{$fmtData($nt['criado_em'])} · {$h($nt['autor'])}</small>" . nl2br($h($nt['texto'])) . "</div>"; ?></div>
      <details style="margin-top:16px"><summary style="cursor:pointer;color:var(--muted);font-size:13px">Excluir candidatura</summary><form method="post" onsubmit="return confirm('Excluir definitivamente?')" style="margin-top:8px"><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="action" value="excluir"/><input type="hidden" name="id" value="<?= $c['id'] ?>"/><button class="btn sm danger" type="submit">Excluir definitivamente</button></form></details>
    </div></div>
  <?php endforeach; ?></div>
<?php endforeach; ?>
</div>
<script>
(function(){
  const csrf = <?= json_encode($csrf) ?>; let dragged = null;
  document.querySelectorAll('.kcard').forEach(c => {
    c.addEventListener('dragstart', () => { dragged = c; c.classList.add('dragging'); });
    c.addEventListener('dragend', () => { c.classList.remove('dragging'); dragged = null; document.querySelectorAll('.col').forEach(x => x.classList.remove('over')); });
  });
  document.querySelectorAll('.col').forEach(col => {
    col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('over'); });
    col.addEventListener('dragleave', () => col.classList.remove('over'));
    col.addEventListener('drop', async e => {
      e.preventDefault(); col.classList.remove('over'); if (!dragged) return;
      const from = dragged.parentElement, id = dragged.dataset.id, status = col.dataset.status;
      col.appendChild(dragged); recount();
      const r = await fetch('candidatos.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ csrf, action: 'status', id, status, ajax: '1' }) });
      if (!r.ok) { from.appendChild(dragged); recount(); alert('Não foi possível salvar. Recarregue a página.'); }
    });
  });
  function recount(){ document.querySelectorAll('.col').forEach(c => c.querySelector('h4 span').textContent = c.querySelectorAll('.kcard').length); }
  document.querySelectorAll('[data-open]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); document.getElementById('m' + a.dataset.open).classList.add('on'); }));
  document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => b.closest('.modal').classList.remove('on')));
  document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('on'); }));
})();
</script>
<?php tap_admin_foot();
