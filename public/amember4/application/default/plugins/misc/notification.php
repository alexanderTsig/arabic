<?php

/**
 * Plugin allow to set up short text messages with begin and expiration date which
 * will be shown in member area for some defined group of user.
 *
 */
class Am_Plugin_Notification extends Am_Plugin
{

    const
        PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const
        PLUGIN_COMM = self::COMM_COMMERCIAL;
    const
        PLUGIN_REVISION = '4.7.0';
    const
        ADMIN_PERM_ID = 'notification';

    public static
        function activate($id, $pluginType)
    {
        try
        {
            Am_Di::getInstance()->db->query("CREATE TABLE ?_notification (
                notification_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                content varchar(255),
                url varchar(255),
                is_blank TINYINT NOT NULL,
                begin date NULL,
                expire date NULL,
                is_disabled TINYINT NOT NULL
                ) CHARACTER SET utf8 COLLATE utf8_general_ci");
        }
        catch (Am_Exception_Db $e)
        {
            
        }

        try
        {
            Am_Di::getInstance()->db->query("CREATE TABLE ?_notification_access (
                notification_access_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                notification_id INT NOT NULL,
                fn enum('product_id','product_category_id','free','user_id'),
                id INT NULL
                ) CHARACTER SET utf8 COLLATE utf8_general_ci");
        }
        catch (Am_Exception_Db $e)
        {
            
        }

        try
        {
            Am_Di::getInstance()->db->query("ALTER TABLE ?_notification
                ADD `limit` TINYINT,
                ADD is_custom TINYINT NOT NULL");
        }
        catch (Am_Exception_Db $e)
        {
            
        }

        try
        {
            Am_Di::getInstance()->db->query("ALTER TABLE ?_notification_access
                MODIFY fn enum('product_id','product_category_id','free','user_id', 'user_group_id')");
        }
        catch (Am_Exception_Db $e)
        {
            
        }

        try
        {
            Am_Di::getInstance()->db->query("ALTER TABLE ?_notification
                MODIFY content TEXT");
        }
        catch (Am_Exception_Db $e)
        {
            
        }

        try
        {
            Am_Di::getInstance()->db->query("ALTER TABLE ?_notification
                ADD sort_order INT NOT NULL");
            Am_Di::getInstance()->db->query("SET @i = 0");
            Am_Di::getInstance()->db->query("UPDATE ?_notification SET sort_order=(@i:=@i+1) ORDER BY notification_id DESC");
        }
        catch (Am_Exception_Db $e)
        {
            
        }

        try
        {
            Am_Di::getInstance()->db->query("CREATE TABLE IF NOT EXISTS ?_notification_click (
                notification_click_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                notification_id INT NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                dattm date NOT NULL,
                INDEX notification_id (notification_id)
            ) CHARACTER SET utf8 COLLATE utf8_general_ci");
        }
        catch (Am_Exception_Db $e)
        {
            //nop
        }


        try
        {
            Am_Di::getInstance()->db->query("ALTER TABLE ?_notification_access
                ADD   start_days int(11) DEFAULT NULL,
                ADD   stop_days int(11) DEFAULT NULL");
        }
        catch (Exception $ex)
        {
            
        }


        return parent::activate($id, $pluginType);
    }

    public
        function renderNotification()
    {
		// FIXME - This is a hack. Find out why the ROOT_URL begins with http:// (Kerin)
		$root = preg_replace('/^http:/', 'https:', ROOT_URL);
        return <<<CUT
<div id="am-notifications"></div>
<script>
jQuery(document).ready(function() {
  $.getScript('{$root}/misc/notification/js', function(){
   $('#am-notifications').amNotifications({class: 'am-info am-notification'});
  });
});        
</script>
        
CUT;
    }

    public
        function onAdminMenu(Am_Event $event)
    {
        $event->getMenu()->addPage(array(
            'id' => 'notification',
            'module' => 'default',
            'controller' => 'admin-notification',
            'action' => 'index',
            'label' => ___('Notifications'),
            'resource' => self::ADMIN_PERM_ID
        ));
    }

    public
        function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        $user = $this->getDi()->user;
        switch ($request->getActionName())
        {
            case 'c' :
                $id = $this->getDi()->app->reveal($request->getFiltered('id')); //actualy it is notification_id
                $notification = $this->getDi()->notificationTable->load($id);
                $this->getDi()->notificationClickTable->log($user, $notification);
                Am_Controller::redirectLocation($notification->url);
                break;
            case 'get' :
                if (!$user)
                    Am_Controller::ajaxResponse(array());
                else
                {

                    $items = $this->getDi()->notificationTable->getNotificationsForUser($this->getDi()->auth->getUser());
                    $dismiss = $user->data()->getBlob('notification.dismiss');

                    if (!$dismiss)
                        $dismiss = array();
                    else
                        $dismiss = unserialize($dismiss);

                    $out = array();
                    foreach ($items as $item)
                    {
                        $display = $user->data()->get('notification.display.' . $item->pk());

                        if ($item->limit && $display >= $item->limit)
                            continue;

                        if (in_array($item->notification_id, $dismiss))
                            continue;

                        $user->data()->set('notification.display.' . $item->pk(), ++$display);
                        $n = new stdClass;
                        $n->id = $this->getDi()->app->obfuscate($item->notification_id);
                        $n->content = $item->content;
                        $n->is_custom = $item->is_custom ? true : false;
                        $n->is_blank = $item->is_blank ? true : false;
                        $n->link = $item->url;
                        $out[] = $n;
                    }
                    $user->save();
                    Am_Controller::ajaxResponse($out);
                }


                break;
            case 'js' :
                $response->setHeader('Content-Type', 'application/x-javascript; charset=utf-8');
                echo $this->getJs();
                break;
            case 'd' :
                $id = $this->getDi()->app->reveal($request->getFiltered('id'));
                if ($user && $id)
                {
                    $dismiss = $user->data()->getBlob('notification.dismiss');
                    if (!$dismiss)
                        $dismiss = array();
                    else
                        $dismiss = unserialize($dismiss);
                    $dismiss[] = $id;
                    $user->data()->setBlob('notification.dismiss', serialize($dismiss));
                    $user->data()->update();
                }
                break;
            default :
                throw new Am_Exception_InternalError('Unknown Action');
        }
    }

    function _initSetupForm(\Am_Form_Setup $form)
    {
        parent::_initSetupForm($form);
    }

    function getJs()
    {
		// FIXME - This is a hack. Find out why the ROOT_URL begins with http:// (Kerin)
		$root = preg_replace('/^http:/', 'https:', ROOT_URL);
        return <<<CUT
    (function($){
        \$.fn.amNotifications = function(params){
   return this.each(function(){
                var options = params;
                var options = $.extend({
                    class: null,
                    animation: 300,
                    link_text : 'Click here'
                }, options);
                var \$this = $(this);
                $.getJSON("{$root}/misc/notification/get", function(data){
                    \$this.empty();
                    for(i in data){
                        var cont =  $('<div></div>');
                        var n=data[i];
                        cont.attr('id', 'am-notification-'+n.id);
                        if(options.class && !n.is_custom) cont.addClass(options.class);
                        else if(!n.is_custom)
                        {
                                cont.css({
                                    'background-color': '#FFFFCF',
                                    'border-color': '#545454',
                                    'color': '#454430',
                                    'border-radius': '10px',
                                    'margin-bottom': '1em',
                                    'padding': '0.5em 1em',
                                    'border': '1px solid'
                                });
                        }
                        cont.append(n.content);
                        if(n.link){
                            var l = $("<a href='{$root}/misc/notification/c/"+n.id+"' "+(n.is_blank ? "target=_blank" : "")+" >"+options.link_text+"</a>");
                            cont.append("&nbsp;");
                            cont.append(l);
                        }
                        var link = $("<span style='float:right;'><a href='#' style='text-decoration:none;'>[x]</a></span>");
                        cont.append(link);
                        \$this.append(cont);
                        link.on('click', function(e){
                            e.preventDefault();
                            var id = $(this).closest('div').attr('id').substr(16);
                            $.get("{$root}/misc/notification/d/"+id, function(){
                                    $('#am-notification-'+id).hide(options.animation);
                                    $('#am-notification-'+id).remove();
                                });
                        });
                    }
                });
                
            });
        }
    })(jQuery);
CUT;
    }

    function onGetPermissionsList(Am_Event $event)
    {
        $event->addReturn(___('Can Operate with Notifications'), self::ADMIN_PERM_ID);
    }

    function onInitFinished(Am_Event $e)
    {
        $this->getDi()->blocks->add(new Am_Block('member/main/top', null, 'notification', null, array($this, 'renderNotification')));
    }

    function init()
    {
        parent::init();

        $front = Zend_Controller_Front::getInstance();
        $front->getRouter()->addRoute('notification', new Zend_Controller_Router_Route(
            'misc/notification/:action/:id', array(
            'module' => 'default',
            'controller' => 'direct',
            'action' => 'index',
            'type' => 'misc',
            'plugin_id' => 'notification'
            )
        ));
    }

    function getReadme()
    {
        $root = ROOT_URL;
        $script = <<<EOT
<div id="am-notifications"></div>
<script>
jQuery(document).ready(function() {
  $.getScript('{$root}/misc/notification/js', function(){
   $('#am-notifications').amNotifications();
  });
});        
</script>
EOT;
        $script = htmlentities($script);

        return <<<CUT
Notifications can be placed outside of aMember; 
In ordert to do this you should include this javascript on your page: 
        
{$script}


Above script uses jQuery library so make sure that jQuery is loaded in the header of your page.
In order to modify notifications look you can create CSS class which will be used instead of default styles: 
$('#am-notifications').amNotifications({class: 'class-for-notification'});
CUT;
    }

}

class Am_Grid_Action_NotificationPreview extends Am_Grid_Action_Abstract
{

    protected
        $type = Am_Grid_Action_Abstract::SINGLE;

    public
        function run()
    {
        $f = $this->createForm();
        $f->setDataSources(array($this->grid->getCompleteRequest()));
        if ($f->isSubmitted() && $f->validate() && $this->process($f))
            return;
        echo $this->renderTitle();
        echo $f;
    }

    function process(Am_Form $f)
    {
        $vars = $f->getValue();
        $user = Am_Di::getInstance()->userTable->findFirstByLogin($vars['user']);
        if (!$user)
        {
            list($el) = $f->getElementsByName('user');
            $el->setError(___('User %s not found', $vars['user']));
            return false;
        }

        $item = $this->grid->getRecord();

        $view = Am_Di::getInstance()->view;
        $view->title = ___('Preview of Notification');
        $view->content = $item->render($view, $user);

        echo $view->render('layout.phtml');
        exit;
    }

    protected
        function createForm()
    {
        $f = new Am_Form_Admin;
        $f->addText('user')->setLabel(___('Enter username of existing user'))
            ->addRule('required');
        $f->addScript()->setScript(<<<CUT
$(function(){
    $("#user-0" ).autocomplete({
        minLength: 2,
        source: window.rootUrl + "/admin-users/autocomplete"
    });
});
CUT
        );
        $f->addSaveButton(___('Preview'));
        foreach ($this->grid->getVariablesList() as $k)
        {
            $kk = $this->grid->getId() . '_' . $k;
            if ($v = @$_REQUEST[$kk])
                $f->addHidden($kk)->setValue($v);
        }
        return $f;
    }

}

class Am_Grid_Action_Sort_Notification extends Am_Grid_Action_Sort_Abstract
{

    protected
        function setSortBetween($item, $after, $before)
    {
        $this->_simpleSort(Am_Di::getInstance()->notificationTable, $item, $after, $before);
    }

}

class AdminNotificationController extends Am_Controller_Grid
{

    public
        function init()
    {
        parent::init();
        include_once dirname(__FILE__) . "/../../controllers/AdminContentController.php";
    }

    public
        function preDispatch()
    {
        parent::preDispatch();
        $this->view->headScript()->appendFile(REL_ROOT_URL . "/application/default/views/public/js/resourceaccess.js");
    }

    public
        function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Plugin_Notification::ADMIN_PERM_ID);
    }

    public
        function clickAction()
    {
        $ds = new Am_Query($this->getDi()->notificationClickTable);
        $ds->leftJoin('?_user', 'u', 't.user_id=u.user_id')
            ->addField('COUNT(t.notification_click_id)', 'cnt')
            ->addField('u.*')
            ->addField("CONCAT(u.name_f, ' ', u.name_l)", 'name')
            ->groupBy('user_id', 'u')
            ->addWhere('notification_id=?', $this->getParam('id'));

        $grid = new Am_Grid_ReadOnly('_n_c', ___('Statistics'), $ds, $this->getRequest(), $this->getView(), $this->getDi());

        $userUrl = new Am_View_Helper_UserUrl();
        $grid->addField('login', ___('Username'))
            ->addDecorator(new Am_Grid_Field_Decorator_Link($userUrl->userUrl('{user_id}'), '_top'));
        $grid->addField('name', ___('Name'));
        $grid->addField('email', ___('E-Mail Address'));
        $grid->addField('cnt', ___('Clicks'), null, null, null, '1%');


        $grid->runWithLayout($this->layout);
    }

    public
        function createGrid()
    {
        $ds = new Am_Query($this->getDi()->notificationTable);
        $ds->leftJoin('?_notification_click', 'nc', 't.notification_id=nc.notification_id')
            ->addField('COUNT(nc.notification_click_id)', 'cnt')
            ->addOrder('sort_order');


        $grid = new Am_Grid_Editable('_notification', ___('Notifications'), $ds, $this->_request, $this->view, $this->getDi());
        $grid->setPermissionId(Am_Plugin_Notification::ADMIN_PERM_ID);
        $grid->addField('content', ___('Content'));
        $grid->addField('url', ___('Link'));
        $grid->addField(new Am_Grid_Field_Date('begin', ___('Begin')))
            ->setFormatDate();
        $grid->addField(new Am_Grid_Field_Date('expire', ___('Expire')))
            ->setFormatDate();
        $grid->addField('cnt', ___('Clicks'), true, null, null, '1%')
            ->setRenderFunction(array($this, 'renderClicks'));
        $grid->addField(new Am_Grid_Field_IsDisabled());
        $grid->setForm(array($this, 'createForm'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, array($this, 'valuesToForm'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, array($this, 'valuesFromForm'));

        $grid->actionAdd(new Am_Grid_Action_NotificationPreview('preview', ___('Preview')))->setTarget('_top');
        $grid->actionAdd(new Am_Grid_Action_Group_Delete());
        $grid->actionAdd(new Am_Grid_Action_Sort_Notification());
        $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_STATIC, array($this, 'renderStatic'));

        $grid->setFilter(new Am_Grid_Filter_Text(___('Filter by Content or Link'), array(
            'content' => 'LIKE',
            'url' => 'LIKE'
        )));

        $grid->setRecordTitle(___('Notification'));

        return $grid;
    }

    public
        function renderStatic(& $out)
    {
        $title = ___('Statistics');

        $out .= <<<CUT
<script type="text/javascript">
$(document).on('click','.notification-click', function(){
    var div = $('<div class="grid-wrap" id="grid-n_c"></div>');
    div.load(window.rootUrl + "/admin-notification/click/?id=" + $(this).data('notification_id'), function(){
        div.dialog({
            autoOpen: true
            ,width: 800
            ,buttons: {}
            ,closeOnEscape: true
            ,title: "$title"
            ,modal: true
            ,open: function(){
                div.ngrid();
            }
        });
    });
})
</script>
CUT;
    }

    public
        function renderClicks($rec)
    {
        return $rec->cnt ?
            sprintf('<td style="text-align: center"><a href="javascript:;" class="local notification-click" data-notification_id="%d">%d</a></td>', $rec->pk(), $rec->cnt) :
            '<td></td>';
    }

    public
        function valuesToForm(array & $values, Notification $record)
    {
        foreach ((array) $record->_access as $access)
        {
            switch ($access->fn)
            {
                case 'free' :
                    $values['_target'][] = 'free';
                    break;
                case 'user_id' :
                    $values['_target'][] = 'user_id';
                    $ids = array();
                    foreach ($record->_access as $r)
                        if ($r->fn == 'user_id')
                            $ids[] = $r->id;
                    $users = array();
                    foreach ($this->getDi()->userTable->loadIds($ids) as $user)
                    {
                        $users[] = $user->login;
                    }

                    $values['_savedLoginOrEmail'] = implode(',', $users);
                    break;
                case 'user_group_id' :
                    $values['_access'][] = $access;
                case 'product_id':
                case 'product_category_id':
                    $values['_raccess'][] = $access;

                    break;
            }
        }
    }

    public
        function valuesFromForm(array & $values, Notification $record)
    {
        $access = array();

        foreach ($values['_target'] as $v)
        {
            switch ($v)
            {
                case 'free' :
                    $rec = $this->getDi()->notificationAccessRecord;
                    $rec->fn = 'free';
                    $rec->id = null;
                    $access[] = $rec;
                    break;
                case 'user_id' :
                    $rec = $this->getDi()->notificationAccessRecord;
                    $user_ids = array();
                    foreach ($_REQUEST['_loginOrEmail'] as $loginOrEmail)
                    {
                        $user = $this->getDi()->userTable->findFirstByLogin($loginOrEmail);
                        if (!$user)
                            $user = $this->getDi()->userTable->findFirstByEmail($loginOrEmail);

                        if ($user)
                            $user_ids[] = $user->pk();
                    }
                    $user_ids = array_filter($user_ids);
                    foreach ($user_ids as $uid)
                    {
                        $rec = $this->getDi()->notificationAccessRecord;
                        $rec->fn = 'user_id';
                        $rec->id = $uid;
                        $access[] = $rec;
                    }
                    break;
                default:
                    preg_match('/(user_group_id)-([0-9]+)/i', $v, $match);
                    $rec = $this->getDi()->notificationAccessRecord;
                    $rec->fn = $match[1];
                    $rec->id = $match[2];
                    $access[] = $rec;
            }
        }
        foreach (array(
        'product_id' => ResourceAccess::FN_PRODUCT,
        'product_category_id' => ResourceAccess::FN_CATEGORY)
        as $key => $rtype)
        {

            if (!empty($values['_raccess'][$key]))
            {
                foreach ($values['_raccess'][$key] as $id => $params)
                {
                    if (!is_array($params))
                        $params = json_decode($params, true);

                    $fa = $this->getDi()->notificationAccessRecord;
                    $fa->fn = $rtype;
                    $fa->id = $id;
                    $fa->start_days = null;
                    $fa->stop_days = null;
                    if (preg_match('/^(-?\d+)(\w+)$/', strtolower($params['start']), $regs))
                    {
                        $fa->start_days = $regs[1];
                    }
                    if (preg_match('/^(-?\d+)(\w+)$/', strtolower($params['stop']), $regs))
                    {
                        $fa->stop_days = $regs[1];
                    }
                    $access[] = $fa;
                }
            }
        }

        if (!$values['expire'])
            $values['expire'] = null;
        if (!$values['begin'])
            $values['begin'] = null;
        if (!$values['url'])
            $values['url'] = null;
        if (!$values['limit'])
            $values['limit'] = null;

        $record->_access = $access;
    }

    function createForm()
    {
        $form = new Am_Form_Admin();

        $form->addAdvRadio('is_custom')
            ->loadOptions(array(
                0 => ___('Use Pre-Defined Template'),
                1 => ___('Define Custom Html Message')
            ))->setValue(0);

        $form->addTextarea('content', array('rows' => '7', 'class' => 'row-wide el-wide'))
            ->setLabel(___("Content\n" .
                    'You can use all user specific placeholders here eg. %user.login%, %user.name_f%, %user.name_l% etc.'))
            ->addRule('required');

        $form->addText('url', array('class' => 'el-wide', 'rel' => 'form-pre-defined'))
            ->setLabel(___('Link'));

        $form->addAdvcheckbox('is_blank', array('rel' => 'form-pre-defined'))
            ->setLabel(___('Open Link in New Window'));

        $form->addScript()
            ->setScript(<<<CUT
$('[name=is_custom]').change(function(){
    $('[rel=form-pre-defined]').closest('.row').toggle($('[name=is_custom]:checked').val() == 0)
}).change();
CUT
        );

        $sel = $form->addMagicSelect('_target')
            ->setLabel(___('Target By User'));

        $cats = $pr = $gr = array();

        foreach ($this->getDi()->userGroupTable->getSelectOptions() as $k => $v)
            $gr['user_group_id-' . $k] = 'Group: ' . $v;


        $options = array(
            'free' => ___('All'),
            'user_id' => ___('Specific User')
            ) + ($cats ? array(___('Product Categories') => $cats) : array()) + ($gr ? array(___('User Groups') => $gr) : array()) + ($pr ? array(___('Products') => $pr) : array());

        $sel->loadOptions($options);
        //$sel->addRule('required');

        $sel->setJsOptions('{onChange:function(val){
                $("input[name^=_loginOrEmail]").closest(\'.row\').toggle(val.hasOwnProperty("user_id"));
        }}');

        $loginGroup = $form->addGroup('');
        $loginGroup
            ->setLabel(___('E-Mail Address or Username'));
        $loginGroup->addHidden('_savedLoginOrEmail')->setValue('');
        $login = $loginGroup->addText('_loginOrEmail[]');

        $label_add_user = ___('Add User');
        $loginGroup->addHtml()
            ->setHtml(<<<CUT
<div><a href="javascript:void(null);" id="target-user_id-add" class="local">$label_add_user</a></div>
CUT
        );

        $form->addElement(new Am_Form_Element_ResourceAccess('_raccess', array('without_free' => true)))->setLabel('Target By Product');


        $gr = $form->addGroup()
            ->setSeparator(' ')
            ->setLabel(___("Dates\n" .
                'date range when notification is shown'));

        $gr->addDate('begin', array('placeholder' => ___('Begin Date')));
        $gr->addDate('expire', array('placeholder' => ___('Expire Date')));

        $form->addScript('script')->setScript(<<<CUT
$("input[name^=_loginOrEmail]").autocomplete({
        minLength: 2,
        source: window.rootUrl + "/admin-users/autocomplete"
});
CUT
        );

        $delIcon = $this->getDi()->view->icon('delete');

        $form->addScript('script2')->setScript(<<<CUT

var arr = $('[name=_savedLoginOrEmail]').val().split(',').reverse();
$('[name^=_loginOrEmail]').val(arr.pop());
for (var i in arr) {
    var \$field = addEmailOrLogin($('#target-user_id-add'));
    \$field.val(arr[i]);
}

function addEmailOrLogin(context) {
    var \$field = $('<input tyep="text" name="_loginOrEmail[]" />');
    $(context).before('<br />');
    $(context).before(\$field);
    $(context).before('<a href="javascript:void(null)" onclick="$(this).prev().remove();$(this).prev().remove();$(this).next().remove();$(this).remove()">$delIcon</a>');
    $(context).before('<br />');
    \$field.autocomplete({
        minLength: 2,
        source: window.rootUrl + "/admin-users/autocomplete"
    });
    return \$field;
}

$('#target-user_id-add').click(function(){
    addEmailOrLogin(this);
})
CUT
        );

        $form->addText('limit', array('placeholder' => ___('Unlimited')))
            ->setLabel(___("Limit Number of Display per User\n" .
                    'keep it empty for unlimited'));

        return $form;
    }

}

class NotificationTable extends Am_Table
{

    protected
        $_table = '?_notification';
    protected
        $_recordClass = 'Notification';
    protected
        $_key = 'notification_id';

    function getNotificationsForUser(User $user)
    {
        return $this->getDi()->notificationAccessTable->getNotificationsForUser($user);
    }

}

/**
 * @property int $notification_id
 * @property string $content
 * @property string $url
 * @property string $fn
 * @property int $id
 * @property string $expire
 */
class Notification extends Am_Record
{

    function __get($name)
    {
        if (!$this->isLoaded())
            return null;
        if ($name == '_access')
        {
            $this->_access = $this->getDi()->notificationAccessTable->findBy(array('notification_id' => $this->pk()));
            return $this->_access;
        }
        throw new Am_Exception_InternalError('No variable ' . $name . ' defined in ' . __CLASS__);
    }

    function update()
    {
        $ret = parent::update();
        $this->saveAccess($this->_access);
        return $ret;
    }

    public
        function insert($reload = true)
    {
        $table_name = $this->getTable()->getName();
        $this->getAdapter()->query("UPDATE {$table_name}
            SET sort_order=sort_order+1");

        $this->sort_order = 1;
        $ret = parent::insert($reload);
        $this->saveAccess($this->_access);

        return $ret;
    }

    public
        function delete()
    {
        $ret = parent::delete();
        $this
            ->deleteFromRelatedTable('?_notification_access')
            ->deleteFromRelatedTable('?_notification_click');

        $table_name = $this->getTable()->getName();
        $this->getAdapter()->query("UPDATE {$table_name}
            SET sort_order=sort_order-1
            WHERE sort_order>?", $this->sort_order);
        return $ret;
    }

    protected
        function saveAccess($access)
    {
        if (!$this->isLoaded())
            return;
        $this->getDi()->notificationAccessTable->deleteBy(array('notification_id' => $this->pk()));
        foreach ($access as $v)
        {
            $v->notification_id = $this->pk();
            $v->insert();
        }
    }

}

class NotificationAccessTable extends ResourceAccessTable
{

    protected
        $_table = '?_notification_access';
    protected
        $_recordClass = 'NotificationAccess';
    protected
        $_key = 'notification_access_id';

    function getNotificationsByTargetUser(User $user)
    {
        $user_group_ids = $user->getGroups();
        array_push($user_group_ids, '-1'); //to avoide SQL error

        return $this->getDi()->db->selectCol("SELECT notification_id FROM ?_notification WHERE
            notification_id IN (
                SELECT notification_id FROM ?_notification_access WHERE
                    ((fn=? AND id =  ?) OR
                    (fn=?)) OR
                    (fn=? AND id IN (?a))
            )
            AND
                (begin IS NULL OR begin<=?) AND
                (expire IS NULL OR expire>=?) AND
                is_disabled=0
            ", 'user_id', $user->pk(), 'free', 'user_group_id', $user_group_ids, sqlDate('now'), sqlDate('now'));
    }

    function getNotificationsByTargetProduct(User $user)
    {
        return $this->getDi()->db->selectCol("SELECT notification_id 
            FROM  ?_notification n
            WHERE n.notification_id in (
                SELECT DISTINCT r.notification_id 
                FROM ?_notification_access r 
                LEFT JOIN ?_access_cache c 
                ON 
                    c.user_id=? 
                    AND (((c.fn = r.fn) AND (c.id = r.id)) OR (r.fn='product_category_id' AND r.id=-1)) 
                    AND ((c.status='active' AND r.start_days IS NULL AND r.stop_days IS NULL) 
                         OR
                         (c.status='active' AND c.days BETWEEN IFNULL(r.start_days,0) AND IFNULL(r.stop_days, 90000)) 
                         OR
                         (c.days >= IFNULL(r.start_days,0) AND r.stop_days = -1)) 
                WHERE c.user_id IS NOT NULL)
            AND  (n.begin IS NULL OR n.begin<=?) AND (n.expire IS NULL OR n.expire>=?) AND is_disabled=0
           ", $user->pk(), sqlDate('now'), sqlDate('now'));
    }

    function getNotificationsForUser(User $user)
    {
        $ids = array_filter(
            array_unique(
                array_merge(
                    $a = $this->getNotificationsByTargetUser($user), $b = $this->getNotificationsByTargetProduct($user)
                )
            )
        );
        return $this->getDi()->notificationTable->selectObjects("select * from ?_notification where notification_id in (?a) order by sort_order", $ids);
    }

}

/**
 * @property int $notification_id
 * @property string $content
 * @property string $url
 * @property string $fn
 * @property int $id
 * @property string $expire
 */
class NotificationAccess extends ResourceAccess
{
    
}

class NotificationClickTable extends Am_Table
{

    protected
        $_table = '?_notification_click';
    protected
        $_key = 'notification_click_id';
    protected
        $_recordClass = 'NotificationClick';

    function log(Am_Record $user, Am_Record $notification)
    {
        $this->_db->query("INSERT INTO ?_notification_click SET ?a", array(
            'user_id' => $user->pk(),
            'dattm' => sqlDate('now'),
            'notification_id' => $notification->pk()
        ));
    }

}

class NotificationClick extends Am_Record
{
    
}
