<?php

/**
 *  this lets you easily unit-test your symfony forms with a set
 *  of datas defined in a .yml file, you define sets which should pass,
 *  you define sets that should fail, and define the errors you expect
 *
 * @copyright 2010 by Robert Schönthal
 * @package rsFormTester
 * @subpackage lib
 */
class rsFormTester
{
  /**
   * @var array $configuration the current configuration for the tester
   */
  protected $configuration = array(
    'withSave' => false,
    'unset'    => array(),
    'formClass' => null,
    'verbose' => true,
    'options' => array(),
    'arguments' => array()
  );

  /**
   * @var array $validData the valid dataSets
   * @var array $invalidData the invalid dataSets
   * @var array $messages the current messages for this set
   */
  protected $validData, $invalidData, $messages = array();

  /**
   * @var sfForm $form the current form instance to test
   */
  protected $form;

  /**
   * creates an instance the short way from a .yml file
   *
   * @param string $file
   * @return rsFormTester
   */
  public static function create($file)
  {
    $instance = new self();
    $instance->loadFile($file);

    return $instance;
  }

  /**
   * loads a .yml file, and sets the configuration
   *
   * @param string $file
   * @return rsFormTester
   */
  public function loadFile($file)
  {
    if(!file_exists($file))
    {
      throw new InvalidArgumentException('configuration file not found');
    }

    $this->loadArray(sfYaml::load($file));

    return $this;
  }

  /**
   * loads the configuration from an array
   *
   * @param array $config
   * @return rsFormTester
   */
  public function loadArray(array $config)
  {
    $this->configuration = isset($config['configuration']) ? array_merge($this->configuration,$config['configuration']) : $this->configuration;
    $this->validData = isset($config['pass']) ? $config['pass'] : array();
    $this->invalidData = isset($config['fail']) ? $config['fail'] : array();

    return $this;
  }

  /**
   * handles simple getter for attributes
   *
   * @param string $name
   * @param mixed $arguments
   * @return mixed
   * @throws BadMethodCallException
   */
  public function  __call($name, $arguments)
  {
    $var = lcfirst(substr($name,3));

    if(isset($this->$var))
    {
      return $this->$var;
    }

    throw new BadMethodCallException(sprintf('method "%s" not found in %s',$name,get_class($this)));
  }

  /**
   * returns an attribute from the configuration
   *
   * @param string $name
   * @return mixed
   * @throws InvalidArgumentException
   */
  public function getAttribute($name)
  {
    if(array_key_exists($name, $this->configuration))
    {
      return $this->configuration[$name];
    }

    throw new InvalidArgumentException(sprintf('configuration option [%s] not found',$name));
  }

  /**
   * returns the form instance, creates and configures the form if needed
   *
   * @param array $options
   * @param array arguments
   * @return sfForm
   * @throws LogicException
   */
  public function getForm(array $options=array(), array $arguments=array())
  {
    $options = array_merge($options,$this->getAttribute('options'));
    $arguments = array_merge($arguments,$this->getAttribute('arguments'));

    if(!$this->form && $formClass = $this->getAttribute('formClass'))
    {
      $this->form = new $formClass($options,$arguments);
    }

    if(!$this->form)
    {
      throw new LogicException('no form is set in configuration');
    }

    $this->sanitizeForm();

    return $this->form;
  }

  /**
   * for setting an already instanciated form (e.g. special options or attributes)
   *
   * @param sfForm $form
   * @return rsFormTester
   */
  public function setForm(sfForm $form)
  {
    $this->configuration['formClass'] = get_class($form);
    $this->form = $form;

    $this->sanitizeForm();

    return $this;
  }

  /**
   * run the tests for the dataSets
   * use $which = 'valid' for the valid Datasets
   * use $which = 'invalid' for the invalid Datasets
   * leave empty for both sets
   *
   * @param mixed $tester (should implemented the lime interface)
   * @param string $which
   * @return rsFormTester
   */
  public function testData($tester, $which = null)
  {
    if(!$which)
    {
      $this->testData($tester,'valid');
      $this->testData($tester,'invalid');

      return $this;
    }

    $sets = ($which == 'valid') ? $this->validData : $this->invalidData;

    foreach((array) $sets as $key => $dataSet)
    {
      $expectedErrors = isset($dataSet['_expectedErrors']) ? $dataSet['_expectedErrors'] : array();
      $options = isset($dataSet['_options']) ? $dataSet['_options'] : array();
      $arguments = isset($dataSet['_arguments']) ? $dataSet['_arguments'] : array();

      $dataSet = $this->sanitizeDataSet($dataSet);

      if($options || $arguments)
      {
        $this->getForm($options, $arguments);
      }

      //recreate form if options and arguments passed
      $this->form->bind($dataSet);

      //handle either valid checks or invalid checks on the bound form
      if($which == 'valid')
      {
        $this->checkValidForm($tester,$key);
      }
      else
      {
        $this->checkInvalidForm($tester,$key,$expectedErrors);        
      }
    }

    return $this;
  }

  /**
   * check the form for a valid dataset
   *
   * @param mixed $tester
   * @param mixed $key the dataSet index
   */
  protected function checkValidForm($tester, $key)
  {
    if(!$this->form->isValid())
    {
      //the form isnt valid so fail the test and print the errors
      $tester->fail(sprintf("form is valid for dataset [%s]",$key));

      //check for errors if needed
      if($this->getAttribute('verbose'))
      {
        $this->iterateErrors($this->form->getErrorSchema(),array());
        $this->flushMessages($tester,'valid',$key, true);
      }
    }
    else
    {
      $tester->is($this->form->isValid(),true,sprintf("form is valid for dataset [%s]",$key));

      //everything ok, save the form if needed and pass the test
      if($this->getAttribute('withSave'))
      {
        try
        {
          $this->form->save();
        }
        catch(Exception $e)
        {
          //the form couldnt be saved, so fail the test
          $tester->fail(sprintf("form saving successfull for dataset [%s]",$key));

          if($this->getAttribute('verbose'))
          {
            $tester->info($e->getMessage());
          }
        }
      }
    }
  }

