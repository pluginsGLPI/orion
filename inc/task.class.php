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

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client as HttpClient;

class PluginOrionTask extends CommonDBTM
{

   public function prepareInputForAdd($input) {
      // When adding a task, the status must be 'pending' (which is the default in the schema)
      unset($input['status']);
      //When adding a task, the report is not available so prevent setting it
      unset($input['report_file']);

      return $input;
   }

   /**
    * Pushes tasks to the APK scanner webservice
    *
    * @param CronTask $crontask
    *
    * @return integer >0 means done, < 0 means not finished, 0 means nothing to do
    */
   public static function cronPushTask(CronTask $crontask) {
      global $DB;

      $cronStatus = 0;

      $config = Config::getConfigurationValues('orion');
      $request = [
        'FROM' => PluginOrionTask::getTable(),
        'WHERE' => ['AND' => [
           'status' => 'pending',
        ]]
      ];
      $options = [
        'headers' => [
           'Content-Type' => 'application/json',
            'apikey'      => $config['orion_api_key']
        ],
      ];
      $url = $config['orion_server'] . '/' . PLUGIN_ORION_API_VERSION . '/';
      $httpClient = new HttpClient(['base_uri' => $url]);
      foreach ($DB->request($request) as $data) {
         // Dumb way for not too large apk !
         $body = [
            'filename' => basename($data['filename']),
            'data' => base64_encode(file_get_contents(GLPI_DOC_DIR . '/' . $data['filename'])),
            'visibility' => 'user',
            'callback_url' => '',
         ];
         try {
            $request = $httpClient->request('POST', 'tasks', $options, $body);
            $response = $httpClient->send($request);
         } catch (ClientException $e) {
            $task = new PluginOrionTask();
            $task->update([
               'id'     => $data['id'],
               'status' => 'failed'
            ]);
         }
         if (isset($response) && $response->getStatusCode() === 201) {
            // The task is successfully created in the service
            $taskData = json_decode($response->getBody(), JSON_OBJECT_AS_ARRAY);

            $task = new PluginOrionTask();
            $task->update([
               'id'        => $data['id'],
               'status'    => 'sent',
               'remote_id' => $taskData['task']['$oid']
            ]);

         }
         $cronStatus++;
      }

      return $cronStatus;
   }

   /**
    * Updates progress status of tasks
    *
    * @param CronTask $crontask
    *
    * @return integer >0 means done, < 0 means not finished, 0 means nothing to do
    */
   public static function cronUpdateStatus(CronTask $crontask) {
      global $DB;

      $cronStatus = 0;

      $config = Config::getConfigurationValues('orion');
      $request = [
         'FROM' => PluginOrionTask::getTable(),
         'WHERE' => ['OR' => [
            'status' => 'sent',
            'status' => 'running',
         ]]
      ];

      $options = [
         'headers' => [
            'Content-Type' => 'application/json',
            'apikey'       => $config['orion_api_key']
         ],
      ];
      $url = $config['orion_server'] . '/' . PLUGIN_ORION_API_VERSION . '/';
      $httpClient = new HttpClient(['base_uri' => $url]);
      foreach ($DB->request($request) as $data) {
         $task = new static();
         try {
            $request = $httpClient->request('GET', 'tasks?id=' . $data['remote_id'], $options);
            $response = $httpClient->send($request);
         } catch (ClientException $e) {
            $task->update([
               'id'     => $data['id'],
               'status' => 'failed'
            ]);
         }
         if (isset($response) && $response->getStatusCode() === 201) {
            $taskData = json_decode($response->getBody(), JSON_OBJECT_AS_ARRAY);
            $sha256 = $taskData['sha256'];
            switch ($taskData['status']) {
               case 0:
                  $status = 'sent';
                  break;
               case 1:
                  $status = 'running';
                  break;
               case 2:
                  $status = 'done';
                  break;
               case 3:
                  $status = 'failed';
                  break;
            }

            // Get the report of the  task
            if ($status === 'done') {
               $reportFilename = $task->downloadReport($sha256, $httpClient);

            }

            // Update the local status of the task
            $task->update([
               'id'           => $data['id'],
               'status'       => $status,
               'remote_id'    => $taskData['task']['_id']['$oid'],
               'report_file'  => $reportFilename,
            ]);
         }
      }

      return $cronStatus;
   }

   /**
    * downloads a analysis report and save it
    *
    * @param string $sha256
    * @param GuzzleHttp\Client $httpClient
    *
    * @return string filename
    */
   private function downloadReport($sha256, GuzzleHttp\Client $httpClient) {
      $config = Config::getConfigurationValues('orion');
      $options = [
         'headers' => [
            'Content-Type' => 'application/json',
            'apikey'       => $config['orion_api_key']
         ],
      ];
      $request = $httpClient->request('GET', "filereports/$sha256", $options);
      $response = $httpClient->send($request);
      $filename = ORION_REPORT_PATH . "$sha256.pdf";
      file_put_contents($filename, $response->getBody());

      return str_replace(GLPI_PLUGIN_DOC_DIR, '', $filename);
   }
}