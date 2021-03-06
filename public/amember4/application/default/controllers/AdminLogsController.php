<?php

class AdminLogsController extends Am_Controller_Pages
{
    public function initPages()
    {
        $admin = $this->getDi()->authAdmin->getUser();

        if ($admin->hasPermission(Am_Auth_Admin::PERM_LOGS))
            $this->addPage(array($this,'createErrors'), 'errors', ___('Errors'));

        if ($admin->hasPermission(Am_Auth_Admin::PERM_LOGS_ACCESS))
             $this->addPage(array($this, 'createAccess'), 'access', ___('Access'));

        if ($admin->hasPermission(Am_Auth_Admin::PERM_LOGS_INVOICE))
             $this->addPage(array($this, 'createInvoice'), 'invoice', ___('Invoice'));

        if ($admin->hasPermission(Am_Auth_Admin::PERM_LOGS_MAIL))
             $this->addPage(array($this, 'createMailQueue'), 'mailqueue', ___('Mail Queue'));

        if ($admin->hasPermission(Am_Auth_Admin::PERM_LOGS_ADMIN))
            $this->addPage(array($this, 'createAdminLog'), 'adminlog', ___('Admin Log'));
    }
    ///
    public function checkAdminPermissions(Admin $admin)
    {
        foreach(array(
            Am_Auth_Admin::PERM_LOGS,
            Am_Auth_Admin::PERM_LOGS_ACCESS,
            Am_Auth_Admin::PERM_LOGS_INVOICE,
            Am_Auth_Admin::PERM_LOGS_MAIL,
            Am_Auth_Admin::PERM_LOGS_ADMIN
            ) as $perm) {
            if ($admin->hasPermission($perm)) return true;
        }

        return false;
    }
    public function createErrors()
    {
        $q = new Am_Query($this->getDi()->errorLogTable);
        $q->setOrder('time', 'desc');
        $g = new Am_Grid_ReadOnly('_error', ___('Error/Debug Log'), $q, $this->getRequest(), $this->view);
        $g->setPermissionId(Am_Auth_Admin::PERM_LOGS);
        $g->addField(new Am_Grid_Field_Date('time', ___('Date/Time')));
        $g->addField(new Am_Grid_Field_Expandable('url', ___('URL'), true))
            ->setPlaceholder(Am_Grid_Field_Expandable::PLACEHOLDER_SELF_TRUNCATE_BEGIN)
            ->setMaxLength(25);
        $g->addField(new Am_Grid_Field('remote_addr', ___('IP')));
        $g->addField(new Am_Grid_Field('error', ___('Message'), true, '', null, '45%'));
        $f = $g->addField(new Am_Grid_Field_Expandable('trace', ___('Trace')))
             ->setAjax(REL_ROOT_URL . '/admin-logs/get-trace?id={log_id}');

        $g->setFilter(new Am_Grid_Filter_Text(___('Filter'), array(
            'url' => 'LIKE',
            'remote_addr' => 'LIKE',
            'referrer' => 'LIKE',
            'error' => 'LIKE',
        )));
        return $g;
    }
    function getTraceAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission(Am_Auth_Admin::PERM_LOGS);
        $log = $this->getDi()->errorLogTable->load($this->getParam('id'));
        echo highlight_string($log->trace, true);
    }

    public function createAccess()
    {
        $query = new Am_Query($this->getDi()->accessLogTable);
        $query->leftJoin('?_user', 'm', 't.user_id=m.user_id')
            ->addField("m.login", 'member_login')
            ->addField("CONCAT(m.name_f, ' ', m.name_l)", 'member_name');
        $query->setOrder('time', 'desc');
        $g = new Am_Grid_Editable('_access', ___('Access Log'), $query, $this->getRequest(), $this->view);
        $g->setPermissionId(Am_Auth_Admin::PERM_LOGS_ACCESS);
        $g->actionsClear();
        $g->addField(new Am_Grid_Field_Date('time', ___('Date/Time')));
        $g->addField(new Am_Grid_Field('member_login', ___('User'), true, '', array($this, 'renderAccessMember')));
        $g->addField(new Am_Grid_Field_Expandable('url', ___('URL')))
            ->setPlaceholder(Am_Grid_Field_Expandable::PLACEHOLDER_SELF_TRUNCATE_BEGIN)
            ->setMaxLength(25);
        $g->addField(new Am_Grid_Field('remote_addr', ___('IP')));
        $g->addField(new Am_Grid_Field_Expandable('referrer', ___('Referrer')))
            ->setPlaceholder(Am_Grid_Field_Expandable::PLACEHOLDER_SELF_TRUNCATE_BEGIN)
            ->setMaxLength(25);
        $g->setFilter(new Am_Grid_Filter_Text(___('Filter by IP or Referrer or URL'), array(
            'remote_addr' => 'LIKE',
            'referrer' => 'LIKE',
            'url' => 'LIKE',
        )));
        $g->actionAdd(new Am_Grid_Action_Export);
        return $g;
    }
    public function createInvoice()
    {
        $query  = new Am_Query(new InvoiceLogTable);
        $query->addField("m.login", "login");
        $query->addField("m.user_id", "user_id");
        $query->addField("i.public_id");
        $query->leftJoin("?_user", "m", "t.user_id=m.user_id");
        $query->leftJoin("?_invoice", "i", "t.invoice_id=i.invoice_id");
        $query->setOrder('log_id', 'desc');

        $g = new Am_Grid_Editable('_invoice', ___('Invoice Log'), $query, $this->getRequest(), $this->view);
        $g->setPermissionId(Am_Auth_Admin::PERM_LOGS_INVOICE);

        $userUrl = new Am_View_Helper_UserUrl();

        $g->addField(new Am_Grid_Field_Date('tm', ___('Date/Time')));
        $g->addField(new Am_Grid_Field('invoice_id', ___('Invoice'), true, '', array($this, 'renderInvoice')));
        $g->addField(new Am_Grid_Field('login', ___('User')))
            ->addDecorator(new Am_Grid_Field_Decorator_Link($userUrl->userUrl('{user_id}'), '_top'));
        $g->addField(new Am_Grid_Field('remote_addr', ___('IP')));
        $g->addField(new Am_Grid_Field('paysys_id', ___('Paysystem')));
        $g->addField(new Am_Grid_Field('title', ___('Title')));
        $g->addField(new Am_Grid_Field_Expandable('details', ___('Details'), false))
            ->setAjax(REL_ROOT_URL . '/admin-logs/get-invoice-details?id={log_id}');
        $g->actionsClear();
        $g->actionAdd(new Am_Grid_Action_InvoiceRetry('retry'));
        $g->setFilter(new Am_Grid_Filter_InvoiceLog);
        $g->actionAdd(new Am_Grid_Action_Group_Callback('retrygroup', ___("Repeat Action Handling"), array('Am_Grid_Action_InvoiceRetry', 'groupCallback')));
        return $g;
    }

    public function renderInvoice($record){
        return $record->invoice_id ?
                sprintf('<td><a class="link" target="_top" href="%s">%s/%s</a></td>',
                    $this->escape(REL_ROOT_URL . "/admin-user-payments/index/user_id/".$record->user_id."#invoice-".$record->invoice_id),
                    $record->invoice_id, $record->public_id) :
                    '<td>&mdash;</td>'
            ;
    }

    public function renderAccessMember($record)
    {
        return sprintf('<td><a class="link" target="_top" href="%s">%s (%s)</a></td>',
            $this->getView()->userUrl($record->user_id), $record->member_login, $record->member_name);
    }

    public function renderRec(AdminLog $record)
    {
        $text = "";
        if ($record->tablename || $record->record_id)
            $text = $this->escape($record->tablename . ":" . $record->record_id);
        // @todo - add links here to edit pages
        return sprintf('<td>%s</td>', $text);
    }

    function getInvoiceDetailsAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission(Am_Auth_Admin::PERM_LOGS_INVOICE);
        $log = $this->getDi()->invoiceLogTable->load($this->getParam('id'));
        echo $this->renderInvoiceDetails($log);
    }

    public function renderInvoiceDetails(Am_Record $obj)
    {

        $ret = "";
        $ret .= "<div class='collapsible'>\n";
        $rows = $obj->getRenderedDetails();
        $open = count($rows) == 1 ? 'open' : '';
        foreach ($rows as $row)
        {
            $popup = @$row[2];
            if ($popup) $popup = "<br /><br />ENCODED DETAILS:<br />" . nl2br($row[2]);
            $ret .= "\t<div class='item $open'>\n";
            $ret .= "\t\t<div class='head'>$row[0]</div>\n";
            $ret .= "\t\t<div class='more'>$row[1]$popup</div>\n";
            $ret .= "\t</div>\n";
        }
        $ret .= "</div>\n\n";
        return $ret;
    }
    public function createMailQueue()
    {
        $ds = new Am_Query($this->getDi()->mailQueueTable);
        $ds->clearFields();
        $ds->addField('recipients')
            ->addField('added')
            ->addField('sent')
            ->addField('subject')
            ->addField('queue_id');
        $ds->setOrder('added', true);

        $g = new Am_Grid_Editable('_mail', ___("E-Mail Queue"), $ds, $this->getRequest(), $this->view);
        $g->setPermissionId(Am_Auth_Admin::PERM_LOGS_MAIL);
        $g->addField(new Am_Grid_Field('recipients', ___('Recipients'), true, '', null, '20%'));
        $g->addField(new Am_Grid_Field_Date('added', ___('Added'), true));
        $g->addField(new Am_Grid_Field_Date('sent', ___('Sent'), true));
        $g->addField(new Am_Grid_Field('subject', ___('Subject'), true, '', null, '30%'))
            ->setRenderFunction(array($this, 'renderSubject'));
        $g->addField(new Am_Grid_Field_Expandable('queue_id', ___('Mail'), false, '', null, '20%'))
            ->setAjax(REL_ROOT_URL . '/admin-logs/get-mail?id={queue_id}');

        $g->setFilter(new Am_Grid_Filter_Text(___("Filter by subject or recipient"), array(
            'subject' => 'LIKE',
            'recipients' => 'LIKE',
        )));
        $g->actionsClear();
        $g->actionAdd(new Am_Grid_Action_MailRetry('retry'));

        if ($this->getDi()->authAdmin->getUser()->isSuper()) {
            $g->actionAdd(new Am_Grid_Action_Delete);
            $g->actionAdd(new Am_Grid_Action_Group_Delete);
        }

        return $g;
    }

    function getMailAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission(Am_Auth_Admin::PERM_LOGS_MAIL);
        $mail = $this->getDi()->mailQueueTable->load($this->getParam('id'));
        echo $this->renderMail($mail);
    }

    function renderMail(Am_Record $obj)
    {
        $_body = $obj->body;
        $atRendered = null;

        $headersRendered = '';
        foreach (unserialize($obj->headers) as $headerName => $headerVal)
        {
            if (isset($headerVal['append'])) {
                unset($headerVal['append']);
                $headerVal = implode(',' . "\r\n" . ' ', $headerVal);
            } else {
                $headerVal = implode("\r\n", $headerVal);
            }

            $_headers[strtolower($headerName)] = $headerVal;
            if (strpos($headerVal, '=?') === 0)
                $headerVal = mb_decode_mimeheader($headerVal);

            $headersRendered .= '<strong>' . $headerName . '</strong>: <em>' . nl2br(Am_Controller::escape($headerVal)) . '</em><br />';
        }

        $part = new Zend_Mail_Part(array(
            'headers' => $_headers,
            'content' => $_body
        ));

        $canHasAttacments = false;

        list($type) = explode(";", $part->getHeader('content-type'));
        if ($type == 'multipart/alternative') {
            $msgPart = $part->getPart(2);
        } else {
            $msgPart = $part->isMultipart() ? $part->getPart(1) : $part;
            if ($msgPart->isMultipart()) {
                $msgPart = $msgPart->getPart(2); //html part
            }
            $canHasAttacments = true;
        }

        list($type) = explode(";", $msgPart->getHeader('content-type'));
        $encoding = $msgPart->getHeader('content-transfer-encoding');

        $content = $msgPart->getContent();
        if ($encoding && $encoding == 'quoted-printable') {
            $content = quoted_printable_decode($content);
        } else {
            $content = base64_decode($content);
        }

        switch ($type) {
            case 'text/plain':
                $bodyRendered = nl2br(Am_Controller::escape($content));
                break;
            case 'text/html':
                $content = preg_replace('#^.*<body.*?>#mi', '', $content);
                $content = preg_replace('#</body>.*$#mi', '', $content);
                $bodyRendered = $content;
                break;
        }

        //attachments
        $atRendered = '';
        if ($canHasAttacments) {
            if ($part->isMultipart()) {
                for ($i=2; $i<=$part->countParts(); $i++) {
                    $attPart = $part->getPart($i);

                    preg_match('/filename="(.*)"/', $attPart->{'content-disposition'}, $matches);
                    $filename = @$matches[1];
                    $atRendered .= sprintf("&ndash; %s (<em>%s</em>)", $filename, $attPart->{'content-type'}) . '<br />';
                }
            }
        }

        $attachTitle = ___('Attachments');
        return $headersRendered .
            '<br />' .
            $bodyRendered .
            ($atRendered ? '<br /><strong>' . $attachTitle . '</strong>:<br />' . $atRendered : '');
    }

    function renderSubject(Am_Record $m)
    {
        $s = $m->subject;
        if (strpos($s, '=?') === 0)
            $s = mb_decode_mimeheader($s);
        return "<td>". Am_Controller::escape($s) . "</td>";
    }

    public function createAdminLog()
    {
        $ds = new Am_Query($this->getDi()->adminLogTable);
        $ds->setOrder('dattm', 'desc');

        $g = new Am_Grid_ReadOnly('_admin', ___('Admin Log'), $ds, $this->getRequest(), $this->view);
        $g->setPermissionId(Am_Auth_Admin::PERM_LOGS_ADMIN);
        $g->addField(new Am_Grid_Field_Date('dattm', ___('Date/Time'), true));
        $g->addField(new Am_Grid_Field('admin_login', ___('Admin'), true))
            ->addDecorator(new Am_Grid_Field_Decorator_Link(REL_ROOT_URL . "/admin-admins?_admin_a=edit&_admin_id={admin_id}", '_top'));
        $g->addField(new Am_Grid_Field('ip', ___('IP'), true, '', null, '10%'));
        $g->addField(new Am_Grid_Field('message', ___('Message')));
        $g->addField(new Am_Grid_Field('record', ___('Record')))->setRenderFunction(array($this, 'renderRec'));

        $g->setFilter(new Am_Grid_Filter_AdminLog);
        return $g;
    }
}

