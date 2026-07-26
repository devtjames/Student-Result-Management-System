<?php
require_once __DIR__ . '/../includes/bootstrap.php';

student_logout();
redirect(BASE_URL . '/student_login.php');
