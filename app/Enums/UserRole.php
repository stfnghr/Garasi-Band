<?php

namespace App\Enums;

/** Role pengguna aplikasi. */
enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';
}
