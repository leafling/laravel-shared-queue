@if($job && in_array($job->status, ['pending', 'running']))
    <div id="shared-queue-watcher-{{ $job->id }}" class="shared-queue-watcher-container" style="margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: #fafafa;">
        <div style="font-weight: 600; margin-bottom: 8px;">
            Import Status: <span class="progress-status-label">{{ ucfirst($job->status) }}</span>
        </div>
        
        <div class="progress-bar-wrapper" style="background: #e5e7eb; border-radius: 9999px; overflow: hidden; height: 16px; margin-bottom: 8px;">
            <div class="progress-bar-fill" style="width: {{ $job->total_steps > 0 ? ($job->current_step / $job->total_steps * 100) : 0 }}%; background: #3b82f6; height: 100%; transition: width 0.4s ease;"></div>
        </div>
        
        <div class="progress-status-message" style="font-size: 0.875rem; color: #4b5563;">
            {{ $job->message ?? 'Initializing...' }}
        </div>
        
        <script>
            (function() {
                const jobId = "{{ $job->id }}";
                const container = document.getElementById(`shared-queue-watcher-${jobId}`);
                const fill = container.querySelector('.progress-bar-fill');
                const label = container.querySelector('.progress-status-label');
                const message = container.querySelector('.progress-status-message');
                
                const pollInterval = setInterval(async () => {
                    try {
                        const response = await fetch("{{ route('shared-queue.status', $job->id) }}");
                        const data = await response.json();
                        
                        // Update progress bar width
                        const percent = data.total_steps > 0 ? (data.current_step / data.total_steps * 100) : 0;
                        fill.style.width = `${percent}%`;
                        
                        // Update messages
                        label.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                        message.textContent = data.message || 'Processing...';
                        
                        if (['completed', 'failed'].includes(data.status)) {
                            clearInterval(pollInterval);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    } catch (e) {
                        console.error('Failed to poll shared queue job status:', e);
                    }
                }, 2000);
            })();
        </script>
    </div>
@endif
