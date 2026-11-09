import './bootstrap';

document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    const toggle = target.closest('[data-sidebar-toggle]');
    if (toggle) {
        const sidebar = document.getElementById('platform-sidebar');
        const overlay = document.getElementById('platform-sidebar-overlay');
        const expanded = toggle.getAttribute('aria-expanded') === 'true';

        toggle.setAttribute('aria-expanded', String(!expanded));
        sidebar?.classList.toggle('is-open', !expanded);
        overlay?.classList.toggle('is-visible', !expanded);
        return;
    }

    if (target.closest('[data-sidebar-close]')) {
        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
            button.setAttribute('aria-expanded', 'false');
        });
        document.getElementById('platform-sidebar')?.classList.remove('is-open');
        document.getElementById('platform-sidebar-overlay')?.classList.remove('is-visible');
        return;
    }

    const dismiss = target.closest('[data-dismiss-alert]');
    if (dismiss) {
        dismiss.closest('.platform-alert')?.remove();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const message = form.dataset.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
