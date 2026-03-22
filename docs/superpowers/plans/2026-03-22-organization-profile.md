# Organization Profile Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a professional organization profile page with listing, stats dashboard, ECharts graphs, Leaflet map, and contact info.

**Architecture:** One new backend stats endpoint on OrganizationController + index enhancement. Two new Nuxt pages: listing (`/organismos`) and profile (`/organismos/:id`). Profile uses vue-leaflet for map with Nominatim geocoding, vue-echarts for charts, and consumes the existing organization + new stats endpoints.

**Tech Stack:** Laravel 12 (backend), Nuxt 3 + Vue 3 + Tailwind v4 (frontend), vue-echarts/echarts (charts), @vue-leaflet/vue-leaflet + leaflet (map), Nominatim API (geocoding)

---

## File Structure

### Backend

| Action | File | Responsibility |
|--------|------|----------------|
| Modify | `app/Modules/Contracts/Http/Controllers/OrganizationController.php` | Add `stats()` method, enhance `index()` with total_amount |
| Modify | `app/Modules/Contracts/Routes/api.php` | Add stats route |

### Frontend

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `pages/organismos/index.vue` | Organization listing with search/sort/pagination |
| Create | `pages/organismos/[id].vue` | Full profile page with charts, map, stats |

---

## Task 1: Backend — Stats endpoint + index enhancement

**Files:**
- Modify: `escanerpublico-backend/app/Modules/Contracts/Http/Controllers/OrganizationController.php`
- Modify: `escanerpublico-backend/app/Modules/Contracts/Routes/api.php`

- [ ] **Step 1: Add stats route**

In `app/Modules/Contracts/Routes/api.php`, add before the `{organization}` show route:

```php
Route::get('/organizations/{organization}/stats', [OrganizationController::class, 'stats']);
```

IMPORTANT: This must come BEFORE the `{organization}` route to avoid route conflict.

- [ ] **Step 2: Add stats() method to OrganizationController**

Add these imports at the top:
```php
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Award;
use Illuminate\Support\Facades\DB;
```

Add this method:

```php
public function stats(Organization $organization): JsonResponse
{
    $orgId = $organization->id;

    $totalContracts = Contract::where('organization_id', $orgId)->count();
    $totalAmount = (float) Contract::where('organization_id', $orgId)->sum('importe_con_iva');
    $avgAmount = $totalContracts > 0 ? round($totalAmount / $totalContracts, 2) : 0;

    $uniqueCompanies = Award::whereHas('contract', fn($q) => $q->where('organization_id', $orgId))
        ->distinct('company_id')
        ->count('company_id');

    $byStatus = Contract::where('organization_id', $orgId)
        ->selectRaw('status_code, count(*) as total')
        ->groupBy('status_code')
        ->pluck('total', 'status_code');

    $byType = Contract::where('organization_id', $orgId)
        ->whereNotNull('tipo_contrato_code')
        ->selectRaw('tipo_contrato_code, count(*) as total')
        ->groupBy('tipo_contrato_code')
        ->pluck('total', 'tipo_contrato_code');

    $byYear = Contract::where('organization_id', $orgId)
        ->selectRaw('YEAR(created_at) as year, SUM(importe_con_iva) as total')
        ->groupBy('year')
        ->orderBy('year')
        ->pluck('total', 'year')
        ->map(fn($v) => round((float) $v, 2));

    $topCompanies = DB::table('awards')
        ->join('contracts', 'awards.contract_id', '=', 'contracts.id')
        ->join('companies', 'awards.company_id', '=', 'companies.id')
        ->where('contracts.organization_id', $orgId)
        ->select(
            'companies.id',
            'companies.name',
            'companies.nif',
            DB::raw('COUNT(*) as contracts_count'),
            DB::raw('SUM(awards.amount) as total_amount')
        )
        ->groupBy('companies.id', 'companies.name', 'companies.nif')
        ->orderByDesc('total_amount')
        ->limit(10)
        ->get();

    $recentContracts = Contract::where('organization_id', $orgId)
        ->select('id', 'objeto', 'status_code', 'importe_con_iva', 'updated_at')
        ->orderByDesc('updated_at')
        ->limit(10)
        ->get();

    return response()->json([
        'total_contracts' => $totalContracts,
        'total_amount' => $totalAmount,
        'avg_amount' => $avgAmount,
        'unique_companies' => $uniqueCompanies,
        'by_status' => $byStatus,
        'by_type' => $byType,
        'by_year' => $byYear,
        'top_companies' => $topCompanies,
        'recent_contracts' => $recentContracts,
    ]);
}
```

