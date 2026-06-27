<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
$f = __DIR__ . '/terminals.json';
if (is_file($f)) { readfile($f); } else { echo '[]'; }
