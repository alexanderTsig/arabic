<?php

class Am_Plugin_SingleLoginSession extends Am_Plugin
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_COMM = self::COMM_FREE;
    const PLUGIN_REVISION = '4.4.2';
    protected $_configPrefix = 'misc.';

    const ACTION_LOGIN_REJECT = 0;
    const ACTION_LOGOUT_OTHER = 1;
    const ACTION_NOTHING = 3;

    public static function activate($id, $pluginType)
    {
        try {
            Am_Di::getInstance()->db->query("CREATE TABLE ?_login_session (
                login_session_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                remote_addr varchar(39) NOT NULL,
                session_id varchar(255) NOT NULL,
                modified datetime NOT NULL,
                need_logout TINYINT NOT NULL
                ) CHARACTER SET utf8 COLLATE utf8_general_ci");
        } catch (Am_Exception_Db $e) {

        }

        try {
            Am_Di::getInstance()->db->query("CREATE INDEX user_id ON ?_login_session
                (user_id)");
        } catch (Am_Exception_Db $e) {

        }

        $name = 'misc.single-login-session.notify_admin';
        $body = <<<CUT

Simultaneous login detected for the following user.

Login: %user.login%
Name: %user.name_f% %user.name_l%
Email: %user.email%

--
Best Regards,
%site_title%
%root_url%
CUT;
        $di = Am_Di::getInstance();
        $cnt = $di->db->selectCell("SELECT COUNT(*) AS cnt FROM ?_email_template WHERE name = ?", $name);
        if (!$cnt) {
            $di->db->query("INSERT INTO ?_email_template (name,lang,format,subject,txt) VALUES (?,?,?,?,?)",
                $name, 'en', 'text', 'Simultaneous login detected [%user.login%]', $body);
        }

    }

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->setTitle(___('Single Login Session'));
        $form->addText('timeout', array('size'=>4))
            ->setLabel(___('Session Timeout, min'));

        $form->setDefault('timeout', 5);

        $form->addSelect('action')
            ->setId('form-action')
            ->setLabel(___('Action on Simultaneous Login Attempt'))
            ->loadOptions(array(
                self::ACTION_LOGIN_REJECT => ___('Show error and do not allow to login until session timeout'),
                self::ACTION_LOGOUT_OTHER => ___('Delete other session when user try to login from new one'),
                self::ACTION_NOTHING => ___('Nothing, allow simultaneous login for same user from different computers')
            ));

        $form->addTextarea('error', array('class' => 'el-wide'))
            ->setId('form-error')
            ->setLabel(___('Error Message'));

        $form->setDefault('error', 'There is already exits active login session for your account. Simultaneous login from different computers is not allowed.');

        $error = self::ACTION_LOGIN_REJECT;
        $form->addScript()
            ->setScript(<<<CUT
$('#form-action').change(function(){
    $('#form-error').closest('.row').toggle($(this).val() == '$error')
}).change();
CUT
        );

        $form->addElement('email_checkbox', 'notify_admin')
            ->setLabel(___('Notify Admin on Simultaneous Login'));
    }

    function onAuthAfterLogout(Am_Event_AuthAfterLogout $event)
    {
        $user = $event->getUser();
        $this->getDi()->loginSessionTable->deleteBy(array(
            'user_id' => $user->pk(),
            'session_id' => Zend_Session::getId()
        ));
    }

    function onAuthAfterLogin(Am_Event_AuthAfterLogin $event)
    {
        $user = $event->getUser();
        $this->getDi()->loginSessionTable->insert(array(
            'user_id' => $user->pk(),
            'session_id' => Zend_Session::getId(),
            'need_logout' => 0,
            'modified' => sqlTime('now'),
            'remote_addr' => $_SERVER['REMOTE_ADDR']
        ));
    }

    function onAuthCheckUser(Am_Event $event)
    {
        /* @var $user User */
        $user = $event->getUser();

        $recs = $this->getDi()->loginSessionTable->findBy(array(
                'user_id' => $user->pk(),
                'session_id' => '<>' . Zend_Session::getId(),
                'modified' => '>' . sqlTime(sprintf('-%d minutes', $this->getConfig('timeout', 5))),
                'need_logout' => 0));

        if ($recs) {
            switch ($this->getConfig('action', self::ACTION_LOGIN_REJECT)) {
                case self::ACTION_LOGIN_REJECT:
                    $event->setReturn(new Am_Auth_Result(-100, $this->getConfig('error', 'There is already exits active login session for your account.
                            Simultaneous login from different computers is not allowed.')));
                    $event->stop();
                    break;
                case self::ACTION_LOGOUT_OTHER :
                    foreach ($recs as $rec)
                        $rec->updateQuick('need_logout', 1);
                    break;
               case self::ACTION_NOTHING :
                   break;
            }
            if ($this->getConfig('notify_admin') &&
                !$this->getDi()->store->get('single-login-session-detected-' . $user->pk())) {

                $this->getDi()->store->set('single-login-session-detected-' . $user->pk(), 1, '+20 minutes');

                if ($et = Am_Mail_Template::load('misc.single-login-session.notify_admin')) {
                    $et->setUser($user);
                    $et->sendAdmin();
                }
            }
        }
    }

    function onInitFinished()
    {
        if ($user_id = $this->getDi()->auth->getUserId()) {
            $rec = $this->getDi()->loginSessionTable->findFirstBy(array(
                    'user_id' => $user_id,
                    'session_id' => Zend_Session::getId()
                ));

            if (!$rec) {
                $rec = $this->getDi()->loginSessionRecord;
                $rec->user_id = $user_id;
                $rec->session_id = Zend_Session::getId();
                $rec->need_logout = 0;
            }

            if ($rec->need_logout) {
                $rec->delete();
                $this->getDi()->auth->logout();
            } else {
                $rec->modified = sqlTime('now');
                $rec->remote_addr = $_SERVER['REMOTE_ADDR'];
                $rec->save();
            }
        }
    }

    function onDaily()
    {
        $this->getDi()->loginSessionTable->deleteBy(array(
            'modified' => '<' . sqlTime(sprintf('-%d minutes', $this->getConfig('timeout', 5)))
        ));
    }

    function onGridUserInitForm(Am_Event $event)
    {
        $user = $event->getGrid()->getRecord();
        if ($user->isLoaded()) {

            $recs = $this->getDi()->loginSessionTable->findBy(array(
                    'user_id' => $user->pk(),
                    'modified' => '>' . sqlTime(sprintf('-%d minutes', $this->getConfig('timeout', 5))),
                    'need_logout' => 0));

            if ($recs) {
                $ips = array();
                foreach ($recs as $r)
                    $ips[] = $r->remote_addr;

                $login = $event->getGrid()->getForm()->getElementById('login');

                $static = new Am_Form_Element_Html();
                $static->setHtml(sprintf('<div>%s</div>', ___('There is %d active login session for this
                    user from following IP address(es): %s', count($recs), implode(', ', $ips))));

                $login->getContainer()->insertBefore($static, $login);
            }
        }
    }

    function onGridUserOnBeforeRun(Am_Event $event)
    {
        /* @var $grid Am_Grid_Editable */
        $grid = $event->getGrid();

        /* @var $query Am_Query_User */
        $query = $grid->getDataSource();
        $date = sqlTime(sprintf('-%d minutes', $this->getConfig('timeout', 5)));
        $query->leftJoin('?_login_session', 'ls', 'u.user_id=ls.user_id')
            ->addField("SUM(IF(modified>'$date' AND need_logout=0, 1, 0))", 'login_session_cnt');


        $loginIndicator = new Am_Grid_Field('login_indicator', ___('Login Indicator'), false);
        $loginIndicator->setRenderFunction(array($this, 'renderLoginIndicator'));

        $action = $grid->actionGet('customize');
        $action->addField($loginIndicator);
    }

    function renderLoginIndicator(Am_Record $rec)
    {
        $res = $rec->last_login ?
            '<span style="background:#FFFFCF; color:#454430; padding:0.2em 0.5em; font-size:80%; border-radius:5px;">' . amDatetime($rec->last_login) . '<span>' :
            '<span style="background:#BA2727; color: white; padding:0.2em 0.5em; font-size:80%; border-radius:5px;">' . ___('Never') . '</span>';
        if ($rec->login_session_cnt)
            $res = '<span style="background:#488f37; color: white; padding:0.2em 0.5em; font-size:80%; border-radius:5px;">' . ___('Online') . (($rec->login_session_cnt > 1) ? sprintf(' (%d)', $rec->login_session_cnt) : '') . '</span>';
        return sprintf('<td>%s</td>', $res);
    }

    function getReadme()
    {
        return <<<CUT
This plugin allow only one login session per account. Several users will not be able to login simultaneously from different computers with same credentials.
CUT;
    }

}

class LoginSessionTable extends Am_Table
{

    protected $_table = '?_login_session';
    protected $_recordClass = 'LoginSession';
    protected $_key = 'login_session_id';

}

class LoginSession extends Am_Record
{

}