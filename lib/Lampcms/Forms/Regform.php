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


namespace Lampcms\Forms;

use \Lampcms\Validate;
use \Lampcms\Captcha\Captcha;
use \Lampcms\Request;

class Regform extends Form
{
    const CAPTCHA_ERROR = 'Incorrect image verification text<br/>Please try again';

    /**
     * Name of form template file
     * The name of actual template should be
     * set in sub-class
     *
     * @var string
     */
    protected $template = 'tplRegform';

    protected function doValidate()
    {
        $this->validateCaptcha()
            ->validateEmail()
            ->validateUsername();
    }

    protected function init()
    {
        $Tr = $this->Registry->Tr;
        $this->aVars['username_d'] = $Tr['Username will appear alongside your posts'];
        $this->aVars['username_l'] = $Tr['Username'];
    }


    /**
     * Make sure email address is valid
     * Make sure email is not already used by some
     * user
     *
     * @return object $this
     */
    protected function validateEmail()
    {
        $email = strtolower($this->getSubmittedValue('email'));

        if (false === Validate::email($email)) {
            $this->setError('email', 'This is not a valid email address');
        }

        $a = $this->Registry->Mongo->EMAILS->findOne(array('email' => $email));

        if (!empty($a)) {
            $this->setError('email', 'There is already an account with this email address. Have you already registered on our site before?');
        }

        return $this;
    }

    /**
     * Make sure username is valid
     * Make sure it's not already in use
     *
     * @return object $this
     */
    protected function validateUsername()
    {
        $username = strtolower($this->getSubmittedValue('username'));

        if (false === Validate::username($username)) {
            $this->setError('username', 'This username is invalid. Username must contain only letters, numbers and a hyphen and MUST start and end with letter or number and MUST be at least 3 characters long');
        }

        $aReserved = \Lampcms\getReservedNames();
        if (in_array($username, $aReserved)) {
            $this->setError('username', 'This username is already in use');
        }


        $a = $this->Registry->Mongo->USERS->findOne(array('username_lc' => $username));

        if (!empty($a)) {
            $this->setError('username', 'This username is already in use');
        }

        return $this;
    }


    /**
     *
     */
    protected function validateCaptcha()
    {
        if (!empty($_SESSION['reg_captcha'])) {
            return $this;
        }

        $oCaptcha = Captcha::factory($this->Registry->Ini);
        $res = $oCaptcha->validate_submit();
        /**
         * If validation good then
         * all is OK
         */
        if (1 === $res) {
            $_SESSION['reg_captcha'] = true;

            return $this;
        }

        /**
         * If 3 then reached the limit of attampts
         */
        if (3 === $res) {
            throw new \Lampcms\CaptchaLimitException('You have reached the limit of image verification attempts');
        }

        if (Request::isAjax()) {
            $aRet = array(
                'exception' => self::CAPTCHA_ERROR,
                'fields' => array('private_key'),
                'captcha' => $oCaptcha->getCaptchaArray()
            );

            \Lampcms\Responder::sendJSON($aRet);
        }

        /**
         * @todo translate string
         */
        $this->setFormError(self::CAPTCHA_ERROR);

        return $this;

    }
}
