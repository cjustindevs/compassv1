<x-admin.layout
    title="Backup & Restore"
    page-title="Backup & Restore"
    page-subtitle="Protected snapshot management"
    active-nav="backup-restore"
    search-placeholder="Search sessions, helpers, resources, users..."
    :admin="$admin"
>
    <section
        class="backup-restore-page"
        data-backup-page
        data-backup-can-run="{{ $capabilities['create'] ? 'true' : 'false' }}"
        data-backup-can-restore="{{ $capabilities['restore'] ? 'true' : 'false' }}"
    >
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('admin.dashboard') }}">COMPASS</a>
            <span aria-hidden="true">/</span>
            <span>Admin</span>
            <span aria-hidden="true">/</span>
            <strong aria-current="page">Backup</strong>
        </nav>

        <header class="backup-page-heading">
            <div>
                <h1>Backup &amp; restore</h1>
                <p>Automated nightly snapshots with 30-day retention.</p>
            </div>

            <div class="backup-page-actions" aria-label="Backup and restore actions">
                <button class="admin-button admin-button-secondary" type="button" data-dialog-open="restore-picker-dialog">
                    <x-admin.icon name="upload" :size="18" /> Restore
                </button>
                <button class="admin-button admin-button-primary" type="button" data-dialog-open="run-backup-dialog">
                    <x-admin.icon name="database" :size="18" /> Run backup now
                </button>
            </div>
        </header>

        <section class="backup-card" aria-labelledby="recent-snapshots-title">
            <header class="backup-card-header">
                <h2 id="recent-snapshots-title">Recent snapshots</h2>
                @unless ($backendAvailable)
                    <span class="backup-backend-status">Backend not configured</span>
                @endunless
            </header>

            @if ($loadFailed)
                <div class="backup-state" role="alert">
                    <span><x-admin.icon name="alert-circle" :size="28" /></span>
                    <h3>Unable to load backup history</h3>
                    <p>Please try again.</p>
                    <a class="admin-button admin-button-secondary" href="{{ route('admin.backup-restore') }}">
                        <x-admin.icon name="undo" :size="17" /> Retry
                    </a>
                </div>
            @elseif ($snapshots->isEmpty())
                <div class="backup-state">
                    <span><x-admin.icon name="backup" :size="28" /></span>
                    <h3>No backups available</h3>
                    <p>Backup generation is not configured in this environment.</p>
                    <button class="admin-button admin-button-secondary" type="button" data-dialog-open="run-backup-dialog">
                        <x-admin.icon name="database" :size="17" /> Review backup setup
                    </button>
                </div>
            @else
                <div class="backup-snapshot-list">
                    @foreach ($snapshots as $snapshot)
                        <x-admin.backup-snapshot-row :snapshot="$snapshot" />
                    @endforeach
                </div>
            @endif
        </section>

        <div class="admin-toast" data-backup-toast role="status" aria-live="polite" hidden>
            <span class="admin-toast-icon"><x-admin.icon name="check-circle" :size="19" /></span>
            <span data-backup-toast-message></span>
        </div>

        <x-admin.dialog
            id="run-backup-dialog"
            title="Run backup now?"
            description="A new system snapshot will be created. This may temporarily increase storage and server usage."
            size="small"
        >
            <div class="admin-dialog-body">
                <div class="backup-operation-note {{ $capabilities['create'] ? '' : 'is-unavailable' }}">
                    <x-admin.icon :name="$capabilities['create'] ? 'check-circle' : 'alert-circle'" :size="20" />
                    <p>
                        @if ($capabilities['create'])
                            The configured backup provider will create a protected snapshot.
                        @else
                            Backup generation is not configured. No command or request will be executed.
                        @endif
                    </p>
                </div>
            </div>
            <footer class="admin-dialog-footer">
                <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
                <button class="admin-button admin-button-primary" type="button" data-confirm-run-backup @disabled(! $capabilities['create'])>
                    <x-admin.icon name="database" :size="17" /> Run backup
                </button>
            </footer>
        </x-admin.dialog>

        <x-admin.dialog
            id="restore-picker-dialog"
            title="Select backup to restore"
            description="Choose a completed snapshot before continuing to the restore warnings."
            size="small"
        >
            <div class="admin-dialog-body">
                @if ($snapshots->where('status', 'completed')->isEmpty())
                    <div class="backup-picker-empty">
                        <x-admin.icon name="backup" :size="25" />
                        <strong>No completed snapshots available</strong>
                        <p>A verified snapshot is required before a restore can be prepared.</p>
                    </div>
                @else
                    <fieldset class="backup-picker-list">
                        <legend class="sr-only">Completed backup snapshots</legend>
                        @foreach ($snapshots->where('status', 'completed') as $snapshot)
                            <label>
                                <input
                                    type="radio"
                                    name="restore_snapshot"
                                    value="{{ $snapshot['id'] }}"
                                    data-snapshot-summary="{{ $snapshot['summary'] }}"
                                >
                                <span>{{ $snapshot['summary'] }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                @endif
            </div>
            <footer class="admin-dialog-footer">
                <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
                <button class="admin-button admin-button-primary" type="button" data-continue-picked-restore disabled>Continue</button>
            </footer>
        </x-admin.dialog>

        <x-admin.dialog
            id="restore-warning-dialog"
            title="Restore backup?"
            description="Review the selected snapshot and operational impact."
            size="small"
        >
            <div class="admin-dialog-body backup-restore-warning">
                <div class="backup-selected-snapshot">
                    <span><x-admin.icon name="backup" :size="21" /></span>
                    <strong data-selected-backup-summary>No snapshot selected</strong>
                </div>
                <p>
                    Restoring this snapshot will replace the current system data with the selected backup.
                    This action may interrupt active users and should only be performed during an approved maintenance window.
                </p>
            </div>
            <footer class="admin-dialog-footer">
                <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
                <button class="admin-button admin-button-danger" type="button" data-continue-restore>Continue</button>
            </footer>
        </x-admin.dialog>

        <x-admin.dialog
            id="restore-confirm-dialog"
            title="Confirm system restore"
            description="Type RESTORE to continue."
            size="small"
        >
            <div class="admin-dialog-body">
                <div class="backup-selected-snapshot">
                    <span><x-admin.icon name="warning" :size="21" /></span>
                    <strong data-confirm-backup-summary>No snapshot selected</strong>
                </div>
                <label class="admin-field backup-confirm-field">
                    <span>Confirmation</span>
                    <input
                        type="text"
                        placeholder="RESTORE"
                        autocomplete="off"
                        spellcheck="false"
                        data-restore-confirmation-input
                    >
                    <small>Enter RESTORE exactly. Backend restoration must also be configured.</small>
                </label>
            </div>
            <footer class="admin-dialog-footer">
                <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
                <button class="admin-button admin-button-danger" type="button" data-confirm-system-restore disabled>
                    Restore system
                </button>
            </footer>
        </x-admin.dialog>
    </section>
</x-admin.layout>
