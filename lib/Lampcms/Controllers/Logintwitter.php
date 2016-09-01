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

namespace Lampcms\Controllers;

use \Lampcms\WebPage;
use \Lampcms\Request;
use \Lampcms\Cookie;
use \Lampcms\Responder;
use \Lampcms\Mongo\Doc as MongoDoc;
use \Lampcms\Twitter;

/**
 * Class for generating a popup page that starts the oauth dance
 * and this also serves as a callback url
 * to which twitter redirects after authorization
 *
 * Dependency is pecl OAuth extension!
 *
 * @author Dmitri Snytkine
 *
 */
class Logintwitter extends WebPage
{

    const REQUEST_TOKEN_URL = 'https://api.twitter.com/oauth/request_token';

    const ACCESS_TOKEN_URL = 'https://api.twitter.com/oauth/access_token';

    const AUTHORIZE_URL = 'https://api.twitter.com/oauth/authorize';

    const VERIFY_CREDENTIALS_URL = 'https://api.twitter.com/1.1/account/verify_credentials.json';


    /**
     * Array of data returned from Twitter
     * This is the main user's profile and stuff
     *
     * @var array
     */
    protected $aUserData;

    /**
     * Object php OAuth
     *
     * @var object of type php OAuth
     * must have oauth extension for this
     */
    protected $oAuth;

    protected $bInitPageDoc = false;

    /**
     * Configuration of Twitter API
     * this is array of values TWITTER section
     * in !config.ini
     *
     * @var array
     */
    protected $aTW = array();


    /**
     * Flag means new account will
     * be created for this
     * 'signed in with twitter' user
     *
     * @var bool
     */
    protected $isNewAccount = false;


    /**
     * Object of type UserTwitter
     *
     * We cannot just update the Viewer object because
     * we need to create an object of type UserTwitter
     * we will then replace the Viewer object with this new object
     * via processLogin()
     *
     * @var object of type UserTwitter
     */
    protected $User;


    /**
     * Flag indicates that this is the
     * request to connect Twitter account
     * with existing user account.
     *
     * @var bool
     */
    protected $bConnect = false;


    /**
     * The main purpose of this class is to
     * generate the oAuth token
     * and then redirect browser to twitter url with
     * this unique token
     *
     * No actual page generation will take place
     *
     * @see classes/WebPage#main()
     */
    protected function main()
    {
        if (!extension_loaded('oauth')) {
            throw new \Lampcms\Exception('@@Unable to use Twitter API because OAuth extension is not available@@');
        }

        /**
         * If user is logged in then this is
         * a request to connect Twitter Account
         * with existing account.
         *
         * @todo check that user does not already have
         *       Twitter credentials and if yes then call
         *       closeWindows as it would indicate that user
         *       is already connected with Twitter
         */
        if ($this->isLoggedIn()) {
            $this->bConnect = true;
        }

        d('$this->bConnect: ' . $this->bConnect);

        $this->aTW = $this->Registry->Ini['TWITTER'];

        try {
            $this->oAuth = new \OAuth($this->aTW['TWITTER_OAUTH_KEY'], $this->aTW['TWITTER_OAUTH_SECRET']); // , OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION
            $this->oAuth->disableSSLChecks();
            $this->oAuth->enableDebug(); // This will generate debug output in your error_log
        } catch ( \OAuthException $e ) {
            e('OAuthException: ' . $e->getMessage());

            throw new \Lampcms\Exception('@@Something went wrong during authorization. Please try again later@@' . $e->getMessage());
        }

        /**
         * If this is start of dance then
         * generate token, secret and store them
         * in session and redirect to twitter authorization page
         */
        if (empty($_SESSION['oauth']) || empty($this->Request['oauth_token'])) {
            $this->startOauthDance();
        } else {
            $this->finishOauthDance();
        }
    }


