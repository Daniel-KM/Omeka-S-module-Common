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
        if (dialog) {
            dialog.showModal();
            dialog.dispatchEvent(new Event('o:dialog-opened'));
        }
    };

    /**
     * Close the dialog and remove it if it is a dynamic one.
     */
    self.dialogClose = function (eventOrElement) {
        const dialog = eventOrElement.target
            ? eventOrElement.target.closest('dialog')
            : eventOrElement.closest('dialog');
        if (dialog) {
            dialog.dispatchEvent(new Event('o:dialog-close'));
            dialog.close();
            if (dialog.hasAttribute('data-is-dynamic') && dialog.getAttribute('data-is-dynamic')) {
                dialog.remove();
            }
        }
    };

    /**
     * Display a message as a dialog, so it can be used to replace a js alert().
     *
     * @deprecated Use dialogAlert() instead.
     */
    self.dialogMessage = function (body, nl2br = false) {
        console.warn('CommonDialog.dialogMessage() is deprecated. Use CommonDialog.dialogAlert() instead.');
        return self.dialogAlert({
            message: body,
            nl2br: nl2br,
         });
    };

    /**
     * Display a message as a dialog, so it can be used to replace a js alert().
     *
     * Trigger o:dialog-opened.
     */
    self.dialogAlert = function(options = {}) {
        if (typeof options !== 'object') {
            options = typeof options === 'string'
                ? { message: options }
                : {};
        }
        options.message = options.message || '';
        options.nl2br = options.nl2br || false;
        options.textOk = options.textOk || Omeka.jsTranslate('OK');
        options.textCancel = null;
        options.input = false;
        return self.dialogGeneric(options);
    };

    /**
     * Display a message with a confirmation as a dialog, so it can be used to
     * replace a js confirm().
     *
     * Trigger o:dialog-opened.
     */
    self.dialogConfirm = function(options = {}) {
        if (typeof options !== 'object') {
            options = typeof options === 'string'
                ? { message: options }
                : {};
        }
        options.message = options.message || '';
        options.nl2br = options.nl2br || false;
        options.textOk = options.textOk || Omeka.jsTranslate('OK');
        options.textCancel = options.textCancel || Omeka.jsTranslate('Cancel');
        options.input = false;
        return self.dialogGeneric(options);
    };

    /**
     * Display a message with an input field as a dialog, so it can be used to
     * replace a js prompt().
     *
     * Trigger o:dialog-opened.
     */
    self.dialogPrompt = function(options = {}) {
        if (typeof options !== 'object') {
            options = typeof options === 'string'
                ? { message: options }
                : {};
        }
        options.message = options.message || '';
        options.nl2br = options.nl2br || false;
        options.textOk = options.textOk || Omeka.jsTranslate('OK');
        options.textCancel = options.textCancel || Omeka.jsTranslate('Cancel');
        options.input = true;
        options.defaultValue = options.defaultValue || '';
        return self.dialogGeneric(options);
    };

    /**
     * Display a message with an input or a confirmation as a dialog, so it can
     * be used to replace a js confirm() or prompt().
     *
     * @param object options Options:
     * - heading (string) Heading to display.
     * - message (string) Message to display.
     * - nl2br (bool) Convert new lines to <br/>.
     * - body (string) A body to display.
     * - input (bool) Display an input field instead of the body option.
     * - defaultValue (string) Default value of the input field.
    * - textOk (string) Text for the ok button.
    * - textCancel (string) If null, the cancel button is not displayed.
     *
     * Trigger o:dialog-opened.
     */
    self.dialogGeneric = function(options) {
        return new Promise(function(resolve) {
            let dialog = document.querySelector('dialog.dialog-generic');
            if (dialog) {
                dialog.remove();
            }
            dialog = document.createElement('dialog');
            dialog.className = 'dialog-common dialog-generic';
            dialog.setAttribute('data-is-dynamic', '1');

            // Build footer buttons according to options.
            let footerHtml = '';
            if (options.textCancel !== null) {
                footerHtml += `<button type="button" class="dialog-button dialog-cancel">${options.textCancel || Omeka.jsTranslate('Cancel')}</button>`;
            }
            if (options.textOk) {
                footerHtml += `<button type="submit" class="dialog-button dialog-ok">${options.textOk || Omeka.jsTranslate('OK')}</button>`;
            }

            // Build input field if needed.
            let body = options.body || '';
            if (options.input) {
                body = `<input type="text" class="dialog-input" value="${options.defaultValue || ''}" autofocus="autofocus" />`;
            }

            dialog.innerHTML = `
                <form method="dialog" class="dialog-background">
                    <div class="dialog-panel">
                        <div class="dialog-header">
                            <button type="button" class="dialog-header-close-button" title="${Omeka.jsTranslate('Close')}">
                                <span class="dialog-close">🗙</span>
                            </button>
                        </div>
                        <div class="dialog-contents">
                            <div class="dialog-heading"></div>
                            <div class="dialog-message"></div>
                            <div class="dialog-body">${body}</div>
                        </div>
                        <div class="dialog-footer">
                            ${footerHtml}
                        </div>
                    </div>
                </form>
                `;
            document.body.appendChild(dialog);

            // Set heading.
            const heading = options.heading || '';
            dialog.querySelector('.dialog-heading').textContent = heading;

            // Set message with optional nl2br.
            const msg = options.nl2br
                ? options.message.replace(/(?:\r\n|\r|\n)/g, '<br/>')
                : options.message;
            dialog.querySelector('.dialog-message').innerHTML = msg;

            // Button handlers.
            const okBtn = dialog.querySelector('.dialog-ok');
            const cancelBtn = dialog.querySelector('.dialog-cancel');
            const input = dialog.querySelector('.dialog-input');

            if (okBtn) {
                okBtn.onclick = function(e) {
                    e.preventDefault();
                    resolve(options.input ? input.value : true);
                    dialog.close();
                    dialog.remove();
                };
            }
            if (cancelBtn) {
                cancelBtn.onclick = function(e) {
                    e.preventDefault();
                    resolve(options.input ? null : false);
                    dialog.close();
                    dialog.remove();
                };
            }
            dialog.querySelector('.dialog-header-close-button').onclick = function(e) {
                e.preventDefault();
                resolve(options.input ? null : false);
                dialog.close();
                dialog.remove();
            };
            dialog.addEventListener('close', function() {
                if (dialog.parentNode) dialog.remove();
            }, { once: true });

            dialog.showModal();
            dialog.dispatchEvent(new Event('o:dialog-opened'));
            (input ? input : okBtn)?.focus();
        });
    };

    /**
     * Send data via jSend from a form or a single button and display a message if any.
     *
     * The response may be a fail when http error codes are not used (not 2xx).
     * The dialog is displayed only if a message exists or in case of a failure.
     * A spinner is appended when the event target (form or button) has
     * attribute data-spinner true. It may be forced via button when the
     * attribute set on form is true or false.
     *
     * @see https://github.com/omniti-labs/jsend
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
            url = target.dataset.action ? target.dataset.action : target.dataset.url;
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
            if (dialog) {
                dialog.close();
            }
            const msg = self.jSendMessage(data);
            if (msg) {
                self.dialogAlert(msg);
            }
            document.dispatchEvent(new CustomEvent('o:jsend-success', { detail: data }));
        }
    };

    /**
     * Manage ajax fail via jSend.
     */
    self.jSendFail = function (error) {
        error = error.responseJSON || error;
        const msg = self.jSendMessage(error) || Omeka.jsTranslate('An error occurred.');
        self.dialogAlert({ message: msg, nl2br: true });
        document.dispatchEvent(new CustomEvent('o:jsend-fail', { detail: error }));
    };

    /**
     * Get the main message of jSend output, in particular for status fail.
     *
     * For fail, when there is no message, return the first string value of data,
     * that should contain an error message.
     * @todo Return keys with all messages for fail? Adapt for a form?
     */
    self.jSendMessage = function (data) {
        if (typeof data !== 'object') return null;
        if (data.message) return data.message.length ? data.message : null;
        if (!data.data) return null;
        if (data.data.message) return data.data.message.length ? data.data.message : null;
        if (!data.status) return null;
        if (data.status === 'fail') {
            let result = '';
            for (let value of Object.values(data.data)) {
                if (typeof value === 'string' && value.length) {
                    result += "\n" + value;
                }
            }
            return result.length ? result : null;
        }
        return null;
    };

    /**
     * Display or append a spinner on an element.
     *
     * The element may be a button (semantically recommended), an input, or a
     * fake link (anchor a with href="#").
     * jQuery is not supported here, so use `element[0]` if needed.
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
