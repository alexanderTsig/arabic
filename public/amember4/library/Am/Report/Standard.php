<?php

/**
 * Commonly used reports
 */
class Am_Report_Income extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Income Report - payments minus refunds');
        $this->description = "";
    }

    // we have a VERY complex query here, so we will run it directly
    // without using Am_Query
    // Simulate FULL OUTER JOIN - not implemened in MYSQL
    // Usually it is better to avoid it!
    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('p.dattm');
        $exprb = $this->quantity->getSqlExpr('r.dattm');
        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT point, SUM(amt) as amt FROM (
            SELECT $expra AS point, ROUND(IFNULL(SUM(p.amount/p.base_currency_multi),0),2) AS amt
                FROM ?_invoice_payment p
                WHERE p.dattm BETWEEN ? AND ?
                GROUP BY $expra
            UNION ALL
            SELECT $exprb AS point, ROUND(SUM(-ABS(r.amount)/r.base_currency_multi),2) AS amt
                FROM ?_invoice_refund r
                WHERE
                r.dattm BETWEEN ? AND ?
                GROUP BY $exprb
            ) AS t GROUP BY point
        ", $this->start, $this->stop, $this->start, $this->stop
        );
    }

    function getLines()
    {
        return array(
            new Am_Report_Line("amt", ___('Payments Amount') . ', ' . Am_Currency::getDefault(), null, array('Am_Currency', 'render'))
        );
    }
}

class Am_Report_Tax extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Tax Report');
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoicePaymentTable, 'p');
        $q->clearFields();
        $q->addField('ROUND(SUM(tax/base_currency_multi), 2)', 'tax');

        return $q;
    }

    function getLines()
    {
        $ret = array();
        $ret[] = new Am_Report_Line('tax', ___('Tax Amount') . ', ' . Am_Currency::getDefault(), null, array('Am_Currency', 'render'));
        return $ret;
    }

}

class Am_Report_TaxCountry extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Tax by Customer Country');
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoicePaymentTable, 'p');
        $q->clearFields();
        $q->leftJoin('?_user', 'u', 'p.user_id=u.user_id');
        foreach ($this->getCountries() as $country) {
            $q->addField("ROUND(SUM(IF(country='$country', tax/base_currency_multi, 0)), 2)", 'tax_' . $country);
        }

        return $q;
    }

    function getLines()
    {
        $ret = array();
        $country_name = $this->getDi()->countryTable->getOptions();
        foreach ($this->getCountries() as $country) {
            $ret[] = new Am_Report_Line('tax_' . $country, sprintf('%s (%s), %s', $country_name[$country], $country, Am_Currency::getDefault()), null, array('Am_Currency', 'render'));
        }
        return $ret;
    }

    function getCountries()
    {
        return $this->getDi()->db->selectCol('SELECT DISTINCT country FROM ?_user WHERE country<>?', '');
    }

}

class Am_Report_PaymentVsRefund extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Payments vs Refunds');
        $this->description = "";
    }

    // we have a VERY complex query here, so we will run it directly
    // without using Am_Query
    // Simulate FULL OUTER JOIN - not implemened in MYSQL
    // Usually it is better to avoid it!
    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('p.dattm');
        $exprb = $this->quantity->getSqlExpr('r.dattm');
        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT point, SUM(pmt) as pmt, SUM(rfd) as rfd, SUM(pmt-rfd) AS amt FROM (
            SELECT $expra AS point, ROUND(IFNULL(SUM(p.amount/p.base_currency_multi),0),2) AS pmt, 0 AS rfd
                FROM ?_invoice_payment p
                WHERE p.dattm BETWEEN ? AND ?
                GROUP BY $expra
            UNION ALL
            SELECT $exprb AS point, 0 AS pmt, ROUND(SUM(ABS(r.amount)/r.base_currency_multi),2) AS rfd
                FROM ?_invoice_refund r
                WHERE
                r.dattm BETWEEN ? AND ?
                GROUP BY $exprb
            ) AS t GROUP BY point
        ", $this->start, $this->stop, $this->start, $this->stop
        );
    }

    function getLines()
    {
        return array(
            new Am_Report_Line("amt", ___('Income') . ', ' . Am_Currency::getDefault(), null, array('Am_Currency', 'render')),
            new Am_Report_Line("pmt", ___('Payment') . ', ' . Am_Currency::getDefault(), '#488f37', array('Am_Currency', 'render')),
            new Am_Report_Line("rfd", ___('Refund') . ', ' . Am_Currency::getDefault(), '#BA2727', array('Am_Currency', 'render')),
        );
    }

}

