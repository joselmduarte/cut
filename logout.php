<?php
require __DIR__ . '/db.php';
logout_user();
header('Location: login.php');
exit;
