@props(['job' => null, 'steps' => null, 'trigger' => null, 'endpoint' => null, 'pollInterval' => 2000])

@php
    $isFinished = $job && in_array($job->status, ['completed', 'failed']);
    $stepDetails = $job->step_details ?? [];
    if (is_string($stepDetails)) {
        $stepDetails = json_decode($stepDetails, true) ?? [];
    }
    $completedCount = count($stepDetails);
    $totalSteps = $job->total_steps ?? ($steps ? count($steps) : 1);
    
    // Percentage is based on completed steps until the job is fully done
    $percent = $isFinished ? 100 : ($totalSteps > 0 ? min(95, max(5, round(($completedCount / $totalSteps) * 100))) : 5);
    if (!$job) $percent = 0;

    $barColor = $job && $job->status === 'completed' ? '#22c55e' : ($job && $job->status === 'failed' ? '#ef4444' : '#3b82f6');
    $labelColor = $job && $job->status === 'completed' ? '#166534' : ($job && $job->status === 'failed' ? '#991b1b' : '#1f2937');
    $containerId = 'shared-queue-watcher-' . ($job->id ?? 'ajax-' . uniqid());

    $rawMessage = $job->message ?? 'Initializing import job...';
    $customRenderer = config('shared-queue.markdown_renderer');
    if ($customRenderer && is_callable($customRenderer)) {
        $renderedMessage = $customRenderer($rawMessage);
    } else {
        $renderedMessage = e($rawMessage);
    }
@endphp

