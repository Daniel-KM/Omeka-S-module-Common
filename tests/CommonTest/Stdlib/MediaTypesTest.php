<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

class MediaTypesTest extends \CommonTest\AbstractHttpControllerTestCase
{
    protected $settings;
    protected $whitelists = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->settings = $this->getApplication()->getServiceManager()->get('Omeka\Settings');
        foreach (['media_type_whitelist', 'extension_whitelist'] as $key) {
            $this->whitelists[$key] = $this->settings->get($key);
        }
    }

    public function tearDown(): void
    {
        foreach ($this->whitelists as $key => $value) {
            $this->settings->set($key, $value);
        }
        parent::tearDown();
    }

    /**
     * The media types and extensions are appended once to the whitelists, that
     * are kept sorted.
     */
    public function testSubtitlesAreAllowed(): void
    {
        $this->settings->set('media_type_whitelist', ['text/plain', 'image/png']);
        $this->settings->set('extension_whitelist', ['txt', 'vtt']);

        $mediaTypes = ['text/vtt', 'application/x-subrip'];
        $extensions = ['vtt', 'srt'];
        $this->assertTrue(\Common\Stdlib\MediaTypes::allow($this->settings, $mediaTypes, $extensions));
        $this->assertFalse(\Common\Stdlib\MediaTypes::allow($this->settings, $mediaTypes, $extensions));

        $this->assertSame(['application/x-subrip', 'image/png', 'text/plain', 'text/vtt'], $this->settings->get('media_type_whitelist'));
        $this->assertSame(['srt', 'txt', 'vtt'], $this->settings->get('extension_whitelist'));
    }
}
