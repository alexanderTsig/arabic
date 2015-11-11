<?php

class Am_Grid_DataSource_CustomField extends Am_Grid_DataSource_Array
{

    protected $config_key, $table, $pk;

    public function __construct(array $array, Am_Table_WithData $table)
    {
        parent::__construct($array);
        $this->config_key = $table->getCustomFieldsConfigKey();
        $this->table = $table->getName(true);
        $this->pk = $table->getKeyField();
    }

    public function insertRecord($record, $valuesFromForm)
    {
        $fields = Am_Di::getInstance()->config->get($this->config_key, array());
        $recordForStore = $this->getRecordForStore($valuesFromForm);
        $recordForStore['name'] = $valuesFromForm['name'];
        $fields[] = $recordForStore;
        Am_Config::saveValue($this->config_key, $fields);
        Am_Di::getInstance()->config->set($this->config_key, $fields);

        if ($recordForStore['sql'])
            $this->addSqlField($recordForStore['name'], $recordForStore['additional_fields']['sql_type']);
    }

    public function updateRecord($record, $valuesFromForm)
    {
        $fields = Am_Di::getInstance()->config->get($this->config_key);
        foreach ($fields as $k => $v) {
            if ($v['name'] == $record->name) {
                $recordForStore = $this->getRecordForStore($valuesFromForm);
                $recordForStore['name'] = $record->name;
                $fields[$k] = $recordForStore;
            }
        }
        Am_Config::saveValue($this->config_key, $fields);
        Am_Di::getInstance()->config->set($this->config_key, $fields);

        if ($record->sql != $recordForStore['sql']) {
            if ($recordForStore['sql']) {
                $this->convertFieldToSql($record->name, $recordForStore['additional_fields']['sql_type']);
            } else {
                $this->convertFieldFromSql($record->name);
            }
        } elseif ($recordForStore['sql'] &&
            $record->sql_type != $recordForStore['additional_fields']['sql_type']) {

            $this->changeSqlField($record->name, $recordForStore['additional_fields']['sql_type']);
        }
    }

    public function deleteRecord($id, $record)
    {
        $record = $this->getRecord($id);
        $fields = Am_Di::getInstance()->config->get($this->config_key);
        foreach ($fields as $k => $v) {
            if ($v['name'] == $record->name)
                unset($fields[$k]);
        }
        Am_Config::saveValue($this->config_key, $fields);
        Am_Di::getInstance()->config->set('member_fields', $fields);

        if ($record->sql)
            $this->dropSqlField($record->name);
    }

    public function createRecord()
    {
        $o = new stdclass;
        $o->name = null;
        $o->options = array();
        $o->default = null;
        return $o;
    }

    protected function getRecordForStore($values)
    {
        $value = array();

        if (($values['type'] == 'text') ||
            ($values['type'] == 'textarea') ||
            ($values['type'] == 'date')) {
            $default = $values['default'];
        } else {
            $default = array_intersect($values['values']['default'], array_keys($values['values']['options']));
            if ($values['type'] == 'radio')
                $default = $default[0];
        }

        if ($values['type'] == 'select')
            $values['size'] = 1;

        $recordForStore['title'] = $values['title'];
        $recordForStore['description'] = $values['description'];
        $recordForStore['sql'] = $values['sql'];
        $recordForStore['type'] = $values['type'];
        $recordForStore['validate_func'] = $values['validate_func'];
        $recordForStore['additional_fields'] = array(
            'sql' => intval($values['sql']),
            'sql_type' => $values['sql_type'],
            'size' => $values['size'],
            'default' => $default,
            'options' => $values['values']['options'],
            'cols' => $values['cols'],
            'rows' => $values['rows'],
        );

        $default_fields = array(
            'type' => 1,
            'default' => 1,
            'values' => 1,
            'size' => 1,
            'title' => 1,
            'description' => 1,
            'validate_func' => 1,
            'sql' => 1,
            'sql_type' => 1,
            'cols' => 1,
            'rows' => 1);

        foreach ($values as $k => $v) {
            if (!isset($default_fields[$k]) && $k[0] != '_') {
                $recordForStore['additional_fields'][$k] = $v;
            }
        }

        return $recordForStore;
    }

    protected function addSqlField($name, $type)
    {
        Am_Di::getInstance()->db->query("ALTER TABLE ?_{$this->table} ADD ?# $type", $name);
    }

    protected function dropSqlField($name)
    {
        Am_Di::getInstance()->db->query("ALTER TABLE ?_{$this->table} DROP ?#", $name);
    }

    protected function changeSqlField($name, $type)
    {
        Am_Di::getInstance()->db->query("ALTER TABLE ?_{$this->table} CHANGE ?# ?# $type", $name, $name);
    }

    protected function convertFieldToSql($name, $type)
    {
        $this->addSqlField($name, $type);
        Am_Di::getInstance()->db->query("UPDATE ?_{$this->table} t SET ?# = (SELECT `value`
            FROM ?_data
            WHERE `table`='{$this->table}'
            AND `key`= ?
            AND `id`=t.{$this->pk} LIMIT 1)", $name, $name);
        Am_Di::getInstance()->db->query("DELETE FROM ?_data WHERE `table`='{$this->table}' AND `key`=?", $name);
    }

    protected function convertFieldFromSql($name)
    {
        Am_Di::getInstance()->db->query("INSERT INTO ?_data (`table`, `key`, `id`, `value`)
            (SELECT '{$this->table}', ?, {$this->pk}, ?# FROM ?_{$this->table})", $name, $name);

        $this->dropSqlField($name);
    }

    public function getDataSourceQuery()
    {
        return null;
    }

}