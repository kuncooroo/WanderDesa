<?php

namespace App\Enums;

enum ValidityType: string
{
    case SameDay = 'same_day';
    case DatetimeWindow = 'datetime_window';
    case DaysFromIssue = 'days_from_issue';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
