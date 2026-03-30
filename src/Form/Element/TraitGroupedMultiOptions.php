<?php declare(strict_types=1);

namespace Common\Form\Element;

/**
 * Support optgroup-style value_options for MultiCheckbox and Radio.
 *
 * Laminas MultiCheckbox and Radio do not support the optgroup format
 * used by Select (label + options). This trait normalizes optgroups
 * into flat options. The first option of each group gets
 * label_attributes with inline styles that mimic a group heading
 * via CSS padding-top and a data-group-label rendered by the browser
 * as a bold block label before the checkbox.
 *
 * The rendering uses inline styles only, so no external CSS file is
 * needed.
 *
 * Usage in value_options (same format as Select):
 *
 *     'value_options' => [
 *         'group1' => [
 *             'label' => 'Group 1',
 *             'options' => [
 *                 'a' => 'Option A',
 *                 'b' => 'Option B',
 *             ],
 *         ],
 *         'c' => 'Option C (ungrouped)',
 *     ],
 */
trait TraitGroupedMultiOptions
{
    /**
     * Flatten optgroup-style value_options into flat options with
     * inline-styled group headings.
     */
    public function setValueOptions(array $options)
    {
        $hasGroups = false;
        foreach ($options as $spec) {
            if (is_array($spec) && isset($spec['options'], $spec['label'])) {
                $hasGroups = true;
                break;
            }
        }
        if (!$hasGroups) {
            return parent::setValueOptions($options);
        }

        $flat = [];
        foreach ($options as $key => $spec) {
            if (is_array($spec) && isset($spec['options'], $spec['label'])) {
                // Insert a disabled heading option for the group title.
                $flat['_group_' . $key] = [
                    'value' => '',
                    'label' => $spec['label'],
                    'disabled' => true,
                    'label_attributes' => [
                        'style' => 'display: block; clear: both; margin-top: 0.5em; font-weight: bold;',
                    ],
                    'attributes' => [
                        'style' => 'display: none;',
                    ],
                ];
                foreach ($spec['options'] as $optKey => $optSpec) {
                    if (is_scalar($optSpec)) {
                        $optSpec = [
                            'value' => $optKey,
                            'label' => $optSpec,
                        ];
                    }
                    $flat[$optSpec['value'] ?? $optKey] = $optSpec;
                }
            } else {
                $flat[$key] = $spec;
            }
        }

        return parent::setValueOptions($flat);
    }
}
