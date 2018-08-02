<?php
// Alias the PHPUnit 6.0 ancestor if available, else fall back to legacy ancestor
if (class_exists('\PHPUnit\Framework\TestCase', true) and !class_exists('\PHPUnit_Framework_TestCase', true)) {
    class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase', true);
}

class Test_sspmod_sqlattribs_Auth_Process_AttributeFromSQL extends \PHPUnit_Framework_TestCase
{
    /**
     * Helper function to run the filter with a given configuration.
     *
     * @param  array $config The filter configuration.
     * @param  array $request The request state.
     * @return array  The state array after processing.
     */
    private static function processFilter(array $config, array $request)
    {
        $filter = new \sspmod_sqlattribs_Auth_Process_AttributeFromSQL($config, null);
        $filter->process($request);
        return $request;
    }

    protected function setUp()
    {
        \SimpleSAML_Configuration::loadFromArray(array(), '[ARRAY]', 'simplesaml');
    }

    /**
     * Test the example from docs
     */
    public function testExample()
    {
        $config = array(
            'attribute' => 'eduPersonPrincipalName',
            'limit' => array('eduPersonEntitlement', 'eduPersonAffiliation'),
            'replace' => false,
            'database' => array(
                'username' => 'phpunit',
                'password' => 'phpunit',
            ),
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('user@example.org'),
                'eduPersonAffiliation' => array('member'),
                'displayName' => array('Example User'),
            ),
            'Destination' => array(
                'entityid' => 'https://idp.example.org/idp/shibboleth',
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $expectedData = array(
            'eduPersonPrincipalName' => array('user@example.org'),
            'displayName' => array('Example User'),
            'eduPersonEntitlement' => array(
                'urn:mace:exampleIdP.org:demoservice:demo-admin',
                'urn:mace:grnet.gr:eduroam:admin',
            ),
            'eduPersonAffiliation' => array(
                'member',
                'faculty',
            ),
        );
        $this->assertEquals($expectedData, $attributes, "Expected data was not correct");
    }

    /**
     * Test attribute replacement
     */
    public function testReplace()
    {
        $config = array(
            'attribute' => 'eduPersonPrincipalName',
            'limit' => array('mail', 'eduPersonAffiliation'),
            'replace' => true,
            'database' => array(
                'username' => 'phpunit',
                'password' => 'phpunit',
            ),
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('user@example.org'),
                'eduPersonAffiliation' => array('member'),
                'displayName' => array('Example User'),
            ),
            'Destination' => array(
                'entityid' => 'https://idp.example.org/idp/shibboleth',
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $expectedData = array(
            'eduPersonPrincipalName' => array('user@example.org'),
            'displayName' => array('Example User'),
            'eduPersonAffiliation' => array(
                'faculty',
            ),
            'mail' => array('user@example.org'),
        );
        $this->assertEquals($expectedData, $attributes, "Expected data was not correct");
    }
    /**
     * Test attribute replacement
     */

    public function testIgnoreExpires()
    {
        $config = array(
            'attribute' => 'eduPersonPrincipalName',
            'limit' => array('mail',),
            'ignoreExpiry' => true,
            'database' => array(
                'username' => 'phpunit',
                'password' => 'phpunit',
            ),
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('user@example.org'),
                'displayName' => array('Example User'),
            ),
            'Destination' => array(
                'entityid' => 'https://idp.example.org/idp/shibboleth',
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $expectedData = array(
            'eduPersonPrincipalName' => array('user@example.org'),
            'displayName' => array('Example User'),
            'mail' => array('user@example.org', 'marty@example.org'),
        );
        $this->assertEquals($expectedData, $attributes, "Expected data was not correct");
    }
}
