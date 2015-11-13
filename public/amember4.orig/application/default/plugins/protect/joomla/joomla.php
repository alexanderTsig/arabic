<?php
/**
 * @table integration
 * @id joomla
 * @title Joomla
 * @visible_link http://www.joomla.org/
 * @description Joomla! is one of the most powerful Open Source Content
 * Management Systems on the planet. It is used all over the world for
 * everything from simple websites to complex corporate applications.
 * Joomla! is easy to install, simple to manage, and reliable.
 * @different_groups 1
 * @single_login 1
 * @type Content Management Systems (CMS)
 */
class Am_Protect_Joomla extends Am_Protect_Databased {

    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_COMM = self::COMM_COMMERCIAL; //paid
    const PLUGIN_REVISION = '4.4.2';

    protected $groupMode = self::GROUP_MULTI;
    protected $guessTablePattern = "users";
    protected $guessFieldsPattern = array(
        'id',
        'name',
        'username',
        'sendEmail',
        'activation'
    );
    protected $versions = array(170 => '1.7', 160 => '1.6', 150 => '1.5');
    protected $defaultVersion = 170;
    protected $_gc = array();

    const JOOMLA = "joomla";
    
    public function __construct(Am_Di $di, array $config) {
        parent::__construct($di, $config);
        if ($this->getConfig('version') == 150)
            $this->groupMode = self::GROUP_SINGLE;
    }

    function parseExternalConfig($path) {
        $config_path = $path . "/configuration.php";
        if (!is_file($config_path) || !is_readable($config_path))
            throw new Am_Exception_InputError("Specified path is not a valid Joomla  installation!");
        include($config_path);
        if (!class_exists('JConfig'))
            throw new Am_Exception_InputError("Specified path is not a valid Joomla installation!");
        $jconfig = new JConfig;
        return array('db' => $jconfig->db,
            'host' => $jconfig->host,
            'user' => $jconfig->user,
            'pass' => $jconfig->password,
            'prefix' => $jconfig->dbprefix,
            'secret' => $jconfig->secret,
            'live_site' => $jconfig->live_site
        );
    }

    function afterAddConfigItems(Am_Form_Setup_ProtectDatabased $form) {
        parent::afterAddConfigItems($form);
        $form->addText('protect.joomla.secret')->setLabel(array('Secret Word',
            "You can get it from Joomla configuration.php file."));
        $form->addText('protect.joomla.live_site')->setLabel(array('Live Site URL',
            "You can get it from Joomla configuration.php file."));

        $form->addSelect('protect.joomla.version', '', array('options' => $this->versions, 'default' => $this->defaultVersion))->setLabel(array('Joomla version',
            ""));
        if ($this->haveJomsocial() || $this->getConfig('jomsocial'))
        {
            $form->addAdvCheckbox('protect.joomla.jomsocial')->setLabel("Update JomSocial groups");
        }
        if ($this->haveJacl() || $this->getConfig('jacl'))
        {
            $form->addAdvCheckbox('protect.joomla.jacl')->setLabel("Update JACL groups");
        }
        
    }
    
    function haveJomsocial()
    {
        try
        {
            $this->_db->query('select count(*) from ?_community_groups');
        }
        catch (Exception $e)
        {
            return false;
        }
        return true;
    }
    
    function haveJacl()
    {
        try
        {
            $this->_db->query('select count(*) from ?_jaclplus_user_group');
        }
        catch (Exception $e)
        {
            return false;
        }
        return true;
    }

