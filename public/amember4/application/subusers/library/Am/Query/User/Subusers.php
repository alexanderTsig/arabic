<?php

class Am_Query_User_Subusers extends Am_Query_User
{
    protected $user_id;
    public function __construct($user_id)
    {
        $this->user_id = (int)$user_id;
        if ($this->user_id <=0)  
            throw Am_Exception_InternalError('user_id is not int');
        parent::__construct();
        //$ds->addField("concat(u.name_f, ' ', 'u.name_l)", "name");
        $this->add(new Am_Query_Condition_Field('subusers_parent_id', '=', $this->user_id));
    }
    public function getRecord($id)
    {
        $rec = parent::getRecord($id);
        if ($rec->get('subusers_parent_id') != $this->user_id)
            throw new Am_Exception_Security(___('Trying to load foreign subuser'));
        return $rec;
    }
}
