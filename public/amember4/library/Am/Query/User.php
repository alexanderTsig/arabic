<?php

/**
 * Renderable users query
 * @package Am_Query
 */
class Am_Query_User extends Am_Query_Renderable
{
    protected $template = 'admin/_user-search.phtml';
    function  __construct() {
        parent::__construct(Am_Di::getInstance()->userTable, 'u');
    }
    function initPossibleConditions(){
        if ($this->possibleConditions) return; // already initialized
        $t = new Am_View;
        $record = $this->table->createRecord();
        $baseFields = $record->getTable()->getFields();
        foreach ($baseFields as $field => $def){
            $title = ucwords(str_replace('_', ' ',$field));
            $f = new Am_Query_User_Condition_Field($field, $title, $def->type, $def->null == 'YES');
            $this->possibleConditions[] = $f;
        }

        foreach (Am_Di::getInstance()->userTable->customFields()->getAll() as $field)
        {
            if((!isset($field->sql) || !$field->sql) && ($field->type!='hidden'))
            {
                $f = new Am_Query_User_Condition_Data($field->name, $field->title, $field->type, (isset($field->options)?$field->options:null), $field->isArray());
                $this->possibleConditions[] = $f;
            }
        }

        $this->possibleConditions[] = new Am_Query_User_Condition_AddedBetween;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionTo(null, null, 'any-completed', ___('Subscribed to any of (including expired):'));
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveNoSubscriptionTo(null, 'none-completed', ___('Having no active subscription to:'));
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionTo(null, User::STATUS_ACTIVE, 'active', ___('Having active subscription to:'));
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionTo(null, User::STATUS_EXPIRED, 'expired', ___('Having expired subscription to:'));
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionDue;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveCancellationDue;
        $this->possibleConditions[] = new Am_Query_User_Condition_HavePaymentBetween;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionDate;
        $this->possibleConditions[] = new Am_Query_User_Condition_SpentAmount();
        $this->possibleConditions[] = new Am_Query_User_Condition_LastSignin();
        $this->possibleConditions[] = new Am_Query_User_Condition_ImportId();
        $this->possibleConditions[] = new Am_Query_User_Condition_UsedPaysys;
        $this->possibleConditions[] = new Am_Query_User_Condition_NotUsedPaysys;
        $this->possibleConditions[] = new Am_Query_User_Condition_Usergroup;
        $this->possibleConditions[] = new Am_Query_User_Condition_NoUsergroup;
        // add payment search options
        $this->possibleConditions[] = new Am_Query_User_Condition_Filter;
        $this->possibleConditions[] = new Am_Query_User_Condition_UserId;

        $event = Am_Di::getInstance()->hook->call(Am_Event::USER_SEARCH_CONDITIONS);
        $this->possibleConditions = array_merge($this->possibleConditions, $event->getReturn());
    }
}

/**
 * Filter users by field value
 */
class Am_Query_User_Condition_Field extends Am_Query_Renderable_Condition_Field
{
    protected $fieldGroupTitle = 'User Base Fields';
    protected static $knownSelects = null;

    public function renderElement(HTML_QuickForm2_Container $form) {
        if (is_null(self::$knownSelects)) {
            self::$knownSelects = array(
                'status' => array(0=>___('Pending'),1=>___('Active'),'2'=>___('Expired')),
                'is_affiliate' => array(0=>___('Not Affiliate'),1=>___('Affiliate'), 2=>___('Only Affiliate, not a member')),
                'is_approved'  => array(0=>___('NO'), 1=>___('YES')),
                'unsubscribed'  => array(0=>___('NO'), 1=>___('YES')),
                'is_locked'  => array(0=>___('NO'), 1=>___('YES')),
                'i_agree'  => array(0=>___('NO'), 1=>___('YES')),
                'email_verified'  => array(0=>___('NO'), 1=>___('YES'))
            );
            foreach (Am_Di::getInstance()->userTable->customFields()->getAll() as $field) {
                if (isset($field->sql) && $field->sql && in_array($field->type, array('select', 'radio'))) {
                    self::$knownSelects[$field->name] = $field->options;
                }
            }
        }
        if (array_key_exists($this->field, self::$knownSelects)){
           $group = $this->addGroup($form);
           $group->addSelect('val')->loadOptions(self::$knownSelects[$this->field]);
        } else
            return parent::renderElement($form);
    }
}