- [ ] **Step 3: Enhance index() to include total_amount**

Replace the return in `index()`:

```php
return response()->json(
    $query->withCount('contracts')
        ->withSum('contracts', 'importe_con_iva')
        ->orderByDesc('contracts_count')
        ->paginate($request->input('per_page', 25))
);
```

This adds `contracts_sum_importe_con_iva` to each organization in the listing.

- [ ] **Step 4: Verify**

```bash
curl -s localhost:8000/api/v1/organizations/1/stats | python -m json.tool | head -20
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Http/Controllers/OrganizationController.php app/Modules/Contracts/Routes/api.php
git commit -m "feat: add organization stats endpoint and index total_amount"
```

---

## Task 2: Frontend — Install leaflet dependencies

**Files:**
- Modify: `escanerpublico-frontend/package.json`

- [ ] **Step 1: Install packages**

```bash
cd /c/laragon/www/escanerpublico-frontend
npm install @vue-leaflet/vue-leaflet leaflet
```

- [ ] **Step 2: Commit**

```bash
git add package.json package-lock.json
git commit -m "feat: install vue-leaflet and leaflet for organization map"
```

---

## Task 3: Frontend — Organization listing page

**Files:**
- Create: `escanerpublico-frontend/pages/organismos/index.vue`

- [ ] **Step 1: Create the listing page**

Create directory `pages/organismos/` if needed, then create `index.vue`.

The page follows the same pattern as `pages/contratos.vue`:
- Search input (q param)
- Table with columns: Name, NIF, Contracts, Total Amount
- Sortable by contracts_count and contracts_sum_importe_con_iva
- Pagination (25/page)
- Rows navigate to `/organismos/:id`

The API returns organizations with `contracts_count` and `contracts_sum_importe_con_iva` fields from the enhanced index endpoint.

Key interfaces:
```typescript
interface Organization {
  id: number
  name: string | null
  identifier: string | null
  nif: string | null
  contracts_count: number
  contracts_sum_importe_con_iva: string | null
}
```

Use `useApi<PaginatedResponse>('/organizations', { query: queryParams, watch: [queryParams], lazy: true, server: false })`.

Follow the exact same UI patterns as contratos.vue: white cards, rounded-xl borders, indigo accent, same search bar style, same pagination buttons.

- [ ] **Step 2: Commit**

```bash
git add pages/organismos/index.vue
git commit -m "feat: add organization listing page with search and pagination"
```

---

## Task 4: Frontend — Organization profile page

**Files:**
- Create: `escanerpublico-frontend/pages/organismos/[id].vue`

This is the main task. The page makes two API calls:
1. `GET /organizations/{id}` — org data with addresses, contacts
2. `GET /organizations/{id}/stats` — all statistics

- [ ] **Step 1: Create the profile page**

The page structure:

```
Header (full width)
  ├── Back arrow → /organismos
  ├── Org name (xl bold)
  ├── NIF badge + type code badge
  └── Hierarchy breadcrumb

Grid (lg:grid-cols-3)
  ├── Left (lg:col-span-2)
  │   ├── Stats cards (4-col grid): total contracts, total amount, avg amount, unique companies
  │   ├── Chart: Amount by year (ECharts bar)
  │   ├── Chart: By contract type (ECharts donut)
  │   ├── Top companies table
  │   └── Recent contracts table
  │
  └── Right (lg:col-span-1)
      ├── Map (vue-leaflet with Nominatim geocoding)
      ├── Contact info (phone, email, web, fax)
      ├── Address
      └── Contracts by status (badges + counts)
```

Key implementation details:

**Data fetching:**
```typescript
const { data: orgData } = await useApi<Organization>(`/organizations/${route.params.id}`, { lazy: true, server: false })
const { data: stats } = await useApi<Stats>(`/organizations/${route.params.id}/stats`, { lazy: true, server: false })
```

**Geocoding (in onMounted or watch):**
```typescript
const mapCenter = ref<[number, number] | null>(null)

async function geocodeAddress() {
  const addr = orgData.value?.addresses?.[0]
  if (!addr?.line) return

  const q = [addr.line, addr.postal_code, addr.city?.name, addr.country?.name].filter(Boolean).join(', ')
  try {
    const res = await $fetch<any[]>(`https://nominatim.openstreetmap.org/search`, {
      params: { format: 'json', q, limit: 1 },
      headers: { 'User-Agent': 'EscanerPublico/1.0' },
    })
    if (res?.[0]) {
      mapCenter.value = [parseFloat(res[0].lat), parseFloat(res[0].lon)]
    }
  } catch { /* silently fail — hide map */ }
}
```

**Map (vue-leaflet):**
```vue
<div v-if="mapCenter" class="h-64 rounded-lg overflow-hidden">
  <LMap :zoom="15" :center="mapCenter" :use-global-leaflet="false">
    <LTileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" attribution="&copy; OpenStreetMap" />
    <LMarker :lat-lng="mapCenter" />
  </LMap>
