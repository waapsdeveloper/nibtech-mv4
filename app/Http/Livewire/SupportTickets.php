<?php

namespace App\Http\Livewire;

use App\Models\Admin_model;
use App\Models\Marketplace_model;
use App\Models\SupportTag;
use App\Models\SupportThread;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class SupportTickets extends Component
{
    use WithPagination;

    public $search = '';
    public $status = '';
    public $priority = '';
    public $marketplace = '';
    public $tag = '';
    public $assigned = '';
    public $changeOnly = false;
    public $perPage = 25;
    public $selectedThreadId;
    public $sortField = 'last_external_activity_at';
    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'priority' => ['except' => ''],
        'marketplace' => ['except' => ''],
        'tag' => ['except' => ''],
        'assigned' => ['except' => ''],
        'changeOnly' => ['except' => false],
        'perPage' => ['except' => 25],
        'sortField' => ['except' => 'last_external_activity_at'],
        'sortDirection' => ['except' => 'desc'],
        'page' => ['except' => 1],
    ];

    protected $listeners = ['supportThreadsUpdated' => '$refresh'];

    public function mount(): void
    {
        $this->perPage = $this->sanitizePerPage($this->perPage);
        $this->sortField = $this->sanitizeSortField($this->sortField);
        $this->sortDirection = $this->sanitizeSortDirection($this->sortDirection);
    }

    public function updated($property, $value): void
    {
        if ($property === 'perPage') {
            $this->perPage = $this->sanitizePerPage($value);
        }

        if ($property === 'sortField') {
            $this->sortField = $this->sanitizeSortField($value);
        }

        if ($property === 'sortDirection') {
            $this->sortDirection = $this->sanitizeSortDirection($value);
        }

        $filters = ['search', 'status', 'priority', 'marketplace', 'tag', 'assigned', 'changeOnly', 'perPage', 'sortField', 'sortDirection'];

        if (in_array($property, $filters, true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->priority = '';
        $this->marketplace = '';
        $this->tag = '';
        $this->assigned = '';
        $this->changeOnly = false;
        $this->sortField = 'last_external_activity_at';
        $this->sortDirection = 'desc';
        $this->perPage = 25;
        $this->selectedThreadId = null;
        $this->resetPage();
    }

    public function selectThread(int $threadId): void
    {
        $this->selectedThreadId = $threadId;
    }

    public function render()
    {
        $threads = $this->threads;

        if ($threads->count() === 0) {
            $this->selectedThreadId = null;
        } elseif (! $this->selectedThreadId || ! $threads->pluck('id')->contains($this->selectedThreadId)) {
            $this->selectedThreadId = $threads->first()->id;
        }

        return view('livewire.support-tickets', [
            'threads' => $threads,
            'selectedThread' => $this->selectedThread,
            'statusOptions' => $this->distinctValues('status'),
            'priorityOptions' => $this->distinctValues('priority'),
            'marketplaceOptions' => Marketplace_model::orderBy('name')->pluck('name', 'id'),
            'tagOptions' => SupportTag::orderBy('name')->get(['id', 'name', 'color']),
            'assigneeOptions' => Admin_model::orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function getThreadsProperty(): LengthAwarePaginator
    {
        $sortable = ['last_external_activity_at', 'priority', 'status', 'created_at'];
        $sortField = in_array($this->sortField, $sortable, true)
            ? $this->sortField
            : 'last_external_activity_at';

        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return SupportThread::query()
            ->with([
                'tags',
                'order.customer',
                'marketplace',
                'assignee',
                'messages' => function ($query) {
                    $query->latest()->limit(1);
                },
            ])
            ->withCount('messages')
            ->when($this->search !== '', function ($query) {
                $needle = '%' . trim($this->search) . '%';
                $query->where(function ($sub) use ($needle) {
                    $sub->where('order_reference', 'like', $needle)
                        ->orWhere('buyer_email', 'like', $needle)
                        ->orWhere('buyer_name', 'like', $needle)
                        ->orWhere('external_thread_id', 'like', $needle);
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->priority !== '', fn ($query) => $query->where('priority', $this->priority))
            ->when($this->marketplace !== '', fn ($query) => $query->where('marketplace_id', $this->marketplace))
            ->when($this->assigned !== '', fn ($query) => $query->where('assigned_to', $this->assigned))
            ->when($this->changeOnly, fn ($query) => $query->where('change_of_mind', true))
            ->when($this->tag !== '', function ($query) {
                $query->whereHas('tags', fn ($tagQuery) => $tagQuery->where('support_tags.id', $this->tag));
            })
            ->orderBy($sortField, $sortDirection)
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function getSelectedThreadProperty(): ?SupportThread
    {
        if (! $this->selectedThreadId) {
            return null;
        }

        return SupportThread::with([
            'messages' => function ($query) {
                $query->reorder('sent_at')->orderBy('id');
            },
            'tags',
            'order.customer',
            'order.order_items',
            'marketplace',
            'assignee',
        ])->find($this->selectedThreadId);
    }

    protected function distinctValues(string $column)
    {
        return SupportThread::query()
            ->select($column)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }

    protected function sanitizePerPage($value): int
    {
        $value = (int) $value;
        if ($value < 10) {
            return 10;
        }

        if ($value > 100) {
            return 100;
        }

        return $value;
    }

    protected function sanitizeSortField(?string $field): string
    {
        $allowed = ['last_external_activity_at', 'priority', 'status', 'created_at'];

        return in_array($field, $allowed, true) ? $field : 'last_external_activity_at';
    }

    protected function sanitizeSortDirection(?string $direction): string
    {
        return $direction === 'asc' ? 'asc' : 'desc';
    }
}
