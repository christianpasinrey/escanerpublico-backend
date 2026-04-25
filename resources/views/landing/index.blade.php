@php
    $totalContracts = $stats['total_contracts'] ?? 0;
    $totalOrgs = $stats['total_organizations'] ?? 0;
    $totalCompanies = $stats['total_companies'] ?? 0;
    $totalAmount = $stats['total_amount'] ?? 0;
    $topOrgs = $stats['top_organizations'] ?? [];
    $lastSnapshot = $stats['last_snapshot_at'] ?? null;
    $appUrl = rtrim(config('app.url'), '/');
    $today = now()->locale('es')->isoFormat('DD/MM/YYYY');
    $year = now()->year;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escáner Público — API abierta de contratos del sector público</title>
    <meta name="description" content="API pública y abierta de contratos del sector público español derivada de PLACSP. {{ number_format($totalContracts, 0, ',', '.') }} contratos indexados.">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0d1318">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght,SOFT,WONK@9..144,400..900,0..100,0..1&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Serif:wght@400;500;600;700&family=Caveat:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --paper: #0d1318;
            --paper-deep: #0a1014;
            --paper-edge: #11181d;
            --ink: #e8e6dd;
            --ink-soft: #d8d3c4;
            --ink-faint: #7d8b91;
            --rule: #1f2a31;
            --rule-strong: #3a4750;
            --stamp: #d96a5e;
            --archive: #5cba9b;
            --highlight: #d4a04c;
        }
        body {
            font-family: 'IBM Plex Serif', Georgia, serif;
            background-color: var(--paper);
            color: var(--ink-soft);
            background-image:
                repeating-linear-gradient(0deg, rgba(160,180,170,0.025) 0 1px, transparent 1px 3px),
                url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='180' height='180'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 0.55  0 0 0 0 0.62  0 0 0 0 0.58  0 0 0 0.04 0'/></filter><rect width='100%25' height='100%25' filter='url(%23n)'/></svg>");
            background-attachment: fixed;
        }
        .font-display { font-family: 'Fraunces', 'IBM Plex Serif', Georgia, serif; font-optical-sizing: auto; }
        .font-mono-tx { font-family: 'IBM Plex Mono', ui-monospace, monospace; }
        .font-hand { font-family: 'Caveat', cursive; }
        ::selection { background-color: var(--highlight); color: var(--paper); }

        .stamp-rojo {
            font-family: 'IBM Plex Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            font-size: 10px;
            font-weight: 700;
            color: var(--stamp);
            border: 2px solid var(--stamp);
            padding: 6px 10px;
            transform: rotate(-3deg);
            background-color: rgba(217, 106, 94, 0.06);
            display: inline-block;
        }
        .form-tag {
            font-family: 'IBM Plex Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            font-size: 10px;
            color: var(--ink-faint);
            border: 1px solid var(--rule-strong);
            padding: 4px 9px;
            display: inline-block;
        }
        .hairline-bottom {
            background-image: linear-gradient(to right, var(--rule-strong) 50%, transparent 0%);
            background-size: 6px 1px;
            background-repeat: repeat-x;
            background-position: 0 100%;
        }
        .perfo-side {
            background-image: radial-gradient(circle at center, var(--paper-deep) 2.5px, transparent 3px);
            background-size: 14px 22px;
            background-repeat: repeat;
            opacity: 0.3;
        }
    </style>
