<?php
/**
 * @table paysystems
 * @id verotel
 * @title Verotel
 * @visible_link http://www.verotel.com/
 * @recurring paysystem
 * @logo_url verotel.png
 * @country NL
 * @fixed_products 1
 * @adult 1
 */
class Am_Paysystem_Verotel extends Am_Paysystem_Abstract {
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.7.0';
    
    protected $defaultTitle = 'Verotel';
    protected $defaultDescription = 'Credit Card Payment';
    
    const URL = "https://secure.verotel.com/cgi-bin/vtjp.pl";
    const DYNAMIC_URL = "https://secure.verotel.com/order/purchase";

    public function _initSetupForm(Am_Form_Setup $form) {
        $form->addInteger('merchant_id',array('size'=>20,'maxlength'=>20))
            ->setLabel('Your Verotel Merchant ID#');
        $form->addInteger('site_id')
            ->setLabel('Verotel Site Id');
        $form->addAdvCheckbox('dynamic_pricing')->setLabel(array('Allow Dynamic Pricing','this option does not allow to use recurring'));
        $form->addText('secret')
            ->setLabel(array('Private key','required for dynamic pricing only'));
    }
    
    public function isConfigured() {
        return strlen($this->getConfig('merchant_id'));
    }

    public function init()
    {
        parent::init();
        $this->getDi()->billingPlanTable->customFields()
            ->add(new Am_CustomFieldText('verotel_id', "VeroTel Site ID", 
            ""));
    }
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result) {
        if($this->getConfig('dynamic_pricing'))
        {
            $a  = new Am_Paysystem_Action_Redirect(self::DYNAMIC_URL);
            $a->version = 1;
            $a->shopID = $this->getConfig('site_id');
            $a->priceAmount = $invoice->first_total;
            $a->priceCurrency = $invoice->currency;
            $a->description = $invoice->getLineDescription();
            $a->referenceID = $invoice->public_id;        
            $a->signature = sha1($q = $this->getConfig('secret').":description=" . $invoice->getLineDescription() . ":priceAmount=" . $invoice->first_total . ":priceCurrency=" . $invoice->currency . ":referenceID=" . $invoice->public_id . ":shopID=" . $this->getConfig('site_id') . ":version=1");
        }
        else
        {
            $a  = new Am_Paysystem_Action_Redirect(self::URL);
            $a->verotel_id = $this->getConfig('merchant_id');
            $a->verotel_product = $invoice->getItem(0)->getBillingPlanData("verotel_id") ?  $invoice->getItem(0)->getBillingPlanData("verotel_id") : $this->getConfig('site_id');
            $a->verotel_website = $invoice->getItem(0)->getBillingPlanData("verotel_id") ?  $invoice->getItem(0)->getBillingPlanData("verotel_id") : $this->getConfig('site_id');
            $a->verotel_usercode = $invoice->getLogin();
            $a->verotel_passcode = 'FromSignupForm';//$invoice->getUser()->getPlaintextPass();
            $a->verotel_custom1 = $invoice->public_id;        
        }
        $a->filterEmpty();
        $result->setAction($a);
    }
    
    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        try{
            parent::directAction($request, $response, $invokeArgs);
        }
        catch(Am_Exception_Paysystem $e)
        {
            $this->getDi()->errorLogTable->logException($e);
            print "APPROVED";
            exit();            
        }
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs) {
        $res = split(":", $request->get('vercode'));
        switch($request->get('trn', @$res[3])){
            case Am_Paysystem_Transaction_Verotel::ADD : 
            case Am_Paysystem_Transaction_Verotel::REBILL : 
                return new Am_Paysystem_Transaction_Verotel_Charge($this, $request, $response,$invokeArgs);
            case Am_Paysystem_Transaction_Verotel::DELETE : 
                return new Am_Paysystem_Transaction_Verotel_Cancellation($this, $request, $response,$invokeArgs);
            case Am_Paysystem_Transaction_Verotel::MODIFY : 
                return new Am_Paysystem_Transaction_Verotel_Modify($this, $request, $response,$invokeArgs);
            default : 
                return new Am_Paysystem_Transaction_Verotel_Dynamic($this, $request, $response,$invokeArgs);
        }
        
    }

    public function getRecurringType() {
        return self::REPORTS_REBILL;        
    }

    
    function getReadme(){
        return <<<CUT
<b>Verotel payment plugin configuration</b>

Configure your Verotel Account - contact verotel support and ask
them to set:
Remote User Management script URL to
%root_url%/payment/verotel/ipn

Run a test transaction to ensure everthing is working correctly.
CUT;
    }
}


