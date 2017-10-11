<?php
/**
 * LICENSE
 *
 * Copyright © 2017 Teclib'
 *
 * This file is part of Orion for GLPI.
 *
 * Orion Plugin for GLPI is a subproject of Flyve MDM. Flyve MDM is a mobile
 * device management software.
 *
 * Orion Plugin for GLPI is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * Orion Plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 * along with Orion Plugin for GLPI. If not, see http://www.gnu.org/licenses/.
 * ------------------------------------------------------------------------------
 * @author    Thierry Bugier Pineau
 * @copyright Copyright © 2017 Teclib
 * @license   AGPLv3+ http://www.gnu.org/licenses/agpl.txt
 * @link      https://github.com/flyve-mdm/orion-glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginOrionConfig extends CommonDBTM {

   // Type reservation : https://forge.indepnet.net/projects/plugins/wiki/PluginTypesReservation
   const RESERVED_TYPE_RANGE_MIN = 11050;
   const RESERVED_TYPE_RANGE_MAX = 11099;

   static $config = array();

   /**
    * Display the configuration form for the plugin.
    */
   public function showForm() {
      echo '<form id="pluginOrion-config" method="post" action="./config.form.php">';

      $fields = Config::getConfigurationValues('orion');
      $fields['orion_api_key_placeholder'] = __('your API key', 'orion');
      if (strlen($fields['orion_api_key']) > 0) {
         $fields['orion_api_key_placeholder'] = __('******', 'orion');
      }
      unset($fields['orion_api_key']);

      $data = [
         'config' => $fields
      ];

      $twig = plugin_orion_getTemplateEngine();
      echo $twig->render('config.html', $data);

      Html::closeForm();
   }

   /**
    * @see CommonDBTM::post_getEmpty()
    */
   public function post_getEmpty() {
      $this->fields['id'] = 1;
      $this->fields['mqtt_broker_address'] = '127.0.0.1';
      $this->fields['mqtt_broker_port'] = '1883';
   }

   /**
    * Hook for config validation before update
    * @param array $input
    */
   public static function configUpdate($input) {
      if (strlen($input['orion_api_key']) === 0) {
         unset($input['orion_api_key']);
      }
      return $input;
   }

   /**
    * @see CommonDBTM::prepareInputForAdd()
    */
   public function prepareInputForAdd($input) {
      return $input;
   }

   /**
    * Remove the value from sensitive configuration entry
    * @param array $fields
    * @return array the filtered configuration entry
    */
   public static function undiscloseConfigValue($fields) {
      $undisclosed = [
            'orion_api_key',
      ];

      if ($fields['context'] == 'orion'
            && in_array($fields['name'], $undisclosed)) {
         unset($fields['value']);
      }
      return $fields;
   }
}
