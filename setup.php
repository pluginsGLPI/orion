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
 *
 * @copyright Copyright © 2017 Teclib
 * @license   AGPLv3+ http://www.gnu.org/licenses/agpl.txt
 * @link      https://github.com/flyve-mdm/orion-glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */

define('PLUGIN_ORION_VERSION', '0.1-dev');
// is or is not an official release of the plugin
define('PLUGIN_ORION_IS_OFFICIAL_RELEASE', false);
// Minimal GLPI version, inclusive
define('PLUGIN_ORION_GLPI_MIN_VERSION', '9.2.1');
// Maximum GLPI version, exclusive
define('PLUGIN_ORION_GLPI_MAX_VERSION', '9.3');

define('PLUGIN_ORION_ROOT', GLPI_ROOT . '/plugins/orion');

if (!defined('ORION_TEMPLATE_CACHE_PATH')) {
   define('ORION_TEMPLATE_CACHE_PATH', GLPI_PLUGIN_DOC_DIR . '/orion/cache');
}

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_orion() {
   global $PLUGIN_HOOKS;

   $pluginName = 'orion';
   $PLUGIN_HOOKS['csrf_compliant']['orion'] = true;
   // This hook must be enabled even if the plugin is disabled
   $PLUGIN_HOOKS['undiscloseConfigValue'][$pluginName] = [PluginFlyvemdmConfig::class,
                                                          'undiscloseConfigValue'];

   $plugin = new Plugin;
   if ($plugin->isActivated($pluginName)) {
      if (is_readable(__DIR__ . '/vendor/autoload.php')) {
         require_once(__DIR__ . '/vendor/autoload.php');
      }
      if (!class_exists('GlpiLocalesExtension')) {
         require_once(__DIR__ . '/lib/GlpiLocalesExtension.php');
      }

      plugin_orion_addHooks();
      plugin_orion_registerClasses();
   }
}


/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_orion() {
   if (defined('GLPI_PREVER') && PLUGIN_ORION_IS_OFFICIAL_RELEASE == false) {
      $glpiVersion = version_compare(GLPI_PREVER, PLUGIN_ORION_GLPI_MIN_VERSION, 'lt');
   } else {
      $glpiVersion = PLUGIN_ORION_GLPI_MIN_VERSION;
   }

   return [
      'name'           => 'orion',
      'version'        => PLUGIN_ORION_VERSION,
      'author'         => '<a href="http://www.teclib.com">Teclib\'</a>',
      'license'        => '',
      'homepage'       => '',
      'requirements'   => [
         'glpi' => [
            'min' => $glpiVersion,
            'max' => PLUGIN_ORION_GLPI_MAX_VERSION,
            'dev' => PLUGIN_ORION_IS_OFFICIAL_RELEASE == false,
            'plugins'   => [],
            'params' => [],
         ],
         'php' => [
            'exts'   => []
         ]
      ]
   ];
}

/**
 * Check pre-requisites before install
 * OPTIONNAL, but recommanded
 *
 * @return boolean
 */
function plugin_orion_check_prerequisites() {
   $prerequisitesSuccess = true;

   if (!is_readable(__DIR__ . '/vendor/autoload.php') || !is_file(__DIR__ . '/vendor/autoload.php')) {
      echo "Run composer install --no-dev in the plugin directory<br>";
      $prerequisitesSuccess = false;
   }

   if (version_compare(GLPI_VERSION, PLUGIN_ORION_GLPI_MIN_VERSION, 'lt') || version_compare(GLPI_VERSION, PLUGIN_ORION_GLPI_MAX_VERSION, 'ge')) {
      echo "This plugin requires GLPi >= " . PLUGIN_ORION_GLPI_MIN_VERSION . " and GLPI < " . PLUGIN_ORION_GLPI_MAX_VERSION . "<br>";
      $prerequisitesSuccess = false;
   }

   return $prerequisitesSuccess;
}

/**
 * Check configuration process
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 *
 * @return boolean
 */
function plugin_orion_check_config($verbose = false) {
   if (true) { // Your configuration check
      return true;
   }

   if ($verbose) {
      _e('Installed / not configured', 'orion');
   }
   return false;
}

/**
 * Register classes
 */
function plugin_orion_registerClasses() {
   Plugin::registerClass(PluginOrionProfile::class,
      ['addtabon' => Profile::class]);
}

/**
 * Adds all hooks the plugin needs
 */
function plugin_orion_addHooks() {
   global $PLUGIN_HOOKS;

   $pluginName = 'orion';
   $PLUGIN_HOOKS['post_init'][$pluginName] = 'plugin_orion_postinit';
   $PLUGIN_HOOKS['config_page'][$pluginName] = 'front/config.form.php';
   $PLUGIN_HOOKS['add_css']['orion'][] = "css/style.css";

}

function plugin_orion_getTemplateEngine() {
   $loader = new Twig_Loader_Filesystem(__DIR__ . '/tpl');
   $twig =  new Twig_Environment($loader, array(
      'cache'        => ORION_TEMPLATE_CACHE_PATH,
      'auto_reload'  => ($_SESSION['glpi_use_mode'] == 2),
   ));
   $twig->addExtension(new GlpiLocalesExtension());
   return $twig;
}

/**
 * Show the last SQL error, logs its backtrace and dies
 * @param Migration $migration
 */
function plugin_orionupgrade_error(Migration $migration) {
   global $DB;

   $error = $DB->error();
   $migration->log($error . "\n" . Toolbox::backtrace(false, '', ['Toolbox::backtrace()']), false);
   die($error . "<br><br> Please, check migration log");
}
