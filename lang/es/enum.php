<?php

declare(strict_types=1);

use App\Enums\CurrencyFormat;
use App\Enums\DateFormat;
use App\Enums\IntervalFormat;
use App\Enums\NumberFormat;
use App\Enums\TimeFormat;
use App\Enums\Weekday;

return [
    'weekday' => [
        Weekday::Monday->value => 'Lunes',
        Weekday::Tuesday->value => 'Martes',
        Weekday::Wednesday->value => 'Miércoles',
        Weekday::Thursday->value => 'Jueves',
        Weekday::Friday->value => 'Viernes',
        Weekday::Saturday->value => 'Sábado',
        Weekday::Sunday->value => 'Domingo',
    ],
    'number_format' => [
        NumberFormat::ThousandsPointDecimalComma->value => '1.111,11',
        NumberFormat::ThousandsCommaDecimalPoint->value => '1,111.11',
        NumberFormat::ThousandsSpaceDecimalComma->value => '1 111,11',
        NumberFormat::ThousandsSpaceDecimalPoint->value => '1 111.11',
        NumberFormat::ThousandsApostropheDecimalPoint->value => '1\'111.11',
    ],
    'date_format' => [
        DateFormat::PointSeparatedDMYYYY->value => 'D.M.AAAA',
        DateFormat::SlashSeparatedMMDDYYYY->value => 'MM/DD/AAAA',
        DateFormat::SlashSeparatedDDMMYYYY->value => 'DD/MM/AAAA',
        DateFormat::HyphenSeparatedDDMMYYY->value => 'DD-MM-AAAA',
        DateFormat::HyphenSeparatedMMDDDYYYY->value => 'MM-DD-AAAA',
        DateFormat::HyphenSeparatedYYYYMMDD->value => 'AAAA-MM-DD',
    ],
    'time_format' => [
        TimeFormat::TwelveHours->value => 'Reloj de 12 horas',
        TimeFormat::TwentyFourHours->value => 'Reloj de 24 horas',
    ],
    'interval_format' => [
        IntervalFormat::Decimal->value => 'Decimal',
        IntervalFormat::HoursMinutes->value => '12h 3m',
        IntervalFormat::HoursMinutesColonSeparated->value => '12:03',
        IntervalFormat::HoursMinutesSecondsColonSeparated->value => '12:03:45',
    ],
    'currency_format' => [
        CurrencyFormat::ISOCodeBeforeWithSpace->value => 'EUR 111',
        CurrencyFormat::ISOCodeAfterWithSpace->value => '111 EUR',
        CurrencyFormat::SymbolBefore->value => '€111',
        CurrencyFormat::SymbolAfter->value => '111€',
        CurrencyFormat::SymbolBeforeWithSpace->value => '€ 111',
        CurrencyFormat::SymbolAfterWithSpace->value => '111 €',
    ],
];
