<?php

/**
 * @table paysystems
 * @id avangate
 * @title Avangate
 * @visible_link http://www.avangate.com/
 * @recurring none
 */
class Am_Paysystem_Avangate extends Am_Paysystem_Abstract
{

    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.7.0';

    protected $defaultTitle = 'Avangate';
    protected $defaultDescription = '';

    public function __construct(Am_Di $di, array $config)
    {

        parent::__construct($di, $config);
        foreach ($di->paysystemList->getList() as $k => $p)
        {
            if ($p->getId() == $this->getId())
                $p->setPublic(false);
        }
        $di->billingPlanTable->customFields()->add(
            new Am_CustomFieldText(
                'avangate_id',
                "Avangate product ID",
                ""
                , array(/* ,'required' */)
        ));
    }

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText("merchant_id")
            ->setLabel('Your Realex Merchant ID');
        $form->addPassword('secret')->setLabel('Secret key');
        $form->addAdvcheckbox('testing')->setLabel('Testing mode');
    }

    public function canAutoCreate()
    {
        return true;
    }

    protected function _afterInitSetupForm(Am_Form_Setup $form)
    {
        parent::_afterInitSetupForm($form);
        $form->removeElementByName($this->_configPrefix . $this->getId() . '.auto_create');
    }

    function getConfig($key = null, $default = null)
    {
        switch ($key)
        {
            case 'testing' : return false;
            case 'auto_create' : return true;
            default: return parent::getConfig($key, $default);
        }
    }

    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Avangate($this, $request, $response, $invokeArgs);
    }

    public function getRecurringType()
    {
        return self::REPORTS_NOT_RECURRING;
    }

    public function getSupportedCurrencies()
    {
        return array('USD');
    }

    public function getReadme()
    {
        $ipn = $this->getPluginUrl('ipn');
        $refund = $this->getPluginUrl('refund');
        return <<<CUT
    Please setup IPN URL to $ipn

    Also you need to enable option "authorized and approved orders"
CUT;

    }
}

class Am_Paysystem_Transaction_Avangate extends Am_Paysystem_Transaction_Incoming
{

    protected $_autoCreateMap = array(
        'name_f' => 'FIRSTNAME',
        'name_l' => 'LASTNAME',
        'email' => 'CUSTOMEREMAIL',
        'state' => 'STATE',
        'country' => 'COUNTRY',
        'zip' => 'ZIPCODE',
        'street' => 'ADDRESS1',
        'street2' => 'ADDRESS2',
        'city' => 'CITY',
        'user_external_id' => 'CUSTOMEREMAIL',
        'invoice_external_id' => 'REFNO',
    );

    public function autoCreateGetProducts()
    {
        $products = array();
        foreach ((array)$this->request->get('IPN_PID') as $l)
        {
            $pl = Am_Di::getInstance()->billingPlanTable->findFirstByData('avangate_id', $l);
            if (!$pl)
                continue;
            $p = $pl->getProduct();
            if ($p)
                $products[] = $p;
        }
        return $products;
    }

    public function findInvoiceId()
    {
        return $this->request->getFiltered('REFNO');
    }

    public function getUniqId()
    {
        return $this->request->getFiltered('REFNO');
    }

    public function validateSource()
    {
        $arr = array();
        foreach($this->request->getPost() as $k => $v)
        {
            if($k == 'HASH') continue;
            if(@is_array($v))
                $arr[] = $v[0];
            else
                $arr[] = $v;
        }
        $hash = hash_hmac('md5', $this->getstrforhash($arr), $this->getPlugin()->getConfig('secret'));
        return $hash == $this->request->get('HASH');
    }

    public function getAmount()
    {
        return moneyRound($this->request->get('IPN_TOTALGENERAL'));
    }

    public function validateStatus()
    {
        return $this->request->getFiltered('ORDERSTATUS') == 'COMPLETE';
    }

    public function validateTerms()
    {
        return true;
    }

    public function processValidated()
    {
        try{
            parent::processValidated();
        }
        catch(Am_Exception_Paysystem $e)
        {
            Am_Di::getInstance()->errorLogTable->logException($e);
            $this->answer();
            return;
        }
        $this->answer();
    }

    public function answer()
    {
        $dt = date("YmdGis");
        $arr = array();
        $IPN_PID = $this->request->get('IPN_PID');
        $arr[] = $IPN_PID[0];
        $IPN_PNAME = $this->request->get('IPN_PNAME');
        $arr[] = $IPN_PNAME[0];
        $arr[] = $this->request->get('IPN_DATE');
        $arr[] = $dt;

        $hash = hash_hmac('md5', $this->getstrforhash($arr), $this->getPlugin()->getConfig('secret'));
        echo "<EPAYMENT>$dt|$hash</EPAYMENT>";
    }

    function getstrforhash($arr)
    {
        $res = '';
        foreach($arr as $a)
            if($l = @strlen($a))
                $res.=$l.$a;
            else
                $res.='0';
        return $res;
    }

    public function validate()
    {
        try{
            parent::validate();
        }
        catch(Am_Exception_Paysystem $e)
        {
            Am_Di::getInstance()->errorLogTable->logException($e);
            $this->answer();
        }
    }
}