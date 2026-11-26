<!-- Confirmation Modal Component: filled by confirmAction() in resources/js/toast.js -->
<div id="confirmationModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalMessage" aria-hidden="true">
    <div class="modal-box" data-tone="danger">
        <div class="modal-head">
            <div class="modal-icon" id="modalIcon" aria-hidden="true"></div>
            <div class="modal-copy">
                <h3 class="modal-title" id="modalTitle">Are you sure?</h3>
                <p class="modal-message" id="modalMessage">This action cannot be undone.</p>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" id="modalCancel">Cancel</button>
            <button type="button" class="btn-modal-confirm" id="modalConfirm">Confirm</button>
        </div>
    </div>
</div>

<style>
    /* One confirmation look for every workflow: icon + title + message, actions on a footer strip.
       Tones: danger (red), primary (green), warning (amber). Colours keep 4.5:1 text contrast. */
    #confirmationModal.modal-overlay {
        position: fixed; inset: 0; z-index: 1050; display: none; align-items: center; justify-content: center; padding: 16px;
        background: rgba(15, 23, 20, .45); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
        animation: confirmFade .18s ease;
    }
    #confirmationModal.modal-overlay.active { display: flex; }
    #confirmationModal .modal-box {
        width: 100%; max-width: 460px; max-height: calc(100dvh - 32px); overflow-y: auto; overflow-wrap: anywhere;
        background: #fff; border-radius: 16px; border: 1px solid #e3ebe6;
        box-shadow: 0 24px 48px -12px rgba(16, 42, 30, .28); animation: confirmRise .2s ease;
    }
    #confirmationModal .modal-head { display: grid; grid-template-columns: 48px minmax(0, 1fr); gap: 16px; align-items: start; padding: 24px 24px 20px; }
    #confirmationModal .modal-icon { display: grid; place-items: center; width: 48px; height: 48px; border-radius: 50%; background: #fdecec; color: #b91c1c; box-shadow: 0 0 0 6px #fff6f6; }
    #confirmationModal .modal-icon[hidden] { display: none; }
    #confirmationModal .modal-icon .compass-icon { width: 24px; height: 24px; }
    #confirmationModal .modal-title { margin: 2px 0 6px; font-size: 18px; line-height: 1.35; font-weight: 700; color: #163b2d; }
    #confirmationModal .modal-message { margin: 0; font-size: 14px; line-height: 1.6; color: #475569; }
    #confirmationModal .modal-actions { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 24px; background: #f8fbf9; border-top: 1px solid #e3ebe6; border-radius: 0 0 16px 16px; }
    #confirmationModal :is(.btn-modal-cancel, .btn-modal-confirm) {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-width: 104px; min-height: 42px; padding: 0 18px;
        border-radius: 10px; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; transition: background-color .15s ease, border-color .15s ease;
    }
    #confirmationModal .btn-modal-cancel { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
    #confirmationModal .btn-modal-cancel:hover { background: #f1f5f9; }
    #confirmationModal .btn-modal-confirm { border: 1px solid transparent; color: #fff; background: #b91c1c; }
    #confirmationModal .btn-modal-confirm:hover { background: #991b1b; }
    #confirmationModal :is(.btn-modal-cancel, .btn-modal-confirm):focus-visible { outline: 2px solid #31711d; outline-offset: 2px; }
    /* Tones */
    #confirmationModal [data-tone="primary"] .modal-icon { background: #ebf6e6; color: #31711d; box-shadow: 0 0 0 6px #f3fbf6; }
    #confirmationModal [data-tone="primary"] .btn-modal-confirm { background: #2b6619; }
    #confirmationModal [data-tone="primary"] .btn-modal-confirm:hover { background: #01562b; }
    #confirmationModal [data-tone="warning"] .modal-icon { background: #fdf3dc; color: #a15c00; box-shadow: 0 0 0 6px #fffaf0; }
    #confirmationModal [data-tone="warning"] .btn-modal-confirm { background: #b45309; }
    #confirmationModal [data-tone="warning"] .btn-modal-confirm:hover { background: #92400e; }
    @keyframes confirmFade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes confirmRise { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: none; } }
    @media (max-width: 480px) {
        /* resources/css/responsive.css pads every .modal-box with !important; this one keeps its edge-to-edge footer. */
        #confirmationModal .modal-box { width: 100% !important; padding: 0 !important; margin: 0 !important; }
        #confirmationModal .modal-head { grid-template-columns: 1fr; justify-items: center; text-align: center; padding: 22px 20px 18px; }
        #confirmationModal .modal-actions { flex-direction: column-reverse; padding: 14px 20px; }
        #confirmationModal :is(.btn-modal-cancel, .btn-modal-confirm) { width: 100%; }
    }
    @media (prefers-reduced-motion: reduce) { #confirmationModal, #confirmationModal .modal-box { animation: none; } }
</style>
