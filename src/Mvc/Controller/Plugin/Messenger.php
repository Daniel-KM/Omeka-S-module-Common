<?php declare(strict_types=1);

namespace Common\Mvc\Controller\Plugin;

use Laminas\Form\Fieldset;

class Messenger extends \Omeka\Mvc\Controller\Plugin\Messenger
{
    /**
     * Fixed messenger for forms with one level of element Collection.
     *
     * @link https://github.com/omeka/omeka-s/pull/1626
     *
     * {@inheritDoc}
     * @see \Omeka\Mvc\Controller\Plugin\Messenger::addFormErrors()
     */
    public function addFormErrors(Fieldset $formOrFieldset): void
    {
        foreach ($formOrFieldset->getIterator() as $elementOrFieldset) {
            if ($elementOrFieldset instanceof Fieldset) {
                $this->addFormErrors($elementOrFieldset);
            } else {
                $label = $this->getController()->translate($elementOrFieldset->getLabel());
                foreach ($elementOrFieldset->getMessages() as $message) {
                    // Manage form with one level of Laminas Collection.
                    if (is_array($message)) {
                        foreach ($message as $msg) {
                            $this->addError(sprintf('%s: %s', $label, $msg));
                        }
                    } else {
                        $this->addError(sprintf('%s: %s', $label, $message));
                    }
                }
            }
        }
    }
}
