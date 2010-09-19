<?php

class rsFormTester
{
  // the default configuration
  protected $configuration = array(
    'withSave' => false,
    'unset'    => array(),
    'formClass' => null,
    'verbose' => true
  );

  //the both datasets
  protected $validData,$invalidData = array();

  protected $form;
  protected $messages = array();

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
   * @return sfForm
   * @throws LogicException
   */
  public function getForm()
  {
    if(!$this->form && $formClass = $this->getAttribute('formClass'))
    {
      $this->form = new $formClass();
      $this->sanitizeForm();
    }

    if(!$this->form)
    {
      throw new LogicException('no form is set in configuration');
    }

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

    $tester->diag('testing '.$which.' data');

    $sets = ($which == 'valid') ? $this->validData : $this->invalidData;

    foreach($sets as $key => $dataSet)
    {
      $expectedErrors = isset($dataSet['_expectedErrors']) ? $dataSet['_expectedErrors'] : array();
      $dataSet = $this->sanitizeDataSet($dataSet);

      $this->form->rewind();
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

      //display all messages for this set
      $this->flushMessages($tester);
    }

    return $this;
  }

  /**
   * prints the messages for a dateSet
   *
   * @param mixed $tester
   */
  protected function flushMessages($tester)
  {
    if($this->messages)
    {
      sort($this->messages);
      $tester->error(join("\n",$this->messages));
      $this->messages = array();
    }
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
      $tester->fail(sprintf('form is valid for dataset [%s]',$key+1));
    }
    else
    {
      //everything ok, save the form if needed and pass the test
      if($this->getAttribute('withSave'))
      {
        $this->form->save();
      }

      $tester->is($this->form->isValid(),true,sprintf('form is valid for dataset [%s]',$key+1));
    }

    //check for errors if needed
    if($this->getAttribute('verbose'))
    {
      $this->iterateErrors($this->form->getErrorSchema(),array());
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
      $tester->fail(sprintf('form is invalid for dataset [%s]',$key+1));
    }
    else
    {
      //everything ok, the form isnt valid, so pass the test
      $tester->pass(sprintf('form is invalid for dataset [%s]',$key+1));
    }
    
    //check for errors if needed
    if($this->getAttribute('verbose'))
    {
      $this->checkForExpectedErrors($expectedErrors);
      $this->iterateErrors($this->form->getErrorSchema(),$expectedErrors);
    }
  }

  /**
   * checks a dataset against the expectedErrors attribute set
   *
   * @param array $expectedErrors
   */
  protected function checkForExpectedErrors($expectedErrors)
  {
    //rewrite the global/named errors to be able to define a "foofile" option
    //which is raised as an unexpected extra form field error
    $globalErrors = $this->sanitizeErrorSchema($this->form->getErrorSchema()->getGlobalErrors());
    $namedErrors = $this->sanitizeErrorSchema($this->form->getErrorSchema()->getNamedErrors());

    foreach($expectedErrors as $error)
    {
      $fieldError = $this->getErrorForField($this->form->getErrorSchema(),$error);
      $globalError = in_array($error, $globalErrors);
      $namedError = in_array($error, $namedErrors);

      //the expected error was not raised, so add add a message
      if(!$fieldError && !$globalError && !$namedError)
      {
        $this->messages[] = 'info: expected error "'.$error.'" not raised';
      }
    }
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
        $this->iterateErrors($error,$expectedErrors,$prefix.$field.'/');
      }

      //cleanup message for finding expected errors in global/named errors
      $message = $this->sanitizeMessage($error->getMessage());
      $field = ($message != $error->getMessage()) ? $message : $field;

      //not found in expected errors, so add a message
      if(!in_array($prefix.$field,$expectedErrors))
      {
        $this->messages[] = 'error: '.($message != $error->getMessage() ? $error : '"'.$prefix.$field.'"').' raised';
      }
    }
  }

  /**
   * returns a sfValidatorError for a field 
   * foo/bar/bazz will search in embedded errorSchemas
   *
   * @param sfValidatorError $error
   * @param string $field
   * @return sfValidatorError
   */
  protected function getErrorForField(sfValidatorError $error,$field)
  {
    $field = $this->sanitizeFieldName($field);
    
    if(is_array($field) && $index = array_shift($field))
    {
      if(count($field) && $error->offsetExists($index))
      {
        return $this->getErrorForField($error->offsetGet($index),$field);
      }

      return $error->offsetGet($index);
    }
    else
    {
      return $error->offsetGet($field);
    }
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

    //unset the expectedErrors config
    unset($set['_expectedErrors']);

    return $set;
  }

  /**
   * prepares the form for binding
   */
  protected function sanitizeForm()
  {
    //TODO this wont work as it should be
    foreach($this->getAttribute('unset') as $field)
    {
      //$this->unsetField($this->form->getFormFieldSchema(),$field);
    }
  }

  /*protected function unsetField(sfFormFieldSchema &$schema,$field)
  {
    $field = $this->sanitizeFieldName($field);

    if(is_array($field) && $index = array_shift($field))
    {
      if(count($field) && $schema->offsetGet($index))
      {
        $this->unsetField($schema[$index],$field);
      }
      else
      {
        unset($schema[$index]);
      }
    }
    else
    {
      unset($schema[$field]);
    }
  }*/

  /**
   * sanitizes a errorschema and the messages (global/named)
   *
   * @param array $errors
   * @return array
   */
  protected function sanitizeErrorSchema($errors)
  {
    $newErrors = array();

    foreach($errors as $k => $error)
    {
      $newErrors[] = $this->sanitizeMessage($error->getMessage());
    }

    return $newErrors;
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

}