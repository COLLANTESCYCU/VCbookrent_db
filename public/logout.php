<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Auth.php';

$auth = Auth::getInstance();
$auth->logout();

header('Location: login.php');
exit;