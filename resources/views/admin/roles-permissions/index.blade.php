<x-admin.layout
    title="Roles & Permissions"
    page-title="Roles & Permissions"
    page-subtitle="Role-based access control matrix"
    active-nav="roles-permissions"
    search-placeholder="Search referrals, cases, users, reports..."
    :admin="$admin"
>
    <section
        class="roles-permissions-page"
        data-permissions-page
        data-current-role="{{ $currentRole }}"
        data-existing-roles='@json($existingRoles)'
    >
        <header class="roles-page-heading">
            <div>
                <h1>Roles &amp; Permissions</h1>
                <p>Configure role-based access across every module of the platform.</p>
            </div>

            <div class="roles-page-actions" aria-label="Permission matrix actions">
                <button class="admin-button admin-button-secondary" type="button" data-dialog-open="duplicate-role-dialog">
                    <x-admin.icon name="copy" :size="18" />
                    Duplicate role
                </button>
                <button class="admin-button admin-button-secondary" type="button" data-reset-permissions disabled>
                    <x-admin.icon name="undo" :size="18" />
                    Reset
                </button>
                <button class="admin-button admin-button-primary" type="button" data-save-permissions disabled>
                    <x-admin.icon name="save" :size="18" />
                    Save
                </button>
            </div>
        </header>

        <section class="permission-matrix-card" aria-labelledby="permission-matrix-title" data-permission-matrix>
            <header class="permission-matrix-context">
                <div>
                    <span>Editing role</span>
                    <strong id="permission-matrix-title">{{ $currentRole }}</strong>
                </div>
                <span class="permission-save-state" data-permission-save-state aria-live="polite">All changes saved</span>
            </header>

            @if (count($permissionMatrix) > 0)
                <div class="permission-table-shell">
                    <table class="permission-table">
                        <thead>
                            <tr>
                                <th scope="col">Category</th>
                                @foreach ($permissionActions as $action)
                                    <th scope="col">{{ $action }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissionMatrix as $category)
                                <tr>
                                    <th scope="row">
                                        <span class="permission-category">
                                            <span class="permission-category-icon" aria-hidden="true">
                                                <x-admin.icon :name="$category['icon']" :size="20" />
                                            </span>
                                            <span>{{ $category['name'] }}</span>
                                        </span>
                                    </th>
                                    @foreach ($permissionActions as $action)
                                        <td>
                                            <x-admin.permission-switch
                                                :category-id="$category['id']"
                                                :category-name="$category['name']"
                                                :action="$action"
                                                :checked="$category['permissions'][$action]"
                                            />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="permission-empty-state">
                    <span><x-admin.icon name="shield" :size="26" /></span>
                    <strong>No permission categories configured</strong>
                    <p>Permission modules will appear here once they are available.</p>
                </div>
            @endif
        </section>

        <div class="admin-toast" data-rbac-toast role="status" aria-live="polite" hidden>
            <span class="admin-toast-icon"><x-admin.icon name="check-circle" :size="19" /></span>
            <span data-rbac-toast-message></span>
        </div>

        <x-admin.dialog
            id="duplicate-role-dialog"
            title="Duplicate role"
            description="Create a local role draft using the current permission matrix as a template."
            size="small"
        >
            <form data-duplicate-role-form novalidate>
                <div class="admin-dialog-body">
                    <label class="admin-field">
                        <span>Role name</span>
                        <input
                            name="role_name"
                            type="text"
                            maxlength="80"
                            placeholder="Enter new role name"
                            autocomplete="off"
                            data-duplicate-role-name
                            required
                        >
                        <small class="field-validation" data-duplicate-role-error hidden></small>
                    </label>

                    <label class="admin-field">
                        <span>Copy permissions from</span>
                        <input type="text" value="{{ $currentRole }}" readonly aria-readonly="true">
                    </label>

                    <p class="field-note">The role will remain a local draft until a protected RBAC persistence workflow is available.</p>
                </div>
                <footer class="admin-dialog-footer">
                    <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
                    <button class="admin-button admin-button-primary" type="submit">
                        <x-admin.icon name="copy" :size="17" /> Duplicate role
                    </button>
                </footer>
            </form>
        </x-admin.dialog>

        <x-admin.dialog
            id="reset-permissions-dialog"
            title="Reset changes?"
            description="All unsaved permission changes will be discarded."
            size="small"
        >
            <div class="admin-dialog-body">
                <p class="confirmation-copy">The matrix will return to the last locally saved state for {{ $currentRole }}.</p>
            </div>
            <footer class="admin-dialog-footer">
                <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
                <button class="admin-button admin-button-danger" type="button" data-confirm-permission-reset>Reset changes</button>
            </footer>
        </x-admin.dialog>
    </section>
</x-admin.layout>
