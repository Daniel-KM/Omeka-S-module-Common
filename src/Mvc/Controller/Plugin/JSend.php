<?php declare(strict_types=1);

namespace Common\Mvc\Controller\Plugin;

use Common\Stdlib\PsrMessage;
use Laminas\Http\PhpEnvironment\Response;
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
     */
    public function __invoke(
        string $status,
        ?array $data = null,
        // Message is null, string or stringable.
        $message = null,
        ?int $httpStatusCode = null,
        ?int $code = null
    ): JsonModel {
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
                        ?: $controller->viewHelpers()->get('messages')->getTranslatedMessages('error')
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
                $httpStatusCode ??= Response::STATUS_CODE_400;
                break;

            case self::ERROR:
                $message = $message
                    ?: $controller->viewHelpers()->get('messages')->getTranslatedMessages('error')
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
                $httpStatusCode ??= Response::STATUS_CODE_500;
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
}
