<div style="position: fixed; bottom: 0; left: 0; right: 0; background: #1f2937; color: white; padding: 1rem; max-height: 400px; overflow-y: auto; z-index: 9999; font-family: monospace; font-size: 12px; border-top: 3px solid #ef4444;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <strong style="font-size: 14px;">🔍 Debug Info - {{ $viewName }}</strong>
        <button onclick="this.parentElement.parentElement.style.display='none'" style="background: #ef4444; color: white; border: none; padding: 0.25rem 0.5rem; cursor: pointer; border-radius: 3px;">Close</button>
    </div>

    <div style="display: grid; gap: 0.5rem;">
        @php
            $grouped = collect($issues)->groupBy('type');
        @endphp

        @foreach($grouped as $type => $typeIssues)
            <div style="background: #374151; padding: 0.5rem; border-radius: 4px;">
                <div style="font-weight: bold; color: #fbbf24; margin-bottom: 0.25rem;">
                    {{ ucfirst(str_replace('_', ' ', $type)) }} ({{ count($typeIssues) }})
                </div>
                @foreach($typeIssues as $issue)
                    <div style="padding: 0.5rem; background: #1f2937; margin-bottom: 0.5rem; border-left: 3px solid #ef4444;">
                        <div style="color: #fff;">{{ $issue['message'] }}</div>

                        @if(!empty($issue['context']))
                            @if(isset($issue['context']['code']))
                                <pre style="background: #000; padding: 0.5rem; margin-top: 0.5rem; overflow-x: auto; font-size: 10px; color: #0f0;">{{ $issue['context']['code'] }}</pre>
                            @endif

                            <div style="font-size: 10px; color: #9ca3af; margin-top: 0.25rem;">
                                @foreach($issue['context'] as $key => $value)
                                    @if($key !== 'code')
                                        <div><strong>{{ $key }}:</strong> {{ is_string($value) ? $value : json_encode($value) }}</div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
