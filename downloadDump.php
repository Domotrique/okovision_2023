<?php

/*
* To download dump files instead of giving direct access
*/
include_once 'config.php';

if (0 !== strcmp(session::getInstance()->getVar('sid'), $_GET['sid'] ?? '')) {
    header('Location: /errors/403.php'); exit;
}
if (!session::getInstance()->getVar('logged')) {
    header('Location: /errors/401.php'); exit;
}

$name = $_GET['dump'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]{1,64}\.sql$/', $name)) {
    header('Location: /errors/404.php'); exit;
}

$path = realpath(DUMP_FOLDER . DIRECTORY_SEPARATOR . $name);
if (false === $path || 0 !== strpos($path, realpath(DUMP_FOLDER) . DIRECTORY_SEPARATOR) || !is_file($path)) {
    header('Location: /errors/404.php'); exit;
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);