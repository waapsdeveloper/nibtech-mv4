<div>
    <!-- Top Bar: Search and Per Page -->
    <div class="d-flex justify-content-between mb-3">
        <input type="text" class="form-control w-50" placeholder="Search..." wire:model.debounce.300ms="search" />
        <select class="form-select w-auto" wire:model="perPage">
            @foreach([10, 25, 50, 100] as $count)
                <option value="{{ $count }}">{{ $count }} per page</option>
            @endforeach
        </select>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th width="30"><input type="checkbox" wire:model="selectPage"></th>
                    @foreach($columns as $col)
                        <th wire:click="sortBy('{{ $col['field'] }}')" style="cursor: pointer;">
                            {{ $col['label'] }}
                            @if($sortField === $col['field'])
                                <i class="fa fa-sort-{{ $sortDirection == 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </th>
                    @endforeach
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td><input type="checkbox" wire:model="selected" value="{{ $row->id }}"></td>
                        @foreach($columns as $col)
                            <td>{{ data_get($row, $col['field']) }}</td>
                        @endforeach
                        <td>
                            <button class="btn btn-sm btn-info" wire:click="$emit('viewItem', {{ $row->id }})">View</button>
                            <button class="btn btn-sm btn-danger" wire:click="$emit('deleteItem', {{ $row->id }})">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 2 }}" class="text-center text-muted">No results found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center">
        <div>
            Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} entries
        </div>
        <div>
            {{ $rows->links() }}
        </div>
    </div>
</div>