class Am_Grid_Filter_InvoiceLog extends Am_Grid_Filter_Abstract
{
    public function __construct()
    {
        $this->title = ___("Filter by string or by invoice#/member#");
    }
    protected function applyFilter()
    {
        $query = $this->grid->getDataSource();
        $filter = $this->vars['filter'];
        $condition = $query->add(new Am_Query_Condition_Field('paysys_id', 'LIKE', '%' . $filter . '%'))
            ->_or(new Am_Query_Condition_Field('title', 'LIKE', '%' . $filter . '%'))
            ->_or(new Am_Query_Condition_Field('type', 'LIKE', '%' . $filter . '%'))
            ->_or(new Am_Query_Condition_Field('details', 'LIKE', '%' . $filter . '%'));
        if ($filter > 0)
        {
            $condition->_or(new Am_Query_Condition_Field('invoice_id', '=', (int)$filter));
            $condition->_or(new Am_Query_Condition_Field('user_id', '=', (int)$filter));
        }
    }
    public function renderInputs()
    {
        return $this->renderInputText();
    }
}

class Am_Grid_Filter_AdminLog extends Am_Grid_Filter_Abstract
{
    public function __construct()
    {
        $this->title = ___("Filter by record_id or by message");
    }
    protected function applyFilter()
    {
        $query = $this->grid->getDataSource();
        $filter = $this->vars['filter'];
        $condition = $query->add(new Am_Query_Condition_Field('message', 'LIKE', '%' . $filter . '%'))
            ->_or(new Am_Query_Condition_Field('record_id', 'LIKE', '%' . $filter . '%'));
    }
    public function renderInputs()
    {
        return $this->renderInputText();
    }
}

