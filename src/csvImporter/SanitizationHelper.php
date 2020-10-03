<?php


namespace mql21\CsvImporter\csvImporter;


class SanitizationHelper
{
  public function sanitizeDecimal($val)
  {
    $val = str_replace(",", ".", $val);
    $val = preg_replace('/\.(?=.*\.)/', '', $val);

    return floatval($val);
  }
}