</head>
<body class="antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:bg-[color:var(--stamp)] focus:text-[color:var(--paper)] focus:px-4 focus:py-2 focus:rounded">Saltar al contenido</a>

    {{-- ░░░░ CABECERA REGISTRO TIPO EXPEDIENTE ░░░░ --}}
    <header class="border-b border-[color:var(--rule)]">
        <div class="max-w-6xl mx-auto px-6 py-3 flex flex-wrap items-center gap-x-6 gap-y-1 text-[10.5px] uppercase tracking-[0.18em] font-mono-tx text-[color:var(--ink-faint)]">
            <span><span class="text-[color:var(--ink-faint)]">Tomo</span> <strong class="text-[color:var(--ink)]">API · v1</strong></span>
            <span class="hidden sm:inline">·</span>
            <span><span class="text-[color:var(--ink-faint)]">Reg.</span> <span class="text-[color:var(--ink)]">{{ $today }}</span></span>
            <span class="hidden sm:inline">·</span>
            <span>Reino de España</span>
            <span class="ml-auto text-[color:var(--archive)]">■ Documento público</span>
        </div>
    </header>

    {{-- ░░░░ CUERPO ░░░░ --}}
    <main id="main" class="relative max-w-6xl mx-auto px-6 sm:px-10 lg:px-16 py-12 sm:py-16">

        {{-- Perforaciones laterales --}}
        <div class="absolute inset-y-0 left-0 w-7 hidden md:block perfo-side pointer-events-none"></div>
        <div class="absolute inset-y-0 right-0 w-7 hidden md:block perfo-side pointer-events-none"></div>

        {{-- ░░ HEADER PRINCIPAL ░░ --}}
        <section class="relative grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 mb-16">
            <div class="lg:col-span-8 relative">
                {{-- Sello rotado --}}
                <div class="stamp-rojo absolute -top-1 right-0 lg:right-auto lg:-left-2 z-10 hidden sm:inline-block">● Anexo B</div>

                <div class="flex items-center gap-3 mb-6">
                    <span class="form-tag">Asunto</span>
                    <span class="font-mono-tx text-[12px] tracking-[0.14em] uppercase text-[color:var(--ink-soft)]">
                        Memoria técnica · API REST pública · GobTracker
                    </span>
                </div>

                <h1 class="font-display text-[44px] sm:text-[56px] lg:text-[72px] leading-[0.94] tracking-[-0.02em] text-[color:var(--ink)] mb-6"
                    style="font-variation-settings: 'opsz' 144, 'SOFT' 50, 'WONK' 1;">
                    Construye sobre<br>
                    <em class="not-italic" style="font-variation-settings: 'opsz' 144, 'SOFT' 100, 'WONK' 1; font-style: italic;">el archivo</em>
                    <span class="text-[color:var(--stamp)]">.</span>
                </h1>

                <p class="text-[18px] sm:text-[19px] leading-[1.5] text-[color:var(--ink-soft)] max-w-[60ch] mb-5 first-letter:font-display first-letter:text-[64px] first-letter:leading-[0.85] first-letter:font-bold first-letter:float-left first-letter:mr-3 first-letter:mt-1.5 first-letter:text-[color:var(--ink)]">
                    Una API REST limpia y abierta sobre la
                    <a href="https://contrataciondelestado.es" class="text-[color:var(--stamp)] underline decoration-dotted underline-offset-2 hover:decoration-solid transition">Plataforma de Contratación del Sector Público</a>:
                    licitaciones, adjudicaciones, modificaciones, organismos contratantes y empresas adjudicatarias del Estado español. Sin claves de acceso, documentada con OpenAPI 3.1.
                </p>

                <p class="font-hand text-[20px] text-[color:var(--stamp)] mb-2">
                    ↳ También disponible vía MCP — añade /mcp como conector en Claude Desktop o cualquier cliente compatible.
                </p>
            </div>

            {{-- Sumario técnico tipo comprobante --}}
            <aside class="lg:col-span-4 lg:pl-8 lg:border-l lg:border-dashed lg:border-[color:var(--rule-strong)]">
                <p class="font-mono-tx text-[11px] uppercase tracking-[0.22em] text-[color:var(--ink-faint)] mb-4">
                    ◆ Comprobante técnico
                </p>
                <dl class="space-y-2">
                    @php
                        $tech = [
                            ['Auth', 'Pública — sin claves'],
                            ['Spec', 'OpenAPI 3.1 + JSON:API'],
                            ['Cache', 'Cloudflare edge + Redis'],
                            ['Throttle', '100/min · IP'],
                            ['Datos', 'Sincronizados a diario'],
                            ['MCP', '/mcp · 19 tools'],
                        ];
                    @endphp
                    @foreach ($tech as [$k, $v])
                        <div class="flex items-baseline justify-between gap-3 hairline-bottom pb-1.5">
                            <dt class="font-mono-tx text-[10.5px] uppercase tracking-[0.2em] text-[color:var(--ink-faint)]">{{ $k }}</dt>
                            <dd class="font-mono-tx text-[12px] text-[color:var(--ink-soft)] text-right">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </aside>
        </section>

        {{-- ░░ § I — SUMARIO CUANTITATIVO ░░ --}}
        <section aria-label="Estadísticas globales" class="mb-16">
            <div class="border-b border-[color:var(--rule)] pb-4 mb-8 flex items-baseline justify-between flex-wrap gap-4">
                <div>
                    <p class="font-mono-tx text-[11px] uppercase tracking-[0.22em] text-[color:var(--ink-faint)] mb-1">§ I — Sumario cuantitativo</p>
                    <h2 class="font-display text-[28px] sm:text-[34px] leading-tight text-[color:var(--ink)]" style="font-variation-settings: 'opsz' 144, 'SOFT' 30;">Lo que figura indexado<span class="text-[color:var(--stamp)]">.</span></h2>
                </div>
                @if ($lastSnapshot)
                    <p class="font-mono-tx text-[10.5px] uppercase tracking-[0.18em] text-[color:var(--archive)]">
                        ◆ Última sincronización · <span class="text-[color:var(--ink-soft)]">{{ $lastSnapshot }}</span>
                    </p>
                @endif
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-px bg-[color:var(--rule)]">
                @foreach ([
                    ['Contratos', 'Nº 01', number_format($totalContracts, 0, ',', '.')],
                    ['Órganos', 'Nº 02', number_format($totalOrgs, 0, ',', '.')],
                    ['Empresas', 'Nº 03', number_format($totalCompanies, 0, ',', '.')],
                    ['Importe total', 'Nº 04', number_format($totalAmount / 1_000_000_000, 2, ',', '.') . ' B€'],
                ] as [$label, $num, $val])
                    <div class="bg-[color:var(--paper)] px-5 py-6">
                        <div class="flex items-baseline justify-between mb-3">
                            <span class="font-mono-tx text-[11px] tracking-[0.18em] text-[color:var(--ink-faint)]">{{ $num }}</span>
                            <span class="font-mono-tx text-[10px] uppercase tracking-[0.18em] text-[color:var(--archive)]">{{ strtoupper($label) }}</span>
                        </div>
                        <div class="font-display tabular-nums text-[36px] sm:text-[44px] leading-none font-medium text-[color:var(--ink)] tracking-[-0.02em]" style="font-variation-settings: 'opsz' 144;">{{ $val }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ░░ § II — EJEMPLOS curl como cinta de impresión matricial ░░ --}}
        <section class="mb-16">
            <div class="border-b border-[color:var(--rule)] pb-4 mb-8">
                <p class="font-mono-tx text-[11px] uppercase tracking-[0.22em] text-[color:var(--ink-faint)] mb-1">§ II — Empieza en 30 segundos</p>
                <h2 class="font-display text-[28px] sm:text-[34px] leading-tight text-[color:var(--ink)]" style="font-variation-settings: 'opsz' 144, 'SOFT' 30;">Pruébala en el terminal<span class="text-[color:var(--stamp)]">.</span></h2>
            </div>

            <div class="border border-[color:var(--rule-strong)]" style="background-color: var(--paper-deep);">
                <div class="flex items-center justify-between px-5 py-2.5 border-b border-[color:var(--rule)]" style="background: linear-gradient(180deg, #11181d, #0a1014);">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#d96a5e]"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-[#d4a04c]"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-[#5cba9b]"></span>
                    </div>
                    <span class="font-mono-tx text-[10px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)]">$ terminal · gobtracker · curl 8.4 · UTF-8</span>
                    <span class="font-mono-tx text-[10px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)] tabular-nums">Folio 02/06</span>
                </div>

                <div class="p-5 sm:p-6 space-y-5 font-mono-tx text-[12.5px] leading-[1.55]">
                    @foreach ([
                        ['Listar últimas adjudicaciones', '/api/v1/contracts?filter[status_code]=ADJ&sort=-snapshot_updated_at'],
                        ['Ficha de contrato con timeline y adjudicación', '/api/v1/contracts/19066873?include=lots.awards.company,notices,modifications'],
                        ['Stats agregadas de un órgano de contratación', '/api/v1/organizations/1/stats'],
                        ['Búsqueda full-text en pliegos', '/api/v1/contracts?filter[search]=puente+autopista'],
                        ['Concesión BDNS por ID', '/api/v1/subsidies/grants/150295544'],
                    ] as [$note, $path])
                        <div>
                            <div class="text-[#5e6c73]">› <span class="text-[#7d8b91]">// {{ $note }}</span></div>
                            <div class="flex flex-wrap gap-x-2 mt-1">
                                <span class="text-[#5cba9b]">$</span>
                                <span class="text-[#d8d3c4]">curl</span>
                                <span class="text-[#d4a04c] break-all">"{{ $appUrl }}{{ $path }}"</span>
                            </div>
                        </div>
                    @endforeach
                    <div class="flex items-center gap-2 pt-1">
                        <span class="text-[#5cba9b]">$</span>
                        <span class="inline-block w-2.5 h-[14px] bg-[#5cba9b] animate-pulse"></span>
                    </div>
                </div>

                <div class="border-t border-dashed border-[color:var(--rule)] px-5 py-2 flex items-center justify-between font-mono-tx text-[10px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)]">
                    <span>—— corte aquí ✂</span>
                    <span>cc-by 4.0</span>
                    <span>v1 / estable</span>
                </div>
            </div>
        </section>

        {{-- ░░ § III — TOP ÓRGANOS ░░ --}}
        @if (! empty($topOrgs))
            <section class="mb-16">
                <div class="border-b border-[color:var(--rule)] pb-4 mb-8">
                    <p class="font-mono-tx text-[11px] uppercase tracking-[0.22em] text-[color:var(--ink-faint)] mb-1">§ III — Anexo I</p>
                    <h2 class="font-display text-[28px] sm:text-[34px] leading-tight text-[color:var(--ink)]" style="font-variation-settings: 'opsz' 144, 'SOFT' 30;">Top 10 órganos por importe adjudicado<span class="text-[color:var(--stamp)]">.</span></h2>
                </div>
                <div class="border border-[color:var(--rule)] overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-[color:var(--paper-edge)]">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left font-mono-tx text-[10.5px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)] font-semibold">Nº</th>
                                    <th scope="col" class="px-4 py-3 text-left font-mono-tx text-[10.5px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)] font-semibold">Órgano</th>
                                    <th scope="col" class="px-4 py-3 text-right font-mono-tx text-[10.5px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)] font-semibold">Contratos</th>
                                    <th scope="col" class="px-4 py-3 text-right font-mono-tx text-[10.5px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)] font-semibold">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topOrgs as $i => $row)
                                    <tr class="border-t border-dashed border-[color:var(--rule)] hover:bg-[color:var(--paper-edge)] transition-colors">
                                        <td class="px-4 py-3 font-mono-tx text-[12px] text-[color:var(--ink-faint)] tabular-nums">[{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}]</td>
                                        <td class="px-4 py-3 text-[14px] text-[color:var(--ink-soft)]">{{ $row['name'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right font-mono-tx text-[12px] tabular-nums text-[color:var(--ink-soft)]">{{ number_format($row['contracts'] ?? 0, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right font-display text-[15px] tabular-nums text-[color:var(--ink)]" style="font-variation-settings: 'opsz' 96;">{{ number_format(($row['total'] ?? 0) / 1_000_000, 2, ',', '.') }} M€</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        {{-- ░░ § IV — RECURSOS ░░ --}}
        <section aria-label="Recursos" class="mb-16">
            <div class="border-b border-[color:var(--rule)] pb-4 mb-8">
                <p class="font-mono-tx text-[11px] uppercase tracking-[0.22em] text-[color:var(--ink-faint)] mb-1">§ IV — Anexo II · Recursos</p>
                <h2 class="font-display text-[28px] sm:text-[34px] leading-tight text-[color:var(--ink)]" style="font-variation-settings: 'opsz' 144, 'SOFT' 30;">Para integrar<span class="text-[color:var(--stamp)]">.</span></h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-px bg-[color:var(--rule)]">
                <a href="/docs" class="bg-[color:var(--paper)] hover:bg-[color:var(--paper-edge)] p-6 transition-colors group">
                    <div class="flex items-baseline justify-between mb-3">
                        <span class="font-mono-tx text-[10.5px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)]">[01]</span>
                        <svg class="w-4 h-4 text-[color:var(--ink-faint)] group-hover:text-[color:var(--stamp)] group-hover:translate-x-0.5 transition-all" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    </div>
                    <div class="font-display text-[20px] text-[color:var(--ink)] mb-1 group-hover:text-[color:var(--stamp)] transition-colors" style="font-variation-settings: 'opsz' 96;">Documentación interactiva</div>
                    <div class="text-[13px] leading-relaxed text-[color:var(--ink-faint)]">Todos los endpoints, filtros e includes. Pruébalos en el navegador.</div>
                </a>
                <a href="/openapi.json" class="bg-[color:var(--paper)] hover:bg-[color:var(--paper-edge)] p-6 transition-colors group">
                    <div class="flex items-baseline justify-between mb-3">
                        <span class="font-mono-tx text-[10.5px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)]">[02]</span>
                        <svg class="w-4 h-4 text-[color:var(--ink-faint)] group-hover:text-[color:var(--stamp)] group-hover:translate-x-0.5 transition-all" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H7a2 2 0 0 0-2 2v5a2 2 0 0 1-2 2 2 2 0 0 1 2 2v5a2 2 0 0 0 2 2h1"/><path d="M16 21h1a2 2 0 0 0 2-2v-5a2 2 0 0 1 2-2 2 2 0 0 1-2-2V5a2 2 0 0 0-2-2h-1"/></svg>
                    </div>
                    <div class="font-display text-[20px] text-[color:var(--ink)] mb-1 group-hover:text-[color:var(--stamp)] transition-colors" style="font-variation-settings: 'opsz' 96;">OpenAPI 3.1 spec</div>
                    <div class="text-[13px] leading-relaxed text-[color:var(--ink-faint)]">Genera clientes en cualquier lenguaje con openapi-generator.</div>
                </a>
                <a href="https://github.com/christianpasinrey/escanerpublico-backend" class="bg-[color:var(--paper)] hover:bg-[color:var(--paper-edge)] p-6 transition-colors group">
                    <div class="flex items-baseline justify-between mb-3">
                        <span class="font-mono-tx text-[10.5px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)]">[03]</span>
                        <svg class="w-[18px] h-[18px] text-[color:var(--ink-faint)] group-hover:text-[color:var(--stamp)] transition-colors" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                            <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z"/>
                        </svg>
                    </div>
                    <div class="font-display text-[20px] text-[color:var(--ink)] mb-1 group-hover:text-[color:var(--stamp)] transition-colors" style="font-variation-settings: 'opsz' 96;">Código backend (MIT)</div>
                    <div class="text-[13px] leading-relaxed text-[color:var(--ink-faint)]">Laravel + Spatie QueryBuilder + Dedoc Scramble. Issues y PRs bienvenidos.</div>
                </a>
            </div>
        </section>

        {{-- ░░ § V — MCP ░░ --}}
        <section aria-label="MCP" class="mb-16">
            <div class="border-b border-[color:var(--rule)] pb-4 mb-8">
                <p class="font-mono-tx text-[11px] uppercase tracking-[0.22em] text-[color:var(--archive)] mb-1">§ V — Anexo III · Acceso por agente</p>
                <h2 class="font-display text-[28px] sm:text-[34px] leading-tight text-[color:var(--ink)]" style="font-variation-settings: 'opsz' 144, 'SOFT' 30;">Conector MCP nativo<span class="text-[color:var(--stamp)]">.</span></h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <p class="text-[15px] leading-[1.55] text-[color:var(--ink-soft)] mb-4">
                        Compatible con Claude Desktop, ChatGPT, Cursor y cualquier cliente que hable Model Context Protocol.
                        19 herramientas que cubren los 7 módulos del proyecto.
                    </p>
                    <p class="font-hand text-[18px] text-[color:var(--stamp)]" style="display: inline-block; transform: rotate(-1deg);">↳ pega la URL en Settings → Connectors</p>
                </div>
                <div class="border border-[color:var(--rule-strong)] p-4" style="background-color: var(--paper-deep);">
                    <p class="font-mono-tx text-[10px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)] mb-2">URL del servidor MCP</p>
                    <code class="block font-mono-tx text-[13px] text-[color:var(--archive)] break-all">{{ $appUrl }}/mcp</code>
                    <div class="hairline-bottom my-3"></div>
                    <p class="font-mono-tx text-[10px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)] mb-2">Comprobación rápida</p>
                    <code class="block font-mono-tx text-[12px] text-[color:var(--ink-soft)] break-all">curl -X POST {{ $appUrl }}/mcp -H "Accept: application/json,text/event-stream" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'</code>
                </div>
            </div>
        </section>

        {{-- ░░ § VI — RATE LIMIT + LICENCIA ░░ --}}
        <section aria-label="Rate limits" class="mb-12 border border-dashed border-[color:var(--rule-strong)] p-5 bg-[color:var(--paper-deep)]">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="font-mono-tx text-[10.5px] uppercase tracking-[0.22em] text-[color:var(--ink-faint)] mb-1">◆ Rate limit</p>
                    <p class="text-[14px] leading-relaxed text-[color:var(--ink-soft)]">100 req/min por IP sin autenticación. Cabeceras <code class="font-mono-tx bg-[color:var(--paper-edge)] px-1.5 py-0.5 text-[color:var(--archive)]">X-RateLimit-Limit / -Remaining / Retry-After</code> en cada respuesta.</p>
                </div>
                <div>
                    <p class="font-mono-tx text-[10.5px] uppercase tracking-[0.22em] text-[color:var(--ink-faint)] mb-1">◆ Licencia</p>
                    <p class="text-[14px] leading-relaxed text-[color:var(--ink-soft)]">Reutilización <strong class="text-[color:var(--archive)]">CC-BY 4.0</strong>. Cita la fuente original (PLACSP) cuando reutilices los datos.</p>
                </div>
            </div>
        </section>

        {{-- ░░ FOOTER COLOFÓN ░░ --}}
        <footer class="border-t-2 border-[color:var(--ink)] pt-8 text-[12px] leading-relaxed">
            <p class="text-[color:var(--ink-soft)] mb-2">Datos oficiales de PLACSP (Plataforma de Contratación del Sector Público). Escáner Público no es una entidad pública — solo una capa técnica de presentación que reestructura datos abiertos.</p>
            <div class="flex flex-wrap items-center justify-between gap-3 mt-6 pt-6 border-t border-dashed border-[color:var(--rule-strong)] font-mono-tx text-[10px] uppercase tracking-[0.18em] text-[color:var(--ink-faint)]">
                <p>© {{ $year }} GobTracker · Reutilización CC-BY 4.0</p>
                <p class="font-hand text-[20px] text-[color:var(--stamp)] normal-case tracking-normal" style="transform: rotate(-1deg);">GobTracker</p>
                <p>Última snapshot · <span class="text-[color:var(--ink-soft)] tabular-nums">{{ $lastSnapshot ?? 'N/A' }}</span></p>
            </div>
            <p class="mt-6 text-center font-mono-tx text-[10px] uppercase tracking-[0.32em] text-[color:var(--ink-faint)]">▬▬▬ Fin del documento ▬▬▬</p>
        </footer>
    </main>
</body>
</html>
