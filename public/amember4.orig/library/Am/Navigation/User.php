<?php

/**
 * User menu at top of member controller
 * @package Am_Utils 
 */
class Am_Navigation_User extends Zend_Navigation
{
    function addDefaultPages()
    {
        $this->addPage(array(
            'id' => 'member',
            'controller' => 'member',
            'label' => ___('Dashboard'),
            'order' => 0
        ));
        $this->addPage(array(
            'id' => 'add-renew',
            'controller' => 'signup',
            'action' => 'index',
            'label' => ___('Add/Renew Subscription'),
            'order' => 100,
        ));
        $this->addPage(array(
            'id' => 'payment-history',
            'controller' => 'member',
            'action' => 'payment-history',
            'label' => ___('Payments History'),
            'order' => 200,
        ));
        $this->addPage(array(
            'id' => 'profile',
            'controller' => 'profile',
            'label' => ___('Edit Profile'),
            'order' => 300,
        ));

        try {
            $user = Am_Di::getInstance()->user;
        } catch (Am_Exception_Db_NotFound $e) {
            $user = null;
        }

        if ($user) {
            $tree = Am_Di::getInstance()->resourceCategoryTable->getAllowedTree($user);
            $pages = array();
            foreach ($tree as $node) {
                $pages[] = $this->getContentCategoryPage($node);
            }

            if (count($pages))
                $this->addPages($pages);
        }


        Am_Di::getInstance()->hook->call(Am_Event::USER_MENU, array(
            'menu' => $this, 
            'user' => $user));
        
        /// workaround against using the current route for generating urls
        foreach (new RecursiveIteratorIterator($this, RecursiveIteratorIterator::SELF_FIRST) as $child)
            if ($child instanceof Zend_Navigation_Page_Mvc && $child->getRoute()===null)
                $child->setRoute('default');
    }

    protected function getContentCategoryPage($node) {

        $page = $node->self_cnt ? array(
            'id' => 'content-category-' . $node->pk(),
            'controller' => 'content',
            'action' => 'c',
            'label' => $node->title,
            'order' => 500 + $node->sort_order,
            'params' => array(
                'id' => $node->pk()
            )
        ) : array(
            'id' => 'content-category-' . $node->pk(),
            'uri' => 'javascript:;',
            'label' => $node->title,
            'order' => 500 + $node->sort_order,
        );

        $subpages = array();
        foreach ($node->getChildNodes() as $n)
            $subpages[] = $this->getContentCategoryPage($n);

        if (count($subpages)) {
            $page['pages'] = $subpages;
        }

        return $page;
    }
}