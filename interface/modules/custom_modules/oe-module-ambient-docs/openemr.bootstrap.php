<?php
/**
 * Ambient Clinical Documentation Module
 * Bootstrap entry point — OpenEMR loads this file automatically
 * when the module is registered and enabled.
 */

// Autoload our classes via Composer
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Load environment variables from .env file
if (class_exists('\Dotenv\Dotenv')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

// Boot the module
use Clinic\OeModuleAmbientDocs\Bootstrap;

$bootstrap = new Bootstrap();
$bootstrap->subscribeToEvents();