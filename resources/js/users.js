const userManagement = document.querySelector('[data-user-management]');

if (userManagement) {
    const createDialog = document.getElementById('create-user-dialog');
    const editDialog = document.getElementById('edit-user-dialog');
    const editForm = document.getElementById('edit-user-form');
    const updateUrlTemplate = editDialog?.dataset.updateUrlTemplate;

    userManagement.querySelector('[data-open-create-user]')?.addEventListener('click', () => {
        createDialog?.showModal();
    });

    userManagement.querySelectorAll('[data-open-edit-user]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!editDialog || !editForm) {
                return;
            }

            editForm.action = button.dataset.updateUrl;
            editForm.querySelector('[name="_user_id"]').value = button.dataset.userId;
            editForm.querySelector('[name="name"]').value = button.dataset.name;
            editForm.querySelector('[name="email"]').value = button.dataset.email;
            editForm.querySelector('[name="role"]').value = button.dataset.role;
            editForm.querySelector('[name="password"]').value = '';
            editDialog.showModal();
        });
    });

    if (editDialog?.dataset.openOnLoad === '' && editForm && updateUrlTemplate) {
        const userId = editDialog.dataset.oldUserId;
        const editButton = userManagement.querySelector(`[data-open-edit-user][data-user-id="${CSS.escape(userId)}"]`);

        if (editButton) {
            editForm.action = updateUrlTemplate.replace('__USER__', encodeURIComponent(userId));
            editDialog.showModal();
        }
    }

    if (createDialog?.dataset.openOnLoad === '') {
        createDialog.showModal();
    }

    userManagement.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('dialog')?.close();
        });
    });

    userManagement.querySelectorAll('[data-confirm-user-delete]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm('Hapus akun ini? Tindakan ini tidak dapat dibatalkan.')) {
                event.preventDefault();
            }
        });
    });
}
