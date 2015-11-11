<?php

class Am_Query_User_Condition_IsSubuser extends Am_Query_Condition
    implements Am_Query_Renderable_Condition
{
    protected $is_a = null;
    protected $options = array();
    
    function __construct() {
        $this->title = ___('Is a Subuser:');
        $this->options = array(
            0 => ___('No, is not a subuser'),
            1 => ___('Yes, is a subuser'),
            //2 => 'Is a reseller',
        );
        
    }
    function _getWhere(Am_Query $q){
        if (!$this->is_a === null) return;
        
        if ($this->is_a == 0) {
            return "u.subusers_parent_id IS NULL OR u.subusers_parent_id = 0";
        } if ($this->is_a == 1) {
            return "u.subusers_parent_id > 0";
        }
    }
    //** for rendering */
    public function setFromRequest(array $input) {
        if (@$input[$this->getId()]['val']!='') {
            $this->is_a = (int)$input[$this->getId()]['val'];
            return true;
        }
    }
    public function getId(){ return '-issubuser'; }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options['Subuser'][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $sel = $group->addSelect('val');
       $sel->loadOptions($this->options);
    }
    public function isEmpty() {
        return $this->is_a === null;
    }
    public function getDescription() {
        return $this->options[$this->is_a];
    }
}