class Am_Query_User_Condition_Data extends Am_Query_Condition_Data
{


    protected
        $title;
    protected
        $fieldType;
    protected
        $isNull;
    protected
        $options;
    protected
        $fieldGroupTitle = "Common (data) fields";
    static
        $renderOperations = array('=' => '=', '<>' => '<>', 'LIKE' => 'LIKE', 'NOT LIKE' => 'NOT LIKE', 'IN' => 'IN');
    static protected
        $validOperations = array('<','<>','=','>','<=','>=','<=>','IS NULL', 'IS NOT NULL', 'LIKE', 'NOT LIKE', 'IN', 'REGEXP');

    protected $alias;

    function __construct($field, $title, $type, $options = null, $checkBlob = false)
    {
        $this->field = $field;
        $this->title = $title;
        $this->checkBlob = $checkBlob;
        $this->options = $options;
        $this->fieldType = $type;

    }

    protected function init($op, $value){
        if (!in_array($op, self::$validOperations))
            throw new Am_Exception_InternalError("Invalid operator provided: " . htmlentities($op) . " in ".__METHOD__);
        $this->op = $op;
        $this->value = $value;
    }

    public
        function setFromRequest(array $input)
    {
        $id = $this->getId();
        if (isset($input[$id]) && ((array_key_exists($id, $input) && is_array($input[$id]['val']) && array_filter($input[$id]['val'])) || (!is_array($input[$id]['val']) && array_filter($input[$id], 'strlen'))))
        {
            if (is_array($input[$id]['val']))
            {
                $input[$id]['op'] = 'REGEXP';
            }
            else
            {
                if (empty($input[$id]['op']))
                    $input[$id]['op'] = '=';
                if (($input[$id]['op'] == 'LIKE') && (strpos($input[$id]['val'], '%') === false))
                {
                    $input[$id]['val'] = '%' . $input[$id]['val'] . '%';
                }

            }
            $this->init(@$input[$id]['op'], @$input[$id]['val']);
            return true;
        }
        else
        {
            $this->empty = true;
            $this->op = null;
        }
    }

    /**
     * @return HTML_QuickForm2_Container
     */
    public
        function addGroup(HTML_QuickForm2_Container $form)
    {
        $form->options[$this->fieldGroupTitle][$this->getId()] = $this->title;
        return $form->addGroup($this->getId())
                ->setLabel($this->title)
                ->setAttribute('id', $this->getId())
                ->setAttribute('class', 'searchField empty');
    }

    public
        function getId()
    {
        return 'data-field-' . $this->field;
    }

    public
        function renderElement(HTML_QuickForm2_Container $form)
    {
        $group = $this->addGroup($form);
        switch ($this->fieldType)
        {
            case 'checkbox' :
            case 'multi_select' :

                $group->addMagicSelect('val', null, array('options' => $this->options));


                break;
            default:

                $group->addSelect('op')->loadOptions(self::$renderOperations);
                $group->addText('val');
        }
    }



    public
        function isEmpty()
    {
        return $this->op === null;
    }

    public
        function getDescription()
    {
        if(is_array($this->value))
        {
            $op = ___('has all values selected');
            $val = $this->value;
            if(!is_null($this->options)){
                array_walk($val, array($this, 'getValue'));
            }

            $val = htmlentities (join(', ', array_filter ($val)));
        }
        else
        {
            $val = htmlentities ($this->value);
            $op = $this->op;
        }
        return $this->title . ' ' . $op . ' [' . $val . ']';
    }

    function getValue(&$value)
    {
        $value = $this->options[$value];
    }

    function _getWhere(Am_Query $q) {
        if(is_array($this->value))
        {
            $parts = array();
            foreach($this->value as $v)
            {
                $parts[] = sprintf(
                    "(%s.`%s`  REGEXP '.*;s:[0-9]+:\"%s\".*')",
                    $this->selfAlias(),
                    ($this->checkBlob ? 'blob' : 'value'),
                    str_replace('\'', '', $q->escape($v)));
            }
            return join(' AND ',$parts);

        }else
            return parent::_getWhere ($q);
    }

