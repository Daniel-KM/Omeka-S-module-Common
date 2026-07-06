<?php declare(strict_types=1);

namespace CommonTest\View\Helper;

use Common\Form\Element\Secret;
use Common\Form\View\Helper\FormSecret;
use Laminas\View\Renderer\PhpRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the FormSecret view helper (no database required).
 */
class FormSecretTest extends TestCase
{
    private function helper(): FormSecret
    {
        // Stub the renderer helpers used by FormSecret: formText/formPassword
        // echo the element name, type and value, the escapers and translator
        // are identity.
        $view = $this->getMockBuilder(PhpRenderer::class)
            ->onlyMethods(['__call'])
            ->getMock();
        $view->method('__call')->willReturnCallback(function ($name, $args) {
            if ($name === 'formText' || $name === 'formPassword') {
                $element = $args[0];
                $type = $name === 'formPassword' ? 'password' : 'text';
                return sprintf('<input type="%s" name="%s" value="%s">', $type, $element->getName(), $element->getValue());
            }
            return $args[0];
        });
        $helper = new FormSecret();
        $helper->setView($view);
        return $helper;
    }

    public function testNeverRendersTheStoredValue(): void
    {
        $element = new Secret('api_key');
        $element->setValue('super-secret');
        $output = $this->helper()->render($element);
        $this->assertStringNotContainsString('super-secret', $output);
    }

    public function testRendersATextInputByDefault(): void
    {
        $output = $this->helper()->render(new Secret('api_key'));
        $this->assertStringContainsString('type="text"', $output);
    }

    public function testRendersAPasswordInputWhenMasked(): void
    {
        $element = new Secret('api_key');
        $element->setOption('masked', true);
        $output = $this->helper()->render($element);
        $this->assertStringContainsString('type="password"', $output);
    }

    public function testNoRemoveControlWhenNoValueIsStored(): void
    {
        $output = $this->helper()->render(new Secret('api_key'));
        $this->assertStringNotContainsString('api_key_remove', $output);
    }

    public function testRemoveControlWhenAValueIsStored(): void
    {
        $element = new Secret('api_key');
        $element->setOption('has_value', true);
        $output = $this->helper()->render($element);
        $this->assertStringContainsString('name="api_key_remove"', $output);
        $this->assertStringContainsString('type="checkbox"', $output);
    }
}
