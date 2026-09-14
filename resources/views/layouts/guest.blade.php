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
            width: 360px;
            padding: 35px;
            background: rgba(255, 255, 255, 0.90);
            border-radius: 12px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.15);
            text-align: center;
            backdrop-filter: blur(3px);
        }
        .icon { font-size: 42px; margin-bottom: 12px; color: #334155; }
        .login-logo { display: block; margin: 0 auto; width: 132px; max-width: 70%; height: auto; object-fit: contain; }
        h1 { margin: 0 0 25px; font-size: 22px; font-weight: 500; color: #475569; }
        p.hint { margin: -10px 0 18px; font-size: 13px; color: #64748b; text-align: left; }
        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
            background: rgba(255, 255, 255, 0.95);
        }
        input:focus { border-color: #64748b; }
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 45px; }
        .toggle-password {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            width: auto; padding: 0; margin: 0; border: none; background: transparent;
            color: #64748b; font-size: 13px; cursor: pointer;
        }
        .toggle-password:hover { background: transparent; color: #334155; }
        .login-button {
            width: 100%; padding: 12px; border: none; border-radius: 7px;
            background: #475569; color: white; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .login-button:hover { background: #334155; }
        .error-message {
            margin-bottom: 15px; padding: 10px; border-radius: 7px;
            background: #fee2e2; color: #b91c1c; font-size: 13px; text-align: left;
        }
        .status-message {
            margin-bottom: 15px; padding: 10px; border-radius: 7px;
            background: #dcfce7; color: #166534; font-size: 13px; text-align: left;
        }
        .footer { margin-top: 18px; font-size: 11px; color: #94a3b8; }
        .footer a, .card-link { color: #475569; font-size: 13px; text-decoration: none; }
        .secret-box {
            text-align: left; font-family: Consolas, monospace; font-size: 13px;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 7px;
            padding: 10px; margin-bottom: 15px; word-break: break-all; color: #334155;
        }
        .code-list { text-align: left; font-family: Consolas, monospace; font-size: 13px; color: #334155; margin: 0 0 15px; padding-left: 18px; }
        body.login-page {
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0.10)),
                url('{{ asset('images/login-bg.jpg') }}?v={{ @filemtime(public_path('images/login-bg.jpg')) ?: '1' }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .login-page .icon { margin-bottom: 8px; }
        .login-page .login-logo { width: 72px; max-width: 40%; }
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
        .login-page .input-with-icon { position: relative; margin-bottom: 15px; }
        .login-page .input-with-icon input { margin-bottom: 0; padding-left: 40px; }
        .login-page .input-with-icon.password-wrapper input { padding-right: 42px; }
        .login-page input[type="password"]::-ms-reveal,
        .login-page input[type="password"]::-ms-clear,
        .login-page input[type="text"]::-ms-reveal,
        .login-page input[type="text"]::-ms-clear {
            display: none;
        }
        .login-page .field-icon {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px; color: #475569; pointer-events: none; display: block;
        }
        .login-page .toggle-password {
            display: none; align-items: center; justify-content: center;
            color: #475569; width: 22px; height: 22px; z-index: 2;
        }
        .login-page .toggle-password.has-value { display: flex; }
        .login-page .toggle-password svg { width: 18px; height: 18px; pointer-events: none; }
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