class Am_Report_Paysystems extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Payments by payment system breakdown');
        $this->description = "";
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoicePaymentTable, 'p');
        $q->clearFields();

        foreach ($this->getPaysystems() as $k => $ps) {
            $ps = $q->escape($ps);
            $q
                ->addField("ROUND(SUM(IF(p.paysys_id=$ps, p.amount/p.base_currency_multi, 0)),2)\n", 'amt_' . $k);
        }
        return $q;
    }

    function getPaysystems()
    {
        static $cache;
        if (!$cache)
            $cache = $this->getDi()->db->selectCol("SELECT DISTINCT paysys_id FROM ?_invoice_payment");
        return $cache;
    }

    function getLines()
    {
        $ret = array();
        foreach ($this->getPaysystems() as $k => $ps) {
            $ret[] = new Am_Report_Line('amt_' . $k, ucfirst($ps), null, array('Am_Currency', 'render'));
        }
        return $ret;
    }

}

class Am_Report_Products extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Payments by products breakdown');
        $this->description = "";
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addMagicSelect('products')->setLabel(___("Products\nkeep empty to report all products"));
        $sel->loadOptions($this->getDi()->productTable->getOptions());
    }

    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('p.dattm');

        $fields = array();
        foreach ($this->getProducts() as $k => $v) {
            $fields[] = "ROUND(
                            SUM(
                                    IFNULL((
                                        SELECT p.amount * LEAST(1, IF(p.is_first, ii.first_total/p.invoice_total, ii.second_total/p.invoice_total))
                                        FROM ?_invoice_item ii WHERE p.invoice_id=ii.invoice_id AND item_id=$k LIMIT 1
                                    ), 0)
                                ), 2) AS amt_$k";
        }

        $db = $this->getDi()->db;
        $db->query("DROP TEMPORARY TABLE IF EXISTS ?_invoice_payment_report_tmp");
        $db->query("CREATE TEMPORARY TABLE ?_invoice_payment_report_tmp (
            dattm DATETIME not null,
            invoice_id int not null,
            is_first smallint,
            amount decimal(12,2),
            invoice_total decimal(12,2)
        )
        ");
        $db->query("
            INSERT INTO ?_invoice_payment_report_tmp
            SELECT p.dattm, p.invoice_id
                ,i.first_total > 0 &&
                    NOT EXISTS (SELECT * FROM ?_invoice_payment pp
                        WHERE pp.invoice_id=p.invoice_id AND pp.invoice_payment_id < p.invoice_payment_id)
                    AS is_first
                ,p.amount / p.base_currency_multi
                ,(SELECT(IF(is_first, i.first_total, i.second_total))) AS invoice_total
            FROM ?_invoice_payment p
                LEFT JOIN ?_invoice i USING (invoice_id)
            WHERE dattm BETWEEN ? AND ? AND amount > 0
            HAVING invoice_total > 0
        ", $this->start, $this->stop);

        $fields = "\n," . implode("\n,", $fields);
        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT
                $expra as point
                $fields
            FROM ?_invoice_payment_report_tmp p
            GROUP BY $expra
            ", $this->start, $this->stop);
    }

    function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
                DISTINCT product_id as ARRAY_KEY, title
                FROM ?_product
                {WHERE product_id IN (?a)}
                ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    function getLines()
    {
        $ret = array();
        foreach ($this->getProducts() as $k => $ps) {
            $ret[] = new Am_Report_Line('amt_' . $k, ucfirst($ps), null, array('Am_Currency', 'render'));
        }
        return $ret;
    }

}