    private function getAlias(Am_Query $q) {
        return (is_null($this->tableAlias) ? $q->getAlias() : $this->tableAlias);
    }
    function selfAlias()
    {
        if(is_null($this->alias)){
            static $i;
            $i++;
            $this->alias = parent::selfAlias().$i;
        }
        return $this->alias;
    }

}

class Am_Query_User_Condition_AddedBetween extends Am_Query_Condition implements Am_Query_Renderable_Condition {

    protected $start, $stop, $empty;

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->start = null;
        $this->stop = true;
        if (!empty($input[$id]['start']) && !empty($input[$id]['stop']))
        {
            $this->start = $input[$id]['start'];
            $this->stop = $input[$id]['stop'];
            $this->empty = false;
            return true;
        }
        return false;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Added between dates:');

       $form->options[___('User Base Fields')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addDate('start');
       $group->addDate('stop');
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function getId(){ return 'added-between'; }

    public function getDescription()
    {
        return htmlentities(___('added between %s and %s',
            amDate($this->start), amDate($this->stop)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $start = $db->escape($this->start . ' 00:00:00');
        $stop = $db->escape($this->stop . ' 23:59:59');
        if (!$start || !$stop) return null;

        return "($a.added BETWEEN $start AND $stop)";
    }
}

class Am_Query_User_Condition_SpentAmount
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $val = null;

    public function __construct()
    {
        $this->title = ___('Spent Amount');
    }
    public function getId() {
        return 'spent-amount';
    }
    public function isEmpty() {
        return !$this->val;
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addSelect('op')->loadOptions(array('=' => '=', '<>'=>'<>', '>'=>'>', '<'=>'<', '>='=>'>=', '<='=>'<=', ));
        $group->addText('val', array('size'=>6));
    }

    public function setFromRequest(array $input)
    {
        $this->op = @$input[$this->getId()]['op'];
        $this->val = @$input[$this->getId()]['val'];
        if ($this->val)
            return true;
    }
    public function getJoin(Am_Query $q)
    {
        $a = $q->getAlias();
        return "LEFT JOIN (SELECT user_id, SUM(amount) AS spent FROM
            (SELECT user_id, amount FROM ?_invoice_payment
            UNION ALL
            SELECT user_id, -1 * amount FROM ?_invoice_refund) tr GROUP BY tr.user_id) sp ON $a.user_id = sp.user_id";
    }

    function _getWhere(Am_Query $db)
    {
        return sprintf("(spent %s %s)", $this->op, $db->escape($this->val));
    }

    public function getDescription(){
        return ___('spent %s %s', $this->op, Am_Currency::render($this->val));
    }
}

class Am_Query_User_Condition_LastSignin
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $val = null;

    public function __construct()
    {
        $this->title = ___('Last Signin');
    }
    public function getId() {
        return 'never-signin';
    }
    public function isEmpty() {
        return !$this->op;
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addSelect('op')->loadOptions(array('never' => ___('Never'), 'between'=>___('Between Dates')));
        $group->addDate('from', array('palceholder' => ___('From')));
        $group->addDate('to', array('palceholder' => ___('To')));
    }

    public function setFromRequest(array $input)
    {
        $this->op = @$input[$this->getId()]['op'];
        $this->from = @$input[$this->getId()]['from'];
        $this->to = @$input[$this->getId()]['to'];
        if ($this->op)
            return true;
    }

    function _getWhere(Am_Query $db)
    {
        $where = '';
        switch ($this->op) {
            case 'never' :
                $where = '(last_login IS NULL)';
                break;
            case 'between' :
                $from = $this->from ? date('Y-m-d 00:00:00', amstrtotime($this->from)) : sqlTime('- 10 years');

                $to = $this->to ? date('Y-m-d 23:59:59', amstrtotime($this->to)) : sqlTime('tommorow');
                $where = sprintf('(last_login BETWEEN %s AND %s)',
                    $db->escape($from), $db->escape($to));
                break;
            default:
                new Am_Exception_InputError("Unknown operation type [$this->op]");
        }
        return $where;
    }