    /**
     * Generate oAuth request token
     * and redirect to twitter for authentication
     *
     * @throws Exception
     * @throws \Exception in case something goes wrong during
     * this stage
     * @return object $this
     */
    protected function startOauthDance()
    {

        try {
            $uri            = $this->Registry->Ini->SITE_URL . '{_WEB_ROOT_}/{_logintwitter_}';
            $routerCallback = $this->Registry->Router->getCallback();
            $callbackUrl    = $routerCallback($uri);
            /**
             * urlencode() is not longer necessary since now callback url is passed in header
             * but if you are having problems with this method try to uncomment urlencode() line below
             * This behaviour may depend on version of php oauth extension
             */
            //$callbackUrl = \urlencode($callbackUrl);
            d('$callbackUrl' . $callbackUrl);

            // State 0 - Generate request token and redirect user to Twitter to authorize
            $_SESSION['oauth'] = $this->oAuth->getRequestToken(self::REQUEST_TOKEN_URL, $callbackUrl);
            $aDebug            = $this->oAuth->getLastResponseInfo();
            d('debug: ' . \print_r($aDebug, 1));

            d('$_SESSION[\'oauth\']: ' . print_r($_SESSION['oauth'], 1));
            if (!empty($_SESSION['oauth']) && !empty($_SESSION['oauth']['oauth_token'])) {
                $authorizeUrl = self::AUTHORIZE_URL . '?oauth_token=' . $_SESSION['oauth']['oauth_token'];
                d('redirecting to url: ' . $authorizeUrl);
                Responder::redirectToPage($authorizeUrl);
            } else {
                /**
                 * Here throw regular Exception, not Lampcms\Exception
                 * so that it will be caught ONLY by the index.php and formatted
                 * on a clean page, without any template
                 */

                throw new \Exception("@@Failed fetching request token, response was@@: " . $this->oAuth->getLastResponse());
            }
        } catch ( \OAuthException $e ) {
            e('OAuthException: ' . $e->getMessage());
            $aDebug = $this->oAuth->getLastResponseInfo();
            d('debug: ' . print_r($aDebug, 1));

            throw new \Exception('@@Something went wrong during authorization. Please try again later@@' . $e->getMessage());
        }

        return $this;
    }


    /**
     * Step 2 in oAuth process
     * this is when Twitter redirected the user back
     * to our callback url, which calls this controller
     *
     * @return object $this
     *
     * @throws Exception in case something goes wrong with oAuth class
     */
    protected function finishOauthDance()
    {

        d('Looks like we are at step 2 of authentication. Request: ' . print_r($_REQUEST, 1));
        try {
            /**
             * This is a callback (redirected back from twitter page
             * after user authorized us)
             * In this case we must: create account or update account
             * in USER table
             * Re-create oViewer object
             * send cookie to remember user
             * and then send out HTML with js instruction to close the popup window
             */
            /**
             * Get 'oauth_verifier' request param which was sent from LinkedIn
             */
            $ver = $this->Registry->Request->get('oauth_verifier', 's', '');
            d('$ver: ' . $ver);
            if (empty($ver)) {
                $ver = null;
            }

            // State 1 - Handle callback from Twitter and get and store an access token
            /**
             * @todo check first to make sure we do have oauth_token
             *       on REQUEST, else close the window
             */
            $this->oAuth->setToken($this->Request['oauth_token'], $_SESSION['oauth']['oauth_token_secret']);
            $aAccessToken = $this->oAuth->getAccessToken(self::ACCESS_TOKEN_URL, null, $ver);
            d('$aAccessToken: ' . \json_encode($aAccessToken));

            unset($_SESSION['oauth']);

            /**
             * @todo
             * there is a slight possibility that
             * we don't get the oData back like if
             * request for verify_credentials with token/secret fails
             * This should not happen because user has just authorized us - this
             * is a callback url after all.
             * But still, what if... what if Twitter hickups and does not
             * return valid response, then what should be do?
             *
             * Probably throw some generic exception telling user to try
             * again in a few minutes
             *
             * So basically we should delegate this whole process to
             * the Twitter->verifyCredentials()
             *
             */
            $this->oAuth->setToken($aAccessToken['oauth_token'], $aAccessToken['oauth_token_secret']);
            $this->oAuth->fetch(self::VERIFY_CREDENTIALS_URL, null, OAUTH_HTTP_METHOD_GET, array('Connection'=> 'close'));
            $aDebug = $this->oAuth->getLastResponseInfo();
            d('debug: ' . \print_r($aDebug, 1));
            $lastResponseHeaders = $this->oAuth->getLastResponseHeaders();
            d('$lastResponseHeaders: ' . $lastResponseHeaders);

            if (false === $this->aUserData = \json_decode($this->oAuth->getLastResponse(), true)) {
                e('Unable to json_decode data returned by Twitter API: ' . $this->oAuth->getLastResponse());
                $this->closeWindow();
                exit;
            }

            if (isset($this->aUserData['status'])) {
                unset($this->aUserData['status']);
            }

            d('json: ' . \print_r($this->aUserData, true));


            $this->aUserData = \array_merge($this->aUserData, $aAccessToken);
            d('$this->aUserData ' . \print_r($this->aUserData, 1));

            $this->aUserData['_id'] = (!empty($this->aUserData['id_str'])) ? $this->aUserData['id_str'] : (string)$this->aUserData['id'];
            unset($this->aUserData['user_id']);


            $this->updateTwitterUserRecord();

            $this->createOrUpdate();
            if (!$this->bConnect) {
                Cookie::sendLoginCookie($this->Registry->Viewer->getUid(), $this->User->rs);
            } else {
                /**
                 * Set flag to session indicating that user just
                 * connected Twitter Account
                 */
                $this->Registry->Viewer['b_tw'] = true;
            }

            $this->closeWindow();

        } catch ( \OAuthException $e ) {
            e('OAuthException: ' . $e->getMessage());
            $aDebug = $this->oAuth->getLastResponseInfo();
            d('debug: ' . print_r($aDebug, 1));
            $lastResponseHeaders = $this->oAuth->getLastResponseHeaders();
            d('$lastResponseHeaders: ' . $lastResponseHeaders);

            /**
             * Cannot throw exception because then it would be
             * displayed as regular page, with login block
             * but the currently opened window is a popup window
             * for showing twitter oauth page and we don't need
             * a login form or any other elements of regular page there
             */
            $err = '@@Something went wrong during authorization. Please try again later@@' . $e->getMessage();
            exit(\Lampcms\Responder::makeErrorPage($err));
        }

        return $this;
    }


