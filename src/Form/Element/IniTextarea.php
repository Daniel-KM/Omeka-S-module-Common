<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Config\Exception;
use Laminas\Config\Reader\Ini as IniReader;
use Laminas\Config\Writer\Ini as IniWriter;
use Laminas\Form\Element\Textarea;
use Laminas\InputFilter\InputProviderInterface;

/**
 * @see https://www.php.net/parse_ini_file
 *
 * @uses \Laminas\Config\Reader\Ini
 * @uses \Laminas\Config\Writer\Ini
 *
 * Warning: a few characters in keys are not supported.
 */
class IniTextarea extends Textarea implements InputProviderInterface
{
    /**
     * Separator for nesting levels of configuration data identifiers.
     *
     * @var string
     */
    protected $nestSeparator = '.';

    /**
     * Flag which determines whether sections are processed or not.
     *
     * @see https://www.php.net/parse_ini_file
     * @var bool
     */
    protected $processSections = true;

    /**
     * If true the INI string is rendered in the global namespace without
     * sections.
     *
     * @var bool
     */
    protected $renderWithoutSections = false;

    /**
     * Flag which determines whether boolean, null, and integer values should be
     * returned as their proper types.
     *
     * @see https://www.php.net/parse_ini_file
     * @var bool
     */
    protected $typedMode = false;

    public function setOptions($options)
    {
        parent::setOptions($options);
        if (array_key_exists('ini_nest_separator', $this->options)) {
            $this->setNestSeparator($this->options['ini_nest_separator']);
        }
        if (array_key_exists('ini_process_sections', $this->options)) {
            $this->setProcessSections($this->options['ini_process_sections']);
        }
        if (array_key_exists('ini_render_without_sections', $this->options)) {
            $this->setRenderWithoutSections($this->options['ini_render_without_sections']);
        }
        if (array_key_exists('ini_typed_mode', $this->options)) {
            $this->setTypedMode($this->options['ini_typed_mode']);
        }
        return $this;
    }

    public function setValue($value)
    {
        $this->value = $this->arrayToString($value);
        return $this;
    }

    public function getInputSpecification()
    {
        return [
            'name' => $this->getName(),
            'required' => false,
            'allow_empty' => true,
            'filters' => [
                [
                    'name' => \Laminas\Filter\Callback::class,
                    'options' => [
                        'callback' => [$this, 'stringToArray'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert an array into a string formatted as "ini".
     */
    public function arrayToString($array): string
    {
        if (is_string($array)) {
            return $array;
        } elseif (is_null($array)) {
            return '';
        }

        $writer = new IniWriter();
        $writer
            ->setNestSeparator($this->nestSeparator)
            ->setRenderWithoutSectionsFlags($this->renderWithoutSections);

        try {
            $result = $writer->toString($array);
        } catch (Exception\InvalidArgumentException $e) {
            return '';
        }

        return (string) $result;
    }

    /**
     * Convert a string formatted as "ini" into an array.
     */
    public function stringToArray($string): array
    {
        if (is_array($string)) {
            return $string;
        } elseif (is_null($string)) {
            return [];
        }

        $reader = new IniReader();
        $reader
            ->setNestSeparator($this->nestSeparator)
            ->setProcessSections($this->processSections)
            ->setTypedMode($this->typedMode);

        try {
            $result = $reader->fromString($string);
        } catch (Exception\RuntimeException $e) {
            return [];
        }

        // Result may be boolean.
        return is_array($result) ? $result : [];
    }

    /**
     * Set nest separator.
     */
    public function setNestSeparator($separator): self
    {
        $this->nestSeparator = (string) $separator;
        return $this;
    }

    /**
     * Get nest separator.
     */
    public function getNestSeparator(): string
    {
        return $this->nestSeparator;
    }

    /**
     * Marks whether sections should be processed.
     * When sections are not processed,section names are stripped and section
     * values are merged
     *
     * @see https://www.php.net/parse_ini_file
     */
    public function setProcessSections($processSections): self
    {
        $this->processSections = (bool) $processSections;
        return $this;
    }

    /**
     * Get if sections should be processed
     * When sections are not processed,section names are stripped and section
     * values are merged
     *
     * @see https://www.php.net/parse_ini_file
     */
    public function getProcessSections(): bool
    {
        return $this->processSections;
    }

    /**
     * Set if rendering should occur without sections or not.
     *
     * If set to true, the INI file is rendered without sections completely
     * into the global namespace of the INI file.
     */
    public function setRenderWithoutSections($renderWithoutSections): self
    {
        $this->renderWithoutSections = (bool) $renderWithoutSections;
        return $this;
    }

    /**
     * Return whether the writer should render without sections.
     */
    public function getRenderWithoutSections(): bool
    {
        return $this->renderWithoutSections;
    }

    /**
     * Set whether boolean, null, and integer values should be returned as their proper types.
     * When set to false, all values will be returned as strings.
     *
     * @see https://www.php.net/parse_ini_file
     */
    public function setTypedMode($typedMode): self
    {
        $this->typedMode = (bool) $typedMode;
        return $this;
    }

    /**
     * Get whether boolean, null, and integer values should be returned as their proper types.
     * When set to false, all values will be returned as strings.
     *
     * @see https://www.php.net/parse_ini_file
     */
    public function getTypedMode(): bool
    {
        return $this->typedMode;
    }

    /**
     * Get the scanner-mode constant value to be used with the built-in parse_ini_file function.
     * Either INI_SCANNER_NORMAL or INI_SCANNER_TYPED depending on $typedMode.
     *
     * @see https://www.php.net/parse_ini_file
     */
    public function getScannerMode(): int
    {
        return $this->getTypedMode()
            ? INI_SCANNER_TYPED
            : INI_SCANNER_NORMAL;
    }
}
