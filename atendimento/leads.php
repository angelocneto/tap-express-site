<?php
// Leads captados no site (rastreamento e outras ações): lista, status, notas e exportação.
require_once __DIR__ . '/auth.php';
tap_require('atendimento'); $edit = tap_can('atendimento', 'editar');
$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) $err = 'Sessão expirada.';
    elseif (!$edit) $err = 'Seu acesso é só de leitura neste módulo.';
    else {
        $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
        if ($a === 'status' && isset(TAP_LEAD_STATUS[$_POST['status'] ?? ''])) { $pdo->prepare('UPDATE leads SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]); $msg = 'Status atualizado.'; }
        if ($a === 'obs') { $pdo->prepare('UPDATE leads SET observacoes = ? WHERE id = ?')->execute([$s_('observacoes', 2000), $id]); $msg = 'Observação salva.'; }
        if ($a === 'excluir' && (int)$user['master'] === 1) { $pdo->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]); $msg = 'Lead removido.'; }
    }
}
if (($_GET['a'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); $cols = ['criado_em', 'nome', 'whatsapp', 'email', 'origem', 'contexto', 'status', 'aceite_em', 'observacoes']; fputcsv($o, $cols, ';');
    foreach ($pdo->query('SELECT * FROM leads ORDER BY id DESC') as $r) fputcsv($o, array_map(fn($c) => $r[$c] ?? '', $cols), ';'); exit;
}
$f = $_GET['s'] ?? 'novo'; $busca = mb_substr(trim($_GET['q'] ?? ''), 0, 80); $where = 'WHERE 1=1'; $p = [];
if (isset(TAP_LEAD_STATUS[$f])) { $where .= ' AND status = ?'; $p[] = $f; }
if ($busca !== '') { $where .= ' AND (nome LIKE ? OR whatsapp LIKE ? OR email LIKE ? OR contexto LIKE ?)'; $p = array_merge($p, array_fill(0, 4, "%$busca%")); }
$q = $pdo->prepare("SELECT * FROM leads $where ORDER BY id DESC LIMIT 500"); $q->execute($p); $rows = $q->fetchAll();
$cnt = fn($st) => (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = '$st'")->fetchColumn();
tap_admin_head('Leads', 'leads'); tap_msgs($msg, $err); ?>
<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap"><h1>Leads</h1><span class="sub">Pessoas que deixaram contato no site e aceitaram receber comunicações.</span><a class="btn sm" style="margin-left:auto" href="leads.php?a=csv">Exportar CSV</a></div>
<div class="stats" style="margin-top:16px"><?php foreach (TAP_LEAD_STATUS as $k => $v) echo "<div class='card'><b>{$cnt($k)}</b><span>$v</span></div>"; ?></div>
<div class="tools"><form method="get"><input type="hidden" name="s" value="<?= $h($f) ?>"/><input name="q" value="<?= $h($busca) ?>" maxlength="80" placeholder="Buscar por nome, WhatsApp, e-mail ou contexto…"/><button class="btn" type="submit">Buscar</button></form>
  <div class="chips"><?php foreach (TAP_LEAD_STATUS as $k => $v) echo "<a class='" . ($f === $k ? 'on' : '') . "' href='?s=$k'>$v</a>"; ?><a class="<?= $f === 'todos' ? 'on' : '' ?>" href="?s=todos">Todos</a></div></div>
<div class="card" style="padding:0;overflow:hidden"><table><tr><th>Recebido</th><th>Contato</th><th>Origem</th><th>Status</th><th>Observações</th></tr>
<?php foreach ($rows as $r): ?><tr>
  <td><?= $fmtData($r['criado_em']) ?><small>aceite <?= $fmtData($r['aceite_em']) ?></small></td>
  <td><b><?= $h($r['nome']) ?></b><small><a href="https://wa.me/<?= $h($r['whatsapp']) ?>" target="_blank" rel="noopener" style="color:var(--green)">+<?= $h($r['whatsapp']) ?></a><?= $r['email'] ? ' · <a href="mailto:' . $h($r['email']) . '">' . $h($r['email']) . '</a>' : '' ?></small></td>
  <td><?= $h($r['origem']) ?><small><?= $h($r['contexto']) ?></small></td>
  <td><?php if ($edit): ?><form method="post" style="display:flex;gap:6px"><input type="hidden" name="action" value="status"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $r['id'] ?>"/><select name="status" onchange="this.form.submit()" style="width:auto;padding:6px 10px"><?php foreach (TAP_LEAD_STATUS as $k => $v) echo "<option value='$k'" . ($r['status'] === $k ? ' selected' : '') . ">$v</option>"; ?></select></form><?php else: echo $h(TAP_LEAD_STATUS[$r['status']] ?? $r['status']); endif; ?></td>
  <td><?php if ($edit): ?><form method="post" style="display:flex;gap:6px"><input type="hidden" name="action" value="obs"/><input type="hidden" name="csrf" value="<?= $csrf ?>"/><input type="hidden" name="id" value="<?= $r['id'] ?>"/><input name="observacoes" maxlength="2000" value="<?= $h($r['observacoes']) ?>" placeholder="Anotação" style="padding:6px 10px"/><button class="btn sm" type="submit">Salvar</button><?php if ((int)$user['master'] === 1): ?><button class="btn sm danger" type="submit" name="action" value="excluir" onclick="return confirm('Remover este lead?')">×</button><?php endif; ?></form><?php else: echo $h($r['observacoes']); endif; ?></td>
</tr><?php endforeach; if (!$rows) echo '<tr><td colspan="5" class="empty">Nenhum lead neste filtro.</td></tr>'; ?>
</table></div>
<?php tap_admin_foot();
