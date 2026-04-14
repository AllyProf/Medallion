@extends('layouts.dashboard')

@section('title', 'Register Food Menu')

@section('content')
    <div class="app-title">
        <div>
            <h1><i class="fa fa-plus"></i> Register Food Menu</h1>
            <p>Add a new item to your kitchen menu</p>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
            <li class="breadcrumb-item"><a href="{{ route('bar.food.index') }}">Food Menu</a></li>
            <li class="breadcrumb-item">Register</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="tile">
                <h3 class="tile-title">Menu Details</h3>
                <div class="tile-body">
                    <form action="{{ route('bar.food.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Food Name <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" name="name" placeholder="e.g. Grilled Chicken"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Variant / Size</label>
                                    <input class="form-control" type="text" name="variant_name"
                                        placeholder="e.g. Standard, Large, 1/4 Portion">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-control" id="category-select" required>
                                        <option value="">-- Select Category --</option>
                                        <option value="Appetizers">Appetizers</option>
                                        <option value="Fast Food">Fast Food</option>
                                        <option value="Main Course">Main Course</option>
                                        <option value="Grills & BBQ">Grills & BBQ</option>
                                        <option value="Seafood">Seafood</option>
                                        <option value="Soups & Stews">Soups & Stews</option>
                                        <option value="Rice & Pasta">Rice & Pasta</option>
                                        <option value="Vegetarian">Vegetarian</option>
                                        <option value="Salads">Salads</option>
                                        <option value="Desserts">Desserts</option>
                                        <option value="Breakfast">Breakfast</option>
                                        <option value="Kids Menu">Kids Menu</option>
                                        <option value="Specials">Specials</option>
                                        <option value="other">Other (type below)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6" id="custom-category-wrapper" style="display:none;">
                                <div class="form-group">
                                    <label class="control-label">Custom Category</label>
                                    <input class="form-control" type="text" id="custom-category" placeholder="e.g. Chef's Special">
                                </div>
                            </div>
                        </div>
                        {{-- Hidden input that carries the final resolved category to the server --}}
                        <input type="hidden" name="category" id="final-category">

                        <div class="form-group">
                            <label class="control-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"
                                placeholder="Brief details about the food..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Selling Price (TZS) <span
                                            class="text-danger">*</span></label>
                                    <input class="form-control" type="number" name="price" placeholder="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Prep Time (Minutes)</label>
                                    <input class="form-control" type="number" name="prep_time_minutes" placeholder="15">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label">Food Image</label>
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
                            <!-- Extras will be added here -->
                        </div>

                        <div class="tile-footer">
                            <button class="btn btn-primary" type="submit"><i
                                    class="fa fa-fw fa-lg fa-check-circle"></i>Register Menu</button>
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
                let extraIndex = 0;

                // Show/hide custom category input
                $('#category-select').on('change', function () {
                    if ($(this).val() === 'other') {
                        $('#custom-category-wrapper').show();
                        $('#custom-category').prop('required', true).focus();
                    } else {
                        $('#custom-category-wrapper').hide();
                        $('#custom-category').prop('required', false).val('');
                    }
                });

                // Live-update hidden field as user types
                $('#custom-category').on('input', function () {
                    $('#final-category').val($(this).val().trim());
                });

                // On select change (non-other), update hidden field too
                $('#category-select').on('change', function () {
                    if ($(this).val() !== 'other') {
                        $('#final-category').val($(this).val());
                    }
                });

                // Before submit, ensure final-category hidden input is correct
                $('form').on('submit', function () {
                    if ($('#category-select').val() === 'other') {
                        var custom = $('#custom-category').val().trim();
                        if (!custom) {
                            alert('Please type your custom category name.');
                            return false;
                        }
                        $('#final-category').val(custom);
                    } else {
                        $('#final-category').val($('#category-select').val());
                    }
                    return true;
                });

                $('#add-extra').click(function () {
                    const html = `
                        <div class="row extra-row mb-3 align-items-center bg-light p-2 rounded mx-0">
                            <div class="col-md-5">
                                <label class="small font-weight-bold text-uppercase text-muted mb-1">Extra Name</label>
                                <input type="text" name="extras[${extraIndex}][name]" class="form-control" placeholder="e.g. Extra Cheese" required>
                            </div>
                            <div class="col-md-3">
                                <label class="small font-weight-bold text-uppercase text-muted mb-1">Price (TZS)</label>
                                <input type="number" name="extras[${extraIndex}][price]" class="form-control extra-price" placeholder="0" required>
                            </div>
                            <div class="col-md-2 text-center pt-3">
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input is-free-toggle" id="free_check_${extraIndex}">
                                    <label class="custom-control-label font-weight-bold" for="free_check_${extraIndex}">FREE</label>
                                </div>
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