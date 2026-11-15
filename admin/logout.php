<?php
define('URL_PREFIX', '../');
require dirname(__DIR__) . '/includes/bootstrap.php';

// Token check prevents other sites from logging you out via a hidden link.
if (csrf_valid($_GET['token'] ?? null)) {
    logout_admin();
}
redirect('login.php');
