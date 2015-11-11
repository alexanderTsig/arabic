<?php

/**
 * @property int subusers_group_user_id
 * @property int product_id
 * @property int user_id
 * @property int status  
 */
class SubusersSubscription extends Am_Record
{
    var $_new = false;
    function enable()
    {
        $a = $this->getDi()->accessTable->createRecord();
        /* @var $a Access */
        $a->user_id = $this->user_id;
        $a->begin_date = $this->getDi()->sqlDate;
        $a->expire_date = Am_Period::MAX_SQL_DATE;
        $a->product_id = $this->product_id;
        $a->transaction_id = 'subusers.' . $this->pk();
        try {   
            $a->insert();
            if(!$this->_new)
                $this->getDi()->emailTemplateTable->sendZeroAutoresponders($this->getDi()->userTable->load($this->user_id), $a);
        } catch (Am_Exception_Db_NotUnique $e) {
            // ignore
        }
        $this->updateQuick('status', 1);
    }
    function disable()
    {
        $a = $this->getDi()->accessTable->findFirstBy($x = array(
            'user_id' => $this->user_id,
            'transaction_id' => 'subusers.' . $this->pk(),
        ));
        try {
            if ($a) $a->delete();
        } catch (Am_Exception_Db_NotFound $e) {
            // ignore
        }
        $this->updateQuick('status', 0);
    }
    function delete()
    {
        $this->disable();
        return parent::delete();
    }
}

class SubusersSubscriptionTable extends Am_Table
{
    protected $_key = 'subusers_subscription_id';
    protected $_table = '?_subusers_subscription';
    protected $_recordClass = 'SubusersSubscription';
    
    const STATUS_PENDING = 0;
    const STATUS_ACTIVE = 1;
    
    /**
     * Return count of added subscriptions
     * @param type $user_id
     * @param array $groups 
     */
    function setForUser($user_id, array $groups)
    {
        $ret = 0;
        
        $user_id = (int)$user_id;
        $groups = array_filter(array_map('intval', $groups));
        
        $existsRows = $this->selectObjects("SELECT * FROM $this->_table
            WHERE user_id=?d", $user_id);
        $exists = array();
        foreach ($existsRows as $s) // delete 
        {
            $exists[] = $s->product_id;
            if (in_array($s->product_id, $groups)) continue; // present - dont delete
            $s->delete();
        }
        // 
        foreach (array_diff($groups, $exists) as $id) // insert
        {
            $s = $this->createRecord();
            $s->_new = true;
            $s->user_id = $user_id;
            $s->product_id = $id;
            $s->status = 0;
            $s->insert();
            $ret++;
        }
        return $ret;
    }
    function getForUser($user_id, $status = null)
    {
        return $this->_db->selectCol("SELECT product_id
            FROM {$this->_table} WHERE user_id=?d {AND status=?d}", 
                $user_id, $status === null ? DBSIMPLE_SKIP : $status);
    }
    
    /**
     * @return array product_id => title 
     * filters out only products that can be resold (has data.subusers_product_id) 
     */
    function getProductOptions(User $reseller, $available_only = false)
    {
        if($available_only)
        {
            $avail_groups = array_keys(array_filter($reseller->data()->get('subusers_count'),array($this,'checkAvailableGroup')));
            $avail_groups[]=0;
        }
        return $this->_db->selectCol("SELECT DISTINCT p1.product_id as ARRAY_KEY, p1.title
            FROM ?_product p
                INNER JOIN ?_product p1 on p1.product_id=p.subusers_product_id
                { WHERE p1.product_id IN (?a) }
            ",$available_only ? $avail_groups : '');
    }
    
    /**
     * @return array product_id => title 
     * filters out only products that can be resold (has data.subusers_product_id)  or already added to subuser
     */
    function getProductOptionsForUser(User $reseller, $user_id, $status = null)
    {
        $user_groups = $this->getForUser($user_id, $status);
        $avail_groups = array_keys(array_filter($reseller->data()->get('subusers_count'),array($this,'checkAvailableGroup')));
        $all_groups = $this->_db->selectCol("SELECT DISTINCT p1.product_id as ARRAY_KEY, p1.title
            FROM ?_product p
                INNER JOIN ?_product p1 on p1.product_id=p.subusers_product_id");
        $diff_groups = array_diff(array_keys($all_groups), $avail_groups);
        $options = array();
        foreach($avail_groups as $agid)
            if(isset($all_groups[$agid]))
                $options[$agid] = $all_groups[$agid];
        foreach($user_groups as $ugid)
        {
            if(in_array($ugid, $avail_groups)) continue;
            if(!in_array($ugid, $diff_groups)) continue;
            if(in_array($ugid, array_keys($all_groups)))
                $options[$ugid] = $all_groups[$ugid];
        }
        return $options;
    }
    
    function checkAvailableGroup($var)
    {
        return ($var['avail_count']-$var['active_count'])>0;
    }
    
    /**
     * Always select last records so reseller cannot trick it easy 
     * by introducing new users each time
     */
    function selectToDisable($reseller_id, $product_id, $limit)
    {
        return $this->selectObjects("SELECT t.* 
            FROM $this->_table t 
                INNER JOIN ?_user u USING (user_id)
            WHERE u.subusers_parent_id=?d 
                AND t.product_id=?d AND t.status>0
            ORDER BY $this->_key DESC
            LIMIT ?d", $reseller_id, $product_id, $limit);
    }
    function selectToEnable($reseller_id, $product_id, $limit)
    {
        return $this->selectObjects("SELECT t.* 
            FROM $this->_table t 
                INNER JOIN ?_user u USING (user_id)
            WHERE u.subusers_parent_id=?d 
                AND t.product_id=?d AND t.status=0
            LIMIT ?d", $reseller_id, $product_id, $limit);
    }
}
