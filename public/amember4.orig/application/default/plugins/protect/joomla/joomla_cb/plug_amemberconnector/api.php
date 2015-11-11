<?php
class API_Curl
{

    private $ch;
    private $response;
    private $errno;
    private $error;
    private $info;
    

    public function __construct($url, $args = false)
    {
        $this->ch = curl_init();
        if (!$this->ch)
        {
            $this->error = 'Curl extention is not available.';
            return;
        }
        curl_setopt($this->ch, CURLOPT_VERBOSE, 1);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        if ($args)
        {
            curl_setopt($this->ch, CURLOPT_POST, count($args));
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($args, '', '&'));
        }
    }
    
    public function getError()
    {
        return $this->error;
    }

    public function addParameters() // additional parametres
    {
        if ($this->error)
            return;
    }

    public function sendRequest()
    {
        if ($this->error)
            return;
        $this->response = curl_exec($this->ch);
        $this->errno = curl_errno($this->ch);
        $this->error = curl_error($this->ch);
        $this->info = curl_getinfo($this->ch);
        curl_close($this->ch);
//print_rr($this->_errno);
//print_rre($this->response);
    }

    public function checkResponse()
    {
        if ($this->errno)
            $this->error = 'Curl error #' . $this->errno . ': ' . $this->error;
        if ($this->error)
            return false;
        return true;
    }
    
    public function getResponse()
    {
        if ($this->error)
            return $this->error;
        return $this->response;
    }
    
}

class API_Message
{
    private $response;
    private $message;
    private $error;
    
    public function __construct($response)
    {
        $this->response = json_decode($response, true);;
    }
    
    public function checkResponse()
    {
        if (!is_array($this->response))
        {
            $this->error = 'Bad response from server';
            return false;
        }
        if (isset($this->response['ok']) && !$this->response['ok'])
        {
            if (!empty($this->response['message']))
                $this->error = $this->response['message'];
            elseif (!empty($this->response['msg']))
                $this->error = $this->response['msg'];
            else
                $this->error = 'Unknow error';
            return false;
        }
//print_rre($this->response);
//print_rre($result);
        return true;
    }
    
    public function getError()
    {
        return $this->error;
    }
    
    public function getMessage()
    {
        return $this->message;
    }
    
    public function productsParse()
    {
        $result = array();
        foreach ($this->response as $key => $value)
        {
            if($key === '_total')
            {
                $result[$key] = $value;
                continue;
            }
//            $result[$value['product_id']] = $value['title'];
            $result[] = array(
                'product_id' => $value['product_id'],
                'title' => $value['title'],
                'first_period' => $value['nested']['billing-plans'][0]['first_period'],
            );
//            $result[] = $value;
        }
        $this->message = $result;
    }
    public function userParse()
    {
//print_rre($this->response);
        $result = array();
        foreach ($this->response as $key => $value)
        {
            if(false !== strpos($key, '_'))
                continue;
            $result[] = array(
                'amemberId' => $value['user_id'],
                'amemberLogin' => $value['login'],
                'amemberEmail' => $value['email'],
            );
        }
        $this->message = (count($result) == 1) ? $result[0] : $result;
    }
    public function checkParse()
    {
        $this->message = true;
    }
    public function accessParse()
    {
        $this->message = true;
    }
}

