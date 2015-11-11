<?php
/**
* @version $Id: amemberconnector.php,v 1.3 2006/06/15 12:54:11 avp Exp $
* @package Community Builder
* @subpackage Amember connector for CB 1.0 RC2
* @copyright (C) cgi-central.net, Alex Scott
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
*/
// ensure this file is being included by a parent file
if ( ! ( defined( '_VALID_CB' ) || defined( '_JEXEC' ) || defined( '_VALID_MOS' ) ) ) { die( 'Direct Access to this location is not allowed.' ); }

global $_PLUGINS, $_CB_framework;
require_once ($_CB_framework->getCfg('absolute_path').'/components/com_comprofiler/plugin/user/plug_amemberconnector/api.php');

// The plugin reacts at the following Cb events
$_PLUGINS->registerFunction( 'onUserActive' , 'activateInAmember', 'getAmemberTab' );
//This must happen before deleting because the AmemberID get deleted as well
//This must happen before deleting because the AmemberID get deleted as well
//**********////
$_PLUGINS->registerFunction( 'onBeforeDeleteUser' , 'deleteFromAmember', 'getAmemberTab' );
$_PLUGINS->registerFunction( 'onAfterLogin' , 'afterLogin', 'getAmemberTab' );
$_PLUGINS->registerFunction( 'onAfterLogout' , 'afterLogout', 'getAmemberTab' );
$_PLUGINS->registerFunction( 'onBeforeUserUpdate' , 'checkUsersAmember', 'getAmemberTab');
$_PLUGINS->registerFunction( 'onBeforeUpdateUser' , 'checkUsersAmember', 'getAmemberTab');
$_PLUGINS->registerFunction( 'onAfterUserUpdate' , 'updateToAmember', 'getAmemberTab' );
$_PLUGINS->registerFunction( 'onAfterUpdateUser' , 'updateToAmember', 'getAmemberTab' );
$_PLUGINS->registerFunction( 'onBeforeNewUser' , 'checkUsersAmember', 'getAmemberTab' );
$_PLUGINS->registerFunction( 'onAfterNewUser' , 'registerToAmember', 'getAmemberTab' );
$_PLUGINS->registerFunction( 'onBeforeUserRegistration' , 'checkUsersAmember', 'getAmemberTab' );
$_PLUGINS->registerFunction( 'onAfterUserRegistration' , 'registerToAmember', 'getAmemberTab' );
$_PLUGINS->registerFunction( 'onNewPassword' , 'newPassword', 'getAmemberTab' );

function print_rr($x, $label="DEBUG"){
    print "<PRE>$label<br>"; print_r($x); print "</PRE>";
}
function print_rre($x, $label="DEBUG"){
    print "<PRE>$label<br>"; print_r($x); print "</PRE>";
    die();
}
/**
* @The following will create the tab for CB profile
*/
class getAmemberTab extends cbTabHandler {  
    private function query($method, $data=array(), $methodRequest = 'GET', $id = null)
    {
        global $_PLUGINS;
        $urlRequest = trim($this->params->_params->amURL, '/') . '/api/' . $method;
        if ($id)
        {
            $urlRequest .= '/' . $id;
        }
        if ($methodRequest == 'GET')
        {
            $data += array(
                '_key' => $this->params->_params->amApiKey
            );
            $urlRequest .= '?' . http_build_query($data, '', '&');
            $data = false;
        } else
        {
            $data += array(
                '_key' => $this->params->_params->amApiKey,
                '_method' => $methodRequest,
            );
        }
        $query = new API_Curl($urlRequest, $data);
        $query->sendRequest();
        
        if (!$query->checkResponse())
        {
            //@todo: error processing
            $_PLUGINS->raiseError(0);
            $_PLUGINS->_setErrorMSG($query->getError());
            return false;
        }
        
        $result = new API_Message($query->getResponse());
        if(!$result->checkResponse())
        {
            //@todo: error processing
            $_PLUGINS->raiseError(0);
            $_PLUGINS->_setErrorMSG($result->getError());
            return false;
        }
        
        switch ($method)
        {
            case 'products':
                $result->productsParse();
                break;
            
            case 'users':
                $result->userParse();
                break;
            
            case 'check-access/by-login-pass':
                $result->checkParse();
                break;

            case 'access':
                $result->accessParse();
                break;
        }
        
        return $result->getMessage();
    }
    
