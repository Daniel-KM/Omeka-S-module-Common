'use strict';

var CommonDialog = (function() {

    var self = {};

    /**
     * Display the dialog when clicking the button for it.
     */
    self.dialogOpen = function (eventOrElement) {
        const dialog = eventOrElement.target
            ? eventOrElement.target.closest('dialog')
            : eventOrElement.closest('dialog');
        dialog.showModal();
        dialog.dispatchEvent(new Event('o:dialog-opened'));
    };

    /**
     * Close the dialog and remove it if it is a dynamic one.
     */
    self.dialogClose = function (eventOrElement) {
        const dialog = eventOrElement.target
            ? eventOrElement.target.closest('dialog')
            : eventOrElement.closest('dialog');
        dialog.dispatchEvent(new Event('o:dialog-close'));
        dialog.close();
        if (dialog.hasAttribute('data-is-dynamic') && dialog.getAttribute('data-is-dynamic')) {
            dialog.remove();
        }
    };

    /**
     * Display a message as a dialog, so it can be used to replace an alert.
     *
     * Trigger o:dialog-opened.
     */
    self.dialogMessage = function (body, nl2br = false) {
        // Use a dialog to display a message, that should be escaped.
        let dialog = document.querySelector('dialog.dialog-message');
        if (!dialog) {
            dialog = document.createElement('dialog');
            dialog.className = 'dialog-common dialog-message';
            dialog.setAttribute('data-is-dynamic', '1');
            dialog.innerHTML = `
                <div class="dialog-background">
                    <div class="dialog-panel">
                        <div class="dialog-header">
                            <button type="button" class="dialog-header-close-button" title="${Omeka.jsTranslate('Close')}" autofocus="autofocus">
                                <span class="dialog-close">🗙</span>
                            </button>
                        </div>
                        <div class="dialog-contents">
                            <div class="dialog-heading"></div>
                            <div class="dialog-message"></div>
                            <div class="dialog-body">${nl2br ? body.replace(/(?:\r\n|\r|\n)/g, '<br/>') : body}</div>
                        </div>
                        <div class="dialog-footer"></div>
                    </div>
                </div>`;
            document.body.appendChild(dialog);
        }
        dialog.showModal();
        dialog.dispatchEvent(new Event('o:dialog-opened'));
    };

    /**
     * Send data via jSend from a form or a single button and display a message if any.
     *
     * The response may be a fail when http error codes are not used (not 2xx).
     * The dialog is displayed only if a message exists or in case of a failure.
     * A spinner is appended when the event target (form or button) has
     * attribute data-spinner true. It may be forced via button when the
     * attribute set on form is true or false.
     */
    self.jSend = function (event) {
        event.preventDefault();
        const target = event.target;
        const isForm = target.tagName === 'FORM';
        const isButton = target.tagName === 'BUTTON';

        let url, formData, formQuery, hasSpinner, spinnerTarget;
        // TODO Clean status for icon on submission.
        // const status = '';

        if (isForm) {
            const button = event.submitter;
            spinnerTarget = button;
            const hasSpinnerForm = [true, 1, '1', 'true'].includes(target.dataset.spinner);
            const hasNoSpinnerForm = [false, 0, '0', 'false'].includes(target.dataset.spinner);
            const hasSpinnerButton = [true, 1, '1', 'true'].includes(button.dataset.spinner);
            const hasNoSpinnerButton = [false, 0, '0', 'false'].includes(button.dataset.spinner);
            hasSpinner = (!hasSpinnerForm && !hasNoSpinnerForm && hasNoSpinnerButton)
                || (hasSpinnerForm && !hasNoSpinnerButton)
                || (hasNoSpinnerForm && hasSpinnerButton);
            url = target.action;
            formData = new FormData(target);
            // Include button name and value when exist (not included by default).
            if (button.name && button.value) {
                formData.append(button.name, button.value);
            }
            formQuery = new URLSearchParams(formData).toString();
        } else if (isButton) {
            spinnerTarget = target;
            hasSpinner = [true, 1, '1', 'true'].includes(spinnerTarget.dataset.spinner);
            url = target.dataset.action;
            const payload = target.dataset.payload ? JSON.parse(target.dataset.payload) : {};
            formQuery = new URLSearchParams(payload).toString();
        } else {
            console.error('Unsupported target for jSend:', target);
            return null;
        }

        spinnerTarget.disabled = true;
        if (hasSpinner) {
            self.spinnerEnable(spinnerTarget);
        }

        return fetch(url, {
            method: 'POST',
            body: formQuery,
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        })
        .then(response => response.json())
        .then(data => self.jSendResponse(data))
        .catch(error => self.jSendFail(error))
        .finally(() => {
            if (hasSpinner) {
                self.spinnerDisable(spinnerTarget);
            }
            spinnerTarget.disabled = false;
        });
    };

    /**
     * Manage ajax response via jSend.
     *
     * The response may be a fail when http error codes are not used (not 2xx).
     * The dialog is displayed only if a message exists or in case of a failure.
     */
    self.jSendResponse = function(data) {
        if (!data.status || data.status !== 'success') {
            self.jSendFail(data);
        } else {
            const dialog = document.querySelector('dialog.dialog-common');
            dialog.close();
            const msg = self.jSendMessage(data);
            if (msg) {
                self.dialogMessage(msg);
            }
            document.dispatchEvent(new CustomEvent('o:jsend-success', { detail: data }));
        }
    };

    /**
     * Manage ajax fail via jSend.
     */
    self.jSendFail = function (error) {
        const msg = self.jSendMessage(error) || Omeka.jsTranslate('An error occurred.');
        self.dialogMessage(msg, true);
        document.dispatchEvent(new CustomEvent('o:jsend-fail', { detail: error }));
    };

    /**
     * Get the main message of jSend output, in particular for status fail.
     */
    self.jSendMessage = function (data) {
        if (typeof data !== 'object') return null;
        if (data.message) return data.message.length ? data.message : null;
        if (!data.data) return null;
        if (data.data.message) return data.data.message.length ? data.data.message : null;
        for (let value of Object.values(data.data)) {
            if (typeof value === 'string' && value.length) return value;
        }
        return null;
    };

    /**
     * Display or append a spinner on an element.
     *
     * The element may be a button (semantically recommended), an input, or a
     * fake link (anchor a with href="#").
     *
     * The spinner is set after an input or inside a button or other element.
     */
    self.spinnerEnable = function (element) {
        let spinner = element.querySelector('span.spinner');
        if (!spinner) {
            spinner = document.createElement('span');
            spinner.className = 'spinner appended fas fa-sync';
            element.tagName === 'INPUT' ? element.insertAdjacentElement('afterend', spinner) : element.appendChild(spinner);
        }
        spinner.classList.add('fa-spin');
        element.disabled = true;
    };

    /**
     * Hide or remove a spinner on an element.
     */
    self.spinnerDisable = function (element) {
        const spinner = element.querySelector('span.spinner') || element.nextElementSibling;
        if (spinner) {
            spinner.classList.remove('fa-spin');
            if (spinner.classList.contains('appended')) {
                spinner.remove();
            }
        }
        element.disabled = false;
    };

    /**
     * Init events for common.
     */
    self.init = function () {
        document.addEventListener('click', function(event) {
            if (event.target.matches('.button-dialog-common')) {
                self.dialogOpen(event);
            } else if (event.target.matches('.dialog-header-close-button, .dialog-header-close-button span')) {
                self.dialogClose(event);
            }
        });

        document.addEventListener('submit', function(event) {
            if (event.target.matches('.form-jsend')) {
                self.jSend(event);
            }
        });

        return self;
    };

    return self;

})();

document.addEventListener('DOMContentLoaded', function() {
    if (typeof Omeka === 'undefined') {
        var Omeka = {};
    }
    if (!Omeka.jsTranslate) {
        Omeka.jsTranslate = (text) => text;
    }

    CommonDialog.init();
});
