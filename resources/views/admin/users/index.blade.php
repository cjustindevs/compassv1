<x-admin.layout
    title="User Management"
    page-title="Users"
    page-subtitle="Account directory and access"
    active-nav="users"
    search-placeholder="Search sessions, helpers, resources, users..."
    :admin="$admin"
>
    <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}">COMPASS</a>
        <span aria-hidden="true">/</span>
        <span>Admin</span>
        <span aria-hidden="true">/</span>
        <strong aria-current="page">Users</strong>
    </nav>

    <header class="users-page-heading">
        <div>
            <h1>User management</h1>
            <p>
                {{ number_format($userSummary['registered']) }} registered {{ Str::plural('user', $userSummary['registered']) }}
                <span aria-hidden="true">·</span>
                {{ number_format($userSummary['pendingInvitations']) }} pending {{ Str::plural('invitation', $userSummary['pendingInvitations']) }}
            </p>
        </div>

        <div class="users-page-actions">
            <button class="admin-button admin-button-secondary" type="button" data-dialog-open="import-users-dialog">
                <x-admin.icon name="upload" :size="18" />
                Import CSV
            </button>
            <button class="admin-button admin-button-primary" type="button" data-dialog-open="create-user-dialog">
                <x-admin.icon name="user-plus" :size="18" />
                Create user
            </button>
        </div>
    </header>

    @if (session('success'))
        <div class="admin-flash admin-flash-success" role="status">
            <x-admin.icon name="check-circle" :size="18" />
            {{ session('success') }}
        </div>
    @endif

    <div class="admin-flash admin-flash-info" data-users-feedback role="status" hidden></div>

    <section class="directory-card" aria-labelledby="directory-title" data-user-directory>
        <header class="directory-header">
            <div>
                <h2 id="directory-title">Directory</h2>
                <p class="selection-summary" data-selection-summary aria-live="polite">No users selected</p>
            </div>

            <div class="directory-controls">
                <label class="directory-search" for="directory-search">
                    <x-admin.icon name="search" :size="18" />
                    <span class="sr-only">Search directory</span>
                    <input id="directory-search" type="search" placeholder="Search..." autocomplete="off" data-directory-search>
                </label>

                <label class="role-filter" for="directory-role-filter">
                    <span class="sr-only">Filter by role</span>
                    <select id="directory-role-filter" data-role-filter>
                        <option value="">All roles</option>
                        @foreach ($roleOptions as $role => $label)
                            <option value="{{ $role }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-admin.icon name="chevron-down" :size="15" />
                </label>
            </div>
        </header>

        <div class="user-table-shell">
            <table class="user-directory-table">
                <thead>
                    <tr>
                        <th class="checkbox-column" scope="col">
                            <input type="checkbox" data-select-all aria-label="Select all displayed users">
                        </th>
                        <th scope="col">Name</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Joined</th>
                        <th class="actions-column" scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody data-directory-body>
                    @foreach ($users as $user)
                        <tr
                            data-directory-row
                            data-user-id="{{ $user['id'] }}"
                            data-user-name="{{ Str::lower($user['name']) }}"
                            data-user-email="{{ Str::lower($user['email']) }}"
                            data-user-role="{{ $user['role'] }}"
                        >
                            <td class="checkbox-column">
                                <input
                                    type="checkbox"
                                    value="{{ $user['id'] }}"
                                    data-user-checkbox
                                    aria-label="Select {{ $user['name'] }}"
                                >
                            </td>
                            <td>
                                <div class="directory-user">
                                    <span class="directory-avatar" aria-hidden="true">
                                        @if ($user['avatarUrl'])
                                            <img src="{{ $user['avatarUrl'] }}" alt="">
                                        @else
                                            {{ $user['initials'] }}
                                        @endif
                                    </span>
                                    <span class="directory-user-copy">
                                        <strong>{{ $user['name'] }}</strong>
                                        <small>{{ $user['email'] }}</small>
                                    </span>
                                </div>
                            </td>
                            <td><x-admin.user-role-badge :label="$user['roleLabel']" /></td>
                            <td><x-admin.user-status-badge :status="$user['status']" :label="$user['statusLabel']" /></td>
                            <td class="joined-cell">{{ $user['joined'] }}</td>
                            <td class="actions-column">
                                <div class="user-actions" data-user-actions>
                                    <button
                                        class="user-actions-trigger"
                                        type="button"
                                        aria-label="Actions for {{ $user['name'] }}"
                                        aria-haspopup="menu"
                                        aria-expanded="false"
                                        data-user-actions-trigger
                                    >
                                        <x-admin.icon name="more-horizontal" :size="20" />
                                    </button>
                                    <div class="user-actions-menu" role="menu" data-user-actions-menu hidden>
                                        <button type="button" role="menuitem" data-prepared-action="View profile">
                                            <x-admin.icon name="eye" :size="16" /> View profile
                                        </button>
                                        <button type="button" role="menuitem" data-prepared-action="Edit user">
                                            <x-admin.icon name="edit" :size="16" /> Edit user
                                        </button>
                                        <button type="button" role="menuitem" data-prepared-action="Manage role">
                                            <x-admin.icon name="role" :size="16" /> Manage role
                                        </button>
                                        <button type="button" role="menuitem" data-prepared-action="Reset password">
                                            <x-admin.icon name="key" :size="16" /> Reset password
                                        </button>
                                        <button
                                            class="danger-menu-item"
                                            type="button"
                                            role="menuitem"
                                            data-deactivate-user
                                            data-user-name="{{ $user['name'] }}"
                                        >
                                            <x-admin.icon name="user-minus" :size="16" /> Deactivate user
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    <tr class="directory-empty-row" data-directory-empty @if ($users->isNotEmpty()) hidden @endif>
                        <td colspan="6">
                            <div class="directory-empty-state">
                                <span><x-admin.icon name="users" :size="25" /></span>
                                <strong>No users found</strong>
                                <p>Try changing your search or role filter.</p>
                                <button type="button" data-clear-directory-filters>Clear filters</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <x-admin.dialog
        id="create-user-dialog"
        title="Create user"
        description="Add a universal COMPASS account. Role-specific profile details can be completed later."
        :open-on-load="old('_dialog') === 'create-user-dialog' && $errors->any()"
    >
        <form method="POST" action="{{ route('admin.users.store') }}" data-dialog-form>
            @csrf
            <input type="hidden" name="_dialog" value="create-user-dialog">

            <div class="admin-dialog-body">
                <fieldset class="dialog-fieldset">
                    <legend>Basic information</legend>
                    <div class="form-grid form-grid-two">
                        <label class="admin-field">
                            <span>First name</span>
                            <input name="first_name" type="text" value="{{ old('first_name') }}" maxlength="100" autocomplete="given-name" required>
                            @error('first_name') <small class="field-validation">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-field">
                            <span>Last name</span>
                            <input name="last_name" type="text" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name" required>
                            @error('last_name') <small class="field-validation">{{ $message }}</small> @enderror
                        </label>
                    </div>
                    <label class="admin-field">
                        <span>Email address</span>
                        <input name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
                        @error('email') <small class="field-validation">{{ $message }}</small> @enderror
                    </label>
                </fieldset>

                <fieldset class="dialog-fieldset">
                    <legend>Account</legend>
                    <div class="form-grid form-grid-two">
                        <label class="admin-field">
                            <span>Role</span>
                            <select name="role" required>
                                <option value="" disabled @selected(old('role') === null)>Select role</option>
                                @foreach ($roleOptions as $role => $label)
                                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role') <small class="field-validation">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-field">
                            <span>Account status</span>
                            <select name="account_status" required>
                                <option value="active" @selected(old('account_status', 'active') === 'active')>Active</option>
                                <option value="pending" @selected(old('account_status') === 'pending')>Pending</option>
                            </select>
                            @error('account_status') <small class="field-validation">{{ $message }}</small> @enderror
                        </label>
                    </div>
                    <p class="field-note">A secure random initial password is generated. The user sets their own password through Forgot Password.</p>
                </fieldset>
            </div>

            <footer class="admin-dialog-footer">
                <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
                <button class="admin-button admin-button-primary" type="submit">
                    <x-admin.icon name="user-plus" :size="17" /> Create user
                </button>
            </footer>
        </form>
    </x-admin.dialog>

    <x-admin.dialog
        id="import-users-dialog"
        title="Import users from CSV"
        description="Choose a CSV file for validation. Import submission will be connected when a reviewed backend workflow is available."
    >
        <form data-import-form novalidate>
            <div class="admin-dialog-body">
                <label class="csv-upload" for="users-csv-file">
                    <span class="csv-upload-icon"><x-admin.icon name="upload" :size="23" /></span>
                    <strong>Choose a CSV file</strong>
                    <small>CSV only, up to 5 MB</small>
                    <input id="users-csv-file" type="file" accept=".csv,text/csv" data-csv-input>
                </label>
                <p class="csv-file-status" data-csv-status aria-live="polite">No file selected.</p>
            </div>
            <footer class="admin-dialog-footer">
                <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
                <button class="admin-button admin-button-primary" type="submit" data-import-submit disabled>Import</button>
            </footer>
        </form>
    </x-admin.dialog>

    <x-admin.dialog
        id="deactivate-user-dialog"
        title="Deactivate user?"
        description="This action will require a reviewed backend authorization flow before it can change an account."
        size="small"
    >
        <div class="admin-dialog-body">
            <p class="confirmation-copy">You are preparing to deactivate <strong data-deactivate-name>this user</strong>. They would no longer be able to sign in.</p>
        </div>
        <footer class="admin-dialog-footer">
            <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
            <button class="admin-button admin-button-danger" type="button" data-confirm-deactivate>Confirm deactivation</button>
        </footer>
    </x-admin.dialog>
</x-admin.layout>
