<?php declare(strict_types=1);

namespace CommonTest\View\Helper;

use Common\View\Helper\FormTabs;
use Laminas\Form\Form;
use Laminas\View\Renderer\PhpRenderer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Common\View\Helper\FormTabs
 */
class FormTabsTest extends TestCase
{
    /**
     * Tabs are derived declaratively from the form option "element_tabs" and
     * the per-element "tab" option, independently from "element_groups".
     */
    public function testTabsFromOption(): void
    {
        $helper = new FormTabs();
        $view = $this->createMock(PhpRenderer::class);
        $view->method('plugin')->willReturn(static fn ($string) => $string);
        $helper->setView($view);

        $form = new Form();
        $form->setOption('element_tabs', ['files' => 'Files', 'advanced' => 'Advanced']);
        $form->add(['name' => 'a', 'type' => 'text', 'options' => ['tab' => 'files']]);
        $form->add(['name' => 'b', 'type' => 'text', 'options' => ['tab' => 'advanced']]);
        $form->add(['name' => 'c', 'type' => 'text', 'options' => ['tab' => 'files']]);
        // No tab: not dispatched to any tab (handled as fallback at render).
        $form->add(['name' => 'd', 'type' => 'text']);
        // Unknown tab: ignored.
        $form->add(['name' => 'e', 'type' => 'text', 'options' => ['tab' => 'nope']]);

        $method = new \ReflectionMethod(FormTabs::class, 'tabsFromOption');
        $method->setAccessible(true);
        $tabs = $method->invoke($helper, $form);

        $this->assertSame([
            'files' => ['label' => 'Files', 'elements' => ['a', 'c']],
            'advanced' => ['label' => 'Advanced', 'elements' => ['b']],
        ], $tabs);
    }

    public function testTabsFromOptionEmptyWithoutElementTabs(): void
    {
        $helper = new FormTabs();
        $view = $this->createMock(PhpRenderer::class);
        $view->method('plugin')->willReturn(static fn ($string) => $string);
        $helper->setView($view);

        $form = new Form();
        // Only element_groups (sections) declared: no tabs derived.
        $form->setOption('element_groups', ['g' => 'Group']);
        $form->add(['name' => 'a', 'type' => 'text', 'options' => ['element_group' => 'g']]);

        $method = new \ReflectionMethod(FormTabs::class, 'tabsFromOption');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke($helper, $form));
    }
}
