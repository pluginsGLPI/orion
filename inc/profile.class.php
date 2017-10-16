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
 * @since 0.1.0
 */
class PluginOrionProfile extends Profile {

   /**
    * Deletes the profiles related to the ones being purged
    * @param Profile $prof
    */
   public static function purgeProfiles(Profile $prof) {
      $plugprof = new self();
      $plugprof->deleteByCriteria(['profiles_id' => $prof->getField("id")]);
   }

   /**
    * @see Profile::showForm()
    */
   public function showForm($ID, $options = []) {
      if (!Profile::canView()) {
         return false;
      }
      $canedit = Profile::canUpdate();
      $profile    = new Profile();
      if ($ID) {
         $profile->getFromDB($ID);
      }
      if ($canedit) {
         echo "<form action='" . $profile->getFormURL() . "' method='post'>";
      }

      $rights = $this->getGeneralRights();
      $profile->displayRightsChoiceMatrix($rights, ['canedit'       => $canedit,
                                                    'default_class' => 'tab_bg_2',
                                                    'title' => __('General')
      ]);

      if ($canedit) {
         echo "<div class='center'>";
         echo "<input type='hidden' name='id' value=".$ID.">";
         echo "<input type='submit' name='update' value=\""._sx('button', 'Save')."\" class='submit'>";
         echo "</div>";
      }
      Html::closeForm();
      $this->showLegend();
   }

   /**
    * @see Profile::getTabNameForItem()
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'Profile') {
         return __('Orion', 'orion');
      }
      return '';
   }

   /**
    * @param CommonGLPI $item
    * @param number $tabnum
    * @param number $withtemplate
    * @return boolean
    */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() == 'Profile') {
         $profile = new self();
         $profile->showForm($item->getField('id'));
      }
      return true;
   }

   /**
    * Get rights matrix for plugin
    * @return array:array:string rights matrix
    */
   public function getGeneralRights() {
      $rights = [
         [
            'itemtype'  => PluginOrionReport::class,
            'label'     => PluginOrionReport::getTypeName(2),
            'field'     => PluginOrionReport::$rightname,
            'rights'    => [
                READ       => __('Read'),
             ]
         ],
      ];

      return $rights;
   }
}
