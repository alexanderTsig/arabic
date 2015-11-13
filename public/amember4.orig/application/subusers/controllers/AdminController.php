<?php

class Subusers_AdminController extends Am_Controller
{
    function preDispatch()
    {
        $this->user_id = $this->getInt('user_id');
        $this->view->user_id = $this->user_id;
    }

    public function checkAdminPermissions(Admin $admin) {
        return $admin->hasPermission(Bootstrap_Subusers::ADMIN_PERM_ID);
    }
    function tabAction()
    {
        $this->setActiveMenu('users-browse');

        $user = $this->getDi()->userTable->load($this->user_id);
        
        $subusers_count = $user->data()->get('subusers_count');
        if (empty($subusers_count))
            throw new Am_Exception_InputError(___('This user is not a reseller'));
        
        $this->view->subusers_count = $subusers_count;
        
        $grid = Am_Grid_Editable_Subusers::factoryAdmin($user,
            $this->getRequest(), $this->view, $this->getDi());
        $grid->setPermissionId(Bootstrap_Subusers::ADMIN_PERM_ID);
        $this->view->title = ___('Subusers');
        $grid->runWithLayout('admin/subusers.phtml');
    }
}
