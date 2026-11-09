<?php declare(strict_types=1);

namespace CommonTest\Form\Element;

use Common\Form\Element\Secret;
use Common\Stdlib\Cipher;
use Laminas\Form\Form;
use Laminas\Http\Request;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Secret element in the settings forms, where the element
 * filters its own value, since the core controllers save the data directly.
 */
class SecretTest extends TestCase
{
    private function cipher(): Cipher
    {
        return new Cipher(base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }

    /**
     * Validate a form with a secret element and return the saved value.
     */
    private function submit(Secret $element, array $post): string
    {
        $form = new Form();
        $form->add($element);
        $form->setData($post);
        $this->assertTrue($form->isValid());
        return (string) $form->getData()['api_key'];
    }

    private function element(array $options = []): Secret
    {
        $element = (new Secret('api_key'));
        foreach ($options as $key => $value) {
            $element->setOption($key, $value);
        }
        return $element;
    }

    public function testWithoutCipherTheValueIsNotFiltered(): void
    {
        // The config forms apply the rules themselves after the validation.
        $this->assertSame('plain', $this->submit($this->element(), ['api_key' => 'plain']));
    }

    public function testNewValueIsEncrypted(): void
    {
        $cipher = $this->cipher();
        $saved = $this->submit($this->element(['secret_cipher' => $cipher]), ['api_key' => 'new-key']);
        $this->assertStringStartsWith(Cipher::PREFIX, $saved);
        $this->assertSame('new-key', $cipher->decrypt($saved));
    }

    public function testEmptySubmissionKeepsTheStoredValueAsIs(): void
    {
        $cipher = $this->cipher();
        $stored = $cipher->encrypt('old-key');
        $saved = $this->submit($this->element([
            'secret_cipher' => $cipher,
            'secret_stored' => $stored,
        ]), ['api_key' => '']);
        // Not encrypted twice.
        $this->assertSame($stored, $saved);
    }

    public function testMissingSubmissionKeepsTheStoredValue(): void
    {
        $saved = $this->submit($this->element([
            'secret_cipher' => $this->cipher(),
            'secret_stored' => 'legacy-plain-key',
        ]), []);
        $this->assertSame('legacy-plain-key', $saved);
    }

    public function testRemoveClearsTheValue(): void
    {
        $saved = $this->submit($this->element([
            'secret_cipher' => $this->cipher(),
            'secret_stored' => 'old-key',
            'secret_remove' => true,
        ]), ['api_key' => 'ignored']);
        $this->assertSame('', $saved);
    }

    public function testOtherOptionsAreKept(): void
    {
        $element = $this->element();
        $element->setOptions(['label' => 'Api key', 'info' => 'Info']);
        $element->setOption('secret_cipher', $this->cipher());
        $this->assertSame('Api key', $element->getLabel());
        $this->assertSame('Info', $element->getOption('info'));
    }

    /**
     * The module prepares the elements of its own settings fieldset.
     *
     * @dataProvider providerPostKeys
     */
    public function testPrepareSecretSettings(?string $postKey, array $post): void
    {
        $services = new ServiceManager();
        $services->setService('Omeka\Cipher', $this->cipher());
        $request = new Request();
        $request->setPost(new Parameters($post));
        $services->setService('Request', $request);

        $module = new class extends \Omeka\Module\AbstractModule {
            use \Common\TraitModule;

            const NAMESPACE = 'CommonTestSecret';
        };
        $module->setServiceLocator($services);

        $withValue = new Secret('api_key');
        $withValue->setOptions(['label' => 'Api key']);
        $withValue->setValue('sodium:stored');
        $withoutValue = new Secret('other_key');

        $prepare = function (...$args): void {
            $this->prepareSecretSettings(...$args);
        };
        $prepare->call($module, [$withValue, $withoutValue], ['api_key' => 'sodium:stored'], $postKey);

        // The stored value is never rendered.
        $this->assertSame('', $withValue->getValue());
        $this->assertTrue($withValue->getOption('has_value'));
        $this->assertFalse($withoutValue->getOption('has_value'));
        $this->assertSame('sodium:stored', $withValue->getOption('secret_stored'));
        $this->assertTrue($withValue->getOption('secret_remove'));
        $this->assertFalse($withoutValue->getOption('secret_remove'));
        $this->assertSame('Api key', $withValue->getLabel());
    }

    public function providerPostKeys(): array
    {
        return [
            'main and site settings' => [null, ['api_key_remove' => '1']],
            'user settings' => ['user-settings', ['user-settings' => ['api_key_remove' => '1']]],
        ];
    }
}
