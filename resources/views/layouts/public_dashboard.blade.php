<!DOCTYPE html>
<html lang="en">
  <head>
    <meta name="description" content="MEDALLION - Customer Feedback">
    <title>@yield('title', 'Feedback') - MEDALLION</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Main CSS (Mirrors internal dashboard) -->
    <link rel="stylesheet" type="text/css" href="{{ asset('css/admin.css') }}">
    <!-- Font-icon css-->
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    
    <style>
      :root {
        --primary: #940000;
        --secondary: #000000;
        --font-family: "Century Gothic", "Apple Gothic", "ITC Century Gothic", sans-serif;
      }
      body {
        font-family: var(--font-family) !important;
        background-color: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
        padding: 20px;
      }
      .feedback-container {
        width: 100%;
        max-width: 600px;
      }
      .card {
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        border: none;
      }
      .card-header {
        background-color: var(--primary);
        color: white;
        border-top-left-radius: 15px !important;
        border-top-right-radius: 15px !important;
        text-align: center;
        padding: 20px;
      }
      .btn-primary {
        background-color: var(--primary);
        border-color: var(--primary);
        padding: 12px;
        font-weight: 700;
      }
      .btn-primary:hover {
        background-color: #7a0000;
        border-color: #7a0000;
      }
      .business-logo {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 5px;
      }
    </style>
    @yield('extra_css')
  </head>
  <body>
    <div class="feedback-container">
        @yield('content')

        <div class="text-center mt-4">
            <p class="text-muted small">Powered By <a href="https://www.emca.tech" target="_blank" style="color: var(--primary); font-weight: 600;">EmCa Technologies LTD</a></p>
        </div>
    </div>

    <!-- Essential Scripts -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