    public function getDescription(){
        return $this->op == 'never' ?
            ___('never signin') :
            ___('last signin between %s and %s', $this->from ? amDate($this->from) : '-',
                $this->to ? amDate($this->to) : '-');
    }
}

class Am_Query_User_Condition_UsedPaysys
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $paysys_id = null;

    public function __construct()
    {
        $this->title = ___('Has Used Payment System');
    }
    public function getId() {
        return 'paysys';
    }
    public function isEmpty() {
        return !$this->paysys_id;
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $options = array();
        foreach (Am_Di::getInstance()->plugins_payment->loadEnabled()->getAllEnabled() as $pl) {
            if ($pl->getId() != 'free') $options[$pl->getId()] = $pl->getTitle();
        }
        $group->addSelect('paysys_id', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions($options);
    }

    public function setFromRequest(array $input)
    {
        $this->paysys_id = @$input[$this->getId()]['paysys_id'];
        if ($this->paysys_id)
            return true;
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map(array(Am_Di::getInstance()->db, 'escape'), $this->paysys_id)));
        if (!$ids) return null;
        return "EXISTS
            (SELECT * FROM ?_invoice_payment ups
            WHERE ups.user_id=$a.user_id AND ups.paysys_id IN ($ids))";
    }

    public function getDescription(){
        return ___('has used payment system %s', implode(', ', $this->paysys_id));
    }
}

class Am_Query_User_Condition_NotUsedPaysys
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $paysys_id = null;

    public function __construct()
    {
        $this->title = ___('Has Not Used Payment System');
    }
    public function getId() {
        return 'no-paysys';
    }
    public function isEmpty() {
        return !$this->paysys_id;
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $options = array();
        foreach (Am_Di::getInstance()->plugins_payment->getAllEnabled() as $pl) {
            if ($pl->getId() != 'free') $options[$pl->getId()] = $pl->getTitle();
        }
        $group->addSelect('paysys_id', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions($options);
    }

    public function setFromRequest(array $input)
    {
        $this->paysys_id = @$input[$this->getId()]['paysys_id'];
        if ($this->paysys_id)
            return true;
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map(array(Am_Di::getInstance()->db, 'escape'), $this->paysys_id)));
        if (!$ids) return null;
        return "NOT EXISTS
            (SELECT * FROM ?_invoice_payment ups
            WHERE ups.user_id=$a.user_id AND ups.paysys_id IN ($ids))";
    }

    public function getDescription(){
        return ___('has not used payment system %s', implode(', ', $this->paysys_id));
    }
}


class Am_Query_Condition_Subscription extends Am_Query_Condition
{
    function getProductOptions()
    {
        $cOptions = array();
        foreach (Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions() as $id => $title) {
            $cOptions['c' . $id] = $title;
        }
        if ($cOptions) {
            $options = array(
                ___('Products') => Am_Di::getInstance()->productTable->getOptions(),
                ___('Product Categories') => $cOptions
            );
        } else {
            $options = Am_Di::getInstance()->productTable->getOptions();
        }

        return $options;
    }

    function extractProductIds($p)
    {
        $ret = array();
        $categoryProduct = Am_Di::getInstance()->productCategoryTable->getCategoryProducts();
        foreach($p as $id) {
            if (preg_match('/c([0-9]*)/i', $id, $m)) {
                $ret = array_merge($ret, $categoryProduct[$m[1]]);
            } else {
                $ret[] = $id;
            }
        }
        return array_map('intval', $ret);
    }
}

/**
 * Filter users having a subscription
 */
