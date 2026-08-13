(() => {
    const body = document.body;
    const sidebar = document.querySelector('[data-admin-sidebar]');
    const openButton = document.querySelector('[data-sidebar-open]');
    const closeButtons = document.querySelectorAll('[data-sidebar-close]');
    const searchInput = document.querySelector('[data-admin-search]');
    const profileMenu = document.querySelector('[data-profile-menu]');
    const profileTrigger = document.querySelector('[data-profile-trigger]');
    const profileDropdown = document.querySelector('[data-profile-dropdown]');

    const setSidebarOpen = (open) => {
        body.classList.toggle('sidebar-open', open);
        openButton?.setAttribute('aria-expanded', String(open));
        if (open) sidebar?.querySelector('a, button')?.focus();
    };

    openButton?.addEventListener('click', () => setSidebarOpen(true));
    closeButtons.forEach((button) => button.addEventListener('click', () => setSidebarOpen(false)));

    const closeProfileMenu = () => {
        if (!profileDropdown || !profileTrigger) return;
        profileDropdown.hidden = true;
        profileTrigger.setAttribute('aria-expanded', 'false');
    };

    profileTrigger?.addEventListener('click', () => {
        if (!profileDropdown) return;
        const shouldOpen = profileDropdown.hidden;
        profileDropdown.hidden = !shouldOpen;
        profileTrigger.setAttribute('aria-expanded', String(shouldOpen));
    });

    document.addEventListener('click', (event) => {
        if (profileMenu && !profileMenu.contains(event.target)) closeProfileMenu();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
            closeProfileMenu();
        }
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            searchInput?.focus();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 980) setSidebarOpen(false);
    });

    const feedback = document.querySelector('[data-users-feedback]');
    const announce = (message) => {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.hidden = false;
        feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    let dialogTrigger = null;
    const dialogs = document.querySelectorAll('[data-admin-dialog]');
    const openDialog = (dialog, trigger = null) => {
        if (!dialog || typeof dialog.showModal !== 'function') return;
        dialogTrigger = trigger;
        if (!dialog.open) dialog.showModal();
        dialog.querySelector('input:not([type="hidden"]), select, button')?.focus();
    };

    document.querySelectorAll('[data-dialog-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => openDialog(document.getElementById(trigger.dataset.dialogOpen), trigger));
    });

    dialogs.forEach((dialog) => {
        if (dialog.hasAttribute('data-open-on-load')) openDialog(dialog);

        dialog.querySelectorAll('[data-dialog-close]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });

        dialog.addEventListener('close', () => {
            dialogTrigger?.focus();
            dialogTrigger = null;
        });

        dialog.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') return;
            const focusable = [...dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), [tabindex]:not([tabindex="-1"])')];
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    });

    const directory = document.querySelector('[data-user-directory]');
    if (directory) {
        const rows = [...directory.querySelectorAll('[data-directory-row]')];
        const search = directory.querySelector('[data-directory-search]');
        const roleFilter = directory.querySelector('[data-role-filter]');
        const selectAll = directory.querySelector('[data-select-all]');
        const emptyState = directory.querySelector('[data-directory-empty]');
        const selectionSummary = directory.querySelector('[data-selection-summary]');
        const selectedIds = new Set();

        const visibleRows = () => rows.filter((row) => !row.hidden);

        const syncSelection = () => {
            rows.forEach((row) => {
                const checkbox = row.querySelector('[data-user-checkbox]');
                const selected = selectedIds.has(row.dataset.userId);
                checkbox.checked = selected;
                row.classList.toggle('is-selected', selected);
            });

            const visibleCheckboxes = visibleRows().map((row) => row.querySelector('[data-user-checkbox]'));
            const visibleSelected = visibleCheckboxes.filter((checkbox) => checkbox.checked).length;
            selectAll.checked = visibleCheckboxes.length > 0 && visibleSelected === visibleCheckboxes.length;
            selectAll.indeterminate = visibleSelected > 0 && visibleSelected < visibleCheckboxes.length;
            selectionSummary.textContent = selectedIds.size === 0
                ? 'No users selected'
                : `${selectedIds.size} ${selectedIds.size === 1 ? 'user' : 'users'} selected`;
        };

        const filterRows = () => {
            const query = search.value.trim().toLowerCase();
            const role = roleFilter.value;
            let visibleCount = 0;

            rows.forEach((row) => {
                const matchesSearch = query === ''
                    || row.dataset.userName.includes(query)
                    || row.dataset.userEmail.includes(query)
                    || row.dataset.userRole.includes(query);
                const matchesRole = role === '' || row.dataset.userRole === role;
                row.hidden = !(matchesSearch && matchesRole);
                if (!row.hidden) visibleCount += 1;
            });

            emptyState.hidden = visibleCount !== 0;
            syncSelection();
        };

        search.addEventListener('input', filterRows);
        roleFilter.addEventListener('change', filterRows);
        directory.querySelector('[data-clear-directory-filters]')?.addEventListener('click', () => {
            search.value = '';
            roleFilter.value = '';
            search.focus();
            filterRows();
        });

        rows.forEach((row) => {
            row.querySelector('[data-user-checkbox]').addEventListener('change', (event) => {
                if (event.target.checked) selectedIds.add(row.dataset.userId);
                else selectedIds.delete(row.dataset.userId);
                syncSelection();
            });
        });

        selectAll.addEventListener('change', () => {
            visibleRows().forEach((row) => {
                if (selectAll.checked) selectedIds.add(row.dataset.userId);
                else selectedIds.delete(row.dataset.userId);
            });
            syncSelection();
        });

        filterRows();
    }

    const actionMenus = [...document.querySelectorAll('[data-user-actions]')];
    const closeActionMenus = (except = null) => {
        actionMenus.forEach((actions) => {
            if (actions === except) return;
            const trigger = actions.querySelector('[data-user-actions-trigger]');
            const menu = actions.querySelector('[data-user-actions-menu]');
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        });
    };

    actionMenus.forEach((actions) => {
        const trigger = actions.querySelector('[data-user-actions-trigger]');
        const menu = actions.querySelector('[data-user-actions-menu]');

        trigger.addEventListener('click', () => {
            const shouldOpen = menu.hidden;
            closeActionMenus(actions);
            menu.hidden = !shouldOpen;
            trigger.setAttribute('aria-expanded', String(shouldOpen));

            if (shouldOpen) {
                const triggerRect = trigger.getBoundingClientRect();
                const menuRect = menu.getBoundingClientRect();
                const left = Math.max(8, Math.min(triggerRect.right - menuRect.width, window.innerWidth - menuRect.width - 8));
                let top = triggerRect.bottom + 5;
                if (top + menuRect.height > window.innerHeight - 8) top = triggerRect.top - menuRect.height - 5;
                menu.style.left = `${left}px`;
                menu.style.top = `${Math.max(8, top)}px`;
                menu.querySelector('[role="menuitem"]')?.focus();
            }
        });

        menu.addEventListener('keydown', (event) => {
            const items = [...menu.querySelectorAll('[role="menuitem"]')];
            const current = items.indexOf(document.activeElement);
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                items[(current + direction + items.length) % items.length]?.focus();
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                closeActionMenus();
                trigger.focus();
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-user-actions]')) closeActionMenus();
    });
    window.addEventListener('scroll', () => closeActionMenus(), true);
    window.addEventListener('resize', () => closeActionMenus());

    document.querySelectorAll('[data-prepared-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const trigger = button.closest('[data-user-actions]')?.querySelector('[data-user-actions-trigger]');
            closeActionMenus();
            announce(`${button.dataset.preparedAction} is prepared for backend integration.`);
            trigger?.focus();
        });
    });

    const deactivateDialog = document.getElementById('deactivate-user-dialog');
    document.querySelectorAll('[data-deactivate-user]').forEach((button) => {
        button.addEventListener('click', () => {
            const trigger = button.closest('[data-user-actions]')?.querySelector('[data-user-actions-trigger]');
            closeActionMenus();
            deactivateDialog.querySelector('[data-deactivate-name]').textContent = button.dataset.userName;
            openDialog(deactivateDialog, trigger);
        });
    });
    document.querySelector('[data-confirm-deactivate]')?.addEventListener('click', () => {
        deactivateDialog.close();
        announce('Deactivation was not submitted. A protected backend authorization flow is still required.');
    });

    const csvInput = document.querySelector('[data-csv-input]');
    const csvStatus = document.querySelector('[data-csv-status]');
    const importSubmit = document.querySelector('[data-import-submit]');
    let csvIsValid = false;

    const setCsvStatus = (message, state = '') => {
        csvStatus.textContent = message;
        csvStatus.classList.toggle('is-error', state === 'error');
        csvStatus.classList.toggle('is-valid', state === 'valid');
        importSubmit.disabled = state !== 'valid';
        csvIsValid = state === 'valid';
    };

    csvInput?.addEventListener('change', () => {
        const file = csvInput.files[0];
        if (!file) return setCsvStatus('No file selected.');
        if (!file.name.toLowerCase().endsWith('.csv')) return setCsvStatus('Please choose a file with a .csv extension.', 'error');
        if (file.size === 0) return setCsvStatus('The selected CSV file is empty.', 'error');
        if (file.size > 5 * 1024 * 1024) return setCsvStatus('The selected CSV exceeds the 5 MB limit.', 'error');
        setCsvStatus(`${file.name} · ${(file.size / 1024).toFixed(1)} KB`, 'valid');
    });

    document.querySelector('[data-import-form]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!csvIsValid) return setCsvStatus('Choose a valid CSV file before importing.', 'error');
        document.getElementById('import-users-dialog')?.close();
        announce('CSV validated locally. Preview and import submission require the future reviewed backend workflow.');
    });

    const permissionsPage = document.querySelector('[data-permissions-page]');
    if (permissionsPage) {
        const matrix = permissionsPage.querySelector('[data-permission-matrix]');
        const switches = [...matrix.querySelectorAll('[data-permission-switch]')];
        const saveButton = permissionsPage.querySelector('[data-save-permissions]');
        const resetButton = permissionsPage.querySelector('[data-reset-permissions]');
        const saveState = permissionsPage.querySelector('[data-permission-save-state]');
        const toast = permissionsPage.querySelector('[data-rbac-toast]');
        const toastMessage = permissionsPage.querySelector('[data-rbac-toast-message]');
        const resetDialog = document.getElementById('reset-permissions-dialog');
        const duplicateDialog = document.getElementById('duplicate-role-dialog');
        const duplicateForm = permissionsPage.querySelector('[data-duplicate-role-form]');
        const duplicateName = permissionsPage.querySelector('[data-duplicate-role-name]');
        const duplicateError = permissionsPage.querySelector('[data-duplicate-role-error]');
        const dependentActions = new Set(['create', 'update', 'delete', 'approve', 'export']);
        const localRoleDrafts = new Map();
        let toastTimer = null;

        const showPermissionToast = (message, tone = 'success') => {
            window.clearTimeout(toastTimer);
            toastMessage.textContent = message;
            toast.classList.toggle('is-info', tone === 'info');
            toast.classList.toggle('is-warning', tone === 'warning');
            toast.hidden = false;
            toastTimer = window.setTimeout(() => {
                toast.hidden = true;
            }, 5000);
        };

        const setSwitch = (permissionSwitch, checked) => {
            permissionSwitch.setAttribute('aria-checked', String(checked));
            permissionSwitch.classList.toggle('is-enabled', checked);
        };

        const readMatrixState = () => {
            const state = {};

            switches.forEach((permissionSwitch) => {
                const category = permissionSwitch.dataset.category;
                state[category] ??= {};
                state[category][permissionSwitch.dataset.action] = permissionSwitch.getAttribute('aria-checked') === 'true';
            });

            return state;
        };

        const cloneState = (state) => JSON.parse(JSON.stringify(state));
        const stateSignature = (state) => JSON.stringify(state);
        let savedMatrixState = cloneState(readMatrixState());

        const syncDirtyState = () => {
            const dirty = stateSignature(readMatrixState()) !== stateSignature(savedMatrixState);
            saveButton.disabled = !dirty;
            resetButton.disabled = !dirty;
            saveState.textContent = dirty ? 'Unsaved changes' : 'All changes saved';
            saveState.classList.toggle('is-dirty', dirty);
        };

        const applyMatrixState = (state) => {
            switches.forEach((permissionSwitch) => {
                const checked = Boolean(state[permissionSwitch.dataset.category]?.[permissionSwitch.dataset.action]);
                setSwitch(permissionSwitch, checked);
            });
        };

        switches.forEach((permissionSwitch) => {
            permissionSwitch.addEventListener('click', () => {
                const category = permissionSwitch.dataset.category;
                const action = permissionSwitch.dataset.action;
                const currentlyEnabled = permissionSwitch.getAttribute('aria-checked') === 'true';
                const categorySwitches = switches.filter((candidate) => candidate.dataset.category === category);

                if (action === 'read' && currentlyEnabled) {
                    const activeDependency = categorySwitches.some((candidate) => (
                        dependentActions.has(candidate.dataset.action)
                        && candidate.getAttribute('aria-checked') === 'true'
                    ));

                    if (activeDependency) {
                        showPermissionToast('Read is required while another permission in this category is enabled.', 'warning');
                        return;
                    }
                }

                setSwitch(permissionSwitch, !currentlyEnabled);

                if (!currentlyEnabled && dependentActions.has(action)) {
                    const readSwitch = categorySwitches.find((candidate) => candidate.dataset.action === 'read');
                    if (readSwitch) setSwitch(readSwitch, true);
                }

                syncDirtyState();
            });
        });

        saveButton.addEventListener('click', () => {
            if (saveButton.disabled) return;
            savedMatrixState = cloneState(readMatrixState());
            syncDirtyState();
            showPermissionToast('Permissions updated successfully. The draft is saved locally pending RBAC backend integration.');
        });

        resetButton.addEventListener('click', () => {
            if (!resetButton.disabled) openDialog(resetDialog, resetButton);
        });

        permissionsPage.querySelector('[data-confirm-permission-reset]')?.addEventListener('click', () => {
            applyMatrixState(savedMatrixState);
            syncDirtyState();
            resetDialog.close();
            showPermissionToast('Unsaved permission changes were reset.', 'info');
        });

        let existingRoles = [];
        try {
            existingRoles = JSON.parse(permissionsPage.dataset.existingRoles || '[]');
        } catch {
            existingRoles = [];
        }
        const knownRoleNames = new Set(existingRoles.map((role) => role.trim().toLowerCase()));

        const setDuplicateError = (message = '') => {
            duplicateError.textContent = message;
            duplicateError.hidden = message === '';
            duplicateName.setAttribute('aria-invalid', String(message !== ''));
        };

        duplicateName?.addEventListener('input', () => setDuplicateError());
        duplicateForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            const roleName = duplicateName.value.trim().replace(/\s+/g, ' ');

            if (roleName === '') {
                setDuplicateError('Enter a role name.');
                duplicateName.focus();
                return;
            }

            if (knownRoleNames.has(roleName.toLowerCase()) || localRoleDrafts.has(roleName.toLowerCase())) {
                setDuplicateError('A role with this name already exists.');
                duplicateName.focus();
                return;
            }

            localRoleDrafts.set(roleName.toLowerCase(), cloneState(readMatrixState()));
            duplicateDialog.close();
            duplicateForm.reset();
            setDuplicateError();
            showPermissionToast(`${roleName} was duplicated as a local role draft.`, 'info');
        });

        duplicateDialog?.addEventListener('close', () => {
            duplicateForm?.reset();
            setDuplicateError();
        });

        syncDirtyState();
    }

    const resourceLibrary = document.querySelector('[data-resource-library]');
    if (resourceLibrary) {
        const cards = [...resourceLibrary.querySelectorAll('[data-resource-card]')];
        const search = resourceLibrary.querySelector('[data-resource-search]');
        const categoryChips = [...resourceLibrary.querySelectorAll('[data-resource-category-chip]')];
        const bookmarksButton = resourceLibrary.querySelector('[data-bookmarks-mode]');
        const bookmarksButtonLabel = resourceLibrary.querySelector('[data-bookmarks-button-label]');
        const emptyState = resourceLibrary.querySelector('[data-resource-empty]');
        const emptyTitle = resourceLibrary.querySelector('[data-resource-empty-title]');
        const emptyCopy = resourceLibrary.querySelector('[data-resource-empty-copy]');
        const emptyAction = resourceLibrary.querySelector('[data-resource-empty-action]');
        const resultsStatus = resourceLibrary.querySelector('[data-resource-results-status]');
        const filterCount = resourceLibrary.querySelector('[data-resource-filter-count]');
        const filterDialog = document.getElementById('resource-filters-dialog');
        const filterForm = resourceLibrary.querySelector('[data-resource-filter-form]');
        const typeFilter = resourceLibrary.querySelector('[data-resource-type-filter]');
        const categoryFilter = resourceLibrary.querySelector('[data-resource-category-filter]');
        const durationFilter = resourceLibrary.querySelector('[data-resource-duration-filter]');
        const bookmarkedFilter = resourceLibrary.querySelector('[data-resource-bookmarked-filter]');
        const toast = resourceLibrary.querySelector('[data-resource-toast]');
        const toastMessage = resourceLibrary.querySelector('[data-resource-toast-message]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        let activeCategory = 'all';
        let activeType = 'all';
        let activeDuration = 'all';
        let bookmarksOnly = false;
        let resourceToastTimer = null;

        const showResourceToast = (message, tone = 'success') => {
            window.clearTimeout(resourceToastTimer);
            toastMessage.textContent = message;
            toast.classList.toggle('is-info', tone === 'info');
            toast.classList.toggle('is-warning', tone === 'warning');
            toast.hidden = false;
            resourceToastTimer = window.setTimeout(() => {
                toast.hidden = true;
            }, 4500);
        };

        const syncFilterControls = () => {
            categoryChips.forEach((chip) => {
                const selected = chip.dataset.resourceCategoryChip === activeCategory;
                chip.classList.toggle('is-active', selected);
                chip.setAttribute('aria-pressed', String(selected));
            });

            if (categoryFilter) categoryFilter.value = activeCategory;
            if (typeFilter) typeFilter.value = activeType;
            if (durationFilter) durationFilter.value = activeDuration;
            if (bookmarkedFilter) bookmarkedFilter.checked = bookmarksOnly;
            bookmarksButton?.setAttribute('aria-pressed', String(bookmarksOnly));
            if (bookmarksButtonLabel) bookmarksButtonLabel.textContent = bookmarksOnly ? 'All resources' : 'My bookmarks';

            const appliedCount = [
                activeCategory !== 'all',
                activeType !== 'all',
                activeDuration !== 'all',
                bookmarksOnly,
            ].filter(Boolean).length;

            if (filterCount) {
                filterCount.textContent = String(appliedCount);
                filterCount.hidden = appliedCount === 0;
            }
        };

        const filterResources = () => {
            const query = search?.value.trim().toLowerCase() || '';
            const maxDuration = activeDuration === 'all' ? null : Number(activeDuration);
            let visibleCount = 0;

            cards.forEach((card) => {
                const duration = Number(card.dataset.resourceDuration);
                const matchesSearch = query === '' || card.dataset.resourceSearch.includes(query);
                const matchesCategory = activeCategory === 'all' || card.dataset.resourceCategory === activeCategory;
                const matchesType = activeType === 'all' || card.dataset.resourceType === activeType;
                const matchesDuration = maxDuration === null
                    || (card.dataset.resourceDuration !== '' && Number.isFinite(duration) && duration <= maxDuration);
                const matchesBookmarks = !bookmarksOnly || card.dataset.resourceBookmarked === 'true';
                const visible = matchesSearch && matchesCategory && matchesType && matchesDuration && matchesBookmarks;

                card.hidden = !visible;
                if (visible) visibleCount += 1;
            });

            if (emptyState) {
                emptyState.hidden = visibleCount !== 0;

                if (bookmarksOnly && visibleCount === 0) {
                    emptyTitle.textContent = 'No bookmarked resources yet';
                    emptyCopy.textContent = 'Bookmark helpful resources to find them quickly later.';
                    emptyAction.textContent = 'Browse resources';
                } else {
                    emptyTitle.textContent = 'No resources found';
                    emptyCopy.textContent = 'Try changing your search or filters.';
                    emptyAction.textContent = 'Clear filters';
                }
            }

            if (resultsStatus) {
                resultsStatus.textContent = `${visibleCount} ${visibleCount === 1 ? 'resource' : 'resources'} shown`;
            }

            syncFilterControls();
        };

        search?.addEventListener('input', filterResources);
        categoryChips.forEach((chip) => {
            chip.addEventListener('click', () => {
                activeCategory = chip.dataset.resourceCategoryChip;
                filterResources();
            });
        });

        bookmarksButton?.addEventListener('click', () => {
            bookmarksOnly = !bookmarksOnly;
            filterResources();
        });

        filterForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            activeType = typeFilter.value;
            activeCategory = categoryFilter.value;
            activeDuration = durationFilter.value;
            bookmarksOnly = bookmarkedFilter.checked;
            filterDialog?.close();
            filterResources();
        });

        filterDialog?.addEventListener('close', syncFilterControls);

        resourceLibrary.querySelector('[data-clear-advanced-filters]')?.addEventListener('click', () => {
            activeType = 'all';
            activeCategory = 'all';
            activeDuration = 'all';
            bookmarksOnly = false;
            filterDialog?.close();
            filterResources();
        });

        resourceLibrary.querySelector('[data-clear-resource-filters]')?.addEventListener('click', () => {
            if (search) search.value = '';
            activeType = 'all';
            activeCategory = 'all';
            activeDuration = 'all';
            bookmarksOnly = false;
            filterResources();
            search?.focus();
        });

        cards.forEach((card) => {
            const bookmarkButton = card.querySelector('[data-resource-bookmark]');
            const title = card.querySelector('h2')?.textContent.trim() || 'resource';

            bookmarkButton?.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                const isBookmarked = card.dataset.resourceBookmarked === 'true';
                const url = isBookmarked ? bookmarkButton.dataset.unsaveUrl : bookmarkButton.dataset.saveUrl;

                bookmarkButton.disabled = true;
                bookmarkButton.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) throw new Error('Bookmark request failed');

                    const result = await response.json();
                    const saved = Boolean(result.saved);
                    card.dataset.resourceBookmarked = String(saved);
                    bookmarkButton.classList.toggle('is-bookmarked', saved);
                    bookmarkButton.setAttribute('aria-pressed', String(saved));
                    bookmarkButton.setAttribute('aria-label', `${saved ? 'Remove bookmark from' : 'Bookmark'} ${title}`);
                    showResourceToast(saved ? `${title} added to your bookmarks.` : `${title} removed from your bookmarks.`);
                    filterResources();
                } catch {
                    showResourceToast('Unable to update this bookmark. Please try again.', 'warning');
                } finally {
                    bookmarkButton.disabled = false;
                    bookmarkButton.removeAttribute('aria-busy');
                }
            });
        });

        filterResources();
    }
})();
