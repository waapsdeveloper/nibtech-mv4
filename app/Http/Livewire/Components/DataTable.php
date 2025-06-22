<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;

class DataTable extends Component
{
    use WithPagination;

    public $model;
    public $columns = [];
    public $search = '';
    public $sortField = 'id';
    public $sortDirection = 'desc';
    public $perPage = 10;
    public $selected = [];

    protected $paginationTheme = 'bootstrap';

    public function mount($model, $columns)
    {
        $this->model = $model;
        $this->columns = $columns;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
    {
        $query = $this->model::query();

        if ($this->search) {
            $query->where(function ($q) {
                foreach ($this->columns as $col) {
                    $q->orWhere($col['field'], 'like', '%' . $this->search . '%');
                }
            });
        }

        $data = $query->orderBy($this->sortField, $this->sortDirection)
                      ->paginate($this->perPage);

        return view('livewire.data-table', [
            'rows' => $data,
        ]);
    }
}
