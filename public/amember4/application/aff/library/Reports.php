<?php

class Am_Report_AffClicks extends Am_Report_Date
{
    protected $aff_id;
    public function __construct()
    {
        $this->title = ___('Affiliate Clicks');
        $this->description = ___('number of affiliate program clicks');
        parent::__construct();
    }
    public function getPointField() {
        return 'cl.time';
    }
    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query(new AffClickTable, 'cl');
        $q->clearFields();
        $q->addField('COUNT(DISTINCT cl.remote_addr) AS clicks');
        $q->addField('COUNT(cl.log_id) AS clicks_all');
        if ($this->aff_id)
            $q->addWhere("aff_id = ?d", $this->aff_id);
        return $q;
    }
    function getLines()
    {
        return array(
            new Am_Report_Line("clicks", ___('Unique Clicks')),
            new Am_Report_Line("clicks_all", ___('All Clicks')),
        );
    }
    public function setAffId($aff_id)
    {
        $this->aff_id = (int)$aff_id;
    }
}

class Am_Report_AffStats extends Am_Report_Date
{
    protected $aff_id;

    public function __construct()
    {
        $this->title = ___('Affiliate Sales');
        $this->description = ___('affiliate program commissions');
    }

    public function getPointField() {
        return 'cl.date';
    }
    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query(new AffCommissionTable, 'cl');
        $q->clearFields();
        $q->addField("SUM(IF(cl.record_type='commission', cl.amount, -cl.amount)) AS commission");
        if ($this->aff_id)
            $q->addWhere("aff_id = ?d", $this->aff_id);
        return $q;
    }
    function getLines()
    {
        return array(
            new Am_Report_Line("commission", ___('Commission'), null, array('Am_Currency', 'render')),
        );
    }
    public function setAffId($aff_id)
    {
        $this->aff_id = (int)$aff_id;
    }
}

class Am_Report_AffSales extends Am_Report_Date
{
    protected $aff_id;

    public function __construct()
    {
        $this->title = ___('Affiliate Sales Number');
        $this->description = ___('number of sales by affiliate');
    }

    public function getPointField() {
        return 'cl.date';
    }
    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query(new AffCommissionTable, 'cl');
        $q->clearFields();
        $q->addField("COUNT(DISTINCT invoice_payment_id) AS sales");
        if ($this->aff_id)
            $q->addWhere("aff_id = ?d", $this->aff_id);
        return $q;
    }
    function getLines()
    {
        return array(
            new Am_Report_Line("sales", ___('Sales')),
        );
    }
    public function setAffId($aff_id)
    {
        $this->aff_id = (int)$aff_id;
    }
}