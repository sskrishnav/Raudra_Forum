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
use \Lampcms\Responder;
use \Lampcms\Question;
use \Lampcms\Mongo\Schema\Question as Schema;

/**
 * Controller for processing the 'Accept as best answer'
 * vote
 *
 * @todo   require POST method for this
 *
 * @author Dmitri Snytkine
 *
 */
class Accept extends WebPage
{

    protected $aRequired = array('aid');

    protected $aOldAnswer;

    /**
     *
     * Question object
     *
     * @var Object Question for which answer is accepted
     */
    protected $Question;

    /**
     * Answer object
     *
     * @var Answer object
     */
    protected $Answer;

    protected $membersOnly = true;

    protected $permission = 'accept';

    protected function main()
    {
        /**
         * Init cache so it can listen to event
         * Does not have to be set as instance variable
         * because its constructor attaches itself to
         * Dispatcher.
         */
        $Cache = $this->Registry->Cache;
        $this->Registry->registerObservers('INPUT_FILTERS');
        //try{
        $this->checkVoteHack()
            ->getAnswer()
            ->getQuestion()
            ->checkViewer()
            ->postOnBefore()
            ->updateQuestion()
            ->updateUser()
            ->postEvent();

        $this->Answer->save();
        //} catch(\Exception $e){
        //	d('Accept not counted due to exception: '.$e->getMessage().' in '.$e->getFile().' line: '.$e->getLine());
        //}

        $this->redirect();
    }


    /**
     * Post onAcceptAnswer event
     *
     * @return object $this
     */
    protected function postEvent()
    {
        $this->Registry->Dispatcher->post($this->Question, 'onAcceptAnswer', array('answer' => $this->Answer));

        return $this;
    }


    /**
     *
     * post onBeforeAcceptAnswer event
     * and if notification is cancelled by observer
     * then throw Exception
     *
     * @throws \Lampcms\Exception
     * @return object $this
     */
    protected function postOnBefore()
    {
        $notification = $this->Registry->Dispatcher->post($this->Question, 'onBeforeAcceptAnswer', array('answer' => $this->Answer));
        if ($notification->isNotificationCancelled()) {
            d('notification onBeforeAcceptAnswer cancelled');
            /**
             * Set Answer object as saved
             * so it will not be auto-saved
             * by its own __destruct()
             */
            $this->Answer->setSaved();
            throw new \Lampcms\Exception('This feature is unavailable at this time');
        }

        return $this;
    }


    /**
     * Check VOTE_HACKS table
     * if accept action comes from ip
     * address or user id who been flagged
     * for vote hack then don't proceed
     * any further
     *
     * @todo move this to external class and make
     *       this method static, accepting only Registry
     *
     *
     * @throws \Lampcms\Exception
     * @return object $this
     */
    protected function checkVoteHack()
    {
        if (!$this->Registry->Viewer->isModerator()) {
            $timeOffset = time() - 172800; // 2 days

            $cur = $this->Registry->Mongo->VOTE_HACKS->find(array('i_ts' => array('$gt' => $timeOffset)));

            if ($cur && $cur->count(true) > 0) {
                $ip  = Request::getIP();
                $uid = $this->Registry->Viewer->getUid();

                foreach ($cur as $aRec) {
                    if ($ip === $aRec['ip'] || $uid == $uid) {
                        throw new \Lampcms\Exception('This action is disabled at this time');
                    }
                }
            }
        }

        return $this;
    }


    /**
     * Get record for the selected answer
     * and create the $this->Answer Answer object
     *
     * @throws \Lampcms\Exception if unable to find record
     * in ANSWERS collection
     *
     * @return object $this
     */
    protected function getAnswer()
    {
        $id      = $this->Router->getNumber(1);
        $aAnswer = $this->Registry->Mongo->ANSWERS->findOne(array(Schema::PRIMARY => $id));
        d('$aAnswer: ' . \json_encode($aAnswer));

        if (empty($aAnswer)) {
            throw new \Lampcms\Exception('Answer not found by id: ' . $id);
        }

        $this->Answer = new \Lampcms\Answer($this->Registry, $aAnswer);

        return $this;
    }


    /**
     * Get data for a question for which
     * the answer is being accepted
     * and create object $this->Question
     *
     * We need object instead of just array because
     * object has getUrl() method which we will need to redirect
     * Also object has an auto-save changes so we can just make changes
     * to the object and don't worry about explicitly saving to Mongo
     *
     *
     * @throws \Lampcms\Exception
     * @return \Lampcms\Controllers\Accept
     */
    protected function getQuestion()
    {
        $id        = $this->Router->getNumber(1);
        $aQuestion = $this->Registry->Mongo->QUESTIONS->findOne(array(Schema::PRIMARY => $this->Answer->getQuestionId()));

        d('$aQuestion: ' . \json_encode($aQuestion));

        if (empty($aQuestion)) {
            throw new \Lampcms\Exception('Question not found for this answer: ' . $id);
        }

        $this->Question = new Question($this->Registry, $aQuestion);
        d('cp');

        return $this;
    }


