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

/**
 *
 * @author tbugier
 * @since 0.1.0
 *
 */
class PluginOrionInstaller {

   protected static $currentVersion = null;

   protected $migration;

   /**
    * Autoloader for installation
    */
   public function autoload($classname) {
      // useful only for installer GLPi autoloader already handles inc/ folder
      $filename = dirname(__DIR__) . '/inc/' . strtolower(str_replace('PluginOrion', '', $classname)). '.class.php';
      if (is_readable($filename) && is_file($filename)) {
         include_once($filename);
         return true;
      }
   }

   /**
    *
    * Install the plugin
    *
    * @return boolean true (assume success, needs enhancement)
    *
    */
   public function install() {
      global $DB;

      spl_autoload_register([__CLASS__, 'autoload']);

      $this->migration = new Migration(PLUGIN_ORION_VERSION);
      $this->migration->setVersion(PLUGIN_ORION_VERSION);

      // adding DB model from sql file
      // TODO : migrate in-code DB model setup here
      if (self::getCurrentVersion() == '') {
         // Setup DB model
         $dbFile = PLUGIN_ORION_ROOT . "/install/mysql/plugin_orion_empty.sql";
         if (!$DB->runFile($dbFile)) {
            $this->migration->displayWarning("Error creating tables : " . $DB->error(), true);
            return false;
         }

         $this->createInitialConfig();
      } else {
         if ($this->endsWith(PLUGIN_ORION_VERSION, "-dev") || (version_compare(self::getCurrentVersion(), PLUGIN_ORION_VERSION) != 0)) {
            // TODO : Upgrade (or downgrade)
            $this->upgrade(self::getCurrentVersion());
         }
      }

      $this->migration->executeMigration();

      $this->createDirectories();
      $this->createAutomaticActions();

      Config::setConfigurationValues('orion', ['version' => PLUGIN_ORION_VERSION]);

      return true;
   }

   public static function getCurrentVersion() {
      if (self::$currentVersion === NULL) {
         $config = \Config::getConfigurationValues("orion", ['version']);
         if (!isset($config['version'])) {
            self::$currentVersion = '';
         } else {
            self::$currentVersion = $config['version'];
         }
      }
      return self::$currentVersion;
   }

   /**
    * Give all rights on the plugin to the profile of the current user
    */
   protected function createFirstAccess() {
      $profileRight = new ProfileRight();

      $newRights = [
      ];

      $profileRight->updateProfileRights($_SESSION['glpiactiveprofile']['id'], $newRights);

      $_SESSION['glpiactiveprofile'] = $_SESSION['glpiactiveprofile'] + $newRights;
   }

   /**
    * Upgrade the plugin to the current code version
    *
    * @param string $fromVersion
    */
   protected function upgrade($fromVersion) {
      switch ($fromVersion) {
         case '0.1.0':
            // Example : upgrade to version 3.0.0
            // $this->upgradeOneStep('3.0.0');
         case '1.0.0':
            // Example : upgrade to version 4.0.0
            // $this->upgradeOneStep('4.0.0');

         default:
      }
      if ($this->endsWith(PLUGIN_ORION_VERSION, "-dev")) {
         $this->upgradeOneStep('dev');
      }

      $this->createDirectories();
      $this->createAutomaticActions();
   }

   /**
    * Proceed to upgrade of the plugin to the given version
    *
    * @param string $toVersion
    */
   protected function upgradeOneStep($toVersion) {

      $suffix = str_replace('.', '_', $toVersion);
      $includeFile = __DIR__ . "/upgrade/update_to_$suffix.php";
      if (is_readable($includeFile) && is_file($includeFile)) {
         include_once $includeFile;
         $updateFunction = "plugin_flyvemdm_update_to_$suffix";
         if (function_exists($updateFunction)) {
            $this->migration->addNewMessageArea("Upgrade to $toVersion");
            $updateFunction($this->migration);
            $this->migration->executeMigration();
            $this->migration->displayMessage('Done');
         }
      }
   }

