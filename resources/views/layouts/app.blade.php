<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>@yield('title', 'IKEA Product Catalog MCP')</title>
    <style>
        :root {
            --bg-1: #2a1f45;
            --bg-2: #5c2f7a;
            --bg-3: #b0478b;
            --panel: rgba(255, 255, 255, 0.045);
            --panel-border: rgba(255, 255, 255, 0.10);
            --panel-border-strong: rgba(255, 255, 255, 0.22);
            --text: rgba(255, 255, 255, 0.92);
            --muted: rgba(255, 255, 255, 0.62);
            --accent: #ffb4d1;
            --accent-strong: #ff86b3;
            --ring: rgba(255, 180, 209, 0.55);
            --danger: #ffc0cb;
            --font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; font-family: var(--font); color: var(--text); line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            background:
                radial-gradient(1200px 600px at 80% -10%, rgba(255, 130, 179, 0.30), transparent 60%),
                radial-gradient(900px 700px at 0% 100%, rgba(96, 60, 160, 0.45), transparent 55%),
                linear-gradient(135deg, var(--bg-1) 0%, var(--bg-2) 48%, var(--bg-3) 100%);
            background-attachment: fixed;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { color: #fff; }

        .topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 28px; max-width: 1120px; margin: 0 auto;
        }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 700; color: var(--text); }
        .brand .mark {
            width: 34px; height: 34px; border-radius: 9px; display: grid; place-items: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: #3a1030; font-weight: 800; font-size: 15px;
        }
        .topbar .links { display: flex; align-items: center; gap: 18px; font-size: 14px; font-weight: 600; }

        .shell { max-width: 1120px; margin: 0 auto; padding: 40px 28px 80px; }
        .narrow { max-width: 460px; margin: 6vh auto 0; }

        .card {
            background: var(--panel); border: 1px solid var(--panel-border); border-radius: 22px; padding: 32px;
            backdrop-filter: blur(4px);
        }
        h1.page-title { font-size: 1.9rem; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 6px; }
        .subtitle { color: var(--muted); margin: 0 0 26px; }

        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 13.5px; font-weight: 700; margin-bottom: 8px; }
        .field input, .field select {
            width: 100%; padding: 13px 15px; border-radius: 12px; font-size: 15px; color: #fff;
            background: rgba(0, 0, 0, 0.24); border: 1px solid var(--panel-border); font-family: inherit;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .field select option { color: #1b1030; }
        .field input:focus, .field select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--ring); }
        .field .hint { font-size: 12.5px; color: var(--muted); margin-top: 6px; }
        .field .err { color: var(--danger); font-size: 12.5px; margin-top: 6px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            padding: 14px 26px; border-radius: 999px; font-weight: 700; font-size: 15px; cursor: pointer;
            border: 1px solid transparent; transition: transform .15s ease, background-color .2s ease, border-color .2s ease;
            font-family: inherit;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: #fff; color: #3a1030; width: 100%; }
        .btn-primary:hover { background: var(--accent); }
        .btn-ghost { background: var(--panel); color: #fff; border-color: var(--panel-border-strong); }
        .btn-ghost:hover { border-color: #fff; }
        .btn-link { background: none; border: 0; color: var(--accent); font-weight: 600; cursor: pointer; padding: 0; font-family: inherit; font-size: 14px; }
        .btn-link:hover { color: #fff; }

        .flash {
            margin-bottom: 22px; padding: 14px 16px; border-radius: 12px;
            background: rgba(255, 180, 209, 0.14); border: 1px solid var(--accent); color: #fff; font-size: 14px;
        }
        .meta { color: var(--muted); font-size: 14px; margin-top: 18px; text-align: center; }
        code {
            color: var(--accent); background: rgba(0, 0, 0, 0.28); padding: 3px 8px; border-radius: 6px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .9em; word-break: break-all;
        }
    </style>
    @stack('head')
</head>
<body>
    <header>
        <div class="topbar">
            <a href="{{ route('home') }}" class="brand">
                <span class="mark">IK</span>
                <span>IKEA MCP</span>
            </a>
            <nav class="links">
                @auth
                    <a href="{{ route('settings.edit') }}">Innstillinger</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-link">Logg ut</button>
                    </form>
                @else
                    <a href="{{ route('home') }}">Hjem</a>
                    <a href="{{ route('login') }}">Logg inn</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="shell">
        @yield('content')
    </main>
</body>
</html>
