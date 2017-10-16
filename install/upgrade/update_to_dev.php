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
 * @author    the flyvemdm plugin team
 * @copyright Copyright © 2017 Teclib
 * @license   AGPLv3+ http://www.gnu.org/licenses/agpl.txt
 * @link      https://github.com/flyve-mdm/orion-glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

function plugin_orion_update_to_dev(Migration $migration) {
   global $DB;

   $migration->setVersion(PLUGIN_ORION_VERSION);

   // update Entity config table
   $table = 'glpi_plugin_orion_tasks';
   $migration->renameTable($table, 'glpi_plugin_orion_reports');

   // Create report / item relation table
   $table = 'glpi_plugin_orion_items_reports';
   $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_orion_items_reports` (
               `id`                        int(11)                                           NOT NULL AUTO_INCREMENT,
               `plugin_orion_reports_id`   int(11)                                           NOT NULL DEFAULT '0',
               `itemtype`                  varchar(255)                                      DEFAULT NULL,
               `items_id`                  int(11)                                           NOT NULL DEFAULT '0',
               PRIMARY KEY (`id`),
               KEY `plugin_orion_reports_id` (`plugin_orion_reports_id`),
               KEY `item` (`itemtype`,`items_id`)
             ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
   if (!$DB->query($query)) {
      plugin_flyvemdm_upgrade_error($migration);
   }
}
