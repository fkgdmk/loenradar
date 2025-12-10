<?php

namespace App\Enums;

enum ReportStatus: string
{
    case DRAFT = 'draft';
    case AWAITING_DATA = 'awaiting_data';
    case COMPLETED = 'completed';
}
