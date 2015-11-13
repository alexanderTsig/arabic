<?php

/**
 * @table paysystems
 * @id paypro
 * @title PayPro
 * @visible_link http://payproglobal.com/
 * @recurring none
 * @logo_url paypro.png
 */
class Am_Paysystem_Paypro extends Am_Paysystem_Abstract
{

    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.7.0';
    const URL = "https://secure.payproglobal.com/orderpage.aspx";

    protected $defaultTitle = 'PayPro';
    protected $defaultDescription = 'purchase using PayPal or Credit Card';

    public function __construct(Am_Di $di, array $config)
    {
        parent::__construct($di, $config);
        $di->billingPlanTable->customFields()->add(
            new Am_CustomFieldText(
                'paypro_product_id',
                "Paypro product ID",
                ""
                , array(/* ,'required' */)
        ));
    }
    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addInteger('product_id', array('size' => 20))
            ->setLabel('PayPro Product Id');
        $form->addText('key', array('size' => 20))
            ->setLabel('PayPro Product Variable Price Hash');
    }

    function getHash($str)
    {
        $key = $this->getConfig('key');
        $data = "";
        $td = mcrypt_module_open('des', '', 'ecb', '');
        $ckey = $key;
        $iv = $key;
        mcrypt_generic_init($td, $ckey, $iv);
        $data = mcrypt_generic($td, $str);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return $data;
    }

    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $a = new Am_Paysystem_Action_Redirect(self::URL);

        $a->products = current(array_filter(array($invoice->getItem(0)->getBillingPlanData('paypro_product_id'), $this->getConfig('product_id'))));

        $id = $this->invoice->getSecureId("THANKS");
        $desc = array();
        foreach ($invoice->getItems() as $it)
            if ($it->first_total > 0)
                $desc[] = $it->item_title;
        $desc = implode(',', $desc);
        $desc .= ". (invoice: $id)";

        $name = $invoice->getLineDescription();

        $hash = "price={$invoice->first_total}-{$invoice->currency}^^^name=$name^^^desc=$desc";

        $a->hash = base64_encode($this->getHash($hash));
        $a->CustomField1 = $invoice->public_id;

        $a->firstname = $invoice->getFirstName();
        $a->Lastname = $invoice->getLastName();
        $a->Email = $invoice->getEmail();

        $a->Address = $invoice->getStreet();
        $a->City = $invoice->getCity();
        $a->Country = $invoice->getCountry() == 'GB' ? 'united kingdom' : $invoice->getCountry();
        $a->State = $invoice->getState();
        $a->Zipcode = $invoice->getZip();
        $a->Phone = $invoice->getPhone();
        
        //$a->lnk = $this->getCancelUrl();
        $result->setAction($a);
    }

    public function getRecurringType()
    {
        return self::REPORTS_NOT_RECURRING;
    }

    public function getReadme()
    {
        $ipn = $this->getPluginUrl('ipn');
        $refund = $this->getPluginUrl('refund');
        return <<<CUT
CUT;

    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Paypro($this, $request, $response, $invokeArgs);
    }

    public function createThanksTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Paypro_Thanks($this, $request, $response, $invokeArgs);
    }

    public function canAutoCreate()
    {
        return true;
    }

}

class Am_Paysystem_Transaction_Paypro extends Am_Paysystem_Transaction_Incoming
{
    protected $_autoCreateMap = array(
        'name_f' => 'CUSTOMER_FIRST_NAME',
        'name_l' => 'CUSTOMER_LAST_NAME',
        'email' => 'CUSTOMER_EMAIL',
        'state' => 'CUSTOMER_STATE',
        'country' => 'CUSTOMER_COUNTRY_CODE',
        'zip' => 'CUSTOMER_ZIPCODE',
        'street' => 'CUSTOMER_STREET_ADDRESS',
        'city' => 'CUSTOMER_CITY',
        'user_external_id' => 'CUSTOMER_ID',
        'invoice_external_id' => 'CUSTOMER_EMAIL',
    );

    public function autoCreateGetProducts()
    {
        $item_name = $this->request->get('PRODUCT_ID');
        if (empty($item_name))
            return;
        $billing_plan = $this->getPlugin()->getDi()->billingPlanTable->findFirstByData('paypro_product_id', $item_name);
        if ($billing_plan)
            return array($billing_plan->getProduct());
    }

    public function validateSource()
    {
//        $this->_checkIp($this->plugin->getConfig('ip'));
//        if ($this->plugin->getConfig('product_id') != $this->request->get('pc'))
//            throw new Am_Exception_Paysystem_TransactionInvalid("Wrong [pc] passed, this transaction is not related to aMember?");
        return true;
    }

    public function validateStatus()
    {
        return !$this->request->get('IS_DELAYED_PAYMENT');
    }

    public function findInvoiceId()
    {
        return $this->request->getFiltered('CUSTOM_FIELD1',$this->request->getFiltered('ORDER_ID'));
    }

    public function processValidated()
    {
        if($this->request->get('SUBSCRIPTION_STATUS') == 'Cancelled')
            $this->invoice->setCancelled();
        else
            $this->invoice->addPayment($this);
    }

    public function validateTerms()
    {
        $s = $this->request->get('ORDER_TOTAL_BEFORE_TAX');
        if (!preg_match('#\(([\d\.]+)\s+USD\)#', $s, $regs))
            return false;
        $this->assertAmount($this->invoice->first_total, $regs[1]);
        return true;
    }

    public function getUniqId()
    {
        return $this->request->get('ORDER_ID');
    }

}

class Am_Paysystem_Transaction_Paypro_Thanks extends Am_Paysystem_Transaction_Incoming
{

    public function validateSource()
    {
//        $this->_checkIp($this->plugin->getConfig('ip'));
//        if ($this->plugin->getConfig('product_id') != $this->request->get('pc'))
//            throw new Am_Exception_Paysystem_TransactionInvalid("Wrong [pc] passed, this transaction is not related to aMember?");
        return true;
    }

    public function validateStatus()
    {
        return !$this->request->get('isDelayedPayment');
    }

    public function findInvoiceId()
    {
        return $this->request->getFiltered('CustomField1');
    }

    public function processValidated()
    {
        //$this->invoice->addPayment($this); processed by ipn
    }

    public function validateTerms()
    {
        $s = $this->request->get('orderTotalBeforeTax');
        if (!preg_match('#\(([\d\.]+)\s+USD\)#', $s, $regs))
            return false;
        $this->assertAmount($this->invoice->first_total, $regs[1]);
        return true;
    }

    public function getUniqId()
    {
        return $this->request->getQuery('OrderID');
    }

}

