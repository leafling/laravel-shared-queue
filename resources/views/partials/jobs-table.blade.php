<div class="shared-queue-table-wrapper">
    <style>
        .sq-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-family: sans-serif; color: #1f2937; }
        .sq-table th, .sq-table td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e7eb; }
        .sq-table th { background: #f9fafb; font-weight: 600; }
        .sq-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; }
        .sq-badge-running { background: #dbeafe; color: #1e40af; }
        .sq-badge-completed { background: #dcfce7; color: #166534; }
        .sq-badge-failed { background: #fee2e2; color: #991b1b; }
        .sq-badge-pending { background: #fef9c3; color: #854d0e; }
        .sq-btn-reset { background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.875rem; }
        .sq-btn-reset:hover { background: #dc2626; }
    </style>

    @if(session('message'))
        <p style="color: #166534; background: #dcfce7; padding: 10px; border-radius: 4px; margin-bottom: 15px;">{{ session('message') }}</p>
    @endif
    
    @if(session('errorMessage'))
        <p style="color: #991b1b; background: #fee2e2; padding: 10px; border-radius: 4px; margin-bottom: 15px;">{{ session('errorMessage') }}</p>
    @endif
    
    <table class="sq-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Last Message</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($jobs as $job)
                <tr>
                    <td>#{{ $job->id }}</td>
                    <td>{{ $job->type }}</td>
                    <td>
                        <span class="sq-badge sq-badge-{{ $job->status }}">
                            {{ $job->status }}
                        </span>
                        @if($job->isStale())
                            <span style="color: #ef4444; font-size: 0.8rem; font-weight: bold; margin-left: 5px;" title="This job has not been updated in over 15 minutes and might be stuck.">
                                ⚠️ Stuck
                            </span>
                        @endif
                    </td>
                    <td>{{ $job->current_step }} / {{ $job->total_steps }}</td>
                    <td>{{ $job->message }}</td>
                    <td>{{ $job->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>
                        @if(in_array($job->status, ['pending', 'running']))
                            <form action="{{ route('shared-queue.reset', $job) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="sq-btn-reset">Reset</button>
                            </form>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No background jobs tracked.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div style="margin-top: 20px;">
        {{ $jobs->links() }}
    </div>
</div>
