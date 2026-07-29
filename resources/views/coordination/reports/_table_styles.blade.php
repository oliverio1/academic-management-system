<style>
    .coordination-report-filters {
        margin-bottom: 1rem;
    }

    .coordination-report-table-wrap {
        padding: 0;
    }

    .reports-table {
        border-collapse: separate;
        border-spacing: 0;
    }

    .reports-table thead th {
        background: #f8fafc;
        border-bottom: 1px solid #d9e2ec;
        color: #344054;
        font-size: .78rem;
        letter-spacing: .02em;
        text-transform: uppercase;
        vertical-align: middle;
    }

    .reports-table tbody tr {
        background: #fff;
    }

    .reports-table tbody td {
        padding-top: .72rem;
        padding-bottom: .72rem;
        vertical-align: middle;
    }

    .reports-table tbody tr.report-row-open-high {
        box-shadow: inset 4px 0 0 #d92d20;
    }

    .reports-table tbody tr.report-row-open-medium {
        box-shadow: inset 4px 0 0 #f79009;
    }

    .reports-table tbody tr.report-row-open-low {
        box-shadow: inset 4px 0 0 #667085;
    }

    .reports-table tbody tr.report-row-open-high td {
        background: #fff8f6;
    }

    .reports-table tbody tr.report-row-open-medium td {
        background: #fffcf5;
    }

    .reports-table tbody tr.report-row-reviewed td {
        background: #f9fafb;
        color: #667085;
    }

    .reports-table tbody tr.report-row-resolved td {
        background: #f6fef9;
        color: #475467;
    }

    .report-badge {
        border-radius: 999px;
        font-weight: 700;
        padding: .28rem .5rem;
    }

    .report-badge-high {
        background: #fee4e2;
        color: #b42318;
    }

    .report-badge-medium {
        background: #fef0c7;
        color: #b54708;
    }

    .report-badge-low {
        background: #eaecf0;
        color: #475467;
    }

    .report-status-open {
        background: #fff4e5;
        color: #b54708;
        border: 1px solid #fedf89;
    }

    .report-status-reviewed {
        background: #ecfdf3;
        color: #027a48;
        border: 1px solid #abefc6;
    }

    .report-status-resolved {
        background: #e0f2fe;
        color: #075985;
        border: 1px solid #bae6fd;
    }

    .report-actions {
        display: flex;
        flex-direction: column;
        gap: .35rem;
    }
</style>
