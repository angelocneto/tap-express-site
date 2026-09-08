<?php
// Atendimento: pedidos de cotação do site em kanban (arraste) ou lista, com detalhe, notas, status e CSV.
require_once __DIR__ . '/auth.php';
tap_require('atendimento');
$edit = tap_can('atendimento', 'editar');
$msg = ($_GET['msg'] ?? '') === 'excluida' ? 'Cotação excluída.' : ''; $err = '';
$action = $_POST['action'] ?? $_GET['a'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) { if (isset($_POST['ajax'])) { http_response_code(403); echo json_encode(['ok' => false]); exit; } $err = 'Sessão expirada. Tente novamente.'; $action = ''; }
    elseif (!$edit) { if (isset($_POST['ajax'])) { http_response_code(403); echo json_encode(['ok' => false]); exit; } $err = 'Seu acesso é só de leitura neste módulo.'; $action = ''; }
}
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($action === 'status' && isset(TAP_STATUS[$_POST['status'] ?? ''])) {
    $pdo->prepare('UPDATE cotacoes SET status = ?, atualizado_em = ? WHERE id = ?')->execute([$_POST['status'], tap_now(), $id]);
    if (isset($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
    $msg = 'Status atualizado.';
}
if ($action === 'nota') {
    $t = $s_('texto', 3000);
    if ($t !== '') { $pdo->prepare('INSERT INTO notas (cotacao_id, criado_em, autor, texto) VALUES (?, ?, ?, ?)')->execute([$id, tap_now(), $user['nome'], $t]); $pdo->prepare('UPDATE cotacoes SET atualizado_em = ? WHERE id = ?')->execute([tap_now(), $id]); $msg = 'Nota registrada.'; }
}
if ($action === 'excluir' && (int)$user['master'] === 1) {
    $pdo->prepare('DELETE FROM notas WHERE cotacao_id = ?')->execute([$id]); $pdo->prepare('DELETE FROM cotacoes WHERE id = ?')->execute([$id]);
    header('Location: cotacoes.php?msg=excluida'); exit;
}
if ($action === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="cotacoes-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    $cols = ['protocolo','criado_em','status','nome','empresa','cnpj','telefone','email','origem','destino','tipo','volumes','peso','dimensoes','valor_mercadoria','pagador','coleta_data','observacoes'];
    fputcsv($o, $cols, ';'); foreach ($pdo->query('SELECT * FROM cotacoes ORDER BY id DESC') as $r) fputcsv($o, array_map(fn($c) => $r[$c] ?? '', $cols), ';'); exit;
}
$statusBadge = fn($s) => '<span class="badge b-' . $h($s) . '">' . $h(TAP_STATUS[$s] ?? $s) . '</span>';
$wa = function ($c) use ($digits) { $t = $digits($c['telefone']); if (strlen($t) <= 11) $t = '55' . $t; return [$t, 'https://wa.me/' . $t . '?text=' . rawurlencode("Olá {$c['nome']}, aqui é da TAP Express sobre a cotação {$c['protocolo']} ({$c['origem']} → {$c['destino']}).")]; };
$view = $_GET['v'] ?? 'kanban'; $modo = in_array($view, ['lista', 'ver'], true) ? $view : 'kanban';

// ---------- detalhe ----------
if ($modo === 'ver') {
    $q = $pdo->prepare('SELECT * FROM cotacoes WHERE id = ?'); $q->execute([$id]); $c = $q->fetch();
    tap_admin_head('Cotação', 'cotacoes'); tap_msgs($msg, $err);
    if (!$c) { echo '<div class="card empty">Cotação não encontrada. <a class="link" href="cotacoes.php">Voltar</a></div>'; tap_admin_foot(); exit; }
    $n = $pdo->prepare('SELECT * FROM notas WHERE cotacao_id = ? ORDER BY id DESC'); $n->execute([$id]); $notas = $n->fetchAll(); [$tel, $walink] = $wa($c); ?>
    <a class="link" href="cotacoes.php">← Voltar ao atendimento</a>
    <div class="grid2" style="margin-top:12px">
      <div class="card"><h2><span style="color:var(--green)"><?= $h($c['protocolo']) ?></span> · <?= $h($c['nome']) ?></h2><?= $statusBadge($c['status']) ?>
        <div class="quick"><a class="btn p" href="<?= $walink ?>" target="_blank" rel="noopener">WhatsApp</a><a class="btn" href="tel:+<?= $tel ?>">Ligar</a><?php if ($c['email']): ?><a class="btn" href="mailto:<?= $h($c['email']) ?>?subject=<?= rawurlencode('Cotação ' . $c['protocolo'] . ' · TAP Express') ?>">E-mail</a><?php endif; ?></div>
        <div class="kv">
          <div>Recebida</div><div><?= $fmtData($c['criado_em']) ?></div><div>Origem</div><div><?= $h($c['origem']) ?></div><div>Destino</div><div><?= $h($c['destino']) ?></div>
          <div>Tipo</div><div><?= $h($c['tipo']) ?></div><div>Volumes</div><div><?= $h($c['volumes']) ?></div><div>Peso</div><div><?= $c['peso'] !== null ? $h($c['peso']) . ' kg' : '' ?></div>
          <div>Dimensões</div><div><?= $h($c['dimensoes']) ?> <?= tap_cubo($c['dimensoes'], 110) ?></div><div>Valor da NF</div><div><?= $c['valor_mercadoria'] !== null ? 'R$ ' . number_format((float)$c['valor_mercadoria'], 2, ',', '.') : '' ?></div>
          <div>Paga o frete</div><div><?= $h($c['pagador'] ?? '') ?></div><div>Coleta desejada</div><div><?= !empty($c['coleta_data']) ? $h(implode('/', array_reverse(explode('-', $c['coleta_data'])))) : '' ?></div>
          <div>Empresa</div><div><?= $h($c['empresa']) ?></div><div>CNPJ</div><div><?= $h($c['cnpj'] ?? '') ?></div><div>Telefone</div><div><a href="tel:+<?= $tel ?>"><?= $h($c['telefone']) ?></a></div><div>E-mail</div><div><?= $h($c['email']) ?></div>
          <div>Observações</div><div><?= nl2br($h($c['observacoes'])) ?></div><div>Página</div><div><?= $h($c['origem_pagina']) ?></div></div></div>
      <div><div class="card"><h3 style="margin-top:0">Status</h3>
        <?php if ($edit): ?><form method="post"><input type="hidden" name="action" value="status"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $id ?>"/>
          <select name="status"><?php foreach (TAP_STATUS as $k => $v) echo "<option value='$k'" . ($c['status'] === $k ? ' selected' : '') . ">$v</option>"; ?></select>
          <div style="margin-top:10px"><button class="btn p" type="submit">Salvar status</button></div></form>
        <h3>Notas internas</h3>
        <form method="post"><input type="hidden" name="action" value="nota"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $id ?>"/>
          <textarea name="texto" rows="3" maxlength="3000" placeholder="Ex.: Cotado R$ 148,00, prazo 18h. Cliente confirma amanhã."></textarea><div style="margin-top:8px"><button class="btn" type="submit">Adicionar nota</button></div></form>
        <?php else: echo $statusBadge($c['status']) . '<p class="sub" style="margin-top:8px">Seu acesso é só de leitura.</p><h3>Notas internas</h3>'; endif; ?>
        <div style="margin-top:12px"><?php foreach ($notas as $nt) echo "<div class='nota'><small>{$fmtData($nt['criado_em'])} · {$h($nt['autor'])}</small>" . nl2br($h($nt['texto'])) . "</div>"; if (!$notas) echo "<p class='sub'>Nenhuma nota ainda.</p>"; ?></div>
        <?php if ((int)$user['master'] === 1): ?><details><summary>Excluir esta cotação</summary><form method="post" onsubmit="return confirm('Excluir definitivamente a cotação <?= $h($c['protocolo']) ?>?')" style="margin-top:8px"><input type="hidden" name="action" value="excluir"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $id ?>"/><button class="btn danger sm" type="submit">Excluir definitivamente</button></form></details><?php endif; ?>
      </div></div></div>
<?php tap_admin_foot(); exit; }

// ---------- kanban / lista ----------
$hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
$cnt = fn($sql, $p = []) => (function() use ($pdo, $sql, $p) { $q = $pdo->prepare($sql); $q->execute($p); return (int)$q->fetchColumn(); })();
$filtro = $_GET['s'] ?? 'abertas'; $busca = mb_substr(trim($_GET['q'] ?? ''), 0, 80);
tap_admin_head('Atendimento', 'cotacoes'); tap_msgs($msg, $err); ?>
<div class="stats"><div class="card"><b><?= $cnt("SELECT COUNT(*) FROM cotacoes WHERE status='novo'") ?></b><span>Novas</span></div><div class="card"><b><?= $cnt("SELECT COUNT(*) FROM cotacoes WHERE criado_em LIKE ?", ["$hoje%"]) ?></b><span>Recebidas hoje</span></div><div class="card"><b><?= $cnt("SELECT COUNT(*) FROM cotacoes WHERE status IN ('atendimento','cotado')") ?></b><span>Em andamento</span></div><div class="card"><b><?= $cnt("SELECT COUNT(*) FROM cotacoes WHERE status='fechado'") ?></b><span>Fechadas</span></div></div>
<div class="tools">
  <form method="get"><input type="hidden" name="v" value="<?= $modo ?>"/><input name="q" value="<?= $h($busca) ?>" maxlength="80" placeholder="Buscar por protocolo, nome, empresa, CNPJ, telefone, cidade…"/><button class="btn" type="submit">Buscar</button></form>
  <div class="seg"><a class="<?= $modo === 'kanban' ? 'on' : '' ?>" href="cotacoes.php?v=kanban&q=<?= urlencode($busca) ?>">Kanban</a><a class="<?= $modo === 'lista' ? 'on' : '' ?>" href="cotacoes.php?v=lista&q=<?= urlencode($busca) ?>">Lista</a></div>
  <a class="btn" href="cotacoes.php?a=csv">Exportar CSV</a>
</div>
<?php
$where = 'WHERE 1=1'; $p = [];
if ($busca !== '') { $where .= ' AND (protocolo LIKE ? OR nome LIKE ? OR empresa LIKE ? OR cnpj LIKE ? OR telefone LIKE ? OR email LIKE ? OR origem LIKE ? OR destino LIKE ?)'; $p = array_fill(0, 8, "%$busca%"); }
if ($modo === 'kanban') {
    $q = $pdo->prepare("SELECT * FROM cotacoes $where ORDER BY atualizado_em DESC LIMIT 600"); $q->execute($p); $rows = $q->fetchAll(); ?>
<div class="kanban" id="kanban">
<?php foreach (TAP_STATUS as $k => $label): $col = array_values(array_filter($rows, fn($c) => $c['status'] === $k)); if (in_array($k, ['fechado', 'perdido'], true)) $col = array_slice($col, 0, 40); ?>
  <div class="col" data-status="<?= $k ?>"><h4><?= $label ?> <span><?= count($col) ?></span></h4>
  <?php foreach ($col as $c): [$tel, $walink] = $wa($c); ?>
    <div class="kcard" draggable="<?= $edit ? 'true' : 'false' ?>" data-id="<?= $c['id'] ?>">
      <span class="proto"><?= $h($c['protocolo']) ?></span><b><?= $h($c['nome']) ?></b>
      <small><?= $h($c['empresa'] ?: $c['telefone']) ?></small>
      <small><?= $h($c['origem']) ?> → <?= $h($c['destino']) ?></small>
      <small><?= $h($c['tipo']) ?><?= $c['volumes'] ? ' · ' . $h($c['volumes']) . ' vol' : '' ?><?= $c['peso'] !== null ? ' · ' . $h($c['peso']) . ' kg' : '' ?><?= $c['valor_mercadoria'] !== null ? ' · NF R$ ' . number_format((float)$c['valor_mercadoria'], 2, ',', '.') : '' ?></small>
      <small><?= $fmtData($c['criado_em']) ?></small>
      <?= tap_cubo($c['dimensoes'], 84) ?>
      <div class="acts"><a href="cotacoes.php?v=ver&id=<?= $c['id'] ?>">Detalhes</a><a href="<?= $walink ?>" target="_blank" rel="noopener">WhatsApp</a></div>
    </div>
  <?php endforeach; ?></div>
<?php endforeach; ?>
</div>
<?php if ($edit): ?>
<script>
(function(){
  const csrf = <?= json_encode($csrf) ?>; let dragged = null;
  document.querySelectorAll('.kcard').forEach(c => { c.addEventListener('dragstart', () => { dragged = c; c.classList.add('dragging'); }); c.addEventListener('dragend', () => { c.classList.remove('dragging'); dragged = null; document.querySelectorAll('.col').forEach(x => x.classList.remove('over')); }); });
  document.querySelectorAll('.col').forEach(col => {
    col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('over'); });
    col.addEventListener('dragleave', () => col.classList.remove('over'));
    col.addEventListener('drop', async e => {
      e.preventDefault(); col.classList.remove('over'); if (!dragged) return;
      const from = dragged.parentElement, id = dragged.dataset.id, status = col.dataset.status;
      col.insertBefore(dragged, col.querySelector('.kcard')); recount();
      const r = await fetch('cotacoes.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ csrf, action: 'status', id, status, ajax: '1' }) });
      if (!r.ok) { from.appendChild(dragged); recount(); alert('Não foi possível salvar. Recarregue a página.'); }
    });
  });
  function recount(){ document.querySelectorAll('.col').forEach(c => c.querySelector('h4 span').textContent = c.querySelectorAll('.kcard').length); }
})();
</script>
<?php endif; } else {
    if ($filtro === 'abertas') $where .= " AND status IN ('novo','atendimento','cotado')"; elseif (isset(TAP_STATUS[$filtro])) { $where .= ' AND status = ?'; $p[] = $filtro; }
    $q = $pdo->prepare("SELECT * FROM cotacoes $where ORDER BY CASE status WHEN 'novo' THEN 0 WHEN 'atendimento' THEN 1 WHEN 'cotado' THEN 2 ELSE 3 END, id DESC LIMIT 300"); $q->execute($p); $rows = $q->fetchAll(); ?>
<div class="chips" style="margin-bottom:14px"><a class="<?= $filtro === 'abertas' ? 'on' : '' ?>" href="?v=lista&s=abertas">Abertas</a><?php foreach (TAP_STATUS as $k => $v) echo "<a class='" . ($filtro === $k ? 'on' : '') . "' href='?v=lista&s=$k'>$v</a>"; ?><a class="<?= $filtro === 'todas' ? 'on' : '' ?>" href="?v=lista&s=todas">Todas</a></div>
<div class="card" style="padding:0;overflow:hidden"><table><tr><th>Protocolo</th><th>Cliente</th><th>Rota</th><th>Carga</th><th>Status</th><th>Recebida</th></tr>
<?php foreach ($rows as $r): ?><tr><td><a class="p" href="cotacoes.php?v=ver&id=<?= $r['id'] ?>"><?= $h($r['protocolo']) ?></a></td><td><a href="cotacoes.php?v=ver&id=<?= $r['id'] ?>"><?= $h($r['nome']) ?></a><small><?= $h($r['empresa'] ?: $r['telefone']) ?></small></td><td><?= $h($r['origem']) ?> → <?= $h($r['destino']) ?></td><td><?= $h($r['tipo']) ?><small><?= $r['volumes'] ? $h($r['volumes']) . ' vol · ' : '' ?><?= $r['peso'] !== null ? $h($r['peso']) . ' kg' : '' ?></small></td><td><?= $statusBadge($r['status']) ?></td><td><?= $fmtData($r['criado_em']) ?></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="6" class="empty">Nenhuma cotação neste filtro.</td></tr>'; ?>
</table></div>
<?php } tap_admin_foot();
