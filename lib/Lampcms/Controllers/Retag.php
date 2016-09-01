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

use \Lampcms\Utf8String;
use \Lampcms\WebPage;
use \Lampcms\TagsTokenizer;
use \Lampcms\Request;
use \Lampcms\Responder;

/**
 * Controller responsible
 * for processing retag form
 *
 * @author Dmitri Snytkine
 *
 */
class Retag extends WebPage
{
    protected $membersOnly = true;

    protected $requireToken = true;

    protected $aRequired = array('qid', 'tags');


    /**
     * Question being deleted
     *
     * @var object of type Question
     */
    protected $Question;


    /**
     * Array of old value of a_tags
     *
     * @var array
     */
    protected $aOldTags = array();


    /**
     * Array of tags that were added to
     * Question as result of this retag
     * Tags are not always added - a retag may
     * be just a removal of some tags
     * If any new tags were added this array will
     * include new tags
     *
     * @var tags
     */
    protected $aAddedTags = array();


    /**
     * Array of submitted tags
     * after they are run through TagsTokenizer
     *
     * @var array
     */
    protected $aSubmitted;


    protected function main()
    {
        $this->aSubmitted = TagsTokenizer::factory($this->Request->getUTF8('tags'))->getArrayCopy();
        d('$this->aSubmitted: ' . print_r($this->aSubmitted, 1));


        $this->validateSubmitted()
            ->getQuestion()
            ->checkPermission()
            ->checkForChanges()
            ->removeOldTags()
            ->updateQuestion()
            ->addNewTags()
            ->postEvent()
            ->returnResult();
    }


    /**
     * Validate to make sure
     * submitted form contains between 1 and 5 tags
     *
     * @throws \Lampcms\Exception if no tags or more than 5 tags
     *
     * @return object $this
     */
    protected function validateSubmitted()
    {

        $min = $this->Registry->Ini->MIN_QUESTION_TAGS;
        $max = $this->Registry->Ini->MAX_QUESTION_TAGS;

        if (($min > 0) && empty($this->aSubmitted)) {
            /*
                * @todo translate string
                */
            throw new \Lampcms\Exception('No valid tags have been submitted. Please use words that best categorize this question');
        }

        $count = count($this->aSubmitted);

        if ($count < $min) {
            /**
             * @todo Translate string
             */
            throw new \Lampcms\Exception('Question must have at least ' . $min . ' tag(s)');
        }

        if ($count > $max) {
            /**
             * @todo translate string
             */
            throw new \Lampcms\Exception('Question cannot have more than ' . $max . ' tags. Please remove some tags');
        }

        return $this;
    }


    /**
     * Create $this->Question object
     *
     * @throws \Lampcms\Exception if question
     * not found or is marked as deleted
     *
     * @return object $this
     */
    protected function getQuestion()
    {

        $a = $this->Registry->Mongo->QUESTIONS->findOne(array('_id' => (int)$this->Request['qid']));
        d('a: ' . print_r($a, 1));

        if (empty($a) || !empty($a['i_del_ts'])) {

            throw new \Lampcms\Exception('Question not found');
        }

        $this->Question = new \Lampcms\Question($this->Registry, $a);
        $this->aOldTags = $this->Question['a_tags'];

        return $this;
    }


    /**
     * Check to make sure Viewer has permission
     * to retag.
     * Permitted to retag are: owner of question,
     * moderator/admin or user with reputation
     *
     * @return object $this
     *
     */
    protected function checkPermission()
    {

        if (!\Lampcms\isOwner($this->Registry->Viewer, $this->Question)
            && ($this->Registry->Viewer->getReputation() < $this->Registry->Ini->POINTS->RETAG)
        ) {

            $this->checkAccessPermission('retag');
        }

        return $this;
    }


    /**
     * Make sure that new tags are
     * different from tags that already
     * in the question
     *
     * @throws \Lampcms\Exception in case tags
     * has not changed
     *
     * @return object $this
     */
    protected function checkForChanges()
    {

        $this->aAddedTags = \array_diff($this->aSubmitted, $this->aOldTags);
        $diff2 = \array_diff($this->aOldTags, $this->aSubmitted);
        d('diff: ' . print_r($this->aAddedTags, 1));
        d('diff2: ' . print_r($diff2, 1));
        if (empty($this->aAddedTags) && empty($diff2)) {
            throw new \Lampcms\Exception('You have not changed any tags');
        }

        return $this;
    }


    /**
     * Update USER_TAGS and QUESTION_TAGS and RELATED_TAGS collections
     * to remove old tags that belong to this questions
     *
     * @return object $this
     */
    protected function removeOldTags()
    {
        \Lampcms\Qtagscounter::factory($this->Registry)->removeTags($this->Question);
        \Lampcms\UserTags::factory($this->Registry)->removeTags($this->Question);
        \Lampcms\Relatedtags::factory($this->Registry)->removeTags($this->Question);
        /**
         * Also update UNANSWERED_TAGS if this question
         * is unanswered
         */
        if (0 === $this->Question['i_sel_ans']) {
            d('going to remove from Unanswered tags');
            \Lampcms\UnansweredTags::factory($this->Registry)->remove($this->Question);
        }

        return $this;
    }


    /**
     * Update USER_TAGS and QUESTION_TAGS collections
     * to add new tags that belong to this questions
     *
     * @return object $this
     */
    protected function addNewTags()
    {
        if (count($this->aSubmitted) > 0) {
            \Lampcms\Qtagscounter::factory($this->Registry)->parse($this->Question);
            \Lampcms\UserTags::factory($this->Registry)->addTags($this->Question['i_uid'], $this->Question);
            \Lampcms\Relatedtags::factory($this->Registry)->addTags($this->Question);

            if (0 === $this->Question['i_sel_ans']) {
                d('going to add to Unanswered tags');
                \Lampcms\UnansweredTags::factory($this->Registry)->set($this->Question);
            }
        }

        return $this;
    }


    /**
     * Update question object with
     * new values related to tags
     *
     * @return object $this
     */
    protected function updateQuestion()
    {

        $this->Question->retag($this->Registry->Viewer, $this->aSubmitted)
            ->save();

        return $this;
    }


    /**
     * Post onRetag event
     *
     * @return object $this
     */
    protected function postEvent()
    {
        $this->Registry->Dispatcher->post($this->Question, 'onRetag', $this->aAddedTags);

        return $this;
    }


    protected function returnResult()
    {
        /**
         * @todo translate string
         */
        $message = 'Question retagged successfully';

        if (Request::isAjax()) {
            $ret = array('reload' => 100); //'alert' => $message,

            Responder::sendJSON($ret);
        }

        Responder::redirectToPage($this->Question->getUrl());
    }

}
