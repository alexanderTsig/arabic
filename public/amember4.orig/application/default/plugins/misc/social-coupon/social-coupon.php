<?php
/**
 *  Social Coupon v1.8
 *  Copyright 2012 (c) R Woodgate
 *  All Rights Reserved
 *
 * ============================================================================
 * Revision History:
 * ----------------
 * 2014-01-13	v1.8	R Woodgate	Made twitter more robust against conflicts
 * 2013-09-25	v1.7	R Woodgate	Updated for aMember v4.3.1
 * 2013-06-13	v1.6	R Woodgate	Added Lightbox / Popup option
 * 2013-06-03	v1.5	R Woodgate	Fixed conflict with aMember Facebook plugin
 * 2012-12-21	v1.4	R Woodgate	Hide on email confirmation screen
 * 2012-12-18	v1.3	R Woodgate	Added Option to specify signup form
 * 2012-12-06	v1.2	R Woodgate	Added FB Options | Production release
 * 2012-11-29	v1.1	R Woodgate	Added LinkedIn
 * 2012-11-16	v1.0	R Woodgate	Plugin Created
 * ============================================================================
 **/

class Am_Plugin_SocialCoupon extends Am_Plugin
{

    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '1.7';
    const SC_COOKIE = 'sc_coupon';

    public function init()
    {
        parent::init();
    }

    public function isConfigured()
    {
        $coupon = Am_Di::getInstance()->couponTable->findFirstByCode($this->getConfig('coupon'));
        return $coupon ? !$coupon->validate() : false; // NB: !validate as returns message (true) on error
    }
	
    public function onLoadSignupForm($event)
    {
        $ret = $event->getReturn(); // SavedForm object (Array with SavedForm object pre v4.3.1)
        $thisForm = is_array($ret) ? $ret[0] : $ret;
        if (!$thisForm) return;
        
        // Check this is the right form
        if (!in_array($thisForm->pk(), $this->getConfig('allowed_forms'))) 
                return;
        
        // Check form has coupon brick
        $blocks = $this->getDi()->blocks;
        foreach ($thisForm->getBricks() as $brick) {
            if ($brick instanceof Am_Form_Brick_Coupon)
            {
                $blocks->add(
                    new Am_Block('signup/form/before', null, 'sc-signup', $this, 'sc-signup.phtml')
                );
                return;
            }
        }   
    }
    
