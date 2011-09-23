<?php

class GEN_Db_Table_Parser
{
    private $_prefix = null;

    public function __construct()
    {
    }

    public function parse(Zend_Db_Table_Abstract $table)
    {
        $info = $table->info();

        $info['indexes'] = array();
        $info['parents'] = array();
        $info['dependants'] = array();

        $adapter = $table->getAdapter();

        // find indexes
        $indexes = $adapter->fetchAll(sprintf('SHOW INDEXES FROM `%s` where Non_unique = 0', $info['name']));

        foreach ($indexes as $index)
        {
            $info['indexes'][$index['Column_name']] = $index['Column_name'];
        }

        // get outgoing references
        $references = $adapter->fetchAll(sprintf('SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = "%s" AND REFERENCED_TABLE_NAME IS NOT NULL', $info['name']));

        foreach ($references as $reference) {
            $info['referenceMap'][$reference['CONSTRAINT_NAME']] = array(
                'columns' => $reference['COLUMN_NAME'],
                'refTableClass' => $this->formatModelClassName($reference['REFERENCED_TABLE_NAME']),
                'refColumns' => $reference['REFERENCED_COLUMN_NAME']
            );

            $info['parents'][$reference['REFERENCED_TABLE_NAME']] = $reference['CONSTRAINT_NAME'];
        }

        unset($references);

        // get incoming references
        $references = $adapter->fetchAll(sprintf('SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME = "%s"', $info['name']));

        foreach ($references as $reference) {
            $info['dependentTables'][] = $this->formatModelClassName($reference['TABLE_NAME']);
        }

        return $info;
    }

    public function setPrefix($string)
    {
        $this->_prefix = $string;
    }

    public function getPrefix()
    {
        return $this->_prefix;
    }

    public function formatTableName($name)
    {
        return str_replace('_','', mb_convert_case($name, MB_CASE_TITLE));
    }

    public function formatRowClassName($name)
    {
        $prefix = $this->getPrefix();

        if (empty($prefix))
        {
            return sprintf('DbTable_Row_%s', $this->formatTableName($name));
        }
        else
        {
            return sprintf('%s_DbTable_Row_%s', $prefix, $this->formatTableName($name));
        }
    }

    public function formatRowsetClassName($name)
    {
        $prefix = $this->getPrefix();

        if (empty($prefix))
        {
            return sprintf('DbTable_Rowset_%s', $this->formatTableName($name));
        }
        else
        {
            return sprintf('%s_DbTable_Rowset_%s', $prefix, $this->formatTableName($name));
        }
    }

    public function formatModelClassName($name)
    {
        $prefix = $this->getPrefix();

        if (empty($prefix))
        {
            return sprintf('%s', $this->formatTableName($name));
        }
        else
        {
            return sprintf('%s_%s', $prefix, $this->formatTableName($name));
        }
    }

    public function formatDbTableClassName($name)
    {
        $prefix = $this->getPrefix();

        if (empty($prefix))
        {
            return sprintf('DbTable_%s', $this->formatTableName($name));
        }
        else
        {
            return sprintf('%s_DbTable_%s', $prefix, $this->formatTableName($name));
        }
    }

    public static function formatMethodName($string)
    {
        return str_replace('_','', mb_convert_case($string, MB_CASE_TITLE));
    }
}
