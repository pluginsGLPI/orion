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

use GuzzleHttp\Client as HttpClient;
use GlpiPlugin\Orion\Exception\IncompatibleItemtypeException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;

class PluginOrionReport extends CommonDBTM {
   static $rightname = 'orion:report';

   const STATUS_PENDING = 'pending';
   const STATUS_SENT    = 'sent';
   const STATUS_RUNNING = 'running';
   const STATUS_DONE    = 'done';
   const STATUS_FAILED  = 'failed';

   // Itemtypes reports may be linked to
   static protected $linkableClasses = [];

   /**
    * Get the status of the report
    * @return the status
    */
   static function getEnumStatus() {
      return [
         'pending'   => __('Pending', 'orion'),
         'sent'      => __('Sent', 'orion'),
         'running'   => __('Running', 'orion'),
         'done'      => __('Done', 'orion'),
         'failed'    => __('Failed', 'orion'),
      ];
   }

   /**
    * Get the evaluation of the report
    * @return the evaluation
    */
   static function getEnumEvaluation() {
      return [
         'n/a'    => __('n/a', 'orion'),
         'low'    => __('Low', 'orion'),
         'medium' => __('Medium', 'orion'),
         'high'   => __('High', 'orion'),
      ];
   }

   /**
    * Gets the classes linkable to a report
    * @return array
    */
   public static function getLinkableClasses() {
      return static::$linkableClasses;
   }

   /**
    * Declare a new itemtype to be linkable to a a report
    */
   static function registerItemtype($itemtype) {
      global $PLUGIN_HOOKS;

      if (!in_array($itemtype, static::$linkableClasses) && class_exists($itemtype)) {
         $item = new $itemtype();
         if (is_subclass_of($itemtype, CommonDBTM::class) && method_exists($item, 'getFilename')) {
            $PLUGIN_HOOKS['pre_item_purge']['orion'] = [
               $itemtype::getType() => 'plugin_orion_hook_pre_item_purge',
            ];
            array_push(static::$linkableClasses, $itemtype);
            Plugin::registerClass(static::class, ['addtabon' => $itemtype]);
         } else {
            throw new IncompatibleItemtypeException("$itemtype is not compatible with Orion plugin; cannot register it");
         }
      }
   }

   /**
    * Localized name of the type
    * @param integer $nb number of item in the type (default 0)
    * @return string
    */
   public static function getTypeName($nb = 0) {
      return _n('Report', 'Reports', $nb, 'orion');
   }

   /**
    * Get the tab names for item
    * @param CommonGLPI $item 
    * @param boolean $withtemplate if with a template or basic, by default is false
    * @return the tab names
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      $nb = 0;
      return self::createTabEntry(self::getTypeName($nb), $nb);
   }

   /**
    * Prepare input data for adding the item
    * @param array $input the data to prepare
    * @return $input the modified data
    */
   public function prepareInputForAdd($input) {
      unset($input['status'],
         $input['remote_id'],
         $input['sha256'],
         $input['report'],
         $input['date_report'],
         $input['evaluation']);

      $itemtype = $input['itemtype'];
      if (!in_array($itemtype, static::$linkableClasses) && class_exists($itemtype)) {
         return false;
      }
      $item = new $itemtype();
      $item->getFromDB($input['items_id']);
      $input['filename'] = $item->getFilename();

      return $input;
   }

   /**
    * Show the tab content according the item
    * @param CommonGLPI $item the item on which the tab is to be displayed
    * @param int $tabnum number of the tab
    * @param boolean $withtemplate if with a template or basic, by default is false
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if (in_array($item->getType(), static::$linkableClasses)) {
         static::showForItem($item);
      }
   }

   /**
    * If the user can view an item
    * @return the item to view
    */
   public function canViewItem() {
      if ($this->isNewItem()) {
         return false;
      }
      $itemtype = $this->fields['itemtype'];
      $item = new $itemtype();
      $item->getFromDB($this->fields['items_id']);
      return $item->canViewItem();
   }

   /**
    * Actions done when a package is being purged
    * @param CommonDBTM $item item being purged
    * @return boolean true if success, false otherwise
    */
   public static function hook_pre_item_purge(CommonDBTM $item) {
      if (in_array($item->getType(), static::getLinkableClasses())) {
         $report = new static();
         return $report->deleteByCriteria([
            'itemtype' => $item->getType(),
            'items_id' => $item->getID()
         ], 1);
      }
      return true;
   }

