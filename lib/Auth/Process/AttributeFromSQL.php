<?php

/**
 * Filter to add attributes from a SQL data source
 *
 * This filter allows you to add attributes from a SQL datasource
 *
 * @author Guy Halse http://orcid.org/0000-0002-9388-8592
 * @copyright Copyright (c) 2016, SAFIRE - South African Identity Federation
 * @license https://github.com/safire-ac-za/simplesamlphp-module-sqlattribs/blob/master/LICENSE MIT License
 * @package SimpleSAMLphp
 */
class sspmod_sqlattribs_Auth_Process_AttributeFromSQL extends SimpleSAML_Auth_ProcessingFilter
{
    /** @var string The DSN we should connect to. */
    private $dsn = 'mysql:host=localhost;dbname=simplesamlphp';

    /** @var string The username we should connect to the database with. */
    private $username;

    /** @var string The password we should connect to the database with. */
    private $password;

    /** @var string The name of the database table to use. */
    private $table = 'AttributeFromSQL';

    /** @var string Username/UID attribute. */
    private $attribute = 'eduPersonPrincipalName';

    /** @var bool|false Should we replace existing attributes? */
    private $replace = false;

    /** @var array|null Limit returned attribute set */
    private $limit = null;

    /**
     * Initialize this filter, parse configuration.
     *
     * @param array $config Configuration information about this filter.
     * @param mixed $reserved For future use.
     * @throws SimpleSAML_Error_Exception
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        assert('is_array($config)');

        if (array_key_exists('attribute', $config)) {
            $this->attribute = $config['attribute'];
        }
        if (!is_string($this->attribute) || !$this->attribute) {
            throw new SimpleSAML_Error_Exception('AttributeFromSQL: attribute name not valid');
        }

        if (array_key_exists('database', $config)) {
            if (array_key_exists('dsn', $config['database'])) {
                $this->dsn = $config['database']['dsn'];
            }
            if (array_key_exists('username', $config['database'])) {
                $this->username = $config['database']['username'];
            }
            if (array_key_exists('password', $config['database'])) {
                $this->password = $config['database']['password'];
            }
            if (array_key_exists('table', $config['database'])) {
                $this->table = $config['database']['table'];
            }
        }
        if (!is_string($this->dsn) || !$this->dsn) {
            throw new SimpleSAML_Error_Exception('AttributeFromSQL: invalid database DSN given');
        }
        if (!is_string($this->table) || !$this->table) {
            throw new SimpleSAML_Error_Exception('AttributeFromSQL: invalid database table');
        }

        if (array_key_exists('replace', $config)) {
            $this->replace = (bool)$config['replace'];
        }

        if (array_key_exists('limit', $config)) {
            if (!is_array($config['limit'])) {
                throw new SimpleSAML_Error_Exception('AttributeFromSQL: limit must be an array of attribute names');
            }
            $this->limit = $config['limit'];
        }
    }

    /**
     * Create a database connection.
     *
     * @return PDO The database connection.
     * @throws SimpleSAML_Error_Exception
     */
    private function connect()
    {
        try {
            $db = new PDO($this->dsn, $this->username, $this->password);
        } catch (PDOException $e) {
            throw new SimpleSAML_Error_Exception('AttributeFromSQL: Failed to connect to \'' .
                $this->dsn . '\': ' . $e->getMessage());
        }
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $driver = explode(':', $this->dsn, 2);
        $driver = strtolower($driver[0]);

        /* Driver specific initialization. */
        switch ($driver) {
            case 'mysql':
                $db->exec("SET NAMES 'utf8'");
                break;
            case 'pgsql':
                $db->exec("SET NAMES 'UTF8'");
                break;
        }

        return $db;
    }

    /**
     * Process this filter
     *
     * Logic is largely the same as (and lifted from) sqlauth:sql
     * @param mixed &$request
     * @throws SimpleSAML_Error_Exception
     */
    public function process(&$request)
    {
        assert('is_array($request)');
        assert('array_key_exists("Attributes", $request)');
        assert('array_key_exists("entityid", $request["Destination"])');

        $attributes =& $request['Attributes'];

        if (!array_key_exists($this->attribute, $attributes)) {
            SimpleSAML\Logger::info('AttributeFromSQL: attribute \'' . $this->attribute . '\' not set, declining');
            return;
        }

        $db = $this->connect();

        try {
            $sth = $db->prepare('SELECT attribute,value FROM ' . $this->table . ' WHERE uid=? AND (sp=\'%\' OR sp=?);');
        } catch (PDOException $e) {
            throw new SimpleSAML_Error_Exception('AttributeFromSQL: prepare() failed: ' . $e->getMessage());
        }

        try {
            $res = $sth->execute(array($attributes[$this->attribute][0], $request["Destination"]["entityid"]));
        } catch (PDOException $e) {
            throw new SimpleSAML_Error_Exception('AttributeFromSQL: execute(' . $attributes[$this->attribute][0] .
                ', ' . $request["Destination"]["entityid"] . ') failed: ' . $e->getMessage());
        }

        try {
            $data = $sth->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            throw new SimpleSAML_Error_Exception('AttributeFromSQL: fetchAll() failed: ' . $e->getMessage());
        }

        if (count($data) === 0) {
            SimpleSAML\Logger::info('AttributeFromSQL: no additional attributes for ' . $this->attribute . '=\'' . $attributes[$this->attribute][0] . '\'');
            return;
        }

        /* Extract attributes from the SQL datasource, and then merge them into
         * the existing attribute set. If $replace is set, overwrite any existing
         * attribute of the same name; otherwise add it as a multi-valued attribute
         */
        foreach ($data as $row) {
            if (empty($row['attribute']) || $row['value'] === null) {
                SimpleSAML\Logger::debug('AttributeFromSQL: skipping invalid attribute/value tuple: ' . var_export($row, true));
                continue;
            }

            $name = (string)$row['attribute'];
            $value = (string)$row['value'];

            /* Limit the attribute set returned */
            if ($this->limit !== null && !in_array($name, $this->limit, true)) {
                SimpleSAML\Logger::notice('AttributeFromSQL: skipping unwanted attribute ' . $name . ' [limited to: ' . var_export($this->limit, true) . ']');
                continue;
            }

            if (!array_key_exists($name, $attributes) || $this->replace === true) {
                $attributes[$name] = array();
            }

            if (in_array($value, $attributes[$name], true)) {
                /* Value already exists in attribute. */
                SimpleSAML\Logger::debug('AttributeFromSQL: skipping duplicate attribute/value tuple ' . $name . '=\'' . $value . '\'');
                continue;
            }

            $attributes[$name][] = $value;
        }
    }
}
