<?php declare(strict_types=1);

namespace Common\Mvc\Controller\Plugin;

use Common\Stdlib\PsrMessage;
use Laminas\Http\PhpEnvironment\Response as HttpResponse;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Mvc\Exception\RuntimeException;
use Laminas\View\Model\JsonModel;
use Omeka\Stdlib\Message;

class JSend extends AbstractPlugin
{
    const ERROR = 'error';
    const FAIL = 'fail';
    const SUCCESS = 'success';

    /**
     * Send output via json according to jSend.
     *
     * Notes:
     * - Unlike jSend, any status can have a main message and a code.
     * - For statuses fail and error, the error messages are taken from
     *   messenger messages when not set.
     * - PsrMessage() and Message() are automatically translated.
     *
     * @see https://github.com/omniti-labs/jsend
     *
     * @throws \Laminas\Mvc\Exception\RuntimeException
     *
     * @return JsonModel|self When status is not set, it returns itself for
     * shortcut methods.
     */
    public function __invoke(
        ?string $status = null,
        ?array $data = null,
        // TODO Use stringable for Omeka omeka 4.2 (php 8.2) and add translator for PsrMessage, but keep compatibility with omeka 4.0.
        // Message is null, string or stringable.
        $message = null,
        ?int $httpStatusCode = null,
        ?int $code = null
    ) /* JsonModel|self */ {
        if ($status === null) {
            return $this;
        }

        $controller = $this->getController();

        // Make the message a simple string.
        if ($message instanceof PsrMessage) {
            $message = $message->setTranslator($controller->translator())->translate();
        } elseif ($message instanceof Message) {
            if ($message->hasArgs()) {
                $message = vsprintf($controller->translate($message->getMessage()), $message->getArgs());
            } else {
                $message = $controller->translate($message->getMessage());
            }
        }

        switch ($status) {
            case self::SUCCESS:
                $json = [
                    'status' => self::SUCCESS,
                    'data' => $data,
                ];
                if (isset($message) && strlen($message)) {
                    $json['message'] = $message;
                }
                if (isset($code)) {
                    $json['code'] = $code;
                }
                break;

            case self::FAIL:
                if (!$data) {
                    $message = $message
                        ?: $this->flattenMessages($controller->viewHelpers()->get('messages')->getTranslatedMessages('error'))
                        ?: $controller->translate('Check your input for invalid data.'); // @translate
                    $data = ['message' => $message];
                }
                $json = [
                    'status' => self::FAIL,
                    'data' => $data,
                ];
                if (isset($message) && strlen($message)) {
                    $json['message'] = $message;
                }
                if (isset($code)) {
                    $json['code'] = $code;
                }
                $httpStatusCode ??= HttpResponse::STATUS_CODE_400;
                break;

            case self::ERROR:
                $message = $message
                    ?: $this->flattenMessages($controller->viewHelpers()->get('messages')->getTranslatedMessages('error'))
                    ?: $controller->translate('An internal error has occurred.'); // @translate
                $json = [
                    'status' => self::ERROR,
                    'message' => $message,
                ];
                if ($data) {
                    $json['data'] = $data;
                }
                if (isset($code)) {
                    $json['code'] = $code;
                }
                $httpStatusCode ??= HttpResponse::STATUS_CODE_500;
                break;

            default:
                throw new RuntimeException(sprintf($controller->translate('The status "%s" is not supported by jSend.'), $status)); // @translate
        }

        if ($httpStatusCode) {
            /** @var \Laminas\Http\Response $response */
            $response = $controller->getResponse();
            $response->setStatusCode($httpStatusCode);
        }

        return new JsonModel($json);
    }

    public function success(
        ?array $data = null,
        // Message is null, string or stringable.
        $message = null,
        ?int $httpStatusCode = null,
        ?int $code = null
    ) {
        return $this->__invoke(self::SUCCESS, $data, $message, $httpStatusCode, $code);
    }

    public function fail(
        ?array $data = null,
        // Message is null, string or stringable.
        $message = null,
        ?int $httpStatusCode = null,
        ?int $code = null
    ) {
        return $this->__invoke(self::FAIL, $data, $message, $httpStatusCode, $code);
    }

    public function error(
        ?array $data = null,
        // Message is null, string or stringable.
        $message = null,
        ?int $httpStatusCode = null,
        ?int $code = null
    ) {
        return $this->__invoke(self::ERROR, $data, $message, $httpStatusCode, $code);
    }

    /**
     * Flatten nested array of messages returned as string.
     *
     * This method avoids issues when multiple errors are stored in messenger,
     * but the output needs only a string.
     */
    public function flattenMessages($messages): string
    {
        if (!$messages) {
            return '';
        }
        if (is_string($messages)) {
            return $messages;
        }
        if (!is_array($messages)) {
            return (string) $messages;
        }
        $flat = [];
        array_walk_recursive($messages, function ($v) use (&$flat) {
            if ($v !== null && $v !== '') {
                $flat[] = (string) $v;
            }
        });
        return implode("\n", $flat);
    }

    /**
     * Flatten Laminas form messages to a JSend-conformant flat array.
     *
     * The JSend spec recommends `data` to be keyed by POST field name, with a
     * value that is a string (or array of strings) describing the error. The
     * nested structure returned by `$form->getMessages()` is flattened here to
     * match html `name` attributes of elements (bracket notation of fieldsets).
     *
     * @param array|\Laminas\Form\FormInterface $messages Either a form instance
     *   or the raw output of `$form->getMessages()`.
     * @param bool $keepMultiple When true, keys with more than one error keep
     *   an array value; otherwise only the first message is kept per key.
     * @return array Flat array keyed by field name, for example
     *   `['title' => 'Value is required', 'fieldset[email]' => '...']`.
     */
    public function flattenFormMessages($messages, bool $keepMultiple = false): array
    {
        if ($messages instanceof \Laminas\Form\FormInterface) {
            $messages = $messages->getMessages();
        }
        if (!is_array($messages)) {
            return [];
        }
        $flat = [];
        $this->flattenFormMessagesWalk($messages, '', $flat, $keepMultiple);
        return $flat;
    }

    protected function flattenFormMessagesWalk(array $messages, string $prefix, array &$flat, bool $keepMultiple): void
    {
        $leafMessages = [];
        foreach ($messages as $key => $value) {
            if (is_array($value)) {
                if ($this->isLeafValidatorArray($value)) {
                    // key is the field name, value is [validatorKey => msg].
                    foreach ($value as $msg) {
                        if ($msg !== null && $msg !== '') {
                            $leafMessages[$key][] = (string) $msg;
                        }
                    }
                } else {
                    // Fieldset: recurse with bracket notation.
                    $subPrefix = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
                    $this->flattenFormMessagesWalk($value, $subPrefix, $flat, $keepMultiple);
                }
            } elseif ($value !== null && $value !== '') {
                $leafMessages[$key][] = (string) $value;
            }
        }
        foreach ($leafMessages as $key => $msgs) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
            $flat[$name] = $keepMultiple && count($msgs) > 1 ? $msgs : reset($msgs);
        }
    }

    protected function isLeafValidatorArray(array $value): bool
    {
        foreach ($value as $v) {
            if (is_array($v)) {
                return false;
            }
        }
        return true;
    }

    public function jsonErrorNotFound(?array $data = null)
    {
        return $this->__invoke(self::FAIL, $data, $this->translate('Not found.'), HttpResponse::STATUS_CODE_404);
    }
}
