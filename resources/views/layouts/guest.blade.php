<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Telco Cost Consolidation' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
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
            background: #475569; color: white; font-size: 14px; cursor: pointer;
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
    </style>
</head>
<body>
    <div class="login-card">
        <div class="icon">📈</div>
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
