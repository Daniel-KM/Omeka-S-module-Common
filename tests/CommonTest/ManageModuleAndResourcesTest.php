<?php declare(strict_types=1);

namespace CommonTest;

use Common\ManageModuleAndResources;

/**
 * Functional tests for ManageModuleAndResources class.
 *
 * Tests the helper methods and resource management functionality.
 */
class ManageModuleAndResourcesTest extends AbstractTestCase
{
    protected ManageModuleAndResources $manager;

    public function setUp(): void
    {
        parent::setUp();
        $this->manager = new ManageModuleAndResources($this->getServiceManager());
    }

    // =========================================================================
    // Invocation test
    // =========================================================================

    public function testInvoke(): void
    {
        $result = $this->manager->__invoke();
        $this->assertSame($this->manager, $result);
    }

    // =========================================================================
    // fileDataPath tests
    // =========================================================================

    public function testFileDataPathWithEmptyString(): void
    {
        $result = $this->manager->fileDataPath('');
        $this->assertNull($result);
    }

    public function testFileDataPathWithHttpUrl(): void
    {
        $url = 'http://example.com/file.json';
        $result = $this->manager->fileDataPath($url);
        $this->assertSame($url, $result);
    }

    public function testFileDataPathWithHttpsUrl(): void
    {
        $url = 'https://example.com/file.json';
        $result = $this->manager->fileDataPath($url);
        $this->assertSame($url, $result);
    }

    public function testFileDataPathWithWhitespace(): void
    {
        $url = '  https://example.com/file.json  ';
        $result = $this->manager->fileDataPath($url);
        $this->assertSame(trim($url), $result);
    }

    public function testFileDataPathWithRelativePathNoModule(): void
    {
        $result = $this->manager->fileDataPath('somefile.json');
        $this->assertNull($result);
    }

    public function testFileDataPathWithNonExistentFile(): void
    {
        $result = $this->manager->fileDataPath('nonexistent.json', 'Common', 'test');
        $this->assertNull($result);
    }

    // =========================================================================
    // checkStringsInFiles tests
    // =========================================================================

    public function testCheckStringsInFilesWithEmptyStrings(): void
    {
        $result = $this->manager->checkStringsInFiles([]);
        $this->assertSame([], $result);
    }

    public function testCheckStringsInFilesWithEmptyString(): void
    {
        $result = $this->manager->checkStringsInFiles('');
        $this->assertSame([], $result);
    }

    public function testCheckStringsInFilesWithDoubleDots(): void
    {
        $result = $this->manager->checkStringsInFiles(['test'], '../../../etc/passwd');
        $this->assertNull($result);
    }

    public function testCheckStringsInFilesWithDotSlash(): void
    {
        $result = $this->manager->checkStringsInFiles(['test'], './hidden');
        $this->assertNull($result);
    }

    public function testCheckStringsInFilesWithAbsolutePathOutsideOmeka(): void
    {
        $result = $this->manager->checkStringsInFiles(['test'], '/etc/*');
        $this->assertNull($result);
    }

    // =========================================================================
    // Vocabulary check tests
    // =========================================================================

    public function testCheckVocabularyWithDcterms(): void
    {
        $data = [
            'vocabulary' => [
                'o:namespace_uri' => 'http://purl.org/dc/terms/',
                'o:prefix' => 'dcterms',
                'o:label' => 'Dublin Core',
                'o:comment' => 'Dublin Core Metadata Terms',
            ],
            // Mark as already checked to avoid file reading.
            'is_checked' => true,
        ];
        // dcterms already exists, so this should return true
        $result = $this->manager->checkVocabulary($data);
        $this->assertTrue($result);
    }

    public function testCheckVocabularyWithNonExistent(): void
    {
        $data = [
            'vocabulary' => [
                'o:namespace_uri' => 'http://nonexistent.example.org/vocab/',
                'o:prefix' => 'nonexistent',
                'o:label' => 'Non-existent Vocabulary',
                'o:comment' => 'Test',
            ],
            'is_checked' => true,
            'strategy' => 'url',
            'options' => [
                'url' => 'http://example.org/nonexistent.rdf',
            ],
        ];
        $result = $this->manager->checkVocabulary($data);
        $this->assertFalse($result);
    }

    /**
     * A property loaded before a rename via sql should not be created again by
     * the update of the vocabulary.
     */
    public function testUpdateVocabularyAfterRenameViaSql(): void
    {
        $this->loginAsAdmin();
        $connection = $this->getService('Omeka\Connection');
        $data = [
            'vocabulary' => [
                'o:namespace_uri' => 'https://example.org/vocabs/stale/',
                'o:prefix' => 'stale',
                'o:label' => 'Stale',
                'o:comment' => 'Test',
            ],
            'is_checked' => true,
            'strategy' => 'file',
            'options' => [
                'file' => dirname(__DIR__) . '/fixtures/vocabularies/stale.ttl',
                'format' => 'turtle',
            ],
        ];
        try {
            $this->assertTrue($this->manager->createOrUpdateVocabulary($data));
            $vocabularyId = (int) $connection->fetchOne('SELECT `id` FROM `vocabulary` WHERE `prefix` = "stale"');
            $this->getEntityManager()->clear();

            $connection->executeStatement('UPDATE `property` SET `local_name` = "dateStart" WHERE `vocabulary_id` = ? AND `local_name` = "start"', [$vocabularyId]);
            $this->api()->read('properties', ['vocabulary' => $vocabularyId, 'localName' => 'dateStart']);
            $connection->executeStatement('UPDATE `property` SET `local_name` = "start" WHERE `vocabulary_id` = ? AND `local_name` = "dateStart"', [$vocabularyId]);

            $this->assertTrue($this->manager->createOrUpdateVocabulary($data));
            $names = $connection->fetchFirstColumn('SELECT `local_name` FROM `property` WHERE `vocabulary_id` = ? ORDER BY `local_name`', [$vocabularyId]);
            $this->assertSame(['end', 'start'], $names);
        } finally {
            foreach (['property', 'resource_class'] as $table) {
                $connection->executeStatement("DELETE `t` FROM `$table` `t` JOIN `vocabulary` `v` ON `v`.`id` = `t`.`vocabulary_id` WHERE `v`.`prefix` = 'stale'");
            }
            $connection->executeStatement('DELETE FROM `vocabulary` WHERE `prefix` = "stale"');
            $this->getEntityManager()->clear();
        }
    }

    // =========================================================================
    // Resource template check tests
    // =========================================================================

    public function testCheckResourceTemplateWithNonExistentFile(): void
    {
        $result = $this->manager->checkResourceTemplate('/nonexistent/path/template.json');
        $this->assertFalse($result);
    }

    // =========================================================================
    // clearCaches test
    // =========================================================================

    public function testClearCaches(): void
    {
        // Just verify it doesn't throw an exception
        $this->manager->clearCaches();
        $this->assertTrue(true);
    }
}