    public function getIntegrationSettingDescription(array $config)
    {
        $groups = array();
        
        //default joomla groups
        $groups[] = parent::getIntegrationSettingDescription($config);
        
        //jomsocial groups
        if(@count((array) $config['jomsocial_groups']))
        {
            $jomsocial_groups = array_combine((array) $config['jomsocial_groups'], (array) $config['jomsocial_groups']);
            try
            {
                foreach ($this->getJomsocialGroups() as $g_id => $g_name)
                {
                    if (!empty($jomsocial_groups[$g_id]))
                        $jomsocial_groups[$g_id] = '[' . $g_name . ']';
                }
            } catch (Am_Exception_PluginDb $e)
            {

            }
            if(@count($jomsocial_groups))
                $groups[] = ___('Assign JomSocial Group') . ' ' . implode(",", array_values($jomsocial_groups));
        }
        
        //jacl groups
        if(@count((array) $config['jacl_groups']))
        {
            $jacl_groups = array_combine((array) $config['jacl_groups'], (array) $config['jacl_groups']);
            try
            {
                foreach ($this->getAvailableUserGroups() as $g)
                {
                    $id = $g->getId();
                    if (!empty($groups[$id]))
                        $jacl_groups[$id] = '[' . $g->getTitle() . ']';
                }
            } catch (Am_Exception_PluginDb $e)
            {

            }
            if(@count($jacl_groups))
                $groups[] = ___('Assign JACL Group') . ' ' . implode(",", array_values($jacl_groups));
        }
        return implode(' ', $groups);
    }
    
    public function getIntegrationFormElements(HTML_QuickForm2_Container $group)
    {
        parent::getIntegrationFormElements($group);
        if ($this->getConfig('jomsocial'))
        {
            $group->addSelect('jomsocial_groups', array(), array(
                'options' =>array(
                    '' => '-- Select Group --'
                    ) + $this->getJomsocialGroups()
                ))->setLabel('JomSocial Group');
        }        
        if ($this->getConfig('jacl'))
        {
            $groups = $this->getManagedUserGroups();
            $options = array();
            foreach ($groups as $g)
                $options[$g->getId()] = $g->getTitle();

            $group->addSelect('jacl_groups', array(), array(
                'options' =>array(
                    '' => '-- Select Group --'
                    ) + $options
                ))->setLabel('JACL Group');
        }        
    }
    
    function getJomsocialGroups()
    {
        $ret = array();
        try
        {
            foreach ($this->getDb()->select("SELECT id, name FROM ?_community_groups") as $g)
            {
                $ret[$g['id']] = $g['name'];
            }
        }
        catch (Exception $e)
        {
            $ret = array();
        }
        return $ret;
    }

    function loadGroups_160() {
        if (!empty($this->_gc))
            return;
        foreach ($this->getDb()->select("SELECT * from ?_usergroups order by id") as $gr) {
            $this->_gc[$gr['parent_id']][] = array('ID' => $gr['id'], 'Title' => $gr['title'], 'ParentID' => $gr['parent_id']);
        }
    }
    function loadGroups_150() {
        if (!empty($this->_gc))
            return;
        foreach ($this->getDb()->select("SELECT *, name as title from ?_core_acl_aro_groups order by id") as $gr) {
            $this->_gc[$gr['parent_id']][] = array('ID' => $gr['id'], 'Title' => $gr['title'], 'ParentID' => $gr['parent_id']);
        }
    }

    function buildTree($parentID, $level) {
        $ret = array();
        if (empty($this->_gc))
            return array();
        foreach ((array) $this->_gc[$parentID] as $item) {
            // Add value for item;
            $g = new Am_Protect_Databased_Usergroup(array(
                        'id' => $item['ID'],
                        'title' => str_repeat("--", $level) . " " . $item['Title'],
                    ));
            $ret[$g->getId()] = $g;
            if (array_key_exists($item['ID'], $this->_gc))
                $ret = $ret + $this->buildTree($g->getId(), $level + 1);
        }
        return $ret;
    }

    public function getAvailableuserGroups_160() {
        $this->loadGroups_160();
        return $this->buildTree(0, 0);
    }

    public function getAvailableUserGroups_150() {
        $this->loadGroups_150();
        return $this->buildTree(0, 0);
    }

    public function getAvailableUserGroups() {
        return $this->runFromVersion("getAvailableUserGroups");
    }

    public function runFromVersion($function, $args = array(), $obj = null) {
        if (!$obj)
            $obj = $this;
        // try to execute function for our version: 
        $version = $this->getConfig("version", $this->defaultVersion);
        foreach ($this->versions as $k => $v) {
            if ($k <= $version) {
                $fname = $function . "_" . $k;
                if (method_exists($obj, $fname))
                    return call_user_func_array(array($obj, $fname), $args);
            }
        }
        if (method_exists($obj, $fname = $function . "_")) {
            return call_user_func_array(array($obj, $fname), $args);
        }
        throw new Am_Exception_InternalError("Can't find suitable method $function for Joomla version: " . $version);
    }

