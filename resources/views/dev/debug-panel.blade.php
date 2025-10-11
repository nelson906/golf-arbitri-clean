<div style="position: fixed; bottom: 0; left: 0; right: 0; background: #1f2937; color: white; padding: 1rem; max-height: 300px; overflow-y: auto; z-index: 9999; font-family: monospace; font-size: 12px; border-top: 3px solid #ef4444;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <strong style="font-size: 14px;">🔍 Debug Info - {{ $viewName }}</strong>
        <button onclick="this.parentElement.parentElement.style.display='none'" style="background: #ef4444; color: white; border: none; padding: 0.25rem 0.5rem; cursor: pointer; border-radius: 3px;">Close</button>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 0.5rem;">
        @php
            $grouped = collect($issues)->groupBy('type');
        @endphp

        @foreach($grouped as $type => $typeIssues)
            <div style="background: #374151; padding: 0.5rem; border-radius: 4px;">
                <div style="font-weight: bold; color: #fbbf24; margin-bottom: 0.25rem;">
                    {{ ucfirst(str_replace('_', ' ', $type)) }} ({{ count($typeIssues) }})
                </div>
                @foreach($typeIssues as $issue)
                    <div style="padding: 0.25rem; background: #1f2937; margin-bottom: 0.25rem; border-left: 2px solid #ef4444;">
                        <div>{{ $issue['message'] }}</div>
                        @if(!empty($issue['context']))
                            <div style="font-size: 10px; color: #9ca3af; margin-top: 0.125rem;">
                                {{ json_encode($issue['context']) }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
