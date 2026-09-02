<?php
// TAP Express — camada de dados (SQLite via PDO). Sem credenciais: o arquivo fica fora do docroot.
declare(strict_types=1);

function tap_data_dir(): string {
    $candidates = [];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) $candidates[] = dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/')) . '/tap_data';
    $candidates[] = dirname(__DIR__) . '/_private_data';
    foreach ($candidates as $dir) {
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        if (is_dir($dir) && is_writable($dir)) {
            if (!file_exists("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");
            return $dir;
        }
    }
    throw new RuntimeException('Sem diretório gravável para o banco de dados.');
}

function tap_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $file = tap_data_dir() . '/cotacoes.sqlite';
    $pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
    $pdo->exec('CREATE TABLE IF NOT EXISTS cotacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        protocolo TEXT UNIQUE NOT NULL,
        criado_em TEXT NOT NULL,
        atualizado_em TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "novo",
        origem TEXT, destino TEXT,
        tipo TEXT, volumes INTEGER, peso REAL, dimensoes TEXT, valor_mercadoria REAL,
        nome TEXT, empresa TEXT, email TEXT, telefone TEXT, observacoes TEXT,
        origem_pagina TEXT, ip TEXT, user_agent TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS notas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cotacao_id INTEGER NOT NULL REFERENCES cotacoes(id) ON DELETE CASCADE,
        criado_em TEXT NOT NULL, autor TEXT, texto TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL, email TEXT UNIQUE NOT NULL, senha_hash TEXT NOT NULL, criado_em TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS rate (ip TEXT, ts INTEGER)');
    return $pdo;
}

function tap_now(): string { return (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s'); }

function tap_protocolo(PDO $pdo): string {
    $ano = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y');
    $n = (int)$pdo->query("SELECT COUNT(*) FROM cotacoes WHERE protocolo LIKE 'TAP-$ano-%'")->fetchColumn() + 1;
    do {
        $p = sprintf('TAP-%s-%05d', $ano, $n++);
        $exists = $pdo->prepare('SELECT 1 FROM cotacoes WHERE protocolo = ?'); $exists->execute([$p]);
    } while ($exists->fetchColumn());
    return $p;
}

const TAP_STATUS = ['novo' => 'Novo', 'atendimento' => 'Em atendimento', 'cotado' => 'Cotado', 'fechado' => 'Fechado', 'perdido' => 'Perdido'];