   /**
    * Pushes tasks to the APK scanner webservice
    * @param CronTask $crontask
    * @return integer >0 means done, < 0 means not finished, 0 means nothing to do
    */
   public static function cronPushTask(CronTask $crontask) {
      global $DB;

      $cronStatus = 0;

      // prepare request to find pending reports (pending means queued)
      $request = [
        'FROM' => static::getTable(),
        'WHERE' => ['AND' => [
           'status' => self::STATUS_PENDING,
        ]]
      ];

      foreach ($DB->request($request) as $data) {
         // Dumb way for not too large apk !
         $report = new static();
         $report->getFromResultSet($data);
         $response = null;
         try {
            $response = $report->pushTask();
            // The report is successfully created in the service
            $taskData = json_decode($response, JSON_OBJECT_AS_ARRAY);
            $report->update([
               'id'        => $report->getField('id'),
               'status'    => self::STATUS_SENT,
               'remote_id' => $taskData['task']['$oid']
            ]);
         } catch (ClientException $e) {
            $report->update([
               'id'     => $report->getField('id'),
               'status' => self::STATUS_FAILED,
            ]);
         } catch (ServerException $e) {
            $report->update([
               'id'     => $report->getField('id'),
               'status' => self::STATUS_FAILED,
            ]);
         } catch (RequestException $e) {
            $report->update([
               'id'     => $report->getField('id'),
               'status' => self::STATUS_FAILED,
            ]);
         } catch (Exception $e) {
            $report->update([
               'id'     => $report->getField('id'),
               'status' => self::STATUS_FAILED,
            ]);
         }
         $crontask->addVolume(1);
         $cronStatus++;
      }

      return $cronStatus;
   }

   /**
    * Sends to Orion webservice a file for analysis
    * @return string body of the answer if successful
    */
   private function pushTask() {
      // settings for the HTTP request to Orion webservice
      $config = PluginOrionConfig::getConfigurationValues();
      $options = [
         'headers' => [
            'Content-Type' => 'application/json',
            'apikey'      => $config['orion_api_key']
         ],
      ];
      $filename = $this->fields['filename'];
      $options['body'] = json_encode([
         'filename' => basename($filename),
         'visibility' => 'group',
         'force' => false,
         'callback_url' => '',
         'data' => base64_encode(file_get_contents(GLPI_DOC_DIR . '/' . $filename)),
      ]);

      $url = $config['orion_server'] . '/v1.0/';
      $httpClient = new HttpClient(['base_uri' => $url]);

      $response = $httpClient->request('POST', 'tasks', $options);
      if (!isset($response) || $response->getStatusCode() !== 201) {
         throw new Exception('Unexpected Response');
      }

      return $response->getBody();
   }

   /**
    * Updates progress status of tasks
    * @param CronTask $crontask
    * @return integer >0 means done, < 0 means not finished, 0 means nothing to do
    */
   public static function cronUpdateStatus(CronTask $crontask) {
      global $DB;

      $cronStatus = 0;

      $config = Config::getConfigurationValues('orion');
      $request = [
         'FROM' => static::getTable(),
         'WHERE' => ['status' => [self::STATUS_SENT, self::STATUS_RUNNING]
         ]
      ];

      $options = [
         'headers' => [
            'Content-Type' => 'application/json',
            'apikey'       => $config['orion_api_key']
         ],
      ];
      $url = $config['orion_server'] . '/v1.0/';
      $httpClient = new HttpClient(['base_uri' => $url]);
      foreach ($DB->request($request) as $data) {
         $task = new static();
         $task->getFromDB($data['id']);
         $response = null;
         try {
            $response = $httpClient->request('GET', 'tasks/id=' . $data['remote_id'], $options);
         } catch (ClientException $e) {
            $task->update([
               'id'     => $data['id'],
               'status' => self::STATUS_FAILED,
            ]);
         } catch (ServerException $e) {
            $task->update([
               'id'     => $data['id'],
               'status' => self::STATUS_FAILED,
            ]);
         } catch (RequestException $e) {
            $task->update([
               'id'     => $data['id'],
               'status' => self::STATUS_FAILED,
            ]);
         }
         if ($response !== null && $response->getStatusCode() === 200) {
            $taskData = json_decode($response->getBody(), JSON_OBJECT_AS_ARRAY);
            $sha256 = $taskData['task']['sha256'];
            switch ($taskData['task']['status']) {
               case 0:
                  $status = self::STATUS_SENT;
                  break;
               case 1:
                  $status = self::STATUS_RUNNING;
                  break;
               case 2:
                  $status = self::STATUS_DONE;
                  break;
               case 3:
                  $status = self::STATUS_FAILED;
                  break;
            }

            // Update the local status of the task
            $task->update([
               'id'           => $data['id'],
               'status'       => $status,
               'sha256'       => $sha256,
            ]);

            // Get the report of the  task
            if ($status === self::STATUS_DONE) {
               $task->downloadReport();
            }

         }
      }

      return $cronStatus;
   }

