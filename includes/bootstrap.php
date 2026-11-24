<?php
/** Loaded at the top of every page. */
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/helpers.php';

send_security_headers();
start_secure_session();
