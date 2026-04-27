<?php

namespace Modules\Borme\Enums;

enum LegalForm: string
{
    case SL = 'SL';      // Sociedad Limitada
    case SLU = 'SLU';    // Sociedad Limitada Unipersonal
    case SLP = 'SLP';    // Sociedad Limitada Profesional
    case SLNE = 'SLNE';  // Sociedad Limitada Nueva Empresa
    case SA = 'SA';      // Sociedad Anónima
    case SAU = 'SAU';    // Sociedad Anónima Unipersonal
    case SAP = 'SAP';    // Sociedad Anónima Profesional
    case SCRL = 'SCRL';  // Sociedad Civil Profesional / Sociedad Cooperativa
    case SC = 'SC';      // Sociedad Civil
    case SCP = 'SCP';    // Sociedad Civil Profesional
    case UTE = 'UTE';    // Unión Temporal de Empresas
    case AIE = 'AIE';    // Agrupación de Interés Económico
    case SLL = 'SLL';    // Sociedad Limitada Laboral
    case Branch = 'BRANCH'; // Sucursal en España de entidad extranjera
    case Coop = 'COOP';
    case Other = 'OTHER';
}
