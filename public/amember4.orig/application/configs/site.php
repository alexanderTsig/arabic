<?php

if (!defined('INCLUDED_AMEMBER_CONFIG')) 
    die("Direct access to this location is not allowed");
  
/*
*  aMember Pro site customization file
*
*  Rename this file to site.php and put your site customizations, 
*  such as fields additions, custom hooks and so on to this file
*  This file will not be overwritten during upgrade
*                                                                               
*/

Am_Di::getInstance()->hook->add('userMenu', 'onUserMenu');

function onUserMenu($event) {
	$menu = $event->getMenu();
	$menu->removePage($menu->findOneBy('id', 'helpdesk')); 
}

Am_Di::getInstance()->hook->add(Am_Event::USER_MENU, 'myAddMenuItem');
 
function myAddMenuItem(Am_Event $event)
{
    $menu = $event->getMenu();
    $menu->addPage(
        array(
            'id' => 'logout',
            'uri' => '/amember4/logout',
            'label' => "Log Out",
            'order' => 500
    ));
}


Am_Di::getInstance()->productTable->customFields()->add(
	new Am_CustomFieldText(
		'thankyou_redirect',
		'Custom thank you page URL',
		'A URL to a custom thank page where customers will be redirected to after purchasing this product. Leave empty for standard aMember behavior.'
	)
);


// Custom thank you redirection by product
function redirectThankyou(Am_Event $event){
	// get list of product objects
	$product_list = $event->getInvoice()->getProducts();
	foreach ($product_list as $product){
		// find a product, may only be one, that has redirect configured
		if (trim($product->data()->get('thankyou_redirect'))){
			header('location: '.$product->data()->get('thankyou_redirect'));
			exit();
		}
	}
}

Am_Di::getInstance()->hook->add(Am_Event::THANKS_PAGE, 'redirectThankyou');