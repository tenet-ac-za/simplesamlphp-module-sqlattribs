<?php

declare(strict_types=1);

namespace SimpleSAML\Module\sqlattribs\Auth\Process;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Error;
use SimpleSAML\Logger;

/**
 * Filter to add attributes from a SQL data source
 *
 * This filter allows you to add attributes from a SQL datasource
 *
 * @author    Guy Halse, http://orcid.org/0000-0002-9388-8592
 * @copyright Copyright (c) 2019, Tertiary Education and Research Network of South Africa
 * @license   https://github.com/tenet-ac-za/simplesamlphp-module-sqlattribs/blob/master/LICENSE MIT License
 * @package   SimpleSAMLphp
 */
class AttributeFromSQL extends Auth\ProcessingFilter
{
    /** @var string The DSN we should connect to. */
    private string $dsn = 'mysql:host=localhost;dbname=simplesamlphp';

    /** @var string The username we should connect to the database with. */
    private ?string $username;

    /** @var string The password we should connect to the database with. */
    private ?string $password;

    /** @var array Options passed to PDO. */
    private $driver_options = [];

    /** @var string The name of the database table to use. */
    private string $table = 'AttributeFromSQL';

    /** @var string Username/UID attribute. */
    private string $identifyingAttribute = 'eduPersonPrincipalName';

    /** @var bool|false Should we replace existing attributes? */
    private bool $replace;

    /** @var array|null Limit returned attribute set */
    private ?array $limit = null;

    /** @var bool|false Should we ignore expiry */
    private bool $ignoreExpiry;

    /** @var string Character used to quote SQL identifiers. Default to " per SQL:1999 */
    private $sqlIdentifierQuoteChar = '"';

    /**
     * Initialize this filter, parse configuration.
     *
     * @param array $config Configuration information about this filter.
     * @param mixed $reserved For future use.
     * @throws \SimpleSAML\Error\Exception
     */
    public function __construct(array $config, $reserved)
    {
        parent::__construct($config, $reserved);

        if (array_key_exists('identifyingAttribute', $config)) {
            $this->identifyingAttribute = $config['identifyingAttribute'];
        }
        if (!is_string($this->identifyingAttribute) || !$this->identifyingAttribute) {
            throw new Error\Exception('AttributeFromSQL: identifyingAttribute name not valid');
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
            if (array_key_exists('driver_options', $config['database'])) {
                $this->driver_options = $config['database']['driver_options'];
            }
            if (array_key_exists('table', $config['database'])) {
                $this->table = $config['database']['table'];
            }
        }
        if (!is_string($this->dsn) || !$this->dsn) {
            throw new Error\Exception('AttributeFromSQL: invalid database DSN given');
        }
        if (!is_string($this->table) || !$this->table) {
            throw new Error\Exception('AttributeFromSQL: invalid database table');
        }

        if (array_key_exists('replace', $config)) {
            $this->replace = (bool)$config['replace'];
        } else {
            $this->replace = false;
        }

        if (array_key_exists('limit', $config)) {
            if (!is_array($config['limit'])) {
                throw new Error\Exception('AttributeFromSQL: limit must be an array of attribute names');
            }
            $this->limit = $config['limit'];
        }

        if (array_key_exists('ignoreExpiry', $config)) {
            $this->ignoreExpiry = (bool)$config['ignoreExpiry'];
        } else {
            $this->ignoreExpiry = false;
        }
    }

    /**
     * Create a database connection.
     *
     * @return \PDO The database connection.
     * @throws \SimpleSAML\Error\Exception
     */
    private function connect(): \PDO
    {
        try {
            $db = new \PDO($this->dsn, $this->username, $this->password, $this->driver_options);
        } catch (\PDOException $e) {
            throw new Error\Exception(
                'AttributeFromSQL: Failed to connect to \'' .
                $this->dsn . '\': ' . $e->getMessage()
            );
        }
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $driver = explode(':', $this->dsn, 2);
        $driver = strtolower($driver[0]);

        /* Driver specific initialization. */
        switch ($driver) {
            case 'mysql':
                $db->exec("SET NAMES 'utf8'");
                $this->sqlIdentifierQuoteChar = '`';
                break;
            case 'pgsql':
                $db->exec("SET NAMES 'UTF8'");
                $this->sqlIdentifierQuoteChar = '"';
                break;
        }

        return $db;
    }

