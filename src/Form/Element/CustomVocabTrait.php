<?php declare(strict_types=1);

namespace Common\Form\Element;

use Omeka\Api\Manager as ApiManager;

/**
 * Adaptation of CustomVocabSelect.
 * Avoid the recursive loop for any type of vocab.
 *
 * @see \CustomVocab\Form\Element\CustomVocabSelect
 */
trait CustomVocabTrait
{
    use TraitOptionalElement;

    /**
     * @var ApiManager
     */
    protected $api;

    public function getValueOptions() : array
    {
        /**
         * @var \CustomVocab\Api\Representation\CustomVocabRepresentation $customVocab
         */

        static $computeds = [];

        ksort($this->options);
        $hash = md5(serialize($this->options));
        if (isset($computeds[$hash])) {
            return $computeds[$hash];
        }

        $computeds[$hash] = [];

        $customVocabId = $this->getOption('custom_vocab_id');

        try {
            $customVocab = $this->api->read('custom_vocabs', $customVocabId)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return [];
        }

        $valueOptions = $customVocab->listValues($this->getOptions());

        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }

        $computeds[$hash] = $valueOptions;

        $this->setValueOptions($valueOptions);
        return $valueOptions;
    }

    public function setApiManager(ApiManager $api): self
    {
        $this->api = $api;
        return $this;
    }
}
