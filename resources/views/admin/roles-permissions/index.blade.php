<x-admin.layout title="Roles & Permissions" page-title="Roles & Permissions" active-nav="roles-permissions" :admin="$admin" :search-action="route('admin.users')" search-placeholder="Search user accounts...">
    <header class="page-heading"><h1>Roles &amp; Permissions</h1><p>Existing role responsibilities and access rules.</p></header>
    <nav class="admin-role-tabs" aria-label="Application roles">
        @foreach ($roles as $role => $label)
            <a href="{{ route('admin.roles-permissions', ['role' => $role]) }}" @if($role === $selectedRole) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <section class="dashboard-card admin-role-overview" aria-label="Selected role">
        <h2>{{ $roles[$selectedRole] }}</h2>
        <p>{{ $responsibilities[$selectedRole] }}</p>
        <p class="admin-muted-copy">Read-only summary of current role access. Allowed operations still require an active account and all applicable ownership, supervision, readiness, consent, and workflow checks. This page does not change permissions.</p>
        <p class="admin-muted-copy">Private identity information is controlled by the Identity Vault authorization and consent rules. Account administration does not grant access to private identity records.</p>
    </section>
    @foreach ($groups as $title => $operations)
        <section class="dashboard-card admin-permission-group" aria-label="{{ $title }}">
            <h2>{{ $title }}</h2>
            <div class="admin-table-scroll" role="region" tabindex="0" aria-label="{{ $title }} permissions">
                <table class="admin-permission-table"><thead><tr><th scope="col">Operation</th><th scope="col">Role access</th><th scope="col">Scope / requirements</th></tr></thead><tbody>
                    @forelse ($operations as $operation)
                        <tr><th scope="row">{{ $operation['label'] }}</th><td><span class="admin-access-badge {{ $operation['allowed'] ? 'is-allowed' : 'is-restricted' }}">{{ $operation['allowed'] ? 'Allowed in scope' : 'Not allowed' }}</span></td><td>{{ $operation['description'] }}</td></tr>
                    @empty
                        <tr><td colspan="3">No operations are defined in this group.</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </section>
    @endforeach
</x-admin.layout>
