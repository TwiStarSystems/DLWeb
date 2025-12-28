<?php
/**
 * Logout Handler
 */
require_once 'auth.php';

auth()->logout();

// Redirect to homepage or login page
header('Location: /login.php');
exit;
