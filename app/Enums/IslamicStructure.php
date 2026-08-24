<?php

namespace App\Enums;

enum IslamicStructure: string
{
    case QardHasan = 'qard_hasan';
    case Murabaha = 'murabaha';
    case Ijarah = 'ijarah';
    case Musharakah = 'musharakah';
    case Mudarabah = 'mudarabah';
    case Tawarruq = 'tawarruq';

    public function label(): string
    {
        return match ($this) {
            self::QardHasan => 'Qard Hasan',
            self::Murabaha => 'Murabaha',
            self::Ijarah => 'Ijarah',
            self::Musharakah => 'Musharakah',
            self::Mudarabah => 'Mudarabah',
            self::Tawarruq => 'Tawarruq',
        };
    }
}
