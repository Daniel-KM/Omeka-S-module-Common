'use strict';

/**
 * Add an "Apply" button to module config forms: save the settings and stay on
 * the same form (active tab included) instead of returning to the module list.
 * The active section is restored via the url fragment.
 */
(function () {
    var init = function () {
        // The asset may be loaded twice (per-module opt-in + EasyAdmin global
        // option): keep the init idempotent.
        if (window.commonConfigApplyDone) {
            return;
        }
        window.commonConfigApplyDone = true;

        var form = document.querySelector('#content form');
        var pageActions = document.getElementById('page-actions');
        if (!form || !pageActions) {
            return;
        }

        var setHidden = function (name, value) {
            var input = form.querySelector('input[type="hidden"][name="' + name + '"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                form.appendChild(input);
            }
            input.value = value;
        };

        var label = (typeof CommonConfigApply === 'object' && CommonConfigApply.label)
            ? CommonConfigApply.label
            : 'Apply';

        var apply = document.createElement('button');
        apply.type = 'submit';
        apply.className = 'apply button';
        apply.textContent = label;
        pageActions.insertBefore(apply, pageActions.firstChild);

        apply.addEventListener('click', function () {
            setHidden('apply', '1');
            var active = document.querySelector('.section.active[id]');
            setHidden('apply_fragment', active ? active.id : '');
        });
    };

    // The asset is loaded in <head>, so wait for the body (#page-actions).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
