<li class="nav nav-item nav-link ps-lg-2 mx-4" wire:poll.20s="refreshCount">
    <a class="nav-link nav-link-bg position-relative" data-bs-toggle="sidebar-right" data-bs-target=".sidebar-right">
        <i class="fe fe-align-right header-icon-svgs"></i>
        @if($count > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; min-width: 1.2rem;">
                {{ $count > 99 ? '99+' : $count }}
            </span>
        @endif
    </a>
</li>
