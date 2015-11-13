<?php



class Subusers_IndexController extends Am_Controller
{
    protected $_session;
    function indexAction()
    {
        $this->getDi()->auth->requireLogin($this->_request->getRequestUri());

        //$this->getModule()->checkAndUpdate($this->getDi()->user);

        $subusers_count = $this->getDi()->user->data()->get('subusers_count');
        if (empty($subusers_count))
            throw new Am_Exception_Security(___('Resellers-only page'));
        $this->view->headScript()->prependFile(REL_ROOT_URL . "/js.php?js=admin");
        $this->view->subusers_count = $subusers_count;

        $grid = Am_Grid_Editable_Subusers::factory($this->getDi()->user,
            $this->getRequest(), $this->view, $this->getDi());

        $pending = 0;
        foreach ($subusers_count as $v)
            if ($v['pending_count'])
                $pending+=$v['pending_count'];
        if ($pending)
        {
            $this->view->message = ___('You have too many subusers assigned to this account.  You may choose to remove %d users from your account', $pending);
        } else {
            if ($this->getDi()->config->get('subusers_cannot_delete')==2) // no pending accounts, user cannot delete
                $grid->actionDelete('delete');
        }

        $grid->runWithLayout('member/subusers.phtml');
    }

    function getSession(){
       return  $this->getModule()->getSession();
    }

    function _saveSession(){
        $sess = serialize($_SESSION);

        $this->getSession()->parent_user = array(
            'reseller_login' => $this->getDi()->auth->getUser()->login,
            'reseller_id' => $this->getDi()->auth->getUserId(),
            'session' => $sess
            );

        $this->getSession()->lock();
    }

    function _restoreSession(){
        if(is_array($this->getSession()->parent_user)){
            $session = unserialize($this->getSession()->parent_user['session']);
            session_unset();
            foreach($session as $k=>$v){
                $_SESSION[$k] = $v;
            }
        }
    }


    function restoreSessionAction()
    {
        if(!is_array($parent_user = $this->getSession()->parent_user))
            throw new Am_Exception_InternalError("Parent session is empty");

        $this->getDi()->auth->logout();
        $this->_restoreSession();
        $this->getDi()->auth->setUser($this->getDi()->userTable->load($parent_user['reseller_id']), $this->getRequest()->getClientIp())->onSuccess();
        $this->redirectLocation(REL_ROOT_URL . '/member');


    }
    function loginAsAction()
    {
        $this->getDi()->auth->requireLogin($this->_request->getRequestUri());

        if(!$this->getDi()->config->get('subusers_can_login'))
            throw new Am_Exception_InputError('Ability to login as user is not enabled');

        $user = $this->getDi()->auth->getUser();

        $child_id = $this->getRequest()->getFiltered('id');

        if(!$child_id)
            throw new Am_Exception_InputError("Empty user ID. Can't login");

        $child = $this->getDi()->getInstance()->userTable->load($child_id);

        if($child->subusers_parent_id != $user->pk())
            throw new Am_Exception_InputError("Permission denied. User assigned to another reseller!");

        $this->_saveSession();
        $this->getDi()->auth->setUser($child, $this->getRequest()->getClientIp())->onSuccess();
        $this->redirectLocation(REL_ROOT_URL . '/member');


    }

}
