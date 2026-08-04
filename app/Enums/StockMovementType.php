<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
}
