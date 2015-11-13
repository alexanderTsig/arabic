<?php

/**
 * @table paysystems
 * @id gocardlesspro
 * @title GoCardlessPro
 * @visible_link https://gocardless.com/
 * @recurring paysystem
 * @logo_url gocardless.png
 */


class Am_Paysystem_Gocardlesspro extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '0.0.1';

    const LIVE_URL = "https://api.gocardless.com";
    const SANDBOX_URL = "https://api-sandbox.gocardless.com";

    protected $defaultTitle = 'GoCardlessPRO';
    protected $defaultDescription = 'European Direct Debits online - SEPA - by GoCardless (Pro API version)';

    public function getSupportedCurrencies()
    {
        return array('GBP', 'EUR');
    }

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('merchant_id', array('size' => 10))
            ->setLabel('Your Merchant ID');
        $form->addText('access_token', array('size' => 64))
            ->setLabel('Your Merchant access token');
        $form->addText('webhook_token', array('size' => 64))
            ->setLabel('Your Merchant WebHook token');
        $form->addAdvCheckbox("testing")
             ->setLabel("Is it a Sandbox(Testing) Account?");
    }

    public function isConfigured()
    {
        return $this->getConfig('merchant_id') && $this->getConfig('access_token');
    }

    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }

    public function getReadme()
    {
        $rootURL = $this->getDi()->config->get('root_url');
        return <<<CUT
<b>GoCardless payment plugin configuration</b>

1. Enable "gocardlesspro" payment plugin at aMember CP->Setup->Plugins

2. Configure "GoCardlesspro" payment plugin at aMember CP -> Setup/Configuration -> GoCardlesspro

3. Set up "Webhook URI" in your GoCardless merchant account to
   $rootURL/payment/gocardlesspro/ipn

   Set up "Redirect URI" and "Cancel URI" to $rootURL

CUT;
    }

    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
      $response = $this->_sendRequest('/redirect_flows', array (
        'redirect_flows' => array (
          'description' => $invoice->getLineDescription(),
          'session_token' => Zend_Session::getId(),
          'success_redirect_url' => $this->getDi()->config->get('root_url').'/payment/gocardlesspro/thanks',
          'links' => array('creditor' => $this->getConfig('merchant_id'))
        )
      ));
      
      $response = json_decode($response->getBody(),true);

      if (!isset($response['redirect_flows']) || !isset($response['redirect_flows']['redirect_url'])) return false;

      $this->invoice->data()->set('gocardlesspro_id', $response['redirect_flows']['id'])->update();

      $result->setAction(new Am_Paysystem_Action_Redirect($response['redirect_flows']['redirect_url']));
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Gocardlesspro_Ipn($this, $request, $response, $invokeArgs);
    }
    public function createThanksTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Gocardlesspro_Thanks($this, $request, $response, $invokeArgs);
    }

    private function _sendRequest($url, $params, $method = 'POST') {
        $request = $this->createHttpRequest();
        $request->setHeader(array(
          'Accept'             => 'application/json',
          'Content-Type'       => 'application/json',
          'User-Agent'         => 'gocardlesspro-php/v0.0.1',
          'Authorization'      => 'Bearer '.$this->getConfig('access_token'),
          'GoCardless-version' => '2015-04-29' // Latest version
        ));

        $request->setUrl(($this->getConfig('testing') ? Am_Paysystem_Gocardlesspro::SANDBOX_URL : Am_Paysystem_Gocardlesspro::LIVE_URL) . $url);
        if (!is_null($params)) {
            $request->setBody(json_encode($params));
        }

        $request->setMethod($method);
        $this->logOther('Request to '.$url, var_export($params, true));
        $response = $request->send();
        $this->logOther('Response to '.$url, var_export($response, true));
        return $response;
    }

    public function cancelAction(Invoice $invoice, $actionName, Am_Paysystem_Result $result)
    {
        // Cancelling subscription
        $subscriptionId = $invoice->data()->get('subscription_id');
        $response = $this->_sendRequest('/subscriptions/'.$subscriptionId.'/actions/cancel');
        if ($response->getStatus() !== 200) {
          throw new Am_Exception_InputError("An error occurred while cancellation request");
        }

        // Cancelling mandate
        $mandateId = $invoice->data()->get('mandate_id');
        $response = $this->_sendRequest('/mandates/'.$mandateId.'/actions/cancel');
        if ($response->getStatus() !== 200) {
          throw new Am_Exception_InputError("An error occurred while cancellation request");
        }
    }

    public function processRefund(InvoicePayment $payment, Am_Paysystem_Result $result, $amount) {

        // Request to check state of payment ; must be confirmed / paid_out
        $response = $this->_sendRequest('/payments/'.$payment->transaction_id, null, 'GET');
        if ($response->getStatus() !== 200) {
            $result->setFailed('An error occured, unable to find the payment.');
            return $result;
        }

        $response = json_decode($response->getBody(),true);

        if (!in_array($response['payments']['status'], array('confirmed', 'paid_out'))) {
            $result->setFailed('Payment status must be either "Confirmed" or "Paid out" at GoCardLess. Current state is "'.$response['payments']['status'].'"');
            return $result;
        }

        $response = $this->_sendRequest('/refunds/', array (
            'refunds' => array (
                'amount' => intval(doubleval($amount) * 100),
                'total_amount_confirmation' => intval(doubleval($amount) * 100),
                'links' => array ('payment' => $payment->transaction_id)
            )
        ));

        if ($response->getStatus() !== 201) {
            throw new Am_Exception_InputError("An error occurred while cancellation request");
        }

        $trans = new Am_Paysystem_Transaction_Manual($this);
        $trans->setAmount($amount);
        $trans->setReceiptId($payment->receipt_id.'-gocardlesspro-refund');
        $result->setSuccess($trans);
    }
}

