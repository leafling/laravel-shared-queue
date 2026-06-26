<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Queue Dashboard</title>
    <style>
        body { font-family: -apple-system, sans-serif; padding: 40px; background: #f3f4f6; color: #1f2937; }
        .card { background: white; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; font-size: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-running { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
        button { background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #dc2626; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Shared Queue Jobs (Site: {{ \Leafling\SharedQueue\Models\JobTracker::resolveSiteCode() }})</h1>
        @include('shared-queue::partials.jobs-table')
    </div>
</body>
</html>
