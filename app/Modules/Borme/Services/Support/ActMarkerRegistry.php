<?php

namespace Modules\Borme\Services\Support;

use Modules\Borme\Enums\ActType;

/**
 * Single source of truth for the literal headers that introduce each act in
 * a BORME-A entry. Used by ActTypeClassifier to detect which acts an entry
 * contains and by CompanyHeaderExtractor / OfficerExtractor to anchor on the
 * boundary between the company name and the first act.
 *
 * Order matters in two ways:
 *  - Longer / more specific markers first (so "Reducción de capital" wins
 *    against "Reducción").
 *  - Free-form markers (terminated by `:`) come last because the regex used
 *    in classify() also accepts `:` as terminator.
 */
class ActMarkerRegistry
{
    /** @var array<string, ActType> */
    public const MARKERS = [
        'Constitución' => ActType::Constitution,
        'Reapertura hoja registral' => ActType::StatusReactivation,
        'Cambio de domicilio social' => ActType::AddressChange,
        'Cambio de denominación social' => ActType::NameChange,
        'Cambio de objeto social' => ActType::ObjectChange,
        'Ampliación del objeto social' => ActType::ObjectChange,
        'Modificaciones estatutarias' => ActType::BylawsChange,
        'Modificación estatutaria' => ActType::BylawsChange,
        'Transformación de sociedad' => ActType::Transformation,
        'Declaración de unipersonalidad' => ActType::SolePartnerDeclaration,
        'Pérdida del carácter de unipersonalidad' => ActType::SolePartnerChange,
        'Sociedad unipersonal' => ActType::SolePartnerChange,
        'Fusión por absorción' => ActType::MergerByAbsorption,
        'Ampliación de capital' => ActType::CapitalIncrease,
        'Reducción de capital' => ActType::CapitalDecrease,
        'Desembolso de dividendos pasivos' => ActType::PaidInCapital,
        'Emisión de obligaciones' => ActType::BondsIssuance,
        'Página web de la sociedad' => ActType::WebsiteChange,
        'Disolución' => ActType::Dissolution,
        'Extinción' => ActType::Extinction,
        'Situación concursal' => ActType::Concurso,
        'Concurso' => ActType::Concurso,
        'Cierre provisional hoja registral' => ActType::Concurso,
        'Reelecciones' => ActType::Reelection,
        'Revocaciones' => ActType::Revocation,
        'Ceses/Dimisiones' => ActType::Cease,
        'Nombramientos' => ActType::Appointment,
        'Datos registrales' => ActType::Other, // sentinel — present in every entry
        'Fe de erratas' => ActType::Erratum,
        'Otros conceptos' => ActType::Other,
    ];

    /**
     * @return array<string, ActType>
     */
    public static function markers(): array
    {
        return self::MARKERS;
    }

    public static function alternation(): string
    {
        return implode('|', array_map(
            fn ($m) => preg_quote($m, '/'),
            array_keys(self::MARKERS)
        ));
    }

    /**
     * Alternation excluding the "Datos registrales" sentinel — used to anchor
     * the company-name boundary, where matching the trailing sentinel would
     * over-capture the entire entry body into the company name.
     */
    public static function alternationForHeader(): string
    {
        $headers = array_filter(
            array_keys(self::MARKERS),
            fn ($m) => $m !== 'Datos registrales'
        );

        return implode('|', array_map(fn ($m) => preg_quote($m, '/'), $headers));
    }
}