   public function createDirectories() {
      // Create cache directory for the template engine
      if (! file_exists(ORION_TEMPLATE_CACHE_PATH)) {
         if (! mkdir(ORION_TEMPLATE_CACHE_PATH, 0770, true)) {
            $this->migration->displayWarning("Cannot create " . ORION_TEMPLATE_CACHE_PATH . " directory");
         }
      }

      // Create directory for report files
      if (! file_exists(ORION_REPORT_PATH)) {
         if (! mkdir(ORION_REPORT_PATH, 0770, true)) {
            $this->migration->displayWarning("Cannot create " . ORION_REPORT_PATH . " directory");
         }
      }
   }

   protected function createAutomaticActions() {
      CronTask::Register(PluginOrionTask::class, 'UpdateStatus', MINUTE_TIMESTAMP,
         [
               'comment'   => __('Update the status of a task', 'orion'),
               'mode'      => CronTask::MODE_EXTERNAL
         ]);
      CronTask::Register(PluginOrionTask::class, 'PushTask', MINUTE_TIMESTAMP,
         [
            'comment'   => __('Create a task in the Orion webservice', 'orion'),
            'mode'      => CronTask::MODE_EXTERNAL
         ]);
   }

   /**
    * Uninstall the plugin
    * @return boolean true (assume success, needs enhancement)
    */
   public function uninstall() {
      $this->rrmdir(GLPI_PLUGIN_DOC_DIR . "/orion");

      $this->deleteRelations();
      $this->deleteDisplayPreferences();
      $this->deleteTables();
      // Cron jobs deletion handled by GLPI

      $config = new Config();
      $config->deleteByCriteria(['context' => 'orion']);

      return true;
   }

   /**
    * Cannot use the method from PluginFlyvemdmToolbox if the plugin is being uninstalled
    * @param string $dir
    */
   protected function rrmdir($dir) {
      if (file_exists($dir) && is_dir($dir)) {
         $objects = scandir($dir);
         foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
               if (filetype($dir . "/" . $object) == "dir") {
                  $this->rrmdir($dir . "/" . $object);
               } else {
                  unlink($dir . "/" . $object);
               }
            }
         }
         reset($objects);
         rmdir($dir);
      }
   }

   /**
    * Generate default configuration for the plugin
    */
   protected function createInitialConfig() {
      // New config management provided by GLPi
      $newConfig = [
      ];
      Config::setConfigurationValues('orion', $newConfig);
   }

   /**
    * Generate HTML version of a text
    * Replaces \n by <br>
    * Encloses the text un <p>...</p>
    * Add anchor to URLs
    * @param string $text
    */
   protected static function convertTextToHtml($text) {
      $text = '<p>' . str_replace("\n\n", '</p><p>', $text) . '</p>';
      $text = '<p>' . str_replace("\n", '<br>', $text) . '</p>';
      return $text;
   }

   protected function deleteTables() {
      global $DB;

      $tables = [
         PluginOrionTask::getTable()
      ];

      foreach ($tables as $table) {
         $DB->query("DROP TABLE IF EXISTS `$table`");
      }
   }

   protected function deleteProfileRights() {
      $rights = [
      ];
      foreach ($rights as $right) {
         ProfileRight::deleteProfileRights([$right]);
         unset($_SESSION["glpiactiveprofile"][$right]);
      }
   }

   protected function deleteRelations() {
      $pluginItemtypes = [
      ];
      foreach ($pluginItemtypes as $pluginItemtype) {
         foreach (['Notepad', 'DisplayPreference', 'DropdownTranslation', 'Log', 'Bookmark', 'SavedSearch'] as $itemtype) {
            if (class_exists($itemtype)) {
               $item = new $itemtype();
               $item->deleteByCriteria(['itemtype' => $pluginItemtype]);
            }
         }
      }
   }

   protected function deleteDisplayPreferences() {
      // To cleanup display preferences if any
      //$displayPreference = new DisplayPreference();
      //$displayPreference->deleteByCriteria(["`num` >= " . PluginFlyvemdmConfig::RESERVED_TYPE_RANGE_MIN . "
      //                                             AND `num` <= " . PluginFlyvemdmConfig::RESERVED_TYPE_RANGE_MAX]);
   }
}
