<?php

namespace Modules\Borme\Enums;

enum ActType: string
{
    case Constitution = 'constitution';
    case Appointment = 'appointment';
    case Cease = 'cease';
    case Reelection = 'reelection';
    case Revocation = 'revocation';
    case CapitalIncrease = 'capital_increase';
    case CapitalDecrease = 'capital_decrease';
    case AddressChange = 'address_change';
    case ObjectChange = 'object_change';
    case BylawsChange = 'bylaws_change';
    case SolePartnerDeclaration = 'sole_partner_declaration';
    case SolePartnerChange = 'sole_partner_change';
    case Dissolution = 'dissolution';
    case Extinction = 'extinction';
    case MergerByAbsorption = 'merger_by_absorption';
    case Erratum = 'erratum';
    case Concurso = 'concurso';
    case StatusReactivation = 'status_reactivation';
    case NameChange = 'name_change';
    case Transformation = 'transformation';
    case WebsiteChange = 'website_change';
    case BondsIssuance = 'bonds_issuance';
    case PaidInCapital = 'paid_in_capital';
    case Other = 'other';
}
