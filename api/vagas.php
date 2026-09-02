<?php
// Vagas ativas em JSON para a página pública de carreiras.
declare(strict_types=1);
require_once dirname(__DIR__) . '/atendimento/db.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
try {
    $rows = tap_db()->query('SELECT id, titulo, area, tipo, unidade, cidade, uf, descricao, requisitos, beneficios, atualizado_em FROM vagas WHERE ativa = 1 ORDER BY atualizado_em DESC')->fetchAll();
    echo json_encode(['ok' => true, 'vagas' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['ok' => false, 'vagas' => []]); }