class Am_Query_User_Condition_HaveSubscriptionTo extends Am_Query_Condition_Subscription
implements Am_Query_Renderable_Condition {
    protected $product_ids;
    protected $currentStatus = null;
    protected $alias = null;
    protected $title = null;
    protected $id;
    protected $empty = true;
    function __construct(array $product_ids=null, $currentStatus=null, $id=null, $title=null) {
        $this->product_ids = $product_ids ? $product_ids : array();
        if ($currentStatus !== null)
            $this->currentStatus = (int)$currentStatus;
        $this->id = $id;
        $this->title = $title;
    }
    function setAlias($alias = null)
    {
        $this->alias = $alias===null ? 'p'.substr(uniqid(), -4, 4) : $alias;
    }
    function getAlias(){
        if (!$this->alias)
            $this->setAlias();
        return $this->alias;
    }
    function getJoin(Am_Query $q){
        $alias = $this->getAlias();
        $ids = array_map('intval', $this->product_ids);
        $productsCond = $ids ? ' AND '.$alias.'.product_id IN (' . join(',',$ids) .')' : '';
        $statusCond = ($this->currentStatus !== null) ? " AND $alias.status=" . (int)$this->currentStatus : null;
        return "INNER JOIN ?_user_status $alias ON u.user_id=$alias.user_id{$productsCond}{$statusCond}";
    }
    /// for rendering
    public function setFromRequest(array $input) {
        $id = $this->getId();
        $this->product_ids = null;
        $this->empty = true;
        if (!empty($input[$id]['product_ids']))
        {
            $this->product_ids = $this->extractProductIds($input[$id]['product_ids']);
            $this->empty = false;
            return true;
        }
    }
    public function getId(){ return '-payments-'.$this->id; }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('User Subscriptions Status')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions($this->getProductOptions());
    }
    public function isEmpty() {
        return $this->empty;
    }
    public function getDescription(){
        if ($this->currentStatus!==null) {
            if ($this->currentStatus == User::STATUS_ACTIVE) $completedCond = 'active';
            elseif ($this->currentStatus == User::STATUS_EXPIRED) $completedCond = 'expired';
            else $completedCond = 'pending';
        } else
            $completedCond = "any";
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : 'any product';
        return htmlentities("have {$completedCond} subscription to $ids");
    }
}

class Am_Query_User_Condition_HaveNoSubscriptionTo extends Am_Query_Condition_Subscription
implements Am_Query_Renderable_Condition {
    protected $product_ids;
    protected $currentStatus = null;
    protected $alias = null;
    protected $title = null;
    protected $id;
    protected $empty = true;
    function __construct(array $product_ids=null, $id=null, $title=null)
    {
        $this->product_ids = $product_ids ? $product_ids : array();
        $this->id = $id;
        $this->title = $title;
    }
    public function getId(){ return '-no-payments-'.$this->id; }
    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = join(',', array_filter(array_map('intval', $this->product_ids)));
        if (!$ids) return null;
        return "NOT EXISTS
            (SELECT * FROM ?_user_status ncmss
            WHERE ncmss.user_id=$a.user_id AND ncmss.product_id IN ($ids) AND ncmss.status = 1)";
    }
    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : 'any product';
        return htmlentities(___('have no active subscriptions to %s', $ids));
    }
    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('User Subscriptions Status')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions($this->getProductOptions());
    }
    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = null;
        $this->empty = true;
        if (!empty($input[$id]['product_ids']))
        {
            $this->product_ids = $this->extractProductIds($input[$id]['product_ids']);
            $this->empty = false;
            return true;
        }
    }
    public function isEmpty()
    {
        return $this->empty;
    }
}

class Am_Query_User_Condition_HaveSubscriptionDue extends Am_Query_Condition_Subscription
implements Am_Query_Renderable_Condition {
    protected $product_ids = array();
    protected $date_start;
    protected $date_end;
    protected $empty = true;

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = $this->date_start = $this->date_end = null;
        $this->empty = true;

        $this->product_ids = isset($input[$id]['product_ids']) ? $this->extractProductIds($input[$id]['product_ids']) : array();
        $this->date_start = @$input[$id]['date_start'];
        $this->date_end = @$input[$id]['date_end'];

        if ($this->date_start && $this->date_end)
            $this->empty = false;

        return !$this->empty;
    }
    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Has subscription that expire between dates:');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions($this->getProductOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }
    public function isEmpty()
    {
        return $this->empty;
    }

    public function getId(){ return 'subscription-due'; }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : 'any product';
        return htmlentities(___("have subscription for %s
            that expire between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hsdac.id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT * FROM ?_access_cache hsdac
            WHERE hsdac.user_id=$a.user_id AND $product_cond AND hsdac.fn = 'product_id' AND hsdac.expire_date BETWEEN $date_start AND $date_end)";
    }
}
class Am_Query_User_Condition_HaveSubscriptionDate extends Am_Query_User_Condition_HaveSubscriptionDue
implements Am_Query_Renderable_Condition {
    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = $this->date_start = $this->date_end = null;
        $this->empty = true;

        $this->product_ids = isset($input[$id]['product_ids']) ? $this->extractProductIds($input[$id]['product_ids']) : array();
        $this->date_start = @$input[$id]['date_start'];

        if ($this->date_start)
            $this->empty = false;

        return !$this->empty;
    }
    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Has subscription on date:');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions($this->getProductOptions());
       $group->addDate('date_start');
    }

    public function getId(){ return 'subscription-date'; }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : ___('any product');
        return htmlentities(___("have subscription for %s
            for date %s ", $ids,
            amDate($this->date_start)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hsdt.product_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        if (!$date_start) return null;
        return "EXISTS (SELECT * FROM ?_access hsdt
            WHERE hsdt.user_id=$a.user_id AND $product_cond AND $date_start BETWEEN hsdt.begin_date AND hsdt.expire_date )";
    }
}

