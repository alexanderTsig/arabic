<?php
/**
* @version		$Id: legacy.php 9115 2007-10-03 00:14:30Z jinx $
* @package		Joomla
* @copyright	Copyright (C) 2005 - 2007 Open Source Matters. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.event.plugin');
jimport('joomla.form.form');

class plgSystemaMember extends JPlugin
{
	function plgSystemaMember(& $subject, $config)
	{
		parent::__construct($subject, $config);
	}
	
	function onAfterInitialise()
	{
		global $mainframe;
		
		$plugin =& JPluginHelper::getPlugin('system', 'amember');
		$pluginParams = new JParameter( $plugin->params );
//		$pluginParams = new JForm( $plugin->params );
		$amemberRoot = $pluginParams->get( 'amemberroot', '/amember' );
//		$amemberRoot = $pluginParams->getField( 'amemberroot', '/amember' );

		if ($amemberRoot)
		{
			if (substr($amemberRoot,-1) == '/')
			$amemberRoot = strlen($amemberRoot) == 1 ? '/' : substr($amemberRoot, 0, -1);
		} else {
			$amemberRoot = '/amember';
		}
		
		$option = JRequest::getCmd('option');
		$task = JRequest::getCmd('task');
		$view = JRequest::getCmd('view');
		$return = JRequest::getCmd('return');
		$return = base64_decode($return);
        if (!$return) 
            $return = $_SERVER['PHP_SELF'];
        $return = preg_replace('/[\n\r]/', ' ', $return);
		
		if (strstr($_SERVER['REQUEST_URI'],'/logout') || @$task == 'logout')
		{
			$url = "$amemberRoot/logout?amember_redirect_url=" . $_SERVER['PHP_SELF'];
			header("Location: $url");
			exit();		
		}
		
		if (strstr($_SERVER['REQUEST_URI'],'/component/user') || @$option == 'com_user')
		{
			if (
                @in_array(@$task, array('requestreset', 'remindusername'))
                || strstr($_SERVER['REQUEST_URI'],'/reset')
                || strstr($_SERVER['REQUEST_URI'],'/remind')
                || @in_array(@$view, array('reset','remind'))
            )
			{
				header("Location: $amemberRoot/login?amember_redirect_url=$return");
				exit();
			}
			if (
                @in_array(@$task, array('register','register_save','activate'))
                || @in_array(@$view, array('register','register_save','activate'))
            )
			{
				header("Location: $amemberRoot/signup");
				exit();	
			}
		}
		
		if (strstr($_SERVER['REQUEST_URI'],'/your-details') || @$option == 'com_user')
		{
			if (@in_array(@$task, array('save', 'edit')) || strstr($_SERVER['REQUEST_URI'],'/your-details'))
			{
				header("Location: $amemberRoot/profile");
				exit();			
			}
		}
        if (@$task == 'login' && @$option == 'com_user')
		{
            $script = '
                <html>
                <head><title>Redirecting</title></head>
                <body>
                    <script type="text/Javascript">
                        window.onload = function(){
                            var form=document.createElement("form");
                            form.action="' . $amemberRoot . '/login/";
                            form.method="POST";
                            document.body.appendChild(form);

                            var login = document.createElement("input");
                            login.type="hidden";
                            login.name = "amember_login";
                            login.value = "' . JRequest::getVar('username') . '";
                            form.appendChild(login);
                                
                            var pass = document.createElement("input");
                            pass.type ="hidden";
                            pass.name = "amember_pass";
                            pass.value = "' . JRequest::getVar('passwd') . '";
                            form.appendChild(pass);

                            var url = document.createElement("input");
                            url.type ="hidden";
                            url.name = "amember_redirect_url";
                            url.value = "' . $return . '";
                            form.appendChild(url);

                            form.submit();
                        }
                    </script>
                </body>
                </html>
            ';
            echo $script;
            exit();
		}
	}

}

?>
