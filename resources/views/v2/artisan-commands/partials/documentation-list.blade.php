{{-- Documentation List Section --}}
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

