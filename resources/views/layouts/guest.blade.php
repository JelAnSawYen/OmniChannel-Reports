<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Telco Cost Consolidation' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/ssg-favicon.png') }}?v={{ @filemtime(public_path('images/ssg-favicon.png')) ?: '1' }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v={{ @filemtime(public_path('favicon.ico')) ?: '1' }}">
    @if(request()->routeIs('login'))
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@600&display=swap" rel="stylesheet">
    @endif
    <style>
        * { box-sizing: border-box; font-family: inherit; }
        html, body {
            font-family: "Segoe UI", Inter, Arial, sans-serif;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Inter, Arial, sans-serif;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.25), rgba(255, 255, 255, 0.25)),
                url('{{ asset('images/login-bg.jpg') }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        button, input, select, textarea {
            font-family: inherit;
        }
        ::placeholder {
            font-family: inherit;
        }
        .login-page h1,
        .login-page p,
        .login-page input,
        .login-page input::placeholder,
        .login-page .toggle-password,
        .login-page .card-link,
        .login-page .footer,
        .login-page .footer a,
        .login-page .error-message,
        .login-page .status-message {
            font-family: "Segoe UI", Inter, Arial, sans-serif;
        }
        .login-card {
            width: 450px;
            padding: 43.75px;
            background: rgba(255, 255, 255, 0.90);
            border-radius: 15px;
            box-shadow: 0 12.5px 43.75px rgba(0, 0, 0, 0.15);
            text-align: center;
            backdrop-filter: blur(3.75px);
        }
        .icon { font-size: 52.5px; margin-bottom: 15px; color: #334155; }
        .login-logo { display: block; margin: 0 auto; width: 165px; max-width: 70%; height: auto; object-fit: contain; }
        h1 { margin: 0 0 31.25px; font-size: 27.5px; font-weight: 500; color: #475569; }
        p.hint { margin: -12.5px 0 22.5px; font-size: 16.25px; color: #64748b; text-align: left; }
        input {
            width: 100%;
            padding: 15px;
            margin-bottom: 18.75px;
            border: 1.25px solid #cbd5e1;
            border-radius: 8.75px;
            font-size: 17.5px;
            line-height: normal;
            outline: none;
            background: rgba(255, 255, 255, 0.95);
        }
        input:focus { border-color: #64748b; }
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 56.25px; }
        .toggle-password {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            width: auto; padding: 0; margin: 0; border: none; background: transparent;
            color: #64748b; font-size: 16.25px; cursor: pointer;
        }
        .toggle-password:hover { background: transparent; color: #334155; }
        .login-button {
            width: 100%; padding: 15px; border: none; border-radius: 8.75px;
            background: #475569; color: white; font-size: 17.5px; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; line-height: 1;
        }
        .login-button:hover { background: #334155; }
        .error-message {
            margin-bottom: 18.75px; padding: 12.5px; border-radius: 8.75px;
            background: #fee2e2; color: #b91c1c; font-size: 16.25px; text-align: left;
        }
        .status-message {
            margin-bottom: 18.75px; padding: 12.5px; border-radius: 8.75px;
            background: #dcfce7; color: #166534; font-size: 16.25px; text-align: left;
        }
        .footer { margin-top: 22.5px; font-size: 13.75px; color: #94a3b8; }
        .footer a, .card-link { color: #475569; font-size: 16.25px; text-decoration: none; }
        .secret-box {
            text-align: left; font-family: Consolas, monospace; font-size: 16.25px;
            background: #fff; border: 1.25px solid #cbd5e1; border-radius: 8.75px;
            padding: 12.5px; margin-bottom: 18.75px; word-break: break-all; color: #334155;
        }
        .code-list { text-align: left; font-family: Consolas, monospace; font-size: 16.25px; color: #334155; margin: 0 0 18.75px; padding-left: 22.5px; }
        body.login-page {
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0.10)),
                url('{{ asset('images/login-bg.jpg') }}?v={{ @filemtime(public_path('images/login-bg.jpg')) ?: '1' }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .login-page .icon { margin-bottom: 10px; }
        .login-page .login-logo { width: 90px; max-width: 40%; }
        .login-page h1 {
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            font-weight: 600;
            color: #475569;
            text-align: center;
        }
        .login-page .card-link {
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            font-weight: 600;
            color: #2563EB;
        }
        .login-page .input-with-icon { position: relative; margin-bottom: 18.75px; }
        .login-page .input-with-icon input { margin-bottom: 0; padding-left: 50px; }
        .login-page .input-with-icon.password-wrapper input { padding-right: 52.5px; }
        .login-page input[type="password"]::-ms-reveal,
        .login-page input[type="password"]::-ms-clear,
        .login-page input[type="text"]::-ms-reveal,
        .login-page input[type="text"]::-ms-clear {
            display: none;
        }
        .login-page .field-icon {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            width: 22.5px; height: 22.5px; color: #475569; pointer-events: none; display: block;
        }
        .login-page .toggle-password {
            display: none; align-items: center; justify-content: center;
            color: #475569; width: 27.5px; height: 27.5px; z-index: 2;
        }
        .login-page .toggle-password.has-value { display: flex; }
        .login-page .toggle-password svg { width: 22.5px; height: 22.5px; pointer-events: none; }
        .login-page .toggle-password .icon-eye { display: none; }
        .login-page .toggle-password .icon-eye-off { display: block; }
        .login-page .toggle-password.is-visible .icon-eye { display: block; }
        .login-page .toggle-password.is-visible .icon-eye-off { display: none; }
    </style>
</head>
<body class="{{ request()->routeIs('login') ? 'login-page' : '' }}">
    <div class="login-card">
        @if (request()->routeIs('login'))
            <div class="icon"><img class="login-logo" src="{{ asset('images/ssg-logo.png') }}" alt="SSG"></div>
        @else
            <div class="icon">📈</div>
        @endif
        <h1>{{ $heading ?? 'Support Services Group (APAC)' }}</h1>
        @if ($errors->any())
            <div class="error-message">{{ $errors->first() }}</div>
        @endif
        @if (session('error'))
            <div class="error-message">{{ session('error') }}</div>
        @endif
        @if (session('status'))
            <div class="status-message">{{ session('status') }}</div>
        @endif
        @yield('content')
        <div class="footer">Powered by OmniChannel Development Team</div>
    </div>
</body>
</html>
