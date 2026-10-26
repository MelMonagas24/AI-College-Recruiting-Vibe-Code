<?php

namespace App\Enums;

/**
 * The highest level of soccer an athlete has competed at.
 */
enum SoccerLeague: string
{
    case Ecnl = 'ecnl';
    case GirlsAcademy = 'girls_academy';
    case EcnlRegionalLeague = 'ecnl_rl';
    case GaAspire = 'ga_aspire';
    case DevelopmentPlayerLeague = 'dpl';
    case UsysNationalLeague = 'usys_national_league';
    case NationalPremierLeagues = 'npl';
    case EliteDevelopmentProgram = 'edp';
    case TravelLeague = 'travel_league';
    case Recreational = 'recreational';
    case HighSchoolOnly = 'high_school_only';
    case OtherOrNotSure = 'other_or_not_sure';

    public function label(): string
    {
        return match ($this) {
            self::Ecnl => 'ECNL',
            self::GirlsAcademy => 'Girls Academy (GA)',
            self::EcnlRegionalLeague => 'ECNL Regional League (ECNL RL)',
            self::GaAspire => 'GA Aspire',
            self::DevelopmentPlayerLeague => 'Development Player League (DPL)',
            self::UsysNationalLeague => 'USYS Elite 64 / National League',
            self::NationalPremierLeagues => 'National Premier Leagues (NPL)',
            self::EliteDevelopmentProgram => 'Elite Development Program (EDP)',
            self::TravelLeague => 'State or regional travel league',
            self::Recreational => 'Recreational soccer',
            self::HighSchoolOnly => 'High school soccer only',
            self::OtherOrNotSure => 'Other / Not sure',
        };
    }

    public function tier(): CompetitiveTier
    {
        return match ($this) {
            self::Ecnl, self::GirlsAcademy => CompetitiveTier::Tier1,
            self::EcnlRegionalLeague, self::GaAspire, self::DevelopmentPlayerLeague => CompetitiveTier::Tier2,
            self::UsysNationalLeague, self::NationalPremierLeagues, self::EliteDevelopmentProgram => CompetitiveTier::Tier3,
            self::TravelLeague => CompetitiveTier::Tier4,
            self::Recreational => CompetitiveTier::Tier5,
            self::HighSchoolOnly => CompetitiveTier::HighSchoolOnly,
            self::OtherOrNotSure => CompetitiveTier::OtherOrNotSure,
        };
    }
}
