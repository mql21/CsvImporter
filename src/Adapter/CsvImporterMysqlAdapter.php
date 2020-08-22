<?php


namespace mql21\CsvImporter\Adapter;


use mql21\CsvImporter\Builder\CsvImporterMysqlBuilder;
use mql21\CsvImporter\CsvImporterInterface;

class CsvImporterMysqlAdapter implements CsvImporterInterface
{
    private $csvImporterMysqlBuilder;

    public function __construct(CsvImporterMysqlBuilder $csvImporterMysqlBuilder)
    {
        $this->csvImporterMysqlBuilder = $csvImporterMysqlBuilder;
    }

    public function import(string $csvFilePath, string $tableName)
    {
        return $this->csvImporterMysqlBuilder
            ->setCsvFilePath($csvFilePath)
            ->setDestinationTable($tableName)
            ->enableOnDuplicateKeyUpdate()
            ->save();
    }
}