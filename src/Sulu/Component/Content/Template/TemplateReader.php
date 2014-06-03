<?php
/*
* This file is part of the Sulu CMS.
*
* (c) MASSIVE ART WebServices GmbH
*
* This source file is subject to the MIT license that is bundled
* with this source code in the file LICENSE.
*/

namespace Sulu\Component\Content\Template;

use Exception;
use Sulu\Exception\FeatureNotImplementedException;
use Sulu\Component\Content\Template\Exception\InvalidXmlException;
use Sulu\Component\Content\Template\Exception\InvalidArgumentException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * reads a template xml and returns a array representation
 */
class TemplateReader implements LoaderInterface
{
    const SCHEME_PATH = '/Resources/schema/template/template-1.0.xsd';

    private $requiredTags = array(
        'sulu.node.name'
    );

    private $runningRequiredTags;

    private $runningTags;

    /**
     * {@inheritdoc}
     */
    public function load($resource, $type = null)
    {
        // init running vars
        // DEEP COPY
        $this->runningRequiredTags = array_merge(array(), $this->requiredTags);
        $this->runningTags = array();

        // read file
        $xmlDocument = XmlUtils::loadFile($resource, __DIR__ . static::SCHEME_PATH);

        // generate xpath for file
        $xpath = new \DOMXPath($xmlDocument);
        $xpath->registerNamespace('x', 'http://schemas.sulu.io/template/template');

        // init result
        $result = $this->loadTemplateAttributes($xpath);

        // load properties
        $result['properties'] = $this->loadProperties('/x:template/x:properties/x:*', $xpath);

        if (sizeof($this->runningRequiredTags) > 0) {
            throw new InvalidXmlException(
                sprintf(
                    'Tag(s) %s required but not found',
                    join(',', $this->runningRequiredTags)
                )
            );
        }

        return $result;
    }

    /**
     * load basic template attributes
     */
    private function loadTemplateAttributes(\DOMXPath $xpath)
    {
        $result = array(
            'key' => $this->getValueFromXPath('/x:template/x:key', $xpath),
            'view' => $this->getValueFromXPath('/x:template/x:view', $xpath),
            'controller' => $this->getValueFromXPath('/x:template/x:controller', $xpath),
            'cacheLifetime' => $this->getValueFromXPath('/x:template/x:cacheLifetime', $xpath),
        );

        $result = array_filter($result);
        if (sizeof($result) < 4) {
            throw new InvalidXmlException();
        }

        return $result;
    }

    /**
     * load properties from given context
     */
    private function loadProperties($path, \DOMXPath $xpath, \DOMNode $context = null)
    {
        $result = array();

        /** @var \DOMElement $node */
        foreach ($xpath->query($path, $context) as $node) {
            if ($node->tagName === 'property') {
                $value = $this->loadProperty($xpath, $node);
                $result[$value['name']] = $value;
            }
        }

        return $result;
    }

    /**
     * load single property
     */
    private function loadProperty(\DOMXPath $xpath, \DOMNode $node)
    {
        $result = $this->loadValues(
            $xpath,
            $node,
            array('name', 'title', 'type', 'minOccurs', 'maxOccurs')
        );
        
        $result['mandatory'] = $this->getBooleanValueFromXPath('@mandatory', $xpath, $node);
        $result['tags'] = $this->loadTags('x:tag', $xpath, $node);
        $result['params'] = $this->loadParams('x:params/x:param', $xpath, $node);

        return $result;
    }

    /**
     * load tags from given tag and validates them
     */
    private function loadTags($path, \DOMXPath $xpath, \DOMNode $context = null)
    {
        $result = array();

        /** @var \DOMElement $node */
        foreach ($xpath->query($path, $context) as $node) {
            $tag = $this->loadTag($xpath, $node);
            $this->validateTag($tag);

            $result[] = $tag;
        }

        return $result;
    }

    /**
     * validates a single tag
     */
    private function validateTag($tag)
    {
        // remove tag from required tags
        $this->runningRequiredTags = array_diff($this->runningRequiredTags, array($tag['name']));

        // check for duplicated priority
        if (
            isset($this->runningTags[$tag['name']]) &&
            in_array(
                $tag['priority'],
                $this->runningTags[$tag['name']]
            )
        ) {
            throw new InvalidXmlException(
                sprintf(
                    'Priority %s of tag %s exists duplicated',
                    $tag['priority'],
                    $tag['name']
                )
            );
        } elseif (!isset($this->runningTags[$tag['name']])) {
            $this->runningTags[$tag['name']] = array();
        }

        $this->runningTags[$tag['name']][] = $tag['priority'];
    }

    /**
     * load single tag
     */
    private function loadTag(\DOMXPath $xpath, \DOMNode $node)
    {
        return $this->loadValues($xpath, $node, array('name', 'priority'));
    }

    /**
     * load params from given node
     */
    private function loadParams($path, \DOMXPath $xpath, \DOMNode $context = null)
    {
        $result = array();

        /** @var \DOMElement $node */
        foreach ($xpath->query($path, $context) as $node) {
            $result[] = $this->loadParam($xpath, $node);
        }

        return $result;
    }

    /**
     * load single param
     */
    private function loadParam(\DOMXPath $xpath, \DOMNode $node)
    {
        return $this->loadValues($xpath, $node, array('name', 'value'));
    }

    /**
     * load values defined by key from given node
     */
    private function loadValues(\DOMXPath $xpath, \DOMNode $node, $keys, $prefix = '@')
    {
        $result = array();

        foreach ($keys as $key) {
            $result[$key] = $this->getValueFromXPath($prefix . $key, $xpath, $node);
        }

        return $result;
    }

    /**
     * returns boolean value of path
     */
    private function getBooleanValueFromXPath($path, \DOMXPath $xpath, \DomNode $context = null)
    {
        return $this->getValueFromXPath($path, $xpath, $context) === 'true' ? true : false;
    }

    /**
     * returns value of path
     */
    private function getValueFromXPath($path, \DOMXPath $xpath, \DomNode $context = null, $default = null)
    {
        try {
            return $xpath->query($path, $context)->item(0)->nodeValue;
        } catch (Exception $ex) {
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        throw new FeatureNotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function getResolver()
    {
        throw new FeatureNotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function setResolver(LoaderResolverInterface $resolver)
    {
        throw new FeatureNotImplementedException();
    }
}
