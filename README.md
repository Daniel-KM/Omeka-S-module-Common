Common Module (module for Omeka S)
===================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__


[Common Module] is a module for [Omeka S] that allows to manage internal
features used in various modules: bulk functions, form elements, view helpers,
one-time tasks for install and settings, etc., so it avoids the developer to
copy-paste common code between modules.

- Services, view helpers and controller plugins

  - AssetUrl for internal assets
  - DefaultSite
  - EasyMeta to get ids, terms and labels from properties, classes, templates,
    vocabularies; to get main data types too (literal, resource or uri); to get
    resource api names from any names used in Omeka and modules.
  - IsHomePage
  - IsHtml
  - IsXml
  - JSend to manage exchanges between a module and js part.
  - MatchedRouteName
  - SendEmail to send email with default values, in particular sender.
  - PrepareMessage to validate and fill messages with placeholders.
  - SpecifyMediaType

- Form elements

  - SendMessageForm (base form for sending messages with placeholders)
  - Array Text
  - Custom Vocab MultiCheckbox
  - Custom Vocab Radio
  - Custom Vocab Select
  - Custom Vocabs Select
  - Data Textarea
  - Data Type Select
  - Group Textarea
  - Ini Textarea
  - Media Ingester Select
  - Media Renderer Select
  - Media Type Select
  - Sites Page Select
  - Optional Checkbox
  - Optional Date
  - Optional DateTime
  - Optional Multi Checkbox
  - Optional Number
  - Optional Radio
  - Optional Select
  - Optional Url
  - Optional Item Set Select
  - Optional Property Select
  - Optional Resource Select
  - Optional Resource Class Select
  - Optional Resource Template Select
  - Optional Role Select
  - Optional Site Select
  - Optional User Select
  - Url Query

- [PSR-3]

  - The logger can log messages in a standard, simple and translatable way.
  - The class PsrMessage extends core `\Omeka\Stdlib\PsrMessage` since Omeka S 4.2,
    with translator awareness, PSR-3 and sprintf support. A polyfill is provided
    for version before Omeka S 4.2.

- One-Time tasks

  Internally, the logic is "config over code": so all settings have just to be
  set in the main `config/module.config.php` file, inside a key with the
  lowercas emodule name, with sub-keys `config`, `settings`, `site_settings`,
  `user_settings` and `block_settings`. All the forms have just to be standard
  Laminas forms.

  Eventual install and uninstall sql can be set in `data/install/` and upgrade
  code in `data/scripts`. Another class allows to check and install resources
  (vocabularies, resource templates, custom vocabs, etc.).

- Improved media type detection

  In many cases, in particular with xml or json, the media type should be
  refined to make features working. For example `text/xml` is not precise enough
  for the module IiifServer to manage xml ocr alto files, that should be
  identified with the right media type `application/alto+xml`. The same issue
  occurs with xml mets, tei, json-ld, etc.

- Internal assets

  By default, only external assets can be overridden in themes. This option in
  the config file allows to by-pass core assets. This is useful mainly for js in
  admin interface.


Installation
------------

See general end user documentation for [installing a module].

