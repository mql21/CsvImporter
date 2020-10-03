<?php


namespace mql21\CsvImporter\Builder;


use Doctrine\ORM\EntityManagerInterface;
use mql21\CsvImporter\csvImporter\SanitizationHelper;

class CsvImporterMysqlBuilder
{
    private $csvFilePath;
    private $entityManager;
    private $csvContent;
    private $csvHeader;
    private $destinationTable;
    private $onDuplicateKeyUpdate;
    private $csvMappingFields;
    private $completeMessage;
    private $success;
    private $sanitizationHelper;

  /**
   * ImportCsvService constructor.
   * @param EntityManagerInterface $entityManager
   * @param array $csvMappingFields (Needs to be defined in services.yaml)
   * @param SanitizationHelper $sanitizationHelper
   */
    public function __construct(
      EntityManagerInterface $entityManager,
      array $csvMappingFields,
      SanitizationHelper $sanitizationHelper
    )
    {
        $this->entityManager = $entityManager;
        $this->csvMappingFields = $csvMappingFields;
        $this->sanitizationHelper = $sanitizationHelper;
    }

    public function setCsvFilePath(string $csvFilePath)
    {
        $this->csvFilePath = $csvFilePath;

        return $this;
    }

    /**
     * @param string $table
     * @return $this
     * Set the destination table where the CSV data will be inserted or updated
     */
    public function setDestinationTable(string $table)
    {
        $this->destinationTable = $table;
        $this->csvMappingFields = $this->csvMappingFields[$table];

        return $this;
    }

    /**
     * @param array $columns
     * @return $this
     * Sets the columns where the CSV data will be inserted
     */
    private function setCsvHeader($csvData)
    {
        $csvData = explode("\n", $csvData);
        $this->csvHeader = explode(";", $csvData[0]);

        return $this;
    }

    private function setCsvContent($csvData)
    {
        $csvContent = explode("\n", $csvData);
        array_shift($csvContent);
        $this->csvContent = $csvContent;

        return $this;
    }

    /**
     * @return $this
     * Enables ON DUPLICATE KEY UPDATE mode.
     * If disabled, CSV data will be inserted to DB as new data.
     * If enabled, DB data will be updated if any key is duplicated.
     */
    public function enableOnDuplicateKeyUpdate()
    {
        $this->onDuplicateKeyUpdate = true;

        return $this;
    }

    private function isOnDuplicateKeyEnabled()
    {
        return $this->onDuplicateKeyUpdate;
    }

    private function getCsvHeader()
    {
        return $this->csvHeader;
    }

    /**
     * @param string $columnName
     * @return bool
     * Checks if a column is required in services.yaml CSV definition
     */
    private function isColumnRequired(string $columnName)
    {
        return isset($this->csvMappingFields[$columnName]['required']) && $this->csvMappingFields[$columnName]['required'];
    }

    /**
     * @return array
     * Returns message that save() will return
     */
    private function getReturnMessage()
    {
        $messageType = $this->success ? 'success' : 'error';
        return [
            $messageType => $this->completeMessage,
        ];
    }

    private function searchCsvMissingColumns()
    {
        return array_diff(array_keys($this->csvMappingFields), $this->csvHeader);
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * Executes previously prepared INSERT query
     */
    public function save()
    {
        $csvContent = file_get_contents($this->csvFilePath);

        $this->setCsvHeader($csvContent);
        $this->setCsvContent($csvContent);

        $missingColumns = $this->searchCsvMissingColumns();
        if (!empty($missingColumns)) {
            $this->completeMessage = "Faltan las columnas: " . implode(", ", $missingColumns) . ". Por favor, revisa el fichero e inténtalo de nuevo.";
            $this->success = false;

            return $this->getReturnMessage();
        }

        $insertQuery = $this->buildInsertQuery();
        if (empty($insertQuery)) {
            return $this->getReturnMessage();
        }

        $affectedRows = $this->entityManager->getConnection()->exec($this->buildInsertQuery());
        if ($affectedRows > 0 || ($affectedRows == 0 && $this->isOnDuplicateKeyEnabled())) {
            $this->completeMessage = "La importación se ha realizado con éxito.";
            $this->success = true;
        }

        return $this->getReturnMessage();
    }

    /**
     * @return string|null
     * Builds insert statement
     */
    private function getInsertStatement()
    {
        if (empty($this->destinationTable)) {
            return null;
        }

        $baseInsert = "INSERT INTO $this->destinationTable ";
        $queryColumns = "(";
        foreach ($this->getCsvHeader() as $key => $column) {
            $column = $this->csvMappingFields[$column]['column_name'];
            $queryColumns .= "$column,";
        }

        $queryColumns = substr_replace($queryColumns, ")", -1);

        return $baseInsert . $queryColumns . " VALUES\n";
    }

    /**
     * @return mixed
     * Prepares VALUES(...)(...) for INSERT query that will be executed.
     * If a value is empty and is defined as required, returns null
     */
    private function getValuesForQuery()
    {
        $valuesQuery = null;
        foreach ($this->csvContent as $line) {
            if (!empty($line)) {
                $csvColumns = explode(";", $line);
                $csvColumns = array_combine(array_keys($this->csvMappingFields), $csvColumns);
                $valuesQuery .= $this->getPreparedValue($csvColumns);
                if (empty($valuesQuery)) {
                    return null;
                }
            }
        }

        return substr_replace($valuesQuery, " ", -1);
    }

    /**
     * @param $csvColumns
     * @return mixed|null
     * Converts array of values to (val1, val2, ... ,valN) format
     */
    private function getPreparedValue($csvColumns)
    {
        $valuesQuery = "(";
        foreach ($csvColumns as $columnName => $columnValue) {
            if (empty($columnValue) && $this->isColumnRequired($columnName)) {
                $this->completeMessage = "La columna $columnName no puede estar vacía";
                $this->success = false;

                return null;
            }

            $columnValue = $this->sanitizeColumn($columnName, $columnValue);
            $valuesQuery .= "'$columnValue',";
        }

        return substr_replace($valuesQuery, "),", -1);
    }

    /**
     * @return mixed|string
     * Builds ON DUPLICATE KEY statement
     */
    private function getOnDuplicateKeyStatement()
    {
        $headerColumns = $this->getCsvHeader();
        $onDuplicateKeyStatement = "\nON DUPLICATE KEY UPDATE ";

        foreach ($headerColumns as $column) {
            $column = $this->csvMappingFields[$column]['column_name'];
            $onDuplicateKeyStatement .= "$column = VALUES($column),";
        }

        $onDuplicateKeyStatement = substr_replace($onDuplicateKeyStatement, ";", -1);

        return $onDuplicateKeyStatement;
    }

    /**
     * @param $csvContent
     * @return string
     * Builds entire INSERT query
     */
    private function buildInsertQuery()
    {
        $baseInsertQuery = $this->getInsertStatement();
        $valuesQuery = $this->getValuesForQuery();

        if (empty($valuesQuery)) {
            return null;
        }

        $insertQuery = $baseInsertQuery . $valuesQuery;

        $insertQuery .= $this->isOnDuplicateKeyEnabled()
            ? $this->getOnDuplicateKeyStatement()
            : ';';

        return $insertQuery;
    }

    private function sanitizeColumn(string $columnName, string $columnValue)
    {
        if (!isset($this->csvMappingFields[$columnName]['validate'])) {
            return $columnValue;
        }

        switch ($this->csvMappingFields[$columnName]['validate']) {
            case 'decimal':
                return $this->sanitizationHelper->sanitizeDecimal($columnValue);
                break;
        }

        return $columnValue;
    }
}