{{-- Commands List Section --}}
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fe fe-terminal me-2"></i>V2 Artisan Commands
        </h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fe fe-info me-2"></i>
            <strong>Guide:</strong> Use these commands to test and manage V2 marketplace synchronization. 
            Commands can be executed directly from this interface for testing purposes.
        </div>

        @foreach($commands as $command)
        <div class="card command-card mb-4" style="{{ isset($command['warning']) && $command['warning'] ? 'border-left-color: #dc3545;' : '' }}">
            <div class="card-header {{ isset($command['warning']) && $command['warning'] ? 'bg-danger text-white' : 'bg-light' }}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 fw-bold">
                            <code>{{ $command['signature'] }}</code>
                        </h6>
                        <p class="mb-0 {{ isset($command['warning']) && $command['warning'] ? 'text-white' : 'text-muted' }} small">{{ $command['description'] }}</p>
                    </div>
                    <span class="badge {{ isset($command['warning']) && $command['warning'] ? 'bg-warning text-dark' : 'bg-primary' }}">{{ $command['category'] }}</span>
                </div>
            </div>
            <div class="card-body">
                {{-- Emergency Warning --}}
                @if(isset($command['warning']) && $command['warning'])
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading">
                        <i class="fe fe-alert-triangle me-2"></i>EMERGENCY COMMAND WARNING
                    </h5>
                    <p class="mb-0">
                        <strong>{{ $command['warning_message'] ?? 'This is a destructive operation that cannot be easily undone!' }}</strong>
                    </p>
                    <hr>
                    <p class="mb-0 small">
                        <strong>What this command does:</strong><br>
                        • Syncs parent stock (variation.listed_stock) to Backmarket (marketplace_id = 1)<br>
                        • Sets all other marketplaces' listed_stock to 0<br>
                        • This will overwrite existing marketplace stock values
                    </p>
                    <p class="mb-0 mt-2 small">
                        <strong>Recommendation:</strong> Always run with <code>--dry-run</code> first to see what will be changed!
                    </p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif
                {{-- Log file link (e.g. for reports that write to a specific log) --}}
                @if(!empty($command['log_file']))
                <div class="mb-3">
                    <a href="{{ route('v2.logs.log-file', ['file' => $command['log_file']]) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                        <i class="fe fe-file-text me-1"></i>View report log ({{ $command['log_file'] }})
                    </a>
                </div>
                @endif
                {{-- Documentation Links --}}
                @if(!empty($command['docs']))
                <div class="mb-3">
                    <strong class="small">Documentation:</strong>
                    @foreach($command['docs'] as $doc)
                        <a href="javascript:void(0);" class="doc-link ms-2" onclick="showDocumentation('{{ $doc }}')">
                            <i class="fe fe-file-text me-1"></i>{{ str_replace('.md', '', $doc) }}
                        </a>
                    @endforeach
                </div>
                @endif

                {{-- Command Options Form --}}
                <form class="command-form" data-command="{{ $command['signature'] }}">
                    <div class="row mb-3">
                        @foreach($command['options'] ?? [] as $optionKey => $option)
                        <div class="col-md-6 mb-2">
                            <label class="form-label small">{{ $option['label'] }}</label>
                            @if($option['type'] === 'select')
                                <select name="{{ $optionKey }}" class="form-control form-control-sm" 
                                        value="{{ $option['default'] ?? '' }}">
                                    @foreach($option['options'] as $value => $label)
                                        <option value="{{ $value }}" {{ ($option['default'] ?? '') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif($option['type'] === 'checkbox')
                                <div class="form-check">
                                    <input type="checkbox" 
                                           name="{{ $optionKey }}" 
                                           class="form-check-input" 
                                           id="{{ $optionKey }}_{{ $loop->parent->index }}"
                                           value="1">
                                    <label class="form-check-label small" for="{{ $optionKey }}_{{ $loop->parent->index }}">
                                        {{ $option['description'] ?? '' }}
                                    </label>
                                </div>
                            @else
                                <input type="{{ $option['type'] }}" 
                                       name="{{ $optionKey }}" 
                                       class="form-control form-control-sm" 
                                       placeholder="{{ $option['placeholder'] ?? '' }}"
                                       value="{{ $option['default'] ?? '' }}">
                            @endif
                        </div>
                        @endforeach
                    </div>

                    {{-- Examples --}}
                    @if(!empty($command['examples']))
                    <div class="mb-3">
                        <strong class="small">Examples:</strong>
                        <div class="mt-2">
                            @foreach($command['examples'] as $example)
                            <code class="d-block mb-1 small bg-light p-2">{{ $example }}</code>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fe fe-play me-1"></i>Execute Command
                    </button>
                </form>

                {{-- Output Area --}}
                <div class="command-output-container mt-3" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">Output:</strong>
                        <button class="btn btn-sm btn-link text-muted" onclick="clearOutput(this)">
                            <i class="fe fe-x"></i> Clear
                        </button>
                    </div>
                    <div class="command-output p-3 rounded" style="display: none;"></div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