**IMPORTANT**: As long as [PR #2412] is not merged, use the Zip method or use
version 3.4.76.

* With composer (recommended, requires Omeka S with [PR #2412])

From the root of Omeka S, install the module:

```sh
composer require daniel-km/omeka-s-module-common
```

The module is automatically downloaded in `composer-addons/modules/` and ready to
be enabled in the admin interface.

* From the zip

Download the last release [Common.zip] from the list of releases, and uncompress
it in the `modules` directory.

* From the sources and for development

Install the sources in directory `modules`, rename the directory as `Common`, go
to the root of the module, run composer install, then :

```sh
cd modules
git clone https://gitlab.com/Daniel-KM/Omeka-S-module-Common.git Common
cd Common
composer install --no-dev
php ../../application/data/scripts/install-omeka-assets.php Common
```

* For test

The module includes a comprehensive test suite with unit and functional tests.
Run them from the root of Omeka:

```sh
vendor/bin/phpunit -c modules/Common/tests/phpunit.xml --testdox
```


Usage (for developer)
---------------------

### Services, controller plugins and view helpers

#### Api Adapter: Simple and complete query builder

Just add the trait `CommonAdapterTrait`, the list of query arguments for all
common queries and a call to `buildQueryFields()`. Then, all queries will work,
including queries with empty or multiple values and use of operators `<≤=≠≥>`.

#### Api Adapter: Simple hydrator

Just add the trait `CommonAdapterTrait` and it will automatically manage the
method `hydrate()` in a generic way, as long as the entity methods and the keys
used in the api or the form are compatible.

#### AssetUrl

Use helper assetUrl().

#### DefaultSite

Use helper defaultSite(). As argument, you may set id, slug, id_slug or slug_id.
The default is to return the representation.

#### EasyMeta

This service can be used as helper or plugin. It allows to get ids, terms and
labels from properties, classes, templates, vocabularies; to get main data types
too (literal, resource or uri); to get resource api names from any names used in
Omeka and modules.

To get methods and more details, use the autocompletion of your IDE.

#### IsHomePage

This view helper allows to check if the current page is the home page.

#### IsHtml

This view helper allows to check if a string is html, in particular to know if
it should be escaped or not.

The check is a basic one: the string should start with a "<", end with a ">" and
without tags. It may be a partial html. It is not possible to get a more precise
check before php 8.4.

#### IsXml

This view helper allows to check if a string is xml, in particular to know if
it should be escaped or not.

The check is a full one: the string should start with a "<", end with a ">" and
should be parsable via SimpleXml. It may be a partial xml.

#### jSend

The plugin jSend() allows to output a JsonModel formatted as [jSend] to simplify
exchanges with a third party or with a module that use some ajax, like [Contact Us],
[Contribute], [Selection], [Two Factor Authentication], etc.

Unlike [jSend], any response can have a status, data, a message and a code.
Some values are filled automatically from the messenger.

#### Messages

The method `getTranslatedMessages()` allows to get all translated messages as
array. It can be used for a json output.

The method `log()` allows to convert all messages into logs, for example to
manage background jobs and keep track of front-end messages of errors of some
modules.

#### Messenger

This is a fix on the core messenger: A form with any number of levels of
sub-messages can be managed.

#### MatchedRouteName

Allow to get the matched route name directly.

#### SendEmail

Allow to send an email. All arguments are optional, except the body. The sender
is the no-reply email of the module [Easy Admin] by default, else the
adminstrator email defined in main setting.

A quick antispam is included via the keywords set in the file /data/mailer/spam_keywords.php.
This file can be completed if needed. This feature will be improved or moved in
a future version.

#### SendFile

Send a file for download, without limit of size or memory, via a stream.
The content disposition can be set via the parameters of via the query (download=1 for attachment, else inline).

#### PrepareMessage

This controller plugin provides helper functions for preparing messages with
validation and placeholder support. It is designed to be used with modules like
[Contact Us], [Contribute], and [Selection].

Features:
- Validate message body and subject with configurable max lengths
- Fill messages with placeholders using moustache-style syntax (`{placeholder}`)
- Common placeholders are automatically available
- Extensible placeholder system for module-specific values

Common placeholders:
- `{ip}`: Client IP address
- `{main_title}`: Installation title
- `{main_url}`: Main URL
- `{site_title}`: Current site title
- `{site_url}`: Current site URL
- `{site_slug}`: Current site slug
- `{user_name}`: Current user name
- `{user_email}`: Current user email
- `{owner_name}`: Owner/recipient name
- `{owner_email}`: Owner/recipient email
- `{resource_id}`: Resource ID
- `{resource_title}`: Resource title
- `{resource_url}`: Resource public URL
- `{resource_url_admin}`: Resource admin URL

Resource property placeholders (when a resource is in context):
- `{prefix:localName}`: Value of any resource property (e.g. `{dcterms:title}`,
  `{dcterms:creator}`, `{dcterms:date}`). The first value is used if multiple
  values exist for the property.

Usage in a controller:

```php
// Validate message body.
$result = $this->prepareMessage()->validateBody($body);
if (!$result['valid']) {
    // Handle error: $result['error']
}

// Validate message subject.
$result = $this->prepareMessage()->validateSubject($subject);

// Fill a message template with placeholders.
$message = $this->prepareMessage()->fillMessage(
    'Hello {user_name}, your {resource_title} has been updated.',
    ['custom_field' => 'value'],  // Additional placeholders
    ['site' => $site, 'user' => $user, 'resource' => $resource]  // Context
);

// Add custom placeholders for all subsequent calls.
$this->prepareMessage()
    ->addPlaceholders(['custom_key' => 'custom_value'])
    ->fillMessage($template);

// Get default subject from settings with fallback.
$subject = $this->prepareMessage()->getDefaultSubject(
    'mymodule_email_subject',
    'Default subject for {site_title}',
    $placeholders,
    $context
);

// Process "myself" options for cc/bcc/reply-to.
$cc = $bcc = $replyTo = [];
$this->prepareMessage()->processMyselfOptions(
    ['cc', 'bcc'],  // Values from "myself" checkbox
    $user,
    $cc,
    $bcc,
    $replyTo
);
```

#### SendMessageForm

A base form class for sending messages that can be extended by modules.
It provides common fields (subject, body) and optional fields (cc, bcc, reply-to,
resource_id, reject checkbox, "myself" multi-checkbox).

Form options:
- `has_resource_id` (bool): Add a hidden resource_id field
- `resource_id_name` (string): Name of the resource id field (default: `resource_id`)
- `has_cc` (bool): Add cc email field
- `has_bcc` (bool): Add bcc email field
- `has_reply_to` (bool): Add reply-to email field
- `has_myself` (bool): Add "myself" multi-checkbox for cc/bcc/reply
- `has_reject` (bool): Add reject checkbox (for moderation workflows)
- `subject_value` (string): Default value for subject
- `body_value` (string): Default value for body

Usage in a module:

```php
// Option 1: Use directly with options.
$form = $this->getForm(\Common\Form\SendMessageForm::class);
$form->setFormOptions([
    'has_cc' => true,
    'has_bcc' => true,
    'has_myself' => true,
    'subject_value' => 'Your contribution',
    'body_value' => 'Dear {name}, thank you for your contribution.',
]);
$form->init();

// Option 2: Extend in your module.
namespace MyModule\Form;

use Common\Form\SendMessageForm as BaseSendMessageForm;

class SendMessageForm extends BaseSendMessageForm
{
    protected $formOptions = [
        'has_resource_id' => true,
        'resource_id_name' => 'contribution_id',
        'has_cc' => true,
        'has_myself' => true,
        'has_reject' => true,
    ];

    public function init(): void
    {
        parent::init();
        // Add custom fields here.
    }
}
```

#### SpecifyMediaType

Get the right media type of a file, even if it is a generic zipped, json or xml.
This helper is used automatically when loading a file via the internal file
factory.

### PSR-3

#### Role of PSR-3

The PHP Framework Interop Group ([PHP-FIG]) represents the majority of php
frameworks, in particular all main CMS.

[PSR-3] means that the message and its context may be separated in the logs, so
they can be translated and managed by any other compliant tools. This is useful
in particular when an external database is used to store logs.

The message uses placeholders that are not in C-style of the function `sprintf`
(`%s`, `%d`, etc.), but in moustache-style, identified with `{` and `}`, without
spaces.

The new and old format are automatically managed by the messenger and the
logger.

So, instead of logging like this:

```php
// Classic logging (not translatable).
$this->logger()->info(sprintf($message, ...$args));
$this->logger()->info(sprintf('The %s #%d has been updated.', 'item', 43));
// output: The item #43 has been updated.
```

A PSR-3 standard log is:

```php
// PSR-3 logging.
$this->logger()->info($message, $context);
$this->logger()->info(
    'The {resource} #{id} has been updated.', // @translate
    ['resource' => 'item', 'id' => 43]
);
// output: The item #43 has been updated.
```

If an Exception object is passed in the context data, it must be in the `exception`
key.

Because the logs are translatable at user level, with a message and context, the
message must not be translated when logging.

#### PSR-3 Message

If the message may be reused or for the messenger, the helper `PsrMessage()` can
be used, with all the values:

```php
// For logging, it is useless to use PsrMessage, since it is natively supported
// by the logging.
$message = new \Common\Stdlib\PsrMessage(
    'The {resource} #{id} has been updated by user #{userId}.', // @translate
    ['resource' => 'item', 'id' => 43, 'userId' => $user->id()]
);
$this->logger()->info($message->getMessage(), $message->getContext());
echo $message;
// Or with internal translator (laminas translator style).
echo $message->setTranslator($translator)->translate();
// With translator (Omeka core style, translator as first argument).
echo $message->translate($translator);
```

Since Omeka S v4.2, the core provides `\Omeka\Stdlib\PsrMessage`, which
implements `MessageInterface` and is natively recognized by the core translator
delegator. `Common\Stdlib\PsrMessage` extends it with additional features:

- TranslatorAwareInterface: set translator then use translate() without args.
- Variadic constructor: supports both PSR-3 array context and sprintf-style
  positional arguments for backward compatibility with \Omeka\Stdlib\Message.
- Polymorphic translate(): accepts TranslatorInterface as first arg (core) or
  translator interface aware signature (no args).

```php
// PSR-3 style (recommended):
$message = new \Common\Stdlib\PsrMessage('Hello {name}', ['name' => 'World']);

// Sprintf style (backward compatibility with \Omeka\Stdlib\Message):
$message = new \Common\Stdlib\PsrMessage('Hello %s', 'World');
```

For Omeka S < 4.2, a polyfill is provided in `data/compat/` that is loaded
automatically.

#### Translator

The translator to set in PsrMessage() is available through `$this->translator()`
in controller and view.

#### Compatibility

* Compatibility with core

`Common\Stdlib\PsrMessage` extends `\Omeka\Stdlib\PsrMessage`, so it implements
`MessageInterface` and is recognized natively by the core translator delegator,
the messenger, and the logger. No special handling is needed.

* Compatibility with messenger

The helper `messenger()` is compatible and can translate PSR-3 messages.

* Compatibility with the default stream logger

The PSR-3 messages are converted into simple messages for the default logger.
Other extra data are appended.

* Compatibility with core messages

The logger stores the core messages as it, without context, so they can be
displayed. They are not translatable if they use placeholders.

* Compatibility with thrown exceptions

An exception should not be translated early. Nevertheless, if you really need
it, you can use:

```php
# Where `$this->translator` is \Laminas\I18n\Translator\TranslatorInterface or
# MvcTranslator from services, either:
throw new \RuntimeException($this->translator->translate($message));
throw new \Exception($message->setTranslator($this->translator)->translate());
```

#### Plural

By construction, the plural is not managed: only one message is saved in the
log. So, if any, the plural message should be prepared before the logging.

### One-time tasks

Unlike old module Generic, there are two ways to get the one-time features
inside any module: the trait (recommended) or the abstract class (deprecated).

To use them, replace the following:

```php
namespace MyModule;

use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
}
```

with this class with the trait:

```php
namespace MyModule;

if (!trait_exists(\Common\TraitModule::class, false)) {
    require_once dirname(__DIR__) . '/Common/src/TraitModule.php';
}

use Common\TraitModule;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    use TraitModule;
}
```

The class AbstractModule is still provided, but deprecated. You may extend it:

```php
if (!class_exists(\Common\AbstractModule::class, false)) {
    require_once dirname(__DIR__) . '/Common/src/AbstractModule.php';
}

use Common\AbstractModule;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;
}
```

**Note**: When Common is installed via composer, classes and traits are
autoloaded via PSR-4 (`Common\TraitModule`, `Common\AbstractModule`, etc.). The
`require_once` with `class_exists()`/`trait_exists()` check ensures backward
compatibility with manual (zip) installations in the directory modules/ and is
needed during upgrades of module Common. This check will be useless for the next
major upgrade of Omeka.

**WARNING**: with an abstract class, `parent::method()` in the module calls the
method of the abstract class (`Common\AbstractModule`), but with a trait,
`parent::method()` is the method of `Omeka\AbstractModule` if it exists.
Furthermore, it is not possible to call a method of the trait that is overridden
by the class Module. This is why there are methods suffixed with "Auto" that can
be used in such a case.

### Installing resources

To install resources, the class `ManageModuleAndResources` can be used. It is
callable via the module `$this->getManageModuleAndResources()`. It contains
tools to manage and update vocabs, custom vocabs, and templates via files
located inside `data/`, that will be automatically imported.

```php
if (!class_exists(\Common\ManageModuleAndResources::class, false)) {
    require_once dirname(__DIR__) . '/Common/src/ManageModuleAndResources.php';
}
```


TODO
----

- [ ] Use key "psr_log" instead of "log" (see https://docs.laminas.dev/laminas-log/service-manager/#psrloggerabstractadapterfactory).
- [ ] Use materialized views for EasyMeta?


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

### Module

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


### Libraries

- jQuery-Autocomplete : [MIT]


Copyright
---------

* Copyright Daniel Berthereau, 2017-2026 (see [Daniel-KM] on GitLab)
* Copyright Tomas Kirda 2017 (library [jQuery-Autocomplete])


[Common module]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[Omeka S]: https://omeka.org/s
[GitLab]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[PSR-3]: http://www.php-fig.org/psr/psr-3
[PHP-FIG]: http://www.php-fig.org
[installing a module]: https://omeka.org/s/docs/user-manual/modules/
[PR #2412]: https://github.com/omeka/omeka-s/pull/2412
[Common.zip]: https://github.com/Daniel-KM/Omeka-S-module-Common/releases
[jSend]: https://github.com/omniti-labs/jsend
[Contact Us]: https://gitlab.com/Daniel-KM/Omeka-S-module-ContactUs
[Contribute]: https://gitlab.com/Daniel-KM/Omeka-S-module-Contribute
[Easy Admin]: https://gitlab.com/Daniel-KM/Omeka-S-module-EasyAdmin
[Selection]: https://gitlab.com/Daniel-KM/Omeka-S-module-Selection
[Two Factor Authentication]: https://gitlab.com/Daniel-KM/Omeka-S-module-TwoFactAuth
[jQuery-Autocomplete]: https://github.com/devbridge/jQuery-Autocomplete
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: http://opensource.org/licenses/MIT
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
