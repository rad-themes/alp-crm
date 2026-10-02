<style>
    .rp-section + .rp-section { margin-top: 2.5rem; }
    .rp-section h2 { font-size: 1.05rem; font-weight: 600; margin: 0 0 .75rem; }
    .rp-card { background: #fff; border: 1px solid #e4e4e7; border-radius: 1rem; overflow: hidden; }
    .rp-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .rp-table th { text-align: left; font-weight: 500; color: #71717a; padding: .7rem 1rem; border-bottom: 1px solid #e4e4e7; white-space: nowrap; }
    .rp-table td { padding: .8rem 1rem; border-bottom: 1px solid #f4f4f5; vertical-align: middle; }
    .rp-table tr:last-child td { border-bottom: 0; }
    .rp-num { text-align: right !important; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .rp-muted { color: #71717a; }
    .rp-badge { display: inline-block; border-radius: 999px; padding: .1rem .55rem; font-size: .75rem; font-weight: 500; background: #f4f4f5; color: #3f3f46; white-space: nowrap; }
    .rp-badge-green { background: #dcfce7; color: #166534; }
    .rp-badge-red { background: #fee2e2; color: #991b1b; }
    .rp-badge-blue { background: #dbeafe; color: #1e40af; }
    .rp-actions { display: flex; gap: .75rem; justify-content: flex-end; white-space: nowrap; }
    .rp-link { color: var(--portal-brand, #18181b); font-weight: 500; text-decoration: underline; text-underline-offset: 2px; }
    .rp-button { display: inline-block; border-radius: .6rem; padding: .35rem .8rem; font-weight: 600; font-size: .8rem; background: var(--portal-brand, #18181b); color: #fff; text-decoration: none; }
    .rp-empty { padding: 1.5rem 1rem; color: #71717a; font-size: .875rem; }
    .rp-scroll { overflow-x: auto; }
    .rp-table strong { white-space: nowrap; }
    @media (max-width: 640px) {
        .rp-table thead, .rp-hide-sm { display: none !important; }
        .rp-table, .rp-table tbody, .rp-table tr, .rp-table td { display: block; width: 100%; }
        .rp-table tr { padding: .85rem 1rem; border-bottom: 1px solid #f4f4f5; }
        .rp-table tr:last-child { border-bottom: 0; }
        .rp-table td { border: 0; padding: .1rem 0; text-align: left !important; }
        .rp-table td[data-label]::before { content: attr(data-label) " "; color: #71717a; }
        .rp-actions { justify-content: flex-start; margin-top: .6rem; }
    }
</style>
