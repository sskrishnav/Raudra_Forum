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
 *    the website's Questions/Answers functionality is powered by lampcms.com
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


namespace Lampcms\Config;

use Lampcms\IniException;

/**
 * Object represents the parsed !config.ini file
 * has accessor for the whole section via getSection
 * or access values from the CONSTANTS section via
 * the magic __get method like this:
 * Ini->ADMIN_EMAIL
 *
 *
 * @author admin
 *
 */
class Ini extends \Lampcms\LampcmsArray
{

    protected $iniFile;

    /**
     * Array of Config\Section objects
     * key is section name
     * value of object
     *
     * @var array
     */
    protected $sections = array();

    /**
     * Constructor
     *
     * @param string $iniFile
     *
     * @throws IniException if unable to parse ini file
     *
     */
    public function __construct($iniFile = null)
    {
        if (null === $iniFile && defined('CONFIG_FILE_PATH')) {
            $iniFile = CONFIG_FILE_PATH;
        }

        $this->iniFile = (!empty($iniFile)) ? $iniFile : \rtrim(constant('LAMPCMS_CONFIG_DIR'), ' /\\') . DIRECTORY_SEPARATOR . '!config.ini';

        if (!\file_exists($this->iniFile)) {
            throw new IniException('Ini file not found at this location: ' . $this->iniFile);
        }

        $aIni = \parse_ini_file($this->iniFile, true);

        if (empty($aIni)) {
            throw new IniException('Unable to parse ini file: ' . $this->iniFile . ' probably a syntax error in file of file does not exist');
        }

        //parent::__construct($aIni);
        $this->exchangeArray($aIni);
    }


    /**
     * Get value of config var from
     * object
     *
     * @param string $name
     *
     * @throws IniException if CONSTANTS key
     * does not exist OR if var
     * does not exist and is a required var
     *
     * @return string value of $name
     */
    public function getVar($name)
    {
        if (!$this->offsetExists('CONSTANTS')) {
            throw new IniException('"CONSTANTS" section is missing in !config.ini file: ' . $this->iniFile . ' config: ' . \print_r($this->getArrayCopy(), true));
        }

        $aConstants = $this->offsetGet('CONSTANTS');

        /**
         * TEMP_DIR returns path
         * to temp always ends with DIRECTORY_SEPARATOR
         * if TEMP_DIR not defined in !config.ini
         * then will use system's default temp dir
         *
         */
        if ('TEMP_DIR' === $name) {
            if (!empty($aConstants['TEMP_DIR'])) {

                $tmpDir = \rtrim($aConstants['TEMP_DIR'], '/');
                $tmpDir .= DIRECTORY_SEPARATOR;

                return $tmpDir;
            }

            return \sys_get_temp_dir();
        }


        if (!array_key_exists($name, $aConstants) && !$this->offsetExists($name) && ('LOG_FILE_PATH' !== $name)) {

            throw new IniException('Error: configuration param: ' . $name . ' does not exist in config file ' . $this->iniFile);
        }

        if ('MAGIC_MIME_FILE' === $name) {
            if (!empty($aConstants['MAGIC_MIME_FILE']) && !is_readable($aConstants['MAGIC_MIME_FILE'])) {
                throw new IniException('magic mime file does not exist in this location or not readable: ' . $aConstants['MAGIC_MIME_FILE']);
            }
        }

        switch ( $name ) {
            case 'SITE_URL':
                if (empty($aConstants['SITE_URL'])) {
                    throw new IniException('Value of SITE_URL in ' . $this->iniFile . ' file SHOULD NOT be empty!');
                }

                $ret = \rtrim($aConstants['SITE_URL'], '/');
                break;

            /**
             * If these constants are not specifically set
             * then we should return the path to our
             * main website.
             * This is because we need to use absolute url, not
             * relative url for these.
             * The reason is if using virtual hosting, then
             * relative urls will point to just /images/
             * so they will actually resolve to individual's own domain + path
             * for example http://somedude.outsite.com/images/
             * and on another user's site http://johnny.oursite.com/images/
             * This will cause chaos in browser caching.
             * Browser will think (rightfully so) that these are different sites.
             *
             * That's why we must point to our main site
             * for all images, css, js, etc... so that no matter whose
             * site we are on the browser can use cached files and most
             * importantly will not keep storing the same images in cache for each
             * sub-domain
             */
            case 'THUMB_IMG_SITE':
            case 'ORIG_IMG_SITE':
                $ret = (empty($aConstants[$name])) ? $this->__get('SITE_URL') : \rtrim($aConstants[$name], '/');
                break;

            case 'EMAIL_ADMIN':
                if (empty($aConstants[$name])) {
                    throw new IniException($name . ' param in ' . $this->iniFile . ' file has not been set! Please make sure it is set');
                }

                $ret = \trim($aConstants[$name], "\"'");
                /**
                 * Always lower-case admin email
                 * otherwise it may cause problems during
                 * the registration of the first account
                 */
                if ('EMAIL_ADMIN' === $name) {
                    $ret = \mb_strtolower($ret);
                }
                break;


            case 'LOG_DIR':
                $ret = (empty($aConstants['LOG_DIR'])) ? \dirname(LAMPCMS_WWW_DIR) . DIRECTORY_SEPARATOR . 'logs' : \rtrim($aConstants['LOG_DIR'], DIRECTORY_SEPARATOR);
                break;

            case 'LOG_FILE_PATH':
                if (\substr(PHP_SAPI, 0, 3) === 'cli') {
                    $ret = $this->__get('LOG_DIR') . DIRECTORY_SEPARATOR . 'cli-php.log';
                } elseif ((\substr(PHP_SAPI, 0, 3) === 'cgi')) {
                    $ret = $this->__get('LOG_DIR') . DIRECTORY_SEPARATOR . 'cgi-php.log';
                } else {
                    $ret = $this->__get('LOG_DIR') . DIRECTORY_SEPARATOR . 'php.log';
                }
                break;

            case 'POINTS':
                if (!array_key_exists('POINTS', $this->sections)) {
                    $this->sections['POINTS'] = new PointsSection($this->getSection('POINTS'));
                }
                $ret = $this->sections['POINTS'];
                break;

            case 'MYCOLLECTIONS':
                if (!array_key_exists('MYCOLLECTIONS', $this->sections)) {
                    $this->sections['MYCOLLECTIONS'] = new MycollectionsSection($this->getSection('MYCOLLECTIONS'));
                }
                $ret = $this->sections['MYCOLLECTIONS'];
                break;


            default:
                $ret = $aConstants[$name];
                break;
        }

        return $ret;
    }


