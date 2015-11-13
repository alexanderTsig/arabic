<?php

/*
 * - if there are free limits, display links to : 
 *      * generate unique user signup link
 * 
 * - also revoke access by cron daily
 * 
 * - check handling of refunds / expirations of main users
 * 
 */

class Bootstrap_Subusers extends Am_Module
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.6.3';
    const SAVED_FORM_TYPE = 'profile-subuser';
    
    const ADMIN_PERM_ID = 'subusers';    
    
    protected $_session;

    static function  activate($id, $pluginType)
    {
        parent::activate($id, $pluginType);
        self::setUpSubuserProfileFormIfNotExist(Am_Di::getInstance()->db);
    }

    function onGetPermissionsList(Am_Event $event)
    {
        $event->addReturn("Can manage subusers", self::ADMIN_PERM_ID);
    }
    
    function onSavedFormTypes(Am_Event $event)
    {
        $event->getTable()->addTypeDef(array(
            'type' => self::SAVED_FORM_TYPE,
            'title' => 'Subuser Profile Form',
            'class' => 'Am_Form_Profile',
            'defaultTitle' => 'Customer Profile',
            'defaultComment' => 'subuser profile form',
            'isSingle' => true,
            'isSignup' => false,
            'noDelete' => true,
            'urlTemplate' => 'profile',
        ));
    }
    
    
    function getSession(){
        if(empty($this->_session))
            $this->_session = new Zend_Session_Namespace('am_subusers');
        return $this->_session;
    }
    
    
    function onInitFinished()
    {
        
        $this->getDi()->blocks->add(
            new Am_Block(
                'content/before', 
                "Parent User Info", 
                'subusers-logged-in', 
                $this, 
                array($this, 'loginBlock'),
                Am_Block::TOP
                    )
                );
    }
    function loginBlock(){
        if(is_array($parent_user = $this->getSession()->parent_user))
        {
            $login = $parent_user['reseller_login'];
            $root = REL_ROOT_URL;
            return <<<CUT
<span class="error"><strong>You are logged on as a reseller user: {$login}.</strong> Click <a href="{$root}/subusers/index/restore-session" class='error'>here</a> to restore your session</span>                        
CUT;
        }
    }

    public function onEmailTemplateTagSets(Am_Event $event)
    {
        $tagSets = $event->getReturn();
        $tagSets['reseller'] = array(
                '%reseller.name_f%' => 'Reseller First Name',
                '%reseller.name_l%' => 'Reseller Last Name',
                '%reseller.login%' => 'Reseller Username',
                '%reseller.email%' => 'Reseller E-Mail',
                '%reseller.user_id%' => 'Reseller Internal ID#',
                '%reseller.street%' => 'Reseller Street',
                '%reseller.street2%' => 'Reseller Street (Second Line)',
                '%reseller.city%' => 'Reseller City',
                '%reseller.state%' => 'Reseller State',
                '%reseller.zip%' => 'Reseller ZIP',
                '%reseller.country%' => 'Reseller Country',
            );

        foreach ($this->getDi()->userTable->customFields()->getAll() as $field) {
            if (@$field->sql && @$field->from_config) {
                $tagSets['reseller']['%reseller.' . $field->name . '%'] = 'Reseller ' . $field->title;
            }
        }

        $event->setReturn($tagSets);
    }

    function onLoadProfileForm(Am_Event $event)
    {
        if (!$this->getDi()->config->get('subusers_different_profile_form')) return;

        /* @var $user User */
        $user = $event->getUser();

        if ($user->subusers_parent_id)
            $event->setReturn($this->getDi()->savedFormTable->findFirstByType(self::SAVED_FORM_TYPE));
    }

    function onUserBeforeMerge(Am_Event $event)
    {
        $target = $event->getTarget();
        $source = $event->getSource();

        if ($target->subusers_parent_id && $source->subusers_parent_id
                && $target->subusers_parent_id!=$source->subusers_parent_id) {

            throw new Am_Exception_InputError(___('You can not merge subusers of different user'));
        }

        if ( ($target->subusers_parent_id && !$source->subusers_parent_id)
            || (!$target->subusers_parent_id && $source->subusers_parent_id)) {
                
                throw new Am_Exception_InputError(___('You can not merge ordinary user with subuser and vice versa'));
        }
    }

    function onUserMerge(Am_Event $event)
    {
        $target = $event->getTarget();
        $source = $event->getSource();

        $this->getDi()->db->query('UPDATE ?_user SET subusers_parent_id=? WHERE subusers_parent_id=?',
            $target->pk(), $source->pk());
        $this->getDi()->db->query('UPDATE ?_subusers_subscription SET user_id=? WHERE user_id=?',
            $target->pk(), $source->pk());
    }

    function onLoadSignupForm(Am_Event $event)
    {
        if (!$this->getDi()->config->get('subusers_cannot_pay')) return;
        $user = $event->getUser();
        if (!$user || !$user->get('subusers_parent_id')) return;
        throw new Am_Exception_InputError(___('Signup/payment functions are disabled for this user account'));
    }
    
    function onUserAfterUpdate(Am_Event_UserAfterUpdate $event)
    {
        $user = $event->getUser();
        $old  = $event->getOldUser();
        if ($user->get('is_locked') != $old->get('is_locked'))
            $this->getDi()->db->query("UPDATE ?_user SET is_locked=?d WHERE subusers_parent_id=?d", 
                $user->get('is_locked'), $user->pk());
    }
    function onUserSearchConditions(Am_Event $event)
    {
        $event->addReturn(new Am_Query_User_Condition_IsSubuser);
        $event->addReturn(new Am_Query_User_Condition_SubuserAssignedTo);
    }
    
    function onSetupForms(Am_Event_SetupForms $event)
    {
        $form = new Am_Form_Setup($this->getId());
        $form->setTitle('SubUsers');
        $event->addForm($form);
        
        $form->addAdvCheckbox('subusers_cannot_pay')->setLabel(___('Hide Payment Forms and History from SubUsers'));
        
        $form->addAdvRadio('subusers_cannot_delete')->setLabel(___('Reseller cannot delete subuser accounts'))
            ->loadOptions(array(
                '0' => ___('Resellers can delete subusers'),
                '1' => ___('Resellers cannot delete subusers'),
                '2' => ___('Resellers can delete subusers only when limit is over'),
            ));
        
        $form->addAdvCheckbox('subusers_cannot_edit')->setLabel(___('Reseller cannot edit subusers accounts after insertion'));
        
        $form->addAdvCheckbox('subusers_cannot_change_email')->setLabel(___('Subuser e-mail address can be changed by site admin only'));
        
        $gr = $form->addGroup('subusers_fields')->setLabel(___('Reseller can manage the following subuser fields'));
        $gr->setSeparator(' ');
        $gr->addCheckbox('', array('value' => 'login'))->setContent(___('Username'));
        $gr->addCheckbox('', array('value' => 'pass'))->setContent(___('Password'));
        $gr->addHidden('', array('value' => '_'));
        $gr->addFilter(array($this, '_filterHidden'));

        $form->addAdvCheckbox('subusers_can_login')->setLabel(___('Reseller can log-in as subuser'));
        
        $form->addAdvCheckbox('subusers_different_profile_form')
            ->setLabel(___("Use different profile form for subusers\n" .
                'you can configure subusers profile form %shere%s',
                '<a class="link" href="' . REL_ROOT_URL . '/admin-saved-form">', '</a>'));
        
        $form->addElement('email_link', 'subusers.registration_mail')
             ->setLabel(___('Registration E-Mail to Subusers'));

        $form->addEpilog('<div class="info"><pre>' . $this->getReadme() . '</pre></div>');
    }
    function _filterHidden($subusersFields)
    {
        foreach ($subusersFields as $k => $v)
            if ($v == '_')
                unset($subusersFields[$k]);
        return $subusersFields;
    }
    
    function onSetupEmailTemplateTypes(Am_Event $event)
    {
        $event->addReturn(array(
            'id' => 'subusers.registration_mail',
            'title' => '%site_title% Registration',
            'mailPeriodic' => Am_Mail::USER_REQUESTED,
            'vars' => array(
                'user',
                'password' => 'Plain-Text Password',
                'reseller',
                'reseller_product' => 'Reseller Product',
                'user_product' => 'Sub-User Product',
            ),
        ), 'subusers.registration_mail');
    }

    function onDaily(Am_Event $event)
    {
        $q = new Am_Query_User();
        $q->add(new Am_Query_Condition_Data('subusers_count', 'IS NOT NULL'));
        foreach ($q->selectPageRecords(0, 100000) as $user) // max 100000 resellers supported
        {
            $this->checkAndUpdate($user);
        }
    }
    
    // add "Subusers" tab to user menu if user is a reseller
    function onUserMenu(Am_Event $event)
    {
        $user = $event->getUser();
        if ($user->data()->get('subusers_count'))
        {
            $menu = $event->getMenu();
            /* @var $menu Am_Navigation_User */
            $menu->addPage(array(
                'id' => 'subusers',
                'controller' => 'index',
                'module' => 'subusers',
                'action' => 'index',
                'label' => ___('Subusers'),
                'order' => 250,
            ));
        } elseif ($this->getDi()->config->get('subusers_cannot_pay') && $user->get('subusers_parent_id')) {
            $menu = $event->getMenu();
            $page = $menu->findOneBy('id', 'add-renew');
            if ($page) $menu->removePage($page);
            $page = $menu->findOneBy('id', 'payment-history');
            if ($page) $menu->removePage($page);
        }
    }

    function onRebuild(Am_Event_Rebuild $event)
    {
        $batch = new Am_BatchProcessor(array($this, 'batchProcess'), 5);
        $context = $event->getDoneString();
        $this->_batchStoreId = 'rebuild-' . $this->getId() . '-' . Zend_Session::getId();
        if ($batch->run($context))
        {
            $event->setDone();
        } else
        {
            $event->setDoneString($context);
        }
    }
    
    function batchProcess(& $context, Am_BatchProcessor $batch)
    {
        $db = $this->getDi()->db;
        $context = $context ? intval($context) : 0;
        $q = $db->queryResultOnly("SELECT DISTINCT subusers_parent_id
            FROM ?_user
            WHERE subusers_parent_id > ?d
            ORDER BY subusers_parent_id", $context);
        $userTable = $this->getDi()->userTable;
        while ($r = $db->fetchRow($q))
        {
            $context = $r['subusers_parent_id'];
            $this->checkAndUpdate($userTable->load($context));
            if (!$batch->checkLimits()) return;
        }
        return true;
    }

    function onGridUserInitForm(Am_Event_Grid $event)
    {
        $form = $event->getGrid()->getForm();
        $user = $event->getGrid()->getRecord();
        if ($user->data()->get('subusers_count'))
        {
            $el = new Am_Form_Element_Html();
            $url = Am_Controller::escape(REL_ROOT_URL . '/subusers/admin/tab/user_id/' . $user->pk());
            $el->setHtml('<div>'.___('This customer is a reseller').'. <a href="'.$url.'">details...</a></div>')->setLabel(___('Subusers'));
            $form->insertBefore($el, $form->getElementById('general'));
        }
        if ($parent_id = $user->get('subusers_parent_id'))
        {
            $el = new Am_Form_Element_Html();
            $parent = $this->getDi()->userTable->load($parent_id, false);
            $url = $this->getDi()->view->userUrl((int)$parent_id);
            $html = sprintf('<div>'. ___('This customer is a subuser of') .' <a href="%s">%s %s &lt;%s&gt;</a> (%s)</div>',
                Am_Controller::escape($url),
                Am_Controller::escape($parent->name_f), 
                Am_Controller::escape($parent->name_l), 
                Am_Controller::escape($parent->email),
                Am_Controller::escape($parent->login)
            );
            $el->setHtml($html)->setLabel(___('Subusers'));
            $form->insertBefore($el, $form->getElementById('general'));
        }
    }
    
    function onUserTabs(Am_Event $event)
    {
        $user_id = $event->getUserId();
        if (!$user_id) return;
        $user = $this->getDi()->userTable->load($user_id, false);
        if (!$user) return;
        if (!$user->data()->get('subusers_count'))
            return;
        $menu = $event->getTabs();
        /* @var $menu Am_Navigation_User */
        $menu->addPage(array(
            'id' => 'subusers',
            'controller' => 'admin',
            'module' => 'subusers',
            'action' => 'tab',
            'label' => ___('Subusers'),
            'order' => 250,
            'resource' => self::ADMIN_PERM_ID,
            'params' => array('user_id' => $user_id),
        ));
    }
    
    
    function onGridProductInitForm(Am_Event_Grid $event)
    {
        $fs = $event->getGrid()->getForm()->getAdditionalFieldSet();

        $fs->addInteger('subusers_count')
            ->setLabel( ___("SubUsers Count\n".
                    "(keep zero for non-reseller products)"));
        $options = array('' => '-- ' . ___('Please select') . ' --');
        foreach ($this->getDi()->db->selectCol("SELECT product_id AS ARRAY_KEY, title FROM ?_product") as $k => $v)
            $options[$k] = $v;
        $fs->addSelect('subusers_product_id')
            ->setLabel(___("SubUsers Product\n".
            "(keep empty for non-reseller products)"))
            ->loadOptions($options);
    }
    
    function renderMemberBlock()
    {
        $user = $this->getDi()->user;
        $ret = "";
        if ($user->get('subusers_parent_id')) // subuser
        {
            $pending = $this->getDi()->subusersSubscriptionTable->countBy(array(
                'user_id' => $user->pk(),
                'status'  => 0,
            ));
            if ($pending)
            {
                $ret .= ___('Subscription expired, please contact your reseller to upgrade subscription');
            }
        } elseif ($subusers_count = $user->data()->get('subusers_count')) { // reseller
            $pending = 0;
            foreach ($subusers_count as $product_id => $v)
                if ($v['pending_count']) $pending+=$v['pending_count'];
            if ($pending)
            {
                $url = Am_Controller::escape(REL_ROOT_URL . '/member/add-renew');
                $ret .= ___('There are %d pending subuser subscriptions. %sUpgrade your access%s',
                    $pending, "<a href='$url'>", '</a>');
            }
        }
        if ($ret)
            return "<div class='am-block subusers-member-notice'>$ret</div>";
    }
    
    public function getReadme()
    {
        $root = REL_ROOT_URL;
        return <<<CUT
SUBUSERS PLUGIN
   
This plugin allows your customers (resellers) to resell your subscriptions to 
"end-users". 
        
RESELLER ORDERS A SUBSCRIPTION FOR THEIR SUBUSERS
        
        Reseller orders a subscription that allows him to create configured number
        of user accounts with access for free. It is the reseller responsibility how
        does it bill their customers. This is also useful for selling corporate or class
        access to your website.

        == Sample Setup == 
        1. Providing you have "reseller" module enabled at aMember CP -> Setup -> Plugins
        2. <a href="$root/admin-products/?_p_a=add" target="_blank">Add Product</a> that will represent end-user site access:
                Title: Sample Subscription
                Billing Terms: 
                    First Amount: 10
                    First Period: 1 month (created users will have access until 

                Mark product as "Disabled" if you do not want customers to directly purchase it
                without resellers.

        3. Go to <a href="$root/admin-content/?_p_a=add" target="_blank">Protect Content -> Files</a> and upload a sample file
            that will be visible to end-users. Specify that is available for subscribers of "Sample Subscription" from 
            start to end of subscription.

        4. <a href="$root/admin-products/?_p_a=add" target="_blank">Add Product</a> that resellers will be ordering from you:
                Title: Reseller Package (5 users)
                Billing Terms: 
                    First Amount: 40
                    First Period: 1 month (created users will have access until 
                                                        reseller subscription expires)
                Reseller Users: 5
                Resell Product: choose "Sample Access"

        5. You are all set. Now anyone who purchased product "Reseller Package" will 
            be able to create 5 customer accounts with access to "Sample Subscription".
            When subscription of the reseller to "Reseller Package" will expire, the
            same will happen with "child" subscriptions.

        6. To try it out, go to aMember CP -> Browse Users, add a user, go to "Payments",
            and add access to "Reseller Package" manually. Then try to login as this
            user and make sure you see "Reseller" tab and can add up to 5 users. Logout, 
            and try to to login as an added user and ensure you have access to file
            you created on step #3.
        
        7. This plugin has additional abilities:
           - you can disable "payments" history and management for subusers
           - subusers gets no expiration emails
           
CUT;
    }
    
    function onAccessAfterInsert(Am_Event $event)
    {
        $this->_onAccessChanged($event);
    }
    function onAccessAfterUpdate(Am_Event $event)
    {
        $this->_onAccessChanged($event);
    }
    function onAccessAfterDelete(Am_Event $event)
    {
        $this->_onAccessChanged($event);
    }
    function _onAccessChanged(Am_Event $event)
    {
        try {
            $user = $event->getAccess()->getUser();
        } catch (Am_Exception_Db_NotFound $e) { 
            return;
        }
        $this->checkAndUpdate($user);
    }
    /**
     * Calculate available limits, enable and disable users
     * @param User $user 
     */
    function checkAndUpdate(User $user)
    {
        $counts = $this->calculateCounts($user->pk());
        $this->workoutLimits($user->pk(), $counts);
        if (!count($counts)) $counts = null;
        $user->data()->set('subusers_count', $counts)->update();
    }
    
    /**
     * 
     *  foreach (subuser access as a)
     *    if (!parent_access_active) remove;
     * 
     *  foreach (reseller access as a)
     *    find subscribed subusers
     *       if subscribed > limit
     *          unsubscribe first d users 
     *       elseif subscribed < limit && pending
     *          subscribe first d users
     * 
     *  delete all subuser access
     *  foreach 
     * 
     * 
     * 
     * 
     * 
     * 
     * 1. Disable overlimit users
     * 2. Enable pending users if limit allows to
     * 3. Update counts if any work is done
     */
    protected function workoutLimits($user_id, array & $counts)
    {
        $changes = 0;
        foreach ($counts as $product_id => $c)
        {
            /////////  reseller ordered - subusers active
            $avail = $c['avail_count'] - $c['active_count'];
            if ($avail<0)
            {
                $this->disableSubusers($user_id, $product_id, - $avail);
                $changes++;
            } elseif (($avail>0) && ($c['pending_count']>0)) {
                $this->enableSubusers($user_id, $product_id, min($avail, $c['pending_count']));
                $changes++;
            }
        }
        if ($changes)
            $counts = $this->calculateCounts($user_id);
    }
    
    protected function disableSubusers($user_id, $product_id, $toDisable)
    {
        // find last access records for $user_id-$product_id
        foreach ($this->getDi()->subusersSubscriptionTable->selectToDisable($user_id, $product_id, $toDisable) as $s)
            $s->disable();
    }
    
    protected function enableSubusers($user_id, $product_id, $toEnable)
    {
        // find unused access records for $user_id-$product_id
        foreach ($this->getDi()->subusersSubscriptionTable->selectToEnable($user_id, $product_id, $toEnable) as $s)
            $s->enable();
    }
    
    /**
     * @return array subusers_product_id => array('avail_count'=>x,'active_count'=>x,'pending_count'=>x)
     */
    protected function calculateCounts($user_id)
    {
        // 
        $ret = $this->getDi()->db->select("
        SELECT 
            p.subusers_product_id as ARRAY_KEY,
                SUM(IFNULL(a.qty, 1) * p.subusers_count) as avail_count
            FROM ?_access a 
                LEFT JOIN ?_product p USING (product_id)
            WHERE a.user_id=?d AND a.begin_date <= ? AND a.expire_date >= ?
            GROUP BY p.subusers_product_id
            HAVING p.subusers_product_id > 0
        ", $user_id, $this->getDi()->sqlDate, $this->getDi()->sqlDate);
        /// 
        $ret2 = $this->getDi()->db->select("
        SELECT s.product_id AS ARRAY_KEY,
            SUM(IF(s.status>0, 1, 0)) AS active_count,
            SUM(IF(s.status=0, 1, 0)) AS pending_count
            FROM ?_subusers_subscription s 
                INNER JOIN ?_user u USING (user_id)
            WHERE u.subusers_parent_id = ?d
            GROUP BY product_id
        ", $user_id);
        // array_merge_recursive 
        foreach ($ret2 as $k => $v)
            if (empty($ret[$k]))
                $ret[$k] = $v;
            else
                $ret[$k] = array_merge($ret[$k], $v);
            
        foreach ($ret as &$v)
        {
            if (empty($v['avail_count'])) $v['avail_count'] = 0;
            if (empty($v['active_count'])) $v['active_count'] = 0;
            if (empty($v['pending_count'])) $v['pending_count'] = 0;
        }
        return $ret;
    }
    
    function onUserAfterDelete(Am_Event $event)
    {
        $id = $event->getUser()->pk();
        $this->getDi()->subusersSubscriptionTable->deleteBy(array('user_id' => $id));
    }

    function onDbUpgrade(Am_Event $e)
    {
        if (version_compare($e->getVersion(), '4.2.20') < 0)
        {
            echo "Set Up Subuser profile form...";
            $this->setUpSubuserProfileFormIfNotExist($this->getDi()->db);
            echo "Done<br>\n";
        }
    }

    protected static function setUpSubuserProfileFormIfNotExist(DbSimple_Interface $db)
    {
        if (!$db->selectCell("SELECT COUNT(*) FROM ?_saved_form WHERE type=?", self::SAVED_FORM_TYPE)) {
            $db->query("INSERT INTO ?_saved_form (title, comment, type, fields)
                SELECT 'Subuser Profile Form', 'subuser profile form', ?, fields
                FROM ?_saved_form WHERE type=?", self::SAVED_FORM_TYPE, SavedForm::T_PROFILE);
        }
    }
}