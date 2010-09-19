<?php

class rsFormTesterForm extends sfForm
{
  public function configure()
  {
    parent::configure();

    $this->widgetSchema['foo'] = new sfWidgetFormInput();
    $this->widgetSchema['bar'] = new sfWidgetFormInput();

    $this->validatorSchema['foo'] = new sfValidatorString(array('required'=>true));
    $this->validatorSchema['bar'] = new sfValidatorString(array('required'=>true));

    $this->enableCSRFProtection('foo');
    $this->embedForm('bazz', new rsFormTesterForm2());
  }

  public function save()
  {    
  }
}

class rsFormTesterForm2 extends sfForm
{
  public function configure()
  {
    parent::configure();

    $this->widgetSchema['foo'] = new sfWidgetFormInput();
    $this->widgetSchema['bar'] = new sfWidgetFormInput();

    $this->validatorSchema['foo'] = new sfValidatorString(array('required'=>true));
    $this->validatorSchema['bar'] = new sfValidatorString(array('required'=>true));
    
    $this->embedForm('bazz', new rsFormTesterForm3());
  }

  public function save()
  {    
  }
}

class rsFormTesterForm3 extends sfForm
{
  public function configure()
  {
    parent::configure();

    $this->widgetSchema['foo'] = new sfWidgetFormInput();
    $this->widgetSchema['bar'] = new sfWidgetFormInput();

    $this->validatorSchema['foo'] = new sfValidatorString(array('required'=>true));
    $this->validatorSchema['bar'] = new sfValidatorString(array('required'=>true));
  }

  public function save()
  {
  }
}