    /**
     * Magic method to get
     * a value of config param
     * from ini array's CONSTANTS section
     *
     * This is how other objects get values
     * from this object
     * most of the times
     *
     * @return string a value of $name
     *
     * @param string $name
     *
     * @throws LampcmsIniException if $name
     * does not exist as a key in this->aIni
     *
     */
    public function __get($name)
    {
        return $this->getVar($name);
    }


    public function __set($name, $val)
    {
        throw new IniException('Not allowed to set value this way');
    }


    /**
     *
     * @param string $name name of section in !config.ini file
     *
     * @throws \Lampcms\IniException
     * @return array associative array of
     * param => val of all params belonging to
     * one section in !config.ini file
     */
    public function getSection($name)
    {
        if (!$this->offsetExists($name)) {
            d('no section ' . $name . ' in config file');

            throw new IniException('Section ' . $name . ' does not exist in config file ' . $this->iniFile);
        }

        return $this->offsetGet($name);
    }


    /**
     * Setter to set particular section
     * This is useful during unit testing
     * We can set the "MONGO" section with arrays
     * of out test database so that read/write
     * operations are performed only on test database
     * Also can set values of other sections like "TWITTER", "FACEBOOK",
     * "GRAVATAR" or any other section that we want to "mock" during test
     *
     * @param string $name name of section in !config.ini file
     *
     * @param array  $val  array of values for this section
     *
     * @return object $this
     */
    public function setSection($name, array $val)
    {
        $this->offsetSet($name, $val);

        return $this;
    }

    /**
     * Get the value of $var from section named $section
     *
     * @param string $section name of section that supposed to contain $var
     * @param string $var     name of variable to get from section
     *
     * @return string value of $var from section name $section
     * @throws \Lampcms\IniException if either section does not exist
     * or section does not contain variable name $var
     */
    public function getSectionVar($section, $var)
    {
        $a = $this->getSection($section);

        if (!array_key_exists($var, $a)) {
            throw new IniException($var . ' does not exist in section: ' . $section);
        }

        return $a[$var];
    }


    /**
     * Creates and returns array of
     * some config params;
     *
     * This array is usually added as json object
     * to some of the pages that then use javascript
     * to get values from it.
     *
     * @return array
     */
    public function getSiteConfigArray()
    {
        $a         = array();
        $aUriParts = $this->getSection('URI_PARTS');

        if ('' !== $imgSite = $aUriParts['IMAGE_SITE']) {
            $a['IMG_SITE'] = $imgSite;
        }

        if ('' !== $avatarSite = $aUriParts['AVATAR_IMG_SITE']) {
            $a['LAMPCMS_AVATAR_IMG_SITE'] = $avatarSite;
        }

        if ('' !== $cssSite = $aUriParts['CSS_SITE']) {
            $a['CSS_SITE'] = $cssSite;
        }

        if ('' !== $jsSite = $aUriParts['JS_SITE']) {
            $a['JS_SITE'] = $jsSite;
        }


        return $a;
    }

}
