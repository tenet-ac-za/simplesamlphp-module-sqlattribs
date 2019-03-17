<?php

namespace SimpleSAML\Test\Module\entattribs\Auth\Process;

class AttributeFromSQL extends \PHPUnit_Framework_TestCase
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
        $filter = new \SimpleSAML\Module\sqlattribs\Auth\Process\AttributeFromSQL($config, null);
        $filter->process($request);
        return $request;
    }

    protected function setUp()
    {
        \SimpleSAML\Configuration::loadFromArray([], '[ARRAY]', 'simplesaml');
    }

    /**
     * Test the example from docs
     */
    public function testExample()
    {
        $config = [
            'attribute' => 'eduPersonPrincipalName',
            'limit' => ['eduPersonEntitlement', 'eduPersonAffiliation'],
            'replace' => false,
            'database' => [
                'username' => 'phpunit',
                'password' => 'phpunit',
            ],
        ];
        $request = [
            'Attributes' => [
                'eduPersonPrincipalName' => ['user@example.org'],
                'eduPersonAffiliation' => ['member'],
                'displayName' => ['Example User'],
            ],
            'Destination' => [
                'entityid' => 'https://idp.example.org/idp/shibboleth',
            ],
        ];
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $expectedData = [
            'eduPersonPrincipalName' => ['user@example.org'],
            'displayName' => ['Example User'],
            'eduPersonEntitlement' => [
                'urn:mace:exampleIdP.org:demoservice:demo-admin',
                'urn:mace:grnet.gr:eduroam:admin',
            ],
            'eduPersonAffiliation' => [
                'member',
                'faculty',
            ],
        ];
        $this->assertEquals($expectedData, $attributes, "Expected data was not correct");
    }

    /**
     * Test attribute replacement
     */
    public function testReplace()
    {
        $config = [
            'attribute' => 'eduPersonPrincipalName',
            'limit' => ['mail', 'eduPersonAffiliation'],
            'replace' => true,
            'database' => [
                'username' => 'phpunit',
                'password' => 'phpunit',
            ],
        ];
        $request = [
            'Attributes' => [
                'eduPersonPrincipalName' => ['user@example.org'],
                'eduPersonAffiliation' => ['member'],
                'displayName' => ['Example User'],
            ],
            'Destination' => [
                'entityid' => 'https://idp.example.org/idp/shibboleth',
            ],
        ];
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $expectedData = [
            'eduPersonPrincipalName' => ['user@example.org'],
            'displayName' => ['Example User'],
            'eduPersonAffiliation' => [
                'faculty',
            ],
            'mail' => ['user@example.org'],
        ];
        $this->assertEquals($expectedData, $attributes, "Expected data was not correct");
    }

    /**
     * Test attribute replacement
     */
    public function testIgnoreExpires()
    {
        $config = [
            'attribute' => 'eduPersonPrincipalName',
            'limit' => ['mail',],
            'ignoreExpiry' => true,
            'database' => [
                'username' => 'phpunit',
                'password' => 'phpunit',
            ],
        ];
        $request = [
            'Attributes' => [
                'eduPersonPrincipalName' => ['user@example.org'],
                'displayName' => ['Example User'],
            ],
            'Destination' => [
                'entityid' => 'https://idp.example.org/idp/shibboleth',
            ],
        ];
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $expectedData = [
            'eduPersonPrincipalName' => ['user@example.org'],
            'displayName' => ['Example User'],
            'mail' => ['user@example.org', 'marty@example.org'],
        ];
        $this->assertEquals($expectedData, $attributes, "Expected data was not correct");
    }
}
