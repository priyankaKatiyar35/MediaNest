<?php
require_once __DIR__ . '/auth.php';
logoutUser();
header('Location: ../index.html');
exit;