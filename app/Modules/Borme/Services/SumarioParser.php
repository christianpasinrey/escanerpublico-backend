<?php

namespace Modules\Borme\Services;

class SumarioParser
{
    /**
     * Flatten the BOE sumario JSON shape into a list of PDF descriptors. Every
     * `(seccion, item)` pair becomes one row. Province INE code is the trailing
     * "-NN" of the identificador (e.g. BORME-A-2026-78-28 → 28 = MADRID).
     *
     * @param  array<string, mixed>  $sumario  the `data.sumario` node
     * @return array<int, array{
     *   cve: string,
     *   section: string,
     *   province_ine: string|null,
     *   province_name: string|null,
     *   source_url: string,
     *   bulletin_no: int,
     *   date: string,
     * }>
     */
    public function flattenPdfs(array $sumario): array
    {
        $publicationDate = $this->normaliseDate($sumario['metadatos']['fecha_publicacion'] ?? null);
        if ($publicationDate === null) {
            return [];
        }

        $rows = [];
        foreach ($sumario['diario'] ?? [] as $diario) {
            $bulletinNo = (int) ($diario['numero'] ?? 0);

            foreach ($diario['seccion'] ?? [] as $seccion) {
                $section = (string) ($seccion['codigo'] ?? '');
                if ($section === '') {
                    continue;
                }

                foreach ($seccion['item'] ?? [] as $item) {
                    $cve = (string) ($item['identificador'] ?? '');
                    $url = (string) ($item['url_pdf']['texto'] ?? '');
                    if ($cve === '' || $url === '') {
                        continue;
                    }

                    $provinceIne = $this->trailingProvinceCode($cve);

                    $rows[] = [
                        'cve' => $cve,
                        'section' => $section,
                        'province_ine' => $provinceIne,
                        'province_name' => $item['titulo'] ?? null,
                        'source_url' => $url,
                        'bulletin_no' => $bulletinNo,
                        'date' => $publicationDate,
                    ];
                }
            }
        }

        return $rows;
    }

    private function trailingProvinceCode(string $cve): ?string
    {
        return preg_match('/-(\d{2})$/', $cve, $m) === 1 ? $m[1] : null;
    }

    private function normaliseDate(?string $yyyymmdd): ?string
    {
        if ($yyyymmdd === null || ! preg_match('/^\d{8}$/', $yyyymmdd)) {
            return null;
        }

        return substr($yyyymmdd, 0, 4).'-'.substr($yyyymmdd, 4, 2).'-'.substr($yyyymmdd, 6, 2);
    }
}
