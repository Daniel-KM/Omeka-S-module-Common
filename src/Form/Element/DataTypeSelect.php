<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element\Select;
use Omeka\DataType\Manager as DataTypeManager;

class DataTypeSelect extends Select
{
    use TraitOptionalElement;
    use TraitPrependValuesOptions;

    protected $attributes = [
        'type' => 'select',
        'multiple' => false,
        'class' => 'chosen-select',
    ];

    /**
     * @var DataTypeManager
     */
    protected $dataTypeManager;

    /**
     * @var array
     */
    protected $dataDataTypes = [];

    public function getValueOptions(): array
    {
        // Set a flag to fix recursive methods when a validator is set or to use
        // the select when present inside a fieldset.
        /** @see \Laminas\Form\Element\Select::setValueOptions() */
        static $flag = false;

        if ($flag) {
            return $this->valueOptions;
        }

        /** @see \Omeka\View\Helper\DataType::getSelect() */
        $options = [];
        $optgroupOptions = [];
        foreach ($this->dataDataTypes as $dataTypeName => $dataDataType) {
            if ($dataDataType['opt_group_label']) {
                // Hash the optgroup key to avoid collisions when merging with
                // data types without an optgroup.
                $optgroupKey = md5($dataDataType['opt_group_label']);
                // Put resource data types before ones added by modules.
                $optionsVal = in_array($dataTypeName, [
                    'resource',
                    'resource:item',
                    'resource:itemset',
                    'resource:media',
                    'resource:annotation',
                ])
                    ? 'options'
                    : 'optgroupOptions';
                if (!isset(${$optionsVal}[$optgroupKey])) {
                    ${$optionsVal}[$optgroupKey] = [
                        'label' => $dataDataType['opt_group_label'],
                        'options' => [],
                    ];
                }
                ${$optionsVal}[$optgroupKey]['options'][$dataTypeName] = $dataDataType['label'];
            } else {
                $options[$dataTypeName] = $dataDataType['label'];
            }
        }
        // Always put data types not organized in option groups before data
        // types organized within option groups.
        $valueOptions = array_merge($options, $optgroupOptions);

        $valueOptions = $this->prependValuesOptions($valueOptions);

        $flag = true;
        $this->setValueOptions($valueOptions);
        $flag = false;

        return $valueOptions;
    }

    public function setDataTypeManager(DataTypeManager $dataTypeManager): self
    {
        $this->dataTypeManager = $dataTypeManager;
        $this->prepareDataDataTypes();
        return $this;
    }

    /**
     * Create the list of data types one time early.
     */
    protected function prepareDataDataTypes(): self
    {
        $this->dataDataTypes = [];
        foreach ($this->dataTypeManager->getRegisteredNames() as $dataTypeName) {
            /** @var \Omeka\DataType\DataTypeInterface $dataType */
            $dataType = $this->dataTypeManager->get($dataTypeName);
            $this->dataDataTypes[$dataTypeName] = [
                'name' => $dataTypeName,
                'label' => $dataType->getLabel(),
                'opt_group_label' => $dataType->getOptgroupLabel(),
            ];
        }
        return $this;
    }
}
