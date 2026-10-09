<x-admin.layout
    title="User Management"
    page-title="Users"
    page-subtitle="Account directory and access"
    active-nav="users"
    search-placeholder="Search user accounts..."
    :search-query="$filters['q'] ?? ''"
    :search-action="route('admin.users')"
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
                {{ number_format($userSummary['unverified']) }} {{ Str::plural('account', $userSummary['unverified']) }} awaiting email verification
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

    @foreach(['q','role','status'] as $filter) @error($filter)<div class="admin-flash" role="alert">{{ $message }}</div>@enderror @endforeach
    @error('confirm_deactivation')<div class="admin-flash" role="alert">{{ $message }}</div>@enderror
    @error('account')<div class="admin-flash" role="alert">{{ $message }}</div>@enderror
    @if (session('success'))
        <div class="admin-flash admin-flash-success" role="status">

            {{ session('success') }}
        </div>
    @endif

    <div class="admin-flash admin-flash-info" data-users-feedback role="status" hidden></div>

    <section class="directory-card" aria-labelledby="directory-title" data-user-directory data-server-directory>
        <header class="directory-header">
            <div>
                <h2 id="directory-title">Directory</h2>
                <p class="selection-summary" data-selection-summary aria-live="polite">No users selected</p>
            </div>

            <form method="GET" action="{{ route('admin.users') }}" class="directory-controls">
                <label class="directory-search" for="directory-search">
                    <x-admin.icon name="search" :size="18" />
                    <span class="sr-only">Search directory</span>
                    <input id="directory-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" type="search" placeholder="Name or email..." autocomplete="off" data-directory-search>
                </label>

                <label class="role-filter" for="directory-role-filter">
                    <span class="sr-only">Filter by role</span>
                    <select id="directory-role-filter" name="role" data-role-filter>
                        <option value="">All roles</option>
                        @foreach ($roleOptions as $role => $label)
                            <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-admin.icon name="chevron-down" :size="15" />
                </label>
                <label class="role-filter"><span class="sr-only">Account status</span><select id="directory-status-filter" name="status"><option value="">All account statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select></label>
                <button class="admin-button admin-button-primary" type="submit">Search</button>
                <a class="admin-text-link" href="{{ route('admin.users') }}">Reset</a>
            </form>
        </header>

        <div class="user-table-shell" tabindex="0" role="region" aria-label="User accounts">
            <table class="user-directory-table">
                <thead>
                    <tr>
                        <th class="checkbox-column" scope="col">
                            <input type="checkbox" data-select-all aria-label="Select all displayed users">
                        </th>
                        <th scope="col">Name</th>
                        <th scope="col">Role</th>
                        <th scope="col">Account status</th>
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
                                @if ($user['status'] === 'active' && (string) $admin->id !== $user['id'])
                                    <button class="admin-deactivate-button" type="button" data-deactivate-user data-deactivate-url="{{ route('admin.users.deactivate', $user['id']) }}" data-user-name="{{ $user['name'] }}" aria-label="Deactivate {{ $user['name'] }}">Deactivate user</button>
                                @elseif ((string) $admin->id === $user['id'])
                                    <span class="admin-account-note">Your account</span>
                                @else
                                    <span class="admin-account-note">Deactivated</span>
                                @endif
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

                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    <tr class="directory-empty-row" data-directory-empty @if ($users->isNotEmpty()) hidden @endif>
                        <td colspan="6">
                            <div class="directory-empty-state">
                                <span></span>
                                <strong>No users found</strong>
                                <p>Try changing your search or role filter.</p>
                                <a href="{{ route('admin.users') }}">Clear filters</a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <footer class="admin-directory-pagination">{{ $users->links() }}</footer>
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
                    <div class="form-grid form-grid-two">
                        <label class="admin-field">
                            <span>Initial password</span>
                            <input name="password" type="password" value="" maxlength="255" autocomplete="new-password" required>
                            @error('password') <small class="field-validation">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-field">
                            <span>Confirm password</span>
                            <input name="password_confirmation" type="password" value="" maxlength="255" autocomplete="new-password" required>
                            @error('password_confirmation') <small class="field-validation">{{ $message }}</small> @enderror
                        </label>
                    </div>
                    <p class="field-note">At least 8 characters with upper and lower case letters, a number, and a symbol. Share this initial password with the user securely.</p>
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
        description="Accounts with protected Helper obligations cannot be deactivated."
        size="small"
    >
        <div class="admin-dialog-body">
            <p class="confirmation-copy">You are preparing to deactivate <strong data-deactivate-name>this user</strong>. They will no longer be able to sign in. Existing records and history will be retained.</p>
        </div>
        <footer class="admin-dialog-footer">
            <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
            <form method="POST" data-deactivate-form>@csrf<input type="hidden" name="confirm_deactivation" value="1"><button class="admin-button admin-button-danger" type="submit">Confirm deactivation</button></form>
        </footer>
    </x-admin.dialog>
</x-admin.layout>
