<?php
require __DIR__ . '/includes/functions.php';

if (uid()) audit('logout');
$lang = $_SESSION['lang'] ?? 'en';
$_SESSION = [];
session_regenerate_id(true);
$_SESSION['lang'] = $lang;
flash('success', t('logged_out'));
redirect('index.php');
