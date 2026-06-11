<?php

require_once(dirname(__FILE__, 5) . "/globals.php");

echo (int)($_SESSION['pid'] ?? 0);