<?php

class Am_Query_User_Condition_SubuserAssignedTo extends Am_Query_Condition
    implements Am_Query_Renderable_Condition
{
    protected $is_a = null;
    protected $reseller_id, $reseller_login;
    
    protected $options = array();
    
    function __construct() {
        $this->title = ___('Subusers Assigned To (reseller username or id#):');
    }
    function _getWhere(Am_Query $q){
        if ($this->reseller_id > 0) 
            return "u.subusers_parent_id = " . intval($this->reseller_id);
    }
    //** for rendering */
    public function setFromRequest(array $input) {
        if (@$input[$this->getId()]['val']!='') {
            $id = $input[$this->getId()]['val'];
            if (is_integer($id))
                $user = Am_Di::getInstance()->userTable->load($id, false);
            else
                $user = Am_Di::getInstance()->userTable->findFirstByLogin(filterId($id));
            if (!$user)
                return false;
            $this->reseller_id = $user->pk();
            $this->reseller_login = $user->login;
            return true;
        }
    }
    public function getId(){ return '-subuserassignedto'; }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options['Subuser'][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $sel = $group->addText('val');
       
    }
    public function isEmpty() {
        return $this->is_a === null;
    }
    public function getDescription() {
        if ($this->reseller_id)
            return ___('Subusers Assigned To [%s #%d]', $this->reseller_login, $this->reseller_id);
    }
}