   /**
    * downloads an analysis report and save it
    * @return string filename
    */
   private function downloadReport() {
      $config = Config::getConfigurationValues('orion');
      $url = $config['orion_server'] . '/v2.0/';
      $httpClient = new HttpClient(['base_uri' => $url]);
      $options = [
         'headers' => [
            'Content-Type' => 'application/json',
            'apikey'       => $config['orion_api_key']
         ],
      ];
      $sha256 = $this->fields['sha256'];
      try {
         $response = $httpClient->request('GET', "filereports/$sha256", $options);
      } catch (ClientException $e) {
         $this->update([
            'id'     => $this->fields['id'],
            'status' => self::STATUS_FAILED,
         ]);
      } catch (ServerException $e) {
         $this->update([
            'id'     => $this->fields['id'],
            'status' => self::STATUS_FAILED,
         ]);
      }
      if (isset($response) && $response->getStatusCode() == 200) {
         $json = $response->getBody();

         // Workaround a malformed json from Orion
         $json = str_replace(["\r", "\n"], '', $json);

         // beautify json before saving it
         $decodedReport = json_decode($json, JSON_PRETTY_PRINT || JSON_OBJECT_AS_ARRAY);
         $json = json_encode($decodedReport);

         $evaluation = 'n/a';
         if (isset($decodedReport['filereport']['overview']['risk']['level'])) {
            $evaluation = $decodedReport['filereport']['overview']['risk']['level'];
         }

         $date_report = null;
         if (isset($decodedReport['filereport']['overview']['submissions']['last_date'])) {
            $date_report = $decodedReport['filereport']['overview']['submissions']['last_date'];
            $date = DateTime::createFromFormat('Y-m-d\TH:i:s\.u\Z', $date_report);
            $date_report = $date->format('Y-m-d H:i:s');
         }
         $this->update([
            'id'           => $this->fields['id'],
            'status'       => self::STATUS_DONE,
            'report'       => $json,
            'date_report'  => $date_report,
            'evaluation'   => $evaluation,
         ]);
      }
   }

   /**
    * Shows report list for the item
    * @param CommonDBTM $item
    */
   public static function showForItem(CommonDBTM $item) {
      if (isset($_GET["start"])) {
         $start = intval($_GET["start"]);
      } else {
         $start = 0;
      }

      $dbUtils = new DbUtils();

      // Total Number of agents
      $items_id = $item->getField('id');
      $itemtype = $item->getType();
      $number = $dbUtils->countElementsInTableForMyEntities(
         static::getTable(),
         [
            'itemtype' => $itemtype,
            'items_id' => $items_id,
         ]
      );

      // get the pager
      $pager = Html::printAjaxPager(self::getTypeName(1), $start, $number, '', false);
      $pager = ''; // disabled because the results are not paged yet

      // get items
      $report = new static();
      $condition = "`itemtype` = '$itemtype' AND `items_id` = '$items_id'" . $dbUtils->getEntitiesRestrictRequest();
      $rows = $report->find($condition, '', '');

      foreach ($rows as &$row) {
         $row['status']     = __($row['status'], 'orion');
         $row['evaluation'] = __($row['evaluation'], 'orion');
      }
      $data = [
         'number'    => $number,
         'pager'     => $pager,
         'reports'   => $rows,
      ];

      $twig = plugin_orion_getTemplateEngine();
      echo $twig->render('report.html', $data);
   }
}
