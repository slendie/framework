@php
    /** Página de debug (APP_DEBUG=true). Variáveis: $class, $message, $file, $line, $snippet, $trace, $request, $environment */
    $dump = static fn (mixed $value): string => (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
@endphp
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $class }}</title>
    <style>
        :root { --bg: #f8fafc; --fg: #0f172a; --muted: #64748b; --card: #fff; --line: #e2e8f0; --danger: #b91c1c; --hit: #fee2e2; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b1120; --fg: #e2e8f0; --muted: #94a3b8; --card: #111827; --line: #1f2937; --danger: #fca5a5; --hit: #450a0a; } }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1.5rem; font: 14px/1.5 system-ui, sans-serif; background: var(--bg); color: var(--fg); }
        .wrap { max-width: 72rem; margin: 0 auto; display: grid; gap: 1rem; }
        section { background: var(--card); border: 1px solid var(--line); border-radius: .5rem; padding: 1rem 1.25rem; overflow: auto; }
        h1 { margin: 0 0 .25rem; font-size: 1.25rem; color: var(--danger); word-break: break-all; }
        h2 { margin: 0 0 .5rem; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
        .msg { font-size: 1.1rem; font-weight: 600; margin: 0 0 .5rem; }
        .loc { color: var(--muted); font-family: ui-monospace, monospace; font-size: .85rem; }
        pre, code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; }
        pre { margin: 0; }
        .code div { display: flex; white-space: pre; }
        .code .n { width: 3.5rem; flex: none; color: var(--muted); text-align: right; padding-right: 1rem; user-select: none; }
        .code .hit { background: var(--hit); }
        ol { margin: 0; padding-left: 1.5rem; }
        ol li { margin-bottom: .35rem; }
    </style>
</head>
<body>
<div class="wrap">
    <section>
        <h1>{{ $class }}</h1>
        <p class="msg">{{ $message }}</p>
        <div class="loc">{{ $file }}:{{ $line }}</div>
    </section>

    @if ($snippet !== [])
    <section>
        <h2>Código</h2>
        <div class="code">
            @foreach ($snippet as $number => $code)
                <div class="{{ $number === $line ? 'hit' : '' }}"><span class="n">{{ $number }}</span><code>{{ $code }}</code></div>
            @endforeach
        </div>
    </section>
    @endif

    <section>
        <h2>Stack trace</h2>
        <ol>
            @foreach ($trace as $frame)
                <li><code>{{ $frame['call'] }}</code><br><span class="loc">{{ $frame['file'] }}:{{ $frame['line'] }}</span></li>
            @endforeach
        </ol>
    </section>

    <section>
        <h2>Pedido</h2>
        <pre>{{ $dump($request) }}</pre>
    </section>

    <section>
        <h2>Ambiente (sanitizado)</h2>
        <pre>{{ $dump($environment) }}</pre>
    </section>
</div>
</body>
</html>
