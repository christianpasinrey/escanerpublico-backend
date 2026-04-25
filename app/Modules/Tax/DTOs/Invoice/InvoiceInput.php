<?php

namespace Modules\Tax\DTOs\Invoice;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;
use Modules\Tax\ValueObjects\Nif;
use Modules\Tax\ValueObjects\RegimeCode;

/**
 * Input completo para emitir y calcular una factura de autónomo.
 *
 * Stateless: el calculator no persiste nada. La idempotencia es función
 * de los inputs. Por privacidad, este DTO no se serializa a logs salvo
 * en errores explícitos del controlador.
 *
 * Disclaimer legal: el cálculo es informativo, basado en la legislación
 * española vigente del año `issueDate`. No sustituye al asesoramiento
 * profesional ni a la presentación oficial AEAT.
 */
final readonly class InvoiceInput implements JsonSerializable
{
    /**
     * @param  list<InvoiceLineInput>  $lines
     */
    public function __construct(
        public array $lines,
        public Nif $issuerNif,
        public RegimeCode $issuerVatRegime,
        public RegimeCode $issuerIrpfRegime,
        public CarbonImmutable $issueDate,
        public ClientType $clientType = ClientType::EMPRESA,
        public ?string $issuerActivityCode = null,
        public bool $issuerNewActivityFlag = false,
        public ?Nif $clientNif = null,
        public string $clientCountry = 'ES',
        public bool $surchargeEquivalenceFlag = false,
    ) {
        if ($lines === []) {
            throw new InvalidArgumentException('La factura debe contener al menos una línea.');
        }

        foreach ($lines as $line) {
            if (! $line instanceof InvoiceLineInput) {
                throw new InvalidArgumentException('Todas las líneas deben ser InvoiceLineInput.');
            }
        }

        if (! $issuerVatRegime->isIva()) {
            throw new InvalidArgumentException(
                "El régimen de IVA del emisor debe ser de scope 'iva', recibido '{$issuerVatRegime->scope()}'."
            );
        }

        if (! $issuerIrpfRegime->isIrpf()) {
            throw new InvalidArgumentException(
                "El régimen de IRPF del emisor debe ser de scope 'irpf', recibido '{$issuerIrpfRegime->scope()}'."
            );
        }

        if (! preg_match('/^[A-Z]{2}$/', $clientCountry)) {
            throw new InvalidArgumentException("Código de país ISO 3166-1 alpha-2 inválido: {$clientCountry}");
        }
    }

    public function isDomestic(): bool
    {
        return $this->clientCountry === 'ES';
    }

    public function isIntracommunity(): bool
    {
        $eu = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'FI', 'FR', 'GR',
            'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT',
            'RO', 'SE', 'SI', 'SK'];

        return $this->clientCountry !== 'ES' && in_array($this->clientCountry, $eu, true);
    }

    public function jsonSerialize(): array
    {
        return [
            'lines' => $this->lines,
            'issuer_nif' => $this->issuerNif,
            'issuer_vat_regime' => $this->issuerVatRegime,
            'issuer_irpf_regime' => $this->issuerIrpfRegime,
            'issuer_activity_code' => $this->issuerActivityCode,
            'issuer_new_activity_flag' => $this->issuerNewActivityFlag,
            'client_nif' => $this->clientNif,
            'client_type' => $this->clientType->value,
            'client_country' => $this->clientCountry,
            'surcharge_equivalence_flag' => $this->surchargeEquivalenceFlag,
            'issue_date' => $this->issueDate->toDateString(),
        ];
    }
}
