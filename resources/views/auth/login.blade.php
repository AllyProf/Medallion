<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Main CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('css/admin.css') }}">
    <!-- Font-icon css-->
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <title>Login - MauzoLink</title>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
      :root {
        --primary: #940000;
        --secondary: #000000;
      }
      body {
        font-family: "Century Gothic", sans-serif;
      }
      .btn-primary {
        background-color: #940000;
        border-color: #940000;
      }
      .btn-primary:hover {
        background-color: #7a0000;
        border-color: #7a0000;
      }
      .logo h1 {
        color: #940000;
      }
      .material-half-bg .cover {
        background: linear-gradient(135deg, #940000 0%, #7a0000 100%);
      }
      /* Password toggle */
      .password-wrapper {
        position: relative;
      }
      .password-wrapper .toggle-eye {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #888;
        font-size: 15px;
        z-index: 10;
      }
      .password-wrapper .toggle-eye:hover {
        color: #940000;
      }
      /* Spinner overlay */
      #login-spinner {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(255,255,255,0.75);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        flex-direction: column;
      }
      #login-spinner .spinner-border {
        width: 3rem;
        height: 3rem;
        border-width: 4px;
        color: #940000;
      }
      #login-spinner p {
        margin-top: 14px;
        font-weight: 600;
        color: #940000;
        font-size: 15px;
      }
    </style>
  </head>
  <body>
    <section class="material-half-bg">
      <div class="cover"></div>
    </section>
    <section class="login-content">
      <div class="logo">
        <h1>MauzoLink</h1>
      </div>
      <div class="login-box">
        <form class="login-form" method="POST" action="{{ route('login') }}">
          @csrf
          <h3 class="login-head"><i class="fa fa-lg fa-fw fa-user"></i>SIGN IN</h3>
          

          <div class="form-group">
            <label class="control-label">USERNAME</label>
            <input class="form-control @error('email') is-invalid @enderror" type="text" name="email" placeholder="Email" value="{{ old('email') }}" autofocus required>
            @error('email')
              <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
              </span>
            @enderror
          </div>
          <div class="form-group">
            <label class="control-label">PASSWORD</label>
            <div class="password-wrapper">
              <input id="passwordInput" class="form-control @error('password') is-invalid @enderror" type="password" name="password" placeholder="Password" required>
              <span class="toggle-eye" id="togglePassword">
                <i class="fa fa-eye" id="eyeIcon"></i>
              </span>
            </div>
            @error('password')
              <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
              </span>
            @enderror
          </div>
          <div class="form-group">
            <div class="utility">
              <div class="animated-checkbox">
                <label>
                  <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}><span class="label-text">Stay Signed in</span>
                </label>
              </div>
              <p class="semibold-text mb-2"><a href="#" data-toggle="flip">Forgot Password ?</a></p>
            </div>
          </div>
          <div class="form-group btn-container">
            <button type="submit" id="signInBtn" class="btn btn-primary btn-block">
              <span id="btnText"><i class="fa fa-sign-in fa-lg fa-fw"></i>SIGN IN</span>
              <span id="btnSpinner" style="display:none;"><i class="fa fa-spinner fa-spin fa-fw"></i> Signing In...</span>
            </button>
          </div>
        </form>
        <form class="forget-form" action="#" method="POST">
          @csrf
          <h3 class="login-head"><i class="fa fa-lg fa-fw fa-lock"></i>Forgot Password ?</h3>
          <div class="form-group">
            <label class="control-label">EMAIL</label>
            <input class="form-control" type="text" placeholder="Email">
          </div>
          <div class="form-group btn-container">
            <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-unlock fa-lg fa-fw"></i>RESET</button>
          </div>
          <div class="form-group mt-3">
            <p class="semibold-text mb-0"><a href="#" data-toggle="flip"><i class="fa fa-angle-left fa-fw"></i> Back to Login</a></p>
          </div>
        </form>
      </div>
    </section>
    <!-- Essential javascripts for application to work-->
    <script src="{{ asset('js/admin/jquery-3.2.1.min.js') }}"></script>
    <script src="{{ asset('js/admin/popper.min.js') }}"></script>
    <script src="{{ asset('js/admin/bootstrap.min.js') }}"></script>
    <script src="{{ asset('js/admin/main.js') }}"></script>
    <!-- The javascript plugin to display page loading on top-->
    <script src="{{ asset('js/admin/plugins/pace.min.js') }}"></script>
    <script type="text/javascript">
      // SweetAlert for messages
      @if(session('success'))
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: '{{ session('success') }}',
          confirmButtonColor: '#940000',
          cancelButtonColor: '#000000'
        });
      @endif
      
      @if(session('error'))
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: '{{ session('error') }}',
          confirmButtonColor: '#940000',
          cancelButtonColor: '#000000'
        });
      @endif
      
      @if($errors->any())
        Swal.fire({
          icon: 'error',
          title: 'Login Failed!',
          html: '<ul style="text-align: left;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>',
          confirmButtonColor: '#940000',
          cancelButtonColor: '#000000'
        });
      @endif
      
      // Login Page Flipbox control
      $('.login-content [data-toggle="flip"]').click(function() {
        $('.login-box').toggleClass('flipped');
        return false;
      });

      // Password visibility toggle
      document.getElementById('togglePassword').addEventListener('click', function() {
        const input = document.getElementById('passwordInput');
        const icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
          input.type = 'password';
          icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
      });

      // Loading spinner on submit
      document.getElementById('signInBtn').closest('form').addEventListener('submit', function() {
        document.getElementById('btnText').style.display = 'none';
        document.getElementById('btnSpinner').style.display = 'inline-block';
        document.getElementById('signInBtn').disabled = true;
      });
    </script>
  </body>
</html>

