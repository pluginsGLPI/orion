<?php
class PluginOrionTask extends CommonDBTM
{
   /**
    * Updates progress status of tasks
    *
    * @param CronTask $crontask
    *
    * @return integer >0 means done, < 0 means not finished, 0 means nothing to do
    */
   public static function cronUpdateTask(CronTask $crontask) {
      $cronStatus = 0;

      return $cronStatus;
   }
}