<?php
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(dirname(__FILE__) . '/library'),
    get_include_path()
)));

require_once 'Zend/Loader/Autoloader.php';

$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('Chaos_');

// Setup the CLI Commands
try
{
    $opts = new Zend_Console_Getopt(array(
        'host|h=s'      => 'host option, required string parameter',
        'port|p-i'      => 'port option, optional integer parameter',
        'database|d=s'  => 'database option, required word parameter',
        'username|u=s'  => 'username option, required string parameter',
        'password|P-s'  => 'password option, optional string parameter',
        'output|o=s'    => 'output directory option, required string parameter',
        'prefix-s'      => 'prefix option, optional string parameter',
        'verbose|v'     => 'Print verbose output',
        'help'          => 'help option'
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

$parser = new Chaos_Db_Table_Parser();

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
    foreach ($info['uniques'] as $key)
    {
        $info['methods'][] = new Zend_CodeGenerator_Php_Method(array(
            'name' => sprintf('findBy%s', $parser->formatMethodName($key)),
            'body' => sprintf('return $this->fetchAll($this->select()->where(\'%s = ?\', $value));', $key),
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
            'body' => sprintf('return $this->findParentRow(new %s(), null, $select);', $parser->formatClassName($table)),
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
            'body' => sprintf('return $this->findDependentRowset(new %s(), null, $select);', $parser->formatClassName($table)),
            'parameters' => array(
                array(
                    'name' => 'select',
                    'defaultValue' => null
                ),
            ),
        ));
    }

    $file = new Zend_CodeGenerator_Php_File(array(
        'classes' => array(
            new Zend_CodeGenerator_Php_Class(array(
                'name' => $parser->formatClassName($name),
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
/*
                    array (
                        'name'          => '_rowClass',
                        'visibility'    => 'protected',
                        'defaultValue'  => 'LS_Db_Table_Row_Abstract'
                    ),
*/
/*
                    array (
                        'name'          => '_rowsetClass',
                        'visibility'    => 'protected',
                        'defaultValue'  => 'LS_Db_Table_Row_Abstract'
                    ),
*/
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

                'extendedClass' => 'Zend_Db_Table_Abstract'
            )),
        )
    ));

    // try to make the directory
    if (!is_dir($opts->output)) {
        mkdir($opts->output);
    }

    file_put_contents($opts->output . DIRECTORY_SEPARATOR . $parser->formatTableName($name) . '.php', $file->generate());
}