    function onSetupForms(Am_Event_SetupForms $event)
    {
		$form = new Am_Form_Setup('social-coupon');
		$form->setTitle("Social Coupon");
		
		// General Settings
		$fs = $form->addFieldset()->setLabel(___('Social Coupon <img src="http://www.cogmentis.com/lcimg/social.jpg" />'));
		$fs->addText('coupon')->setLabel(___("Coupon Code\n Enter the coupon code you wish to use for social actions.\n".
                                                     'NB: If you get a warning message that this plugin is not configured then the coupon you have listed is not valid or is no longer valid.'));
		$fs->addCheckboxedGroup('debug')->setLabel(___("Debug Messages?\n".'If ticked, debug messages will be written to the error log'));
                
                // Popup Options
                $fs->addCheckboxedGroup('lightbox')->setLabel(___("Popup Mode?\n".'If ticked, the Social Coupon box will appear only when a button is clicked'));
		$fs->addText('lightbox_button_text', array('size' => 40))->setLabel(___("Popup Button Label\n".'The text of the popup button (only used if popup mode enabled)'));
                $form->setDefault('lightbox_button_text', 'Click here for Coupon');
                
                // Share title
		$fs->addText('share_title', array('size' => 80))->setLabel(___("Share Title\n The title for the Social Coupon share box."));
		$form->setDefault('share_title', 'Get Social and Save!');
		
		// Share message
		$fs->addTextarea("share_message", array("cols"=>80, "rows"=>3))->setLabel(
				___("Share message\n". 
				"you can enter any HTML here, it will be displayed to\n".
				"customer before they have liked or shared."));
		$form->setDefault('share_message', 'Like or Share and get a discount on your order!');
		
		// Thanks title
		$fs->addText('thanks_title', array('size' => 80))->setLabel(___("Thanks Title\n The title for the Social Coupon share box thanks message."));
		$form->setDefault('thanks_title', 'Thanks for sharing!');
		
		// Thanks message
		$fs->addTextarea("thanks_message", array("cols"=>80, "rows"=>3))->setLabel(
				___("Thanks message\n". 
				"you can enter any HTML here, it will be displayed to\n".
				"customer after they have liked or shared.\n"));
		$form->setDefault('thanks_message', 'Thanks for sharing. Your discount coupon has been entered for you in the coupon box below.');
		
		// Allowed signup forms
                $signupFormOptions = array();
                $signupForms = $this->getDi()->savedFormTable->findBy(array('type'=>'signup'));
                foreach($signupForms as $sf) $signupFormOptions[$sf->saved_form_id] = $sf->title;
                $form->addMagicSelect('allowed_forms')
                    ->setLabel(array("Applicable Signup Forms\n"."The signup forms on which the social coupon should appear"))
                    ->loadOptions(
                        $signupFormOptions
                );

                // Facebook
		$fs = $form->addFieldset()->setLabel(___('Facebook Settings'));
		$fs->addCheckboxedGroup('fb_enabled')->setLabel(___("Facebook Enabled?\n"));
		$fs->addText('fb_url', array('size' => 40))->setLabel(___("Share URL\n".'The URL you want shared on Facebook'));
		$form->setDefault('fb_url', ROOT_URL);
		$fs->addElement('advradio', 'fb_scheme')->setLabel(___('Facebook Colour Scheme'))
			 ->loadOptions(array(
				 'light' => ___('Light'),
				 'dark' => ___('Dark')
			 ));
		$form->setDefault('fb_scheme', 'light');
		
		// Google+
		$fs = $form->addFieldset()->setLabel(___('Google+ Settings'));
		$fs->addCheckboxedGroup('google_enabled')->setLabel(___("Google+ Enabled?\n"));
		$fs->addText('google_url', array('size' => 40))->setLabel(___("Share URL\n".'The URL you want shared on Google+'));
		$form->setDefault('google_url', ROOT_URL);
		
		// LinkedIn
		$fs = $form->addFieldset()->setLabel(___('LinkedIn Settings'));
		$fs->addCheckboxedGroup('linkedin_enabled')->setLabel(___("LinkedIn Enabled?\n"));
		$fs->addText('linkedin_url', array('size' => 40))->setLabel(___("Share URL\n".'The URL you want shared on LinkedIn'));
		$form->setDefault('linkedin_url', ROOT_URL);
		
		// Twitter
		$fs = $form->addFieldset()->setLabel(___('Twitter Settings'));
		$fs->addCheckboxedGroup('tweet_enabled')->setLabel(___("Twitter Share Enabled?\n"));
		$fs->addText('tweet_url', array('size' => 40))->setLabel(___("Tweet URL\n".'The URL you want shared on Twitter'));
		$form->setDefault('tweet_url', ROOT_URL);
		$fs->addText('tweet_text', array('size' => 40))->setLabel(___("Tweet Text\n".'The default text of the tweet'));;
		$form->setDefault('tweet_text', 'Currently making an order with '.$this->getDi()->config->get('site_title'));
		
		$fs->addCheckboxedGroup('follow_enabled')->setLabel(___("Twitter Follow Enabled?\n"));
		$fs->addText('follow_username', array('size' => 40))->setLabel(___("Twitter Username\n".'The user you want people to follow on Twitter'));;
		
		
		$form->addFieldsPrefix('misc.social-coupon.');
		$event->addForm($form);
    }

    // called ONCE per invoice - when first payment received or on free signup
    function onInvoiceStarted(Am_Event $event)
    {  
        // Delete the social coupon cookie as order made
        Am_Controller::setCookie(self::SC_COOKIE, null, time() - 3600*24, "/"); // Expire cookie
        unset($_COOKIE[self::SC_COOKIE]);
        return;
    }

