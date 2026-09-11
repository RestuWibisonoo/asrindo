<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$_SESSION = [];
session_destroy();

session_start();
flash_set('success', 'Anda telah keluar dari panel admin.');
redirect('/admin/login.php');
