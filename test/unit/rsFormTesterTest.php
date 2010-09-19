<?php

require_once dirname(__FILE__).'/../bootstrap/unit.php';
require_once dirname(__FILE__).'/../fixtures/project/lib/form/rsFormTesterForm.php';

$file = dirname(__FILE__).'/../fixtures/project/test/fixtures/form_tester.yml';
$file_config = sfYaml::load($file);
$defaults = array(
    'withSave' => false,
    'unset'    => array(),
    'formClass'=> null,
    'verbose' => true
  );

$testCount = 18;
$t = new lime_test();

//@Test instanciations
$t->diag('test instanciations');
$tester = new rsFormTester();
$t->is(get_class($tester),'rsFormTester','correct instanciated the oldskool way');
$tester = rsFormTester::create($file);
$t->is(get_class($tester),'rsFormTester','correct instanciated through ::create');

//@Test file loading
$t->diag('test data loading');
$t->is($tester->loadArray($file_config),$tester,'array loaded and method is chained');
$t->is($tester->loadFile($file),$tester,'file loaded and method is chained');

try{
  $tester->loadFile('foo.yml');
  $t->fail();
}catch(Exception $e){
  $t->pass('loadFile throws a exception if file not found');
}

//@Test configuration
$t->diag('test configuration');
$tester2 = new rsFormTester();
$default_config = $tester2->getConfiguration();
$t->is($defaults,$default_config,'defaults are set correctly');
$t->is(count($tester->getConfiguration()),count(array_merge($defaults,$file_config['configuration'])),'attribute count matches');
$t->is($tester->getAttribute('withSave'),$file_config['configuration']['withSave'],'attribute correct fetched');
try{
  $tester->getAttribute('foo');
  $t->fail();
}catch(Exception $e){
  $t->pass('getAttribute throws an exception for a invalid configuration option');
}
try{
  $tester->getFoo();
  $t->fail();
}catch(Exception $e){
  $t->pass('unknown getter throws expection');
}

//@Test Datasets
$t->diag('test data sets');
$validData = $tester->getValidData();
$t->is(is_array($validData),true,'validData is an array');
$t->is($validData,$file_config['pass'],'validData matches');

$invalidData = $tester->getInvalidData();
$t->is(is_array($invalidData),true,'invalidData is an array');
$t->is($invalidData,$file_config['fail'],'invalidData matches');

//@Test form tests
$t->diag('form tests');
$form = new rsFormTesterForm();
$t->is(get_class($tester->getForm()),get_class($form),'form correct instanciated');
$tester->setForm($form);
$t->is(get_class($tester->getForm()),get_class($form),'form instance set correctly');
$tester2 = new rsFormTester();
try{
  $tester2->getForm();
  $t->fail();
}catch(Exception $e){
  $t->pass('getForm throws an exception if no form could be returned');
}

//@Test testing data
$t->diag('testing data');
$test = new lime_test(null,array('verbose'=>true));
#$t->is($tester->testData($test,'valid'),$tester,'valid data tested');
#$t->is($tester->testData($test,'invalid'),$tester,'invalid data tested');
$t->is($tester->testData($test),$tester,'both data tested');
