<?php
namespace tests\units;

use GlpiPlugin\Orion\Exception\IncompatibleItemtypeException;
use Glpi\Test\CommonTestCase;
use \PluginFlyvemdmPackage;
use CronTask;
use Document;
use PluginFooScannable;
use mageekguy\atoum\asserters\testedClass;

class PluginOrionReport extends CommonTestCase {
   public function beforeTestMethod($method) {
      $this->resetState();
      parent::beforeTestMethod($method);
      $this->setupGLPIFramework();
      $this->login('glpi', 'glpi');
   }

   /**
    * Tests registration of an itemtype for use with Item_Report relation
    */
   public function testRegisterClass() {
      global $PLUGIN_HOOKS;

      // Test an incompatible itemtype is not registrable
      $instance = $this->newTestedInstance();
      $this->exception(function() use ($instance) {
         $instance::registerItemtype(Document::class);
      });
      $this->exception($this->exception)->isInstanceOf(IncompatibleItemtypeException::class);

      // Test a comaptible itemtype is registrable

      $this->array($instance::getLinkableClasses())->contains(PluginFlyvemdmPackage::class);
      $this->array($PLUGIN_HOOKS)->hasKey('pre_item_purge');
      $this->array($PLUGIN_HOOKS['pre_item_purge'])->hasKey('orion');
      $this->array($PLUGIN_HOOKS['pre_item_purge']['orion'])->hasKey(PluginFlyvemdmPackage::class);
      $this->string($PLUGIN_HOOKS['pre_item_purge']['orion'][PluginFlyvemdmPackage::class])->isEqualTo('plugin_orion_hook_pre_item_purge');
   }

   /**
    * @dataProvider prapareInputForAddProvider
    * @param array $input
    * @param array $expected
    */
   public function testPrepareInputForAdd($input, $expected) {
      $forbiddenKeys = [
         'status',
         'remote_id',
         'sha256',
         'report',
         'date_report',
         'evaluation',
      ];

      $testedObject = $this->newTestedInstance();
      $output = $testedObject->prepareInputForAdd($input);
      $this->array($output)->notHasKeys($forbiddenKeys);
   }

   /**
    * data provider
    */
   protected function prapareInputForAddProvider() {
      return [
         [
            'input' => [
               'filename'     => 'myfile.exe',
               'itemtype'     => PluginFlyvemdmPackage::class,
               'items_id'     => 1,
               'status'       => 'anything',
               'remote_id'    => '123',
               'report'       => 'anything',
               'date_report'  => '2017-10-10 00:00:00',
               'evaluation'   => 'high'
            ],
            'expected' => [
               'filename'     => 'myfile.exe',
               'itemtype'     => PluginFlyvemdmPackage::class,
               'items_id'     => 1,
            ],
         ],
      ];
   }

   public function testHook_pre_item_purge() {
      require_once(__DIR__ . '/../fixtures/PluginFooScannable.php');

      $testedObject = $this->newTestedInstance();
      $testedObject::registerItemtype(PluginFooScannable::class);

      $scannable = new PluginFooScannable();
      $scannable->getFromResultSet(['id' => 1]);
      $this->boolean($scannable->isNewItem())->isFalse();

      $testedObject->add([
         'itemtype' => $scannable::getType(),
         'items_id' => $scannable->getID()
      ]);

      $rows = $testedObject->find();
      $this->array($rows)->size->isEqualTo(1);

      plugin_orion_hook_pre_item_purge($scannable);

      $rows = $testedObject->find();
      $this->array($rows)->size->isEqualTo(0);
   }
}
