// Application JavaScript entry point (vanilla JS only — no frameworks).

const sidebar = document.querySelector('#app-sidebar');
const sidebarToggle = document.querySelector('#sidebar-toggle');
const sidebarBackdrop = document.querySelector('#sidebar-backdrop');

if (sidebar && sidebarToggle && sidebarBackdrop) {
    const desktop = window.matchMedia('(min-width: 1024px)');
    let previouslyFocused = null;

    const closeSidebar = (restoreFocus = false) => {
        if (desktop.matches) return;
        sidebar.classList.add('-translate-x-full');
        sidebar.inert = true;
        sidebarBackdrop.classList.add('hidden');
        sidebarToggle.setAttribute('aria-expanded', 'false');
        sidebarToggle.setAttribute('aria-label', 'Open navigation');
        if (restoreFocus) (previouslyFocused || sidebarToggle).focus();
    };

    const openSidebar = () => {
        previouslyFocused = document.activeElement;
        sidebar.inert = false;
        sidebar.classList.remove('-translate-x-full');
        sidebarBackdrop.classList.remove('hidden');
        sidebarToggle.setAttribute('aria-expanded', 'true');
        sidebarToggle.setAttribute('aria-label', 'Close navigation');
        sidebar.querySelector('a')?.focus();
    };

    const syncViewport = () => {
        if (desktop.matches) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.inert = false;
            sidebarBackdrop.classList.add('hidden');
            sidebarToggle.setAttribute('aria-expanded', 'false');
            sidebarToggle.setAttribute('aria-label', 'Open navigation');
        } else {
            closeSidebar();
        }
    };

    sidebarToggle.addEventListener('click', () => {
        sidebarToggle.getAttribute('aria-expanded') === 'true' ? closeSidebar(true) : openSidebar();
    });
    sidebarBackdrop.addEventListener('click', () => closeSidebar(true));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebarToggle.getAttribute('aria-expanded') === 'true') closeSidebar(true);
    });
    desktop.addEventListener('change', syncViewport);
    syncViewport();
}

document.querySelectorAll('[data-file-picker]').forEach((picker) => {
    const input = picker.querySelector('input[type="file"]');
    const details = picker.querySelector('[data-file-details]');
    const name = picker.querySelector('[data-file-name]');
    const size = picker.querySelector('[data-file-size]');
    const remove = picker.querySelector('[data-file-remove]');
    if (!input || !details || !name || !size || !remove) return;

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
            details.classList.add('hidden');
            details.classList.remove('flex');
            input.setCustomValidity('');
            return;
        }

        name.textContent = file.name;
        size.textContent = file.size >= 1024 * 1024
            ? `${(file.size / (1024 * 1024)).toFixed(1)} MB`
            : `${Math.max(1, Math.round(file.size / 1024))} KB`;
        details.classList.remove('hidden');
        details.classList.add('flex');
        input.setCustomValidity(file.size > 10 * 1024 * 1024 ? 'Choose a file smaller than 10 MB.' : '');
    });

    const dropZone = picker.querySelector('[data-file-dropzone]');
    if (dropZone) {
        const clearDropState = () => dropZone.classList.remove('border-indigo-500', 'bg-indigo-50', 'ring-2', 'ring-indigo-500/20');

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropZone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropZone.classList.add('border-indigo-500', 'bg-indigo-50', 'ring-2', 'ring-indigo-500/20');
            });
        });

        ['dragleave', 'dragend'].forEach((eventName) => {
            dropZone.addEventListener(eventName, (event) => {
                if (eventName === 'dragend' || !dropZone.contains(event.relatedTarget)) clearDropState();
            });
        });

        dropZone.addEventListener('drop', (event) => {
            event.preventDefault();
            clearDropState();
            if (!event.dataTransfer?.files?.length) return;

            input.files = event.dataTransfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    remove.addEventListener('click', () => {
        input.value = '';
        input.setCustomValidity('');
        details.classList.add('hidden');
        details.classList.remove('flex');
        input.focus();
    });
});