class Am_Report_ProductCategories extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Payments by product categories breakdown');
        $this->description = "";
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addMagicSelect('categories')->setLabel(___("Product categories\nkeep empty to report all categories"));
        $sel->loadOptions($this->getDi()->productCategoryTable->getAdminSelectOptions());
    }

    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('p.dattm');

        $fields = array();
        foreach ($this->getCetegoryProducts($this->getCategories()) as $k => $v) {
            array_push($v, -1);
            array_walk($v, create_function('$v', 'return (int)$v;'));
            $v = implode(',', $v);
            $fields[] = "ROUND(
                            SUM(
                                    IFNULL((
                                        SELECT SUM(p.amount * LEAST(1, IF(p.is_first, ii.first_total/p.invoice_total, ii.second_total/p.invoice_total)))
                                        FROM ?_invoice_item ii WHERE p.invoice_id=ii.invoice_id AND item_id IN ($v)
                                    ), 0)
                                ), 2) AS amt_$k";
        }

        $db = $this->getDi()->db;
        $db->query("DROP TEMPORARY TABLE IF EXISTS ?_invoice_payment_report_tmp");
        $db->query("CREATE TEMPORARY TABLE ?_invoice_payment_report_tmp (
            dattm DATETIME not null,
            invoice_id int not null,
            is_first smallint,
            amount decimal(12,2),
            invoice_total decimal(12,2)
        )
        ");
        $db->query("
            INSERT INTO ?_invoice_payment_report_tmp
            SELECT p.dattm, p.invoice_id
                ,i.first_total > 0 &&
                    NOT EXISTS (SELECT * FROM ?_invoice_payment pp
                        WHERE pp.invoice_id=p.invoice_id AND pp.invoice_payment_id < p.invoice_payment_id)
                    AS is_first
                ,p.amount / p.base_currency_multi
                ,(SELECT(IF(is_first, i.first_total, i.second_total))) AS invoice_total
            FROM ?_invoice_payment p
                LEFT JOIN ?_invoice i USING (invoice_id)
            WHERE dattm BETWEEN ? AND ? AND amount > 0
            HAVING invoice_total > 0
        ", $this->start, $this->stop);

        $fields = count($fields) ? "\n," . implode("\n,", $fields) : '';
        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT
                $expra as point
                $fields
            FROM ?_invoice_payment_report_tmp p
            GROUP BY $expra
            ", $this->start, $this->stop);
    }

    function getCetegoryProducts($categories)
    {
        $res = $this->getDi()->productCategoryTable->getCategoryProducts();
        foreach ($res as $k => $v)
            if (!array_key_exists($k, $categories))
                unset($res[$k]);

        return $res;
    }

    function getCategories()
    {
        $res = array();
        $options = $this->getDi()->productCategoryTable->getAdminSelectOptions();
        $vars = $this->form->getValue();
        if (!empty($vars['categories'])) {
            foreach ($vars['categories'] as $cat_id)
                $res[$cat_id] = $options[$cat_id];
        } else {
            $res = $options;
        }
        return $res;
    }

    function getLines()
    {
        $ret = array();
        foreach ($this->getCategories() as $k => $ps) {
            $ret[] = new Am_Report_Line('amt_' . $k, ucfirst($ps), null, array('Am_Currency', 'render'));
        }
        return $ret;
    }

}

class Am_Report_NewVsExisting extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Payments by New vs Existing members');
        $this->description = "";
    }

    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('p.dattm');
        $exprpp = $this->quantity->getSqlExpr('pp.dattm');

        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT
                $expra as point,
                ROUND(SUM(p.amount / p.base_currency_multi),2) as total,
                ROUND(SUM(IF(
                    EXISTS (SELECT * FROM ?_invoice_payment pp WHERE user_id=p.user_id AND $exprpp < point)
                , 0, p.amount / p.base_currency_multi)),2) as new,
                ROUND(SUM(IF(
                    EXISTS (SELECT * FROM ?_invoice_payment pp WHERE user_id=p.user_id AND $exprpp < point)
                , p.amount / p.base_currency_multi, 0)),2) as existing
            FROM ?_invoice_payment p
            WHERE dattm BETWEEN ? AND ? AND amount > 0
            GROUP BY $expra
            ", $this->start, $this->stop);
    }

    function getLines()
    {
        $ret = array();
        $ret[] = new Am_Report_Line('total', ___('Payments total'), null, array('Am_Currency', 'render'));
        $ret[] = new Am_Report_Line('existing', ___('Payments from existing customers'), null, array('Am_Currency', 'render'));
        $ret[] = new Am_Report_Line('new', ___('Payments from new customers'), null, array('Am_Currency', 'render')); // who did not pay earlier in the point period
        return $ret;
    }

}

