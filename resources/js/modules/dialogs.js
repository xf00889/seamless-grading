const dialogSelector = 'dialog[data-modal]';

function findDialog(name) {
    if (!name) {
        return null;
    }

    return document.querySelector(`${dialogSelector}[data-modal="${name}"]`);
}

function syncBodyState() {
    document.body.classList.toggle(
        'has-open-dialog',
        document.querySelector(`${dialogSelector}[open]`) !== null,
    );
}

function focusInitialElement(dialog) {
    const focusTarget = dialog.querySelector(
        '[data-modal-initial-focus], textarea, input:not([type="hidden"]), select, button, a[href]',
    );

    if (focusTarget instanceof HTMLElement) {
        requestAnimationFrame(() => {
            focusTarget.focus();
        });
    }
}

function closeDialog(dialog) {
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    if (typeof dialog.close === 'function' && dialog.open) {
        dialog.close();
    } else {
        dialog.removeAttribute('open');
        syncBodyState();
    }
}

function openDialog(dialog) {
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    document.querySelectorAll(`${dialogSelector}[open]`).forEach((openInstance) => {
        if (openInstance instanceof HTMLDialogElement && openInstance !== dialog) {
            closeDialog(openInstance);
        }
    });

    if (typeof dialog.showModal === 'function') {
        if (!dialog.open) {
            dialog.showModal();
        }
    } else {
        dialog.setAttribute('open', 'open');
        syncBodyState();
    }

    focusInitialElement(dialog);
}

document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const openTrigger = target.closest('[data-modal-open]');

    if (openTrigger instanceof HTMLElement) {
        event.preventDefault();
        openDialog(findDialog(openTrigger.dataset.modalOpen));
        return;
    }

    const closeTrigger = target.closest('[data-modal-close]');

    if (closeTrigger instanceof HTMLElement) {
        event.preventDefault();
        closeDialog(closeTrigger.closest(dialogSelector));
        return;
    }

    if (target instanceof HTMLDialogElement && target.matches(dialogSelector)) {
        closeDialog(target);
    }
});

document.querySelectorAll(dialogSelector).forEach((dialog) => {
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    dialog.addEventListener('close', syncBodyState);
    dialog.addEventListener('cancel', syncBodyState);

    if (dialog.dataset.modalAutoOpen === 'true') {
        openDialog(dialog);
    }
});