    /**
     * Process this filter
     *
     * Logic is largely the same as (and lifted from) sqlauth:sql
     * @param mixed &$state
     * @throws \SimpleSAML\Error\Exception
     * @return void
     */
    public function process(array &$state): void
    {
        Assert::keyExists($state, 'Attributes');
        Assert::keyExists($state, 'Destination');
        Assert::keyExists($state['Destination'], 'entityid');

        $attributes =& $state['Attributes'];

        if (!array_key_exists($this->identifyingAttribute, $attributes)) {
            Logger::info('AttributeFromSQL: attribute \'' . $this->identifyingAttribute . '\' not set, declining');
            return;
        }

        $db = $this->connect();
        $iq = $this->sqlIdentifierQuoteChar;

        try {
            $sth = $db->prepare(
                'SELECT ' . $iq . 'attribute' . $iq . ',' . $iq . 'value' . $iq . ' FROM ' .
                $this->table . ' WHERE ' .
                $iq . 'uid' . $iq . '=? AND (' . $iq . 'sp' . $iq . '=\'%\' OR ' . $iq . 'sp' . $iq . '=?)' .
                ($this->ignoreExpiry ? '' : ' AND ' . $iq . 'expires' . $iq . '>CURRENT_DATE') .
                ';'
            );
        } catch (\PDOException $e) {
            throw new Error\Exception('AttributeFromSQL: prepare() failed: ' . $e->getMessage());
        }

        try {
            $res = $sth->execute([$attributes[$this->identifyingAttribute][0], $state["Destination"]["entityid"]]);
        } catch (\PDOException $e) {
            throw new Error\Exception(
                'AttributeFromSQL: execute(' . $attributes[$this->identifyingAttribute][0] .
                ', ' . $state["Destination"]["entityid"] . ') failed: ' . $e->getMessage()
            );
        }

        try {
            $data = $sth->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new Error\Exception('AttributeFromSQL: fetchAll() failed: ' . $e->getMessage());
        }

        if (count($data) === 0) {
            Logger::info(
                'AttributeFromSQL: no additional attributes for ' .
                $this->identifyingAttribute . '=\'' . $attributes[$this->identifyingAttribute][0] . '\''
            );
            return;
        }

        /* Extract attributes from the SQL datasource, and then merge them into
         * the existing attribute set. If $replace is set, overwrite any existing
         * attribute of the same name; otherwise add it as a multi-valued attribute
         */
        foreach ($data as $row) {
            if (empty($row['attribute']) || $row['value'] === null) {
                Logger::debug(
                    'AttributeFromSQL: skipping invalid attribute/value tuple: '
                    . var_export($row, true)
                );
                continue;
            }

            $name = (string)$row['attribute'];
            $value = (string)$row['value'];

            /* Limit the attribute set returned */
            if ($this->limit !== null && !in_array($name, $this->limit, true)) {
                Logger::notice(
                    'AttributeFromSQL: skipping unwanted attribute ' .
                    $name . ' [limited to: ' . join(', ', $this->limit) . ']'
                );
                continue;
            }

            if (!array_key_exists($name, $attributes) || $this->replace === true) {
                $attributes[$name] = [];
            }

            if (in_array($value, $attributes[$name], true)) {
                /* Value already exists in attribute. */
                Logger::debug(
                    'AttributeFromSQL: skipping duplicate attribute/value tuple ' .
                    $name . '=\'' . $value . '\''
                );
                continue;
            }

            $attributes[$name][] = $value;
        }
    }
}