class Am_Query_User_Condition_HavePaymentBetween extends Am_Query_User_Condition_HaveSubscriptionDue
implements Am_Query_Renderable_Condition {
    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Has payment made between dates:');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions($this->getProductOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }

    public function getId(){ return 'payment-between'; }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : ___('any product');
        return htmlentities(___("have payment for %s
            that made between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hspbetit.item_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT hspbet.* FROM ?_invoice_payment hspbet , ?_invoice hspbeti, ?_invoice_item hspbetit
            WHERE hspbet.user_id=$a.user_id
                AND hspbet.invoice_id = hspbeti.invoice_id
                AND hspbetit.invoice_id=hspbet.invoice_id
                AND $product_cond AND  hspbet.dattm BETWEEN $date_start AND $date_end)";
    }
}
class Am_Query_User_Condition_HaveCancellationDue extends Am_Query_User_Condition_HaveSubscriptionDue
implements Am_Query_Renderable_Condition {
    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Has invoice canceled between dates:');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addSelect('product_ids', array('multiple'=>'multiple', 'size' => 5))
           ->loadOptions($this->getProductOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }

    public function getId(){ return 'canceled-between'; }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . join(',', $this->product_ids) : ___('any product');
        return htmlentities(___("have invoice for %s
            that canceled between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hspbetit.item_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT hspbet.* FROM ?_invoice_payment hspbet , ?_invoice hspbeti, ?_invoice_item hspbetit
            WHERE hspbet.user_id=$a.user_id
                AND hspbet.invoice_id = hspbeti.invoice_id
                AND hspbetit.invoice_id=hspbet.invoice_id
                AND $product_cond AND  hspbeti.tm_cancelled BETWEEN $date_start AND $date_end)";
    }
}

class Am_Query_User_Condition_Filter
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $filter;
    public function __construct()
    {
        $this->title = ___('Quick Filter');
    }
    public function getId() {
        return 'filter';
    }
    public function isEmpty() {
        return $this->filter === null;
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Quick Filter')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addText('val');
    }
    public function setFromRequest(array $input) {
        if (is_string($input)) {
            $this->filter = $input;
            return true;
        } elseif (@$input['filter']['val']!='') {
            $this->filter = $input['filter']['val'];
            return true;
        }
    }
    public function _getWhere(Am_Query $q){
        $a = $q->getAlias();
        $f = '%'.$this->filter.'%';
        return $q->escapeWithPlaceholders("($a.login LIKE ?) OR ($a.email LIKE ?) OR ($a.name_f LIKE ?) OR ($a.name_l LIKE ?)
            OR CONCAT($a.name_f, ' ', $a.name_l) LIKE ?
            OR ($a.remote_addr LIKE ?)
            OR ($a.user_id IN (SELECT user_id FROM ?_invoice WHERE public_id=? OR CAST(invoice_id as char(11))=?))
            OR ($a.user_id IN (SELECT user_id FROM ?_invoice_payment WHERE receipt_id=?))
            ",
                $f, $f, $f, $f, $f, $f, $this->filter, $this->filter, $this->filter);
    ;}
    public function getDescription(){
        $f = htmlentities($this->filter);
        return ___('username, e-mail or name contains string [%s]', $f);
    }
}