    /**
     * Test to see if user with the twitter ID already exists
     * by requesting tid_ key from cache
     * this is faster than even a simple SELECT because
     * the user object may already exist in cache
     *
     * If user not found, then create a record for
     * a new user, otherwise update record
     *
     * @todo special case if this is 'connect' type of action
     *       where existing logged in user is adding twitter to his account
     *       then we should delegate to connect() method which
     *       does different things - adds twitter data to $this->Registry->Viewer
     *       but also first checks if another user already has
     *       this twitter account in which case must show error - cannot
     *       use same Twitter account by different users
     *
     * @throws \Exception
     * @return object $this
     */
    protected function createOrUpdate()
    {

        $tid = $this->aUserData['_id']; // it will be string!
        d('$tid: ' . $tid);
        $aUser = $this->getUserByTid($tid);

        if (!empty($this->bConnect)) {
            d('this is connect action');

            $this->User = $this->Registry->Viewer;
            $this->connect($tid);

        } elseif (!empty($aUser)) {
            $this->User = $User = \Lampcms\UserTwitter::userFactory($this->Registry, $aUser);
            $this->updateUser();
        } else {
            $this->isNewAccount = true;
            $this->createNewUser();
        }


        try {
            $this->processLogin($this->User);
        } catch ( \Lampcms\LoginException $e ) {
            /**
             * re-throw as regular exception
             * so that it can be caught and shown in popup window
             */
            e('Unable to process login: ' . $e->getMessage());
            throw new \Exception($e->getMessage());
        }

        $this->Registry->Dispatcher->post($this, 'onTwitterLogin');

        if ($this->isNewAccount) {
            $this->postTweetStatus();
        }

        return $this;
    }


    /**
     * Add Twitter credentials to existing user
     *
     * @param $tid
     *
     * @return $this
     */
    protected function connect($tid)
    {
        $aUser = $this->getUserByTid($tid);
        d('$aUser: ' . print_r($aUser, 1));
        if (!empty($aUser) && ($aUser['_id'] != $this->User->getUid())) {

            $name = '';
            if (!empty($aUser['fn'])) {
                $name .= $aUser['fn'];
            }

            if (!empty($aUser['ln'])) {
                $name .= ' ' . $aUser['fn'];
            }
            $trimmed = \trim($name);
            $name    = (!empty($trimmed)) ? \trim($name) : $aUser['username'];

            /**
             * This error message will appear inside the
             * Small extra browser Window that Login with Twitter
             * opens
             *
             */
            $err = '<div class="larger"><p>This Twitter account is already connected to
			another registered user: <strong>' . $name . '</strong><br>
			<br>
			A Twitter account cannot be associated with more than one account on this site<br>
			If you still want to connect Twitter account to this account you must use a different Twitter account</p>';
            $err .= '<br><br>
			<input type="button" class="btn-m" onClick="window.close();" value="&nbsp;OK&nbsp;">&nbsp;
			<input type="button"  class="btn-m" onClick="window.close();" value="&nbsp;Close&nbsp;">
			</div>';

            $s = Responder::makeErrorPage($err);
            echo ($s);
            exit;
        }

        $this->updateUser(false);
    }


