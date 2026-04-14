@extends('layouts.dashboard')

@section('title', 'Edit Food Menu')

@section('content')
    <div class="app-title">
        <div>
            <h1><i class="fa fa-pencil"></i> Edit Food Menu</h1>
            <p>Modify existing menu item and its extras</p>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
            <li class="breadcrumb-item"><a href="{{ route('bar.food.index') }}">Food Menu</a></li>
            <li class="breadcrumb-item">Edit</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="tile">
                <h3 class="tile-title">Edit Menu: {{ $food->name }}</h3>
                <div class="tile-body">
                    <form action="{{ route('bar.food.update', $food) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Food Name <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" name="name" value="{{ $food->name }}"
                                        required>
                                </div>
                            </div>
                             <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Variant / Size</label>
                                    <input class="form-control" type="text" name="variant_name"
                                        value="{{ $food->variant_name }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Category <span class="text-danger">*</span></label>
                                    <select name="category" class="form-control" required>
                                        <option value="">-- Select Category --</option>
                                        <option value="Main Course" {{ $food->category == 'Main Course' ? 'selected' : '' }}>Main Course</option>
                                        <option value="Fast Food" {{ $food->category == 'Fast Food' ? 'selected' : '' }}>Fast Food</option>
                                        <option value="Appetizers" {{ $food->category == 'Appetizers' ? 'selected' : '' }}>Appetizers</option>
                                        <option value="Soups & Stews" {{ $food->category == 'Soups & Stews' ? 'selected' : '' }}>Soups & Stews</option>
                                        <option value="Desserts" {{ $food->category == 'Desserts' ? 'selected' : '' }}>Desserts</option>
                                        <option value="Breakfast" {{ $food->category == 'Breakfast' ? 'selected' : '' }}>Breakfast</option>
                                        <option value="Local Dishes" {{ $food->category == 'Local Dishes' ? 'selected' : '' }}>Local Dishes</option>
                                        <option value="Other" {{ $food->category == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Status</label>
                                    <select name="is_available" class="form-control">
                                        <option value="1" {{ $food->is_available ? 'selected' : '' }}>Available</option>
                                        <option value="0" {{ !$food->is_available ? 'selected' : '' }}>Unavailable</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label">Description</label>
                            <textarea class="form-control" name="description"
                                rows="3">{{ $food->description }}</textarea>
                        </div>

                        <div class="row">
                             <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Selling Price (TZS) <span
                                            class="text-danger">*</span></label>
                                    <input class="form-control" type="number" name="price"
                                        value="{{ (int) $food->price }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Prep Time (Minutes)</label>
                                    <input class="form-control" type="number" name="prep_time_minutes"
                                        value="{{ $food->prep_time_minutes }}">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label">Update Food Image</label>
                            @if($food->image)
                                <div class="mb-2">
                                    <img src="{{ asset('storage/' . $food->image) }}" width="100" class="rounded">
                                </div>
                            @endif
                            <input class="form-control" type="file" name="image">
                        </div>

                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0">Extras / Add-ons</h4>
                            <button type="button" class="btn btn-sm btn-info" id="add-extra">
                                <i class="fa fa-plus"></i> Add Extra
                            </button>
                        </div>

                        <div id="extras-container">
                            @foreach($food->extras as $index => $extra)
                                <div class="row extra-row mb-3 align-items-center bg-light p-2 rounded mx-0">
                                    <input type="hidden" name="extras[{{ $index }}][id]" value="{{ $extra->id }}">
                                    <div class="col-md-4">
                                        <label class="small font-weight-bold text-uppercase text-muted mb-1">Extra Name</label>
                                        <input type="text" name="extras[{{ $index }}][name]" class="form-control"
                                            value="{{ $extra->name }}" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small font-weight-bold text-uppercase text-muted mb-1">Price (TZS)</label>
                                        <input type="number" name="extras[{{ $index }}][price]" class="form-control extra-price {{ (int)$extra->price == 0 ? 'bg-light' : '' }}"
                                            value="{{ (int) $extra->price }}" {{ (int)$extra->price == 0 ? 'readonly' : '' }} required>
                                    </div>
                                    <div class="col-md-2 text-center pt-3">
                                        <div class="custom-control custom-checkbox mt-2">
                                            <input type="checkbox" class="custom-control-input is-free-toggle" id="free_check_edit_{{ $index }}" {{ (int)$extra->price == 0 ? 'checked' : '' }}>
                                            <label class="custom-control-label font-weight-bold" for="free_check_edit_{{ $index }}">FREE</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small font-weight-bold text-uppercase text-muted mb-1">Status</label>
                                        <select name="extras[{{ $index }}][is_available]" class="form-control">
                                            <option value="1" {{ $extra->is_available ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ !$extra->is_available ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 pt-3">
                                        <button type="button" class="btn btn-danger btn-block remove-extra mt-2 shadow-sm">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="tile-footer">
                            <button class="btn btn-primary" type="submit"><i
                                    class="fa fa-fw fa-lg fa-check-circle"></i>Update Menu</button>
                            <a class="btn btn-secondary" href="{{ route('bar.food.index') }}"><i
                                    class="fa fa-fw fa-lg fa-times-circle"></i>Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function () {
                let extraIndex = {{ $food->extras->count() }};

                $('#add-extra').click(function () {
                    const html = `
                        <div class="row extra-row mb-3 align-items-center bg-light p-2 rounded mx-0 border-primary" style="border-left: 4px solid #009688;">
                            <div class="col-md-4">
                                <label class="small font-weight-bold text-uppercase text-muted mb-1">Extra Name</label>
                                <input type="text" name="extras[${extraIndex}][name]" class="form-control" placeholder="e.g. Extra Sauce" required>
                            </div>
                            <div class="col-md-2">
                                <label class="small font-weight-bold text-uppercase text-muted mb-1">Price (TZS)</label>
                                <input type="number" name="extras[${extraIndex}][price]" class="form-control extra-price" placeholder="0" required>
                            </div>
                            <div class="col-md-2 text-center pt-3">
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input is-free-toggle" id="free_check_new_${extraIndex}">
                                    <label class="custom-control-label font-weight-bold" for="free_check_new_${extraIndex}">FREE</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="small font-weight-bold text-uppercase text-muted mb-1">Status</label>
                                <select name="extras[${extraIndex}][is_available]" class="form-control">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2 pt-3">
                                <button type="button" class="btn btn-danger btn-block remove-extra mt-2 shadow-sm">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    $('#extras-container').append(html);
                    extraIndex++;
                });

                $(document).on('change', '.is-free-toggle', function () {
                    const row = $(this).closest('.extra-row');
                    const priceInput = row.find('.extra-price');
                    if ($(this).is(':checked')) {
                        priceInput.val(0).prop('readonly', true).addClass('bg-light');
                    } else {
                        priceInput.prop('readonly', false).removeClass('bg-light').focus();
                    }
                });

                $(document).on('click', '.remove-extra', function () {
                    $(this).closest('.extra-row').remove();
                });
            });
        </script>
    @endpush
@endsection