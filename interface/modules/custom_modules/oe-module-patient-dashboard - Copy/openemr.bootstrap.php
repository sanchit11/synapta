<?php

/**
 * oe-module-patient-dashboard — Bootstrap
 *
 * Auto-discovered by OpenEMR when this directory is placed under
 *   /openemr/interface/modules/custom_modules/
 *
 * @package   OpenEMR\Modules\PatientDashboard
 */

use OpenEMR\Core\Kernel;
use OpenEMR\Modules\PatientDashboard\Bootstrap;

/**
 * @var Kernel $kernel  Injected by the OpenEMR module loader.
 */
$bootstrap = new Bootstrap($kernel);
$bootstrap->subscribeToEvents();
