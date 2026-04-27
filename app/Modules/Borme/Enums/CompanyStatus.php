<?php

namespace Modules\Borme\Enums;

enum CompanyStatus: string
{
    case Active = 'active';
    case Dissolved = 'dissolved';
    case Extinct = 'extinct';
    case Concurso = 'concurso';
    case InLiquidation = 'in_liquidation';
}