class Am_Report_SignupsCount extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Count of user signups');
        $this->description = ___('including pending records');
    }

    public function getPointField()
    {
        return 'u.added';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->userTable, 'u');
        $q->clearFields();
        $q->addField('COUNT(user_id)', 'cnt');

        return $q;
    }

    function getLines()
    {
        $ret = array();
        $ret[] = new Am_Report_Line('cnt', ___('Count of signups'));
        return $ret;
    }

}

class Am_Report_PurchaseCount extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Count of product purchase');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addMagicSelect('products')->setLabel(___("Products\nkeep empty to report all products"));
        $sel->loadOptions($this->getDi()->productTable->getOptions());
    }

    public function getPointField()
    {
        return 'i.tm_added';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceItemTable, 'ii');
        $q->clearFields()
            ->leftJoin('?_invoice', 'i', 'ii.invoice_id=i.invoice_id')
            ->addWhere('i.status IN (?a)', array(Invoice::PAID,
                Invoice::RECURRING_ACTIVE,
                Invoice::RECURRING_FINISHED))
            ->addWhere('ii.item_type=?', 'product');


        $fields = array();
        foreach ($this->getProducts() as $k => $v) {
            $q->addField("COUNT(IF(ii.item_id = $k, 1, NULL))", 'cnt_' . $k);
        }
        return $q;
    }

    function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    function getLines()
    {
        $ret = array();
        foreach ($this->getProducts() as $k => $title) {
            $ret[] = new Am_Report_Line('cnt_' . $k, ucfirst($title));
        }
        return $ret;
    }

}

