<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element\Password;
use Laminas\InputFilter\InputProviderInterface;

/**
 * A setting field holding a secret (API key, password) encrypted at rest.
 *
 * The stored value is never rendered back to the browser:
 * - empty means that a value is already set (a placeholder may be set);
 * - an empty submission keep the current value.
 *
 * Rendered as a text input with a lock icon, excluded from browser autofill;
 * set option "masked" to true to render a password input instead.
 * @see \Omeka\Form\View\Helper\FormSecret
 *
 * In the settings forms, where the core controllers save the data directly, the
 * element filters its own value when the option "secret_cipher" is set:
 * @see \Common\TraitModule::prepareSecretSettings()
 */
class Secret extends Password implements InputProviderInterface
{
    public function getInputSpecification(): array
    {
        $spec = [
            'name' => $this->getName(),
            'required' => false,
        ];
        if ($this->getOption('secret_cipher')) {
            $spec['filters'] = [
                [
                    'name' => \Laminas\Filter\Callback::class,
                    'options' => [
                        'callback' => [$this, 'filterSecret'],
                    ],
                ],
            ];
        }
        return $spec;
    }

    /**
     * Apply the rules of the secret fields to the submitted value:
     * - the companion checkbox "{name}_remove" is checked: clear the value;
     * - the submission is empty: keep the current stored value;
     * - otherwise: encrypt the submitted value.
     *
     * The stored value is returned as is, so it is not encrypted twice.
     *
     * @param mixed $value
     */
    public function filterSecret($value): string
    {
        if ($this->getOption('secret_remove')) {
            return '';
        }
        $value = (string) $value;
        if ($value === '') {
            return (string) $this->getOption('secret_stored');
        }
        /** @var \Common\Stdlib\Cipher|\Omeka\Stdlib\Cipher $cipher */
        $cipher = $this->getOption('secret_cipher');
        return $cipher->encrypt($value);
    }
}