    /**
     * Check ownership
     * If Viewer is not the owner of the question
     * then this is some type of a vote hack
     * We should record it
     *
     * This does not apply to moderators as moderators
     * can also accept the best answer
     *
     *
     * @throws \Lampcms\Exception if accept action
     * came from someone other than question owner or moderator
     * @return object $this
     */
    protected function checkViewer()
    {
        $ownerID = $this->Question->getOwnerId();
        if (!$this->Registry->Viewer->isModerator()) {
            if ($ownerID != $this->Registry->Viewer->getUid()) {
                d('cp voting for someone else question');
                /**
                 * Post onAcceptHack event
                 */
                $this->Registry->Dispatcher->post($this->Question, 'onAcceptHack');

                $this->recordVoteHack();

                throw new \Lampcms\Exception('You can only accept answer for your own question');

            }
        }

        return $this;
    }


    /**
     * Update the value of i_sel_and and i_lm_ts
     * in the QUESTIONS collection for this question
     *
     *
     * @throws \Lampcms\Exception
     * @return object $this
     */
    protected function updateQuestion()
    {
        $ansID = $this->Question[Schema::SELECTED_ANSWER_ID];
        d('$ansID: ' . $ansID);

        if (!empty($ansID)) {

            if ($ansID == $this->Answer->getResourceId()) {
                $err = 'This answer is already a selected answer';

                /**
                 * No point in proceeding any further
                 * if the same answer is already marked
                 * as selected
                 */
                throw new \Lampcms\Exception($err);
            }

            /**
             * Need to set 'accepted' to null
             * in old answer because it is not longer an accepted one
             * and also decrease reputation for user who
             * owned formerly accepted answer
             */
            $this->getOldAnswer()
                ->updateOldAnswer()
                ->updateOldAnswerer();

        } else {
            $this->rewardViewer();
        }

        $this->Question->setBestAnswer($this->Answer)->save();

        return $this;
    }


    /**
     * Get array for old answer
     *
     * @return object $this
     */
    protected function getOldAnswer()
    {
        $this->aOldAnswer = $this->Registry->Mongo->ANSWERS->findOne(array(
                Schema::PRIMARY => $this->Question[Schema::SELECTED_ANSWER_ID]
            )
        );
        d('old answer: ' . \json_encode($this->aOldAnswer));

        return $this;
    }


    /**
     * Mark old answer as not accepted
     *
     * @return object $this
     */
    protected function updateOldAnswer()
    {
        if (!empty($this->aOldAnswer)) {
            $this->aOldAnswer['accepted'] = false;
            $this->Registry->Mongo->ANSWERS->save($this->aOldAnswer);
        }

        return $this;
    }


    /**
     * Decrease reputation of user who
     * owns the old answer
     *
     * @todo check current score and make sure
     *       it will not become negative after we deduct
     *       some points
     *
     * @return object $this
     */
    protected function updateOldAnswerer()
    {
        if (!empty($this->aOldAnswer)) {
            $uid = $this->aOldAnswer[Schema::POSTER_ID];
            if (!empty($uid)) {
                try {
                    \Lampcms\User::userFactory($this->Registry)->by_id($uid)->setReputation((0 - $this->Registry->Ini->POINTS->BEST_ANSWER))->save();

                } catch ( \MongoException $e ) {
                    e('unable to update reputation for old answerer ' . $e->getMessage());
                }
            }
        }

        return $this;
    }


    /**
     * Increase reputation of user
     * who answered this question
     *
     * But NOT if answered own question
     *
     *
     * @return object $this
     */
    protected function updateUser()
    {
        $uid = $this->Answer->getOwnerId();
        d('$this->Answer->getOwnerId():. ' . $uid);

        if (!empty($uid) && ($this->Question[Schema::POSTER_ID] == $uid)) {
            d('Answered own question, this does not count towards reputation');

            return $this;
        }

        try {
            $this->Registry->Mongo->USERS->update(array('_id' => $uid), array('$inc' => array("i_rep" => $this->Registry->Ini->POINTS->BEST_ANSWER)));
        } catch ( \MongoException $e ) {
            e('unable to increase reputation for answerer ' . $e->getMessage());
        }

        return $this;
    }


    /**
     * Increase the reputation of Viewer for
     * accepting an answer BUT
     * ONLY if this is the first type Viewer
     * accepted answer for this question
     *
     * @return object $this;
     */
    protected function rewardViewer()
    {

        /**
         * Check that accepted by owner of question
         * In case it was accepted by a moderator
         * we don't reward moderator by accpeting
         * the answer for someone else's question
         */
        if ($this->Question->getOwnerId() == $this->Registry->Viewer->getUid()) {
            $this->Registry->Viewer->setReputation($this->Registry->Ini->POINTS->ACCEPT_ANSWER)->save();
        }

        return $this;
    }

    /**
     * Insert record into VOTE_HACKS collection
     *
     * @todo move this to external class and make
     *       this method static, accepting only Registry
     * @return \Lampcms\Controllers\Accept
     */
    protected function recordVoteHack()
    {
        $coll = $this->Registry->Mongo->VOTE_HACKS;
        $coll->ensureIndex(array(Schema::CREATED_TIMESTAMP => 1));
        $aData = array(
            Schema::POSTER_ID => $this->Registry->Viewer->getUid(),
            Schema::CREATED_TIMESTAMP  => time(),
            Schema::IP_ADDRESS    => Request::getIP());

        $coll->save($aData);

        return $this;
    }


    /**
     * Redirect user back to url
     * of the question.
     *
     * This time user will see the just selected
     * answer as 'selected'
     */
    protected function redirect()
    {
        Responder::redirectToPage($this->Question->getUrl());
    }
}
