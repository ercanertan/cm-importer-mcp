<?php 

namespace App\Enums;

enum CustomFieldTypes: string
{
    case Text = 'Text';
    case Number = 'Number';
    case MultiSelectOne = 'MultiSelectOne';
    case MultiSelectMany = 'MultiSelectMany';
    case Country = 'Country';
    case Date = 'Date';

    public function label(): string
    {
        return match($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::MultiSelectOne => 'MultiSelect - One',
            self::MultiSelectMany => 'MultiSelect - Many',
            self::Country => 'Country',
            self::Date => 'Date',
        };
    }
}