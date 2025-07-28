<?php declare(strict_types=1);

namespace Common\Service\ViewHelper;

use Common\View\Helper\DefaultSite;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory to get default site, or the first public, or the first one.
 */
class DefaultSiteFactory implements FactoryInterface
{
    /**
     * Create and return the DefaultSite view helper.
     *
     * @return DefaultSite
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $site = null;
        $api = $services->get('Omeka\ApiManager');
        $defaultSiteId = $services->get('Omeka\Settings')->get('default_site');
        if ($defaultSiteId) {
            try {
                $site = $api->read('sites', ['id' => $defaultSiteId])->getContent();
            } catch (\Exception $e) {
                // Nothing.
                // The site may be private to the user.
            }
        }
        // Fix issues after Omeka install without public site.
        if (empty($site)) {
            // Use a sql query to avoid long process of api and possible issue
            // with module Advanced Search delegator.
            // Use Doctrine DQL to fetch the site entity directly.
            // In most of the cases, the first site is the default one and is
            // public and is already cached in entity manager.
            /** @var \Doctrine\ORM\EntityManager $entityManager */
            $entityManager = $services->get('Omeka\EntityManager');
            $queryBuilder = $entityManager->createQueryBuilder();
            $queryBuilder
                ->select('site')
                ->from(\Omeka\Entity\Site::class, 'site')
                ->orderBy('site.isPublic', 'DESC')
                ->setMaxResults(1);
            $site = $queryBuilder->getQuery()->getOneOrNullResult();
            if ($site) {
                try {
                    $site = $api->read('sites', ['id' => $site->getId()])->getContent();
                } catch (\Exception $e) {
                    // Reset site, because it is probably private to the user.
                    $site = null;
                }
            }
        }
        return new DefaultSite($site);
    }
}
