<?php declare(strict_types=1);

namespace Common;

return [
    'service_manager' => [
        'factories' => [
            'EasyMeta' => Service\Stdlib\EasyMetaFactory::class,
            // TODO Use a delegator for file, dispatcher and logger factories? A direct factory is simpler for the same result for these services.
            'Omeka\File\TempFileFactory' => Service\File\TempFileFactoryFactory::class,
            'Omeka\File\Validator' => Service\File\ValidatorFactory::class,
            // Allow to use the PSR-3 formatter in job.
            'Omeka\Job\Dispatcher' => Service\Job\DispatcherFactory::class,
            // Allow to add the PSR-3 formatter to default logger.
            'Omeka\Logger' => Service\LoggerFactory::class,
        ],
        'delegators' => [
            'Laminas\I18n\Translator\TranslatorInterface' => [
                Service\Delegator\TranslatorDelegatorFactory::class,
            ],
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
            'defaultSite' => Service\ViewHelper\DefaultSiteFactory::class,
            'easyMeta' => Service\ViewHelper\EasyMetaFactory::class,
            'matchedRouteName' => Service\ViewHelper\MatchedRouteNameFactory::class,
            'mediaTypeSelect' => Service\ViewHelper\MediaTypeSelectFactory::class,
            'translator' => Service\ViewHelper\TranslatorFactory::class,
        ],
    ],
    // Add some common elements and make standard elements and some omeka ones optional.
    // The elements of the module Advanced Search that add features are not included.
    'form_elements' => [
        'invokables' => [
            Form\Element\ArrayText::class => Form\Element\ArrayText::class,
            Form\Element\DataTextarea::class => Form\Element\DataTextarea::class,
            Form\Element\OptionalCheckbox::class => Form\Element\OptionalCheckbox::class,
            Form\Element\OptionalDate::class => Form\Element\OptionalDate::class,
            Form\Element\OptionalDateTime::class => Form\Element\OptionalDateTime::class,
            Form\Element\OptionalMultiCheckbox::class => Form\Element\OptionalMultiCheckbox::class,
            Form\Element\OptionalNumber::class => Form\Element\OptionalNumber::class,
            Form\Element\OptionalRadio::class => Form\Element\OptionalRadio::class,
            Form\Element\OptionalSelect::class => Form\Element\OptionalSelect::class,
            Form\Element\OptionalUrl::class => Form\Element\OptionalUrl::class,
            Form\Element\UrlQuery::class => Form\Element\UrlQuery::class,
        ],
        'factories' => [
            // This element does not exist in Omeka.
            Form\Element\CustomVocabsSelect::class => Service\Form\Element\CustomVocabsSelectFactory::class,
            // This element does not exist in Omeka.
            Form\Element\DataTypeSelect::class => Service\Form\Element\DataTypeSelectFactory::class,
            Form\Element\MediaIngesterSelect::class => Service\Form\Element\MediaIngesterSelectFactory::class,
            Form\Element\MediaRendererSelect::class => Service\Form\Element\MediaRendererSelectFactory::class,
            // This element does not exist in Omeka.
            Form\Element\MediaTypeSelect::class => Service\Form\Element\MediaTypeSelectFactory::class,
            // This element is not the same than \Omeka\Form\Element\SitePageSelect (singular site).
            Form\Element\SitesPageSelect::class => Service\Form\Element\SitesPageSelectFactory::class,
            // Optional core elements.
            Form\Element\OptionalItemSetSelect::class => Service\Form\Element\OptionalItemSetSelectFactory::class,
            Form\Element\OptionalPropertySelect::class => Service\Form\Element\OptionalPropertySelectFactory::class,
            Form\Element\OptionalResourceSelect::class => Service\Form\Element\OptionalResourceSelectFactory::class,
            Form\Element\OptionalResourceClassSelect::class => Service\Form\Element\OptionalResourceClassSelectFactory::class,
            Form\Element\OptionalResourceTemplateSelect::class => Service\Form\Element\OptionalResourceTemplateSelectFactory::class,
            Form\Element\OptionalRoleSelect::class => Service\Form\Element\OptionalRoleSelectFactory::class,
            Form\Element\OptionalSiteSelect::class => Service\Form\Element\OptionalSiteSelectFactory::class,
            Form\Element\OptionalUserSelect::class => Service\Form\Element\OptionalUserSelectFactory::class,
        ],
        'aliases' => [
            // Use aliases to keep core keys.
            'Omeka\Form\Element\DataTypeSelect' => Form\Element\DataTypeSelect::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'easyMeta' => Service\ControllerPlugin\EasyMetaFactory::class,
            'specifyMediaType' => Service\ControllerPlugin\SpecifyMediaTypeFactory::class,
            'translator' => Service\ControllerPlugin\TranslatorFactory::class,
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
