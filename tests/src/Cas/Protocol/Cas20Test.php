<?php

declare(strict_types=1);

namespace SimpleSAML\Casserver;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\casserver\Cas\Protocol\Cas20;
use SimpleSAML\XML\DOMDocumentFactory;

final class Cas20Test extends TestCase
{
    /** @var \DOMDocument */
    protected DOMDocument $document;


    /**
     */
    protected function setUp(): void
    {
        $this->document = DOMDocumentFactory::fromFile(
            dirname(__FILE__, 4) . '/resources/xml/testAttributeToXmlConversion.xml',
        );
    }


    /**
     */
    public function testAttributeToXmlConversion(): void
    {
        $casConfig = Configuration::loadFromArray([
            'attributes' => true, //send all attributes
        ]);

        $userAttributes = [
            'lastName' => ['lasty'],
            'valuesAreEscaped' => [
                '>abc<blah>',
            ],
            // too many illegal characters
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname' => ['Firsty'],
            // ':' will get turn to '_'
            'urn:oid:0.9.2342.19200300.100.1.1' => ['someValue'],
            'urn:oid:1.3.6.1.4.1.34199.1.7.1.5.2' => [
                'CN=Some-Service,OU=Non-Privileged,OU=Groups,DC=example,DC=com',
                'CN=Other Servics,OU=Non-Privileged,OU=Groups,DC=example,DC=com',
            ],
        ];

        $casProtocol = new Cas20($casConfig);
        $casProtocol->setAttributes($userAttributes);

        // We will remove the cas:authenticationDate element since we know that it will fail. The dates will not match
        $xml = $casProtocol->getValidateSuccessResponse('myUser');
        $actualXml = simplexml_load_string((string)$xml);

        $expectedXml = simplexml_load_string($this->document->saveXML($this->document->documentElement));

        // We will remove the cas:authenticationDate element since we know that it will fail. The dates will not match
        $authenticationNodeToDeleteExpected = $expectedXml->xpath('//cas:authenticationDate')[0];
        $authenticationNodeToDeleteActual = $actualXml->xpath('//cas:authenticationDate')[0];
        unset($authenticationNodeToDeleteExpected[0], $authenticationNodeToDeleteActual[0]);

        $this->assertEquals(
            $expectedXml->asXML(),
            $actualXml->asXML(),
        );
    }
}