<div id="{{ $containerId }}" class="shared-queue-watcher-container" style="{{ $job ? 'display: block;' : 'display: none;' }} margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: #fafafa;">
    <div style="font-weight: 600; margin-bottom: 8px; color: {{ $labelColor }};">
        Import Status: <span class="progress-status-label">{{ ucfirst($job->status ?? 'Initializing') }}</span>
    </div>
    
    <div class="progress-bar-wrapper" style="background: #e5e7eb; border-radius: 9999px; overflow: hidden; height: 16px; margin-bottom: 8px;">
        <div class="progress-bar-fill" style="width: {{ $percent }}%; background: {{ $barColor }}; height: 100%; transition: width 0.5s ease-in-out;"></div>
    </div>
    
    <div class="progress-status-message" style="font-size: 0.875rem; color: #4b5563; margin-bottom: 12px;">
        {!! $renderedMessage !!}
    </div>

    @if($steps)
        <div class="step-logs" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; font-size: 0.75rem; color: #4b5563;">
            @foreach($steps as $index => $stepLabel)
                @php
                    $stepNumber = $index + 1;
                    $isCompleted = isset($stepDetails['step_' . $stepNumber]) || ($job && $job->status === 'completed');
                    $isActive = !$isFinished && !$isCompleted && $job && $job->current_step == $stepNumber;
                    $duration = $stepDetails['step_' . $stepNumber] ?? null;
                @endphp
                <div class="log-step-row" style="display: flex; justify-content: space-between; margin-bottom: 6px; transition: opacity 0.3s; {{ $isCompleted ? 'color: #16a34a; font-weight: 600;' : ($isActive ? 'color: #2563eb; animation: shared-queue-pulse 2s infinite;' : 'opacity: 0.5;') }}">
                    <span>{{ $stepLabel }}</span>
                    <span class="log-duration" style="font-family: monospace;">
                        @if($duration !== null)
                            Completed in {{ $duration }}s
                        @elseif($isCompleted)
                            Completed
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
            const containerId = "{{ $containerId }}";
            const container = document.getElementById(containerId);
            if (!container) return;

            const fill = container.querySelector('.progress-bar-fill');
            const label = container.querySelector('.progress-status-label');
            const message = container.querySelector('.progress-status-message');
            const hasSteps = {{ $steps ? 'true' : 'false' }};
            const triggerSelector = "{{ $trigger }}";
            const endpointUrl = "{{ $endpoint }}";
            
            const pollIntervalMs = {{ (int) $pollInterval }};
            let activePollInterval = null;

            function parseMarkdown(str) {
                if (!str) return '';
                let html = str.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" style="text-decoration: underline; font-weight: 600; color: #2563eb;">$1</a>');
                html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
                return html;
            }

            function startPolling(jobId) {
                if (activePollInterval) clearInterval(activePollInterval);
                
                container.style.display = 'block';

                activePollInterval = setInterval(async () => {
                    try {
                        const statusRoute = "{{ route('shared-queue.status', ':jobId') }}".replace(':jobId', jobId);
                        const response = await fetch(statusRoute + '?_t=' + Date.now(), {
                            headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' }
                        });
                        
                        if (!response.ok) return;
                        const data = await response.json();
                        
                        let details = data.step_details || {};
                        if (typeof details === 'string') {
                            try { details = JSON.parse(details); } catch(e) { details = {}; }
                        }
                        
                        const completedCount = Object.keys(details).length;
                        const totalSteps = data.total_steps || 1;
                        const isDone = ['completed', 'failed'].includes(data.status);
                        
                        // Progress calculation: Based on COMPLETED steps + active in-flight fraction
                        let percent = 5;
                        if (isDone) {
                            percent = 100;
                        } else if (totalSteps > 0) {
                            const completedPercent = (completedCount / totalSteps) * 100;
                            // Add small active progress bump for current running step (capped at 95%)
                            percent = Math.min(95, Math.max(5, Math.round(completedPercent + (100 / totalSteps * 0.25))));
                        }

                        if (fill) {
                            fill.style.width = `${percent}%`;
                            if (data.status === 'completed') fill.style.background = '#22c55e';
                            else if (data.status === 'failed') fill.style.background = '#ef4444';
                        }
                        
                        // Update status & message
                        if (label) label.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                        if (message && data.message) {
                            message.innerHTML = parseMarkdown(data.message);
                        }
                        
                        // Update step rows
                        if (hasSteps) {
                            const currentStep = data.current_step || 0;
                            const rows = container.querySelectorAll('.log-step-row');
                            rows.forEach((row, idx) => {
                                const stepNum = idx + 1;
                                const durationSpan = row.querySelector('.log-duration');
                                const stepKey = 'step_' + stepNum;
                                
                                if (details && details[stepKey] !== undefined) {
                                    row.style.color = '#16a34a';
                                    row.style.fontWeight = '600';
                                    row.style.opacity = '1';
                                    row.style.animation = 'none';
                                    if (durationSpan) durationSpan.textContent = 'Completed in ' + details[stepKey] + 's';
                                } else if (isDone || stepNum < currentStep) {
                                    row.style.color = '#16a34a';
                                    row.style.fontWeight = '600';
                                    row.style.opacity = '1';
                                    row.style.animation = 'none';
                                    if (durationSpan) durationSpan.textContent = 'Completed';
                                } else if (stepNum === currentStep && data.status === 'running') {
                                    row.style.color = '#2563eb';
                                    row.style.fontWeight = 'normal';
                                    row.style.opacity = '1';
                                    row.style.animation = 'shared-queue-pulse 2s infinite';
                                    if (durationSpan) durationSpan.textContent = 'Running...';
                                } else {
                                    row.style.color = '';
                                    row.style.fontWeight = 'normal';
                                    row.style.opacity = '0.5';
                                    row.style.animation = 'none';
                                    if (durationSpan) durationSpan.textContent = 'Pending...';
                                }
                            });
                        }
                        
                        if (isDone) {
                            clearInterval(activePollInterval);
                            setTimeout(() => {
                                window.location.reload();
                            }, 800);
                        }
                    } catch (e) {
                        console.error('Failed to poll shared queue job status:', e);
                    }
                }, pollIntervalMs);
            }

            // Bind trigger click if selector and endpoint are provided
            if (triggerSelector && endpointUrl) {
                document.addEventListener('DOMContentLoaded', () => {
                    const btn = document.querySelector(triggerSelector);
                    if (btn) {
                        btn.addEventListener('click', async (e) => {
                            e.preventDefault();
                            btn.classList.add('disabled', 'opacity-50', 'pointer-events-none');
                            
                            try {
                                container.style.display = 'block';
                                if (label) label.textContent = 'Starting...';
                                if (message) message.textContent = 'Initializing import job...';
                                if (fill) {
                                    fill.style.width = '5%';
                                    fill.style.background = '#3b82f6';
                                }

                                const response = await fetch(endpointUrl, {
                                    method: 'GET',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                });

                                const resData = await response.json();
                                if (resData.job_id) {
                                    startPolling(resData.job_id);
                                } else {
                                    if (message) message.textContent = resData.message || 'Failed to start import.';
                                    btn.classList.remove('disabled', 'opacity-50', 'pointer-events-none');
                                }
                            } catch (err) {
                                console.error('Failed to initiate import:', err);
                                if (message) message.textContent = 'Error starting import job.';
                                btn.classList.remove('disabled', 'opacity-50', 'pointer-events-none');
                            }
                        });
                    }
                });
            }

            // If an active job was rendered server-side, start polling immediately
            @if($job && in_array($job->status, ['pending', 'running']))
                startPolling({{ $job->id }});
            @endif

            // Add pulse keyframes if not defined
            if (!document.getElementById('shared-queue-pulse-style')) {
                const style = document.createElement('style');
                style.id = 'shared-queue-pulse-style';
                style.innerHTML = `@keyframes shared-queue-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }`;
                document.head.appendChild(style);
            }
        })();
    </script>
</div>