    protected function createNewUser()
    {
        d('cp');
        $aUser = array();
        if (!empty($this->aUserData['utc_offset'])) {
            $timezone = \Lampcms\TimeZone::getTZbyoffset($this->aUserData['utc_offset']);
        } elseif (false !== $tzn = Cookie::get('tzn')) {
            $timezone = $tzn;
        } else {
            $timezone = $this->Registry->Ini->SERVER_TIMEZONE;
        }

        $username = $this->makeUsername();
        $sid      = Cookie::getSidCookie();
        d('sid is: ' . $sid);

        $aUser['username']        = $username;
        $aUser['username_lc']     = \mb_strtolower($username, 'utf-8');
        $aUser['fn']              = $this->aUserData['name'];
        $aUser['avatar_external'] = $this->aUserData['profile_image_url'];

        $aUser['lang']               = $this->aUserData['lang'];
        $aUser['i_reg_ts']           = time();
        $aUser['date_reg']           = date('r');
        $aUser['role']               = 'external_auth';
        $aUser['tz']                 = $timezone;
        $aUser['rs']                 = (false !== $sid) ? $sid : \Lampcms\String::makeSid();
        $aUser['twtr_username']      = $this->aUserData['screen_name'];
        $aUser['oauth_token']        = $this->aUserData['oauth_token'];
        $aUser['oauth_token_secret'] = $this->aUserData['oauth_token_secret'];
        $aUser['twitter_uid']        = $this->aUserData['_id'];
        $aUser['i_rep']              = 1;

        $aUser = \array_merge($this->Registry->Geo->Location->data, $aUser);

        if (!empty($this->aUserData['url'])) {
            $aUser['url'] = $this->aUserData['url'];
        }

        if (!empty($this->aUserData['description'])) {
            $aUser['description'] = $this->aUserData['description'];
        }

        d('aUser: ' . print_r($aUser, 1));

        $this->User = \Lampcms\UserTwitter::userFactory($this->Registry, $aUser);

        /**
         * This will mark this userobject is new user
         * and will be persistent for the duration of this session ONLY
         * This way we can know it's a newly registered user
         * and ask the user to provide email address but only
         * during the same session
         */
        //$this->User->setNewUser();
        //d('isNewUser: '.$this->User->isNewUser());
        $this->User->save();

        \Lampcms\PostRegistration::createReferrerRecord($this->Registry, $this->User);

        $this->Registry->Dispatcher->post($this->User, 'onNewUser');
        $this->Registry->Dispatcher->post($this->User, 'onNewTwitterUser');

        return $this;
    }


    /**
     * The currect Viewer object may be updated with the data
     * we got from Twitter api
     *
     * This means we found record for existing user by twitter uid
     *
     */
    protected function updateUser($bUpdateAvatar = true)
    {
        d('adding Twitter credentials to User object');
        $this->User['oauth_token']        = $this->aUserData['oauth_token'];
        $this->User['oauth_token_secret'] = $this->aUserData['oauth_token_secret'];
        $this->User['twitter_uid']        = $this->aUserData['_id'];
        if (!empty($this->aUserData['screen_name'])) {
            $this->User['twtr_username'] = $this->aUserData['screen_name'];
        }

        $avatarTwitter = $this->User['avatar_external'];
        if (empty($avatarTwitter)) {
            $this->User['avatar_external'] = $this->aUserData['profile_image_url'];

            $srcAvatar = \trim($this->User->offsetGet('avatar'));
            /**
             * If user also did not have any avatar
             * then
             * after this update we should also update
             * the welcome block (removing it from SESSION will
             * ensure that it updates on next page load) so that
             * avatar on the welcome block will change to the
             * external avatar
             */
            if (empty($srcAvatar)) {
                if (!empty($_SESSION) && !empty($_SESSION['welcome'])) {
                    unset($_SESSION['welcome']);
                }
            }
        }

        $this->User->save();

        return $this;
    }