class Am_Paysystem_Transaction_Gocardlesspro_Payment extends Am_Paysystem_Transaction_Incoming {
    public function __construct(Am_Paysystem_Abstract $plugin, array $request, Zend_Controller_Response_Http $response, $invokeArgs)
    {
        parent::__construct($plugin, new Am_Request($request), $response, $invokeArgs);
    }


    public function getUniqId()
    {
        return $this->request->get('id');
    }

    public function findInvoiceId()
    {
        $i = Am_Di::getInstance()->invoiceTable->findFirstByData('gocardlesspro_id', $this->getUniqId());
        if($i) {
          $this->invoice = $i;
          return $i->public_id;
        }
        return null;
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
}

class Am_Paysystem_Transaction_Gocardlesspro_Thanks extends Am_Paysystem_Transaction_Incoming_Thanks
{
    public function getUniqId()
    {
        return $this->request->get('redirect_flow_id');
    }

    public function findInvoiceId()
    {
        $i = Am_Di::getInstance()->invoiceTable->findFirstByData('gocardlesspro_id', $this->getUniqId());
        if($i) {
          $this->invoice = $i;
          return $i->public_id;
        }
        return null;
    }

    private function _sendRequest($url, $params, $method = 'POST') {
        $request = $this->plugin->createHttpRequest();
        $request->setHeader(array(
          'Accept'             => 'application/json',
          'Content-Type'       => 'application/json',
          'User-Agent'         => 'gocardlesspro-php/v0.0.1',
          'Authorization'      => 'Bearer '.$this->plugin->getConfig('access_token'),
          'GoCardless-version' => '2015-04-29' // Current API version (version header might be updated soon, see https://gocardless.com/blog/)
        ));

        $request->setUrl(($this->plugin->getConfig('testing') ? Am_Paysystem_Gocardlesspro::SANDBOX_URL : Am_Paysystem_Gocardlesspro::LIVE_URL) . $url);
        if (!is_null($params)) {
            $request->setBody(json_encode($params));
        }

        $request->setMethod($method);
        $this->plugin->logOther('Request to '.$url, var_export($params, true));
        $response = $request->send();
        $this->plugin->logOther('Response to '.$url, var_export($response, true));
        return $response;
    }

    private function _updateInvoice($type, $id) {
        switch ($type) {
            case 'mandate':
                $this->invoice->data()->set('mandate_id', $id)->update();
                break;
            case 'subscription':
                $this->invoice->data()->set('subscription_id', $id)->update();
                break;
        }
    }

    public function processValidated()
    {
      // We do nothing ;
      // The default behavior is : $this->invoice->addPayment($this);
    }

