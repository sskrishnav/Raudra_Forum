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

namespace Lampcms\Controllers;

use \Lampcms\WebPage;
use \Lampcms\Request;
use \Lampcms\Responder;


/**
 * Controller for creating the page
 * with "Email options" form
 * as well as processing that form
 *
 * @author Dmitri Snytkine
 *
 */
class Emailoptions extends WebPage
{
    protected $membersOnly = true;

    protected $permission = 'edit_profile';

    protected $layoutID = 1;

    /**
     * @var object of type Form
     */
    protected $Form;


    /**
     *
     * @todo maybe send email on save() notifying
     * that email settings has been updated
     *
     * (non-PHPdoc)
     * @see Lampcms.WebPage::main()
     */
    protected function main()
    {

        $email = $this->Registry->Viewer->email;
        $this->Form = new \Lampcms\Forms\EmailOptions($this->Registry);
		//$this->Form->formTitle = $this->aPageVars['title'] = $this->_('Your Email Subscription Preferences');
		$this->Form->your_email = $email;

		if ($this->Form->isSubmitted()) {
            $this->Registry->Dispatcher->post($this->Form, 'onBeforeEmailOptionsUpdate');
            $this->savePrefs();
            $this->Registry->Dispatcher->post($this->Form, 'onEmailOptionsUpdate');
            $this->aPageVars['body'] = '<div id="tools"><h3>@@Your email subscription preferences have been updated@@.</h3><p><a href="{_WEB_ROOT_}/{_emailoptions_}/">@@Your email preferences@@</a></p></div>';

        } else {
            $this->setForm();
            $this->aPageVars['body'] = $this->Form->getForm();
        }
	}


    /**
     * Save Email preferences in
     * Viewer object and call save() to store
     * to Database right away.
     *
     * This will set values of e_fu, e_fq, e_ft in USERS to
     * either true or false, so the value will not be null
     * it may become false but it will exist - it will
     * not be considered null anymore
     *
     * @return object $this
     */
    protected function savePrefs()
    {

        $formVals = $this->Form->getSubmittedValues();
        d('formVals: ' . print_r($formVals, 1));
        $oViewer = $this->Registry->Viewer;

        $oViewer['ne_fu'] = (empty($formVals['e_fu']));
        $oViewer['ne_fq'] = (empty($formVals['e_fq']));
        $oViewer['ne_ft'] = (empty($formVals['e_ft']));
        $oViewer['ne_fa'] = (empty($formVals['e_fa']));
        $oViewer['ne_fc'] = (empty($formVals['e_fc']));
        $oViewer['ne_ok'] = (empty($formVals['e_ok']));

        $oViewer->save();

        return $this;
    }


    /**
     * Set the "checked" values of check boxes
     * to the ones in Viewer object
     *
     * Value is considered checked if it is
     * not specifically set to false by user
     * by default there is no value in USERS collection
     * for these settings, so it will be returned
     * as null (but not false) from Viewer object
     *
     */
    protected function setForm()
    {
        $this->Form->e_fu = (true !== $this->Registry->Viewer->ne_fu) ? 'checked' : '';
        $this->Form->e_ft = (true !== $this->Registry->Viewer->ne_ft) ? 'checked' : '';
        $this->Form->e_fq = (true !== $this->Registry->Viewer->ne_fq) ? 'checked' : '';
        $this->Form->e_fa = (true !== $this->Registry->Viewer->ne_fa) ? 'checked' : '';
        $this->Form->e_fc = (true !== $this->Registry->Viewer->ne_fc) ? 'checked' : '';
        $this->Form->e_ok = (true !== $this->Registry->Viewer->ne_ok) ? 'checked' : '';

        return $this;
    }
}