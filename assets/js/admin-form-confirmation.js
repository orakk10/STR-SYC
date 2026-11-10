const formSnapshots = new WeakMap();
const approvedSubmissions = new WeakSet();
const deferredFormSnapshots = new WeakSet();

function getFormValues(form) {
    return Array.from(form.elements)
        .filter((element) => element instanceof HTMLInputElement
            || element instanceof HTMLSelectElement
            || element instanceof HTMLTextAreaElement)
        .map((element) => {
            if (element instanceof HTMLInputElement && ['checkbox', 'radio'].includes(element.type)) {
                return [element.checked, element.value];
            }

            if (element instanceof HTMLSelectElement && element.multiple) {
                return Array.from(element.selectedOptions, (option) => option.value);
            }

            return element.value;
        });
}

function snapshotForm(form) {
    formSnapshots.set(form, JSON.stringify(getFormValues(form)));
}

function getConfirmationDialog() {
    let dialog = document.getElementById('adminFormConfirmation');
    if (dialog instanceof HTMLDialogElement) {
        return dialog;
    }

    const style = document.createElement('style');
    style.textContent = `
        #adminFormConfirmation {
            position: fixed;
            inset: auto;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
            width: min(420px, calc(100% - 32px));
            max-height: calc(100% - 32px);
            overflow-y: auto;
            padding: 0;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            color: #1e293b;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
            font-family: Inter, system-ui, sans-serif;
        }
        #adminFormConfirmation::backdrop { background: rgba(15, 23, 42, 0.55); }
        #adminFormConfirmation .admin-confirmation-content { padding: 22px; }
        #adminFormConfirmation h2 { margin: 0; font-size: 1.1rem; }
        #adminFormConfirmation p { margin: 10px 0 22px; color: #475569; line-height: 1.5; }
        #adminFormConfirmation .admin-confirmation-actions {
            display: flex; justify-content: flex-end; gap: 8px;
        }
        #adminFormConfirmation button {
            min-height: 36px; padding: 7px 14px; border: 1px solid #cbd5e1;
            border-radius: 6px; background: #f1f5f9; color: #475569;
            font: inherit; font-size: 0.85rem; font-weight: 600; cursor: pointer;
        }
        #adminFormConfirmation button:hover { background: #e2e8f0; }
        #adminFormConfirmation button[data-confirm-action="confirm"] {
            border-color: #1d4ed8; background: #2563eb; color: #fff;
        }
        #adminFormConfirmation button[data-confirm-action="confirm"]:hover { background: #1d4ed8; }
        #adminFormConfirmation button[data-confirm-variant="danger"] {
            border-color: #b91c1c; background: #dc2626; color: #fff;
        }
        #adminFormConfirmation button[data-confirm-variant="danger"]:hover { background: #b91c1c; }
    `;
    document.head.appendChild(style);

    dialog = document.createElement('dialog');
    dialog.id = 'adminFormConfirmation';
    dialog.setAttribute('aria-labelledby', 'adminFormConfirmationTitle');
    dialog.innerHTML = `
        <div class="admin-confirmation-content">
            <h2 id="adminFormConfirmationTitle"></h2>
            <p id="adminFormConfirmationMessage"></p>
            <div class="admin-confirmation-actions"></div>
        </div>
    `;
    document.body.appendChild(dialog);
    return dialog;
}

function showConfirmation({ title, message, confirmLabel, cancelLabel, confirmVariant }) {
    const dialog = getConfirmationDialog();
    const actions = dialog.querySelector('.admin-confirmation-actions');
    dialog.querySelector('#adminFormConfirmationTitle').textContent = title;
    dialog.querySelector('#adminFormConfirmationMessage').textContent = message;
    actions.replaceChildren();

    return new Promise((resolve) => {
        let cancelHandler;
        const finish = (accepted) => {
            dialog.removeEventListener('cancel', cancelHandler);
            if (dialog.open) {
                dialog.close();
            }
            resolve(accepted);
        };
        if (confirmLabel) {
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.textContent = cancelLabel;
            cancel.addEventListener('click', () => finish(false), { once: true });
            actions.appendChild(cancel);

            const confirm = document.createElement('button');
            confirm.type = 'button';
            confirm.dataset.confirmAction = 'confirm';
            if (confirmVariant) {
                confirm.dataset.confirmVariant = confirmVariant;
            }
            confirm.textContent = confirmLabel;
            confirm.addEventListener('click', () => finish(true), { once: true });
            actions.appendChild(confirm);
        } else {
            const acknowledge = document.createElement('button');
            acknowledge.type = 'button';
            acknowledge.dataset.confirmAction = 'confirm';
            acknowledge.textContent = cancelLabel;
            acknowledge.addEventListener('click', () => finish(true), { once: true });
            actions.appendChild(acknowledge);
        }

        cancelHandler = (event) => {
            event.preventDefault();
            finish(false);
        };
        dialog.addEventListener('cancel', cancelHandler);
        dialog.showModal();
    });
}

document.querySelectorAll('form').forEach((form) => {
    if (form.getClientRects().length === 0) {
        deferredFormSnapshots.add(form);
    } else {
        snapshotForm(form);
    }
});

document.addEventListener('focusin', (event) => {
    const form = event.target instanceof HTMLElement ? event.target.closest('form') : null;
    if (form instanceof HTMLFormElement && deferredFormSnapshots.has(form)) {
        snapshotForm(form);
        deferredFormSnapshots.delete(form);
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post'
        || (form.hasAttribute('onsubmit') && !form.dataset.confirmMessage)) {
        return;
    }

    if (approvedSubmissions.has(form)) {
        approvedSubmissions.delete(form);
        return;
    }

    const currentSnapshot = JSON.stringify(getFormValues(form));
    const confirmationMessage = form.dataset.confirmMessage;
    const confirmationTitle = form.dataset.confirmTitle;
    const confirmationLabel = form.dataset.confirmLabel;
    const confirmationVariant = form.dataset.confirmVariant;
    let initialSnapshot = formSnapshots.get(form);
    if (initialSnapshot === undefined) {
        snapshotForm(form);
        initialSnapshot = formSnapshots.get(form);
    }

    if (!confirmationMessage && initialSnapshot === currentSnapshot) {
        event.preventDefault();
        await showConfirmation({
            title: 'No Changes',
            message: 'No changes were made to this form.',
            confirmLabel: '',
            cancelLabel: 'OK',
        });
        return;
    }

    event.preventDefault();
    const submitter = event.submitter;
    const isCustomConfirmation = Boolean(confirmationMessage);
    const approved = await showConfirmation({
        title: confirmationTitle || (isCustomConfirmation ? 'Confirm Action' : 'Confirm Changes'),
        message: confirmationMessage || 'Changes detected. Are you sure you want to save them?',
        confirmLabel: confirmationLabel || (isCustomConfirmation ? 'Confirm' : 'Save Changes'),
        cancelLabel: 'Cancel',
        confirmVariant: confirmationVariant,
    });

    if (approved && form.isConnected) {
        approvedSubmissions.add(form);
        if (submitter instanceof HTMLElement && submitter.form === form) {
            form.requestSubmit(submitter);
        } else {
            form.requestSubmit();
        }
    }
}, true);
