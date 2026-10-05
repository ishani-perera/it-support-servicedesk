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
    form.addEventListener('submit', () => {
        if (!form.checkValidity()) return;
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = button.dataset.loadingLabel || 'Sending…';
    });
});

document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => button.closest('[role="status"]')?.remove());
});
