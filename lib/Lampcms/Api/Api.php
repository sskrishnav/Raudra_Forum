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


namespace Lampcms\Api;

use \Lampcms\Registry;
use \Lampcms\User;
use \Lampcms\Request;
use \Lampcms\Response;
use \Lampcms\UserAuth;

/**
 * Base class for all API calls
 * Includes methods for logging and enforcing
 * rate limit, authenticating client APPs
 * and creating Viewer object,
 * oResponse object
 * Output object based on type of
 * format a client requested - json, jsonp or xml
 * as well as doing base pre and post
 * processing of request
 *
 *
 * The main() method is implemented in
 * concrete sub-classes and is responsible
 * for generating actual data to be returned to
 * the client as well as populating the
 * $this->Output object
 *
 * @author Dmitri Snytkine
 *
 */
abstract class Api extends \Lampcms\Base
{
    /**
     * Flag indicates that this call
     * is subject to rate limit
     * Some POST-based API calls may be
     * excluded from rate limiting
     * This flag may be changed in concrete controller class
     *
     * @var bool
     */
    protected $bRateLimited = true;

    /**
     * Current daily rate limit
     * for the current client
     * This value is dynamic and depends on type
     * of client - anonymous, registered APP or
     * authenticated user
     *
     * @var int
     */
    protected $rateLimit;

    /**
     * Number of accesses already made
     * toda.
     *
     * @var int
     */
    protected $accessCounter = 0;


    /**
     * API config options
     * This array is a section
     * [API] from !config.ini file
     *
     * @var array
     */
    protected $aConfig;

    /**
     * Response object
     *
     * @var object of type Lampcms\Response
     */
    protected $oResponse;

    /**
     * Array of required HTTP Request params
     *
     * @var array
     */
    protected $aRequired = array();

    /**
     * Is set to true in sub-class
     * then if request type is not POST
     * an exception will be thrown
     * and will result in returning
     * HTTP error with code 405
     *
     * @var bool
     */
    protected $bRequirePost = false;

    /**
     * Request object
     *
     * @var object of type \Lampcms\Request
     */
    protected $Request;


    /**
     * Formatter is responsible for formatting
     * data into either json or xml string
     *
     * @var object of type \Lampcms\Api\Formatter
     */
    protected $Output;

    /**
     * MongoCursor that holds
     * found results
     *
     * @var object of type MongoCursor
     */
    protected $cursor;


    /**
     * Timestamp of the Questions's
     * latest activity (in unix timestamp)
     *
     * @var int
     */
    protected $startTime = 0;


    protected $endTime = 0;


    /**
     * Per-page limit
     * a maximum of this many items
     * will be included in result
     * This cannot be larger than the
     * MAX_RESULTS in [API] Section of !config.ini
     * or an exception will be thrown
     *
     * @var int
     */
    protected $limit = 20;


    /**
     * User ID of Viewer
     * This will be populated with
     * actual user id only in the OAuth2 based
     * version of the API
     *
     * @var int
     */
    protected $viewerId = 0;


    /**
     * Access id is a string that consists of
     * YearMonthDate_appID_userID
     * For example: 20111011_2342235_23423
     * This string uniquely identifies each
     * user accessing the API on a specific day
     *
     * Enter description here ...
     * @var string
     */
    protected $accessId;


    /**
     * Page ID
     * @var int
     */
    protected $pageID = 1;


    /**
     * Sort order
     * if sort=asc then this is set to 1
     * if sort=desc then this is set to -1
     *
     * @var int
     */
    protected $sortOrder = -1;

    /**
     * Field on which the sorting (ordering)
     * of questions will be performed
     * default is _id key
     *
     * @var string
     */
    protected $sortBy = '_id';


    /**
     * Flag indicates this class is used
     * as RESTful API
     * Alternatively it can be used as
     * worker for SOAP server, in which case
     * we should skip certain things like
     * formatting output
     * and should also NOT handle any exceptions and let
     * the exceptions be caught by the class or
     * by the php file that handles SOAP requests
     * It will then convert Exception's messages to SOAP Fault exception
     * This is the correct way to do things in SOAP
     *
     *
     * @var bool
     */
    protected $restful = true;