  /**
   * checks the form for a invalid dateset
   *
   * @param mixed $tester
   * @param int $key the dataSet index
   * @param array $expectedErrors the expected errors for this set
   */
  protected function checkInvalidForm($tester, $key, $expectedErrors)
  {    
    if($this->form->isValid())
    {
      //the form is valid, so fail the test
      $tester->fail(sprintf("form is invalid for dataset [%s]",$key));
    }
    else
    {
      //everything ok, the form isnt valid, so pass the test
      $tester->pass(sprintf("form is invalid for dataset [%s]",$key));
    }

    //check for errors if needed
    if($this->getAttribute('verbose'))
    {
      $expectedErrors = $this->iterateErrors($this->form->getErrorSchema(),$expectedErrors);

      $this->addExpectedErrorMessages($expectedErrors);
    }

    $this->flushMessages($tester,'invalid',$key);

  }

  /**
   * iterate the errorschema and print the errors
   *
   * @param sfValidatorError $schema
   * @param array $expectedErrors
   * @param string $prefix to be able to define foo/bar/bazz as field
   */
  protected function iterateErrors(sfValidatorError $schema,$expectedErrors,$prefix='')
  {
    foreach($schema->getErrors() as $field => $error)
    {
      //an embedded form, recursion
      if($error instanceof sfValidatorErrorSchema)
      {
        $expectedErrors = $this->iterateErrors($error,$expectedErrors,$prefix.$field.'/');
      }
      else
      {
        //cleanup message for finding expected errors in global/named errors
        $message = $this->sanitizeMessage($error->getMessage());
        $field = ($message != $error->getMessage()) ? $message : $field;
        
        //not found in expected errors, so add a message
        if(!$expectedErrors || !in_array($prefix.$field,$expectedErrors))
        {
          $this->messages[] = '[error] '.($message != $error->getMessage() && !$prefix ? $error : '"'.$prefix.$field.'"').' raised';
        }

        //TODO must be easier to unset a array field by value
        $expectedErrors = array_flip($expectedErrors);
        unset($expectedErrors[$prefix.$field]);
        $expectedErrors = array_flip($expectedErrors);
      }
    }

    return $expectedErrors;
  }

  /**
   * converts foo/bar/bazz into an array, leaves arrays as arrays, returns plain string
   *
   * @param mixed $field
   * @return mixed
   */
  protected function sanitizeFieldName($field)
  {
    if(!is_array($field) && strpos($field, '/'))
    {
      $field = split('/', $field);
    }

    return $field;
  }

  /**
   * prepares the dataset for binding
   *
   * @param array $set
   * @return array
   */
  protected function sanitizeDataSet(array $set)
  {
    //add csrf field
    if($this->getForm()->isCSRFProtected())
    {
      $set = array_merge($set,array($this->form->getCSRFFieldName() => $this->form->getCSRFToken()));
    }

    //unset the config options for this set
    unset($set['_expectedErrors'],$set['_options'],$set['_arguments']);

    return $set;
  }

  /**
   * prepares the form for binding
   */
  protected function sanitizeForm()
  {
    foreach($this->getAttribute('unset') as $field)
    {
      $this->unsetField($this->form->getWidgetSchema(),$this->form->getValidatorSchema(),$field);
    }
  }

  /**
   * unsets all fields defined in the configuration
   *
   * @param sfWidgetFormSchema $wschema
   * @param sfValidatorSchema $vschema
   * @param string $field
   */
  protected function unsetField(sfWidgetFormSchema &$wschema, sfValidatorSchema &$vschema, $field)
  {
    $field = $this->sanitizeFieldName($field);

    if(is_array($field) && $index = array_shift($field))
    {
      if(count($field) && $wschema->offsetGet($index))
      {
        $this->unsetField($wschema[$index],$vschema[$index],$field);
      }
      else
      {
        unset($wschema[$index]);
        unset($vschema[$index]);
      }
    }
    else
    {
      unset($wschema[$field]);
      unset($vschema[$field]);
    }
  }

  /**
   * prepares a message for later finding in arrays
   * define a "foo" field in expectedErrors and dont add a foo field to the form
   * will raise an unexpected field "foo" error
   * this function parses the fields out of error messages
   *
   * @param string $message
   * @return string
   */
  protected function sanitizeMessage($message)
  {
    //TODO bad finding for expectedError field in error message
    if(strpos($message, '"'))
    {
      $message = substr($message, strpos($message, ' "')+2);
      $message = substr($message, 0, strpos($message, '".'));
    }

    return $message;
  }

  /**
   * prints the messages for a dateSet
   *
   * @param mixed $tester
   * @param string $setType
   * @param boolean force
   * @return string
   */
  protected function flushMessages($tester,$setType,$setName, $force=false)
  {
    if($force || ($this->getAttribute('verbose') && $this->messages))
    {
      sort($this->messages);
      $messages = join("\n",$this->messages);
      $tester->info(sprintf("%s set[%s]:\n%s\n",$setType,$setName,$messages));
    }

    $this->messages = array();
  }

  /**
   * adds the expected errors to the messages list
   * this array should first be cleanup by iterate errors
   *
   * @param array $expectedErrors
   */
  protected function addExpectedErrorMessages($expectedErrors)
  {
    foreach($expectedErrors as $error)
    {
      $this->messages[] = sprintf('[expected error] "%s" not raised',$error);
    }
  }
}