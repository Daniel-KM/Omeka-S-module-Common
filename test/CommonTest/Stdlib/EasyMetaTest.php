<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

use CommonTest\AbstractTestCase;

/**
 * Functional tests for EasyMeta class.
 *
 * These tests require a database connection and module installation.
 */
class EasyMetaTest extends AbstractTestCase
{
    protected \Common\Stdlib\EasyMeta $easyMeta;

    public function setUp(): void
    {
        parent::setUp();
        $this->easyMeta = $this->getEasyMeta();
    }

    // =========================================================================
    // Property tests
    // =========================================================================

    public function testPropertyIdByTerm(): void
    {
        $id = $this->easyMeta->propertyId('dcterms:title');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testPropertyIdByInvalidTerm(): void
    {
        $id = $this->easyMeta->propertyId('invalid:property');
        $this->assertNull($id);
    }

    public function testPropertyIdByNumericId(): void
    {
        // Get a known property ID first
        $titleId = $this->easyMeta->propertyId('dcterms:title');
        // Then look it up by ID
        $id = $this->easyMeta->propertyId($titleId);
        $this->assertSame($titleId, $id);
    }

    public function testPropertyTermById(): void
    {
        $titleId = $this->easyMeta->propertyId('dcterms:title');
        $term = $this->easyMeta->propertyTerm($titleId);
        $this->assertSame('dcterms:title', $term);
    }

    public function testPropertyTermByTerm(): void
    {
        $term = $this->easyMeta->propertyTerm('dcterms:title');
        $this->assertSame('dcterms:title', $term);
    }

    public function testPropertyTermByInvalidId(): void
    {
        $term = $this->easyMeta->propertyTerm(999999);
        $this->assertNull($term);
    }

    public function testPropertyIds(): void
    {
        $ids = $this->easyMeta->propertyIds(['dcterms:title', 'dcterms:description']);
        $this->assertIsArray($ids);
        $this->assertCount(2, $ids);
        $this->assertArrayHasKey('dcterms:title', $ids);
        $this->assertArrayHasKey('dcterms:description', $ids);
    }

    public function testPropertyTerms(): void
    {
        $titleId = $this->easyMeta->propertyId('dcterms:title');
        $descId = $this->easyMeta->propertyId('dcterms:description');

        $terms = $this->easyMeta->propertyTerms([$titleId, $descId]);
        $this->assertIsArray($terms);
        $this->assertContains('dcterms:title', $terms);
        $this->assertContains('dcterms:description', $terms);
    }

    public function testPropertyLabel(): void
    {
        $label = $this->easyMeta->propertyLabel('dcterms:title');
        $this->assertIsString($label);
        $this->assertNotEmpty($label);
    }

    public function testPropertyLabelById(): void
    {
        $titleId = $this->easyMeta->propertyId('dcterms:title');
        $label = $this->easyMeta->propertyLabel($titleId);
        $this->assertIsString($label);
        $this->assertNotEmpty($label);
    }

    // =========================================================================
    // Resource class tests
    // =========================================================================

    public function testResourceClassIdByTerm(): void
    {
        $id = $this->easyMeta->resourceClassId('dctype:Image');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testResourceClassIdByInvalidTerm(): void
    {
        $id = $this->easyMeta->resourceClassId('invalid:class');
        $this->assertNull($id);
    }

    public function testResourceClassTermById(): void
    {
        $classId = $this->easyMeta->resourceClassId('dctype:Image');
        $term = $this->easyMeta->resourceClassTerm($classId);
        $this->assertSame('dctype:Image', $term);
    }

    public function testResourceClassIds(): void
    {
        $ids = $this->easyMeta->resourceClassIds(['dctype:Image', 'dctype:Text']);
        $this->assertIsArray($ids);
        $this->assertArrayHasKey('dctype:Image', $ids);
        $this->assertArrayHasKey('dctype:Text', $ids);
    }

    public function testResourceClassLabel(): void
    {
        $label = $this->easyMeta->resourceClassLabel('dctype:Image');
        $this->assertIsString($label);
        $this->assertNotEmpty($label);
    }

    // =========================================================================
    // Vocabulary tests
    // =========================================================================

    public function testVocabularyIdByPrefix(): void
    {
        $id = $this->easyMeta->vocabularyId('dcterms');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testVocabularyIdByUri(): void
    {
        $id = $this->easyMeta->vocabularyId('http://purl.org/dc/terms/');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testVocabularyPrefix(): void
    {
        $prefix = $this->easyMeta->vocabularyPrefix('http://purl.org/dc/terms/');
        $this->assertSame('dcterms', $prefix);
    }

    public function testVocabularyPrefixById(): void
    {
        $vocabId = $this->easyMeta->vocabularyId('dcterms');
        $prefix = $this->easyMeta->vocabularyPrefix($vocabId);
        $this->assertSame('dcterms', $prefix);
    }

    public function testVocabularyUri(): void
    {
        $uri = $this->easyMeta->vocabularyUri('dcterms');
        $this->assertSame('http://purl.org/dc/terms/', $uri);
    }

    public function testVocabularyLabel(): void
    {
        $label = $this->easyMeta->vocabularyLabel('dcterms');
        $this->assertIsString($label);
        $this->assertNotEmpty($label);
    }

    // =========================================================================
    // Resource name tests
    // =========================================================================

    public function testResourceName(): void
    {
        $this->assertSame('items', $this->easyMeta->resourceName('items'));
        $this->assertSame('items', $this->easyMeta->resourceName('item'));
        $this->assertSame('items', $this->easyMeta->resourceName('Item'));
        $this->assertSame('items', $this->easyMeta->resourceName('Omeka\Entity\Item'));
    }

    public function testResourceNameForMedia(): void
    {
        $this->assertSame('media', $this->easyMeta->resourceName('media'));
        $this->assertSame('media', $this->easyMeta->resourceName('Omeka\Entity\Media'));
    }

    public function testResourceNameForItemSets(): void
    {
        $this->assertSame('item_sets', $this->easyMeta->resourceName('item_sets'));
        $this->assertSame('item_sets', $this->easyMeta->resourceName('ItemSet'));
        $this->assertSame('item_sets', $this->easyMeta->resourceName('Omeka\Entity\ItemSet'));
    }

    public function testResourceNameInvalid(): void
    {
        $this->assertNull($this->easyMeta->resourceName('invalid_resource'));
    }

    public function testResourceNames(): void
    {
        $names = $this->easyMeta->resourceNames(['items', 'media', 'item_sets']);
        $this->assertIsArray($names);
        $this->assertContains('items', $names);
        $this->assertContains('media', $names);
        $this->assertContains('item_sets', $names);
    }

    // =========================================================================
    // Data type tests
    // =========================================================================

    public function testDataTypeName(): void
    {
        $name = $this->easyMeta->dataTypeName('literal');
        $this->assertSame('literal', $name);
    }

    public function testDataTypeNameUri(): void
    {
        $name = $this->easyMeta->dataTypeName('uri');
        $this->assertSame('uri', $name);
    }

    public function testDataTypeNameResource(): void
    {
        $name = $this->easyMeta->dataTypeName('resource');
        $this->assertSame('resource', $name);
    }

    public function testDataTypeNameInvalid(): void
    {
        $name = $this->easyMeta->dataTypeName('invalid_type');
        $this->assertNull($name);
    }

    public function testDataTypeNames(): void
    {
        $names = $this->easyMeta->dataTypeNames(['literal', 'uri', 'resource']);
        $this->assertIsArray($names);
        $this->assertContains('literal', $names);
        $this->assertContains('uri', $names);
        $this->assertContains('resource', $names);
    }

    public function testDataTypeLabels(): void
    {
        $labels = $this->easyMeta->dataTypeLabels(['literal']);
        $this->assertIsArray($labels);
        $this->assertArrayHasKey('literal', $labels);
        $this->assertIsString($labels['literal']);
        $this->assertNotEmpty($labels['literal']);
    }

    // =========================================================================
    // Order preservation tests
    // =========================================================================

    public function testPropertyIdsPreservesOrder(): void
    {
        $input = ['dcterms:description', 'dcterms:title', 'dcterms:creator'];
        $result = $this->easyMeta->propertyIds($input);

        $this->assertSame(array_keys($input), array_keys(array_keys($result)));
    }

    public function testResourceClassIdsPreservesOrder(): void
    {
        $input = ['dctype:Text', 'dctype:Image'];
        $result = $this->easyMeta->resourceClassIds($input);

        $resultKeys = array_keys($result);
        $this->assertSame('dctype:Text', $resultKeys[0]);
        $this->assertSame('dctype:Image', $resultKeys[1]);
    }
}