    public function __construct(Registry $Registry, Request $Request = null, $restful = true)
    {
        parent::__construct($Registry);
        $this->Request = (null !== $Request) ? $Request : $Registry->Request;
        $this->restful = $restful;
        $format = $this->Request->get('alt', 's', 'json');
        d('requested output format: ' . $format);
        $callback = $this->Request->get('callback', 's', null);
        $this->Output = Output\Formatter::factory($format, $callback);
        $this->Registry->Response = Response::factory();
        $this->aConfig = $Registry->Ini->getSection('API');

        $this->pageID = $this->Request['pageID'];
        try {
            $this->initParams()
                ->initClientUser()
                ->setClientAppId()
                ->makeAccessId()
                ->checkLimit()
                ->checkLoginStatus()
                ->checkAccessPermission()
                ->main();
            $this->logRequest();
        } catch (\Exception $e) {
            $this->handleException($e);
        }

        $this->prepareResponse();
    }


    /**
     * Request may contain apikey value
     * but it's optional
     * Also in case of OAuth2
     * the oauth2 secret token will resolve to
     * specific APP ID, in which case that value (from OAuth2 token)
     * will override this value
     *
     * @throws \Lampcms\HttpResponseCodeException
     * @return object this
     */
    protected function setClientAppId()
    {
        /**
         * In case user has already authenticated
         * via OAuth2, we already know the clientAppId,
         * then skip this step
         */
        if (empty($this->accessId)) {

            /**
             * @todo This is WRONG
             * this is apikey, NOT the same as app_id which we
             * will get from the database based on apikey!
             *
             */
            $this->Registry->clientAppId = $this->Request->get('apikey', 's', null);
            /**
             * Check here if the API idendified by this API key
             * isValid or has been suspended of deleted
             */
            if (!empty($this->Registry->clientAppId)) {

                $a = $this->Registry->Mongo->API_CLIENTS->findOne(array('api_key' => $this->Registry->clientAppId), array('_id' => 1, 'i_suspended' => 1, 'i_deleted' => 1));
                if (empty($a)) {
                    throw new \Lampcms\HttpResponseCodeException('Invalid api key: ' . $this->Registry->clientAppId, 401);
                }

                if (!empty($a['i_suspended'])) {
                    throw new \Lampcms\HttpResponseCodeException('Suspended api key: ' . $this->Registry->clientAppId, 401);
                }

                if (!empty($a['i_deleted'])) {
                    throw new \Lampcms\HttpResponseCodeException('This app was deleted on ' . date('r', $a['i_deleted']), 401);
                }
            }
        }

        return $this;
    }


    /**
     * Create User object representing
     * not-logged-in ApiUser
     * Later when OAuth based login is added the User object
     * will be created based on OAuth token
     *
     * @return object $this
     */
    protected function initClientUser()
    {

        /**
         * @todo when OAuth2 is supported then route to
         * initOAuth2User if OAuth2 token is present in request
         *
         * @todo check if Basic Auth is enabled in Settings API section
         * admin may disable basic auth in case OAuth2 is available
         * If Basic auth is disabled then throw appropriate exception
         *
         */
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $this->initBasicAuthUser($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        } else {
            d('No user credentials in request. Using basic quest user');
            $this->Registry->Viewer = ApiUser::userFactory($this->Registry);
        }

        d('Viewer id: ' . $this->Registry->Viewer->getUid());

        return $this;
    }


    /**
     * Use Credentials from Basic Auth headers
     * to instantiate our BasicAuthUser
     *
     * @throws \Lampcms\HttpResponseCodeException
     */
    protected function initBasicAuthUser($username, $pwd)
    {
        try {
            $UserAuth = new UserAuth($this->Registry);
            $User = $UserAuth->validateLogin($username, $pwd, '\Lampcms\\Api\\UserBasicAuth');

            /**
             * If user logged in that means he got the email
             * with password,
             * thus we confirmed email address
             * and can activate user
             */
            $User->activate();
            $this->Registry->Viewer = $User;
            /**
             * Set $this->viewerId
             * it will result in increasing
             * access rate limit
             *
             *
             */
            $this->viewerId = $User->getUid();

        } catch (\Lampcms\LoginException $e) {
            e('Login error: ' . $e->getMessage() . ' in file: ' . $e->getFile() . ' on line: ' . $e->getLine());
            /**
             * Re-throw exception here with
             * proper HTTP Code (as HttpResponseCodeException)
             */
            throw new \Lampcms\HttpResponseCodeException('Wrong login credentials: ' . $e->getMessage(), 401);
        }
    }


