<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
}
