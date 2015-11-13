<?php

class Am_Form_Subuser extends Am_Form
{
    protected $record;
    protected $reseller;
    
    public function __construct(User $record,User $reseller)
    {
        $this->record = $record;
        $this->reseller = $reseller;
        parent::__construct();
    }
    public function init()
    {
        parent::init();
        
        $subusers_fields = Am_Di::getInstance()->config->get('subusers_fields', array());
        $subusers_fields = array_combine($subusers_fields, $subusers_fields);
        if (in_array('login', $subusers_fields))
        {
            $loginGroup = $this->addGroup('', array('id' => 'login',))->setLabel(___('Username'));
            $login = $loginGroup->addElement('text', 'login', array('size' => 20));
            $login->addRule('required');
            $loginGroup->addRule('callback2', '-error-', array($this, 'checkUniqLogin'));
            unset($subusers_fields['login']);
        }

        if (in_array('pass', $subusers_fields))
        {
            $gr = $this->addGroup()->setLabel(___('Password'));
            $pass = $gr->addPassword('_pass', array('size' => 20));
            if (!$this->record || !$this->record->isLoaded())
                $pass->addRule('required');
            $label_generate = ___('generate');
            $this->addScript()->setScript(<<<CUT
$(document).ready(function(){
    var pass0 = $("input#_pass-0").after("&nbsp;<a href='javascript:' id='generate-pass'>$label_generate</a>");
    $("a#generate-pass").click(function(){
        if (pass0.attr("type")!="text")
        {
            pass0.replaceWith("<input type='text' name='"+pass0.attr("name")
                    +"' id='"+pass0.attr("id")
                    +"' size='"+pass0.attr("size")
                    +"' />");
            pass0 = $("input#_pass-0");
        }
        var chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz";
        var pass = "";
        var len = 9;
        for(i=0;i<len;i++)
        {
            x = Math.floor(Math.random() * 62);
            pass += chars.charAt(x);
        }
        pass0.val(pass);
    });
});            
CUT
            );    
            unset($subusers_fields['pass']);            
        }
        
        $nameField = $this->addGroup('', array('id' => 'name'), array('label' => ___('Name')));
        $nameField->setSeparator(' ');
        $nameField->addElement('text', 'name_f', array('size'=>20));
        $nameField->addElement('text', 'name_l', array('size'=>20));
        $nameField->addRule('required');
        unset($subusers_fields['name_f']);        
        unset($subusers_fields['name_l']);        
        
        $gr = $this->addGroup()->setLabel(___('E-Mail Address'));
        $em = $gr->addElement('text', 'email', array('size' => 40));
        unset($subusers_fields['email']);
        if (Am_Di::getInstance()->config->get('subusers_cannot_change_email') && $this->record->isLoaded())
        {
            $em->toggleFrozen(true);
        } else {
            $em->addRule('required');
            $gr->addRule('callback2', '-error-', array($this, 'checkUniqEmail'));
        }
        if($this->record->pk())
        {
            $options = Am_Di::getInstance()->subusersSubscriptionTable->getProductOptionsForUser($this->reseller, $this->record->pk());
            reset($options);
            if (count($options) == 1)
            {
                $sel = $this->addHidden('_groups[0]')->setValue(key($options))
                    ->toggleFrozen(true);
            } else {
                $sel = $this->addMagicSelect('_groups')->setLabel(___('Groups'))
                    ->loadOptions($options);
            }
        }
        else
        {
            $options = Am_Di::getInstance()->subusersSubscriptionTable->getProductOptions($this->reseller, true);
            reset($options);
            if (count($options) == 1)
            {
                $sel = $this->addHidden('_groups[0]')->setValue(key($options))
                    ->toggleFrozen(true);
            } else {
                $sel = $this->addMagicSelect('_groups')->setLabel(___('Groups'))
                    ->loadOptions($options);
            }
        }
        
        foreach($subusers_fields as $k=>$v){
            $field = Am_Di::getInstance()->userTable->customFields()->get($k);
            $field->addToQF2($this);
        }
    }
    
    function checkUniqLogin(array $group)
    {
        $login = $group['login'];
        if (!preg_match(Am_Di::getInstance()->userTable->getLoginRegex(), $login))
            return ___('Username contains invalid characters - please use digits and letters');
        if ($this->record->getTable()->checkUniqLogin($login, $this->record ? $this->record->pk() : null) === 0)
            return ___('Username %s is already taken. Please choose another username', Am_Controller::escape($login));
    }
    function checkUniqEmail(array $group)
    {
        $email = $group['email'];
        if (!Am_Validate::email($email))
            return ___('Please enter valid Email');
        if ($this->record->getTable()->checkUniqEmail($email, $this->record ? $this->record->pk() : null) === 0)
            return ___('An account with the same email already exists.');
    }    
    
}
