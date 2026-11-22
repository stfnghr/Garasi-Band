<?php

namespace App\Enums;

/** Status pemakaian garasi. */
enum GarageStatus: string
{
    case Available = 'available';
    case Used = 'used';
}
