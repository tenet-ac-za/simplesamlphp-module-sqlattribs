sqlattribs:AttributeFromSQL
===========================

This SimpleSAMLphp auth proc filter allows you to provides additional
attributes from a SQL datastore. It is useful in situations where your
primary authsource is a directory (e.g. AD) that you do not have direct
control over, and you need to add additional attributes for specific
users but cannot add them into the directory/modify the schema.

Installation
------------

Once you have installed SimpleSAMLphp, installing this module is
very simple.  Just execute the following command in the root of your
SimpleSAMLphp installation:

```
composer.phar require safire-ac-za/simplesamlphp-module-sqlattribs:dev-master
```

where `dev-master` instructs Composer to install the `master` (**development**)
branch from the Git repository. See the
[releases](https://github.com/safire-ac-za/simplesamlphp-module-sqlattribs/releases)
available if you want to use a stable version of the module.

You then need to create the following table in your SQL database:

```sql
CREATE TABLE `AttributeFromSQL` (
    `uid` VARCHAR(100) NOT NULL,
	`sp` VARCHAR(250) DEFAULT '%',
    `attribute` VARCHAR(30) NOT NULL,
    `value` TEXT
) DEFAULT CHARSET=utf8;
```

Usage
-----

This module provides the _sqlattribs:AttributeFromSQL_ auth proc filter,
which can be used as follows:

```php
50 => array(
    'class'     => 'sqlattribs:AttributeFromSQL',
    'attribute' => 'eduPersonPrincipalName',
    'limit'     => array('eduPersonEntitlement', 'eduPersonAffiliation'),
    'replace'   => false,
    'database'  => array(
        'dsn'       => 'mysql:host=localhost;dbname=simplesamlphp',
        'username'  => 'yourDbUsername',
        'password'  => 'yourDbPassword',
        'table'     => 'AttributeFromSQL',
    ),
),
```

Where the parameters are as follows:

* `class` - the name of the class, must be _sqlattribs:AttributeFromSQL_

* `attribute` - the attribute to use as the uid/key for database searches, defaults to _eduPersonPrincipalName_ if not specified.

* `limit` - an optional array specifying the attribute names we can add. If not specified, all attributes that are found in the database are added. Defaults to allowing all attributes.

* `replace` - behaviour when an existing attribute of the same name is encountered. If `false` (the default) then new values are pushed into an array, creating a multi-valued attribute. If `true`, then existing attributes of the same name are replaced (deleted).

* `database` - an array containing information about the data store, with the following parameters:

  * `dsn` - the data source name, defaults to _mysql:host=localhost;dbname=simplesamlphp_

  * `username` - the username to connect to the database, defaults to none (blank username)

  * `password` - the password to connect to the database, defaults to none (blank password)

  * `table` - the name of the table/view to search for attributes, defaults to _AttributeFromSQL_

Adding attributes
-----------------

This module provides no interface to add attributes into the
database. This can be done manually with SQL similar to the following:

```sql
INSERT INTO AttributeFromSQL (uid, sp, attribute, value) VALUES ('user@example.org', '%', 'eduPersonEntitlement', 'urn:mace:exampleIdP.org:demoservice:demo-admin');
INSERT INTO AttributeFromSQL (uid, sp, attribute, value) VALUES ('user@example.org', 'https://idp.example.org/idp/shibboleth', 'eduPersonEntitlement', 'urn:mace:grnet.gr:eduroam:admin');
INSERT INTO AttributeFromSQL (uid, sp, attribute, value) VALUES ('user@example.org', '%', 'eduPersonAffiliation', 'faculty');
INSERT INTO AttributeFromSQL (uid, attribute, value) VALUES ('user@example.org', 'mail', 'user@example.org');
```

The optional _sp_ field (defaults to '%' with the above SQL CREATE) is used
to limit which SP sees a particular attribute. The special value `%`
is used to indicate all SPs. If you wish to indicate more than one SP but
not all, insert multiple lines.

Where multiple attributes of the same name occur, these become a single
multi-valued attribute. Thus assuming the user _user@example.org_
started with attributes of:

```php
$attributes = array(
   'eduPersonPrincipalName' => 'user@example.org',
   'eduPersonAffiliation' => array('member'),
   'displayName' => 'Example User',
),
```

The the above SQL table and example auth proc filter would lead to a
combined attribute set of:

```php
$attributes = array(
    'eduPersonPrincipalName' => 'user@example.org',
    'displayName' => 'Example User',
    'eduPersonEntitlement' => array(
        'urn:mace:exampleIdP.org:demoservice:demo-admin',
        'urn:mace:grnet.gr:eduroam:admin',
    ),
    'eduPersonAffiliation' => array(
        'member',
        'faculty',
    ),
),
```

Note that because the the `limit` parameter, the mail attribute was not added. And because `replace` was false, _eduPersonAffiliation_ was merged. It is assumed that this SP has an Entity Id of `https://sp.example.org/shibboleth-sp` - other SPs would not see the SP-specific _eduPersonEntitlement_ attribute.

