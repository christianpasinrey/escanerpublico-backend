<?php

namespace Modules\Tax\Models;

use Database\Factories\Modules\Tax\TaxTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tax\Enums\LevyType;
use Modules\Tax\Enums\Scope;

/**
 * @property int $id
 * @property string $code
 * @property Scope $scope
 * @property LevyType $levy_type
 * @property string $name
 * @property string|null $base_law_url
 * @property string|null $region_code
 * @property int|null $municipality_id
 * @property string|null $editorial_md
 */
class TaxType extends Model
{
    /** @use HasFactory<TaxTypeFactory> */
    use HasFactory;

    protected $table = 'tax_types';

    protected $fillable = [
        'code',
        'scope',
        'levy_type',
        'name',
        'base_law_url',
        'region_code',
        'municipality_id',
        'editorial_md',
    ];

    protected function casts(): array
    {
        return [
            'scope' => Scope::class,
            'levy_type' => LevyType::class,
            'municipality_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'tax_type_id');
    }

    /**
     * @param  Builder<TaxType>  $query
     */
    public function scopeState(Builder $query): Builder
    {
        return $query->where('scope', Scope::State->value);
    }

    /**
     * @param  Builder<TaxType>  $query
     */
    public function scopeRegional(Builder $query): Builder
    {
        return $query->where('scope', Scope::Regional->value);
    }

    /**
     * @param  Builder<TaxType>  $query
     */
    public function scopeImpuestos(Builder $query): Builder
    {
        return $query->where('levy_type', LevyType::Impuesto->value);
    }

    /**
     * @param  Builder<TaxType>  $query
     */
    public function scopeTasas(Builder $query): Builder
    {
        return $query->where('levy_type', LevyType::Tasa->value);
    }

    protected static function newFactory(): TaxTypeFactory
    {
        return TaxTypeFactory::new();
    }
}
