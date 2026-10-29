<?php

declare(strict_types=1);

// Etiquetas de App\Enums\TimeEntryAggregationType::description() (informes en PDF y hoja de cálculo).
// La librería datomatic/laravel-enum-helper busca 'enums.<clase>.<valor>' como alternativa.
return [
    'App\Enums\TimeEntryAggregationType' => [
        'day' => 'Día',
        'week' => 'Semana',
        'month' => 'Mes',
        'year' => 'Año',
        'user' => 'Miembro',
        'project' => 'Proyecto',
        'task' => 'Tarea',
        'client' => 'Cliente',
        'billable' => 'Facturable',
        'description' => 'Descripción',
        'tag' => 'Etiqueta',
        'type' => 'Tipo',
    ],
];
