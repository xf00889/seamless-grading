<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Adviser = 'adviser';
    case Registrar = 'registrar';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
