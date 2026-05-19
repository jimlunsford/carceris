(function () {
    function initTextareaShortcuts() {
        const textareas = document.querySelectorAll('textarea');

        textareas.forEach(function (textarea) {
            textarea.addEventListener('keydown', function (event) {
                if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                    const form = textarea.closest('form');

                    if (form) {
                        form.requestSubmit();
                    }
                }
            });
        });
    }

    function initLateEntryToggle() {
        const checkbox = document.getElementById('is_late_entry');
        const fields = document.getElementById('late-entry-fields');
        const eventTime = document.getElementById('event_time');
        const reason = document.getElementById('late_entry_reason');

        if (!checkbox || !fields || !eventTime || !reason) {
            return;
        }

        function updateLateFields() {
            const enabled = checkbox.checked;

            fields.hidden = !enabled;
            fields.setAttribute('aria-hidden', enabled ? 'false' : 'true');
            eventTime.required = enabled;
            reason.required = enabled;
            eventTime.disabled = !enabled;
            reason.disabled = !enabled;

            if (!enabled) {
                eventTime.value = '';
                reason.value = '';
            }
        }

        checkbox.addEventListener('change', updateLateFields);
        checkbox.addEventListener('click', updateLateFields);
        updateLateFields();
    }

    function initCarcerisApp() {
        initTextareaShortcuts();
        initLateEntryToggle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCarcerisApp);
    } else {
        initCarcerisApp();
    }
})();
