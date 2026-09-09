<!-- Confirmation Modal Component -->
<div id="confirmationModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-box">
        <div class="text-center">
            <div class="modal-icon mb-4" id="modalIcon">
                <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl"></i>
            </div>
            <h3 class="modal-title text-xl font-semibold text-gray-900 mb-2" id="modalTitle">Are you sure?</h3>
            <p class="modal-message text-gray-600 mb-6" id="modalMessage">This action cannot be undone.</p>
            <div class="modal-actions flex justify-center gap-4">
                <button type="button" class="btn-modal-cancel px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2" id="modalCancel">
                    Cancel
                </button>
                <button type="button" class="btn-modal-confirm px-6 py-2.5 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2" id="modalConfirm">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #confirmationModal.modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 1050;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        animation: fadeIn 0.2s ease;
    }
    
    #confirmationModal.modal-overlay.active {
        display: flex;
    }
    
    #confirmationModal .modal-box {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        width: 100%;
        max-width: 440px;
        max-height: calc(100dvh - 32px);
        overflow-y: auto;
        overflow-wrap: anywhere;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        animation: slideUp 0.2s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    
    #confirmationModal .modal-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 1rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #FEF3C7;
    }
    
    #confirmationModal .modal-icon.success {
        background: #D1FAE5;
    }
    
    #confirmationModal .modal-icon.success i {
        color: #04A052;
    }
    
    #confirmationModal .modal-icon.error {
        background: #FEE2E2;
    }
    
    #confirmationModal .modal-icon.error i {
        color: #DC2626;
    }
    
    #confirmationModal .modal-icon.warning {
        background: #FEF3C7;
    }
    
    #confirmationModal .modal-icon.warning i {
        color: #F59E0B;
    }
    
    #confirmationModal .modal-icon.info {
        background: #DBEAFE;
    }
    
    #confirmationModal .modal-icon.info i {
        color: #3B82F6;
    }
    
    #confirmationModal .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #111827;
        margin-bottom: 0.5rem;
    }
    
    #confirmationModal .modal-message {
        font-size: 0.9375rem;
        color: #4B5563;
        line-height: 1.5;
    }
    
    #confirmationModal .modal-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
        margin-top: 1.5rem;
    }
    
    #confirmationModal .btn-modal-cancel,
    #confirmationModal .btn-modal-confirm {
        padding: 0.625rem 1.5rem;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
        min-width: 100px;
    }
    
    #confirmationModal .btn-modal-cancel {
        background: #F3F4F6;
        color: #374151;
        border: 1px solid #D1D5DB;
    }
    
    #confirmationModal .btn-modal-cancel:hover {
        background: #E5E7EB;
    }
    
    #confirmationModal .btn-modal-confirm {
        color: white;
        border: none;
    }
    
    #confirmationModal .btn-modal-confirm:hover {
        background: #B91C1C;
    }
    
    #confirmationModal .btn-modal-confirm.danger {
        background: #DC2626;
    }
    
    #confirmationModal .btn-modal-confirm.danger:hover {
        background: #B91C1C;
    }
    
    #confirmationModal .btn-modal-confirm.warning {
        background: #F59E0B;
    }
    
    #confirmationModal .btn-modal-confirm.warning:hover {
        background: #D97706;
    }
    
    #confirmationModal .btn-modal-confirm.primary {
        background: #04A052;
    }
    
    #confirmationModal .btn-modal-confirm.primary:hover {
        background: #038A45;
    }
    
    @media (max-width: 480px) {
        #confirmationModal .modal-box {
            margin: 1rem;
            padding: 1.25rem;
        }
        
        #confirmationModal .modal-actions {
            flex-direction: column-reverse;
        }
        
        #confirmationModal .btn-modal-cancel,
        #confirmationModal .btn-modal-confirm {
            width: 100%;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        #confirmationModal, #confirmationModal .modal-box { animation: none; }
    }
</style>