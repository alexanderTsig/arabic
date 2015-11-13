<?php
/**
 * @table paysystems
 * @id centili
 * @title Centili
 * @visible_link https://www.centili.com/
 * @recurring paysystem
 */
class Am_Paysystem_Centili extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.7.0';

    protected $defaultTitle = 'Centili';

    public function init()
    {
        Am_Di::getInstance()->billingPlanTable->customFields()->add(
            new Am_CustomFieldText(
                'centili_apikey',
                'Centili Api Key#',
                'you have to create similar service in Centili and enter its Api Key here')
        );
    }

    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }

    public function isNotAcceptableForInvoice(Invoice $invoice)
    {
        foreach ($invoice->getItems() as $item) {
            /* @var $item InvoiceItem */
            if (!$item->getBillingPlanData('centili_apikey'))
                return "item [" . $item->item_title . "] has no related Centili Api Key configured";
        }
    }

    public function _initSetupForm(Am_Form_Setup $form)
    {
        //nop
    }

    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $a = new Am_Paysystem_Action_HtmlTemplate('layout.phtml');

        $apikey = $this->invoice->getItem(0)->getBillingPlanData('centili_apikey');
        $clientId = urlencode($invoice->public_id);

        $a->title = sprintf('Pay with %s', $this->getTitle());
        $a->content = <<<CUT
<a id="c-mobile-payment-widget" href="https://www.centili.com/widget/WidgetModule?api=$apikey&clientId=$clientId"><img src="https://www.centili.com/images/payment-widget-button.png"/></a>
<script type="text/javascript" src="https://www.centili.com/widget/js/c-mobile-payment-scripts.js"></script>
CUT;

        $result->setAction($a);
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Centili($this, $request, $response, $invokeArgs);
    }

    public function getReadme()
    {
        $ipn = $this->getPluginUrl('ipn');
        $thanks = ROOT_SURL . '/thanks';

        return <<<CUT
You need to set up Service in your Centili account for each of your product in aMember.
Then put API Key from Centili service to product settings in aMember.

When you set up service in Centili please use the following urls:

Redirect URL after successful payment:
<strong>$thanks</strong>

Payment result notification URL:
<strong>$ipn</strong>

You can do it with 'Advanced Integration Setup' on tab 'Setup' of service configuration.

CUT;
    }

}

class Am_Paysystem_Transaction_Centili extends Am_Paysystem_Transaction_Incoming
{

    public function getUniqId()
    {
        return $this->request->getParam('transactionid');
    }

    public function validateSource()
    {
        $p = $this->request->getRequestOnlyParams();
        $sign = $p['sign'];
        unset($p['sign']);
        sort($p, SORT_STRING);
        $expect = sha1(implode('', $p));
        return $expect == $sign;
    }

    public function validateTerms()
    {
        return true;
    }

    public function validateStatus()
    {
        return $this->request->getParam('status') == 'success';
    }

    public function findInvoiceId()
    {
        $this->request->getParam('clientid');
    }

    public function processValidated()
    {
        switch ($this->request->getParam('event_type')) {
            case 'one_off' :
            case 'opt_in' :
            case 'recurring_billing' :
                $this->invoice->addPayment($this);
                break;
            case 'opt_out' :
                $this->invoice->setCancelled();
                break;
        }
    }

}