<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element\MultiCheckbox;
use Omeka\Api\Manager as ApiManager;

/**
 * Adaptation of CustomVocabSelect.
 * @see \CustomVocab\Form\Element\CustomVocabSelect
 */
class CustomVocabMultiCheckbox extends MultiCheckbox
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

        $this->setValueOptions($valueOptions);
        return $valueOptions;
    }

    public function setApiManager(ApiManager $api): self
    {
        $this->api = $api;
        return $this;
    }
}
