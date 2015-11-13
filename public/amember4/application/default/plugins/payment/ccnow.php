<?php
/**
 * @table paysystems
 * @id ccnow
 * @title CCNow
 * @recurring none
 */
class Am_Paysystem_Ccnow extends Am_Paysystem_Abstract
{

    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.7.0';

    protected $defaultTitle = 'CCNow';
    protected $defaultDescription = 'accepts all major credit cards';

    const URL_LIVE = 'https://www.ccnow.com/cgi-local/transact.cgi';

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('login')
            ->setLabel('CCNow Client ID')
            ->addRule('required');

        $form->addText('key')
            ->setLabel('Activation Key')
            ->addRule('required');

        $form->addAdvCheckbox('testmode')
            ->setLabel('Is Test Mode?');
    }

    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $a = new Am_Paysystem_Action_Form(self::URL_LIVE);
        $result->setAction($a);

        $sequence   = rand(1, 1000);
        $vars = array(  
            'x_version'   => '1.0',
            'x_login'     => $this->getConfig('login'),
            'x_invoice_num' => $invoice->public_id,
            'x_method' => $this->getConfig('testmode') ? 'TEST' : 'NONE',
            'x_name' => $invoice->getName(),
            'x_address' => $invoice->getStreet(),
            'x_address2' => $invoice->getStreet2(),
            'x_city' => $invoice->getCity(),
            'x_country' => $invoice->getCountry(),
            'x_state' => $invoice->getState(),
            'x_zip' => $invoice->getZip(),
            'x_email' => $invoice->getEmail(),
            'x_currency_code' => $invoice->currency,
            'x_amount'    => $price = sprintf('%.2f', $invoice->first_total),
            'x_tax_amount'    => $tprice = sprintf('%.2f', $invoice->first_tax),
            'x_fp_sequence' => $sequence,
            'x_fp_arg_list' => 'x_login^x_fp_arg_list^x_fp_sequence^x_amount^x_currency_code',
            'x_fp_hash' => '',
            'x_fp_hash' => md5($q = $this->getConfig('login')."^x_login^x_fp_arg_list^x_fp_sequence^x_amount^x_currency_code^".$sequence."^".$price."^".$invoice->currency."^".$this->getConfig('key'))
        );
        foreach ($invoice->getItems() as $kk => $item)
        {
            $k = $kk+1;
            $vars['x_product_sku_'.$k] = $item->item_id;
            $vars['x_product_title_'.$k] = $item->item_title;
            $vars['x_product_quantity_'.$k] = $item->qty;
            $vars['x_product_unitprice_'.$k] = $item->first_total;
            $vars['x_product_url_'.$k] = ROOT_URL;
            
        }
        foreach($vars as $k => $v)
            $a->addParam($k, $v);

        $result->setAction($a);
    }

    public function getRecurringType()
    {
        return self::REPORTS_NOT_RECURRING;
    }

    public function getReadme()
    {
        return <<<CUT
CUT;
    }
    
    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        try{
            parent::directAction($request, $response, $invokeArgs);        
        } catch (Am_Exception_Paysystem_TransactionInvalid $ex) {
            //nothing
        }
        echo 'ok';
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Ccnow($this, $request, $response, $invokeArgs);
    }

}

class Am_Paysystem_Transaction_Ccnow extends Am_Paysystem_Transaction_Incoming
{
    
    public function validateSource()
    {
        return $this->request->get('x_status') == 'received';
    }

    public function findInvoiceId()
    {
        return $this->request->get('x_invoice_num');
    }

    public function validateStatus()
    {
        return true;
    }

    public function getUniqId()
    {
        return $this->request->get('x_orderid');
    }

    public function validateTerms()
    {
        $this->assertAmount($this->invoice->first_total, $this->request->get('x_amount'));
        return true;
    }

}