</div>
```

Import leaflet CSS in the component or in nuxt.config:
```typescript
import 'leaflet/dist/leaflet.css'
```

**ECharts (bar chart for by_year):**
```typescript
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { BarChart, PieChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, GridComponent, LegendComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

use([BarChart, PieChart, TitleComponent, TooltipComponent, GridComponent, LegendComponent, CanvasRenderer])
```

Bar chart option:
```typescript
const yearChartOption = computed(() => {
  const years = Object.keys(stats.value?.by_year || {})
  const amounts = Object.values(stats.value?.by_year || {})
  return {
    tooltip: { trigger: 'axis' },
    xAxis: { type: 'category', data: years },
    yAxis: { type: 'value', axisLabel: { formatter: (v: number) => `${(v / 1e6).toFixed(1)}M` } },
    series: [{ type: 'bar', data: amounts, color: '#6366f1' }],
  }
})
```

Donut chart option:
```typescript
const tipoLabels: Record<string, string> = { '1': 'Obras', '2': 'Servicios', '3': 'Suministros', ... }

const typeChartOption = computed(() => ({
  tooltip: { trigger: 'item' },
  series: [{
    type: 'pie', radius: ['40%', '70%'],
    data: Object.entries(stats.value?.by_type || {}).map(([code, count]) => ({
      name: tipoLabels[code] || code, value: count,
    })),
  }],
}))
```

**Contact helpers** (same pattern as [id].vue contrato):
```typescript
function getContact(type: string) { return orgData.value?.contacts?.find(c => c.type === type)?.value ?? null }
const address = computed(() => orgData.value?.addresses?.[0] ?? null)
```

**Status badges** (same colors as contratos):
```typescript
const statusLabels = { PRE: 'Anuncio previo', PUB: 'En plazo', ... }
const statusColors = { PRE: 'bg-slate-100 text-slate-700', PUB: 'bg-blue-100 text-blue-700', ... }
```

**Formatting helpers** (same as contratos):
```typescript
function fmt(val) { ... } // EUR currency
function fmtShort(val) { ... } // EUR no decimals
function fmtDate(val) { ... } // es-ES date
```

**Top companies table:**
```vue
<table>
  <thead>...</thead>
  <tbody>
    <tr v-for="company in stats.top_companies" :key="company.id">
      <td>{{ company.name }}</td>
      <td>{{ company.nif }}</td>
      <td>{{ company.contracts_count }}</td>
      <td>{{ fmt(company.total_amount) }}</td>
    </tr>
  </tbody>
</table>
```

**Recent contracts table:**
```vue
<tr v-for="c in stats.recent_contracts" :key="c.id" @click="router.push(`/contratos/${c.id}`)" class="cursor-pointer hover:bg-gray-50">
  <td class="line-clamp-1">{{ c.objeto }}</td>
  <td><span :class="statusColors[c.status_code]">{{ statusLabels[c.status_code] }}</span></td>
  <td>{{ fmtShort(c.importe_con_iva) }}</td>
  <td>{{ fmtDate(c.updated_at) }}</td>
</tr>
```

- [ ] **Step 2: Commit**

```bash
git add pages/organismos/\[id\].vue
git commit -m "feat: add organization profile page with charts, map, stats"
```

---

## Task 5: Navigation — Add organismos to nav

**Files:**
- Modify: `escanerpublico-frontend/layouts/default.vue`

- [ ] **Step 1: Add nav link**

Read the current layout file. Add a "Organismos" link next to the existing "Contratos" link in the navbar, pointing to `/organismos`.

- [ ] **Step 2: Commit**

```bash
git add layouts/default.vue
git commit -m "feat: add Organismos link to navigation"
```

---

## Summary

| Task | What |
|------|------|
| 1 | Backend: stats endpoint + index enhancement |
| 2 | Frontend: install vue-leaflet + leaflet |
| 3 | Frontend: organization listing page |
| 4 | Frontend: organization profile page (charts, map, stats, tables) |
| 5 | Frontend: add nav link |
