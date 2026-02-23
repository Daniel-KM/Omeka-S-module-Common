<?php declare(strict_types=1);

namespace Common\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use DOMDocument;
use finfo;
use Laminas\Log\Logger;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use XMLReader;

/**
 * Get a more precise media type for files: xml, json, zip and binary ones.
 *
 * @see \Omeka\File\TempFile
 *
 * Copy or old adaptations:
 *
 * @see \Common\Mvc\Controller\Plugin\SpecifyMediaType
 * @see \EasyAdmin\Mvc\Controller\Plugin\SpecifyMediaType
 * @see \IiifSearch\Mvc\Controller\Plugin\SpecifyMediaType
 * @see \XmlViewer\Mvc\Controller\Plugin\SpecifyMediaType
 *
 * @see \BulkImport\Form\Reader\XmlReaderParamsForm
 * @see \EasyAdmin /data/media-types/media-type-identifiers
 * @see \ExtractText /data/media-types/media-type-identifiers
 * @see \IiifSearch /data/media-types/media-type-identifiers
 * @see \IiifServer\Iiif\TraitIiifType
 * @see \ModelViewer /data/media-types/media-type-identifiers
 * @see \XmlViewer /data/media-types/media-type-identifiers
 */
class SpecifyMediaType extends AbstractPlugin
{
    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * List of normalized media types extracted from files metadata.
     *
     * @var array
     */
    protected $mediaTypesIdentifiers;

    /**
     * @var string
     */
    protected $filepath;

    public function __construct(
        Logger $logger,
        Connection $connection,
        string $basePath,
        array $mediaTypesIdentifiers
    ) {
        $this->logger = $logger;
        $this->connection = $connection;
        $this->basePath = $basePath;
        $this->mediaTypesIdentifiers = $mediaTypesIdentifiers;
    }

    public function __invoke(string $filepath, ?string $mediaType = null): ?string
    {
        $this->filepath = $filepath;

        // The media type may be already properly detected.
        if (!$mediaType) {
            $mediaType = $this->simpleMediaType();
        }
        if ($mediaType === 'text/xml' || $mediaType === 'application/xml') {
            $mediaType = $this->getMediaTypeXml() ?: $mediaType;
        }
        if ($mediaType === 'application/zip') {
            $mediaType = $this->getMediaTypeZip() ?: $mediaType;
        }
        if ($mediaType === 'application/json') {
            $mediaType = $this->getMediaTypeJson() ?: $mediaType;
        }
        if ($mediaType === 'text/html') {
            $mediaType = $this->getMediaTypeHtml() ?: $mediaType;
        }
        if ($mediaType === 'application/octet-stream') {
            $mediaType = $this->getMediaTypeOctetStream() ?: $mediaType;
        }
        if ($mediaType === 'text/plain') {
            $mediaType = $this->getMediaTypeText() ?: $mediaType;
        }
        return $mediaType;
    }

