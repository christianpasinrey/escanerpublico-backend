<?php

use App\Mcp\Servers\GobTrackerServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| Rutas MCP — Model Context Protocol
|--------------------------------------------------------------------------
|
| Servidor MCP de GobTracker / Escáner Público. Expone los datos del
| sector público español (PLACSP, BDNS, BOE, AEAT) como herramientas
| que cualquier cliente compatible con MCP puede consumir: Claude
| Desktop, ChatGPT, Cursor, agentes propios, etc.
|
| Endpoint HTTP streamable: POST/GET https://app.gobtracker.tailor-bytes.com/mcp
|
| API pública sin autenticación — encaja con el ADN del proyecto:
| transparencia y reutilización CC-BY 4.0.
|
*/

// HTTP Streamable transport (Claude Desktop, web clients, etc.).
// Registra automáticamente las rutas POST /mcp y GET /mcp para handshake/SSE.
Mcp::web('mcp', GobTrackerServer::class)
    ->name('mcp.gobtracker');

// STDIO transport para uso local con `php artisan mcp:serve gobtracker`.
Mcp::local('gobtracker', GobTrackerServer::class);
