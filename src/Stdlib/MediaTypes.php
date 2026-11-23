<?php declare(strict_types=1);

namespace Common\Stdlib;

use Omeka\Settings\SettingsInterface;

/**
 * Allow media types and extensions missing in the whitelists of the core.
 *
 * Used by the modules that manage specific files, generally on install.
 */
class MediaTypes
{
    /**
     * Append media types and extensions to the whitelists, kept sorted.
     *
     * @return bool True when a whitelist was updated.
     */
    public static function allow(SettingsInterface $settings, array $mediaTypes, array $extensions = []): bool
    {
        $updated = false;
        foreach (['media_type_whitelist' => $mediaTypes, 'extension_whitelist' => $extensions] as $key => $values) {
            $whitelist = array_values((array) $settings->get($key, []));
            $merged = array_values(array_unique(array_merge($whitelist, $values)));
            if (count($merged) !== count($whitelist)) {
                sort($merged);
                $settings->set($key, $merged);
                $updated = true;
            }
        }
        return $updated;
    }
}