class Am_Report_Downloads extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Downloads by files breakdown');
        $this->description = ___('only files downloaded by registered users is taken to account');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addMagicSelect('files')->setLabel(___("Files\nkeep empty to report all files"));
        $sel->loadOptions($this->getOptions());
    }

    protected function getOptions()
    {
        return $this->getDi()->db->selectCol("SELECT DISTINCT file_id as ARRAY_KEY,
            title FROM ?_file");
    }

    public function getPointField()
    {
        return 'fd.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->fileDownloadTable, 'fd');
        $q->clearFields();
        foreach ($this->getFiles() as $k => $v) {
            $q->addField(sprintf('SUM(IF(file_id=%d,1,0))', $k), 'cnt_' . $k);
        }
        return $q;
    }

    function getFiles()
    {
        $vars = $this->form->getValue();
        $files = $this->getDi()->db->selectCol("SELECT
                DISTINCT file_id as ARRAY_KEY, title
                FROM ?_file
                {WHERE file_id IN (?a)}", !empty($vars['files']) ? (array) $vars['files'] : DBSIMPLE_SKIP);
        return $files;
    }

    function getLines()
    {
        $ret = array();
        foreach ($this->getFiles() as $k => $ps) {
            $ret[] = new Am_Report_Line('cnt_' . $k, ucfirst($ps));
        }
        return $ret;
    }

}

class Am_Report_RetentionRate extends Am_Report_Abstract
{

    public function __construct()
    {
        $this->title = ___('Retention Rate');
        $this->description = ___('Number of cancel on each billing cycle');
        $this->setQuantity(new Am_Report_Quant_Exact());
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addMagicSelect('products')->setLabel(___("Products\nkeep empty to report all products"));
        $sel->loadOptions($this->getDi()->productTable->getOptions());
    }

    public function runQuery()
    {
        $fields = array();
        foreach ($this->getProducts() as $k => $product) {
            $fields[] = "SUM(IF(ii.item_id=$k AND ii.item_type='product', 1, 0)) AS cnt_" . $k;
        }
        $fields = implode(',', $fields);

        $point_fld = self::POINT_FLD;
        $sql = "SELECT $fields,
        (SELECT COUNT(invoice_payment_id) + IF(i.first_total = 0, 1, 0)
            FROM ?_invoice_payment WHERE invoice_id=i.invoice_id) AS $point_fld
        FROM ?_invoice i LEFT JOIN ?_invoice_item ii USING(invoice_id)
        WHERE i.tm_cancelled IS NOT NULL GROUP BY $point_fld";

        $this->stmt = $this->getDi()->db->queryResultOnly($sql);
    }

    public function getLines()
    {
        $ret = array();
        foreach ($this->getProducts() as $k => $product) {
            $ret[] = new Am_Report_Line('cnt_' . $k, $product);
        }
        return $ret;
    }

    protected function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

}

class Am_Report_Active extends Am_Report_Abstract
{

    public function __construct()
    {
        $this->title = ___('Active Users by Products');
        $this->description = ___('number of active users per product');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addMagicSelect('products')->setLabel(___("Products\nkeep empty to report all products"));
        $sel->loadOptions($this->getDi()->productTable->getOptions());
    }

    public function runQuery()
    {
        $now = $this->getDi()->sqlDate;

        $products = array();
        foreach ($this->getProducts() as $k => $product) {
            $products[] = $k;
        }
        $products = implode(',', $products);

        $point_fld = self::POINT_FLD;
        $sql = "SELECT COUNT(user_id) as active, product_id AS $point_fld
        FROM ?_access
        WHERE begin_date <= '$now'
        AND expire_date >= '$now'
        GROUP BY $point_fld
        HAVING product_id IN ($products)";

        $this->stmt = $this->getDi()->db->queryResultOnly($sql);
    }

    public function getLines()
    {
        $ret = array(
            new Am_Report_Line('active', ___('Active Users'))
        );
        return $ret;
    }

    protected function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    protected function processConfigForm(array $values)
    {
        $this->setQuantity(new Am_Report_Quant_Enum($this->getProducts()));
    }

}

class Am_Report_UserDemography extends Am_Report_Abstract
{

    public function __construct()
    {
        $this->title = ___('User Demographics');
        $this->description = ___('number of users per region');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addAdvRadio('type')->setLabel(___('Group By'));
        $sel->loadOptions(array(
            'country' => ___('Country'),
            'state' => ___('State')
        ));
        $sel->setValue('country');
    }

    public function runQuery()
    {

        $vars = $this->form->getValue();

        $field = $vars['type'];

        $point_fld = self::POINT_FLD;
        $sql = "SELECT COUNT(user_id) AS demography, $field AS $point_fld
        FROM ?_user
        GROUP BY $point_fld
        HAVING $point_fld<>''";

        $this->stmt = $this->getDi()->db->queryResultOnly($sql);
    }

    public function getLines()
    {
        $ret = array(
            new Am_Report_Line('demography', ___('User Demographics'))
        );
        return $ret;
    }

    protected function getOptions()
    {
        $vars = $this->form->getValue();
        switch ($vars['type']) {
            case 'country' :
                return $this->getDi()->db->selectCol("SELECT
            country as ARRAY_KEY, title
            FROM ?_country
            WHERE country IN (SELECT DISTINCT country FROM ?_user)");
                break;
            case 'state' :
                return $this->getDi()->db->selectCol("SELECT
            state as ARRAY_KEY, title
            FROM ?_state
            WHERE state IN (SELECT DISTINCT state FROM ?_user)");
                break;
            default:
                throw new Am_Exception_InputError(sprintf('Unknown type [%s] in %s::%s', $vars['type'], __CLASS__, __METHOD__));
        }
    }

    protected function processConfigForm(array $values)
    {
        $this->setQuantity(new Am_Report_Quant_Enum($this->getOptions()));
    }

}

class Am_Report_ChurnRate extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Churn Rate');
        $this->description = ___('Number of users who becomes inactive in given period');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addMagicSelect('products')->setLabel(___("Products\nkeep empty to report all products"));
        $sel->loadOptions($this->getDi()->productTable->getOptions());
    }

    protected function runQuery()
    {
        $expbegin = $this->quantity->getSqlExpr('begin');
        $expend = $this->quantity->getSqlExpr('expire');


        $vars = $this->form->getValue();
        if (empty($vars['products'])) {
            $this->stmt = $this->getDi()->db->queryResultOnly(
                "SELECT $expend as point, COUNT(t.user_id) as cnt
                 FROM (
                      SELECT user_id, MIN(begin_date) AS begin, MAX(expire_date) AS expire
                      FROM ?_access GROUP BY user_id
                ) AS t
                WHERE $expbegin < $expend  and expire > ? and expire < ?  group by point", sqlDate($this->start), sqlDate($this->stop));
        } else {
            $fields = array();
            foreach ($this->getProducts() as $k => $title) {
                $fields[] = "sum(if(product_id=$k, 1,0)) as cnt_$k";
            }
            $fields = implode(", ", $fields);
            $this->stmt = $this->getDi()->db->queryResultOnly(
                "SELECT $expend as point, $fields
                 FROM (
                      SELECT concat(user_id, '-',product_id) as user_product,  user_id, product_id, MIN(begin_date) AS begin, MAX(expire_date) AS expire
                      FROM ?_access GROUP BY user_product
                ) AS t
                WHERE $expbegin < $expend  and expire > ? and expire < ?  group by point", sqlDate($this->start), sqlDate($this->stop));
        }
    }

    function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    function getLines()
    {
        $vars = $this->form->getValue();
        $ret = array();
        if (empty($vars['products'])) {
            $ret[] = new Am_Report_Line('cnt', ucfirst("Users who became inactive"));
        } else {
            foreach ($this->getProducts() as $k => $title) {
                $ret[] = new Am_Report_Line('cnt_' . $k, ucfirst($title));
            }
        }
        return $ret;
    }

}

class Am_Report_ActiveUsers extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Active Users by Period');
        $this->description = ___('Number of Active Users by Date');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addMagicSelect('products')->setLabel(___("Include only users who have active subscritpions to these products:"));
        $sel->loadOptions($this->getDi()->productTable->getOptions());
    }

    protected function runQuery()
    {
        $expbegin = $this->quantity->getSqlExpr('begin_date');
        $expend = $this->quantity->getSqlExpr('expire_date');
        $this->getDi()->db->query("DROP TEMPORARY TABLE IF EXISTS ?_active_users_by_period_tmp");
        $this->getDi()->db->query(
            "CREATE TEMPORARY TABLE ?_active_users_by_period_tmp (
            point varchar(10) not null,
            cnt int(10) not null,
            UNIQUE KEY(point)
        )
        ");

        $this->getDi()->db->query(
            "INSERT  IGNORE INTO ?_active_users_by_period_tmp (point) "
            . "SELECT DISTINCT($expbegin) FROM ?_access WHERE begin_date BETWEEN ? AND ?  "
            . "UNION SELECT DISTINCT($expend) FROM ?_access WHERE expire_date BETWEEN ? AND ?", $this->start, $this->stop, $this->start, $this->stop);

        $vars = $this->form->getValue();

        $this->getDi()->db->query("UPDATE ?_active_users_by_period_tmp p SET p.cnt = (SELECT COUNT(DISTINCT(user_id)) FROM ?_access a WHERE p.point BETWEEN $expbegin AND $expend {AND a.product_id in (?a)})", (!empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP));


        $this->stmt = $this->getDi()->db->queryResultOnly("SELECT point, cnt FROM ?_active_users_by_period_tmp");
    }

    function getProducts()
    {
        $vars = $this->form->getValue();

        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    public function getLines()
    {
        return array(new Am_Report_Line('cnt', 'Active Users'));
    }

}

class Am_Report_RollingConversion extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Rolling Conversion');
        $this->description = ___('Percentage of users who convert from free to paid within given amount of days');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addMagicSelect('products')->setLabel(___("Count conversion for these products only"));
        $sel->loadOptions($this->getDi()->productTable->getOptions());
        $form->addText('rolling_days', array('placeholder' => '30', 'size' => 3))
            ->setLabel(___("Rolling Days"));
    }

    protected function runQuery()
    {
        $pointexp = $this->quantity->getSqlExpr('t.added');
        $vars = $this->form->getValue();
        $rolling_days = intval($vars['rolling_days']) ? intval($vars['rolling_days']) : 30;

        $this->getDi()->db->query("DROP TABLE IF EXISTS ?_rolling_conversion_tmp");
        $this->getDi()->db->query(
            "CREATE TEMPORARY TABLE ?_rolling_conversion_tmp (
            point varchar(10) not null,
            added int(10) not null,
            paid int(10) not null,
            UNIQUE KEY(point)
        )
        ");
        // Add count of users who
        $this->getDi()->db->query(
            "INSERT INTO ?_rolling_conversion_tmp (point, added)
             SELECT $pointexp as point, count(t.user_id) AS added
             FROM ?_user t where t.added>? AND t.added < ?
             GROUP BY point
             ", $this->start, $this->stop
        );

        $this->getDi()->db->queryResultOnly(
            "INSERT INTO ?_rolling_conversion_tmp (point, paid) SELECT $pointexp as point, COUNT(t.user_id) as cnt
                 FROM (
                      SELECT u.user_id, added,  MIN(p.dattm)  AS  paid
                      FROM ?_user u LEFT JOIN ?_invoice_payment p ON u.user_id = p.user_id
                      {LEFT JOIN ?_access a  on p.invoice_payment_id = a.invoice_payment_id WHERE a.product_id IN (?a)} GROUP BY u.user_id
                ) AS t
                WHERE   to_days(paid) - to_days(added) <= ?  AND  t.added > ? AND  t.added<? GROUP BY point
                ON DUPLICATE KEY UPDATE paid=VALUES(paid)
                ", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP, $rolling_days, $this->start, $this->stop);


        $this->stmt = $this->getDi()->db->queryResultOnly("SELECT point, paid/added as cnt FROM ?_rolling_conversion_tmp");
    }

    public function getLines()
    {
        return array(new Am_Report_Line('cnt', 'Conversion'));
    }

}

class Am_Report_ProductConversion extends Am_Report_Date
{

    public function __construct()
    {
        $this->title = ___('Product Convertion');
        $this->description = ___('Number of Users who Purchase product B after Product A');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addSelect('producta')
            ->setLabel(___("Product A"))
            ->loadOptions($this->getDi()->productTable->getOptions());
        $sel = $form->addSelect('productb')
            ->setLabel(___("Product B"))
            ->loadOptions($this->getDi()->productTable->getOptions());
    }

    public function getPointField()
    {
        return 'begin_date';
    }

    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('begin_date');

        $vars = $this->form->getValue();

        $db = $this->getDi()->db;
        $db->query("DROP TEMPORARY TABLE IF EXISTS ?_product_conversion_tmp");
        $db->query("CREATE TEMPORARY TABLE ?_product_conversion_tmp (
            user_id INT NOT NULL,
            begin_date DATE NOT NULL
        )");

        $db->query("INSERT INTO ?_product_conversion_tmp (user_id, begin_date)
            SELECT a.user_id, MIN(a.begin_date)
            FROM ?_access a
            LEFT JOIN ?_access a2
                ON a.user_id = a2.user_id AND a2.product_id = ?
            WHERE a.product_id = ?
                AND a2.product_id IS NOT NULL
                AND a2.begin_date < a.begin_date
            GROUP BY a.user_id", $vars['producta'], $vars['productb']);

        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT
                COUNT(DISTINCT user_id) AS cnt,
                $expra AS point
            FROM ?_product_conversion_tmp
            WHERE begin_date BETWEEN ? AND ?
            GROUP BY $expra", $this->start, $this->stop);
    }

    public function getLines()
    {
        return array(new Am_Report_Line('cnt', 'Conversion'));
    }

}