    public function listProducts()
    {
        $params = $this->params->_params;
        if (!$params->amURL)
        {
            $s = "To see actual product options, 
                  configure 'URL of aMember' and Api Key first,
                  then back to this page and you will see
                  product choices";
        } else
        {
            $count = 100;
            $products = $this->query("products", array('_count' => $count));
            if (!is_array($products))
            {
                if ($params->amURL == '') $s .= "<b>Please enter aMember URL</b> into corresponding field above<br>";
                if ($params->amApiKey == '') $s .= "<b>Please enter Api Key</b> into corresponding field above<br>";
            } else
            {
                if (isset($products['_total']) && $products['_total'] >= count($products))
                {
                    if ($count >= count($products))
                        $count = count($products) - 1;
                    $residualQueries = round(($products['_total'] - $count)/$count);
                    for ($i = 1; $i <= $residualQueries; $i++)
                        $products = array_merge($products, $this->query("products", array('_count' => $count, '_page' => $i)));
                }
                $s = "<SELECT NAME='params[amProductId]'>";
                $s .= "<OPTION VALUE=''>== Do not add a subscription ==\n";
                $sel = $this->params->_params->amProductId;
                foreach ($products as $product)
                {
                    if (!is_array($product))
                        continue;
                    $id = $product['product_id'];
                    $title = $product['title'];
                    $sc = $sel == $id ? 'SELECTED' : '';
                    $s .= "<OPTION VALUE='$id' $sc>$title</OPTION>\n";
                }
                $s .= "</SELECT>";
            }
        }
        return $s;
    }
    
    public function quote($s)
    {
        return mysql_real_escape_string($s);
    }
    
    public function setAmemberId(&$row, $id)
    {
        global $_CB_database;
        settype($id, 'integer');
        $_CB_database->setQuery( "UPDATE #__comprofiler SET cb_amemberid = '$id' WHERE user_id='{$row->id}'" );
        return $_CB_database->query();
    }
    
    public function getAmemberId(&$row)
    {
        global $_CB_database;
        $_CB_database->setQuery("SELECT cb_amemberid FROM #__comprofiler WHERE user_id='{$row->id}' LIMIT 1");
        $items = $_CB_database->loadResult();
        return intval($items);
    }

    public function checkUsersAmember(&$row)
    {
        global $_PLUGINS;
        $id = $this->getAmemberId($row);

        $user = $this->query('users', array('_filter' => array('login' => $row->username)));
        if ($id)
        {
            if (!empty($user) && $user['amemberId'] != $id)
            {
                $_PLUGINS->raiseError(0);
                $_PLUGINS->_setErrorMSG("This username is already being used! Please choose another username.");
            }
        } else
        {
            $password = $_POST['password'];
            
            if (!empty($user))
            {
                if (!$this->query('check-access/by-login-pass', array('login' => $row->username, 'pass' => $password)))
                {
                    $_PLUGINS->raiseError(0);
                    $_PLUGINS->_setErrorMSG("This username is already being used! Please choose another username.");
                } elseif($row->email != $user['amemberEmail'])
                {
                    $_PLUGINS->raiseError(0);
                    $_PLUGINS->_setErrorMSG("This username is already being used with other email! Please choose another username or enter same email.");
                }
            }
        }
    }
    
