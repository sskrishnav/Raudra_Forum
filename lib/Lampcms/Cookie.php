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


namespace Lampcms;

/**
 * Static class for setting and getting cookie(s)
 *
 * @author Dmitri Snytkine
 *
 */
class Cookie
{

    /**
     * Function for setting or deleting login cookie
     * the value of the s cookie is a hash of user password
     * the value of the uid cookie is the userID
     *
     * @param integer $intUserId userID
     * @param         $strSID
     * @param string  $cookieName
     *
     * @throws DevException
     * @internal param bool $boolKeepSigned true if user checked 'remember me' box on login form
     * @internal param string $strPassword user's password
     * @return void cookies are sent to browser
     */
    public static function sendLoginCookie($intUserId, $strSID, $cookieName = 'uid')
    {
        if (!is_numeric($intUserId)) {
            throw new DevException('wrong value of $intUserId param. Must be numeric. Was: ' . $intUserId);
        }

        /**
         * sid cookie is always sent out
         * this is a very important cookie
         * and will help us spot already registered user
         * who is also trying to either re-register
         * OR login with third party auth system like
         * Google Friend Connect or Facebook connect
         */

        $salt = LAMPCMS_COOKIE_SALT;
        $cookieSid = \hash('sha256', $intUserId . $salt);
        $cookie = \http_build_query(array('uid' => $intUserId, 's' => $cookieSid));

        self::set('sid', $strSID);
        self::set($cookieName, $cookie);

    }


    /**
     * Sends cookie
     *
     * @param string     $name    name of cookie
     *
     * @param string     $val     value of cookie
     *
     * @param int|string $ttl     expiration time in seconds
     *                            default is 63072000 means 2 years
     *
     * @param string     $sDomain optional if set the setcookie will use
     *                            this value instead of LAMPCMS_COOKIE_DOMAIN constant
     *
     * @throws DevException
     * @return bool
     */
    public static function set($name, $val, $ttl = 63072000, $sDomain = null)
    {

        if (headers_sent($filename, $linenum)) {
            e('Cannot set cookie: ' . $name . ' ' . $val . ' because headers have already been sent in ' . $filename . ' on line ' . $linenum);
            return;
        }

        $sDomain = (!empty($sDomain)) ? $sDomain : \trim(constant('LAMPCMS_COOKIE_DOMAIN'));
        $sDomain = (empty($sDomain) || 'null' === $sDomain) ? null : $sDomain;

        $t = time() + $ttl;

        if (false === $sent = \setcookie($name, $val, $t, '/', $sDomain, false, false)) {

            $err = 'Unable to send cookie: ' . $name . ' val ' . $val . ' ttl: ' . $ttl . ' time: ' . $t . ' domain: ' . $sDomain;
            e($err);
            throw new DevException($err);
        }

        return true;
    }


    /**
     * Sends cookie with expiration
     * in the past, which will delete the cookie
     *
     * @param mixed $name a string
     *                    or array of cookies to delete
     *
     * @throws DevException
     * @return bool
     */
    public static function delete($name)
    {
        if (!is_string($name) && !is_array($name)) {
            throw new DevException('wrong type of $name param: ' . gettype($name));
        }

        $name = (is_string($name)) ? (array)$name : $name;


        foreach ($name as $val) {
            self::set($val, false, -3600000);
        }

        return true;
    }


    /**
     * Sends out the 'ref' cookie
     * if it has not already been set
     * and if user came from a website
     * different from currently viewed domain
     *
     *
     * @return void
     */
    public static function sendRefferrerCookie()
    {

        if (!isset($_COOKIE['ref']) && isset($_SERVER['HTTP_REFERER'])) {
            $strReferrer = $_SERVER['HTTP_REFERER'];
            $res = preg_match('@^(?:http(?:s*)://)?([^/]+)@i', $strReferrer, $matches);
            if (empty($res) || !is_array($matches) || empty($matches[1])) {
                d('cannot extract HTTP_REFERRER: ' . $_SERVER['HTTP_REFERER'] . ' $res: ' . $res);
                return;
            }

            $host = $matches[1];

            /**
             * If user came from a site other that this domain,
             * set referrer in SESSION
             */
            if (strtolower($_SERVER['HTTP_HOST']) !== strtolower($host)) {
                self::set('ref', $strReferrer);
            }
        }
    }


    /**
     * Sets the cookie 'sid' (first visit)
     * for about 6 years.
     * The value of 'sid' cookie starts with the timestamp
     * so we can always extract the first visit from it
     *
     * @return void
     */
    public static function sendFirstVisitCookie()
    {
        if (!isset($_COOKIE['sid'])) {
            self::set('sid', String::makeSid(), 189216000);
        }
    }


    /**
     * Returns value of specific cookie name
     *
     * @param string $cookieName
     *
     * @param mixed $fallbackVal a value to return if cookie
     * does not exist or its value is empty
     *
     * @return mixed value if cookie found or false
     * if cookie not found
     */
    public static function get($cookieName, $fallbackVal = false)
    {
        if (!isset($_COOKIE) || empty($_COOKIE)) {

            return $fallbackVal;
        }

        $val = \filter_input(INPUT_COOKIE, $cookieName, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);

        if(empty($val)){
            return $fallbackVal;
        }

       if('tzn' === $cookieName){
           $val = \urldecode($val);
       }

        return $val;
    }


    /**
     * Returns value of first visit extracted
     * from sid cookie or false if sid cookie not present
     *
     * @param bool $bFirstVisitOnly
     *
     * @internal                                param \Lampcms\if $bool $bFirstVisitOnly if set to true then return
     *                                          only the value of first visit, otherwise
     *                                          return the value of sid cookie
     *
     * @return mixed int timestamp of first visit
     *                                          or false if value not found in cookie
     */
    public static function getSidCookie($bFirstVisitOnly = false)
    {

        if (false === $sid = self::get('sid')) {

            return false;
        }

        $ts = substr($sid, 0, 10);

        /**
         * All first 10 chars must be numeric
         * or it's not a valid timestamp
         * it also means that our 'sid' cookie is
         * not valid and we should re-send it but...
         * but then we have a problem because sid cookie is tied to userID in
         * the database, so we can't just regenerate another sid
         * we must actually get it from the database for
         * the currently logged in user
         * at this point we don't even know if user is logged in or not,
         * so we will not do anything like resetting the sid cookie
         * but we will delete it just in case so as to prevent
         * the same problem
         *
         */
        if (preg_match('/\D/', $ts)) {
            e('LampcmsError sid cookie is not valid: ' . $sid);

            self::deleteCookie('sid');

            return false;
        }

        return ($bFirstVisitOnly) ? (int)$ts : $sid;
    }

}
