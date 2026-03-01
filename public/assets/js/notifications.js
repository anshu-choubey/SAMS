/**
 * SAMS Notification System
 * Provides unified dialog and toast notifications for CRUD operations
 */

// Show success dialog
function showSuccessDialog(title, message, callback = null) {
    showDialog('success', title, message, callback);
}

// Show error dialog
function showErrorDialog(title, message, callback = null) {
    showDialog('danger', title, message, callback);
}

// Show warning dialog
function showWarningDialog(title, message, callback = null) {
    showDialog('warning', title, message, callback);
}

// Show info dialog
function showInfoDialog(title, message, callback = null) {
    showDialog('info', title, message, callback);
}

// Generic dialog function
function showDialog(type, title, message, callback = null) {
    const iconMap = {
        'success': '<i class="bi bi-check-circle text-success" style="font-size: 2.5rem;"></i>',
        'danger': '<i class="bi bi-x-circle text-danger" style="font-size: 2.5rem;"></i>',
        'warning': '<i class="bi bi-exclamation-circle text-warning" style="font-size: 2.5rem;"></i>',
        'info': '<i class="bi bi-info-circle text-info" style="font-size: 2.5rem;"></i>'
    };

    const dialogId = 'notification-dialog-' + Date.now();
    const dialogHTML = `
        <div class="modal fade" id="${dialogId}" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-body text-center py-5">
                        <div class="mb-3">
                            ${iconMap[type] || iconMap['info']}
                        </div>
                        <h5 class="modal-title mb-2">${title}</h5>
                        <p class="text-muted mb-0">${message}</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center pb-3">
                        <button type="button" class="btn btn-${type === 'success' ? 'success' : type === 'danger' ? 'danger' : type === 'warning' ? 'warning' : 'info'}" data-bs-dismiss="modal">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add dialog to body
    const dialogContainer = document.createElement('div');
    dialogContainer.innerHTML = dialogHTML;
    document.body.appendChild(dialogContainer);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById(dialogId));
    
    // Handle callback and cleanup
    document.getElementById(dialogId).addEventListener('hidden.bs.modal', function() {
        dialogContainer.remove();
        if (callback && typeof callback === 'function') {
            callback();
        }
    });

    modal.show();
}

// Show toast notification
function showToast(type, title, message) {
    const toastId = 'toast-' + Date.now();
    const config = {
        'success': { bgClass: 'bg-success', icon: 'bi-check-circle' },
        'danger': { bgClass: 'bg-danger', icon: 'bi-x-circle' },
        'warning': { bgClass: 'bg-warning', icon: 'bi-exclamation-circle' },
        'info': { bgClass: 'bg-info', icon: 'bi-info-circle' }
    }[type] || { bgClass: 'bg-info', icon: 'bi-info-circle' };

    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white ${config.bgClass} border-0 shadow-lg" role="alert" style="min-width: 350px;">
            <div class="d-flex p-3">
                <i class="bi ${config.icon} me-3" style="font-size: 1.5rem; flex-shrink: 0;"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold">${title}</div>
                    <div style="font-size: 0.9rem; opacity: 0.95;">${message}</div>
                </div>
                <button type="button" class="btn-close btn-close-white flex-shrink-0 ms-2" data-bs-dismiss="toast" style="margin: 0;"></button>
            </div>
        </div>
    `;

    // Get or create toast container
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;';
        document.body.appendChild(toastContainer);
    }

    // Add toast
    const toastElement = document.createElement('div');
    toastElement.innerHTML = toastHTML;
    toastContainer.appendChild(toastElement);

    // Show toast
    const toast = new bootstrap.Toast(document.getElementById(toastId), {
        autohide: true,
        delay: 4000
    });
    toast.show();

    // Remove after hidden
    document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}

// Confirm dialog
function showConfirmDialog(title, message, onConfirm, onCancel = null) {
    const dialogId = 'confirm-dialog-' + Date.now();
    const dialogHTML = `
        <div class="modal fade" id="${dialogId}" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-body text-center py-4">
                        <div class="mb-3">
                            <i class="bi bi-question-circle text-warning" style="font-size: 2.5rem;"></i>
                        </div>
                        <h5 class="modal-title mb-2">${title}</h5>
                        <p class="text-muted mb-0">${message}</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center pb-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmBtn">OK, Proceed</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const dialogContainer = document.createElement('div');
    dialogContainer.innerHTML = dialogHTML;
    document.body.appendChild(dialogContainer);

    const modal = new bootstrap.Modal(document.getElementById(dialogId));
    
    document.getElementById('confirmBtn').addEventListener('click', function() {
        modal.hide();
        if (onConfirm && typeof onConfirm === 'function') {
            onConfirm();
        }
    });

    document.getElementById(dialogId).addEventListener('hidden.bs.modal', function() {
        if (!document.getElementById(dialogId).dataset.confirmed) {
            if (onCancel && typeof onCancel === 'function') {
                onCancel();
            }
        }
        dialogContainer.remove();
    });

    modal.show();
}

// Loading dialog
function showLoadingDialog(message = 'Loading...') {
    const dialogId = 'loading-dialog-' + Date.now();
    const dialogHTML = `
        <div class="modal fade" id="${dialogId}" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted">${message}</p>
                    </div>
                </div>
            </div>
        </div>
    `;

    const dialogContainer = document.createElement('div');
    dialogContainer.innerHTML = dialogHTML;
    document.body.appendChild(dialogContainer);

    const modal = new bootstrap.Modal(document.getElementById(dialogId), {
        backdrop: 'static',
        keyboard: false
    });
    
    modal.show();

    return {
        hide: function() {
            modal.hide();
            setTimeout(() => dialogContainer.remove(), 500);
        }
    };
}
