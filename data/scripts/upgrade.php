<?php declare(strict_types=1);

namespace Common;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Laminas\Mvc\Controller\Plugin\Url $url
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$url = $plugins->get('url');
// Api is not available when upgrading with module AdvancedSearch.
// $api = $plugins->get('api');
// $config = require dirname(__DIR__, 2) . '/config/module.config.php';
// $settings = $services->get('Omeka\Settings');
// $translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
// $entityManager = $services->get('Omeka\EntityManager');

$this->preparePsrMessage();
$this->checkExtensionIntl();
$this->fixIndexes();
$this->checkGeneric();
