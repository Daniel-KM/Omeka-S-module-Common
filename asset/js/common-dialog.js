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
     * Post a form and managed it as jSend.
     */
    self.jSend = function (event) {
        event.preventDefault();
        const dialog = event.target.closest('dialog');
        const button = event.submitter;
        const form = button.closest('form');
        const url = form.action;
        // TODO Clean status for icon on submission.
        // const status = '';
        const formData = new FormData(form);
        // Include button name and value when exist (not included by default).
        if (button.name && button.value) {
            formData.append(button.name, button.value);
        }
        const formQuery = new URLSearchParams(formData).toString();
        self.spinnerEnable(button);
        fetch(url, {
            method: 'POST',
            body: formQuery,
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        })
        .then(response => response.json())
        .then(data => self.jSendSuccess(data))
        .catch(error => self.jSendFail(error))
        .finally(() => self.spinnerDisable(button));
    };

    /**
     * Manage ajax success via jSend.
     *
     * A success may be a fail if the endpoint returns a http code 2xx.
     */
    self.jSendSuccess = function(data) {
        if (!data.status || data.status !== 'success') {
            self.jSendFail(data);
            document.dispatchEvent(new CustomEvent('o:jsend-fail', { detail: data }));
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
