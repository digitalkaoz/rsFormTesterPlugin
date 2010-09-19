rsFormTesterPlugin
========

*test your symfony forms painless*


Installation
------------------

**svn:**
svn co [http://svn.github.com/digitalkaoz/rsFormTesterPlugin.git](http://svn.github.com/digitalkaoz/rsFormTesterPlugin.git)

**git:**
git clone [git@github.com:digitalkaoz/rsFormTesterPlugin.git](git@github.com:digitalkaoz/rsFormTesterPlugin.git)

**pear:**
symfony plugin install rsFormTesterPlugin

**enable the plugin in your *config/ProjectConfiguration.class.php* !**

Configuration
-------------

    configuration:
      formClass: fooForm
      withSave: true
      verbose: true

    pass:
      -
        foo: bar
        bar: bazz
        bazz:
          bar: foo
          foo: bar
      # ...
    fail:
      -
        _expectedErrors: [foo, bazz, bazz/bar]
        foo: 
        bar: bazz
        bazz:
          bar: 
          foo: bar
      # ...

  - The **formClass** option defines which form the tester should instanciate.

  - The **withSave** option invokes a sfForm::save call.

  - The **verbose** option enables more detailed information on each test (**recommended**)

  - The **pass** options holds all different datasets which should pass the form validation.

  - The **fail** options holds all different datasets which should fail the form validation. the *_expectedErrors* options defines the errors you expect from the form. the *verbose* will show other errors as well.

**Notice the *foo/bar/bazz/* notation, thats how you define errors in embedded forms.**


Usage
-----

in your tests you are able to use the tester this way:

    /* @var $t lime_test */

    $tester = rsFormTester::create('path/to/config.yml');
    $tester->testData($t);         #for both set
    $tester->testData($t,'valid'); # for valid sets only
    $tester->testData($t,'invalid'); # for invalid sets only
    
    //or the short way
    $tester = rsFormTester::create('path/to/config.yml')->testData($t);

**Dont forget to increase the test count if you defined one!**

If you will you can pass an already instanciated form to the tester (before runnning your tests)

    $form = new FooBarForm($object,array('special_option'=>'foo'),array('special_arg'=>'bar'));
    $tester->setForm($form);
 
    //go on with your tests


*That's it! All the different sets of data will be iterated and compared to what you expect*

TODO
----
  - fix the form field unset option (e.g. for unsetting captchas from fieldschema)
  - remove dependency to lime

