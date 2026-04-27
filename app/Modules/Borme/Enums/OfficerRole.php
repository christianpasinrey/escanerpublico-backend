<?php

namespace Modules\Borme\Enums;

enum OfficerRole: string
{
    case SoleAdmin = 'sole_admin';                // Adm. Unico
    case JointAdmin = 'joint_admin';              // Adm. Mancom.
    case SeveralAdmin = 'several_admin';          // Adm. Solid.
    case BoardMember = 'board_member';            // Consejero
    case ChairmanBoard = 'chairman';              // Presidente
    case SecretaryBoard = 'secretary';            // Secretario
    case Attorney = 'attorney';                   // Apoderado
    case JointAttorney = 'joint_attorney';        // Apo.Man.Soli
    case SeveralAttorney = 'several_attorney';    // Apo.Sol.
    case Liquidator = 'liquidator';               // Liquidador
    case SolePartner = 'sole_partner';            // Socio único
    case Representative = 'representative';       // Representan
    case Other = 'other';
}
