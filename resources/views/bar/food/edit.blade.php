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
        <div class="col-md-12">
            <div class="tile shadow-sm border-0">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                    <h3 class="tile-title mb-0"><i class="fa fa-pencil mr-2 text-primary"></i> Edit Menu: {{ $food->name }}</h3>
                </div>
                
                <div class="tile-body">
                    <form action="{{ route('bar.food.update', $food) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold small text-uppercase">Food Name <span class="text-danger">*</span></label>
                                    <input class="form-control form-control-lg border-primary" type="text" name="name" value="{{ $food->name }}"
                                        required>
                                </div>
                            </div>
                             <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold small text-uppercase">Variant / Size</label>
                                    <input class="form-control form-control-lg" type="text" name="variant_name"
                                        value="{{ $food->variant_name }}" placeholder="e.g. Standard, Large">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                @php
                                    $predefined = ['Appetizers', 'Fast Food', 'Main Course', 'Grills & BBQ', 'Seafood', 'Soups & Stews', 'Rice & Pasta', 'Vegetarian', 'Salads', 'Desserts', 'Breakfast', 'Kids Menu', 'Specials'];
                                    $isCustom = !empty($food->category) && !in_array($food->category, $predefined);
                                @endphp
                                <div class="form-group">
                                    <label class="font-weight-bold small text-uppercase text-info">Category <span class="text-danger">*</span></label>
                                    <select id="category-select" class="form-control form-control-lg" required>
                                        <option value="">-- Select Category --</option>
                                        @foreach($predefined as $cat)
                                            <option value="{{ $cat }}" {{ (!$isCustom && $food->category == $cat) ? 'selected' : '' }}>{{ $cat }}</option>
                                        @endforeach
                                        <option value="other" {{ $isCustom ? 'selected' : '' }}>Other (Custom)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4" id="custom-category-wrapper" style="{{ $isCustom ? '' : 'display:none;' }}">
                                <div class="form-group">
                                    <label class="font-weight-bold small text-uppercase text-warning">Custom Category</label>
                                    <input class="form-control form-control-lg border-warning" type="text" id="custom-category" value="{{ $isCustom ? $food->category : '' }}" placeholder="Chef's Selection">
                                </div>
                            </div>
                            {{-- Actual field sent to server --}}
                            <input type="hidden" name="category" id="final-category" value="{{ $food->category }}">

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold small text-uppercase">Availability Status</label>
                                    <select name="is_available" class="form-control form-control-lg">
                                        <option value="1" {{ $food->is_available ? 'selected' : '' }}>Active & Available</option>
                                        <option value="0" {{ !$food->is_available ? 'selected' : '' }}>Out of Stock</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold small text-uppercase">Description</label>
                            <textarea class="form-control" name="description"
                                rows="3" placeholder="Explain the ingredients or taste...">{{ $food->description }}</textarea>
                        </div>

                        <div class="row alert alert-light mx-0 border py-4">
                             <div class="col-md-6 border-right">
                                <div class="form-group">
                                    <label class="font-weight-bold small text-uppercase text-primary">Selling Price (TZS) <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text font-weight-bold">TSh</span></div>
                                        <input class="form-control form-control-lg font-weight-bold text-primary" type="number" name="price"
                                            value="{{ (int) $food->price }}" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 pl-4">
                                <div class="form-group">
                                    <label class="font-weight-bold small text-uppercase">Prep Time (Minutes)</label>
                                    <div class="input-group">
                                        <input class="form-control form-control-lg" type="number" name="prep_time_minutes"
                                            value="{{ $food->prep_time_minutes }}" placeholder="15">
                                        <div class="input-group-append"><span class="input-group-text">mins</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tile shadow-sm p-4 mb-4 bg-white border">
                            <label class="font-weight-bold small text-uppercase d-block mb-3">Menu Representation</label>
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    @if($food->image)
                                        <div class="position-relative">
                                            <img src="{{ asset('storage/' . $food->image) }}" class="img-fluid rounded shadow-sm border" style="max-height: 150px; object-fit: cover;">
                                            <span class="badge badge-primary position-absolute" style="top: 5px; left: 5px;">Current</span>
                                        </div>
                                    @else
                                        <div class="bg-light rounded border d-flex flex-column align-items-center justify-content-center" style="height: 150px;">
                                            <i class="fa fa-image fa-3x text-muted opacity-25"></i>
                                            <span class="smallest text-muted mt-2">No Image</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-md-9 pl-4">
                                    <div class="form-group mb-0">
                                        <label class="small text-muted font-weight-bold">Change Image</label>
                                        <input class="form-control border-dashed p-4" type="file" name="image" style="border: 2px dashed #ddd; height: auto;">
                                        <small class="text-muted mt-2 d-block">Recommended size: 800x600px | Max 2MB</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5">
                        <div class="d-flex justify-content-between align-items-center mb-4 px-2">
                             <div>
                                <h4 class="mb-0 font-weight-bold text-dark"><i class="fa fa-list-alt mr-2 text-info"></i> Extras & Add-ons</h4>
                                <p class="small text-muted mb-0">Define optional toppings or side portions</p>
                             </div>
                            <button type="button" class="btn btn-info shadow-sm rounded-pill px-4" id="add-extra">
                                <i class="fa fa-plus-circle mr-1"></i> Add Extra Ingredient
                            </button>
                        </div>

                        <div id="extras-container">
                            @foreach($food->extras as $index => $extra)
                                <div class="row extra-row mb-3 align-items-center bg-light p-3 rounded mx-0 border card-shadow">
                                    <input type="hidden" name="extras[{{ $index }}][id]" value="{{ $extra->id }}">
                                    <div class="col-md-4">
                                        <label class="smallest font-weight-bold text-uppercase text-muted mb-1 d-block text-truncate">Extra Item Name</label>
                                        <input type="text" name="extras[{{ $index }}][name]" class="form-control font-weight-bold"
                                            value="{{ $extra->name }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="smallest font-weight-bold text-uppercase text-muted mb-1 d-block text-truncate">Surcharge (TZS)</label>
                                        <div class="input-group">
                                            <input type="number" name="extras[{{ $index }}][price]" class="form-control extra-price font-weight-bold {{ (int)$extra->price == 0 ? 'bg-light' : '' }}"
                                                value="{{ (int) $extra->price }}" {{ (int)$extra->price == 0 ? 'readonly' : '' }} required>
                                            <div class="input-group-append">
                                                <div class="input-group-text bg-white">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input is-free-toggle" id="free_check_edit_{{ $index }}" {{ (int)$extra->price == 0 ? 'checked' : '' }}>
                                                        <label class="custom-control-label font-weight-bold smallest" for="free_check_edit_{{ $index }}">FREE</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="smallest font-weight-bold text-uppercase text-muted mb-1 d-block text-truncate">Stock Status</label>
                                        <select name="extras[{{ $index }}][is_available]" class="form-control">
                                            <option value="1" {{ $extra->is_available ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ !$extra->is_available ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 text-right">
                                        <button type="button" class="btn btn-outline-danger remove-extra mt-4 shadow-sm btn-sm">
                                            <i class="fa fa-trash"></i> DELETE
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="tile-footer bg-light p-4 rounded-lg mt-5 border d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                <i class="fa fa-info-circle mr-1"></i> Changes will be reflected in Kiosk and Customer portals immediately.
                            </div>
                            <div>
                                <a class="btn btn-secondary px-4 mr-2" href="{{ route('bar.food.index') }}"><i
                                    class="fa fa-times-circle"></i> CANCEL</a>
                                <button class="btn btn-primary px-5 font-weight-bold shadow shadow-lg" type="submit"><i
                                    class="fa fa-check-circle mr-2"></i> UPDATE MENU ITEM</button>
                            </div>
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

                // Show/hide custom category input
                $('#category-select').on('change', function () {
                    if ($(this).val() === 'other') {
                        $('#custom-category-wrapper').fadeIn().show();
                        $('#custom-category').prop('required', true).focus();
                        $('#final-category').val($('#custom-category').val());
                    } else {
                        $('#custom-category-wrapper').fadeOut().hide();
                        $('#custom-category').prop('required', false);
                        $('#final-category').val($(this).val());
                    }
                });

                $('#custom-category').on('input', function () {
                    $('#final-category').val($(this).val().trim());
                });

                $('#add-extra').click(function () {
                    const html = `
                        <div class="row extra-row mb-3 align-items-center bg-white p-3 rounded mx-0 border border-info" style="border-left: 5px solid #17a2b8 !important;">
                            <div class="col-md-4">
                                <label class="smallest font-weight-bold text-uppercase text-muted mb-1">Extra Item Name</label>
                                <input type="text" name="extras[${extraIndex}][name]" class="form-control" placeholder="e.g. Extra Sauce" required>
                            </div>
                            <div class="col-md-3">
                                <label class="smallest font-weight-bold text-uppercase text-muted mb-1">Surcharge (TZS)</label>
                                <div class="input-group">
                                    <input type="number" name="extras[${extraIndex}][price]" class="form-control extra-price" placeholder="0" required>
                                    <div class="input-group-append">
                                        <div class="input-group-text bg-white">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input is-free-toggle" id="free_check_new_${extraIndex}">
                                                <label class="custom-control-label font-weight-bold smallest" for="free_check_new_${extraIndex}">FREE</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="smallest font-weight-bold text-uppercase text-muted mb-1">Initial Status</label>
                                <select name="extras[${extraIndex}][is_available]" class="form-control">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2 text-right">
                                <button type="button" class="btn btn-outline-danger remove-extra mt-4 shadow-sm btn-sm">
                                    <i class="fa fa-trash"></i> DELETE
                                </button>
                            </div>
                        </div>
                    `;
                    $('#extras-container').append($(html).hide().fadeIn(500));
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
                    $(this).closest('.extra-row').fadeOut(300, function() {
                        $(this).remove();
                    });
                });
            });
        </script>
        <style>
             .smallest { font-size: 0.65rem; letter-spacing: 0.5px; }
             .border-dashed { border: 2px dashed #ddd !important; }
             .card-shadow { transition: all 0.2s; }
             .card-shadow:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        </style>
    @endpush

@endsection