    public function getPasswordFormat() {
        return self::JOOMLA;
    }

    function cryptPassword($pass, &$salt = null, User $user = null) {
        if (!$salt)
            $salt = $this->getDi()->app->generateRandomString(32);
        if(strpos($salt, ':')!== false){
            list(,$salt) = explode(':', $salt);
        }
        return md5($pass . $salt) . ":" . $salt;
    }

    public function getReadme() {
        return <<<CUT
<b>Joomla plugin readme</b>
Plugin is compatible with Joomla 1.5, 1.7, 2.5, 3

For better integration, you can install a plugin to Joomla, that will make
redirects to aMember for the following events:
  1) Registration (/amember/signup)
  2) Log-in (/amember/login)
  3) User Details Change (/amember/profile)
  4) Get Lost Password (/amember/login)
  5) Logout (/amember/logout)
(1), (2) and (3) may not be suitable for all users. Make sure you really need
this and it will work as expected. To don't confuse your members, you will 
need to edit aMember templates (at least header and footer) to more or less
match your Joomla look.
  
aMember <-> Joomla Plugin installation
(It was tested with Joomla Version 1.5.26)
---------------------------------------------
1. Go to Joomla Administrator -> Extensions -> Install/Uninstall
2. Choose Upload package file : amember-plugin15.zip
3. Click Upload File & Install, if there are any installation problems, 
   check permissions for "joomla/plugins/" folder -
   it must be  writeable (chmod it 777)
4. Go to Joomla Administrator -> Extensions -> Plugin Manager 
5. Find 'System - aMember' and click it
6. In 'Details': Status turn on 'Enabled'
7. In 'Parameters -> Plugin Parameters': enter correct aMember Root URL
8. Click 'Save'.

aMember <-> Joomla Plugin installation
(It was tested with Joomla Version 2.5.6)
---------------------------------------------
1. Go to Joomla Administrator -> Extensions -> Extension Manager
2. Choose Upload package file : amember-plugin.zip
3. Click Upload File & Install, if there are any installation problems, 
   check permissions for "joomla/plugins/" folder -
   it must be  writeable (chmod it 777)
4. Go to Joomla Administrator -> Extensions -> Plug-in Manager
5. Find 'System - aMember' and click it
6. In 'Details': Status turn on 'Enabled'
7. In 'Basic Options': enter correct aMember Root URL
8. Click 'Save'.

Try to register, login, change details, get lost password, and finally logout from joomla.

