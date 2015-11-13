<?php

class Am_Grid_Action_ImportCSV extends Am_Grid_Action_Abstract {
    protected $type = self::NORECORD;
    const UPLOAD_PREFIX = 'subuser-import';
    protected $reseller;
    function __construct($id = null, $title = null, User $reseller)
    {
        parent::__construct($id, $title);
        $this->reseller = $reseller;
    }
    function run()
    {
        $errors = array();
        $importFields = array(
            'email'=>'email',
            'name_f' => 'name_f',
            'name_l' => 'name_l'
        );

        $subusers_fields = $this->getDi()->config->get('subusers_fields', array());
        if (in_array('login', $subusers_fields)) {
            $importFields['login'] = 'username';
        }
        if (in_array('pass', $subusers_fields)) {
            $importFields['pass'] = 'password';
        }

        $form = new Am_Form();
        $form->setAttribute('target', '_top');
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->addStatic()
            ->setContent('<div>' . ___('File should contain CSV list of user records for import in the following format:<br />
<strong>%s</strong>', implode(',', $importFields)) . '</div>');
        $form->addFile('file[]', null, array('prefix'=>self::UPLOAD_PREFIX))
            ->setLabel(___('File'))
            ->addRule('required', ___('This field is a requried field'));

        $options = $this->getDi()->subusersSubscriptionTable->getProductOptions($this->reseller, true);
        reset($options);
        if (count($options) == 1)
        {
            $form->addHidden('groups[0]')->setValue(key($options))
                ->toggleFrozen(true);
        } else {
            $form->addMagicSelect('groups')->setLabel(___('Groups'))
                ->loadOptions($options);
        }

        $form->addSaveButton(___('Do Import'));

        $this->initForm($form);

        if ($form->isSubmitted()) {
            $value = $form->getValue();

            $upload = new Am_Upload($this->getDi());
            $upload->setPrefix(self::UPLOAD_PREFIX)->setTemp(3600);
            $upload->processSubmit('file');
            list($file) = $upload->getUploads();

            if (!$file) throw new Am_Exception_InputError(___('CSV File was not specified'));

            $pn = fopen($file->getFullPath(), 'r');
            while($res = fgetcsv($pn)) {
                if (count($res) == count($importFields)) {
                    foreach($importFields as $fieldName => $v)
                        ${$fieldName} = trim(array_shift($res));

                    $user = Am_Di::getInstance()->userRecord;
                    if ($error = $this->checkUniqEmail($email)) {
                        $errors[] = $error;
                        continue;
                    }

                    if (isset($login) && ($error = $this->checkUniqLogin($login))) {
                        $errors[] = $error;
                        continue;
                    }

                    $user->email =  $email;
                    $user->name_f =  $name_f;
                    $user->name_l =  $name_l;

                    isset($pass) ?
                        $user->setPass($pass) :
                        $user->generatePassword();

                    isset($login) ?
                        $user->login = $login :
                        $user->generateLogin();

                    $user->data()->set('signup_email_sent', 1);
                    $user->set('subusers_parent_id', $this->reseller->pk());
                    $user->is_approved = 1;

                    $user->save();

                    if($et = Am_Mail_Template::load('subusers.registration_mail', $user->lang))
                    {
                        $et->setUser($user);
                        $et->setPassword($user->getPlaintextPass());
                        $et->setReseller($this->reseller);

                        //backward compatibality (reseller_name_f, reseller_name_l, reseller_email)
                        $et->setReseller_name_f($this->reseller->name_f);
                        $et->setReseller_name_l($this->reseller->name_l);
                        $et->setReseller_email($this->reseller->email);

                        if(!empty($value['groups']))
                        {
                            $userTitle = array();
                            foreach ($this->getDi()->productTable->loadIds($value['groups']) as $product)
                                $userTitle[] = $product->title;
                            $et->setUser_product(join(', ', $userTitle));

                            $resellerTitle = array();
                            $conditions = array('subusers_product_id' => $value['groups']);
                            foreach ($this->getDi()->productTable->findBy($conditions) as $product)
                                $resellerTitle[] = $product->title;
                            $et->setReseller_product(join(', ', $resellerTitle));
                        }
                        $et->send($user);
                    }

                    $this->getDi()->subusersSubscriptionTable->setForUser($user->pk(), $value['groups']);
                }
            }
            fclose($pn);
            $this->getDi()->modules->get('subusers')->checkAndUpdate($this->reseller);

            if ($errors) {
                $out = '<ul class="errors">';
                foreach ($errors as $error) {
                    $out .= sprintf('<li>%s</li>', $error);
                }
                $out .= "</ul>";

                echo $out . $this->renderBackUrl() . '<br /><br />';
            } else {
                $this->grid->redirectBack();
            }
        } else {
            echo $this->renderTitle();
            echo $form;
        }
    }

    function getDi()
    {
        return Am_Di::getInstance();
    }

    function checkUniqLogin($login)
    {
        if (!preg_match($this->getDi()->userTable->getLoginRegex(), $login))
            return ___('Username [%s] contains invalid characters - please use digits and letters', $login);
        if ($this->getDi()->userTable->checkUniqLogin($login) === 0)
            return ___('Username [%s] is already taken. Please choose another username', Am_Controller::escape($login));
    }
    function checkUniqEmail($email)
    {
        if (!Am_Validate::email($email))
            return ___('Email [%s] is not valid', Am_Controller::escape($email));
        if ($this->getDi()->userTable->checkUniqEmail($email) === 0)
            return ___('An account with the same email [%s] is already exists.', Am_Controller::escape($email));
    }

    protected function initForm($form) {
        $form->setDataSources(array(
            $this->grid->getCompleteRequest(),
        ));


        $vars = array();
        foreach ($this->grid->getVariablesList() as $k) {
            $vars[$this->grid->getId() . '_' . $k] = $this->grid->getRequest()->get($k, "");
        }
        $form->addHtml('hidden')
            ->setHtml(Am_Controller::renderArrayAsInputHiddens($vars));

    }
}

class Am_Grid_Editable_Subusers extends Am_Grid_Editable
{
    protected $reseller;
    function __construct($id, $title, Am_Grid_DataSource_Interface_Editable $ds, Am_Request $request, Am_View $view, Am_Di $di = null, User $reseller)
    {
        parent::__construct($id, $title, $ds, $request, $view, $di);
        $this->reseller = $reseller;
    }

    /**
     * @return Am_Grid_Editable_Subusers
     */
    static function factory(User $reseller, Zend_Controller_Request_Http $request, Am_View $view, Am_Di $di)
    {
        $ds = new Am_Query_User_Subusers($reseller->pk());
        $ds->leftJoin('?_subusers_subscription', 'sgu');
        $ds->addField('GROUP_CONCAT(sgu.product_id)', 'groups');
        $ds->addField('GROUP_CONCAT(sgu.status)', 'groups_status');
        
        $grid = new self('_subusers', ___("Subusers"), $ds, $request, $view, $di, $reseller);
        $grid->setEventId('gridSubusersUser');

        $grid->addField('login', ___('Username'));
        $grid->addField('name_f', ___('First Name'));
        $grid->addField('name_l', ___('Last Name'));
        $grid->addField('email', ___('E-Mail Address'));
        $grid->addField('groups', ___('Groups'))->setRenderFunction(array($grid, 'renderGroups'));
        
        $grid->setForm(array($grid, '_createForm'));
        
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_INSERT, array($grid, 'beforeInsert'));
        $grid->addCallback(Am_Grid_Editable::CB_AFTER_INSERT, array($grid, 'afterInsert'));
        $grid->addCallback(Am_Grid_Editable::CB_AFTER_SAVE, array($grid, 'afterSave'));
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_SAVE, array($grid, 'beforeSave'));
        $grid->addCallback(Am_Grid_Editable::CB_AFTER_DELETE, array($grid, 'afterDelete'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, array($grid, '_valuesToForm'));
        
        if($di->config->get('subusers_can_login'))
            $grid->actionAdd(new Am_Grid_Action_Url('login', ___('Login as User'), '__ROOT__/subusers/index/login-as?id=__ID__'))->setTarget('_top');
        
        $grid->actionGet('edit')->setTarget('_top');
        $grid->actionGet('delete')->setTarget('_top');
        $grid->actionGet('insert')->setTarget('_top');
        
        $subusers_count = $reseller->data()->get('subusers_count');
        
        $canAdd = 0;
        foreach ($subusers_count as $product_id => $v) 
            if ($v['avail_count']>($v['pending_count']+$v['active_count'])) $canAdd++;
        if (!$canAdd)
            $grid->actionDelete('insert');

        if ($canAdd)
            $grid->actionAdd(new Am_Grid_Action_ImportCSV('import', ___('Import from CSV'), $reseller));
        
        if ($di->config->get('subusers_cannot_delete')==1)
            $grid->actionDelete('delete');
        if ($di->config->get('subusers_cannot_edit'))
            $grid->actionDelete('edit');
        return $grid;
    }
    static function factoryAdmin(User $reseller, Zend_Controller_Request_Http $request, Am_View $view, Am_Di $di)
    {
        $grid = self::factory($reseller, $request, $view, $di);
        $grid->actionDelete('insert');
        $grid->actionDelete('edit');
        $grid->actionDelete('delete');

        $url = $view->userUrl('{user_id}');
        
        $grid->getField('login')->addDecorator(new Am_Grid_Field_Decorator_Link(
            $url, '_blank'));
        $grid->setEventId('gridSubusersAdmin');

        return $grid;
    }
    function renderGroups(User $record)
    {
        static $groupTitles;
        if (!$groupTitles)
            $groupTitles = $this->getDi()->subusersSubscriptionTable->getProductOptions($this->reseller);
        $gr = array();
        $record->groups_status = explode(',', $record->groups_status);
        foreach (explode(',', $record->groups) as $k => $id)
        {
            if (empty($id)) continue;
            $status = $record->groups_status[$k] ? ___('Active') : ___('Pending');
            $gr[] = @$groupTitles[$id] . ' (' . $status . ')';
        }
        return "<td>".
                implode(", ", $gr) . 
                "</td>";
    }
    
    function _createForm(Am_Grid_Editable $grid)
    {
        return new Am_Form_Subuser($grid->getRecord(),$this->reseller);
    }
    function beforeInsert(array & $values, User $record, Am_Grid_Editable $grid)
    {
        if($values['_pass'])
            $record->setPass($values['_pass']);
        
        if ($record->get('login') == '')
            $record->generateLogin();
        if ($record->get('pass') == '')
            $record->generatePassword();
        $record->data()->set('signup_email_sent', 1);
        $record->set('subusers_parent_id', $this->reseller->pk());
        $record->is_approved = 1;
    }
    function afterInsert(array & $values, User $record, Am_Grid_Editable $grid)
    {
        if($et = Am_Mail_Template::load('subusers.registration_mail', $record->lang))
        {
            $et->setUser($record);
            $et->setPassword($record->getPlaintextPass());
            $reseller = $this->getDi()->userTable->load($record->subusers_parent_id);
            $et->setReseller($reseller);

            //backward compatibality (reseller_name_f, reseller_name_l, reseller_email)
            $et->setReseller_name_f($reseller->name_f);
            $et->setReseller_name_l($reseller->name_l);
            $et->setReseller_email($reseller->email);

            if(!empty($values['_groups']))
            {
                $userTitle = array();
                foreach ($this->getDi()->productTable->loadIds($values['_groups']) as $product)
                    $userTitle[] = $product->title;
                $et->setUser_product(join(', ', $userTitle));

                $resellerTitle = array();
                $conditions = array('subusers_product_id' => $values['_groups']);
                foreach ($this->getDi()->productTable->findBy($conditions) as $product)
                    $resellerTitle[] = $product->title;
                $et->setReseller_product(join(', ', $resellerTitle));
            }
            $et->send($record);
        }
        
    }
    function beforeSave(array & $values, User $record)
    {
        if (in_array('pass',Am_Di::getInstance()->config->get('subusers_fields', array())) && $values['_pass'])
            $record->setPass($values['_pass']);
    }
    function afterSave(array &$values, User $record)
    {
        $added = $this->getDi()->subusersSubscriptionTable->setForUser($record->pk(), $values['_groups']);
        //if ($added) // add related access records if possible
        $this->getDi()->modules->get('subusers')->checkAndUpdate($this->reseller);
    }
    function afterDelete()
    {
        $this->getDi()->modules->get('subusers')->checkAndUpdate($this->reseller);
    }
    function _valuesToForm(array &$values, User $record)
    {
        if ($record->isLoaded())
            $values['_groups'] = $this->getDi()->subusersSubscriptionTable->getForUser($record->pk());
        else
            $values['_groups'] = array();
    }
}

