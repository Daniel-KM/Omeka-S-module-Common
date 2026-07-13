<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

use Common\Stdlib\MessagePreparerInterface;
use Common\Stdlib\MessagePreparerTrait;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Response as ApiResponse;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Mailer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the placeholders interpolation of MessagePreparerTrait, with
 * an emphasis on the single and multiple resources placeholders and on the fact
 * that the resource urls must be absolute (canonical), since a message is read
 * outside of any http request (mail, job).
 *
 * The trait is exercised through a lightweight host (below) that injects mocked
 * collaborators and a deterministic urlFromRoute(), so no database nor Omeka
 * application is required. The fakes honour the "canonical"/"force_canonical"
 * flag by prefixing HOST, so a missing flag would surface as a relative url.
 */
class MessagePreparerTraitTest extends TestCase
{
    /**
     * Host added by the fakes when an absolute (canonical) url is requested.
     */
    private const HOST = 'http://example.org';

    private function preparer(array $resources = [], ?Settings $settings = null): TestableMessagePreparer
    {
        $mailer = $this->createMock(Mailer::class);
        $mailer->method('getInstallationTitle')->willReturn('Test Install');

        $response = $this->createMock(ApiResponse::class);
        $response->method('getContent')->willReturn($resources);
        $api = $this->createMock(ApiManager::class);
        $api->method('search')->willReturn($response);

        return new TestableMessagePreparer($api, $mailer, $settings ?? $this->createMock(Settings::class));
    }

    private function site(): FakeSite
    {
        return new FakeSite('my-site', 'My Site', '/s/my-site');
    }

    // =========================================================================
    // Basic interpolation
    // =========================================================================

    public function testEmptyMessageReturnsEmpty(): void
    {
        $this->assertSame('', $this->preparer()->fillMessage(''));
        $this->assertSame('', $this->preparer()->fillMessage(null));
    }

    public function testCommonPlaceholders(): void
    {
        $out = $this->preparer()->fillMessage(
            'Hello on {main_title} ({main_url}), site {site_title} at {site_url}.',
            [],
            ['site' => $this->site()]
        );
        // main_url and site_url are requested canonical, so they are absolute.
        $this->assertStringContainsString('Hello on Test Install (http://example.org/top)', $out);
        $this->assertStringContainsString('site My Site at http://example.org/s/my-site', $out);
    }

    public function testExplicitPlaceholdersOverrideAndExtraKeys(): void
    {
        $out = $this->preparer()->fillMessage(
            'From {name} <{email}>: {subject}',
            ['name' => 'Jane', 'email' => 'jane@example.org', 'subject' => 'Hi']
        );
        $this->assertSame('From Jane <jane@example.org>: Hi', $out);
    }

    public function testUnknownCommonPlaceholdersDefaultToEmpty(): void
    {
        // {resource_link} and {resources} are known defaults: with no resource
        // in context they must resolve to an empty string, not stay as braces.
        $out = $this->preparer()->fillMessage('[{resource_link}][{resources}]');
        $this->assertSame('[][]', $out);
    }

    public function testUrlEncodedBracesAreFixed(): void
    {
        $out = $this->preparer()->fillMessage('%7Bname%7D', ['name' => 'Bob']);
        $this->assertSame('Bob', $out);
    }

    public function testArrayAndObjectPlaceholdersAreSkipped(): void
    {
        $out = $this->preparer()->fillMessage(
            '{scalar}{arr}',
            ['scalar' => 'ok', 'arr' => ['x'], 'obj' => (object) []]
        );
        // {arr} is not scalar: it stays untouched (no default for it).
        $this->assertSame('ok{arr}', $out);
    }

    public function testOwnerAliases(): void
    {
        $out = $this->preparer()->fillMessage(
            '{owner_name} {owner_email} {name} {email}',
            [],
            ['owner' => new FakeOwner('Owner', 'owner@example.org')]
        );
        $this->assertSame('Owner owner@example.org Owner owner@example.org', $out);
    }