CUT;
    }

    function createTable() {
        return $this->runFromVersion('createTable');
    }

    function createTable_160() {
        $table = new Am_Protect_Table_Joomla($this, $this->getDb(), '?_users', 'id');

        $table->setFieldsMapping(array(
            array(Am_Protect_Table::FIELD_LOGIN, 'username'),
            array(Am_Protect_Table::FIELD_NAME, 'name'),
            array(Am_Protect_Table::FIELD_EMAIL, 'email'),
            array(Am_Protect_Table::FIELD_PASS, 'password'),
            array(Am_Protect_Table::FIELD_ADDED_SQL, 'registerDate')
        ));

        $table->setGroupsTableConfig(array(
            Am_Protect_Table::GROUP_TABLE => "?_user_usergroup_map",
            Am_Protect_Table::GROUP_GID => 'group_id',
            Am_Protect_Table::GROUP_UID => 'user_id'
        ));

        return $table;
    }

    function createTable_150() {
        return new Am_Protect_Table_Joomla_150($this, $this->getDb(), '?_users', 'id');
    }

    function createSessionTable() {
        return $this->runFromVersion("createSessionTable");
    }

    function getSessionCookieName() {
        return md5(md5($this->getConfig('secret') . 'site'));
    }

    function createSessionTable_170() {
        $table = new Am_Protect_SessionTable_Joomla($this, $this->getDb(), '?_session', 'session_id');
        $table->setTableConfig(array(
            Am_Protect_SessionTable::FIELD_SID => 'session_id',
            Am_Protect_SessionTable::FIELD_UID => 'userid',
            Am_Protect_SessionTable::FIELD_CHANGED => 'time',
            Am_Protect_SessionTable::SESSION_COOKIE => $this->getSessionCookieName(),
            Am_Protect_SessionTable::FIELDS_ADDITIONAL =>
            array('guest' => 0,
                'username' => create_function('$record, $session', 'return $record->username;'),
                'data'  =>  array($this, 'getSessionData_170')
            )
        ));
        return $table;
    }

    function createSessionTable_160() {
        $table = new Am_Protect_SessionTable_Joomla($this, $this->getDb(), '?_session', 'session_id');
        $table->setTableConfig(array(
            Am_Protect_SessionTable::FIELD_SID => 'session_id',
            Am_Protect_SessionTable::FIELD_UID => 'userid',
            Am_Protect_SessionTable::FIELD_CHANGED => 'time',
            Am_Protect_SessionTable::SESSION_COOKIE => $this->getSessionCookieName(),
            Am_Protect_SessionTable::FIELDS_ADDITIONAL =>
            array('guest' => 0,
                'username' => create_function('$record, $session', 'return $record->username;'),
                'data'  =>  array($this, 'getSessionData_150')
            )
        ));
        return $table;
    }
    
    function createSessionTable_150() {
        $table = new Am_Protect_SessionTable_Joomla($this, $this->getDb(), '?_session', 'session_id');
        $table->setTableConfig(array(
            Am_Protect_SessionTable::FIELD_SID => 'session_id',
            Am_Protect_SessionTable::FIELD_UID => 'userid',
            Am_Protect_SessionTable::FIELD_CHANGED => 'time',
            Am_Protect_SessionTable::SESSION_COOKIE => $this->getSessionCookieName(),
            Am_Protect_SessionTable::FIELDS_ADDITIONAL =>
            array('guest' => 0,
                'username' => create_function('$record, $session', 'return $record->username;'),
                'data'  =>  array($this, 'getSessionData_150'),
                'gid'   =>create_function('$record, $session', 'return $record->gid;'), 
                'usertype'  =>  create_function('$record, $session', 'return $record->usertype;')
            )
        ));
        return $table;
    }
    
    function getSessionData_170(Am_Record $record, Am_Record $session) {
        $user = new JUser($record);
        //$user->setParams(new JParameter);
        $data = array(
            'session.counter'           =>  1,
            'session.timer.start'       =>  time(),
            'session.timer.last'        =>  time(),
            'session.timer.now'         =>  time(),
            'session.client.browser'    =>  $this->getDi()->request->getServer('HTTP_USER_AGENT'),
            'user'                      =>  $user,
            'registry'                  =>  new JRegistry()
        );
        return '__default|'.serialize($data);
    }
    
    function getSessionData_150(Am_Record $record, Am_Record $session) {
        $user = new JUser($record);
        $user->setParams(new JParameter);
        try{
            $gids = array($record->gid);
            foreach ($this->getDb()->select("SELECT group_id FROM ?_jaclplus_user_group WHERE id = ?", $record->id) as $gr)
                $gids[] = $gr['group_id'];
            $gids = implode(',', $gids);            
            $jaclplus = $this->getDb()->selectCell("SELECT value from ?_core_acl_aro_groups where id in (?)", $gids);
            $user->jaclplus = $jaclplus;
            $user->gids = $gids;
        }
        catch(Exception $e)
        {
            $this->getDi()->errorLogTable->logException($e);
        }
        $data = array(
            'session.counter'           =>  1,
            'session.timer.start'       =>  time(),
            'session.timer.last'        =>  time(),
            'session.timer.now'         =>  time(),
            'session.client.browser'    =>  $this->getDi()->request->getServer('HTTP_USER_AGENT'),
            'user'                      =>  $user,
            'registry'                  =>  new JRegistry()
        );
        return '__default|'.serialize($data);
    }

    function getHash($seed) {
        return md5($this->getConfig('secret') . $seed);
    }

    function encrypt($s, $key) {
        $ai = $this->xorCharString($s, $key);
        $s1 = "";
        for ($i = 0; $i < count($ai); $i++)
            $s1 = $s1 . $this->intToHex((int) $ai[$i]);
        return $s1;
    }

    function intToHex($i) {
        (int) $j = (int) $i / 16;
        if ((int) $j == 0) {
            (string) $s = " ";
        } else {
            (string) $s = strtoupper(dechex($j));
        }
        (int) $k = (int) $i - (int) $j * 16;
        (string) $s = $s . strtoupper(dechex($k));
        return $s;
    }

    function xorCharString($s, $key) {
        $ac = preg_split('//', $s, -1, PREG_SPLIT_NO_EMPTY);
        (string) $s1 = $key;
        (int) $i = strlen($s1);
        (int) $j = count($ac);
        for ($i = 0; $i < $j; $i = strlen($s1)) {
            $s1 = $s1 . $s1;
        }
        for ($k = 0; $k < $j; $k++) {
            $c = substr($s1, $k, 1);
            $ai[$k] = ord($c) ^ ord($ac[$k]);
        }
        return $ai;
    }
    function calculateJomsocialGroups(User $user)
    {
        $levels = array();
        if ($user && $user->pk())
        {
            foreach ($this->getIntegrationTable()->getAllowedResources($user, $this->getId()) as $integration)
            {
                $vars = unserialize($integration->vars);
                $levels[] = $vars['jomsocial_groups'];
            }
        }
        return array_filter(array_unique($levels));
    }
    function calculateJaclGroups(User $user)
    {
        $levels = array();
        if ($user && $user->pk())
        {
            foreach ($this->getIntegrationTable()->getAllowedResources($user, $this->getId()) as $integration)
            {
                $vars = unserialize($integration->vars);
                $levels[] = $vars['jacl_groups'];
            }
        }
        return array_filter(array_unique($levels));
    }
    function updateJomsocialGroups(Am_Record $record, User $user)
    {
        if(!$this->_db->selectCell("SELECT count(*) FROM ?_community_users WHERE userid = ?", $record->pk()))
            $this->_db->query("INSERT into ?_community_users (userid, avatar, thumb) 
				values(?,'components/com_community/assets/default.jpg','components/com_community/assets/default_thumb.jpg')", $record->pk ());
        $this->_db->query("DELETE FROM ?_community_groups_members  WHERE memberid = ?", $record->pk());
        foreach($this->calculateJomsocialGroups($user) as $group_id)
            $this->_db->query("INSERT INTO ?_community_groups_members 
				(groupid, memberid, approved, permissions)
				values (?, ?, 1, 0)", $group_id, $record->pk());
    }
    function updateJaclGroups(Am_Record $record, User $user)
    {
        $this->_db->query("DELETE FROM ?_jaclplus_user_group  WHERE id = ?", $record->pk());
        foreach($this->calculateJaclGroups($user) as $group_id)
            $this->_db->query("INSERT INTO ?_jaclplus_user_group 
				(id, group_type, group_id)
				values (?, 'sub', ?)", $group_id, $record->pk());        
    }
}

class Am_Protect_Table_Joomla extends Am_Protect_Table {

    function __construct(Am_Protect_Databased $plugin, $db = null, $table = null, $recordClass = null, $key = null) {
        parent::__construct($plugin, $db, $table, $recordClass, $key);
    }
    function setGroups(Am_Record $record, $groups)
    {
        parent::setGroups($record, $groups);
        $record->updateQuick('block', empty($groups) ? 1 : 0);
        if ($this->getPlugin()->getConfig('jomsocial_groups'))
            $this->getPlugin()->updateJomsocialGroups($record, $this->findAmember($record));
        if ($this->getPlugin()->getConfig('jacl_groups'))
            $this->getPlugin()->updateJaclGroups($record, $this->findAmember($record));
    }
}

class Am_Protect_Table_Joomla_150 extends Am_Protect_Table
{
    public function __construct(Am_Protect_Databased $plugin, $db = null, $table = null, $recordClass = null, $key = null) {
        parent::__construct($plugin, $db, $table, $recordClass, $key);

        $this->setFieldsMapping(array(
            array(Am_Protect_Table::FIELD_LOGIN, 'username'),
            array(Am_Protect_Table::FIELD_NAME, 'name'),
            array(Am_Protect_Table::FIELD_EMAIL, 'email'),
            array(Am_Protect_Table::FIELD_PASS, 'password'),
            array(Am_Protect_Table::FIELD_ADDED_SQL, 'registerDate'),
            array(Am_Protect_Table::FIELD_GROUP_ID, 'gid'),
        ));
        
        $this->setGroupsTableConfig(array(
            Am_Protect_Table::GROUP_TABLE => "?_core_acl_aro",
            Am_Protect_Table::GROUP_GID => 'group_id',
            Am_Protect_Table::GROUP_UID => 'value'
        ));
    }
    
    
    public function getGroups(Am_Record $record) {
        return $this->getPlugin()->getDb()->selectCol("SELECT group_id FROM ?_core_acl_groups_aro_map
            WHERE aro_id=?d", $this->getAroId($record));
    }
                
    public function setGroups(Am_Record $record, $groups) 
    {
        $aro_id = $this->getAroId($record);
        $rows = array();
        foreach ((array)$groups as $group_id)
            $rows[] = "(" . intval($group_id) . ", $aro_id)";
        $this->getPlugin()->getDb()->query("DELETE FROM ?_core_acl_groups_aro_map
            WHERE aro_id=?d", $aro_id);
        if ($rows)
            $this->getPlugin()->getDb()->query("INSERT INTO ?_core_acl_groups_aro_map (group_id, aro_id) VALUES " . 
                implode("\n,", $rows));
        
        $groups = (array)$groups;
        reset($groups);
        $record->updateQuick(array(
            'block' => empty($groups) ? 1 : 0,
            'gid'   => current($groups),
            'usertype' => $this->getPlugin()->getDb()->selectCell('SELECT name FROM ?_core_acl_aro_groups WHERE id = ?', current($groups))
        ));
        if ($this->getPlugin()->getConfig('jomsocial_groups'))
            $this->getPlugin()->updateJomsocialGroups($record, $this->findAmember($record));
        if ($this->getPlugin()->getConfig('jacl_groups'))
            $this->getPlugin()->updateJaclGroups($record, $this->findAmember($record));
    }
    public function getAroId(Am_Record $record)
    {
        //mysql> select * from jos_core_acl_aro;
        //+----+---------------+-------+-------------+---------------+--------+
        //| id | section_value | value | order_value | name          | hidden |
        //+----+---------------+-------+-------------+---------------+--------+
        //| 10 | users         | 62    |           0 | Administrator |      0 |
        //| 11 | users         | 64    |           0 | Xxx           |      0 |
        //+----+---------------+-------+-------------+---------------+--------+
       $db = $this->getPlugin()->getDb();
       $id = $db->selectCell("SELECT id FROM ?_core_acl_aro
           WHERE section_value='users' AND value=?d", $record->id);
       if ($id) return $id;
       $db->query("INSERT INTO ?_core_acl_aro SET section_value=?, value=?d, name=?",
               'users', $record->id, $record->name);
       return $db->selectCell("SELECT LAST_INSERT_ID()");
    }
}

class Am_Protect_SessionTable_Joomla extends Am_Protect_SessionTable 
{
    function __construct(Am_Protect_Databased $plugin, $db = null, $table = null, $key = null, $recordClass = null) 
    {
        parent::__construct($plugin, $db, $table, $key, $recordClass);
    }
}


//For session compatibility;
class JUser{
    public $id;
    public $name;
    public $username;
    public $email; 
    public $password;
    public $usertype;
    public $block;
    public $sendEmail;
    public $registerDate;
    public $lastvisitDate;
    public $activation;
    public $params;
    public $gid;
    public $aid;
    public $guest;
    protected $_params;
    
    function __construct(Am_Record $record)
    {
        foreach(get_class_vars(get_class($this)) as $k=>$v){
            if($k == '_params') continue;
            $this->$k = $record->get($k);
        }
        $this->setParams(new JRegistry());
        $this->aid = ($this->gid ==18 ? 1 : 2);
        $this->guest = 0;
    }
    function setParams($o)
    {
        $this->_params = $o;
        return $this;
    }
}

class JParameter 
{
    public $_registry = array('_default' => array());
    public $_defaultNameSpace = '_default';
}

class JRegistry{
    protected $data;
    
    function __construct(){
        $this->data = new stdClass();
    }
}
