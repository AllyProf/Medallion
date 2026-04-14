@extends('layouts.dashboard')

@section('title', 'Food Menu')

@section('content')
    <div class="app-title">
        <div>
            <h1><i class="fa fa-cutlery"></i> Food Menu Management</h1>
            <p>Register and manage food items and extras</p>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item">Food Menu</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="tile-title">Available Menus</h3>
                    <a href="{{ route('bar.food.create') }}" class="btn btn-primary">
                        <i class="fa fa-plus"></i> Register New Menu
                    </a>
                </div>

                <!-- Real-time Filter Section -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-primary text-white"><i class="fa fa-search"></i></span>
                            </div>
                            <input type="text" id="food-search" class="form-control form-control-lg" placeholder="Search by food name or variant..." style="border-left: none;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select id="category-filter" class="form-control form-control-lg">
                            <option value="all">All Categories</option>
                            @php
                                $categories = $foodItems->pluck('category')->unique()->filter()->sort();
                            @endphp
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ \Illuminate\Support\Str::title($cat) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="tile-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead>
                                <tr>
                                    <th width="80">Image</th>
                                    <th>Name</th>
                                    <th>Variant</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Extras</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="food-table-body">
                                @forelse($foodItems as $item)
                                    <tr class="food-row" data-name="{{ strtolower($item->name . ' ' . ($item->variant_name ?? '')) }}" data-category="{{ $item->category ?? 'uncategorized' }}">
                                        <td>
                                            @if($item->image)
                                                <img src="{{ asset('storage/' . $item->image) }}" width="60" class="rounded">
                                            @else
                                                <div class="bg-light d-flex align-items-center justify-content-center rounded"
                                                    style="width: 60px; height: 60px;">
                                                    <i class="fa fa-image text-muted"></i>
                                                </div>
                                            @endif
                                        </td>
                                        <td><strong>{{ $item->name }}</strong></td>
                                        <td>{{ $item->variant_name ?? '-' }}</td>
                                        <td>{{ $item->category ?? '-' }}</td>
                                        <td class="price-cell" data-id="{{ $item->id }}" data-price="{{ (int)$item->price }}">
                                            <span class="price-display">{{ number_format($item->price) }} TZS</span>
                                            <button class="btn btn-sm btn-link text-primary p-0 ml-1 btn-edit-price" title="Quick Edit Price">
                                                <i class="fa fa-pencil"></i>
                                            </button>
                                            <div class="price-input-group d-none mt-1">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" class="form-control price-input" value="{{ (int)$item->price }}" style="width: 80px;">
                                                    <div class="input-group-append">
                                                        <button class="btn btn-success btn-save-price" type="button">
                                                            <i class="fa fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-secondary btn-cancel-price" type="button">
                                                            <i class="fa fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($item->extras->count() > 0)
                                                <ul class="list-unstyled mb-0">
                                                    @foreach($item->extras as $extra)
                                                        <li><small>• {{ $extra->name }} (+{{ number_format($extra->price) }})</small>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-muted"><small>No extras</small></span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $item->is_available ? 'badge-success' : 'badge-danger' }}">
                                                {{ $item->is_available ? 'Available' : 'Out of Stock' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="{{ route('bar.food.edit', $item) }}" class="btn btn-sm btn-info">
                                                    <i class="fa fa-pencil"></i>
                                                </a>
                                                <form id="delete-form-{{ $item->id }}" action="{{ route('bar.food.destroy', $item) }}" method="POST" style="display:inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-sm btn-danger btn-delete-food"
                                                        data-id="{{ $item->id }}"
                                                        data-name="{{ $item->name }}">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="empty-results" style="display: none;">
                                        <td colspan="8" class="text-center">No menus found matching your criteria.</td>
                                    </tr>
                                    <tr id="initial-empty">
                                        <td colspan="8" class="text-center">No menus registered yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $foodItems->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Quick Edit Price Toggle
    $(document).on('click', '.btn-edit-price', function () {
        const cell = $(this).closest('.price-cell');
        cell.find('.price-display, .btn-edit-price').addClass('d-none');
        cell.find('.price-input-group').removeClass('d-none');
        cell.find('.price-input').focus();
    });

    // Cancel Price Edit
    $(document).on('click', '.btn-cancel-price', function () {
        const cell = $(this).closest('.price-cell');
        cell.find('.price-display, .btn-edit-price').removeClass('d-none');
        cell.find('.price-input-group').addClass('d-none');
    });

    // Save Price Edit
    $(document).on('click', '.btn-save-price', function () {
        const cell = $(this).closest('.price-cell');
        const id = cell.data('id');
        const newPrice = cell.find('.price-input').val();
        const btn = $(this);

        if (!newPrice || newPrice < 0) {
            Swal.fire('Error', 'Please enter a valid price', 'error');
            return;
        }

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: "{{ route('bar.food.update-price') }}",
            method: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                id: id,
                price: newPrice
            },
            success: function (response) {
                if (response.success) {
                    cell.find('.price-display').text(response.new_price);
                    cell.data('price', newPrice);
                    cell.find('.price-display, .btn-edit-price').removeClass('d-none');
                    cell.find('.price-input-group').addClass('d-none');
                    
                    $.notify({
                        title: "Success: ",
                        message: response.message,
                        icon: 'fa fa-check'
                    }, {
                        type: "success"
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function (xhr) {
                Swal.fire('Error', 'Failed to update price', 'error');
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="fa fa-check"></i>');
            }
        });
    });

    $(document).on('click', '.btn-delete-food', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');

        Swal.fire({
            title: 'Delete "' + name + '"?',
            text: 'This will permanently remove the food item and all its extras.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-trash"></i> Yes, Delete',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                $('#delete-form-' + id).submit();
            }
        });
    });

    // Real-time Filtering Logic
    const foodSearch = $('#food-search');
    const categoryFilter = $('#category-filter');
    const foodRows = $('.food-row');
    const emptyResults = $('#empty-results');

    function performFilter() {
        const searchTerm = foodSearch.val().toLowerCase().trim();
        const selectedCategory = categoryFilter.val();
        let visibleCount = 0;

        foodRows.each(function() {
            const row = $(this);
            const name = row.data('name').toLowerCase();
            const category = row.data('category');

            const matchesSearch = name.includes(searchTerm);
            const matchesCategory = selectedCategory === 'all' || category === selectedCategory;

            if (matchesSearch && matchesCategory) {
                row.show();
                visibleCount++;
            } else {
                row.hide();
            }
        });

        if (visibleCount === 0 && foodRows.length > 0) {
            emptyResults.show();
        } else {
            emptyResults.hide();
        }
    }

    foodSearch.on('input', performFilter);
    categoryFilter.on('change', performFilter);
</script>
@endpush