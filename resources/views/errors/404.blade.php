<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Página no encontrada · Coteja</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Inter, system-ui, -apple-system, Segoe UI, sans-serif; background: #f6f7fb; color: #172033; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .box { text-align: center; padding: 48px 32px; max-width: 480px; }
        .code { font-size: 96px; font-weight: 800; color: #2563eb; line-height: 1; }
        .title { font-size: 22px; font-weight: 700; margin: 12px 0 8px; }
        .msg { color: #6b7280; font-size: 15px; line-height: 1.6; margin-bottom: 28px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; background: #2563eb; color: #fff; border: 0; border-radius: 8px; padding: 10px 22px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="box">
        <div class="code">404</div>
        <div class="title">Página no encontrada</div>
        <p class="msg">La ruta que buscas no existe o fue movida. Verifica la URL o regresa al inicio.</p>
        <a class="btn" href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}">← Regresar</a>
        &nbsp;
        <a class="btn" href="/" style="background:#111827;">Inicio</a>
    </div>
</body>
</html>
