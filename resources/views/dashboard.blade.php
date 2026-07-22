<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Queue Dashboard</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 40px;
            background: #f3f4f6;
            color: #1f2937;
            margin: 0;
        }

        .card {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        h1 {
            margin-top: 0;
            font-size: 1.5rem;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>Shared Queue Jobs (Site: {{ \Leafling\SharedQueue\Models\JobTracker::resolveSiteCode() }})</h1>
        @include('shared-queue::partials.jobs-table')
    </div>
</body>

</html>