    public function validateSource()
    {
      // We "complete" the request by acknowledging that specific user
      $response = $this->_sendRequest('/redirect_flows/'.$this->getUniqId().'/actions/complete', array (
        'data' => array (
          'session_token' => Zend_Session::getId(),
        )
      ));

      if ($response->getStatus() !== 200) return false;

      $response = json_decode($response->getBody(),true);

      if (!(isset($response['redirect_flows']) && isset($response['redirect_flows']['links']) && isset($response['redirect_flows']['links']['mandate']))) return false;

      $invoice = $this->loadInvoice($this->findInvoiceId());
      $mandateId = $response['redirect_flows']['links']['mandate'];
      $this->_updateInvoice('mandate', $mandateId);


      if (!empty($invoice->rebill_times) && intval($invoice->rebill_times) > 0) {

          // First payment is made outside the subscription, right away (one off payment):
          $paymentParams = array(
              'payments' => array (
                  'currency'    => $invoice->currency,
                  'amount'      => intval(floatval($invoice->first_total) * 100),
                  'description' => $invoice->getLineDescription(),
                  'metadata' => array (
                      'user'       => $invoice->getEmail(),
                      'invoice_id' => $invoice->public_id
                  ),
                  'links'       => array('mandate' => $mandateId)
              )
          );

          // One time payment of first_total
          $response = $this->_sendRequest('/payments', $paymentParams);
          if ($response->getStatus() !== 201) return false;
          $response = json_decode($response->getBody(),true);

          $invoice->addPayment(new Am_Paysystem_Transaction_Gocardlesspro_Payment($this->getPlugin(), $response['payments'], $this->response, $this->invokeArgs));

          // Start subscription at interval + n
          $first_period = new Am_Period($invoice->first_period);
          $date_period = new DateTime($first_period->addTo(date('Y-m-d')), new DateTimeZone('UTC'));

          // Subscription of second_total
          $subscriptionParams = array (
              'start_at' => $date_period->format('Y-m-d'),
              'links' => array ('mandate' => $mandateId),
              'metadata' => array (
                  'user' => $invoice->getEmail(),
                  'invoice_id' => $invoice->public_id
              )
          );
          $period = empty($invoice->second_period) ? $invoice->first_period : $invoice->second_period;

          if($period === Am_Period::MAX_SQL_DATE)
          {
              $subscriptionParams['interval_unit'] = 'yearly';
          }
          else
          {
              $am_period = new Am_Period($period);
              switch ($am_period->getUnit())
              {
                  case 'm': $interval_unit = 'monthly'; break;
                  case 'y': $interval_unit = 'yearly'; break;
              }
              $subscriptionParams['interval_unit'] = $interval_unit;
          }

          if (!empty($invoice->second_total)) {
              $subscriptionParams['amount'] = intval(floatval($invoice->second_total) * 100);
          } else {
              $subscriptionParams['amount'] = intval(floatval($invoice->first_total) * 100);
          }

          $subscriptionParams['name'] = $invoice->getLineDescription();
          $subscriptionParams['currency'] = $invoice->currency;
          $subscriptionParams['count'] = intval($invoice->rebill_times);

          if ($subscriptionParams['count'] > 1000) {
              $subscriptionParams['count'] = 1000;
          }

          if ($subscriptionParams['interval_unit'] === 'monthly') {
              $subscriptionParams['day_of_month'] = 5;
          }

          $response = $this->_sendRequest('/subscriptions', array ('subscriptions' => $subscriptionParams));
          if ($response->getStatus() !== 201) return false;
          $response = json_decode($response->getBody(),true);
          $this->_updateInvoice('subscription', $response['subscriptions']['id']);
      } else {
          // One time payment only
          $paymentParams = array(
              'payments' => array (
                  'currency'    => $invoice->currency,
                  'amount'      => intval(floatval($invoice->first_total) * 100),
                  'description' => $invoice->getLineDescription(),
                  'metadata' => array (
                      'user'       => $invoice->getEmail(),
                      'invoice_id' => $invoice->public_id
                  ),
                  'links'       => array('mandate' => $mandateId)
              )
          );

          // One time payment of first_total
          $response = $this->_sendRequest('/payments', $paymentParams);
          if ($response->getStatus() !== 201) return false;
          $response = json_decode($response->getBody(), true);

          $invoice->addPayment(new Am_Paysystem_Transaction_Gocardlesspro_Payment($this->getPlugin(), $response['payments'], $this->response, $this->invokeArgs));
      }

      $invoice->updateStatus();

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
}

class Am_Paysystem_Transaction_Gocardlesspro_Ipn extends Am_Paysystem_Transaction_Incoming
{
    public function getUniqId()
    {

    }

    public function findInvoiceId()
    {

    }