    /**
     * Get the Internet media type of the file.
     *
     * @uses finfo
     */
    protected function simpleMediaType(): ?string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mediaType = $finfo->file($this->filepath) ?: null;
        if (array_key_exists($mediaType, \Omeka\File\TempFile::MEDIA_TYPE_ALIASES)) {
            $mediaType = \Omeka\File\TempFile::MEDIA_TYPE_ALIASES[$mediaType];
        }
        return $mediaType;
    }

    /**
     * Extract a more precise xml media type when possible.
     */
    protected function getMediaTypeXml(): ?string
    {
        $type = null;

        libxml_clear_errors();

        //  Try xmlreader first, quicker, but more strict.
        $reader = new XMLReader();
        if ($reader->open($this->filepath)) {
            // Don't output error in case of a badly formatted file since there is no logger.
            while (@$reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    $type = $reader->name;
                    break;
                }

                if ($reader->nodeType === XMLReader::PI
                    && !in_array($reader->name, ['xml-stylesheet', 'oxygen'])
                ) {
                    $matches = [];
                    if (preg_match('~href="(.+?)"~mi', $reader->value, $matches)) {
                        $type = $matches[1];
                        break;
                    }
                }

                if ($reader->nodeType === XMLReader::ELEMENT) {
                    // Fix exception.
                    if ($reader->namespaceURI === 'urn:oasis:names:tc:opendocument:xmlns:office:1.0') {
                        $type = $reader->getAttributeNs('mimetype', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
                    } else {
                        $type = $reader->namespaceURI ?: $reader->getAttribute('xmlns') ?: $reader->name;
                    }
                    break;
                }
            }

            $reader->close();

            $error = libxml_get_last_error();
            if ($error) {
                // TODO Message is kept for existing translations, but it is possible to log error directly.
                $this->logger->warn(
                    'Xml parsing error level {level}, code {code}, for file "{file}" (media #{media_id}), line {line}, column {column}: {message}', // @translate
                    [
                        'level' => $error->level,
                        'code' => $error->code,
                        'file' => $error->file,
                        'media_id' => $this->getMediaIdFromFilePath(),
                        'line' => $error->line,
                        'column' => $error->column,
                        'message' => $error->message,
                    ]
                );
            }

            if ($type) {
                return $this->mediaTypesIdentifiers[$type] ?? null;
            }
        }

        // Try dom if xmlreader is too much strict.
        $dom = $this->loadAndFixXml();
        if (!$dom) {
            $this->logger->err(
                'The file "{file}" (media #{media_id}) is not parsable by xml reader neither dom.', // @translate
                ['file' => $this->filepath, 'media_id' => $this->getMediaIdFromFilePath()]
            );
            return null;
        }

        $type = $dom->documentElement->namespaceURI
            ?: ($dom->documentElement->getAttribute('xmlns')
            ?: ($dom->documentElement->tagName
            ?: ($dom->documentElement->localName
            ?: $dom->documentElement->nodeName)));

        // Fix exception.
        if ($type === 'urn:oasis:names:tc:opendocument:xmlns:office:1.0') {
            $type = $dom->documentElement->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:office:1.0', 'mimetype');
        }

        return $this->mediaTypesIdentifiers[$type] ?? null;
    }

    /**
     * Extract a more precise zipped media type when possible.
     *
     * In many cases, the media type is saved in a uncompressed file "mimetype"
     * at the beginning of the zip file. If present, get it.
     *
     * Also detects USDZ archives (first entry is a .usdc or .usda).
     * @link https://openusd.org/release/spec_usdz.html
     */
    protected function getMediaTypeZip(): ?string
    {
        $handle = @fopen($this->filepath, 'rb');
        if (!$handle) {
            return null;
        }
        $contents = fread($handle, 256);
        fclose($handle);

        if (strlen($contents) < 32) {
            return null;
        }

        // Many formats (EPUB, ODF…) store the type in an uncompressed
        // "mimetype" file as first entry.
        if (substr($contents, 30, 8) === 'mimetype') {
            $end = strpos($contents, 'PK', 38);
            if ($end !== false) {
                return substr($contents, 38, $end - 38);
            }
        }

        // USDZ: uncompressed ZIP whose first entry is a .usdc/.usda.
        $nameLen = unpack('v', substr($contents, 26, 2))[1] ?? 0;
        if ($nameLen > 0 && $nameLen < 200) {
            $firstName = substr($contents, 30, $nameLen);
            $ext = strtolower(pathinfo($firstName, PATHINFO_EXTENSION));
            if ($ext === 'usdc' || $ext === 'usda') {
                return 'model/vnd.usdz+zip';
            }
        }

        return null;
    }

    /**
     * Extract a more precise json media type when possible.
     *
     * Detects glTF, GeoJSON and JSON-LD among generic json
     * files. Only files up to 5 MB are decoded to avoid memory
     * issues with very large datasets (e.g. GeoJSON).
     *
     * @link https://registry.khronos.org/glTF/specs/2.0/glTF-2.0.html
     * @link https://datatracker.ietf.org/doc/html/rfc7946
     * @link https://www.w3.org/TR/json-ld11/
     */
    protected function getMediaTypeJson(): ?string
    {
        if (@filesize($this->filepath) > 5242880) {
            return null;
        }

        $content = @file_get_contents($this->filepath);
        if (!$content) {
            return null;
        }

        $json = @json_decode($content, true);
        if (!is_array($json)) {
            return null;
        }

        // glTF 2.0: required top-level "asset" with "version".
        if (isset($json['asset']['version'])) {
            return 'model/gltf+json';
        }

        // GeoJSON (RFC 7946): "type" is one of 9 values.
        if (isset($json['type'])
            && in_array($json['type'], [
                'FeatureCollection', 'Feature',
                'Point', 'MultiPoint',
                'LineString', 'MultiLineString',
                'Polygon', 'MultiPolygon',
                'GeometryCollection',
            ])
        ) {
            return 'application/geo+json';
        }

        // JSON-LD (W3C): "@context" at top level.
        if (array_key_exists('@context', $json)) {
            return 'application/ld+json';
        }

        return null;
    }

    /**
     * Detect hOCR among html files.
     *
     * hOCR is an html-based format for ocr output (tesseract, etc.) with
     * extension ".hocr.html". Since finfo returns "text/html", check file
     * extension or content for hOCR markers.
     */
    protected function getMediaTypeHtml(): ?string
    {
        // Quick check: compound extension ".hocr.html" or ".hocr".
        $filepath = $this->filepath;
        if (substr($filepath, -10) === '.hocr.html'
            || substr($filepath, -5) === '.hocr'
        ) {
            return 'text/vnd.hocr+html';
        }

        // Content check: look for hOCR class markers in first 4 KB.
        $handle = @fopen($filepath, 'rb');
        if (!$handle) {
            return null;
        }
        $head = fread($handle, 4096);
        fclose($handle);
        if ($head && strpos($head, 'ocr_page') !== false) {
            return 'text/vnd.hocr+html';
        }

        return null;
    }

    /**
     * Detect 3D model formats among generic octet-stream files.
     *
     * PHP's bundled libmagic (< 5.45) does not recognize GLB and FBX.
     *
     * @link https://registry.khronos.org/glTF/specs/2.0/glTF-2.0.html#glb-file-format-specification
     */
    protected function getMediaTypeOctetStream(): ?string
    {
        $handle = @fopen($this->filepath, 'rb');
        if (!$handle) {
            return null;
        }
        $head = fread($handle, 32);
        fclose($handle);

        if (strlen($head) < 4) {
            return null;
        }

        // GLB (glTF Binary): 12-byte header starting with "glTF".
        if (substr($head, 0, 4) === 'glTF') {
            return 'model/gltf-binary';
        }

        // FBX Binary (Autodesk FilmBox): 23-byte header
        // "Kaydara FBX Binary  \0".
        // No IANA type; the type below is used by ModelViewer.
        if (strlen($head) >= 21
            && substr($head, 0, 21) === "Kaydara FBX Binary  \x00"
        ) {
            return 'model/vnd.filmbox';
        }

        return null;
    }

    /**
     * Detect specialized formats among files reported as text/plain.
     *
     * finfo returns text/plain for several binary or structured formats whose
     * header happens to be printable ascii.
     *
     * @link https://www.asprs.org/wp-content/uploads/2019/07/LAS_1_4_r15.pdf
     * @link https://openusd.org/release/spec_usdz.html
     */
    protected function getMediaTypeText(): ?string
    {
        $handle = @fopen($this->filepath, 'rb');
        if (!$handle) {
            return null;
        }
        $head = fread($handle, 16);
        fclose($handle);

        if (strlen($head) < 4) {
            return null;
        }

        // LAS (LIDAR point cloud): magic "LASF" at offset 0.
        if (substr($head, 0, 4) === 'LASF') {
            return 'application/vnd.las';
        }

        // USDC (USD Crate binary): magic "PXR-USDC" at offset 0.
        // No specific IANA type for USDC; model/vnd.usda covers only
        // the ascii variant. The generic model/vnd.usd is used here.
        if (strlen($head) >= 8
            && substr($head, 0, 8) === 'PXR-USDC'
        ) {
            return 'model/vnd.usd';
        }

        // USDA (USD ascii): magic "#usda " at offset 0.
        if (strlen($head) >= 6
            && substr($head, 0, 6) === '#usda '
        ) {
            return 'model/vnd.usda';
        }

        // PLY (Stanford Polygon Format): magic "ply\n" or "ply\r\n".
        // No IANA-registered type; application/x-ply is commonly used
        // by tools such as MeshLab.
        if (substr($head, 0, 4) === "ply\n"
            || substr($head, 0, 5) === "ply\r\n"
        ) {
            return 'application/x-ply';
        }

        return null;
    }

    /**
     * Try to fix xml content before parsing.
     */
    protected function loadAndFixXml(): ?\DOMDocument
    {
        $xmlContent = file_get_contents($this->filepath);
        if ($xmlContent) {
            $xmlContent = $this->fixUtf8($xmlContent);
            if ($xmlContent) {
                return $this->fixXmlDom($xmlContent);
            }
        }
        return null;
    }

    /**
     * Some utf-8 files, generally edited under Windows, should be cleaned.
     *
     * Microsoft encodes many files with 16 bits (utf-16), so edition of a file
     * encoded as utf-8 may be broken, or the file may be converted into utf-16
     * without warning. This is particularly insidiuous for xml files, because
     * its encoding scheme is declared in the first line and is not updated.
     * The issue occurs with json files too, that must be utf-8 encoded.
     *
     * Microsoft is aware of this issue and that standards are not respected
     * since some decades, but reject fixes, that is one of the multiple
     * disloyal ways to force users to do all their workflow under Microsoft
     * tools (server, os, office, etc.).
     *
     * The same issue occurs with Apple and Google or with any monopolistic
     * publisher, so use only open standards and tools that really respect them,
     * generallly free (libre).
     *
     * @see https://stackoverflow.com/questions/1401317/remove-non-utf8-characters-from-string#1401716
     *
     * Helper available in:
     * @see \EasyAdmin\Mvc\Controller\Plugin\SpecifyMediaType::fixUtf8()
     * @see \IiifServer\Mvc\Controller\Plugin\FixUtf8
     * @see \IiifSearch\View\Helper\FixUtf8
     */
    protected function fixUtf8($string): string
    {
        $string = (string) $string;
        if (!strlen($string)) {
            return $string;
        }

        $regex = <<<'REGEX'
            /
            (
                (?: [\x00-\x7F]               # single-byte sequences   0xxxxxxx
                |   [\xC0-\xDF][\x80-\xBF]    # double-byte sequences   110xxxxx 10xxxxxx
                |   [\xE0-\xEF][\x80-\xBF]{2} # triple-byte sequences   1110xxxx 10xxxxxx * 2
                |   [\xF0-\xF7][\x80-\xBF]{3} # quadruple-byte sequence 11110xxx 10xxxxxx * 3
                ){1,100}                      # ...one or more times
            )
            | ( [\x80-\xBF] )                 # invalid byte in range 10000000 - 10111111
            | ( [\xC0-\xFF] )                 # invalid byte in range 11000000 - 11111111
            /x
            REGEX;

        $utf8replacer = function ($captures) {
            if ($captures[1] !== '') {
                // Valid byte sequence. Return unmodified.
                return $captures[1];
            } elseif ($captures[2] !== '') {
                // Invalid byte of the form 10xxxxxx.
                // Encode as 11000010 10xxxxxx.
                return "\xC2" . $captures[2];
            } else {
                // Invalid byte of the form 11xxxxxx.
                // Encode as 11000011 10xxxxxx.
                return "\xC3" . chr(ord($captures[3]) - 64);
            }
        };

        // Log invalid files.
        $count = 0;
        $result = preg_replace_callback($regex, $utf8replacer, $string, -1, $count);
        if ($count && $string !== $result) {
            $this->logger->warn(
                'Warning: some files contain invalid unicode characters and cannot be processed directly.' // @translate
            );
        }

        return $result;
    }

    protected function fixXmlDom(string $xmlContent): ?DOMDocument
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.1', 'UTF-8');
        $dom->strictErrorChecking = false;
        $dom->validateOnParse = false;
        $dom->recover = true;
        $dom->loadXML($xmlContent, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        return $dom;
    }

    protected function getMediaIdFromFilePath(): ?int
    {
        $pos = mb_strpos($this->filepath, $this->basePath);
        if ($pos !== 0) {
            return null;
        }

        $storage = mb_substr($this->filepath, mb_strlen($this->basePath) + 1);

        // Remove the main dir ("original", "medium"…).
        $pos = mb_strpos($storage, '/');
        if ($pos !== false) {
            $storage = mb_substr($storage, $pos + 1);
        }

        // Manage ArchiveRepertory.
        $lengthExtension = mb_strlen(pathinfo($storage, PATHINFO_EXTENSION));
        $storageId = $lengthExtension ? mb_substr($storage, 0, -$lengthExtension - 1) : $storage;

        $sql = 'SELECT `id` FROM `media` WHERE `storage_id` = :storage_id LIMIT 1;';
        $result = $this->connection->executeQuery($sql, ['storage_id' => $storageId])->fetchOne();
        return $result ? (int) $result : null;
    }
}