document.querySelectorAll('[data-ticket-form]').forEach((form) => {
    const title = form.querySelector('[name="title"]');
    const description = form.querySelector('[name="description"]');
    const validateText = (field, message) => {
        if (!field) return;
        field.setCustomValidity(field.value.trim() ? '' : message);
        field.addEventListener('input', () => field.setCustomValidity(field.value.trim() ? '' : message));
    };

    validateText(title, 'Enter a short title for your request.');
    validateText(description, 'Describe the issue so the IT team can help.');
});

document.querySelectorAll('[data-resolution-form]').forEach((form) => {
    const status = form.querySelector('[data-resolution-status]');
    const field = form.querySelector('[data-resolution-field]');
    const input = form.querySelector('[data-resolution-input]');
    if (!status || !field || !input) return;

    const syncResolution = () => {
        const required = status.value === 'resolved';
        field.classList.toggle('hidden', !required);
        input.required = required;
    };

    status.addEventListener('change', syncResolution);
    syncResolution();
});

document.querySelectorAll('[data-loading-form]').forEach((form) => {
    if (form.hasAttribute('data-async-workflow-form')) return;

    form.addEventListener('submit', () => {
        if (!form.checkValidity()) return;
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = button.dataset.loadingLabel || 'Sending…';
    });
});

document.querySelectorAll('[data-async-workflow-form]').forEach((form) => {
    const feedback = form.querySelector('[data-workflow-feedback]');
    const submit = form.querySelector('button[type="submit"]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    const clearErrors = () => {
        form.querySelectorAll('[data-field-error]').forEach((node) => {
            node.textContent = '';
            node.classList.add('hidden');
        });
        form.querySelectorAll('[aria-invalid="true"]').forEach((field) => field.removeAttribute('aria-invalid'));
    };

    const showFeedback = (message, isError = false) => {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.className = `rounded-xl px-3 py-2 text-sm ${isError ? 'border border-rose-200 bg-rose-50 text-rose-900' : 'border border-emerald-200 bg-emerald-50 text-emerald-900'}`;
        feedback.classList.remove('hidden');
        feedback.setAttribute('role', isError ? 'alert' : 'status');
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.dataset.submitting === 'true') return;
        clearErrors();

        const trimRequired = form.querySelector('[data-trim-required]');
        if (trimRequired && trimRequired.value.trim() === '') {
            const error = form.querySelector(`[data-field-error="${trimRequired.name}"]`);
            if (error) {
                error.textContent = 'Please provide a reason.';
                error.classList.remove('hidden');
            }
            trimRequired.setAttribute('aria-invalid', 'true');
            trimRequired.focus();
            return;
        }

        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;

        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        if (submit) {
            submit.disabled = true;
            submit.textContent = submit.dataset.loadingLabel || 'Saving…';
        }

        try {
            const response = await fetch(form.action, {
                method: form.method.toUpperCase(),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: new FormData(form),
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const errors = payload.errors || payload.error?.details || {};
                let firstInvalid = null;
                Object.entries(errors).forEach(([field, messages]) => {
                    const target = Array.from(form.querySelectorAll('[data-field-error]'))
                        .find((node) => node.dataset.fieldError === field);
                    const input = Array.from(form.elements).find((element) => element.name === field);
                    const message = Array.isArray(messages) ? messages[0] : messages;
                    if (target && typeof message === 'string') {
                        target.textContent = message;
                        target.classList.remove('hidden');
                        input?.setAttribute('aria-invalid', 'true');
                        firstInvalid ||= input;
                    }
                });
                showFeedback(payload.message || payload.error?.message || 'The request could not be completed. Check the fields and try again.', true);
                firstInvalid?.focus();
                return;
            }

            showFeedback(form.dataset.successMessage || 'Saved successfully.');
            const target = new URL(window.location.href);
            if (form.dataset.successKey) target.searchParams.set('workflow_notice', form.dataset.successKey);
            window.setTimeout(() => window.location.assign(target.toString()), 500);
        } catch (error) {
            showFeedback('A network error interrupted the request. Please try again.', true);
        } finally {
            if (form.dataset.submitting === 'true' && feedback?.classList.contains('border-rose-200')) {
                form.dataset.submitting = 'false';
                form.setAttribute('aria-busy', 'false');
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = submit.dataset.originalLabel || submit.textContent;
                }
            }
        }
    });

    if (submit) submit.dataset.originalLabel = submit.textContent.trim();
});

document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => button.closest('[role="status"]')?.remove());
});
