# Organization Profile Page — Design Spec

## Goal

Professional organization profile page with listing, detailed stats, ECharts graphs, Leaflet map, and contact info. Two new frontend pages (`/organismos` and `/organismos/:id`) plus one new backend stats endpoint.

## Pages

### `/organismos` — Listing

- Search by name, NIF, DIR3 code
- Table columns: name, NIF, contracts count, total amount
- Sortable by contracts_count / total amount
- Paginated (25/page)
- Rows link to `/organismos/:id`

### `/organismos/:id` — Profile

**Layout**: 2-column grid on desktop (lg:grid-cols-3), stacked on mobile.

#### Left column (lg:col-span-2)

1. **Header**
   - Organization name (large)
   - NIF badge, type code badge
   - Hierarchy breadcrumb (reversed, separated by `/`)

2. **Stats cards** (4-column grid)
   - Total contracts
   - Total amount (formatted EUR)
   - Average amount per contract
   - Unique awarded companies

3. **Chart: Amount by year** (ECharts bar chart)
   - X axis: years
   - Y axis: total amount (EUR)
   - Data from `stats.by_year`

4. **Chart: Distribution by contract type** (ECharts donut/pie)
   - Segments: Obras, Servicios, Suministros, etc.
   - Data from `stats.by_type` mapped through TIPO_LABELS

5. **Top awarded companies** (table)
   - Columns: company name, NIF, contracts count, total amount
   - Top 10, ordered by total_amount desc
   - Company name links to `/empresas/:id` (future)

6. **Recent contracts** (table)
   - Columns: objeto (truncated), status badge, amount, date
   - Last 10, ordered by updated_at desc
   - Rows link to `/contratos/:id`

#### Right column (lg:col-span-1)

1. **Map** (vue-leaflet)
   - Pin on organization address
   - Geocoding via Nominatim API: `https://nominatim.openstreetmap.org/search?format=json&q={address},{city},{country}`
   - Zoom level 15
   - If geocoding fails or no address: hide map section
   - Tile layer: OpenStreetMap default

2. **Contact info** (from polymorphic contacts)
   - Phone (icon + value)
   - Email (icon + mailto link)
   - Website (icon + external link)
   - Fax (icon + value, if present)

3. **Address** (from polymorphic addresses)
   - Street line
   - Postal code + city name
   - Country name

4. **Contracts by status** (vertical list)
   - Each status with colored badge + count
   - PRE, PUB, EV, ADJ, RES, ANUL
   - Same color scheme as contracts page

## Backend

### Existing endpoint (no changes needed)

`GET /api/v1/organizations/:id` — already returns organization with addresses, contacts, contracts_count.

### New endpoint

`GET /api/v1/organizations/:id/stats`

Response:
```json
{
  "total_contracts": 142,
  "total_amount": 45000000.00,
  "avg_amount": 316901.41,
  "unique_companies": 87,
  "by_status": { "ADJ": 45, "RES": 80, "PUB": 10, "EV": 5, "PRE": 2, "ANUL": 0 },
  "by_type": { "1": 30, "2": 95, "3": 17 },
  "by_year": { "2018": 5000000, "2019": 12000000, "2020": 15000000 },
  "top_companies": [
    { "id": 1, "name": "Empresa S.L.", "nif": "B12345678", "contracts_count": 12, "total_amount": 3500000.00 }
  ],
  "recent_contracts": [
    { "id": 100, "objeto": "Suministro de...", "status_code": "ADJ", "importe_con_iva": 150000.00, "updated_at": "2026-03-20" }
  ]
}
```

**Query implementation:**
- `total_contracts`: `Contract::where('organization_id', $id)->count()`
- `total_amount`: `Contract::where('organization_id', $id)->sum('importe_con_iva')`
- `avg_amount`: `total_amount / total_contracts`
- `unique_companies`: `Award::whereHas('contract', fn($q) => $q->where('organization_id', $id))->distinct('company_id')->count('company_id')`
- `by_status`: group by status_code, count
- `by_type`: group by tipo_contrato_code, count
- `by_year`: group by YEAR(created_at), sum importe_con_iva
- `top_companies`: join awards → companies, group by company_id, sum amount, order desc, limit 10
- `recent_contracts`: order by updated_at desc, limit 10, select only needed fields

### Route addition

In `app/Modules/Contracts/Routes/api.php`:
```php
Route::get('/organizations/{organization}/stats', [OrganizationController::class, 'stats']);
```

## Frontend dependencies

- `@vue-leaflet/vue-leaflet` — Vue 3 wrapper for Leaflet
- `leaflet` — map library
- `vue-echarts` + `echarts` — already installed, used for charts

## Geocoding

Use Nominatim (OpenStreetMap) free API for address → coordinates:
- Endpoint: `https://nominatim.openstreetmap.org/search`
- Params: `format=json`, `q={line}, {postal_code} {city}, {country}`
- Rate limit: 1 req/sec (no issue for single page loads)
- Cache result in component (no need to persist lat/lng in DB for now)
- User-Agent header required: `EscanerPublico/1.0`

## File structure

### Backend
- Modify: `app/Modules/Contracts/Http/Controllers/OrganizationController.php` — add `stats()` method
- Modify: `app/Modules/Contracts/Routes/api.php` — add stats route

### Frontend
- Create: `pages/organismos/index.vue` — listing page
- Create: `pages/organismos/[id].vue` — profile page
- Install: `@vue-leaflet/vue-leaflet`, `leaflet`

## Design notes

- Follow existing UI patterns (same card style, colors, fonts as contratos pages)
- Same status color scheme: PRE=slate, PUB=blue, EV=amber, ADJ=green, RES=indigo, ANUL=red
- Same currency/date formatting helpers
- Responsive: columns stack on mobile, map goes above contact info
- ECharts charts use indigo as primary color, consistent with app theme
