<?php

use OpenEMR\Core\ModulesClassLoader;

/**
 * Bootstrap for Synapta Provider Dashboard module
 */
$bootstrap = function (ModulesClassLoader $classLoader) {
    $classLoader->registerNamespaceIfNotExists(
        "Synapta\\OeModulePhysicianDashboard\\",
        __DIR__ . DIRECTORY_SEPARATOR . "src"
    );

    /**
     * @var EventDispatcher $eventDispatcher
     */
    $eventDispatcher = $GLOBALS['kernel']->getEventDispatcher();

    // Load the Bootstrap class which wires into OpenEMR's events
    $moduleBootstrap = new \Synapta\OeModulePhysicianDashboard\Bootstrap(
        $eventDispatcher,
        $GLOBALS['kernel']
    );
    $moduleBootstrap->subscribeToEvents();
};