    /**
     * Post tweet like
     * "Joined this site"
     * Also can and probably should add
     * the person to follow
     * our site's account
     */
    protected function postTweetStatus()
    {
        $sToFollow = $this->aTW['TWITTER_USERNAME'];
        d('$sToFollow: ' . $sToFollow);
        if (empty($sToFollow)) {
            return $this;
        }

        $follow             = (!empty($sToFollow)) ? ' #follow @' . $sToFollow : '';
        $siteName           = $this->Registry->Ini->SITE_TITLE;
        $ourTwitterUsername = $this->Registry->Ini->SITE_URL . $follow;

        $oTwitter = new Twitter($this->Registry);

        if (!empty($ourTwitterUsername)) {
            register_shutdown_function(function() use ($oTwitter, $siteName, $ourTwitterUsername, $sToFollow)
            {
                try {
                    $oTwitter->followUser($sToFollow);
                } catch ( \Exception $e ) {
                    $message = 'Error in: ' . $e->getFile() . ' line: ' . $e->getLine() . ' message: ' . $e->getMessage();
                    if (function_exists('d')) {
                        d($message);
                    }
                }

                /**
                 * Auto-posting tweet on user signup is a bad idea
                 * and may anger some users.
                 * Don't do this unless you really need this feature!
                 */
                /*try{
                     $oTwitter->postMessage('I Joined '.$siteName. ' '.$stuff);

                     } catch (\Exception $e){
                     $message = 'Exception in: '.$e->getFile(). ' line: '.$e->getLine().' message: '.$e->getMessage();
                        if (function_exists('d')) {
                            d($message);
                        }
                     }*/
            });
        }

        return $this;
    }


    /**
     * Create a new record in USERS_TWITTER table
     * or update an existing record
     *
     * @return \Lampcms\Controllers\Logintwitter
     */
    protected function updateTwitterUserRecord()
    {
        $this->Registry->Mongo->USERS_TWITTER->save($this->aUserData);

        return $this;
    }


    /**
     * Get user object ty twitter id (tid)
     *
     * @param string $tid Twitter id
     *
     * @return mixed array or null
     *
     */
    protected function getUserByTid($tid)
    {
        $coll = $this->Registry->Mongo->USERS;
        $coll->ensureIndex(array('twitter_uid' => 1));

        $aUser = $coll->findOne(array('twitter_uid' => $this->aUserData['_id']));

        return $aUser;
    }


    /**
     * Return html that contains JS window.close
     * code and nothing else
     *
     * @todo instead of just closing window
     *       can show a small form with pre-populated
     *       text to be posted to Twitter,
     *       for example "I just joined SITE_NAME, awesome site
     * + link +
     *
     * And there will be 2 buttons Submit and Cancel
     * Cancel will close window
     *
     * @param array $a
     *
     * @return void
     */
    protected function closeWindow(array $a = array())
    {
        d('cp a: ' . print_r($a, 1));
        $js = '';
        /*if(!empty($a)){
           $o = json_encode($a);
           $js = 'window.opener.oSL.processLogin('.$o.')';
           }*/

        $tpl = '
		var myclose = function(){
		window.close();
		}
		if(window.opener){
		%s
		setTimeout(myclose, 300); // give opener window time to process login and cancell intervals
		}else{
			alert("not a popup window or opener window gone away");
		}';
        d('cp');

        $script = \sprintf($tpl, $js);

        $s = Responder::PAGE_OPEN . Responder::JS_OPEN .
            $script .
            Responder::JS_CLOSE .
            '<h2>@@You have successfully logged in. You should close this window now@@</h2>' .
            //print_r($_SESSION, 1).
            Responder::PAGE_CLOSE;
        d('cp s: ' . $s);
        echo $s;
        fastcgi_finish_request();
        exit;
    }


    /**
     * Checks in username of twitter user
     * already exists in our regular USERS table
     * and if it does then prepends the @ to the username
     * otherwise returns twitter username
     *
     * The result is that we will use the value of
     * Twitter username as our username OR the
     *
     * @username
     * if username is already taken
     *
     * @return string the value of username that will
     * be used as our own username
     *
     */
    protected function makeUsername()
    {
        $res = $this->Registry->Mongo->USERS->findOne(array('username_lc' => \mb_strtolower($this->aUserData['screen_name'])));

        $ret = (empty($res)) ? $this->aUserData['screen_name'] : '@' . $this->aUserData['screen_name'];
        d('ret: ' . $ret);

        return $ret;
    }

}