    public function registerToAmember(&$row, $rowExtras)
    {
        
        $amUser = $this->query('users', array('_filter' => array('login' => $row->username)));

        if(empty($amUser))
        {
            list($name_f, $name_l) = preg_split('/\s+/', $row->name, 2);
            $vars = array(
                        'login' => $row->username,
                        'pass' => $_POST['password'],
                        'email' => $row->email,
                        'name_f' => $name_f,
                        'name_l' => $name_l,
                        'is_approved' => empty($_POST['approved']) ? 0 : 1,
                );
            $amUser = $this->query('users', $vars, 'POST');
        }

        $this->setAmemberId($row, $amUser['amemberId']);
        
        if ($this->params->_params->amProductId)
        {
            $amProduct = $this->query("products", array('_filter' => array('product_id' => $this->params->_params->amProductId)));
            $now = time();
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $amProduct[0]['first_period']))
            {
                $expire = $product[0]['first_period'];
            } else
            {
                preg_match('/(\d+)(\w)/', $amProduct[0]['first_period'], $matches);
                $period = '+ ' . $matches[1] . ' ';
                switch ($matches[2])
                {
                    case 'd':
                        $period .= 'day';
                        break;
                    case 'm':
                        $period .= 'month';
                        break;
                    case 'y':
                        $period .= 'year';
                        break;
                }
                $expire = date('Y-m-d', strtotime($period, $now));
            }
            $vars = array(
                'user_id' => $amUser['amemberId'],
                'product_id' => $this->params->_params->amProductId,
                'begin_date' => date('Y-m-d', $now),
                'expire_date' => $expire,
                'transaction_id' => 'JOOMLA',
            );
            $this->query('access', $vars, 'POST');
        }
    }
    
    public function activateInAmember(&$row, $active)
    {
    }
    
    public function updateToAmember(&$row, $rowExtras)
    {
        $amUserId = $this->getAmemberId($row);
        if (!$amUserId)
            return false;
        $amUser = $this->query('users', array('_filter' => array('user_id' => $amUserId)));
        if (empty($amUser))
            return false;

        list($name_f, $name_l) = preg_split('/\s+/', $row->name, 2);
        $vars = array(
            'login' => $row->username,
            'email' => $row->email,
            'name_f' => $name_f,
            'name_l' => $name_l,
        );
        if (!empty($_POST['password']))
            $vars['pass'] = $_POST['password'];

        $u=$this->query('users', $vars, 'PUT', $amUserId);
    }
    
    public function newPassword($user_id, $new_pass)
    {
        $row = new StdClass();
        $row->id = $user_id;
        $amUserId = $this->getAmemberId($row);
        if (!$amUserId)
            return false;
        $amUser = $this->query('users', array('_filter' => array('user_id' => $amUserId)));
        if (empty($amUser))
            return false;

        $vars = array(
            'pass' => $new_pass
        );
        $this->query('users', $vars, 'PUT', $amUserId);
    }

    public function deleteFromAmember($row){
        $amUserId = $this->getAmemberId($row);
        if (!$amUserId)
            return false;
        
        $this->query('users', array(), 'DELETE', $amUserId);
    }
    

    public function afterLogin(&$row)
    {
        $script = '
            <script type="text/javascript">
                window.onload = function(){
                    var form=document.createElement("form");
                    document.body.appendChild(form);
                    form.method="POST";

                    var login = document.createElement("input");
                    form.appendChild(login);
                    login.type="hidden";
                    login.name = "amember_login";
                    login.value = "' . $_POST['username'] . '";
                    var pass = document.createElement("input");
                    form.appendChild(pass);
                    pass.type ="hidden";
                    pass.name = "amember_pass";
                    pass.value = "' . $_POST['passwd'] . '";
                    var url = document.createElement("input");
                    form.appendChild(url);
                    url.type ="hidden";
                    url.name = "amember_redirect_url";
                    url.value = "' . $this->getRedirectUrl() . '";
                    form.action="' . $this->params->_params->amURL . '/login";
                    form.submit();
                }
            </script>
        ';
        echo $script;
        exit();
    }
    
    public function afterLogout()
    {

        $url = $this->params->_params->amURL . "/logout?amember_redirect_url=" . $this->getRedirectUrl();
        header("Location: $url");
        exit();
    }
    
    private function getRedirectUrl()
    {
        $return = $_POST['return'];
        if (strpos($return, ':') !== false)
        {
            list($_, $return) = explode(':', $return);
            $return = base64_decode($return);
        } else
            $return = $_SERVER['HTTP_REFERER'];
        
        return $return;
    }
}
