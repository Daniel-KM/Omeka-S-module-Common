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
 * Warning: the mode to get typed values from ini is set by default.
 *
 * @todo Add an option to manage required keys by section.
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
     * Warning: when set, the strings without double quotes "true", "on", "yes",
     * "false", "off", "no", "none", "null" and numeric strings are converted
     * into true, false, null and integers. This option is used only for
     * parsing, not storing.
     *
     * Unlike parse_ini_file and Laminas Ini Reader, the typed mode is set by
     * default, because this is the way ini files are used most of the time.
     *
     * @see https://www.php.net/parse_ini_file
     * @var bool
     */
    protected $typedMode = true;

    /**
     * Flag which determines whether double quotes are allowed in values.
     *
     * When enabled, double quotes are escaped as &quot; internally to work
     * around Laminas IniWriter limitation, but displayed as " to users.
     *
     * @var bool
     */
    protected $allowDoubleQuotes = false;

    /**
     * Specific options:
     *
     * - ini_nest_separator (string): default is "."
     * - ini_process_sections (bool): default is true
     * - ini_render_without_sections (bool): default is false
     * - ini_typed_mode (bool): default is true, so the strings without double
     *   quotes "true", "on", "yes", "false", "off", "no", "none", "null" and
     *   numeric strings are converted into true, false, null and integers.
     * - ini_allow_double_quotes (bool): default is false. When true, double
     *   quotes in values are allowed by escaping them internally.
     *
     * {@inheritDoc}
     * @see \Laminas\Form\Element::setOptions()
     */
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
        if (array_key_exists('ini_allow_double_quotes', $this->options)) {
            $this->setAllowDoubleQuotes($this->options['ini_allow_double_quotes']);
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
                        'callback' => [$this, 'filterStringToArray'],
                    ],
                ],
            ],
            'validators' => [
                [
                    'name' => \Laminas\Validator\Callback::class,
                    'options' => [
                        'callback' => [$this, 'validateIni'],
                        'callbackOptions' => [
                            // A bug may occur on php 8+ when the callback is
                            // called with a string key that is different from
                            // function argument name (see validateIni below).
                            // See https://www.php.net/manual/fr/function.call-user-func-array.php#125953
                            'contextKey' => $this->getName(),
                        ],
                        'message' => 'Invalid ini string or values with double quotes', // @translate
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert a string formatted as "ini" into an array.
     *
     * If the value provided is not a a valid input, the value will remain
     * unfiltered, as any laminas filter.
     */
    public function filterStringToArray($string)
    {
        return $this->stringToArray($string) ?? $string;
    }

    /**
     * Convert an array into a string formatted as "ini".
     */
    public function arrayToString($array): ?string
    {
        if (is_string($array)) {
            return $array;
        } elseif ($array === null) {
            return '';
        }

        // Escape double quotes as HTML entity to avoid IniWriter exception.
        // @see \Laminas\Config\Writer\Ini::prepareValue()
        if ($this->allowDoubleQuotes) {
            $array = $this->escapeDoubleQuotes($array);
        }

        $writer = new IniWriter();
        $writer
            ->setNestSeparator($this->nestSeparator)
            ->setRenderWithoutSectionsFlags($this->renderWithoutSections);

        try {
            $result = $writer->toString($array);
        } catch (Exception\ExceptionInterface $e) {
            (new \Omeka\Mvc\Controller\Plugin\Messenger())->addError(new \Common\Stdlib\PsrMessage(
                'The field {label} has an issue: {msg}', // @translate
                ['label' => $this->getLabel(), 'msg' => $e->getMessage()],
            ));
            return null;
        }

        // Unescape for display so user sees actual " instead of &quot;.
        if ($this->allowDoubleQuotes) {
            return str_replace('&quot;', '"', (string) $result);
        }

        return (string) $result;
    }

    /**
     * Convert a string formatted as "ini" into an array.
     */
    public function stringToArray($string): ?array
    {
        if (is_array($string)) {
            return $string;
        } elseif ($string === null) {
            return [];
        }

        // Pre-process to escape " in values so IniReader can parse them.
        if ($this->allowDoubleQuotes) {
            $string = $this->escapeDoubleQuotesInIniString($string);
        }

        $reader = new IniReader();
        $reader
            ->setNestSeparator($this->nestSeparator)
            ->setProcessSections($this->processSections)
            ->setTypedMode($this->typedMode);

        try {
            $result = $reader->fromString($string);
        } catch (Exception\ExceptionInterface $e) {
            return null;
        }

        // Result may be boolean.
        if (!is_array($result)) {
            return null;
        }

        // Unescape double quotes that were escaped when storing.
        // @see self::escapeDoubleQuotes()
        if ($this->allowDoubleQuotes) {
            return $this->unescapeDoubleQuotes($result);
        }

        return $result;
    }

    public function validateIni($value, ?array $context = null, ?string $contextKey = null): bool
    {
        if (isset($context) && isset($contextKey)) {
            if (!isset($context[$contextKey])) {
                return false;
            }
            $value = $context[$contextKey];
        }
        $validator = new \Common\Validator\Ini();
        $validator->setAllowDoubleQuotes($this->allowDoubleQuotes);
        return $validator->isValid($value);
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
     * Warning: when set, the strings without double quotes "true", "on", "yes",
     * "false", "off", "no", "none", "null" and numeric strings are converted
     * into true, false, null and integers. This option is used only for
     * parsing, not storing.
     *
     * Unlike parse_ini_file and Laminas Ini Reader, the typed mode is set by
     * default, because this is the way ini files are used most of the time.
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

    /**
     * Set whether double quotes are allowed in values.
     */
    public function setAllowDoubleQuotes($allowDoubleQuotes): self
    {
        $this->allowDoubleQuotes = (bool) $allowDoubleQuotes;
        return $this;
    }

    /**
     * Get whether double quotes are allowed in values.
     */
    public function getAllowDoubleQuotes(): bool
    {
        return $this->allowDoubleQuotes;
    }

    /**
     * Escape double quotes inside values of an INI-formatted string.
     *
     * This pre-processes the raw INI string before IniReader parses it,
     * distinguishing between quote delimiters and quotes inside values.
     */
    protected function escapeDoubleQuotesInIniString(string $string): string
    {
        $lines = explode("\n", $string);
        $result = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Keep empty lines, comments, and section headers as-is.
            if ($trimmed === '' || $trimmed[0] === ';' || $trimmed[0] === '#' || $trimmed[0] === '[') {
                $result[] = $line;
                continue;
            }

            // Find the = separator.
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                $result[] = $line;
                continue;
            }

            $key = substr($line, 0, $eqPos + 1);
            $value = substr($line, $eqPos + 1);
            $valueTrimmed = ltrim($value);
            $leadingSpace = substr($value, 0, strlen($value) - strlen($valueTrimmed));

            // Check if value is quoted.
            if (strlen($valueTrimmed) > 0 && $valueTrimmed[0] === '"') {
                // Quoted value: find the closing quote and escape " inside.
                // Remove leading quote.
                $inner = substr($valueTrimmed, 1);
                // Find last quote (closing delimiter).
                $lastQuotePos = strrpos($inner, '"');
                if ($lastQuotePos !== false) {
                    $content = substr($inner, 0, $lastQuotePos);
                    $trailing = substr($inner, $lastQuotePos + 1);
                    // Escape any " inside the content.
                    $content = str_replace('"', '&quot;', $content);
                    $result[] = $key . $leadingSpace . '"' . $content . '"' . $trailing;
                } else {
                    // No closing quote found, escape all " except the first.
                    $inner = str_replace('"', '&quot;', $inner);
                    $result[] = $key . $leadingSpace . '"' . $inner;
                }
            } else {
                // Unquoted value: escape all ".
                $value = str_replace('"', '&quot;', $value);
                $result[] = $key . $value;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Recursively escape double quotes as HTML entity in array values.
     *
     * This is needed because Laminas IniWriter throws an exception when a
     * value contains double quotes.
     *
     * @see \Laminas\Config\Writer\Ini::prepareValue()
     */
    protected function escapeDoubleQuotes(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->escapeDoubleQuotes($value);
            } elseif (is_string($value)) {
                $array[$key] = str_replace('"', '&quot;', $value);
            }
        }
        return $array;
    }

    /**
     * Recursively unescape HTML entity back to double quotes in array values.
     *
     * @see self::escapeDoubleQuotes()
     */
    protected function unescapeDoubleQuotes(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->unescapeDoubleQuotes($value);
            } elseif (is_string($value)) {
                $array[$key] = str_replace('&quot;', '"', $value);
            }
        }
        return $array;
    }
}
