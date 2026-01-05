<?php declare(strict_types=1);

namespace Common\Validator;

use Laminas\Config\Exception as ConfigException;
use Laminas\Config\Reader\Ini as IniReader;
use Laminas\Config\Writer\Ini as IniWriter;
use Laminas\Validator\AbstractValidator;

/**
 * Check if a string is a valid ini that can be converted into an array.
 *
 * @see https://www.php.net/parse_ini_file
 *
 * Use the default parameters of IniReader and IniWriter.
 * @uses \Laminas\Config\Reader\Ini
 * @uses \Laminas\Config\Writer\Ini
 */
class Ini extends AbstractValidator
{
    public const INI_EXCEPTION = 'iniException';
    public const INCORRECT = 'iniIncorrect';
    public const INCORRECT_VALUES = 'iniIncorrectValues';
    public const INCORRECT_VALUES_DOUBLE_QUOTES = 'iniIncorrectValuesDoubleQuotes';
    public const INVALID = 'iniInvalid';

    /**
     * @var bool
     */
    protected $allowDoubleQuotes = false;

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $messageTemplates = [
        self::INI_EXCEPTION => 'The string is not formatted as ini', // @translate
        self::INCORRECT => 'The string is incorrectly formatted', // @translate
        self::INCORRECT_VALUES => 'The string contains invalid characters like double quotes', // @translate
        self::INCORRECT_VALUES_DOUBLE_QUOTES => 'The string contains invalid characters', // @translate
        self::INVALID => 'Invalid type given. String expected', // @translate
    ];

    public function setAllowDoubleQuotes(bool $allowDoubleQuotes): self
    {
        $this->allowDoubleQuotes = $allowDoubleQuotes;
        return $this;
    }

    public function getAllowDoubleQuotes(): bool
    {
        return $this->allowDoubleQuotes;
    }

    public function isValid($value)
    {
        if (!is_string($value)) {
            $this->error(self::INVALID);
            return false;
        }

        try {
            $reader = new IniReader();
            // Pre-process to escape " in values so IniReader can parse them.
            $processedValue = $this->allowDoubleQuotes
                ? $this->escapeDoubleQuotesInIniString((string) $value)
                : (string) $value;
            $array = $reader->fromString($processedValue);
        } catch (ConfigException\RuntimeException $e) {
            $this->messageTemplates[self::INI_EXCEPTION] = $e->getMessage();
            $this->error(self::INI_EXCEPTION);
            return false;
        } catch (\Exception $e) {
            $this->error(self::INCORRECT);
            return false;
        }

        /**
         * Check ini with writer too: reader does not check for double quotes,
         * but writer does.
         *
         * @see \Laminas\Config\Writer::prepareValue()
         */

        if (!is_array($array)) {
            return false;
        }

        try {
            $writer = new IniWriter();
            // Escape double quotes as HTML entity before checking with writer,
            // since the IniTextarea form element will escape them when storing.
            // @see \Common\Form\Element\IniTextarea::escapeDoubleQuotes()
            if ($this->allowDoubleQuotes) {
                $array = $this->escapeDoubleQuotes($array);
            }
            $writer->toString($array);
            return true;
        } catch (ConfigException\RuntimeException $e) {
            $this->error($this->allowDoubleQuotes ? self::INCORRECT_VALUES_DOUBLE_QUOTES : self::INCORRECT_VALUES);
            return false;
        } catch (\Exception $e) {
            $this->error(self::INCORRECT);
            return false;
        }
    }

    /**
     * Escape double quotes inside values of an INI-formatted string.
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
                $inner = substr($valueTrimmed, 1);
                $lastQuotePos = strrpos($inner, '"');
                if ($lastQuotePos !== false) {
                    $content = substr($inner, 0, $lastQuotePos);
                    $trailing = substr($inner, $lastQuotePos + 1);
                    $content = str_replace('"', '&quot;', $content);
                    $result[] = $key . $leadingSpace . '"' . $content . '"' . $trailing;
                } else {
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
}
