<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scalar Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Scalar will be accessible from. If this
    | setting is null, Scalar will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */
    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Scalar Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Scalar will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */
    'path' => '/docs',

    /*
    |--------------------------------------------------------------------------
    | Scalar Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Scalar route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Scalar OpenAPI Document URL
    |--------------------------------------------------------------------------
    |
    | This is the URL to the OpenAPI document that Scalar will use to generate
    | the API reference. By default, it points to the latest version of the
    | Scalar Galaxy package. You can change this to use a custom OpenAPI file.
    |
    */
    'url' => '/openapi.json',

    /*
    |--------------------------------------------------------------------------
    | Scalar CDN URL
    |--------------------------------------------------------------------------
    |
    | This is the URL to the CDN where Scalar's API reference assets are hosted.
    | By default, it points to the jsDelivr CDN for the @scalar/api-reference
    | package. You can change this if you want to use a different CDN.
    |
    */
    'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',

    /*
    |--------------------------------------------------------------------------
    | Scalar Configuration
    |--------------------------------------------------------------------------
    |
    | The configuration options for the Scalar API reference. This array
    | contains all the settings that control the behavior and appearance
    | of the API documentation.
    |
    */
    'configuration' => [
        /** A string to use one of the color presets */
        // Tema custom 'Expediente del Estado' — definido en customCss más abajo.
        'theme' => 'none',

        /** The layout to use for the references */
        'layout' => 'modern',

        /** URL to a request proxy for the API client */
        'proxyUrl' => 'https://proxy.scalar.com',

        /** Whether to show the sidebar */
        'showSidebar' => true,

        /**
         * Whether to show models in the sidebar, search, and content.
         */
        'hideModels' => false,

        /**
         * Whether to show the “Download OpenAPI Document” button
         */
        'hideDownloadButton' => false,

        /**
         * Whether to show the “Test Request” button
         */
        'hideTestRequestButton' => false,

        /**
         * Whether to show the sidebar search bar
         */
        'hideSearch' => false,

        /** Whether dark mode is on or off initially (light mode) */
        'darkMode' => false,

        /** forceDarkModeState makes it always this state no matter what*/
        'forceDarkModeState' => 'dark',

        /** Tema fijo dark editorial — el toggle no aplica */
        'hideDarkModeToggle' => true,

        /** Key used with CTRL/CMD to open the search modal (defaults to 'k' e.g. CMD+k) */
        'searchHotKey' => 'k',

        /**
         * If used, passed data will be added to the HTML header
         *
         * @see https://unhead.unjs.io/usage/composables/use-seo-meta
         */
        'metaData' => [
            'title' => 'Escáner Público — API Reference',
        ],

        /**
         * Path to a favicon image
         *
         * @example '/favicon.svg'
         */
        'favicon' => '',

        /**
         * List of httpsnippet clients to hide from the clients menu
         * By default hides Unirest, pass `[]` to show all clients
         */
        'hiddenClients' => [

        ],

        /** Determine the HTTP client that’s selected by default */
        'defaultHttpClient' => [
            'targetId' => 'shell',
            'clientKey' => 'curl',
        ],

        /** Custom CSS — tema 'Expediente del Estado' oscuro (coherente con ApiCallout) */
        'customCss' => <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght,SOFT,WONK@9..144,400..900,0..100,0..1&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Serif:wght@400;500;600;700&family=Caveat:wght@400;600&display=swap');

.dark-mode,
:root.dark-mode,
.scalar-app.dark-mode,
.scalar-api-reference.dark-mode {
    /* Backgrounds — papel calco oscuro tipo carbon copy */
    --scalar-background-1: #0d1318;
    --scalar-background-2: #11181d;
    --scalar-background-3: #0a1014;
    --scalar-background-accent: #1a2229;

    /* Texto — paper sobre dark */
    --scalar-color-1: #e8e6dd;
    --scalar-color-2: #d8d3c4;
    --scalar-color-3: #a8b2b8;
    --scalar-color-disabled: #5e6c73;
    --scalar-color-ghost: #7d8b91;

    /* Acentos — stamp / archive / highlight */
    --scalar-color-accent: #d96a5e;
    --scalar-color-green: #5cba9b;
    --scalar-color-red: #d96a5e;
    --scalar-color-yellow: #d4a04c;
    --scalar-color-orange: #d4a04c;
    --scalar-color-blue: #5cba9b;
    --scalar-color-purple: #d4a04c;

    /* Bordes — rule oscuro */
    --scalar-border-color: #1f2a31;

    /* Botones */
    --scalar-button-1: #d96a5e;
    --scalar-button-1-color: #0d1318;
    --scalar-button-1-hover: #e58576;

    /* Sidebar */
    --scalar-sidebar-background-1: #0a1014;
    --scalar-sidebar-color-1: #d8d3c4;
    --scalar-sidebar-color-2: #a8b2b8;
    --scalar-sidebar-border-color: #1f2a31;
    --scalar-sidebar-item-hover-color: #d96a5e;
    --scalar-sidebar-item-hover-background: #11181d;
    --scalar-sidebar-item-active-background: #11181d;
    --scalar-sidebar-search-background: #11181d;
    --scalar-sidebar-search-border-color: #1f2a31;
    --scalar-sidebar-search--color: #a8b2b8;

    /* Code blocks */
    --scalar-code-language-color-supersede: #d8d3c4;

    /* Bordes redondeados muy moderados — papel no redondea */
    --scalar-radius: 2px;
    --scalar-radius-lg: 3px;
    --scalar-radius-xl: 4px;

    /* Tipografías editoriales */
    --scalar-font: 'IBM Plex Serif', 'Georgia', serif;
    --scalar-font-code: 'IBM Plex Mono', ui-monospace, monospace;

    /* Headings con personalidad — Fraunces variable */
    --scalar-heading-1-color: #e8e6dd;
    --scalar-heading-2-color: #e8e6dd;
    --scalar-heading-3-color: #d8d3c4;
}

/* Body global con grain editorial sutil */
body, .scalar-app {
    background-color: #0d1318 !important;
    background-image:
        repeating-linear-gradient(0deg, rgba(160,180,170,0.025) 0 1px, transparent 1px 3px) !important;
}

/* Headings con Fraunces variable */
.scalar-app h1,
.scalar-app h2 {
    font-family: 'Fraunces', 'IBM Plex Serif', Georgia, serif !important;
    font-variation-settings: 'opsz' 144, 'SOFT' 30 !important;
    letter-spacing: -0.015em !important;
}

/* Bloques de código tipo recibo de máquina matricial */
.scalar-app pre,
.scalar-app code,
.scalar-app .code-block {
    font-family: 'IBM Plex Mono', ui-monospace, monospace !important;
    background-color: #0a1014 !important;
    border: 1px solid #1f2a31 !important;
    border-radius: 2px !important;
}

/* Botones con monospace y tracking ancho */
.scalar-app button,
.scalar-app .scalar-button {
    font-family: 'IBM Plex Mono', ui-monospace, monospace !important;
    text-transform: uppercase !important;
    letter-spacing: 0.16em !important;
    font-weight: 600 !important;
    border-radius: 2px !important;
}

/* Tags de método HTTP — paleta editorial */
.scalar-app .method-tag-get,
.scalar-app [data-method="get"] { background-color: #5cba9b !important; color: #0d1318 !important; }
.scalar-app .method-tag-post,
.scalar-app [data-method="post"] { background-color: #d4a04c !important; color: #0d1318 !important; }
.scalar-app .method-tag-put,
.scalar-app [data-method="put"] { background-color: #5cba9b !important; color: #0d1318 !important; }
.scalar-app .method-tag-patch,
.scalar-app [data-method="patch"] { background-color: #d4a04c !important; color: #0d1318 !important; }
.scalar-app .method-tag-delete,
.scalar-app [data-method="delete"] { background-color: #d96a5e !important; color: #0d1318 !important; }

/* Selección con highlight ocre */
.scalar-app ::selection {
    background-color: #d4a04c !important;
    color: #0d1318 !important;
}

/* Tablas tipo formulario administrativo */
.scalar-app table {
    border-collapse: collapse !important;
}
.scalar-app table th,
.scalar-app table td {
    border-bottom: 1px solid #1f2a31 !important;
}
.scalar-app table th {
    font-family: 'IBM Plex Mono', ui-monospace, monospace !important;
    text-transform: uppercase !important;
    letter-spacing: 0.16em !important;
    font-size: 11px !important;
    color: #7d8b91 !important;
    font-weight: 600 !important;
}
CSS,

        /** Prefill authentication */
        // 'authentication' => [
        //     // TODO
        // ],

        /**
         * The baseServerURL is used when the spec servers are relative paths and we are using SSR.
         * On the client we can grab the window.location.origin but on the server we need
         * to use this prop.
         */
        // 'baseServerURL' => '',

        /**
         * List of servers to override the openapi spec servers
         */
        // 'servers' => [
        //     [
        //         'url' => 'https://api.scalar.com',
        //         'description' => 'Production server',
        //     ],
        // ],

        /**
         * Inter / JetBrains Mono off — usamos las fuentes editoriales
         * cargadas vía customCss (Fraunces, IBM Plex Serif/Mono).
         */
        'withDefaultFonts' => false,

        /**
         * By default we only open the relevant tag based on the url, however if you want all the tags open by default then set this configuration option :)
         */
        'defaultOpenAllTags' => false,
    ],

];
