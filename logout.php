<?php
require __DIR__ . '/includes/functions.php';

// Only log out with the session's token (a link on another website can't log you out)
if (!hash_equals(csrf_token(), $_GET['t'] ?? '')) redirect(home_for_role());
if (uid()) audit('logout');
$lang = $_SESSION['lang'] ?? 'en';
$_SESSION = [];
session_regenerate_id(true);
$_SESSION['lang'] = $lang;
flash('success', t('logged_out'));
redirect('index.php');