class Am_Query_User_Condition_UserId
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title = "UserId#";
    protected $ids = null;

    public function getId() {
        return 'member_id_filter';
    }
    public function isEmpty() {
        return !empty($this->ids);
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       //$form->options['Quick Filter'][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addText('val');
    }
    public function setIds($ids){
        if (!is_array($ids)) $ids = split(',', $ids);
        $this->ids = array_filter(array_map('intval', $ids));
    }
    public function setFromRequest(array $input) {
        if (@$input[$this->getId()]['val']!='') {
            $this->setIds($input[$this->getId()]['val']);
            return true;
        }
    }
    public function _getWhere(Am_Query $q){
        if (!$this->ids) return null;
        $a = $q->getAlias();
        $ids = join(',', $this->ids);
        return "$a.user_id IN ($ids)";
    ;}
    public function getDescription(){
        $ids = join(',', $this->ids);
        return "user_id IN ($ids)";
    }
}

class Am_Query_User_Condition_ImportId
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $id = '';

    public function __construct()
    {
        $this->title = ___('Added During Import');
    }
    public function getId() {
        return 'import';
    }
    public function isEmpty() {
        return !$this->id;
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addText('id');
    }

    public function setFromRequest(array $input)
    {
        $this->id = @$input[$this->getId()]['id'];
        if ($this->id)
            return true;
    }
    public function getJoin(Am_Query $q)
    {
        $a = $q->getAlias();
        $id = $this->id;
        $ids = $ids ? implode(',', $ids) : '-1';
        return "LEFT JOIN ?_data itd ON $a.user_id = itd.id AND itd.`table` = 'user' AND itd.`key` = 'import-id'";
    }
    public function _getWhere(Am_Query $q){
        if (!$this->id) return null;
        $id = $q->escape($this->id);
        return "itd.value = $id";
    ;}
    public function getDescription(){
        return "import-id = $this->id";
    }
}

class Am_Query_User_Condition_Usergroup
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $ids = array();

    public function __construct()
    {
        $this->title = ___('Assigned to usergroup');
    }
    public function getId() {
        return 'user-group';
    }
    public function isEmpty() {
        return !$this->ids;
    }
    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('User Groups')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addSelect('ids', 'multiple=multiple size=5')->loadOptions($this->getOptions());
    }
    protected function getOptions()
    {
        return Am_Di::getInstance()->userGroupTable->getSelectOptions();
    }
    public function setFromRequest(array $input)
    {
        $this->ids = @$input[$this->getId()]['ids'];
        $this->ids = array_filter(array_map('intval', (array)$this->ids));
        if ($this->ids)
            return true;
    }
    public function getJoin(Am_Query $q)
    {
        $a = $q->getAlias();
        $ids = array_filter(array_map('intval', $this->ids));
        $ids = $ids ? implode(',', $ids) : '-1';
        return "INNER JOIN ?_user_user_group uug ON $a.user_id = uug.user_id AND uug.user_group_id IN ($ids)";
    }
    public function getDescription(){
        $g = $this->getOptions();
        $g = array_intersect_key($g, array_combine($this->ids, $this->ids));
        $g = array_map(array('Am_Controller', 'escape'), $g);
        $g = implode(',', $g);
        return ___('assigned to usergroups [%s]', $g);
    }
}

class Am_Query_User_Condition_NoUsergroup
    extends Am_Query_User_Condition_Usergroup
{
    public function __construct()
    {
        $this->title = ___('Not assigned to usergroup');
    }
    public function getId() {
        return 'no-user-group';
    }
    public function getJoin(Am_Query $q)
    {
        return;
    }
    public function _getWhere(Am_Query $q)
    {
        $a = $q->getAlias();
        $ids = array_filter(array_map('intval', $this->ids));
        if (!$ids) return;
        $ids = implode(',', $ids);
        return "NOT EXISTS (SELECT * FROM ?_user_user_group uug WHERE $a.user_id = uug.user_id AND uug.user_group_id IN ($ids))";
    }

}