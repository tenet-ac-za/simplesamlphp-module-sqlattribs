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

    public function testAny()
    {
        $this->assertTrue(true, 'Just for travis.yml test');
    }
}
