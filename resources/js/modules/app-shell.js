const closeShell = (shell) => {
    shell.classList.remove('is-sidebar-open');
};

const toggleShell = (shell) => {
    shell.classList.toggle('is-sidebar-open');
};

document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const shell = target.closest('[data-app-shell]');

    if (!shell) {
        return;
    }

    if (target.closest('[data-sidebar-toggle]')) {
        toggleShell(shell);

        return;
    }

    if (target.closest('[data-sidebar-close]')) {
        closeShell(shell);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    document.querySelectorAll('[data-app-shell].is-sidebar-open').forEach((shell) => {
        closeShell(shell);
    });
});
