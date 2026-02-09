<?php declare(strict_types=1);

namespace CommonTest;

use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Entity\Job;

/**
 * Trait for test classes that need to create and clean up Omeka resources.
 *
 * Provides helpers to create items via the API and track them for automatic
 * cleanup. Modules can extend this trait with their own resource types by
 * aliasing cleanupResources:
 *
 * ```php
 * use CommonTest\JobTestTrait;
 *
 * trait MyModuleTestTrait
 * {
 *     use JobTestTrait {
 *         JobTestTrait::cleanupResources as baseCleanupResources;
 *     }
 *
 *     protected function cleanupResources(): void
 *     {
 *         // Clean up module-specific resources first...
 *         $this->baseCleanupResources();
 *     }
 * }
 * ```
 *
 * Requires the using class to extend a Laminas or Omeka test case that
 * provides getApplicationServiceLocator().
 *
 * @see \CommonTest\AbstractHttpControllerTestCase
 * @see \Omeka\Test\AbstractHttpControllerTestCase
 */
trait JobTestTrait
{
    /**
     * Authenticate as admin user.
     *
     * Delegates to loginAsAdmin() if available (CommonTest), otherwise
     * authenticates directly via the adapter.
     */
    protected function loginAdmin(): void
    {
        if (method_exists($this, 'loginAsAdmin')) {
            $this->loginAsAdmin();
            return;
        }

        $services = $this->getApplicationServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');
        if ($auth->hasIdentity()) {
            return;
        }
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Get the service locator.
     *
     * Alias for getApplicationServiceLocator() for convenience.
     *
     * @return \Laminas\ServiceManager\ServiceManager
     */
    protected function getServiceLocator()
    {
        return $this->getApplicationServiceLocator();
    }

    /**
     * Get the API manager.
     *
     * @return \Omeka\Api\Manager
     */
    protected function api()
    {
        return $this->getApplicationServiceLocator()->get('Omeka\ApiManager');
    }

    /**
     * Get the Entity Manager.
     *
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getApplicationServiceLocator()->get('Omeka\EntityManager');
    }

    /**
     * @var array List of created item IDs for cleanup.
     */
    protected array $createdItemIds = [];

    /**
     * @var array List of created item set IDs for cleanup.
     */
    protected array $createdItemSetIds = [];

    /**
     * Create a test item via the API and track it for cleanup.
     *
     * Property terms (e.g. 'dcterms:title') are resolved to property_id
     * automatically so callers can use the simpler term-based format.
     *
     * @param array $data Item data with property terms as keys.
     * @return ItemRepresentation
     */
    protected function createTrackedItem(array $data): ItemRepresentation
    {
        $data = $this->resolvePropertyIds($data);
        $response = $this->api()->create('items', $data);
        $item = $response->getContent();
        $this->createdItemIds[] = $item->id();
        return $item;
    }

    /**
     * Create a test item set via the API and track it for cleanup.
     *
     * @param array $data Item set data.
     * @return \Omeka\Api\Representation\ItemSetRepresentation
     */
    protected function createTrackedItemSet(array $data)
    {
        $data = $this->resolvePropertyIds($data);
        $response = $this->api()->create('item_sets', $data);
        $itemSet = $response->getContent();
        $this->createdItemSetIds[] = $itemSet->id();
        return $itemSet;
    }

    /**
     * Resolve property terms to property_id in resource data.
     *
     * The Omeka API requires each value to have 'property_id'. This helper
     * detects keys that look like property terms (prefix:localName) and adds
     * the resolved property_id to each value that lacks one.
     *
     * @param array $data Resource data.
     * @return array Data with property_id added to values.
     */
    protected function resolvePropertyIds(array $data): array
    {
        $easyMeta = $this->getApplicationServiceLocator()->get('Common\EasyMeta');
        foreach ($data as $key => $values) {
            if (!is_array($values) || !preg_match('/^[a-z][a-z0-9]*:[a-zA-Z]/', $key)) {
                continue;
            }
            $propertyId = $easyMeta->propertyId($key);
            if (!$propertyId) {
                continue;
            }
            foreach ($values as &$value) {
                if (is_array($value) && !isset($value['property_id'])) {
                    $value['property_id'] = $propertyId;
                }
            }
            unset($value);
            $data[$key] = $values;
        }
        return $data;
    }

    /**
     * Run an Omeka job synchronously in-process.
     *
     * Creates a Job entity, instantiates the job class, and calls perform().
     * The job status is updated to COMPLETED or ERROR accordingly.
     *
     * @param string $jobClass Fully qualified job class name.
     * @param array $args Job arguments.
     * @return Job The job entity after execution.
     */
    protected function runJob(string $jobClass, array $args = []): Job
    {
        $services = $this->getApplicationServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $auth = $services->get('Omeka\AuthenticationService');

        $job = new Job();
        $job->setClass($jobClass);
        $job->setArgs($args);
        $job->setStatus(Job::STATUS_IN_PROGRESS);
        $job->setStarted(new \DateTime('now'));
        if ($auth->hasIdentity()) {
            $job->setOwner($auth->getIdentity());
        }

        $entityManager->persist($job);
        $entityManager->flush();

        try {
            $jobInstance = new $jobClass($job, $services);
            $jobInstance->perform();
            // Only mark completed if the job didn't change its own status
            // (some jobs set STATUS_ERROR internally without throwing).
            if ($job->getStatus() === Job::STATUS_IN_PROGRESS) {
                $job->setStatus(Job::STATUS_COMPLETED);
            }
        } catch (\Exception $e) {
            $job->setStatus(Job::STATUS_ERROR);
            $job->setLog((string) $e);
        }

        $job->setEnded(new \DateTime('now'));
        $entityManager->flush();

        return $job;
    }

    /**
     * Clean up all tracked resources.
     *
     * Deletes items and item sets created during the test in reverse order.
     */
    protected function cleanupResources(): void
    {
        // Delete items first (they may reference item sets).
        foreach (array_reverse($this->createdItemIds) as $id) {
            try {
                $this->api()->delete('items', $id);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdItemIds = [];

        foreach (array_reverse($this->createdItemSetIds) as $id) {
            try {
                $this->api()->delete('item_sets', $id);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdItemSetIds = [];
    }
}
