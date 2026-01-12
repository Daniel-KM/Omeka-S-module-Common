<?php declare(strict_types=1);

namespace CommonTest;

use Laminas\ServiceManager\ServiceManager;

/**
 * Abstract base class for Common module tests.
 *
 * Extends AbstractHttpControllerTestCase which provides authentication helpers.
 * Adds convenience methods for accessing services and creating test fixtures.
 */
abstract class AbstractTestCase extends AbstractHttpControllerTestCase
{
    /**
     * Get the service manager.
     */
    protected function getServiceManager(): ServiceManager
    {
        return $this->getApplication()->getServiceManager();
    }

    /**
     * Get a service from the service manager.
     *
     * @param string $name Service name
     * @return mixed
     */
    protected function getService(string $name)
    {
        return $this->getServiceManager()->get($name);
    }

    /**
     * Get the view helper manager.
     */
    protected function getViewHelperManager(): \Laminas\View\HelperPluginManager
    {
        return $this->getService('ViewHelperManager');
    }

    /**
     * Get a view helper by name.
     *
     * @param string $name Helper name
     * @return \Laminas\View\Helper\AbstractHelper
     */
    protected function getViewHelper(string $name)
    {
        return $this->getViewHelperManager()->get($name);
    }

    /**
     * Get the controller plugin manager.
     */
    protected function getControllerPluginManager(): \Laminas\Mvc\Controller\PluginManager
    {
        return $this->getService('ControllerPluginManager');
    }

    /**
     * Get a controller plugin by name.
     *
     * @param string $name Plugin name
     * @return \Laminas\Mvc\Controller\Plugin\AbstractPlugin
     */
    protected function getControllerPlugin(string $name)
    {
        return $this->getControllerPluginManager()->get($name);
    }

    /**
     * Get EasyMeta service.
     */
    protected function getEasyMeta(): \Common\Stdlib\EasyMeta
    {
        return $this->getService('Common\EasyMeta');
    }
}
