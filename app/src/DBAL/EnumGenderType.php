<?php

namespace App\DBAL;

class EnumGenderType extends EnumTypeAbstract
{
    protected $name = 'enum_gender';
    protected $values = ['male', 'female'];
}