<?php

namespace App\Enums;

enum PayslipMatchType: string
{
    /**
     * Fuld match: Både erfaring og region matcher
     */
    case FULL_MATCH = 'full_match';

    /**
     * Kun erfaring matcher (>= 5 payslips)
     */
    case EXPERIENCE_MATCH = 'experience_match';

    /**
     * Kun region matcher
     */
    case REGION_MATCH = 'region_match';

    /**
     * Kun jobtitel matcher (ingen erfaring eller region match)
     */
    case TITLE_MATCH = 'title_match';

    /**
     * Ikke nok data til at generere en konklusion
     */
    case INSUFFICIENT_DATA = 'insufficient_data';

    /**
     * Få datapunkter (5-9 payslips) - vis kun interval
     */
    case LIMITED_DATA = 'limited_data';

    /**
     * Hent label til visning
     */
    public function label(): string
    {
        return match($this) {
            self::FULL_MATCH => 'Fuld match',
            self::EXPERIENCE_MATCH => 'Erfarings-match',
            self::REGION_MATCH => 'Regions-match',
            self::TITLE_MATCH => 'Titel-match',
            self::INSUFFICIENT_DATA => 'Utilstrækkelig data',
            self::LIMITED_DATA => 'Begrænset data',
        };
    }
}