    // =========================================================================
    // Single resource
    // =========================================================================

    public function testSingleResourcePlaceholders(): void
    {
        $resource = new FakeResource(42, 'item', 'Solo Title', ['dcterms:title' => 'Solo Title'], '/s/my-site/item/42', '/admin/item/42');
        $out = $this->preparer()->fillMessage(
            'id={resource_id} title={resource_title} url={resource_url} link={resource_link} term={dcterms:title}',
            [],
            ['resource' => $resource]
        );
        $this->assertStringContainsString('id=42', $out);
        $this->assertStringContainsString('title=Solo Title', $out);
        $this->assertStringContainsString('url=http://example.org/s/my-site/item/42', $out);
        $this->assertStringContainsString('link=<a href="http://example.org/s/my-site/item/42">Solo Title</a>', $out);
        $this->assertStringContainsString('term=Solo Title', $out);
    }

    /**
     * The resource urls come from siteUrl(null, true)/adminUrl(null, true), so
     * they must be absolute (canonical), never relative: a relative link would
     * be broken once the message is read in a mail client.
     */
    public function testSingleResourceUrlsAreAbsolute(): void
    {
        $resource = new FakeResource(9, 'item', 'Doc', [], '/s/my-site/item/9', '/admin/item/9');
        $out = $this->preparer()->fillMessage(
            '{resource_url}|{resource}|{resource_url_admin}|{resource_link}',
            [],
            ['resource' => $resource]
        );
        [$url, $res, $admin, $link] = explode('|', $out);

        $this->assertStringStartsWith('http://', $url);
        $this->assertSame('http://example.org/s/my-site/item/9', $url);
        // {resource} is an alias of the absolute {resource_url}.
        $this->assertSame($url, $res);
        $this->assertStringStartsWith('http://', $admin);
        $this->assertSame('http://example.org/admin/item/9', $admin);
        $this->assertStringContainsString('href="http://example.org/s/my-site/item/9"', $link);
    }

    public function testSingleResourcePropertyValue(): void
    {
        $resource = new FakeResource(1, 'item', 'Title', [
            'dcterms:title' => 'A title',
            'dcterms:creator' => 'Ada Lovelace',
        ], '/u', '/a');
        $out = $this->preparer()->fillMessage(
            'creator={dcterms:creator} title={dcterms:title}',
            [],
            ['resource' => $resource]
        );
        $this->assertSame('creator=Ada Lovelace title=A title', $out);
    }

    public function testSingleResourceMissingPropertyTermIsEmpty(): void
    {
        $resource = new FakeResource(1, 'item', 'T', [], '/u', '/a');
        $out = $this->preparer()->fillMessage('[{dcterms:description}]', [], ['resource' => $resource]);
        $this->assertSame('[]', $out);
    }

    // =========================================================================
    // Multiple resources
    // =========================================================================

    public function testMultipleResourcesUrlsIdsAndLinks(): void
    {
        $resources = [
            new FakeResource(1, 'item', 'Alpha', ['dcterms:title' => 'Alpha']),
            new FakeResource(3, 'item', 'Beta', ['dcterms:title' => 'Beta']),
        ];
        $out = $this->preparer($resources)->fillMessage(
            "urls={resources}\nids={resources_ids}\nlinks={resources_links}",
            [],
            ['site' => $this->site(), 'resource_ids' => [1, 3]]
        );

        $this->assertStringContainsString(
            'urls=http://example.org/site/resource-id/my-site/item/1, http://example.org/site/resource-id/my-site/item/3',
            $out
        );
        $this->assertStringContainsString('ids=1, 3', $out);
        $this->assertStringContainsString(
            'links=<a href="http://example.org/site/resource-id/my-site/item/1">Alpha</a>, <a href="http://example.org/site/resource-id/my-site/item/3">Beta</a>',
            $out
        );
    }

