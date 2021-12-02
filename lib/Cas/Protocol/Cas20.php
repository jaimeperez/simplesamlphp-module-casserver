<?php

/*
 *    simpleSAMLphp-casserver is a CAS 1.0 and 2.0 compliant CAS server in the form of a simpleSAMLphp module
 *
 *    Copyright (C) 2013  Bjorn R. Jensen
 *
 *    This library is free software; you can redistribute it and/or
 *    modify it under the terms of the GNU Lesser General Public
 *    License as published by the Free Software Foundation; either
 *    version 2.1 of the License, or (at your option) any later version.
 *
 *    This library is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 *    Lesser General Public License for more details.
 *
 *    You should have received a copy of the GNU Lesser General Public
 *    License along with this library; if not, write to the Free Software
 *    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Cas\Protocol;

use DOMDocument;
use DOMElement;
use DOMException;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\XML\DOMDocumentFactory;

class Cas20
{
    /** @var bool $sendAttributes */
    private bool $sendAttributes;

    /** @var bool $base64EncodeAttributes */
    private bool $base64EncodeAttributes;

    /** @var string|null $base64IndicatorAttribute */
    private ?string $base64IndicatorAttribute;

    /** @var array $attributes */
    private array $attributes = [];

    /** @var string|null $proxyGrantingTicketIOU */
    private ?string $proxyGrantingTicketIOU = null;


    /**
     * @param \SimpleSAML\Configuration $config
     */
    public function __construct(Configuration $config)
    {
        $this->sendAttributes = $config->getValue('attributes', false);
        $this->base64EncodeAttributes = $config->getValue('base64attributes', false);
        $this->base64IndicatorAttribute = $config->getValue('base64_attributes_indicator_attribute', null);
    }


    /**
     * @param array $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }


    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }


    /**
     * @param string $proxyGrantingTicketIOU
     */
    public function setProxyGrantingTicketIOU(string $proxyGrantingTicketIOU): void
    {
        $this->proxyGrantingTicketIOU = $proxyGrantingTicketIOU;
    }


    /**
     * @return string|null
     */
    public function getProxyGrantingTicketIOU(): ?string
    {
        return $this->proxyGrantingTicketIOU;
    }


    /**
     * @param string $username
     * @return string
     */
    public function getValidateSuccessResponse(string $username): string
    {
        $xmlDocument = DOMDocumentFactory::create();
        $xmlDocument->formatOutput = true;

        $root = $xmlDocument->createElement("cas:serviceResponse");
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cas', 'http://www.yale.edu/tp/cas');

        $usernameNode = $xmlDocument->createTextNode($username);
        $casUser = $xmlDocument->createElement('cas:user');
        $casUser->appendChild($usernameNode);

        $casSuccess = $xmlDocument->createElement('cas:authenticationSuccess');
        $casSuccess->appendChild($casUser);

        if (is_string($this->proxyGrantingTicketIOU)) {
            $iouNode = $xmlDocument->createTextNode($this->proxyGrantingTicketIOU);
            $iouElement = $xmlDocument->createElement("cas:proxyGrantingTicket");
            $iouElement->appendChild($iouNode);
            $casSuccess->appendChild($iouElement);
        }

        if ($this->sendAttributes && count($this->attributes) > 0) {
            $casAttributes = $xmlDocument->createElement('cas:attributes');

            foreach ($this->attributes as $name => $values) {
                // Fix the most common cause of invalid XML elements
                $_name = str_replace(':', '_', $name);
                if ($this->isValidXmlName($_name) === true) {
                    foreach ($values as $value) {
                        $casAttributes->appendChild(
                            $this->generateCas20Attribute($xmlDocument, $_name, $value)
                        );
                    }
                } else {
                    Logger::warning("Dom exception creating attribute '$_name'. Continuing without atrribute'");
                }
            }

            if (!is_null($this->base64IndicatorAttribute)) {
                $casAttributes->appendChild(
                    $this->generateCas20Attribute(
                        $xmlDocument,
                        $this->base64IndicatorAttribute,
                        $this->base64EncodeAttributes ? "true" : "false"
                    )
                );
            }

            $casSuccess->appendChild($casAttributes);
        }

        $root->appendChild($casSuccess);
        $xmlDocument->appendChild($root);

        return $xmlDocument->saveXML();
    }


    /**
     * @param string $errorCode
     * @param string $explanation
     * @return string
     */
    public function getValidateFailureResponse(string $errorCode, string $explanation): string
    {
        $xmlDocument = DOMDocumentFactory::create();

        $root = $xmlDocument->createElement("cas:serviceResponse");
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cas', 'http://www.yale.edu/tp/cas');

        $casFailureCode = $xmlDocument->createAttribute('code');
        $casFailureCode->value = $errorCode;

        $casFailureNode = $xmlDocument->createTextNode($explanation);
        $casFailure = $xmlDocument->createElement('cas:authenticationFailure');
        $casFailure->appendChild($casFailureNode);
        $casFailure->appendChild($casFailureCode);

        $root->appendChild($casFailure);

        $xmlDocument->appendChild($root);

        return $xmlDocument->saveXML();
    }


    /**
     * @param string $proxyTicketId
     * @return string
     */
    public function getProxySuccessResponse(string $proxyTicketId): string
    {
        $xmlDocument = DOMDocumentFactory::create();

        $root = $xmlDocument->createElement("cas:serviceResponse");
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cas', 'http://www.yale.edu/tp/cas');

        $casProxyTicketIdNode = $xmlDocument->createTextNode($proxyTicketId);
        $casProxyTicketId = $xmlDocument->createElement('cas:proxyTicket');
        $casProxyTicketId->appendChild($casProxyTicketIdNode);

        $casProxySuccess = $xmlDocument->createElement('cas:proxySuccess');
        $casProxySuccess->appendChild($casProxyTicketId);

        $root->appendChild($casProxySuccess);
        $xmlDocument->appendChild($root);

        return $xmlDocument->saveXML();
    }


    /**
     * @param string $errorCode
     * @param string $explanation
     * @return string
     */
    public function getProxyFailureResponse(string $errorCode, string $explanation): string
    {
        $xmlDocument = DOMDocumentFactory::create();

        $root = $xmlDocument->createElement("cas:serviceResponse");
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cas', 'http://www.yale.edu/tp/cas');

        $casFailureCode = $xmlDocument->createAttribute('code');
        $casFailureCode->value = $errorCode;

        $casFailureNode = $xmlDocument->createTextNode($explanation);
        $casFailure = $xmlDocument->createElement('cas:proxyFailure');
        $casFailure->appendChild($casFailureNode);
        $casFailure->appendChild($casFailureCode);

        $root->appendChild($casFailure);

        $xmlDocument->appendChild($root);

        return $xmlDocument->saveXML();
    }


    /**
     * @param \DOMDocument $xmlDocument
     * @param string $attributeName
     * @param string $attributeValue
     * @return \DOMElement
     */
    private function generateCas20Attribute(
        DOMDocument $xmlDocument,
        string $attributeName,
        string $attributeValue
    ): DOMElement {
        $attributeValueNode = $xmlDocument->createTextNode($this->base64EncodeAttributes
            ? base64_encode($attributeValue)
            : $attributeValue);

        $attributeElement = $xmlDocument->createElement('cas:' . $attributeName);

        $attributeElement->appendChild($attributeValueNode);

        return $attributeElement;
    }


    /**
     * XML element names have a lot of rules and not every SAML attribute name can be converted.
     * Ref: https://www.w3.org/TR/REC-xml/#NT-NameChar
     * https://stackoverflow.com/q/2519845/54396
     * must only start with letter or underscore
     * cannot start with 'xml' (or maybe it can - stackoverflow commenters don't agree)
     * cannot contain a ':' since those are for namespaces
     * cannot contains space
     * can only  contain letters, digits, hyphens, underscores, and periods
     * @param string $name The attribute name to be used as an element
     * @return bool true if $name would make a valid xml element.
     */
    private function isValidXmlName(string $name): bool
    {
        try {
            new DOMElement($name);
            return true;
        } catch (DOMException $e) {
                return false;
        }
    }
}
