<?php

/**
 * Class represents records from table newsletter_list
 * {autogenerated}
 * @property int $list_id 
 * @property string $title 
 * @property string $desc 
 * @property int $disabled 
 * @property int $auto_subscribe 
 * @property string $auto_unsubscribe
 * @property string $plugin_id
 * @property string $plugin_list_id
 * @see Am_Table
 */
class NewsletterList extends ResourceAbstract 
{
    const NEWSLETTER_LIST = 'newsletterlist';

    /**
     * @return Am_Newsletter_Plugin
     */
    public function getPlugin()
    {
        if (empty($this->plugin_id))
            $plugin_id = 'standard';
        else
            $plugin_id = $this->plugin_id;
        return $this->getDi()->plugins_newsletter->loadGet($plugin_id);
    }
    
    function enable()
    {
        // @todo disabled related subscriptions ? 
        $this->updateQuick('disabled', 0);
    }
    function disable()
    {
        // @todo disabled related subscriptions ? 
        $this->updateQuick('disabled', 1);
    }
    function setVars(array $vars)
    {
        $this->vars = serialize($vars);
        return $this;
    }
    function getVars()
    {
        if (empty($this->vars)) return array();
        return (array)unserialize($this->vars);
    }
}

class NewsletterListTable extends ResourceAbstractTable
{
    protected $_key = 'list_id';
    protected $_table = '?_newsletter_list';
    
    protected $useCache = true;
    
    public function getAccessType()
    {
        return NewsletterList::NEWSLETTER_LIST;
    }
    
    public function getAccessTitle()
    {
        return ___('Newsletters');
    }
    
    public function getPageId()
    {
        return 'newsletter';
    }
    
    function getAdminOptions()
    {
        return $this->_db->selectCol("SELECT list_id AS ARRAY_KEY, title FROM $this->_table WHERE IFNULL(disabled,0) = 0");
    }
    function getUserOptions()
    {
        return $this->_db->selectCol("SELECT list_id AS ARRAY_KEY, title FROM $this->_table t WHERE IFNULL(disabled,0) = 0
            ORDER BY (SELECT ras.sort_order FROM ?_resource_access_sort ras
             WHERE ras.resource_id=t.list_id AND ras.resource_type=? LIMIT 1)", NewsletterList::NEWSLETTER_LIST);
    }
    
    function getAllowed(User $user)
    {
        $ids = array(-99); // to avoid empty array errors
        foreach ($this->getDi()->resourceAccessTable->selectAllowedResources($user, NewsletterList::NEWSLETTER_LIST) as $r)
            $ids[] = $r['resource_id'];
        return $this->selectObjects("SELECT t.* FROM $this->_table t WHERE (t.list_id IN (?a) AND IFNULL(disabled,0) = 0)
             ORDER BY (SELECT ras.sort_order FROM ?_resource_access_sort ras
             WHERE ras.resource_id=t.list_id AND ras.resource_type=? LIMIT 1)", $ids, NewsletterList::NEWSLETTER_LIST);
    }
    
    function disableDisabledPlugins(array $enabledPlugins)
    {
        foreach ($this->selectObjects("SELECT * FROM $this->_table 
            WHERE plugin_id > '' AND (NOT plugin_id IN (?a))
            ", $enabledPlugins) as $list)
            $list->disable();
    }
    
    function syncLists(Am_Newsletter_Plugin $plugin, array $lists)
    {
        $existing = $disabled = array();
        foreach ($this->findByPluginId($plugin->getId()) as $r)
        {
            if ($r->disabled)
                $disabled[$r->plugin_list_id] = $r;
            else
                $existing[$r->plugin_list_id] = $r;
        }
        // disable exising lists
        foreach (array_diff_key($existing, $lists, $disabled) as $list)
        {
            $list->disable(); // not available on service side anymore?
        }
        // 
        foreach (array_diff_key($lists, $existing, $disabled) as $id => $list)
        {
            $r = $this->createRecord(array(
                'plugin_id' => $plugin->getId(),
                'plugin_list_id' => $id,
                'title' => $list['title'],
            ));
            $r->insert();
        }
        // list is now enabled
        foreach (array_intersect_key($disabled, $lists) as $list)
        {
            $list->enable();
        }
        //sync titles for existing lists
        foreach (array_intersect_key($existing, $lists) as $list)
        {
            if ($list->title != $lists[$list->plugin_list_id]['title'])
            {
                $list->updateQuick('title', $lists[$list->plugin_list_id]['title']);
            }
        }
    }
}