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
    public const INVALID = 'iniInvalid';

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $messageTemplates = [
        self::INI_EXCEPTION => 'The string is not formatted as ini', // @translate
        self::INCORRECT => 'The string is incorrectly formatted', // @translate
        self::INCORRECT_VALUES => 'The string contains double quotes or invalid characters', // @translate
        self::INVALID => 'Invalid type given. String expected', // @translate
    ];

    public function isValid($value)
    {
        if (!is_string($value)) {
            $this->error(self::INVALID);
            return false;
        }

        try {
            $reader = new IniReader();
            $array = $reader->fromString((string) $value);
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
            $writer->toString($array);
            return true;
        } catch (ConfigException\RuntimeException $e) {
            $this->error(self::INCORRECT_VALUES);
            return false;
        } catch (\Exception $e) {
            $this->error(self::INCORRECT);
            return false;
        }
    }
}