    /**
     * Every multiple-resources url is built with force_canonical, so the list,
     * the links and the browse urls must all be absolute.
     */
    public function testMultipleResourcesUrlsAreAbsolute(): void
    {
        $resources = [
            new FakeResource(1, 'item', 'Alpha'),
            new FakeResource(3, 'item', 'Beta'),
        ];
        $out = $this->preparer($resources)->fillMessage(
            '{resources}|{resources_links}|{resources_url}|{resources_url_admin}',
            [],
            ['site' => $this->site(), 'resource_ids' => [1, 3]]
        );
        [$list, $links, $pub, $adm] = explode('|', $out);

        foreach (explode(', ', $list) as $url) {
            $this->assertStringStartsWith('http://', $url);
        }
        $this->assertStringNotContainsString('href="/', $links);
        $this->assertStringContainsString('href="http://example.org/', $links);
        $this->assertStringStartsWith('http://', $pub);
        $this->assertStringStartsWith('http://', $adm);
    }

    public function testMultipleResourcesBrowseUrls(): void
    {
        $resources = [
            new FakeResource(1, 'item', 'Alpha'),
            new FakeResource(3, 'item', 'Beta'),
        ];
        $out = $this->preparer($resources)->fillMessage(
            'pub={resources_url} adm={resources_url_admin}',
            [],
            ['site' => $this->site(), 'resource_ids' => [1, 3]]
        );
        // A single browse url per controller (both items), carrying all ids.
        $this->assertStringContainsString('pub=http://example.org/site/resource/my-site/item?id=1,3', $out);
        $this->assertStringContainsString('adm=http://example.org/admin/default/item?id=1,3', $out);
    }

    public function testMultipleResourcesBrowseUrlsGroupedByController(): void
    {
        $resources = [
            new FakeResource(1, 'item', 'Alpha'),
            new FakeResource(5, 'item-set', 'Coll'),
            new FakeResource(3, 'item', 'Beta'),
        ];
        $out = $this->preparer($resources)->fillMessage(
            '{resources_url}',
            [],
            ['site' => $this->site(), 'resource_ids' => [1, 5, 3]]
        );
        // Items grouped together, item-set on its own browse url.
        $this->assertStringContainsString('http://example.org/site/resource/my-site/item?id=1,3', $out);
        $this->assertStringContainsString('http://example.org/site/resource/my-site/item-set?id=5', $out);
    }

    public function testResourcesPropertyTermPlaceholder(): void
    {
        $resources = [
            new FakeResource(1, 'item', 'Alpha', ['dcterms:title' => 'Alpha', 'dcterms:creator' => 'Ada']),
            new FakeResource(3, 'item', 'Beta', ['dcterms:title' => 'Beta']),
        ];
        $out = $this->preparer($resources)->fillMessage(
            'titles={resources::dcterms:title} creators={resources::dcterms:creator}',
            [],
            ['site' => $this->site(), 'resource_ids' => [1, 3]]
        );
        $this->assertStringContainsString('titles=Alpha, Beta', $out);
        // Only the resource carrying the term contributes a value.
        $this->assertStringContainsString('creators=Ada', $out);
    }

    public function testMultipleResourcesIgnoredWithoutSite(): void
    {
        $resources = [new FakeResource(1, 'item', 'Alpha')];
        $out = $this->preparer($resources)->fillMessage(
            '[{resources}][{resources_ids}]',
            [],
            ['resource_ids' => [1]]
        );
        // No site: the multiple-resources placeholders fall back to empty.
        $this->assertSame('[][]', $out);
    }

    public function testResourceIdsAcceptsScalar(): void
    {
        $resources = [new FakeResource(7, 'item', 'Solo')];
        $out = $this->preparer($resources)->fillMessage(
            '{resources_ids}',
            [],
            ['site' => $this->site(), 'resource_ids' => 7]
        );
        $this->assertSame('7', $out);
    }

    // =========================================================================
    // Additional placeholders, validation and default subject
    // =========================================================================

