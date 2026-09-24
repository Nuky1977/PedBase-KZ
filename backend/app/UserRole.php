<?php

namespace App;

enum UserRole: string
{
    case ADMIN = 'admin';
    case METHODIST = 'methodist';
    case SCHOOL_ADMIN = 'school_admin';
    case TEACHER = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Жүйе әкімшісі',
            self::METHODIST => 'Әдіскер',
            self::SCHOOL_ADMIN => 'Мектеп әкімшісі',
            self::TEACHER => 'Педагог',
        };
    }
}