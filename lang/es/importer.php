<?php

declare(strict_types=1);

return [
    'clockify_time_entries' => [
        'name' => 'Registros de tiempo de Clockify',
        'description' => '<strong>Importante:</strong> si también quieres importar tus proyectos, usa antes el importador "Proyectos de Clockify", porque esa exportación incluye más detalles, como el estado facturable, las tarifas por hora y el tiempo estimado.<br><br>'.
            '1. Primero, en los ajustes de usuario, configura el formato de fecha como "MM/DD/YYYY" y el formato de hora como "12-hour".<br>'.
            '2. En la misma página de preferencias, cambia el idioma de Clockify a inglés.<br>'.
            '3. En el menú de la izquierda, ve a REPORTS -> TIME -> Detailed. <br>'.
            '4. Ahora selecciona el periodo que quieres exportar, arriba a la derecha. '.
            'En el plan gratuito de Clockify, actualmente no se puede seleccionar más de un año. '.
            'Puedes exportar cada año por separado e importarlos uno tras otro.'.
            '<br> 4. Ahora haz clic en Export -> Save as CSV. El menú Export está en la cabecera de la tabla de exportación, a la izquierda del icono de la impresora. '.
            '<br><br>Antes de importar, comprueba que la zona horaria (Timezone) de Clockify es la misma que la de VALO\'s Time.',
    ],
    'generic_projects' => [
        'name' => 'Proyectos (genérico)',
        'description' => 'Si quieres importar muchos proyectos por tu cuenta, este es el importador adecuado. Consulta en la documentación <a href="https://docs.solidtime.io/user-guide/import">más información sobre la estructura del CSV</a>',
    ],
    'generic_time_entries' => [
        'name' => 'Registros de tiempo (genérico)',
        'description' => 'Si quieres importar muchos registros de tiempo por tu cuenta, este es el importador adecuado. Consulta en la documentación <a href="https://docs.solidtime.io/user-guide/import">más información sobre la estructura del CSV</a>',
    ],
    'clockify_projects' => [
        'name' => 'Proyectos de Clockify',
        'description' => '1. Configura el idioma de Clockify en inglés en "Preferences -> General".<br>'.
            '2. En el menú de la izquierda, ve a PROJECTS.<br> '.
            '3. Haz clic en los tres puntos a la derecha del proyecto que quieres exportar y selecciona Export.<br> '.
            '4. Ahora haz clic en Export -> Save as CSV. El menú Export está en la cabecera de la tabla de exportación, arriba a la derecha.',
    ],
    'toggl_data_importer' => [
        'name' => 'Importador de datos de Toggl',
        'description' => '1. Ve a Admin -> Settings -> Data export. <br>'.
            '2. En "Data Export", selecciona todos los elementos que quieres exportar y haz clic en "Export to email". <br> '.
            '3. Recibirás un correo con un enlace de descarga. Descarga el ZIP y súbelo aquí. '.
            '<br><br>"Data Export" exporta todo excepto los registros de tiempo. '.
            'Si también quieres importar registros de tiempo, usa después el importador "Registros de tiempo de Toggl".',
    ],
    'toggl_time_entries' => [
        'name' => 'Registros de tiempo de Toggl',
        'description' => '<strong>Importante:</strong> si quieres importar una organización de Toggl, usa el "Importador de datos de Toggl" antes que este, porque esa exportación incluye más detalles. '.
            '<br><br>1. Ve a Admin -> Settings -> Data export. <br>2. En "Time entries", selecciona el año que quieres exportar y haz clic en "Export time entries". <br><br>Puedes exportar todos los años, uno tras otro, e importarlos uno tras otro. '.
            ' <br>Antes de importar, comprueba que la zona horaria (Timezone) de Toggl es la misma que la de VALO\'s Time.',
    ],
    'solidtime_importer' => [
        'name' => 'Solidtime',
        'description' => '1. Elige la organización que quieres exportar en la lista de arriba a la izquierda<br>2. Haz clic en "Exportar" en el menú de la izquierda, dentro de "Administración" (tienes que ser Administrador o Propietario de la organización para ver esta opción)<br>3. Haz clic en "Exportar". <br>4. Guarda el archivo y súbelo aquí.',
    ],
    'harvest_clients' => [
        'name' => 'Clientes de Harvest',
        'description' => '1. Ve a "Manage" (menú superior)<br>2. Haz clic en "Clients"'.
            '<br>3. Haz clic en "Import/Export" y, en la lista, en "Export clients to CSV" '.
            '<br>',
    ],
    'harvest_projects' => [
        'name' => 'Proyectos de Harvest',
        'description' => '1. Ve a "Projects" (menú superior)<br>2. Haz clic en el botón "Export"'.
            '<br>3. Selecciona los proyectos que quieres exportar y elige el formato CSV '.
            '<br><br>Antes de importar, comprueba que la zona horaria (Timezone) de Harvest es la misma que la de VALO\'s Time.',
    ],
    'harvest_time_entries' => [
        'name' => 'Registros de tiempo de Harvest',
        'description' => '<strong>Importante:</strong> si también quieres importar tus proyectos, usa antes el importador "Proyectos de Harvest", porque esa exportación incluye más detalles, como el estado facturable y el tiempo estimado.<br><br>'.
            '1. Ve a Settings (arriba a la derecha)<br>2. Haz clic en "Import/Export" en el menú de la izquierda'.
            '<br>3. Ahora haz clic en "Export all time" '.
            '<br><br>Antes de importar, comprueba que la zona horaria (Timezone) de Harvest es la misma que la de VALO\'s Time.',
    ],
];
