<?php
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(dirname(__FILE__) . '/library'),
    get_include_path()
)));

require_once 'Zend/Loader/Autoloader.php';

$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('GEN_');

// Setup the CLI Commands
try
{
    $opts = new Zend_Console_Getopt(array(
        'host|h=s'      => 'Databse Host, required string parameter',
        'port|p-i'      => 'Database Port, optional integer parameter',
        'database|d=s'  => 'Database Name, required word parameter',
        'username|u=s'  => 'Username, required string parameter',
        'password|P-s'  => 'Password, optional string parameter',
        'ignore|i-s'    => 'Ignore (space separated list of tables), optional string parameter',
        'output|o=s'    => 'Output Directory, required string parameter',
        'prefix-s'      => 'Model Prefix, optional string parameter',
        'tableclass-s'  => 'Table Class Name (replaces Zend_Db_Table_Abstract), optional string parameter',
        'rowclass-s'    => 'Row Class Name (replaces Zend_Db_Table_Row_Abstract), optional string parameter',
        'rowsetclass-s' => 'Rowset Class Name (Zend_Db_Table_Rowset_Abstract), optional string parameter',
        'verbose|v'     => 'Print verbose output',
        'help'          => 'Help'
    ));

    $opts->parse();
}
catch (Zend_Console_Getopt_Exception $e)
{
    exit($e->getMessage() ."\n\n". $e->getUsageMessage());
}

if (isset($opts->help) or !isset($opts->host, $opts->database, $opts->username, $opts->output))
{
    echo $opts->getUsageMessage();
    exit;
}

if (!isset($opts->port))
{
    $opts->port = 3306;
}

if (!isset($opts->tableclass))
{
    $opts->tableclass = 'Zend_Db_Table_Abstract';
}

if (!isset($opts->rowclass))
{
    $opts->rowclass = 'Zend_Db_Table_Row_Abstract';
}

if (!isset($opts->rowsetclass))
{
    $opts->rowsetclass = 'Zend_Db_Table_Rowset_Abstract';
}

if (!isset($opts->ignore))
{
    $ignore = array();
}
else
{
    $ignore = explode(' ', $opts->ignore);
}

$parser = new GEN_Db_Table_Parser();

if (isset($opts->prefix))
{
    $parser->setPrefix($opts->prefix);
}

$adapter = new Zend_Db_Adapter_Pdo_Mysql(array(
    'host'      => $opts->host,
    'dbname'    => $opts->database,
    'port'      => $opts->port,
    'username'  => $opts->username,
    'password'  => $opts->password,
    'charset'   => 'UTF8'
));

Zend_Db_Table::setDefaultAdapter($adapter);

$tables = $adapter->listTables();

printf('Found %d table(s)' . "\n", count($tables));