class Am_Paysystem_Transaction_Verotel extends Am_Paysystem_Transaction_Incoming{
    const ADD = 'add';
    const REBILL = 'rebill';
    const MODIFY  = 'modify';
    const DELETE = 'delete';
    
    protected $vercode;
    protected $ip  = array(
        '195.20.32.202'
    );

    public function __construct(Am_Paysystem_Abstract $plugin, Am_Request $request, Zend_Controller_Response_Http $response, $invokeArgs)
    {
        $this->vercode = split(":", $request->get('vercode'));
        parent::__construct($plugin, $request, $response, $invokeArgs);
    }

    public function findInvoiceId(){
        return $this->request->get('custom1',@$this->vercode[5]);
    }
    public function getUniqId() {
        return $this->request->get("trn_id",@$this->vercode[6]);
    }
    
    public function validateSource() {
        $this->_checkIp($this->ip);
        return true;
    }
    
    public function validateStatus() {
        return true;
    }
    
    public function validateTerms() {
        return true;
    }
    
    public function processValidated() {
        print "APPROVED";
    }    
}

class Am_Paysystem_Transaction_Verotel_Charge extends Am_Paysystem_Transaction_Verotel{
    //uncomment to allow users to change product on verotel site
    /*public function validateTerms() {
        if($this->invoice->isFirstPayment())
        {
            if(doubleval($this->request->get("amount")) == $this->invoice->first_total ) return true;
            if($bp = Am_Di::getInstance()->billingPlanTable->findFirstBy(array('first_price' => doubleval($this->request->get("amount")))))
            {
                Am_Di::getInstance()->db->query("DELETE from ?_invoice_item where invoice_id=?",$this->invoice->invoice_id);
                $this->invoice->add(Am_Di::getInstance()->productTable->load($bp->product_id),1);
                $this->invoice->calculate();
                $this->invoice->update();
                return true;
            }
            return false;
        }
        else return true;
    }*/
    public function processValidated() {
        $this->invoice->addPayment($this);
        parent::processValidated();
    }
}

class Am_Paysystem_Transaction_Verotel_Cancellation extends Am_Paysystem_Transaction_Verotel{
    public function processValidated() {
        $this->invoice->setCancelled(true);
        $this->invoice->stopAccess($this);
        parent::processValidated();
    }
}

class Am_Paysystem_Transaction_Verotel_Modify extends Am_Paysystem_Transaction_Verotel{
    public function processValidated() {
        parent::processValidated();
    }
}

class Am_Paysystem_Transaction_Verotel_Dynamic extends Am_Paysystem_Transaction_Incoming
{
    protected $ip  = array(
        '195.20.32.202',
        '217.115.203.18',
        '89.187.131.244',
        '93.185.97.248'
    );

    public function findInvoiceId(){
        return $this->request->get('referenceID');
    }
    public function getUniqId() {
        return $this->request->get("saleID");
    }
    
    public function validateSource() {
        $this->_checkIp($this->ip);
        return true;
    }
    
    public function validateStatus() {
        return true;
    }
    
    public function validateTerms() {
        return true;
    }
    
    public function processValidated() {
        $this->invoice->addPayment($this);
        print "OK";
    }    
    
}