    public function validateSource()
    {
      return $this->request->getHeader('Webhook-Signature') === hash_hmac('sha256', $this->request->getRawBody(), $this->getPlugin()->getConfig('webhook_token'));
    }

    public function processValidated()
    {
        $payload = json_decode($this->request->getRawBody(), true);
        foreach($payload['events'] as $event)
        {
            $request = new Am_Request($event, $this->request->getActionName());
            $transaction = new Am_Paysystem_Transaction_Gocardlesspro_IpnEvent($this->getPlugin(), $request, $this->response, $this->invokeArgs);
            $transaction->process();
        }
    }

    public function validateStatus()
    {
        return true;
    }

    public function validateTerms()
    {
        return true;
    }

    public function autoCreate()
    {
        return;
    }
}

class Am_Paysystem_Transaction_Gocardlesspro_IpnEvent extends Am_Paysystem_Transaction_Incoming
{
    public function getUniqId()
    {
        return $this->request->get('id'); // == EVXXXXXX
    }

    private function _sendRequest($url, $params, $method = 'POST') {
        $request = $this->plugin->createHttpRequest();
        $request->setHeader(array(
          'Accept'             => 'application/json',
          'Content-Type'       => 'application/json',
          'User-Agent'         => 'gocardlesspro-php/v0.0.1',
          'Authorization'      => 'Bearer '.$this->plugin->getConfig('access_token'),
          'GoCardless-version' => '2015-04-29' // Current API version (version header might be updated soon, see https://gocardless.com/blog/)
        ));

        $request->setUrl(($this->plugin->getConfig('testing') ? Am_Paysystem_Gocardlesspro::SANDBOX_URL : Am_Paysystem_Gocardlesspro::LIVE_URL) . $url);
        if (!is_null($params)) {
            $request->setBody(json_encode($params));
        }

        $request->setMethod($method);
        $this->plugin->logOther('Request to '.$url, var_export($params, true));
        $response = $request->send();
        $this->plugin->logOther('Response to '.$url, var_export($response, true));
        return $response;
    }

    public function findInvoiceId()
    {
        $links = $this->request->get('links');
        switch($this->request->get('resource_type')) {
            case 'mandates':
                $i = Am_Di::getInstance()->invoiceTable->findFirstByData('mandate_id', $links['mandate']);
                if($i) {
                    $this->invoice = $i;
                    return $i->public_id;
                }
                break;
            case 'subscriptions':
                $i = Am_Di::getInstance()->invoiceTable->findFirstByData('subscription_id', $links['subscription']);
                if($i) {
                    $this->invoice = $i;
                    return $i->public_id;
                }
                break;
            case 'payments':
                $transaction = Am_Di::getInstance()->invoicePaymentTable->findFirstBy(array('transaction_id' => $links['payment']));
                if ($transaction) {
                  $this->invoice = $transaction->getInvoice();
                  return $transaction->getInvoice()->public_id;
                }
                break;
        }

        return null;
    }

    public function processValidated()
    {
        $links = $this->request->get('links');

        switch ($this->request->get('resource_type')) {
            case 'mandates':
                if (in_array($this->request->get('action'), array('cancelled', 'failed', 'expired'))) {
                    $this->invoice->setCancelled(true);
                }
                break;
            case 'subscriptions':
                if ($this->request->get('action') === 'payment_created') {
                    $this->invoice->addPayment(new Am_Paysystem_Transaction_Gocardlesspro_Payment($this->getPlugin(), array('id' => $links['payment']), $this->response, $this->invokeArgs));
                } else if ($this->request->get('action') === 'cancelled') {
                    $this->invoice->setCancelled(true);
                }
                break;
            case 'payments':
                if (in_array($this->request->get('action'), array('cancelled', 'failed', 'late_failure_settled'))) {
                    $this->invoice->setCancelled(true);
                } else if ($this->request->get('action') === 'charged_back') {
                    $this->invoice->addChargeBack(new Am_Paysystem_Transaction_Gocardlesspro_Payment($this->getPlugin(), array('id' => $links['payment']), $this->response, $this->invokeArgs), $links['payment']);
                }
                break;
        }

        if (!is_null($this->invoice)) {
          $this->invoice->updateStatus();
        }
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

    public function autoCreate()
    {
        try {
            parent::autoCreate();
        }
        catch (Am_Exception_Paysystem $e)
        {
            Am_Di::getInstance()->errorLogTable->logException($e);
        }
    }
}