<?php
/**
 * @table paysystems
 * @id micropayment
 * @title Micropayment
 * @visible_link https://www.micropayment.de/
 * @recurring none
 * @country DE
 */
class Am_Paysystem_Micropayment extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.7.0';
    
    const URL = 'https://billing.micropayment.de/';

    protected $defaultTitle = 'Micropayment';
    protected $defaultDescription = 'Pay by bank transfer';
    
    protected $defaultPrepayDescription = 'Bank Transfer';
    protected $defaultLastschriftDescription = 'Lastschrift';
    
    public function getSupportedCurrencies()
    {
        return array('EUR', 'USD', 'CHF');
    }

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText("key")->setLabel(array('Access Key', '
            You\'ll find your AccessKey in
            ControlCenter --> My Configuration'));
        $form->addText("project")->setLabel(array('Project Identifier'));
        $form->addAdvCheckbox("testing")->setLabel("Test Mode Enabled");
        $form->addMagicSelect("methods")
            ->setLabel(___("Available methods\n".
            "if none is selected then all methods will be listed"
            ))
            ->loadOptions(array(
                'prepay'=>$this->defaultPrepayDescription,
                'lastschrift'=>$this->defaultLastschriftDescription
                ));
        $form->addText("prepay.title")->setLabel(array('Prepay method title',$this->defaultPrepayDescription));
        $form->setDefault('prepay.title', $this->defaultPrepayDescription);
        
        $form->addText("lastschrift.title")->setLabel(array('Lastschrift method title',$this->defaultLastschriftDescription));
        $form->setDefault('lastschrift.title', $this->defaultLastschriftDescription);
    }
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $m = $this->getConfig('methods');
        if(@count($m)==1)
            $a = new Am_Paysystem_Action_Form(self::URL.$m[0].'/event/');
        else
        {
            $a = new Am_Paysystem_Action_HtmlTemplate_Micropayment($this->getDir(), 'micropayment-confirm.phtml');            
            $methods = array();
            if(@count($m))
            {
                $a->url = self::URL.$m[0].'/event/';
                foreach($m as $title)
                    $methods[self::URL.$title.'/event/'] = $this->getConfig($title.'.title');
            }
            else
            {
                foreach($this->getConfig() as $k => $v)
                {
                    if(is_array($v) && !empty($v['title']))
                        $methods[self::URL.$k.'/event/'] = $v['title'];
                }
                $a->url = array_shift(array_keys($methods));
            }
            $a->methods = $methods;
        }
        $a->project = $this->getConfig('project');
        $a->amount = $invoice->first_total*100;
        $a->freepaymentid = $invoice->public_id;
        $a->seal = md5("project={$a->project}&amount={$a->amount}&freepaymentid={$a->freepaymentid}".$this->getConfig('key'));
        $result->setAction($a);       
    }
    public function getReadme()
    {
        $rootURL = $this->getDi()->config->get('root_url');
        return <<<CUT
Setup API URL in Micropayment Account to
   $rootURL/payment/micropayment/ipn
Go to --> My Configuration --> Projects
   Choose Actions for the product you want to configure
   Click on Configure Payment Methods
   For module Prepay and service Event choose Actions
   Click on Configure
Check Activate payment for product and fill in your API-URL
   $rootURL/payment/micropayment/ipn
   Do the same steps for module debit (Lastschrift) and service Event
   
In your Micropayment Control Center go to My Configuration --> Payment Methods
   Choose your product and click on Actions for the respective payment method
   Click on Configure and go to Add GET-Parameters
   Add freepaymentid on left side and __\$freepaymentid__ on right side
   Click Save settings
CUT;
    }

    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        try
        {
            parent::directAction($request, $response, $invokeArgs);
        }
        catch (Exception $e)
        {
            $this->getDi()->errorLogTable->logException($e);
            $sep  = "\n";
            $url  = $this->getDi()->config->get('root_url')."/thanks";
            echo "status=ok{$sep}url=$url{$sep}target=_top{$sep}forward=1";            
        }
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Micropayment($this, $request, $response, $invokeArgs);
    }

    public function getRecurringType()
    {
        return self::REPORTS_NOT_RECURRING;
    }
}
class Am_Paysystem_Transaction_Micropayment extends Am_Paysystem_Transaction_Incoming
{
    public function findInvoiceId()
    {
        return $this->request->get('freepaymentid');
    }
    public function getUniqId()
    {
        return $this->request->get('auth');
    }

    public function validateSource()
    {
        return true;
    }

    public function validateStatus()
    {
        return true;
    }

    public function validateTerms()
    {
        return true;
    }
    public function processValidated()
    {
        switch ($this->request->get('paystatus'))
        {
            case 'PAID': 
                $this->invoice->addPayment($this);
                break;            
            case 'INIT': break;
            default: break;
        }
        $sep  = "\n";
        $url  = $this->getPlugin()->getDi()->config->get('root_url')."/thanks?id=".$this->invoice->getSecureId("THANKS");
        echo "status=ok{$sep}url=$url{$sep}target=_top{$sep}forward=1";
    }
}
class Am_Paysystem_Action_HtmlTemplate_Micropayment extends Am_Paysystem_Action_HtmlTemplate
{
    protected $_template;
    protected $_path;
    public function  __construct($path, $template) 
    {
        $this->_template = $template;
        $this->_path = $path;
    }
    public function process(Am_Controller $action = null)
    {        
        $action->view->addBasePath($this->_path);
        
        $action->view->assign($this->getVars());       
        $action->renderScript($this->_template);
        throw new Am_Exception_Redirect;
    }
}
