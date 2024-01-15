<?php declare(strict_types=1);

namespace Common;

return [
    'service_manager' => [
        'factories' => [
            'EasyMeta' => Service\Stdlib\EasyMetaFactory::class,
            // TODO Use a delegator for dispatcher and logger factories? A direct factory is simpler for the same result for these services.
            // Allow to use the PSR-3 formatter in job.
            'Omeka\Job\Dispatcher' => Service\Job\DispatcherFactory::class,
            // Allow to add the PSR-3 formatter to default logger.
            'Omeka\Logger' => Service\LoggerFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            // Required to manage PsrMessage.
            'messages' => View\Helper\Messages::class,
        ],
        'factories' => [
            'assetUrl' => Service\ViewHelper\AssetUrlFactory::class,
            'easyMeta' => Service\ViewHelper\EasyMetaFactory::class,
            'matchedRouteName' => Service\ViewHelper\MatchedRouteNameFactory::class,
            'mediaTypeSelect' => Service\ViewHelper\MediaTypeSelectFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\Element\OptionalCheckbox::class => Form\Element\OptionalCheckbox::class,
            Form\Element\OptionalMultiCheckbox::class => Form\Element\OptionalMultiCheckbox::class,
            Form\Element\OptionalNumber::class => Form\Element\OptionalNumber::class,
            Form\Element\OptionalRadio::class => Form\Element\OptionalRadio::class,
            Form\Element\OptionalSelect::class => Form\Element\OptionalSelect::class,
            Form\Element\OptionalUrl::class => Form\Element\OptionalUrl::class,
            Form\Element\UrlQuery::class => Form\Element\UrlQuery::class,
        ],
        'factories' => [
            Form\Element\MediaTypeSelect::class => Service\Form\Element\MediaTypeSelectFactory::class,
            // Overridden from Core.
            Form\Element\DataTypeSelect::class => Service\Form\Element\DataTypeSelectFactory::class,
            // Override in order to fix prepend value "0".
            Form\Element\ItemSetSelect::class => Service\Form\Element\ItemSetSelectFactory::class,
            Form\Element\ResourceTemplateSelect::class => Service\Form\Element\ResourceTemplateSelectFactory::class,
            Form\Element\SiteSelect::class => Service\Form\Element\SiteSelectFactory::class,
        ],
        'aliases' => [
            // Use aliases to keep core keys.
            'Omeka\Form\Element\DataTypeSelect' => Form\Element\DataTypeSelect::class,
            \Omeka\Form\Element\ItemSetSelect::class => Form\Element\ItemSetSelect::class,
            \Omeka\Form\Element\ResourceTemplateSelect::class => Form\Element\ResourceTemplateSelect::class,
            \Omeka\Form\Element\SiteSelect::class => Form\Element\SiteSelect::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'easyMeta' => Service\ControllerPlugin\EasyMetaFactory::class,
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