    // called each payment - including recurring. NB: not free signup
    function onPaymentAfterInsert(Am_Event_PaymentAfterInsert $event)
    {
        // Only process here if a recurring payment. First payment handled by onInvoiceStarted
        if ($event->getInvoice()->getPaymentsCount() == 1) return;
        // Do any recurring actions here
        return;
    }
    
    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        switch ($action = $request->getActionName())
        {
            case 'verify':
                // Set cookie and redirect back to signup page.
                $md5 = $request->getParam('sc');
                if (!$md5)
                    throw new Am_Exception_InputError("Incomplete request received");
            
                // Verify coupon
                if (md5($this->getConfig('coupon')) == $md5) {
                    Am_Controller::setCookie(self::SC_COOKIE, $md5, time() + 3600*24*365, "/"); // Valid 1 year
                    $_COOKIE[self::SC_COOKIE] = $md5;
                    
                    // Build JSON response
                    $out = array();
                    $out['c'] = $this->getConfig('coupon');
                    $out['t'] = $this->getConfig('thanks_title');
                    $out['m'] = $this->getConfig('thanks_message');
                    $out = json_encode($out, JSON_FORCE_OBJECT);
                    if ($this->getConfig('debug')) $this->getDi()->errorLogTable->log('Social Coupon: Verify returned: '.$out);
                    echo $out;
                }
                break;
            default:
                throw new Am_Exception_InputError("Invalid request: [$action]");
        }
    }

    function getReadme()
    {
        $version = self::PLUGIN_REVISION;
        return <<<CUT
<strong>Social Coupon Plugin v$version</strong> The Social Coupon plugin will allow you to reward social media users with a discount
coupon for performing a social action (Like/Gplus/Tweet/Follow) on your site. <strong>Instructions</strong> 1. Upload this plugin file to: <strong>amember/application/default/plugins/misc/</strong> folder.

 2. Enable the plugin at <strong>aMember Admin -&gt; Setup/Configuration -&gt; Plugins</strong> 3. Configure the plugin at <strong>aMember Admin -&gt; Setup/Configuration -&gt; Social Coupon</strong> a. <strong>Coupon Code</strong> - 
       Enter the coupon code you wish to use for social actions.
       NB: If you get a warning message in the dashboard that this plugin is not
       configured then the coupon you have listed is invalid (e.g used or expired).
      
    b. <strong>Debug Mode</strong> - 
       Setting this option will cause debug messages to be written to the error log,
       in addition to regular error messages. Leaving it off will result in only
       error messages being written to the log.
       
    c. <strong>Popup Mode</strong> - 
       Setting this option will cause the social coupon box to be hidden by default,
       and a button will appear next to the coupon input. When the button is clicked,
       the social coupon box will appear as a 'lightbox' popup.
       
    d. <strong>Popup Button Text</strong> -
       The text of the Popup Button, which appears next to the coupon input when 
       popup mode is selected above.

    c. <strong>Share Title/Message</strong> -
       This are shown to the member BEFORE they complete a social action.
      
    d. <strong>Thanks Title/Message</strong> -
       This are shown to the member AFTER they complete a social action. You may
       optionally wish to include the coupon code here too.
       
    e. <strong>Applicable Signup Forms</strong> - 
		Select the Signup forms the Social Coupon should appear on.
	
	f. <strong>Facebook / Twitter / Google Plus Settings</strong> -
       These options allow you to specify custom URLs to share, so for example,
       you might use your facebook fan page for Facebook, but your membership site
       URL for Google Plus. You can also enable and disable each individually,
       in case you don't want all social actions to qualify for the coupon.
      
    g. Save plugin settings.
    
   
-------------------------------------------------------------------------------   
 
Copyright 2012 (c) Rob Woodgate, Cogmentis Ltd. All Rights Reserved
				
This file may not be distributed unless permission is given by author.

This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.

For support (to report bugs and request new features) visit: <a href="http://www.cogmentis.com/">http://www.cogmentis.com/</a><img src="http://www.cogmentis.com/lcimg/social.jpg" /> -------------------------------------------------------------------------------
CUT;
    }

}