    /**
     * Make accessId - a unique
     * identifier for the current request
     * based on date + appID (or ip address) and
     * Viewer ID
     *
     * @return object $this
     */
    protected function makeAccessId()
    {
        if (!isset($this->accessId)) {
            d('making accessId. $this->Registry->clientAppId is: ' . $this->Registry->clientAppId);
            $clientId = (empty($this->Registry->clientAppId)) ? Request::getIP() : $this->Registry->clientAppId;
            d('clientId: ' . $clientId);
            $this->accessId = date('Ymd') . '_' . $clientId . '_' . $this->Registry->Viewer->getUid();
        }

        return $this;
    }


    /**
     * Check the limit per client
     * or per IP address
     *
     * Limit is based on type of authentication:
     * Anonymous user gets lowest limit
     * Registered APP gets higher limit
     * Authenticated user (via OAuth2) gets hits limit
     *
     * @throws \Lampcms\HttpResponseCodeException with http code 400
     * if daily rate access limit has been exceeded
     *
     * @return object $this
     */
    protected function checkLimit()
    {
        /**
         * Skip this if current
         * controller is not subject
         * to rate limit
         */
        if (!$this->bRateLimited) {
            d('excluded from rateLimited');

            return $this;
        }

        $this->Registry->Mongo->API_ACCESS_COUNTER->ensureIndex(array('id' => 1), array('unique' => true));

        $a = $this->Registry->Mongo->API_ACCESS_COUNTER->findOne(array('id' => $this->accessId));
        d('a: ' . print_r($a, 1));

        switch (true) {
            case ($this->viewerId > 0):
                $this->rateLimit = $this->aConfig['DAILY_LIMIT_USER'];
                break;

            case (isset($this->Registry->clientAppId)):
                $this->rateLimit = $this->aConfig['DAILY_LIMIT_APP'];
                break;

            default:
                $this->rateLimit = $this->aConfig['DAILY_LIMIT_ANON'];
        }

        d('$this->rateLimit: ' . $this->rateLimit);

        if (empty($a)) {
            return $this;
        }

        $this->accessCounter = $a['i_count'];

        if ($this->accessCounter > $this->rateLimit) {
            throw new \Lampcms\HttpResponseCodeException('Exceeded your daily API access limit of ' . $this->rateLimit, 400);
        }

        return $this;
    }


    /**
     * Update daily request counter
     * in order to enforce access limit
     *
     * @return object $this
     */
    protected function logRequest()
    {

        if ($this->bRateLimited) {
            d('updating daily counter for $this->accessId: ' . $this->accessId);

            $this->Registry->Mongo->API_ACCESS_COUNTER->update(array('id' => $this->accessId), array('$inc' => array("i_count" => 1)), array("upsert" => true));
        }

        /**
         * Posting onApiAccess event
         * enables to later write Observer class
         * to log API calls.
         */
        $this->Registry->Dispatcher->post($this->Request, 'onApiAccess');

        return $this;
    }


    /**
     * The job of main() in concrete sub-classes
     * is to populate
     * the $this->Output object
     * Now we can use $this->Output to populate
     * $this->Registry->Response with value for the body
     * and headers of response
     * The Response object will then
     * be ready for sending out output
     * to client
     *
     * @return object $this
     */
    protected function prepareResponse()
    {
        d('this->rateLimit: ' . $this->rateLimit . ' $this->accessCounter: ' . $this->accessCounter);

        $this->Registry->Response->addHeader('X-RateLimit-Limit', $this->rateLimit);
        $this->Registry->Response->addHeader('X-RateLimit-Remaining', ($this->rateLimit - $this->accessCounter));
        /**
         * Currently the reset of rate limit is
         * on the start of next day. In the future we may
         * implement hourly rate limit like Twitter, but
         * probably not going to do this any time soon...
         * So for now the reset time is the unix timestamp
         * of start of day tomorrow
         *
         */
        $resetTimestamp = mktime(0, 0, 0, date('n'), date('j') + 1);
        $this->Registry->Response->addHeader('X-RateLimit-Reset', $resetTimestamp);

        if ($this->restful) {
            d('setting output object of type: ' . get_class($this->Output));

            $this->Registry->Response->setOutput($this->Output);
        }

        return $this;
    }


    /**
     * Exceptions are returned to client in the form
     * of a message in the 'error' element
     * Appropriate http response code is used
     *
     * @param \Exception $e
     *
     * @throws \Exception
     * @return void
     */
    protected function handleException(\Exception $e)
    {

        if (!$this->restful) {
            throw $e;
        }

        if ($e instanceof \Lampcms\HttpResponseCodeException) {
            $code = $e->getHttpCode();
            $this->Registry->Response->setHttpCode($code);
            /**
             * @todo if $code = 405 and bRequirePost then
             * set extra header Allow: POST
             * This is to comply with RFC
             */
        } else {
            $this->Registry->Response->setHttpCode(500);
        }

        $err = \strip_tags($e->getMessage());
        $err2 = ('API Exception caught in: ' . $e->getFile() . ' on line: ' . $e->getLine() . ' error: ' . $err);

        d($err2);

        $this->Output->setData(array('error' => $err));
        $this->Registry->Response->setBody($this->Output);

    }