class Am_Grid_Action_InvoiceRetry extends Am_Grid_Action_Abstract
{
    protected $type = self::SINGLE;

    public function __construct($id = null, $title = null)
    {
        $this->title = ___('Repeat Action Handling');
        parent::__construct($id, $title);
        $this->setTarget('_top');
    }

    public function isAvailable($record)
    {
        return (strpos($record->details, 'type="incoming-request"') !== false);
    }

    public static function repeat(InvoiceLog $invoiceLog, array & $response)
    {
        Am_Di::getInstance()->plugins_payment->load($invoiceLog->paysys_id);
        $paymentPlugin = Am_Di::getInstance()->plugins_payment->get($invoiceLog->paysys_id);
        $paymentPlugin->toggleDisablePostbackLog(true);
        /* @var $paymentPlugin Am_Paysystem_Abstract */
        try
        {
            $request = $invoiceLog->getFirstRequest();
            if (!$request instanceof Am_Request)
                throw new Am_Exception_InputError('Am_Request is not saved for this record, this action cannot be repeated');
            $resp = new Zend_Controller_Response_Http();

            Zend_Controller_Front::getInstance()->getRouter()->route($request);

            $paymentPlugin->toggleDisablePostbackLog(true);
            $paymentPlugin->directAction($request, $resp, array('di' => Am_Di::getInstance()));

            $response['status'] = 'OK';
            $response['msg'] = ___('The action has been repeated, ipn script response [%s]', $resp->getBody());
        } catch (Exception $e)
        {
            $response['status'] = 'ERROR';
            $response['msg'] = sprintf("Exception %s : %s", get_class($e), $e->getMessage());
        }
    }


