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
        font-family: "Century Gothic", sans-serif;
        color: #ffffff;
        font-size: 2.4rem;
        font-weight: 500;
        letter-spacing: 8px;
        text-transform: uppercase;
        text-align: center;
        text-shadow: 0 2px 14px rgba(0, 0, 0, 0.75);
        margin-bottom: 1rem;
        background: rgba(25, 16, 10, 0.58);
        border: 1px solid rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        border-radius: 10px;
        padding: 14px 28px;
        display: inline-block;
      }
      .logo {
        text-align: center;
      }
      .side-brand-wrap {
        position: fixed;
        right: 20px;
        bottom: 18px;
        z-index: 20;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
      }
      .support-row {
        display: flex;
        align-items: center;
        gap: 10px;
      }
      .support-pill {
        border-radius: 999px;
        padding: 6px 16px;
        color: #ffffff;
        background: rgba(0, 0, 0, 0.75);
        border: 1px solid rgba(255, 255, 255, 0.35);
        font-family: "Century Gothic", sans-serif;
        letter-spacing: 1.6px;
        font-size: 0.78rem;
        font-weight: 700;
        text-decoration: none !important;
        transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
        animation: supportPulse 1.8s ease-in-out infinite;
      }
      .support-pill:hover {
        color: #ffffff;
        background: rgba(148, 0, 0, 0.9);
        transform: translateY(-1px) scale(1.03);
        box-shadow: 0 0 0 7px rgba(148, 0, 0, 0.12);
      }
      .support-call {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        border: 3px solid #ffffff;
        background: #940000;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
      }
      .powered-card {
        background: #ffffff;
        border-left: 4px solid #940000;
        border-radius: 7px;
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.24);
        padding: 7px 14px;
        min-width: 300px;
        font-family: "Century Gothic", sans-serif;
        letter-spacing: 1.2px;
        color: #6d7278;
        text-transform: uppercase;
        font-weight: 700;
        font-size: 0.78rem;
        text-align: center;
      }
      .powered-card strong {
        color: #940000;
        letter-spacing: 2px;
      }
      @keyframes supportPulse {
        0% { box-shadow: 0 0 0 0 rgba(148, 0, 0, 0.32); }
        70% { box-shadow: 0 0 0 9px rgba(148, 0, 0, 0); }
        100% { box-shadow: 0 0 0 0 rgba(148, 0, 0, 0); }
      }
      @media (max-width: 768px) {
        .side-brand-wrap {
          right: 14px;
          left: 14px;
          bottom: 14px;
          align-items: stretch;
        }
        .support-row {
          justify-content: flex-end;
        }
        .powered-card {
          min-width: 0;
          width: 100%;
          font-size: 0.78rem;
          letter-spacing: 1.3px;
        }
      }
      .material-half-bg .cover {
        background:
          linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)),
          url('{{ asset('img/landing/Meddalion_background_image.jpg') }}');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
      }
      .material-half-bg,
      .material-half-bg .cover {
        position: fixed;
        inset: 0;
        width: 100vw;
        height: 100vh;
      }
      .login-content {
        min-height: 100vh;
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
        <h1>MEDALLION RESTAURANT</h1>
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
            </div>
          </div>
          <div class="form-group btn-container">
            <button type="submit" id="signInBtn" class="btn btn-primary btn-block">
              <span id="btnText"><i class="fa fa-sign-in fa-lg fa-fw"></i>SIGN IN</span>
              <span id="btnSpinner" style="display:none;"><i class="fa fa-spinner fa-spin fa-fw"></i> Signing In...</span>
            </button>
          </div>
        </form>
    </section>
    <div class="side-brand-wrap" aria-hidden="true">
      <div class="support-row">
        <a class="support-pill" href="https://www.emca.tech/contact" target="_blank" rel="noopener noreferrer">SUPPORT</a>
        <span class="support-call"><i class="fa fa-phone"></i></span>
      </div>
      <div class="powered-card">
        Powered By <strong>EmCa Techonologies LTD</strong>
      </div>
    </div>
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

