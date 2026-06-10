<?php

/**
 * Bootstrap — Patient Dashboard Module
 *
 * @package OpenEMR\Modules\PatientDashboard
 */

namespace OpenEMR\Modules\PatientDashboard;

use OpenEMR\Core\Kernel;
use OpenEMR\Events\PatientPortal\RenderEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Bootstrap
{
    /** @var Kernel */
    private Kernel $kernel;

    /** @var EventDispatcherInterface */
    private EventDispatcherInterface $dispatcher;

    public function __construct(Kernel $kernel)
    {
        $this->kernel     = $kernel;
        $this->dispatcher = $kernel->getEventDispatcher();
    }

    public function subscribeToEvents(): void
    {
        $this->dispatcher->addListener(
            RenderEvent::EVENT_SECTION_NAV_REGISTERED,
            [$this, 'onPortalPageRender']
        );

        $this->dispatcher->addListener(
            RenderEvent::EVENT_PORTAL_HEADER_RENDER,
            [$this, 'onPortalHeaderRender']
        );
    }

    public function onPortalHeaderRender(RenderEvent $event): void
    {
        if (!$this->isPortalHome()) {
            return;
        }

        $path = $this->getModulePublicPath();
        echo '<link rel="stylesheet" href="' . $path . '/css/synapta-portal.css">';
        echo '<script defer src="' . $path . '/js/synapta-portal.js"></script>';
    }

    public function onPortalPageRender(RenderEvent $event): void
    {
        if (!$this->isPortalHome()) {
            return;
        }

        $controller = new Controller\DashboardController();
        $controller->render();
    }

    private function isPortalHome(): bool
    {
        $page = basename($_SERVER['PHP_SELF'] ?? '');
        return in_array($page, ['home.php', 'index.php', 'portal_home.php'], true);
    }

    private function getModulePublicPath(): string
    {
        return $GLOBALS['web_root']
            . '/interface/modules/custom_modules/oe-module-patient-dashboard/public';
    }
}
