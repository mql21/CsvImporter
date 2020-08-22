<?php


namespace mql21\CsvImporter;


interface CsvImporterInterface
{
    public function import(string $csvFilePath, string $tableName);
}