<x-admin.layout
    title="Reports"
    page-title="Reports"
    page-subtitle="Account and operational records"
    active-nav="reports"
    search-placeholder="Search report catalog..."
    :search-query="$searchQuery"
    :search-action="route('admin.reports')"
    :admin="$admin"
>
    <section class="reports-page" data-reports-page>
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <span>COMPASS</span><span aria-hidden="true">/</span><strong>Reports</strong>
        </nav>

        <header class="reports-page-heading">
            <div>
                <h1>Reports</h1>
                <p>Review actual system activity, account totals, and operational records.</p>
            </div>

        </header>

        <x-admin.activity-report :report="$roleReport" />
        <details class="admin-report-catalog" data-report-catalog @if($searchQuery !== '') open @endif>
            <summary>Report catalog <span>Source summaries and available report actions</span></summary>
            <div class="admin-catalog-heading"><p>Catalog totals cover all source records. Use the activity report above for period-filtered records.</p><button class="admin-button admin-button-secondary" type="button" data-dialog-open="report-filters-dialog">Catalog filters <span class="report-filter-count" data-report-filter-count hidden>0</span></button></div>

    @if ($loadFailed)
            <section class="reports-state" role="alert">
                <span></span>
                <h2>Unable to load reports</h2>
                <p>Please try again.</p>
                <a class="admin-button admin-button-secondary" href="{{ route('admin.reports') }}">Retry</a>
            </section>
        @else
            @if ($reports->isNotEmpty())
                <p class="report-results-status" data-report-results-status aria-live="polite"></p>

                <section class="reports-grid" data-reports-grid aria-label="Available reports">
                    @foreach ($reports as $report)
                        <x-admin.report-card :report="$report" />
                    @endforeach
                </section>

                <section class="reports-state report-filter-empty" data-report-filter-empty hidden>
                    <span></span>
                    <h2>No reports found</h2>
                    <p>Try changing your filters.</p>
                    <button class="admin-button admin-button-secondary" type="button" data-clear-report-filters>Clear filters</button>
                </section>
            @else
                <section class="reports-state">
                    <span></span>
                    <h2>No reports configured</h2>
                    <p>Report definitions will appear here once they are available.</p>
                </section>
            @endif

            <div class="admin-toast is-info" data-report-toast role="status" aria-live="polite" hidden>

                <span data-report-toast-message></span>
            </div>
        @endif
        </details>
    </section>

    @unless ($loadFailed)
        <x-admin.dialog
            id="report-filters-dialog"
            title="Filter reports"
            description="Narrow the catalog by category, source activity, and available output."
            size="small"
        >
            <form data-report-filter-form>
                <div class="admin-dialog-body report-filter-fields">
                    <label class="admin-field">
                        <span>Category</span>
                        <select data-report-category-filter>
                            <option value="all">All categories</option>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}">{{ Str::headline($value) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="admin-field">
                        <span>Date updated</span>
                        <select data-report-date-filter>
                            <option value="all">Any time</option>
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </label>

                    <label class="admin-field">
                        <span>Output type</span>
                        <select data-report-output-filter>
                            <option value="all">All output types</option>
                            <option value="viewable">Viewable</option>
                            <option value="exportable">Exportable</option>
                            <option value="printable">Printable</option>
                        </select>
                    </label>
                </div>
                <footer class="admin-dialog-footer">
                    <button class="admin-button admin-button-secondary" type="button" data-clear-report-filters>Clear filters</button>
                    <button class="admin-button admin-button-primary" type="submit">Apply filters</button>
                </footer>
            </form>
        </x-admin.dialog>

        <x-admin.dialog
            id="report-preview-dialog"
            title="Report preview"
            description="Catalog metadata and source readiness."
        >
            <div class="admin-dialog-body report-preview-body">
                <div class="report-preview-heading">
                    <span></span>
                    <div>
                        <small data-report-preview-category></small>
                        <h3 data-report-preview-title></h3>
                        <p data-report-preview-description></p>
                    </div>
                </div>

                <dl class="report-preview-metadata">
                    <div><dt>Data source</dt><dd data-report-preview-source></dd></div>
                    <div><dt>Source records</dt><dd data-report-preview-records></dd></div>
                    <div><dt>Latest activity</dt><dd data-report-preview-updated></dd></div>
                </dl>

                <div class="report-privacy-note" data-report-privacy-note hidden>

                    <p>This catalog item can contain sensitive incident information. Detailed access requires a reviewed report policy and backend authorization.</p>
                </div>

                <div class="report-operation-note" role="note">

                    <p>This preview intentionally shows metadata only. Use the activity report above for filtered operational records. Catalog exports are not configured.</p>
                </div>
            </div>
            <footer class="admin-dialog-footer">
                <button class="admin-button admin-button-primary" type="button" data-dialog-close>Close</button>
            </footer>
        </x-admin.dialog>

        <x-admin.dialog
            id="report-unavailable-dialog"
            title="Report action unavailable"
            description="A secure report backend is required for this operation."
            size="small"
        >
            <div class="admin-dialog-body">
                <div class="report-operation-note" role="note">

                    <p><strong data-report-unavailable-action-label></strong> for <strong data-report-unavailable-title></strong> is not configured. No file was generated, printed, exposed, or downloaded.</p>
                </div>
            </div>
            <footer class="admin-dialog-footer">
                <button class="admin-button admin-button-primary" type="button" data-dialog-close>Understood</button>
            </footer>
        </x-admin.dialog>
    @endunless
</x-admin.layout>
