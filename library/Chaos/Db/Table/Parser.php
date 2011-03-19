<?php

class Chaos_Db_Table_Parser
{
    private $_prefix = null;

    public function __construct()
    {
    }

    public function parse(Zend_Db_Table_Abstract $table)
    {
        $info = $table->info();

        $info['uniques'] = array();
        $info['parents'] = array();
        $info['dependants'] = array();

        $adapter = $table->getAdapter();

        foreach ($info['metadata'] as $property => $details)
        {
            // match php types
            $info['phptypes'][$property] = $this->convertMysqlTypeToPhp($details['DATA_TYPE']);

            // find uniques
            $tmp = $adapter->fetchRow('DESCRIBE `'.$info['name'].'` `'.$property.'`;');

            if (!empty($tmp['Key']))
            {
                $info['uniques'][$property] = $property;
            }
        }

        // get foreign keys
        $result = $adapter->fetchAll('SHOW CREATE TABLE `' . $info['name'].'`');
        $query = $result[0]['Create Table'];
        $lines = explode("\n", $query);
        $tblinfo = array();
        $keys = array();

        foreach ($lines as $line) {
            preg_match('/^\s*CONSTRAINT `(\w+)` FOREIGN KEY \(`(\w+)`\) REFERENCES `(\w+)` \(`(\w+)`\)/', $line, $tblinfo);

            if (sizeof($tblinfo) > 0)
            {
                $keys[] = $tmp = array(
                    'key'       => $tblinfo[1],
                    'column'    => $tblinfo[2],
                    'fk_table'  => $tblinfo[3],
                    'fk_column' => $tblinfo[4]
                );

                $info['referenceMap'][$tmp['key']] = array(
                    'columns' => $tmp['column'],
                    'refTableClass' => $this->formatClassName($tmp['fk_table']),
                    'refColumns' => $tmp['fk_column']
                );

                $info['parents'][$tmp['fk_table']] = $tmp['key'];

/*
                $info['dependentTables']
*/
            }
        }

        $info['foreign_keys'] = $keys;

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

    public function formatClassName($name)
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

    public static function formatMethodName($string)
    {
        return str_replace('_','', mb_convert_case($string, MB_CASE_TITLE));
    }

    /**
     * map mysql data types to php data types
     * @param string $mysqlType
     * @return string
     */
    protected function convertMysqlTypeToPhp($mysqlType)
    {

        $type = 'string';

        // integers
        if (preg_match('#^(.*)int(.*)$#', $mysqlType))
        {
            $type = 'int';
        }

        if (preg_match('#^(.*)float(.*)$#', $mysqlType))
        {
            $type = 'float';
        }

        return $type;
    }

}
