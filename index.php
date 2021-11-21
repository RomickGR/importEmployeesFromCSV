<?php

function myAutoLoader(string $className)
{
    require_once __DIR__ . '/Classes/' . str_replace('\\', '/', $className) . '.php';
}

spl_autoload_register('myAutoLoader');

try {
    $csv = new ImportFromCsv("import.csv");

    echo $csv->importEmployeesFromCSV();
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}

