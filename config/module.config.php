<?php declare(strict_types=1);

namespace Common;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'assetUrl' => Service\ViewHelper\AssetUrlFactory::class,
            'easyMeta' => Service\ViewHelper\EasyMetaFactory::class,
            'matchedRouteName' => Service\ViewHelper\MatchedRouteNameFactory::class,
            'mediaTypeSelect' => Service\ViewHelper\MediaTypeSelectFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\Element\OptionalMultiCheckbox::class => Form\Element\OptionalMultiCheckbox::class,
            Form\Element\OptionalRadio::class => Form\Element\OptionalRadio::class,
            Form\Element\OptionalSelect::class => Form\Element\OptionalSelect::class,
            Form\Element\OptionalUrl::class => Form\Element\OptionalUrl::class,
            Form\Element\UrlQuery::class => Form\Element\UrlQuery::class,
        ],
        'factories' => [
            Form\Element\MediaTypeSelect::class => Service\Form\Element\MediaTypeSelectFactory::class,
            // These three elements are overridden from core in order to be able to fix prepend value "0".
            Form\Element\ItemSetSelect::class => Service\Form\Element\ItemSetSelectFactory::class,
            Form\Element\ResourceTemplateSelect::class => Service\Form\Element\ResourceTemplateSelectFactory::class,
            Form\Element\SiteSelect::class => Service\Form\Element\SiteSelectFactory::class,
        ],
        'aliases' => [
            \Omeka\Form\Element\ItemSetSelect::class => Form\Element\ItemSetSelect::class,
            \Omeka\Form\Element\ResourceTemplateSelect::class => Form\Element\ResourceTemplateSelect::class,
            \Omeka\Form\Element\SiteSelect::class => Form\Element\SiteSelect::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'assets' => [
        // Override internals assets. Only for Omeka assets: modules can use another filename.
        'internals' => [
        ],
    ],
    'common' => [
    ],
];