    public function run()
    {
        echo $this->renderTitle();
        $invoiceLog = Am_Di::getInstance()->invoiceLogTable->load($this->getRecordId());

        $response = array();
        try
        {
            self::repeat($invoiceLog, $response);
        } catch (Exception $e)
        {
            $response['status'] = 'ERROR';
            $response['msg'] = $e->getMessage();
        }

        echo "<b>RESULT: $response[status]</b><br />";
        echo $response['msg'];
        echo "<br /><br />\n";
        echo $this->renderBackUrl();
    }

    static function groupCallback($id, InvoiceLog $record, Am_Grid_Action_Group_Callback $action, Am_Grid_Editable $grid)
    {
        @set_time_limit(3600);
        try {
            $req = $record->getFirstRequest();
            if (!$req)
            {
                echo "<br />\n$record->log_id: SKIPPED";
                return;
            }
            $response = array();
            self::repeat($record, $response);
        } catch (Exception $e) {
            $response['status'] = 'Error';
            $response['msg'] = $e->getMessage();
        }
        echo "<br />\n$record->log_id: {$response['status']} : {$response['msg']}";
    }
}

class Am_Grid_Action_MailRetry extends Am_Grid_Action_Abstract
{
    protected $type = self::SINGLE;

    public function __construct($id = null, $title = null)
    {
        $this->title = ___('Resend Email');
        parent::__construct($id, $title);
        $this->setTarget('_top');
    }

    public function isAvailable($record)
    {
        return !$record->sent;
    }


    public function run()
    {
        echo $this->renderTitle();
        $record = Am_Di::getInstance()->mailQueueTable->load($this->getRecordId());
        $row = $record->toArray();

        $response = array();
        try
        {
            Am_Mail_Queue::getInstance()->getTransport()
                ->sendFromSaved($row['from'], $row['recipients'],
                    $row['body'], unserialize($row['headers']), $row['subject']);
            $row['sent'] = Am_Di::getInstance()->time;
            Am_Di::getInstance()->db->query("UPDATE ?_mail_queue SET sent=?d WHERE queue_id=?d",
                $row['sent'], $row['queue_id']);

            $response['status'] = 'OK';
            $response['msg'] = ___('Email has been send');

        } catch (Exception $e) {
            $response['status'] = 'ERROR';
            $response['msg'] = $e->getMessage();
        }

        echo "<b>RESULT: $response[status]</b><br />";
        echo $response['msg'];
        echo "<br /><br />\n";
        echo $this->renderBackUrl();
    }
}