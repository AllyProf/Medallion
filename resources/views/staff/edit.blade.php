@extends('layouts.dashboard')

@section('title', 'Edit Staff')

@push('styles')
<style>
  .section-title {
    border-left: 4px solid #940000;
    padding-left: 12px;
    margin-bottom: 20px;
    font-weight: 700;
    color: #333;
    background: #f8f9fa;
    padding-top: 8px;
    padding-bottom: 8px;
    border-radius: 0 4px 4px 0;
  }
  .form-control:focus {
    border-color: #940000;
    box-shadow: 0 0 0 0.2rem rgba(148, 0, 0, 0.25);
  }
  .attachment-card {
    border: 1px dashed #ced4da;
    border-radius: 8px;
    padding: 15px;
    background: #fff;
    transition: all 0.3s ease;
    height: 100%;
  }
  .attachment-card:hover {
    border-color: #940000;
    background: #fdfdfd;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
  }
  .current-attachment {
    font-size: 0.85rem;
    color: #28a745;
    margin-top: 8px;
    padding: 5px 10px;
    background: #e8f5e9;
    border-radius: 4px;
    display: inline-block;
  }
  .tile { border-radius: 10px; border: none; }
</style>
@endpush

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Edit Staff Member</h1>
    <p>Update information for <strong>{{ $staff->full_name }}</strong></p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('staff.index') }}">Staff</a></li>
    <li class="breadcrumb-item">Edit</li>
  </ul>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile shadow-sm">
      @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
          <strong><i class="fa fa-exclamation-triangle"></i> Please fix the following errors:</strong>
          <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
      @endif

      <form action="{{ route('staff.update', $staff->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <input type="hidden" name="business_type_id" value="{{ $staff->business_type_id ?? 2 }}">
        
        <!-- Section 1: Personal Information -->
        <h5 class="section-title"><i class="fa fa-user-circle mr-2"></i> Personal Information</h5>
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="font-weight-bold">Full Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('full_name') is-invalid @enderror" 
                     name="full_name" value="{{ old('full_name', $staff->full_name) }}" required>
              @error('full_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          
          <div class="col-md-4">
            <div class="form-group">
              <label class="font-weight-bold">Email Address <span class="text-danger">*</span></label>
              <input type="email" class="form-control @error('email') is-invalid @enderror" 
                     name="email" value="{{ old('email', $staff->email) }}" required>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-group">
              <label class="font-weight-bold">Phone Number <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('phone_number') is-invalid @enderror" 
                     name="phone_number" value="{{ old('phone_number', $staff->phone_number) }}" required>
              @error('phone_number')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="font-weight-bold">Gender <span class="text-danger">*</span></label>
              <select class="form-control @error('gender') is-invalid @enderror" name="gender" required>
                <option value="male" {{ old('gender', $staff->gender) == 'male' ? 'selected' : '' }}>Male</option>
                <option value="female" {{ old('gender', $staff->gender) == 'female' ? 'selected' : '' }}>Female</option>
                <option value="other" {{ old('gender', $staff->gender) == 'other' ? 'selected' : '' }}>Other</option>
              </select>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-group">
              <label class="font-weight-bold">Religion (Optional)</label>
              <input type="text" class="form-control @error('religion') is-invalid @enderror" 
                     name="religion" value="{{ old('religion', $staff->religion) }}">
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-group">
              <label class="font-weight-bold">NIDA Number</label>
              <input type="text" class="form-control @error('nida') is-invalid @enderror" 
                     name="nida" value="{{ old('nida', $staff->nida) }}">
            </div>
          </div>
        </div>

        <!-- Section 2: Kin & Emergency -->
        <h5 class="section-title mt-4"><i class="fa fa-heartbeat mr-2"></i> Next of Kin & Emergency</h5>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="font-weight-bold">Next of Kin Name</label>
              <input type="text" class="form-control @error('next_of_kin') is-invalid @enderror" 
                     name="next_of_kin" value="{{ old('next_of_kin', $staff->next_of_kin) }}">
            </div>
          </div>
          
          <div class="col-md-6">
            <div class="form-group">
              <label class="font-weight-bold">Next of Kin Phone</label>
              <input type="text" class="form-control @error('next_of_kin_phone') is-invalid @enderror" 
                     name="next_of_kin_phone" value="{{ old('next_of_kin_phone', $staff->next_of_kin_phone) }}">
            </div>
          </div>
        </div>

        <!-- Section 3: Employment Details -->
        <h5 class="section-title mt-4"><i class="fa fa-briefcase mr-2"></i> Employment Details</h5>
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="font-weight-bold">Staff Role <span class="text-danger">*</span></label>
              <select class="form-control @error('role_id') is-invalid @enderror" name="role_id" required>
                @foreach($roles as $role)
                  <option value="{{ $role->id }}" {{ old('role_id', $staff->role_id) == $role->id ? 'selected' : '' }}>
                    {{ $role->name }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-group">
              <label class="font-weight-bold">Salary (TSh)</label>
              <input type="number" step="0.01" class="form-control @error('salary_paid') is-invalid @enderror" 
                     name="salary_paid" value="{{ old('salary_paid', $staff->salary_paid) }}">
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-group">
              <label class="font-weight-bold">Branch/Location</label>
              <input type="text" class="form-control @error('location_branch') is-invalid @enderror" 
                     name="location_branch" value="{{ old('location_branch', $staff->location_branch) }}">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="font-weight-bold">Kiosk PIN <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text" class="form-control @error('pin') is-invalid @enderror" 
                       id="custom_pin" name="pin" value="{{ old('pin', $staff->pin) }}" maxlength="4">
                <div class="input-group-append">
                  <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('custom_pin').value = Math.floor(1000 + Math.random() * 9000)">
                    <i class="fa fa-refresh"></i> Generate
                  </button>
                </div>
              </div>
              <small class="text-muted">4-digit code for Kiosk login.</small>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group">
              <label class="font-weight-bold">Account Status</label>
              <select class="form-control" name="is_active">
                <option value="1" {{ old('is_active', $staff->is_active) == 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ old('is_active', $staff->is_active) == 0 ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Section 4: Attachments -->
        <h5 class="section-title mt-4"><i class="fa fa-paperclip mr-2"></i> Documents & Attachments</h5>
        <div class="row">
          <!-- NIDA -->
          <div class="col-md-4 mb-3">
            <div class="attachment-card shadow-sm">
              <label class="font-weight-bold">NIDA Document</label>
              <input type="file" name="nida_attachment" class="form-control-file">
              @if($staff->nida_attachment)
                <div class="current-attachment">
                  <i class="fa fa-file-pdf-o"></i> <a href="{{ Storage::url($staff->nida_attachment) }}" target="_blank">Current NIDA</a>
                </div>
              @endif
            </div>
          </div>
          
          <!-- Voter ID -->
          <div class="col-md-4 mb-3">
            <div class="attachment-card shadow-sm">
              <label class="font-weight-bold">Voter ID</label>
              <input type="file" name="voter_id_attachment" class="form-control-file">
              @if($staff->voter_id_attachment)
                <div class="current-attachment">
                  <i class="fa fa-id-card-o"></i> <a href="{{ Storage::url($staff->voter_id_attachment) }}" target="_blank">Current Voter ID</a>
                </div>
              @endif
            </div>
          </div>

          <!-- Certificate -->
          <div class="col-md-4 mb-3">
            <div class="attachment-card shadow-sm">
              <label class="font-weight-bold">Professional Certificate</label>
              <input type="file" name="professional_certificate_attachment" class="form-control-file">
              @if($staff->professional_certificate_attachment)
                <div class="current-attachment">
                  <i class="fa fa-certificate"></i> <a href="{{ Storage::url($staff->professional_certificate_attachment) }}" target="_blank">Current Certificate</a>
                </div>
              @endif
            </div>
          </div>
        </div>

        <div class="tile-footer mt-4 text-right">
          <hr>
          <a href="{{ route('staff.index') }}" class="btn btn-secondary btn-lg px-4 mr-2">
            <i class="fa fa-times mr-1"></i> Cancel
          </a>
          <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
            <i class="fa fa-save mr-1"></i> Update Staff Information
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
