@php
    $totalContracts = $stats['total_contracts'] ?? 0;
    $totalOrgs = $stats['total_organizations'] ?? 0;
    $totalCompanies = $stats['total_companies'] ?? 0;
    $totalAmount = $stats['total_amount'] ?? 0;
    $topOrgs = $stats['top_organizations'] ?? [];
    $lastSnapshot = $stats['last_snapshot_at'] ?? null;
    $appUrl = rtrim(config('app.url'), '/');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escáner Público — API abierta de contratos del sector público</title>
    <meta name="description" content="API pública y abierta de contratos del sector público español derivada de PLACSP. {{ number_format($totalContracts, 0, ',', '.') }} contratos indexados.">
    <meta name="color-scheme" content="light">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        pre, code { font-family: 'JetBrains Mono', ui-monospace, monospace; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:bg-indigo-600 focus:text-white focus:px-4 focus:py-2 focus:rounded">Saltar al contenido</a>

    <main id="main" class="max-w-5xl mx-auto px-6 py-16">

        <header class="mb-16">
            <div class="text-xs tracking-widest text-indigo-600 font-semibold mb-2">ESCÁNER PÚBLICO</div>
            <h1 class="text-4xl md:text-5xl font-bold mb-4 leading-tight">API abierta de contratos del sector público</h1>
            <p class="text-lg md:text-xl text-slate-600 max-w-2xl">
                Datos oficiales de la <a href="https://contrataciondelestado.es" class="underline decoration-indigo-300 underline-offset-2 hover:text-indigo-700">Plataforma de Contratación del Sector Público</a>, reestructurados en una API REST consultable.
            </p>
        </header>

        <section aria-label="Estadísticas globales" class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-16">
            <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Contratos</div>
                <div class="text-2xl md:text-3xl font-bold mt-2">{{ number_format($totalContracts, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Órganos</div>
                <div class="text-2xl md:text-3xl font-bold mt-2">{{ number_format($totalOrgs, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Empresas</div>
                <div class="text-2xl md:text-3xl font-bold mt-2">{{ number_format($totalCompanies, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Importe total</div>
                <div class="text-2xl md:text-3xl font-bold mt-2">{{ number_format($totalAmount / 1_000_000_000, 2, ',', '.') }}B&euro;</div>
            </div>
        </section>

        <section class="mb-16">
            <h2 class="text-2xl font-bold mb-6">Empieza en 30 segundos</h2>
            <div class="space-y-4">
                <div class="bg-slate-900 rounded-xl p-4 overflow-x-auto">
                    <div class="text-xs text-slate-400 mb-2">Listar últimas adjudicaciones</div>
                    <pre class="text-emerald-400 text-sm leading-relaxed"><code>curl "{{ $appUrl }}/api/v1/contracts?filter[status_code]=ADJ&sort=-snapshot_updated_at"</code></pre>
                </div>
                <div class="bg-slate-900 rounded-xl p-4 overflow-x-auto">
                    <div class="text-xs text-slate-400 mb-2">Ficha de contrato con timeline y adjudicación</div>
                    <pre class="text-emerald-400 text-sm leading-relaxed"><code>curl "{{ $appUrl }}/api/v1/contracts/19066873?include=lots.awards.company,notices,modifications"</code></pre>
                </div>
                <div class="bg-slate-900 rounded-xl p-4 overflow-x-auto">
                    <div class="text-xs text-slate-400 mb-2">Stats de un órgano de contratación</div>
                    <pre class="text-emerald-400 text-sm leading-relaxed"><code>curl "{{ $appUrl }}/api/v1/organizations/1/stats"</code></pre>
                </div>
            </div>
        </section>

        @if (! empty($topOrgs))
            <section class="mb-16">
                <h2 class="text-2xl font-bold mb-6">Top 10 órganos por importe adjudicado</h2>
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left">Órgano</th>
                                    <th scope="col" class="px-4 py-3 text-right">Contratos</th>
                                    <th scope="col" class="px-4 py-3 text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topOrgs as $row)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-4 py-3">{{ $row['name'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['contracts'] ?? 0, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right font-medium tabular-nums">{{ number_format(($row['total'] ?? 0) / 1_000_000, 2, ',', '.') }}M&euro;</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        <section aria-label="Recursos" class="mb-16">
            <h2 class="text-2xl font-bold mb-6">Recursos</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="/docs" class="bg-white rounded-xl p-6 border border-slate-200 hover:border-indigo-500 hover:shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <div class="font-semibold mb-1">Docs interactivas</div>
                    <div class="text-sm text-slate-600">Todos los endpoints, filtros e includes. Pruébalos en el navegador.</div>
                </a>
                <a href="/openapi.json" class="bg-white rounded-xl p-6 border border-slate-200 hover:border-indigo-500 hover:shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <div class="font-semibold mb-1">OpenAPI spec</div>
                    <div class="text-sm text-slate-600">Genera clientes en cualquier lenguaje con openapi-generator.</div>
                </a>
                <a href="https://github.com/christianpasinrey/escanerpublico-backend" class="bg-white rounded-xl p-6 border border-slate-200 hover:border-indigo-500 hover:shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <div class="font-semibold mb-1">GitHub</div>
                    <div class="text-sm text-slate-600">Código abierto. Contribuciones bienvenidas.</div>
                </a>
            </div>
        </section>

        <section aria-label="Rate limits" class="mb-16 bg-slate-100 rounded-xl p-6 text-sm text-slate-700">
            <div class="font-semibold mb-2 text-slate-900">Rate limits</div>
            <div>60 req/min por IP sin auth · 600 req/min con <code class="bg-white px-1.5 py-0.5 rounded text-xs">X-Api-Key</code> (disponible próximamente — hoy la API es totalmente abierta).</div>
        </section>

        <footer class="text-xs text-slate-500 border-t border-slate-200 pt-6 leading-relaxed">
            <p>Datos oficiales de PLACSP (Plataforma de Contratación del Sector Público). Escáner Público no es una entidad pública — solo una capa de presentación que reestructura datos abiertos.</p>
            <p class="mt-2">Última sincronización: <span class="font-medium tabular-nums">{{ $lastSnapshot ?? 'N/A' }}</span></p>
        </footer>
    </main>
</body>
</html>
