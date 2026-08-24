<?php

namespace App\Support;

class NigerianNames
{
    /** @var list<string> */
    private const MALE = [
        'Adewale', 'Chinedu', 'Ibrahim', 'Emeka', 'Tunde', 'Yusuf', 'Olumide', 'Nnamdi',
        'Abubakar', 'Kayode', 'Segun', 'Ifeanyi', 'Musa', 'Babatunde', 'Chukwuemeka',
        'Sani', 'Femi', 'Obinna', 'Usman', 'Kunle', 'Oluwaseun', 'Hassan', 'Chibueze',
        'Gbenga', 'Idris', 'Tochukwu', 'Adebayo', 'Suleiman', 'Ikechukwu', 'Olamide',
    ];

    /** @var list<string> */
    private const FEMALE = [
        'Aisha', 'Chioma', 'Funke', 'Ngozi', 'Fatima', 'Yetunde', 'Amaka', 'Halima',
        'Bukola', 'Adaeze', 'Zainab', 'Folake', 'Blessing', 'Hadiza', 'Ifeoma',
        'Kemi', 'Amina', 'Nneka', 'Ronke', 'Maryam', 'Titilayo', 'Chiamaka', 'Hauwa',
        'Oluwatosin', 'Nkechi', 'Bisi', 'Rahmat', 'Uchechi', 'Mariam', 'Temitope',
    ];

    /** @var list<string> */
    private const LAST = [
        'Adeyemi', 'Okafor', 'Mohammed', 'Balogun', 'Nwosu', 'Abdullahi', 'Adebayo',
        'Eze', 'Bello', 'Okonkwo', 'Ibrahim', 'Ogunleye', 'Chukwu', 'Suleiman',
        'Afolabi', 'Nnamani', 'Yusuf', 'Adeleke', 'Danjuma', 'Okoro', 'Lawal',
        'Onyeka', 'Garba', 'Akinola', 'Uche', 'Obi', 'Alabi', 'Musa', 'Ogundipe', 'Nwachukwu',
    ];

    /** @var list<string> */
    private const OCCUPATIONS = [
        'Teacher', 'Civil servant', 'Trader', 'Nurse', 'Engineer', 'Farmer',
        'Accountant', 'Tailor', 'Banker', 'Driver', 'Pharmacist', 'Electrician',
    ];

    /** @var list<string> */
    private const BANKS = [
        'Access Bank', 'GTBank', 'Zenith Bank', 'First Bank', 'UBA', 'Fidelity Bank',
    ];

    /** @var list<string> */
    private const KIN = ['Spouse', 'Brother', 'Sister', 'Father', 'Mother'];

    /**
     * @return array{first_name: string, last_name: string, gender: string, occupation: string, phone: string, bank: string}
     */
    public static function person(int $index): array
    {
        $female = $index % 2 === 0;
        $first = $female
            ? self::FEMALE[($index - 1) % count(self::FEMALE)]
            : self::MALE[($index - 1) % count(self::MALE)];
        $last = self::LAST[($index - 1) % count(self::LAST)];

        return [
            'first_name' => $first,
            'last_name' => $last,
            'gender' => $female ? 'female' : 'male',
            'occupation' => self::OCCUPATIONS[($index - 1) % count(self::OCCUPATIONS)],
            'phone' => self::phone($index),
            'bank' => self::BANKS[($index - 1) % count(self::BANKS)],
            'kin_relationship' => self::KIN[($index - 1) % count(self::KIN)],
        ];
    }

    public static function phone(int $index): string
    {
        $prefixes = ['0803', '0806', '0810', '0813', '0903', '0703'];
        $prefix = $prefixes[($index - 1) % count($prefixes)];

        return $prefix.str_pad((string) (1000000 + ($index * 137) % 8999999), 7, '0', STR_PAD_LEFT);
    }

    public static function accountNumber(int $index): string
    {
        return str_pad((string) (2000000000 + $index * 173), 10, '0', STR_PAD_LEFT);
    }
}
