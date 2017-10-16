<?php
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginorionCommon
{
   /**
    * Display massive actions
    * @param array $massiveactionparams
    * @return string an HTML
    */
   public static function getMassiveActions($massiveactionparams) {
      ob_start();
      Html::showMassiveActions($massiveactionparams);
      $html = ob_get_clean();

      return $html;
   }

   /**
    * Return an array of values from enum fields
    * @param string $table name
    * @param string $field name
    * @return array
    */
   public static function getEnumValues($table, $field) {
      global $DB;

      $enum = [];
      if ($res = $DB->query( "SHOW COLUMNS FROM `$table` WHERE Field = '$field'" )) {
         $data = $DB->fetch_array($res);
         $type = $data['Type'];
         $matches = null;
         preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
         if (isset($matches[1])) {
            $enum = explode("','", $matches[1]);
         }
      }

      return $enum;
   }
}
