<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is licensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 *       the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attributes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2012 (or current year) Dmitri Snytkine
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: 0.2.45
 *
 *
 */


namespace Lampcms\Modules\Blogger;

use Lampcms\Dom\Document;
use Lampcms\String\HTMLString;

/**
 *
 * Class for constructing a new
 * blog entry - the xml file that will
 * be POSTed to Blogger API
 * This class is also used for parsing
 * the xml returned by blogger API - specifically
 * to extract the 'id' of the new entry
 *
 * @author Dmitri Snytkine
 *
 */
class Entry extends Document implements EntryInterface
{
    /**
     * Constructor
     *
     * @param string $xml
     * This should be left at default when we creating a new entry
     * it will be an xml string returned by the Blogger API in response
     * to a POST of our entry
     */
    public function __construct($xml = '<entry xmlns="http://www.w3.org/2005/Atom"/>')
    {
        parent::__construct();
        $this->loadXML($xml);
        $this->validateRoot();
    }


    /**
     * Validate to make sure the document element
     * is actually 'entry' tag
     * this is just to ensure that blogger
     * returned the xml with what we expect
     *
     *
     * @throws \Lampcms\Dom\Exception
     */
    protected function validateRoot()
    {
        $name = $this->documentElement->nodeName;
        if ('entry' !== \strtolower($name)) {
            throw new \Lampcms\Dom\Exception('Incorrect root node. It must be "entry", was: ' . $name);
        }
    }


    /**
     * Get value of 'id' of entry
     * The id is not present when we creating a new
     * entry but it is present in xml returned by
     * Blogger API after the post was accepted, in which case
     * the same xml is returned but now it contains some new elements,
     * id is one of them.
     * The Blogger API is superbly stupid and the
     * value of id is not really the pure value of post-id,
     * it's a string that contains blog-id, post-id and
     * other stuff concatinated together, like this:
     * <id>tag:blogger.com,1999:blog-4083976222769752292.post-3748921081779387177</id>
     * so we still
     * need to extract the actual value of id from it.
     *
     * @return mixed null if id not found | string value of id
     * which is numeric string
     *
     */
    public function getId()
    {
        $a = $this->documentElement->getElementsByTagName('id');
        if (0 === $a->length) {
            return null;
        }

        $val = $a->item(0)->nodeValue;
        $pos = \strrpos($val, '-');

        return (!$pos) ? null : \trim(\substr($val, ($pos + 1)));
    }


    /**
     * Set the value of title tag of
     * this blog entry
     *
     * @param string $title
     * @param string $type
     */
    public function setTitle($title, $type = 'text')
    {
        $this->documentElement
            ->getElementByTagName('title', true)
            ->addAttribute('type', $type)
            ->nodeValue = \htmlspecialchars($title, ENT_QUOTES, 'UTF-8', false);

        return $this;
    }


    /**
     * Set the value of body
     * of this entry
     *
     * @param object $content object HtmlString
     * html fragment. Be requiring this to be an object HtmlString
     * we guarantee that it is a valid html and also that
     * is has getXML() method - so we can get a valid XML representation
     * of the HTML fragment. This way tag like <br> which is valid in
     * HTML string but not valid in XML (XML must be <br/> will be
     * automatically converted to XML version of tag - <br/>
     * This is all done by DOMDocument object which basically can convert
     * valid html to valid xml when doing loadHTML() and then saveXML()
     *
     * @todo if $type is 'html' then we can just use getHTML() instead
     * But there is no reason to use any type other than xhtml
     * as it is the default type when posting entry to blogger
     *
     * @param string $type should not change this -it should
     * be xhtml for the html type of $content
     */
    public function setBody(HtmlString $content, $type = 'xhtml')
    {

        $content = $content->getXML();
        $this->documentElement
            ->getElementByTagName('content', true)
            ->addAttribute('type', $type)
            ->addChild('div', null, 'http://www.w3.org/1999/xhtml')
            ->appendXml($content);

        return $this;
    }


    /**
     * Adds element 'category' under the root
     * with this structure:
     * <category scheme="http://www.blogger.com/atom/ns#" term="marriage" />
     *
     * @param string $tag
     *
     * @return object $this
     */
    public function addTag($tag)
    {
        if (!is_string($tag)) {
            throw new \Lampcms\Dom\Exception('param $tag must be a string. Was: ' . gettype($tag));
        }

        $this->documentElement->addChild('category')
            ->addAttribute('scheme', 'http://www.blogger.com/atom/ns#')
            ->addAttribute('term', \htmlspecialchars($tag, ENT_QUOTES, 'UTF-8', false));

        return $this;
    }


    /**
     * Add multiple tags
     * @see addTag
     *
     * @param array $tags
     *
     * @return object $this
     */
    public function addTags(array $tags)
    {
        foreach ($tags as $t) {
            $this->addTag($t);
        }

        return $this;
    }

}
