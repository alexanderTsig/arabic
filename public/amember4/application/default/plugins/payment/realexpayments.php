<?php

/**
 * @table paysystems
 * @id realexpayments
 * @title Realexpayments
 * @visible_link http://www.realexpayments.com/
 * @recurring none
 * @logo_url realexpayments.png
 */
class Am_Paysystem_Realexpayments extends Am_Paysystem_Abstract
{

    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.7.0';

    protected $defaultTitle = 'Realexpayments';
    protected $defaultDescription = 'Pay by credit card card';

    const LIVE_URL = "https://hpp.realexpayments.com/pay";
    const SANDBOX_URL = "https://hpp.sandbox.realexpayments.com/pay";

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText("merchant_id")
            ->setLabel('Your Realex Merchant ID');
        $form->addPassword('secret')->setLabel('Secret key');
        $form->addAdvcheckbox('testing')->setLabel('Testing mode');
    }

    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $u = $invoice->getUser();
        $timestamp = strftime("%Y%m%d%H%M%S");
        $a = new Am_Paysystem_Action_Redirect($this->getConfig('testing') ? self::SANDBOX_URL : self::LIVE_URL);
        $a->MERCHANT_ID = $this->getConfig('merchant_id');
        $a->ORDER_ID = $invoice->public_id;
        $a->CURRENCY = $invoice->currency;
        $a->AMOUNT = $invoice->first_total * 100;
        $a->TIMESTAMP = $timestamp;
        $a->SHA1HASH = sha1(sha1($timestamp.'.'.$this->getConfig('merchant_id').'.'.$invoice->public_id.'.'.($invoice->first_total * 100).'.'.$invoice->currency).'.'.$this->getConfig('secret'));
        $a->AUTO_SETTLE_FLAG = 1;
        $result->setAction($a);
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
    }
    
    public function createThanksTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Realexpayments($this, $request, $response, $invokeArgs);
    }

    public function getRecurringType()
    {
        return self::REPORTS_NOT_RECURRING;
    }

    public function getSupportedCurrencies()
    {
        return array('EUR', 'GBP');
    }
    
}

class Am_Paysystem_Transaction_Realexpayments extends Am_Paysystem_Transaction_Incoming_Thanks
{
    public function findInvoiceId()
    {
        return $this->request->getFiltered('ORDER_ID');
    }

    public function getUniqId()
    {
        return $this->request->getFiltered('PASREF');
    }

    public function validateSource()
    {
        return $this->request->getFiltered('SHA1HASH') == sha1(sha1($this->request->getFiltered('TIMESTAMP').$this->getPlugin()->getConfig('merchant_id').$this->request->getFiltered('ORDER_ID').$this->request->getFiltered('RESULT').$this->request->getFiltered('MESSAGE').$this->request->getFiltered('PASREF').$this->request->getFiltered('AUTHCODE')).$this->getPlugin()->getConfig('secret'));
    }

    public function validateStatus()
    {
        return $this->request->getFiltered('RESULT') == '00';
    }

    public function validateTerms()
    {
        return true;
    }
}