foreach ($tables as $name)
{
    if (!in_array($name, $ignore))
    {
        printf('Processing "%s"' . "\n", $name);

        $table = new Zend_Db_Table($name);
        $info = $parser->parse($table);
    /*
        foreach ($info['primary'] as $key_name) {
            $parameters[] = array('name' => $key_name);
        }

        $info['methods'][] = new Zend_CodeGenerator_Php_Method(array(
            'name' => sprintf('count'),
            'body' => sprintf('return $this->fetchRow($this->select()->from($this->_name, array(\'%s\', \'num\'=> \'COUNT(*)\'))->where(\'%s = ?\', $value))->num;', 0, 0),
            'parameters' => $parameters
        ));
    */

        $info['methods'][] = new Zend_CodeGenerator_Php_Method(array(
            'name' => 'findAll',
            'body' => 'return $this->fetchAll($where, $order, $count, $offset);',
            'parameters' => array(
                array(
                    'name' => 'where',
                    'defaultValue' => null
                ),
                array(
                    'name' => 'order',
                    'defaultValue' => null
                ),
                array(
                    'name' => 'count',
                    'defaultValue' => null
                ),
                array(
                    'name' => 'offset',
                    'defaultValue' => null
                ),
            ),
        ));

        $info['methods'][] = new Zend_CodeGenerator_Php_Method(array(
            'name' => 'findById',
            'body' => 'return $this->find($id)->current();',
            'parameters' => array(
                array(
                    'name' => 'id'
                ),
            ),
        ));

        foreach ($info['uniques'] as $key)
        {
            $info['methods'][] = new Zend_CodeGenerator_Php_Method(array(
                'name' => sprintf('findBy%s', $parser->formatMethodName($key)),
                'body' => sprintf('return $this->fetchAll($this->getAdapter()->quoteInto(\'%s = ?\', $value));', $key),
                'parameters' => array(
                    array(
                        'name' => 'value'
                    ),
                ),
            ));

            $info['methods'][] = new Zend_CodeGenerator_Php_Method(array(
                'name' => sprintf('countBy%s', $parser->formatMethodName($key)),
                'body' => sprintf('return $this->fetchRow($this->select()->from($this->_name, array(\'%s\', \'num\'=> \'COUNT(*)\'))->where(\'%s = ?\', $value))->num;', $key, $key),
                'parameters' => array(
                    array(
                        'name' => 'value'
                    ),
                ),
            ));
        }

        foreach ($info['parents'] as $table => $key)
        {
            $info['methods'][] = new Zend_CodeGenerator_Php_Method(array(
                'name' => sprintf('find%s', $parser->formatMethodName($table)),
                'body' => sprintf('return $this->findParentRow(new %s(), null, $select);', $parser->formatDbTableClassName($table)),
                'parameters' => array(
                    array(
                        'name' => 'select',
                        'defaultValue' => null
                    ),
                ),
            ));
        }

        foreach ($info['dependants'] as $table)
        {
            $info['methods'][] = new Zend_CodeGenerator_Php_Method(array(
                'name' => sprintf('find%s', $parser->formatMethodName($table)),
                'body' => sprintf('return $this->findDependentRowset(new %s(), null, $select);', $parser->formatDbTableClassName($table)),
                'parameters' => array(
                    array(
                        'name' => 'select',
                        'defaultValue' => null
                    ),
                ),
            ));
        }

        $row = new Zend_CodeGenerator_Php_File(array(
            'classes' => array(
                new Zend_CodeGenerator_Php_Class(array(
                    'name' => $parser->formatRowClassName($name),
                    'extendedClass' => $opts->rowclass
                ))
            )
        ));

        $rowset = new Zend_CodeGenerator_Php_File(array(
            'classes' => array(
                new Zend_CodeGenerator_Php_Class(array(
                    'name' => $parser->formatRowSetClassName($name),
                    'extendedClass' => $opts->rowsetclass
                ))
            )
        ));

        $model = new Zend_CodeGenerator_Php_File(array(
            'classes' => array(
                new Zend_CodeGenerator_Php_Class(array(
                    'name' => $parser->formatModelClassName($name),
                    'extendedClass' => $parser->formatDbTableClassName($name)
                ))
            )
        ));

        $dbtable = new Zend_CodeGenerator_Php_File(array(
            'classes' => array(
                new Zend_CodeGenerator_Php_Class(array(
                    'name' => $parser->formatDbTableClassName($name),
                    'methods' => $info['methods'],
                    'properties' => array(
                        array (
                            'name'          => '_name',
                            'visibility'    => 'protected',
                            'defaultValue'  => $name
                        ),

                        array (
                            'name'          => '_primary',
                            'visibility'    => 'protected',
                            'defaultValue'  => array_values($info['primary'])
                        ),

                        array (
                            'name'          => '_rowClass',
                            'visibility'    => 'protected',
                            'defaultValue'  => $parser->formatRowClassName($name)
                        ),

                        array (
                            'name'          => '_rowsetClass',
                            'visibility'    => 'protected',
                            'defaultValue'  => $parser->formatRowsetClassName($name)
                        ),
                        array (
                            'name'          => '_referenceMap',
                            'visibility'    => 'protected',
                            'defaultValue'  => $info['referenceMap']
                        ),

                        array (
                            'name'          => '_dependentTables',
                            'visibility'    => 'protected',
                            'defaultValue'  => array()
                        ),
                    ),

                    'extendedClass' => $opts->tableclass
                )),
            )
        ));

        // try to make the directory
        if (!is_dir($opts->output)) {
            mkdir($opts->output);
        }

        if (!is_dir($opts->output . DIRECTORY_SEPARATOR . 'DbTable')) {
            mkdir($opts->output . DIRECTORY_SEPARATOR . 'DbTable');
        }

        if (!is_dir($opts->output . DIRECTORY_SEPARATOR . 'DbTable' . DIRECTORY_SEPARATOR . 'Row')) {
            mkdir($opts->output . DIRECTORY_SEPARATOR . 'DbTable' . DIRECTORY_SEPARATOR . 'Row');
        }

        if (!is_dir($opts->output . DIRECTORY_SEPARATOR . 'DbTable' . DIRECTORY_SEPARATOR . 'Rowset')) {
            mkdir($opts->output . DIRECTORY_SEPARATOR . 'DbTable' . DIRECTORY_SEPARATOR . 'Rowset');
        }

        file_put_contents($opts->output . DIRECTORY_SEPARATOR . $parser->formatTableName($name) . '.php', $model->generate());

        file_put_contents($opts->output . DIRECTORY_SEPARATOR . 'DbTable' . DIRECTORY_SEPARATOR . $parser->formatTableName($name) . '.php', $dbtable->generate());

        file_put_contents($opts->output . DIRECTORY_SEPARATOR . 'DbTable' . DIRECTORY_SEPARATOR . 'Row' . DIRECTORY_SEPARATOR . $parser->formatTableName($name) . '.php', $row->generate());

        file_put_contents($opts->output . DIRECTORY_SEPARATOR . 'DbTable' . DIRECTORY_SEPARATOR . 'Rowset' . DIRECTORY_SEPARATOR . $parser->formatTableName($name) . '.php', $rowset->generate());
    }
}
