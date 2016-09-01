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
 * Class for generating html of the registration block
 * with registration form
 * This html block is usually shown inside the modal window
 *
 *
 * @author Dmitri Snytkine
 *
 * @todo Translate Strings
 *
 */
class RegBlock extends LampcmsObject
{
    protected $aUsername = array(
        'usernameLabel' => '@@Username@@',
        'usernameVal' => '',
        'usernameNote' => '@@Username will appear alongside your posts@@');

    protected $tplOptin = '<tr>
	<td>
	<div class="tr">
	<input type="checkbox" id="optin" name="optin" %3$s/>&nbsp;%1$s
	<div class="note2">%2$s</div>
	</div>
	</td>
	</tr>';

    protected $aOptin = array(
        'listName' => 'API News ',
        'listNote' => 'Receive our email newsletter about interesting developments in Social Media, Open source projects and APIs',
        'isChecked' => ''
    );


    protected $Registry;

    /**
     * Object of type User
     * or any subclass
     * representing currently logged in user
     *
     * @var object
     */
    protected $oViewer;

    /**
     * Array of replacement vars that will
     * be set in template tplRegform.php
     *
     * @var array
     */
    protected $aVars = array();

    public function __construct(Registry $Registry)
    {
        $this->Registry = $Registry;
        $this->oViewer = $Registry->Viewer;
    }

    /**
     * Makes an object which is sub-class of this
     * class, depending on the type of oViewer
     * we have: if user just logged in with Twitter
     * then we create object RegBlockTwitter
     * which has some specific html for Twitter user,
     * if it's a type of Google FriendConnect, then of type
     * RegBlockGfc, etc.
     *
     * @param Registry Registry
     *
     * @return \Lampcms\LampcmsObject|object of this class or subclass
     */
    public static function factory(Registry $Registry)
    {
        $oViewer = $Registry->Viewer;
        switch (true) {
            case ($oViewer instanceof UserTwitter):
                $o = new RegBlockTwitter($Registry);
                break;

            case ($oViewer instanceof UserLinkedin):
                $o = new RegBlockLinkedin($Registry);
                break;

            default:
                $o = new RegBlockTwitter($Registry);

        }

        return $o;
    }

    /**
     * Create html block for registration form
     *
     * @return string HTML of registration block
     */
    public function getBlock()
    {
        $this->prepareVars()
            ->setUsernameVars()
            ->addUsernameBlock();
        d('cp $this->aVars ' . \json_encode($this->aVars));
        $ret = \tplRegform::parse($this->aVars);
        d('$ret: ' . $ret);

        return $ret;

    }


    /**
     * Makes the block for the 'Username' Form
     * field and sets the new block
     *
     * @return object $this;
     */
    protected function addUsernameBlock()
    {
        /*d('cp $this->aUsername: '.print_r($this->aUsername, 1));
           $this->aVars['usernameBlock'] = \tplUsernameblock::parse($this->aUsername, false);//vsprintf($this->tplUsername, $this->aUsername);
           d('$this->aVars[usernameBlock]: '.print_r($this->aVars['usernameBlock'], 1) );
           */

        return $this;
    }


    /**
     * @todo change to NOT use usernameBLock
     * and instead just directly set vars in tplRegform
     * values of 'username'
     *
     */
    protected function setUsernameVars()
    {
        d('cp');
        $this->aUsername = array('@@Username@@', $this->oViewer->username, '@@Username will appear alongside your posts@@');
        d('$this->aUsername: ' . print_r($this->aUsername, 1));

        $this->aVars['username'] = $this->oViewer['username'];

        return $this;
    }


    /**
     * Set values of replacement vars for the template
     * depending on the type of oViewer object
     * If it's isNewUser and already has external avatar
     * then also add block and show the name of external
     * auth provider like "Twitter"
     *
     * If this is a brand new registration then we also
     * need to add Captcha image and hidden field
     * and extra input text field
     */
    protected function prepareVars()
    {

        return $this;
    }
}