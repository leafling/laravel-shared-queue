@props(['job', 'steps' => null])

@if($job && in_array($job->status, ['pending', 'running']))
    <div id="shared-queue-watcher-{{ $job->id }}" class="shared-queue-watcher-container" style="margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: #fafafa;">
        <div style="font-weight: 600; margin-bottom: 8px;">
            Import Status: <span class="progress-status-label">{{ ucfirst($job->status) }}</span>
        </div>
        
        <div class="progress-bar-wrapper" style="background: #e5e7eb; border-radius: 9999px; overflow: hidden; height: 16px; margin-bottom: 8px;">
            <div class="progress-bar-fill" style="width: {{ $job->total_steps > 0 ? ($job->current_step / $job->total_steps * 100) : 0 }}%; background: #3b82f6; height: 100%; transition: width 0.4s ease;"></div>
        </div>
        
        <div class="progress-status-message" style="font-size: 0.875rem; color: #4b5563; margin-bottom: 12px;">
            {{ $job->message ?? 'Initializing...' }}
        </div>

        @if($steps)
            <div class="step-logs" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; font-size: 0.75rem; color: #4b5563;">
                @foreach($steps as $index => $stepLabel)
                    @php
                        $stepNumber = $index + 1;
                        $stepDetails = $job->step_details ?? [];
                        $isCompleted = isset($stepDetails['step_' . $stepNumber]);
                        $isActive = !$isCompleted && $job->current_step == $stepNumber;
                        $duration = $stepDetails['step_' . $stepNumber] ?? null;
                    @endphp
                    <div class="log-step-row" style="display: flex; justify-content: space-between; margin-bottom: 6px; transition: opacity 0.3s; {{ $isCompleted ? 'color: #16a34a; font-weight: 600;' : ($isActive ? 'color: #2563eb; animation: shared-queue-pulse 2s infinite;' : 'opacity: 0.5;') }}">
                        <span>{{ $stepLabel }}</span>
                        <span class="log-duration" style="font-family: monospace;">
                            @if($isCompleted)
                                Completed in {{ $duration }}s
                            @elseif($isActive)
                                Running...
                            @else
                                Pending...
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
        
        <script>
            (function() {
                const jobId = "{{ $job->id }}";
                const container = document.getElementById(`shared-queue-watcher-${jobId}`);
                const fill = container.querySelector('.progress-bar-fill');
                const label = container.querySelector('.progress-status-label');
                const message = container.querySelector('.progress-status-message');
                const hasSteps = {{ $steps ? 'true' : 'false' }};
                
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
                        
                        // Update steps if present
                        if (hasSteps) {
                            const details = data.step_details || {};
                            const currentStep = data.current_step || 0;
                            
                            // Find all rows
                            const rows = container.querySelectorAll('.log-step-row');
                            rows.forEach((row, idx) => {
                                const stepNum = idx + 1;
                                const durationSpan = row.querySelector('.log-duration');
                                
                                if (details['step_' + stepNum] !== undefined) {
                                    row.style.color = '#16a34a';
                                    row.style.fontWeight = '600';
                                    row.style.opacity = '1';
                                    row.style.animation = 'none';
                                    durationSpan.textContent = 'Completed in ' + details['step_' + stepNum] + 's';
                                } else if (currentStep === stepNum) {
                                    row.style.color = '#2563eb';
                                    row.style.fontWeight = 'normal';
                                    row.style.opacity = '1';
                                    row.style.animation = 'shared-queue-pulse 2s infinite';
                                    durationSpan.textContent = 'Running...';
                                } else {
                                    row.style.color = '';
                                    row.style.fontWeight = 'normal';
                                    row.style.opacity = '0.5';
                                    row.style.animation = 'none';
                                    durationSpan.textContent = 'Pending...';
                                }
                            });
                        }
                        
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
                
                // Add keyframe animation if not already defined
                if (!document.getElementById('shared-queue-pulse-style')) {
                    const style = document.createElement('style');
                    style.id = 'shared-queue-pulse-style';
                    style.innerHTML = `
                        @keyframes shared-queue-pulse {
                            0%, 100% { opacity: 1; }
                            50% { opacity: 0.5; }
                        }
                    `;
                    document.head.appendChild(style);
                }
            })();
        </script>
    </div>
@endif
