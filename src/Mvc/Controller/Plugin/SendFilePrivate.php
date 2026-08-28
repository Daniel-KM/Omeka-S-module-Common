<?php declare(strict_types=1);

namespace Common\Mvc\Controller\Plugin;

use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class SendFilePrivate extends AbstractPlugin
{
    /**
     * Stream a private file confined to an allowed base directory.
     *
     * The file must be inside the allowed directory (protection against path
     * traversal via "realpath"), then it is streamed through the plugin
     * "sendFile" (headers, content type, ranges…).
     *
     * The access rights are not checked here: the caller must verify them
     * before, because they depend on the file (owner, site admin or global
     * admin). This plugin only confines the path and streams the file.
     *
     * @param string $filepath Absolute path of the file to stream.
     * @param string $allowedBaseDir Absolute path the file must be inside.
     * @param array $params Options passed to the plugin "sendFile" (filename,
     * content_type, disposition_mode, cache…). Defaults to an attachment
     * without cache.
     * @return \Laminas\Http\Response|null Null when the path is invalid or
     * outside the allowed directory, so the caller can return a 404 or an
     * error.
     */
    public function __invoke(string $filepath, string $allowedBaseDir, array $params = []): ?HttpResponse
    {
        $realBaseDir = realpath($allowedBaseDir);
        $realFilepath = realpath($filepath);
        if ($realBaseDir === false
            || $realFilepath === false
            || strpos($realFilepath, $realBaseDir . DIRECTORY_SEPARATOR) !== 0
            || !is_file($realFilepath)
            || !is_readable($realFilepath)
        ) {
            return null;
        }

        $params += [
            'disposition_mode' => 'attachment',
            'cache' => false,
        ];
        if (!isset($params['filename']) || !strlen(trim((string) $params['filename']))) {
            $params['filename'] = basename($realFilepath);
        }

        return $this->getController()->sendFile($realFilepath, $params) ?: null;
    }
}
