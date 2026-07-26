<?php
/**
 * Bootstrap — included at the top of every page
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/security_patterns.php';
require_once __DIR__ . '/security_logger.php';
require_once __DIR__ . '/security.php';
securityGuardRequest();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/grades.php';
require_once __DIR__ . '/helpers.php';
