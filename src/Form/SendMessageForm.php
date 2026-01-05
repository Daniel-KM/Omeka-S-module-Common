<?php declare(strict_types=1);

namespace Common\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Form;

/**
 * Base form for sending messages.
 *
 * This form can be extended by modules to add specific fields.
 * Options:
 * - has_resource_id (bool): Add a hidden resource_id field.
 * - resource_id_name (string): Name of the resource id field (default: resource_id).
 * - has_cc (bool): Add cc field.
 * - has_bcc (bool): Add bcc field.
 * - has_reply_to (bool): Add reply-to field.
 * - has_myself (bool): Add "myself" multi-checkbox for cc/bcc/reply.
 * - has_reject (bool): Add reject checkbox (for moderation).
 * - subject_value (string): Default value for subject.
 * - body_value (string): Default value for body.
 *
 *Adapted from old versions of:
 * @see \Contribute\Form\SendMessageForm
 * @see \Selection\Form\SendMessageForm
 */
class SendMessageForm extends Form
{
    /**
     * @var array
     */
    protected $formOptions = [
        'has_resource_id' => false,
        'resource_id_name' => 'resource_id',
        'has_cc' => false,
        'has_bcc' => false,
        'has_reply_to' => false,
        'has_myself' => false,
        'has_reject' => false,
        'subject_value' => '',
        'body_value' => '',
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'send-message-form')
            ->setAttribute('class', 'form-send-message jsend-form')
            ->setAttribute('method', 'post')
            ->setName('send-message');

        // Resource id field (optional, hidden).
        if (!empty($this->formOptions['has_resource_id'])) {
            $resourceIdName = $this->formOptions['resource_id_name'] ?: 'resource_id';
            $this
                ->add([
                    'name' => $resourceIdName,
                    'type' => Element\Hidden::class,
                    'attributes' => [
                        'id' => strtr($resourceIdName, ['_' => '-']),
                    ],
                ]);
        }

        // Subject field.
        $this
            ->add([
                'name' => 'subject',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Subject', // @translate
                ],
                'attributes' => [
                    'id' => 'subject',
                    'required' => false,
                    'value' => $this->formOptions['subject_value'] ?? '',
                ],
            ]);

        // Body/message field.
        $this
            ->add([
                'name' => 'body',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Message', // @translate
                    'label_attributes' => [
                        'class' => 'required',
                    ],
                ],
                'attributes' => [
                    'id' => 'body',
                    'rows' => 10,
                    'required' => true,
                    'value' => $this->formOptions['body_value'] ?? '',
                ],
            ]);

        // Reject checkbox (for moderation).
        if (!empty($this->formOptions['has_reject'])) {
            $this
                ->add([
                    'name' => 'reject',
                    'type' => Element\Checkbox::class,
                    'options' => [
                        'label' => 'Mark not submitted', // @translate
                    ],
                    'attributes' => [
                        'id' => 'reject',
                    ],
                ]);
        }

        // Myself multi-checkbox.
        if (!empty($this->formOptions['has_myself'])) {
            $this
                ->add([
                    'name' => 'myself',
                    'type' => CommonElement\OptionalMultiCheckbox::class,
                    'options' => [
                        'label' => 'Add myself as', // @translate
                        'value_options' => [
                            'cc' => 'cc', // @translate
                            'bcc' => 'bcc', // @translate
                            'reply' => 'Reply to', // @translate
                        ],
                    ],
                    'attributes' => [
                        'id' => 'myself',
                    ],
                ]);
        }

        // CC field (copy-carbon).
        if (!empty($this->formOptions['has_cc'])) {
            $this
                ->add([
                    'name' => 'cc',
                    'type' => CommonElement\ArrayText::class,
                    'options' => [
                        'label' => 'Add specific emails as cc', // @translate
                        'info' => 'Use "=" to separate multiple emails.', // @translate
                        'value_separator' => '=',
                    ],
                    'attributes' => [
                        'id' => 'cc',
                    ],
                ]);
        }

        // BCC field (blind copy-carbon).
        if (!empty($this->formOptions['has_bcc'])) {
            $this
                ->add([
                    'name' => 'bcc',
                    'type' => CommonElement\ArrayText::class,
                    'options' => [
                        'label' => 'Add specific emails as bcc', // @translate
                        'value_separator' => '=',
                    ],
                    'attributes' => [
                        'id' => 'bcc',
                    ],
                ]);
        }

        // Reply-to field.
        if (!empty($this->formOptions['has_reply_to'])) {
            $this
                ->add([
                    'name' => 'reply',
                    'type' => CommonElement\ArrayText::class,
                    'options' => [
                        'label' => 'Add specific emails as reply-to', // @translate
                        'value_separator' => '=',
                    ],
                    'attributes' => [
                        'id' => 'reply',
                    ],
                ]);
        }

        // Submit button.
        $this
            ->add([
                'name' => 'submit',
                'type' => Element\Button::class,
                'options' => [
                    'label' => 'Send message', // @translate
                ],
                'attributes' => [
                    'id' => 'submit',
                    'type' => 'submit',
                    'class' => 'submit',
                    'data-spinner' => 'true',
                ],
            ]);
    }

    /**
     * Set form options.
     */
    public function setFormOptions(array $options): self
    {
        $this->formOptions = $options + $this->formOptions;
        return $this;
    }

    /**
     * Get form options.
     */
    public function getFormOptions(): array
    {
        return $this->formOptions;
    }
}