    /**
     * Check Request object for required params
     * as well as for required form token
     *
     * @throws \Lampcms\HttpResponseCodeException
     * @return object $this
     */
    protected function initParams()
    {

        if ($this->bRequirePost && ('POST' !== Request::getRequestMethod())) {
            throw new \Lampcms\HttpResponseCodeException('HTTP POST request method required', 405);
        }

        $this->Request->setRequired($this->aRequired);

        try {
            $this->Request->checkRequired();
        } catch (\Exception $e) {
            throw new \Lampcms\HttpResponseCodeException($e->getMessage(), 400);
        }

        return $this;
    }


    /**
     *
     * Sets $this->startTime which is the
     * unix timestamp
     * The search will be performed to return
     * items created after and not including
     * this unix timestamp
     *
     * @return object $this
     */
    protected function setStartTime()
    {
        $id = $this->Request->get('starttime', 'i', null);
        if (!empty($id)) {
            $this->startTime = abs($id);
        }

        return $this;
    }


    /**
     * Sets the unix timestamp value
     * of the $this->endTime
     * Items will be returned that were
     * created before this unix timestamp
     *
     * @return object $this
     */
    protected function setEndTime()
    {
        $id = $this->Request->get('endtime', 'i', null);
        if (!empty($id)) {
            $this->endTime = abs($id);
        }

        return $this;
    }


    /**
     * Set limit of results to return based
     * on value of "limit" param
     *
     *
     * @throws \Lampcms\HttpResponseCodeException if limit is
     * greater than value of MAX_RESULTS in !config.ini [API] section
     *
     * @return object $this
     */
    protected function setLimit()
    {
        $limit = $this->Request->get('limit', 'i', 20);
        d('limit: ' . $limit);

        if (!empty($limit)) {
            if ($limit > $this->aConfig['MAX_RESULTS']) {
                throw new \Lampcms\HttpResponseCodeException('Value of "limit" param is too large. Must be under 100. Was: ' . $limit, 406);
            }

            $this->limit = $limit;
        }

        return $this;
    }


    /**
     *
     * Set the value of $this->sortBy
     * can be one of
     * _id (default)
     * i_lm_ts (last modified timestamp)
     * i_ans (count of answers)
     * i_votes (number of votes)
     *
     * @throws \Lampcms\HttpResponseCodeException if value of sort
     * is not one of allowed values
     *
     * @return object $this
     */
    protected function setSortBy()
    {
        $sortBy = $this->Request->get('sort', 's', null);
        if (empty($sortBy)) $sortBy = $this->sortBy;
        if (!\in_array($sortBy, $this->allowedSortBy)) {
            throw new \Lampcms\HttpResponseCodeException('Invalid value of "sort" param in request. Allowed values are: ' . implode(', ', $this->allowedSortBy) . ' Value was" ' . $sortBy, 406);
        }

        $this->sortBy = $sortBy;

        return $this;
    }


    /**
     * Set sort order based on value
     * of "dir" param: asc means sort in ascending order
     * desc means sort in descending order
     *
     * @throws \Lampcms\HttpResponseCodeException if value
     * of "dir" is not asc or desc
     *
     * @return object $this
     */
    protected function setSortOrder()
    {
        $allowed = array('asc', 'desc');
        $order = $this->Request->get('dir', 's', 'desc');
        if (!\in_array($order, $allowed)) {
            throw new \Lampcms\HttpResponseCodeException('Invalid value of "dir" param in request. Allowed values are: ' . implode(', ', $allowed) . ' Value was" ' . $order, 406);
        }

        $this->sortOrder = ('desc' === $order) ? -1 : 1;

        return $this;
    }


    /**
     * Sub-class must implement this method
     * The job of this method is to
     * Generate either MongoCursor with results
     * or some other type of data (if the form
     * of php array) and set the $this->Output->setData($data)
     * End result of main() is that $this->Output is populated
     * with some data.
     *
     */
    abstract protected function main();


    /**
     * Getter for Response object
     *
     * @return object of type \Lampcms\Response
     *
     */
    public function getResponse()
    {
        return $this->Registry->Response;
    }

}
