@extends('layouts.app')

@section('styles')
<style>
    .command-card {
        transition: all 0.3s ease;
        border-left: 4px solid #007bff;
    }
    .command-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .command-output {
        background: #1e1e1e;
        color: #d4d4d4;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        max-height: 400px;
        overflow-y: auto;
    }
    .doc-link {
        color: #007bff;
        text-decoration: none;
    }
    .doc-link:hover {
        text-decoration: underline;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">V2 Artisan Commands Guide</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item active" aria-current="page">Artisan Commands</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
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
                    <div class="card command-card mb-4">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold">
                                        <code>{{ $command['signature'] }}</code>
                                    </h6>
                                    <p class="mb-0 text-muted small">{{ $command['description'] }}</p>
                                </div>
                                <span class="badge bg-primary">{{ $command['category'] }}</span>
                            </div>
                        </div>
                        <div class="card-body">
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

            {{-- Documentation List --}}
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fe fe-book me-2"></i>Documentation Files
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($docs as $doc)
                        <div class="col-md-4 mb-2">
                            <a href="javascript:void(0);" class="doc-link" onclick="showDocumentation('{{ $doc['filename'] }}')">
                                <i class="fe fe-file-text me-1"></i>{{ $doc['name'] }}
                            </a>
                            <small class="text-muted d-block">{{ number_format($doc['size'] / 1024, 2) }} KB</small>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Documentation Modal --}}
            <div class="modal fade" id="documentationModal" tabindex="-1" aria-labelledby="documentationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="documentationModalLabel">
                                <i class="fe fe-file-text me-2"></i>Documentation
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="documentationContent" class="markdown-content" style="max-height: 70vh; overflow-y: auto;">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Handle command form submission
document.querySelectorAll('.command-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const command = this.dataset.command;
        const formData = new FormData(this);
        const options = {};
        
        // Build options object - only include non-empty values
        formData.forEach((value, key) => {
            if (value !== null && value !== '' && value !== '0') {
                // Convert string numbers to actual numbers if needed
                if (!isNaN(value) && value !== '') {
                    options[key] = isNaN(parseFloat(value)) ? value : parseFloat(value);
                } else {
                    options[key] = value;
                }
            }
        });

        // Show output container
        const outputContainer = this.closest('.card-body').querySelector('.command-output-container');
        const outputDiv = outputContainer.querySelector('.command-output');
        outputContainer.style.display = 'block';
        outputDiv.style.display = 'block';
        outputDiv.innerHTML = '<div class="text-info">Executing command...</div>';

        // Execute command
        fetch('{{ url("v2/artisan-commands/execute") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                command: command,
                options: options
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                outputDiv.innerHTML = '<div class="text-success mb-2">✓ Command executed successfully</div>' +
                                     '<div class="text-muted small mb-2">Command: <code>' + data.command + '</code></div>' +
                                     '<pre class="mb-0">' + escapeHtml(data.output || 'No output') + '</pre>';
            } else {
                outputDiv.innerHTML = '<div class="text-danger mb-2">✗ Command failed</div>' +
                                     '<div class="text-muted small mb-2">Command: <code>' + data.command + '</code></div>' +
                                     '<pre class="mb-0 text-danger">' + escapeHtml(data.error || 'Unknown error') + '</pre>';
            }
        })
        .catch(error => {
            outputDiv.innerHTML = '<div class="text-danger">Error: ' + escapeHtml(error.message) + '</div>';
        });
    });
});

// Show documentation
function showDocumentation(filename) {
    const modal = new bootstrap.Modal(document.getElementById('documentationModal'));
    const contentDiv = document.getElementById('documentationContent');
    
    contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>';
    modal.show();

    fetch('{{ url("v2/artisan-commands/documentation") }}?file=' + encodeURIComponent(filename))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Convert markdown to HTML
                let html = '<div class="mb-3">';
                html += '<h4 class="mb-3">' + data.filename.replace('.md', '').replace(/_/g, ' ') + '</h4>';
                html += '<hr class="mb-4">';
                html += convertMarkdownToHtml(data.content);
                html += '</div>';
                contentDiv.innerHTML = html;
            } else {
                contentDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(error => {
            contentDiv.innerHTML = '<div class="alert alert-danger">Error loading documentation: ' + error.message + '</div>';
        });
}

// Clear output
function clearOutput(btn) {
    const outputDiv = btn.closest('.command-output-container').querySelector('.command-output');
    outputDiv.innerHTML = '';
    outputDiv.style.display = 'none';
    btn.closest('.command-output-container').style.display = 'none';
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Simple markdown to HTML converter
function convertMarkdownToHtml(markdown) {
    let html = markdown;
    
    // Code blocks (do first to avoid conflicts)
    html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre class="bg-dark text-light p-3 rounded"><code>$2</code></pre>');
    html = html.replace(/```([\s\S]*?)```/g, '<pre class="bg-dark text-light p-3 rounded"><code>$1</code></pre>');
    
    // Inline code
    html = html.replace(/`([^`\n]+)`/g, '<code class="bg-light px-1 rounded">$1</code>');
    
    // Headers
    html = html.replace(/^#### (.*$)/gim, '<h4 class="mt-4 mb-2">$1</h4>');
    html = html.replace(/^### (.*$)/gim, '<h3 class="mt-4 mb-2">$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2 class="mt-4 mb-3">$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1 class="mt-4 mb-3">$1</h1>');
    
    // Bold and italic
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Unordered lists
    html = html.replace(/^[\*\-] (.*$)/gim, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ul class="mb-3">$1</ul>');
    
    // Ordered lists
    html = html.replace(/^\d+\. (.*$)/gim, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ol class="mb-3">$1</ol>');
    
    // Links
    html = html.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
    
    // Horizontal rules
    html = html.replace(/^---$/gim, '<hr>');
    html = html.replace(/^\*\*\*$/gim, '<hr>');
    
    // Tables (basic)
    html = html.replace(/\|(.+)\|/g, function(match, content) {
        const cells = content.split('|').map(c => c.trim()).filter(c => c);
        return '<tr>' + cells.map(c => '<td>' + c + '</td>').join('') + '</tr>';
    });
    
    // Line breaks
    html = html.replace(/\n\n/g, '</p><p class="mb-2">');
    html = '<div class="markdown-body"><p class="mb-2">' + html + '</p></div>';
    
    return html;
}
</script>
@endsection