    public function testAddAndClearPlaceholders(): void
    {
        $preparer = $this->preparer();
        $preparer->addPlaceholders(['custom' => 'Value']);
        $this->assertSame('Value', $preparer->fillMessage('{custom}'));
        $preparer->clearPlaceholders();
        // {custom} is not a known default, so it stays untouched once cleared.
        $this->assertSame('{custom}', $preparer->fillMessage('{custom}'));
    }

    public function testValidateBody(): void
    {
        $preparer = $this->preparer();
        $this->assertFalse($preparer->validateBody('')['valid']);
        $this->assertFalse($preparer->validateBody('   ')['valid']);
        $this->assertTrue($preparer->validateBody('Hello')['valid']);
        $this->assertFalse($preparer->validateBody(str_repeat('a', 11), 10)['valid']);
    }

    public function testValidateSubject(): void
    {
        $preparer = $this->preparer();
        $this->assertTrue($preparer->validateSubject('Short')['valid']);
        $this->assertFalse($preparer->validateSubject(str_repeat('a', 80), 78)['valid']);
        // The absolute cap wins over a larger requested max.
        $this->assertFalse($preparer->validateSubject(str_repeat('a', 200), 500)['valid']);
    }

    public function testGetDefaultSubjectUsesSettingThenFallback(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('get')->willReturnMap([
            ['contactus_subject', null, 'Configured {name}'],
            ['missing_subject', null, null],
        ]);
        $preparer = $this->preparer([], $settings);

        $this->assertSame(
            'Configured Jane',
            $preparer->getDefaultSubject('contactus_subject', 'Default {name}', ['name' => 'Jane'])
        );
        $this->assertSame(
            'Default Jane',
            $preparer->getDefaultSubject('missing_subject', 'Default {name}', ['name' => 'Jane'])
        );
    }
}

/**
 * Host of the trait, with a deterministic urlFromRoute() that mimics Omeka: it
 * prefixes an absolute host only when force_canonical is set, so a test can
 * assert that the trait requested an absolute url.
 */
class TestableMessagePreparer implements MessagePreparerInterface
{
    use MessagePreparerTrait;

    private const HOST = 'http://example.org';

    public function __construct($api, $mailer, $settings)
    {
        $this->api = $api;
        $this->mailer = $mailer;
        $this->settings = $settings;
    }

    protected function urlFromRoute(string $route, array $params = [], array $options = []): string
    {
        $url = (empty($options['force_canonical']) ? '' : self::HOST) . '/' . $route;
        foreach ($params as $value) {
            $url .= '/' . $value;
        }
        if (!empty($options['query'])) {
            $pairs = [];
            foreach ($options['query'] as $key => $value) {
                $pairs[] = $key . '=' . $value;
            }
            $url .= '?' . implode('&', $pairs);
        }
        return $url;
    }
}

class FakeValue
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

/**
 * siteUrl()/adminUrl() return an absolute url only when canonical is requested,
 * exactly like an Omeka resource representation, so the trait's use of the
 * canonical flag is observable.
 */
class FakeResource
{
    private const HOST = 'http://example.org';

    public function __construct(
        private int $id,
        private string $controller,
        private string $title,
        private array $values = [],
        private string $sitePath = '',
        private string $adminPath = ''
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function getControllerName(): string
    {
        return $this->controller;
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    public function value($term, array $options = [])
    {
        return isset($this->values[$term]) ? new FakeValue($this->values[$term]) : null;
    }

    public function siteUrl($siteSlug = null, $canonical = false): string
    {
        return ($canonical ? self::HOST : '') . $this->sitePath;
    }

    public function adminUrl($action = null, $canonical = false): string
    {
        return ($canonical ? self::HOST : '') . $this->adminPath;
    }
}

class FakeSite
{
    private const HOST = 'http://example.org';

    public function __construct(
        private string $slug,
        private string $title,
        private string $path
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function siteUrl($locale = null, $canonical = false): string
    {
        return ($canonical ? self::HOST : '') . $this->path;
    }
}

class FakeOwner
{
    public function __construct(private string $name, private string $email)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }
}
