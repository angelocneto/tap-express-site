<?php
// Páginas internas: exige login. Permissão por módulo é checada em cada página com tap_require().
declare(strict_types=1);
require_once __DIR__ . '/boot.php';
if (!$user) { header('Location: index.php'); exit; }
