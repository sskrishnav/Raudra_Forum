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


namespace Lampcms\Modules\Observers;

use \Lampcms\Mail\TranslatableBody;
use \Lampcms\Mail\TranslatableSubject;
use \Lampcms\Mongo\Schema\User as Schema;

/**
 * This class is an observer
 * It monitors events that trigger
 * sending out of emails to users
 * who are subscribed to certain things.
 *
 * For example users who follow specific tag
 * will all be sent emails telling them that
 * a new question with that tag has been added.
 *
 * The sending our of emails is done via the shutdown_function
 * which means that it will not delay the rendering of the page
 * that posted the event. Even the find() from the database
 * will be done inside the shutdown function just in case
 * the find() takes longer than a couple of seconds, it will
 * never affect the page rendering time
 *
 * Of cause for this to work the php must be running as
 * a fastcgi controlled by php fpm which is part of php 5.3.X
 * Talk to me if you need clarification on this one
 * Ask your question at http://support.lampcms.com
 *
 *
 * @todo   For a super busy and popular site
 *         that has over 10,000 followers for some
 *         tags or for some users this class has to be re-written to run
 *         very differently.
 *
 * Basically such sites should have separate dedicated server
 * for sending out mass emails. The most efficient way then would
 * be to invoke the script on that other server and only pass
 * the params there. So the notifyTagFollowers
 * will just be invoking the remote script
 * and passing comma separated list
 * of tags as param
 * notifyUserFollowers will be invoking remote script and passing
 * userID of user as just one param.
 *
 * That remote script will
 * know what to do it will normally do this:
 * get own cursor, get that huge number of results like maybe 25000 followers
 * and then send then in chunks of 1000 and sleep 2 minutes in between
 * It's perfectly fine for one script to take sometimes 1 hours to send out such
 * large number of emails.
 *
 * Another possible way to do this is to just add
 * the email notification jobs to the
 * PENDING_NOTIFICATIONS collection as
 * a nested object via $addToSet operation of Mongo
 *
 * @todo   The dedicated server will periodically check
 *         that collection and will pop the notifications off
 *         the nested array and process them.
 *         This is probably a very effective way to deal
 *         with frequent notifications and where notification
 *         jobs can potentially jobs that need to send very large
 *         number of emails - like when a user or topic is followed
 *         by tens of thousands of other users.
 *         This type of solution will not require invoking
 *         a remote script, instead a remove server will be
 *         checking the PENDING_NOTIFICATIONS collection
 *         via cron script
 *         In order for this to work you need to extend this class
 *         and the factory() must return the instance of
 *         that sub-class instead of this class.
 *
 * @author Dmitri Snytkine
 *
 */
class EmailNotifier extends \Lampcms\Event\Observer
{

    /**
     * UserID of author
     *
     * @var int
     */
    protected $author_id = 0;

    /**
     * @var \Lampcms\Question object
     */
    protected $Question;

    /**
     * Mongo USERS collection
     * this collection is used from
     * every method, so it's an instance
     * variable, here in one place
     *
     * @var object of type MongoCollection
     */
    protected $collUsers;

    /**
     * @var function
     */
    protected $routerCallback;

    /**
     * Factory
     * This is not required for the default operation - to instantiate
     * this class because \Lampcms\Observer already has
     * the same factory but
     * in case you need a more fancier implementation of all the
     * Email notification methods - like instead of actually
     * sending out emails right away you can
     * just add the pending jobs to some Mongo Collection
     * what you can do is extend this class and have this factory
     * return your new sub-class
     *
     * Another thing you can do (maybe even better) is to write
     * a totally new EmailNotifier-type of class and then
     * in !config.ini replace the path to this class
     * with the path to your own new class. This is better
     * because in case of upgrades your changes will not
     * be overridden since !config.ini is never overridden in
     * upgrade - it's not included in the distro
     *
     *
     * @param \Lampcms\Registry $Registry
     *
     * @return \Lampcms\Event\Observer|\Lampcms\Modules\Observers\EmailNotifier
     */
    public static function factory(\Lampcms\Registry $Registry)
    {
        return new self($Registry);
    }

    public function __construct(\Lampcms\Registry $Registry)
    {
        parent::__construct($Registry);

    }

    /**
     * @todo Finish this by adding handling
     *       updates onEditedQuestion, onQuestionVote,
     *       onAcceptAnswer, etc...
     *       and later deal with comment replies
     *
     * (non-PHPdoc)
     * @see  Lampcms.Observer::main()
     */
    public function main()
    {

        /**
         * @todo must write function to handle onApprovedAnswer
         * cannot just use onNewAnswer for onApprovedAnswer event
         * because the Viewer is NOT the same user who posted an Answer - a
         * Viewer in this case is a Moderator who approved Answer and we need
         * the Answer posted id. So we need to extract uid of posted
         * first and then pass it to notifyQuestionFollowers
         */
        d('get event: ' . $this->eventName);
        switch ( $this->eventName ) {
            case 'onNewQuestion':
            case 'onApprovedQuestion':
                $this->collUsers = $this->Registry->Mongo->USERS;
                $this->Question  = $this->obj;
                $this->notifyUserFollowers();
                $this->notifyTagFollowers();
                break;

            case 'onNewAnswer':
                $this->collUsers = $this->Registry->Mongo->USERS;
                $this->Question  = $this->aInfo['question'];
                $this->notifyUserFollowers();
                $this->notifyQuestionFollowers();
                break;

            case 'onNewComment':
                $this->collUsers = $this->Registry->Mongo->USERS;
                $this->notifyOnComment();
                break;

            case 'onRetag' :
                $this->collUsers = $this->Registry->Mongo->USERS;
                $this->Question  = $this->obj;
                $this->notifyTagFollowers($this->aInfo);
                break;
        }

        d('done main()');
    }


    /**
     * In case this is a comment to a question:
     * notify Question followers ONLY
     *
     * In Case this is a comment to an answer:
     * notify Answer author ONLY if not opted out
     * of this option.
     *
     * IN case this is a reply to a comment:
     * notify parent-comment author in addition
     * to the above and ONLY if not opted out of this
     * AND also then exclude the parent-comment
     * author from all the above updates.
     *
     * In addition always exclude comment author from updates
     *
     *
     *
     */
    protected function notifyOnComment()
    {

        /**
         * Special case if this is a reply to existing comment:
         * in which case we notify parent comment owner
         * and also question owner if this is comment on a question.
         *
         * @todo not yet implemented
         */

        /**
         * $this->obj is object of type SubmittedComment
         * it has getResource() method and returns a resource
         * for this it is a comment, usually Question or Answer
         * If it's a Question we set $this->Question
         * and then just notify question followers
         *
         * else we notify Answer author (since there is no
         * such thing as answer follower) and Also notify question
         * followers but we don't have the Question object so
         * we need to just use getQuestionId from Answer object
         *
         * @var
         */
        $Resource = $this->obj->getResource();
        if (!empty($this->aInfo['inreply_uid'])) {
            d('this is a reply');
            $this->notifyCommentAuthor($Resource);
        } else {
            if ($Resource instanceof \Lampcms\Question) {
                d('cp');
                $this->Question = $Resource;
                $this->notifyQuestionFollowers();
            } elseif ($Resource instanceof \Lampcms\Answer) {
                d('cp');
                $this->notifyAnswerAuthor($Resource);
            } else {
                throw new \Lampcms\DevException('Something is wrong here. The object is not Question and not Answer. it is: ' . \get_class($Resource));
            }
        }
    }


    /**
     * Notify just the author of the answer
     * exclude the ViewerID
     *
     * @param \Lampcms\Answer $Answer
     * @param int             $excludeUid
     *
     * @return object $this
     */
    protected function notifyAnswerAuthor(\Lampcms\Answer $Answer, $excludeUid = 0)
    {

        $routerCallback = $this->Registry->Router->getCallback();
        $commentorID    = (int)$this->aInfo['i_uid'];
        $answerOwnerId  = $Answer->getOwnerId();
        $siteUrl        = $this->Registry->Ini->SITE_URL;

        d('$siteUrl: ' . $siteUrl);

        $url = $siteUrl . '{_WEB_ROOT_}/{_viewquestion_}/{_QID_PREFIX_}' . $this->aInfo['i_qid'] . '/#c' . $this->aInfo['_id'];

        $commUrl = $routerCallback($url);
        d('commUrl: ' . $commUrl);

        $ansID = $Answer->getResourceId();
        d('ansID: ' . $ansID);
        d('$answerOwnerId: ' . $answerOwnerId);

        $coll = $this->collUsers;

        $varsBody = array(
            '{username}'   => $this->aInfo['username'],
            '{link}'       => $commUrl,
            '{site_title}' => $this->Registry->Ini->SITE_NAME,
            '{title}'      => $Answer['title'],
            '{body}'       => \strip_tags($this->aInfo['b']),
            '{home}'       => $this->Registry->Router->getHomePageUrl());

        $subj   = new TranslatableSubject('email.subject.answer_author', array('{username}' => $this->aInfo['username']));
        $body   = new TranslatableBody('email.body.answer_author', $varsBody);
        $locale = LAMPCMS_DEFAULT_LOCALE;

        d('subj: ' . (string)$subj);

        $Mailer = $this->Registry->Mailer;
        $Mailer->setCache($this->Registry->Cache);

        /**
         * Don not notify if comment made
         * by the same user who is answer author
         *
         * This update is sent to only one user - answer owner
         * so we use findOne instead of find()
         * and send use mail() instead of mailFromCursor() on Mailer
         */
        if (($commentorID !== $answerOwnerId) && ($commentorID != $excludeUid)) {
            d('setting up callable function');
            $callable = function() use ($answerOwnerId, $coll, $subj, $body, $Mailer, $locale)
            {

                $aUser = $coll->findOne(array('_id' => $answerOwnerId, 'ne_fa' => array('$ne' => true)), array('email' => true, 'locale' => true));

                if (!empty($aUser) && !empty($aUser['email'])) {

                    if (!empty($aUser['locale'])) {
                        $locale = $aUser['locale'];
                    }

                    $subj = $Mailer->translate($locale, $subj);
                    $body = $Mailer->translate($locale, $body);

                    $Mailer->mail($aUser['email'], $subj, $body, null, false);
                }
            };


            d('adding callable to runLater');

            \Lampcms\runLater($callable);
        }

        return $this;
    }


    /**
     * Notify just one user - the author of comment
     * that a reply has been posted to his comment
     *
     * Should NOT send out this notification
     * if author of parent comment
     * is also the author of the Answer AND optin to receive
     * comments on answer in case this in an answer
     *
     * In case of question IF parent comment author
     * is also following QUESTION OR QUESTION AUTHOR
     * then also exclude that user.
     *
     * OR MAYBE _ DON TREAT REPLY AS COMMENT _ SO DON'T
     * SEND OUT THE REGULAR onNewComment emails in case
     * of a reply and ONLY send out a onCommentReply email!
     *
     * This actually makes sense because reply to comment
     * often very specific to that parent comment and NOT
     * interesting to Question followers...
     *
     * @param \Lampcms\Interfaces\Post|object $Resource Answer OR Question object
     *
     * @return \Lampcms\Modules\Observers\EmailNotifier
     */
    protected function notifyCommentAuthor(\Lampcms\Interfaces\Post $Resource)
    {
        $routerCallback = $this->Registry->Router->getCallback();
        /**
         * CommentorID is always the ViewerID
         */
        $commentorID        = (int)$this->aInfo['i_uid'];
        $parentCommentOwner = (int)$this->aInfo['inreply_uid'];
        $siteUrl            = $this->Registry->Ini->SITE_URL;
        $home               = $this->Registry->Router->getHomePageUrl();
        $locale             = LAMPCMS_DEFAULT_LOCALE;

        $url = $siteUrl . '{_WEB_ROOT_}/{_viewquestion_}/{_QID_PREFIX_}' . $Resource->getQuestionId() . '/#c' . $this->aInfo['_id'];

        $commUrl = $routerCallback($url);
        d('commUrl: ' . $commUrl);

        /**
         * If replied to own comment don't notify self
         * This will also take care of not notifying
         * Viewer because if Viewer replied to own comment
         * this condition check will be true
         */
        if ($parentCommentOwner == $commentorID) {
            return $this;
        }

        $coll = $this->collUsers;
        $subj = new TranslatableSubject('email.subject.comment_reply', array('{username}' => $this->aInfo['username']));
        $body = new TranslatableBody('email.body.comment_reply', array(
                '{username}'    => $this->aInfo['username'],
                '{parent_body}' => \strip_tags($this->aInfo['parent_body']),
                '{body}'        => \strip_tags($this->aInfo['b']),
                '{link}'        => $commUrl,
                '{home}'        => $home)
        );

        d('subj: ' . $subj . ' body: ' . $body);

        $Mailer = $this->Registry->Mailer;
        $Mailer->setCache($this->Registry->Cache);

        $callable = function() use ($parentCommentOwner, $coll, $subj, $body, $Mailer, $locale)
        {

            $aUser = $coll->findOne(array('_id' => $parentCommentOwner, 'ne_fc' => array('$ne' => true)), array('email' => true, 'locale' => true));

            if (!empty($aUser) && !empty($aUser['email'])) {

                if (!empty($aUser['locale'])) {
                    $locale = $aUser['locale'];
                }

                $subj = $Mailer->translate($locale, $subj);
                $body = $Mailer->translate($locale, $body);
                $Mailer->mail($aUser['email'], $subj, $body, null, false);
            }
        };

        \Lampcms\runLater($callable);

    }


    /**
     * Get all users that follow any of the tags in question
     * BUT NOT following the Question owner because
     * we already sending out emails to all
     * who following question owner.
     *
     * This is an easy way to avoid sending out emails twice
     * in case user happens to follow Question asker and
     * one of the tags in question
     *
     * Also exclude the id of question author, in case
     * author is also following one of the tags in question
     * the author does not have to be notified
     * of own question.
     *
     * The cursor is then passed to Mailer object
     *
     * @param array $aNewTags array of new tags, if not passed then
     *                        array from $this->Question['a_tags'] will be used. This param
     *                        is used when handling onRetag Event in which case we receive
     *                        array of "new" tags that have been added as result of re-tagging
     *
     * @return object $this
     *
     * @todo use different subject if $aNewTags is passed here - the
     *       subject should indicate that Question was tagged with one
     *       of your tags
     */
    protected function notifyTagFollowers(array $aNewTags = null)
    {
        $aTags = (!empty($aNewTags)) ? $aNewTags : $this->Question['a_tags'];
        /**
         * since tags can be empty
         * simple return in case
         * there are not tags
         */
        if (empty($aTags)) {
            return $this;
        }

        $homeUrl = $this->Registry->Router->getHomePageUrl();
        $askerID = $this->Question->getOwnerId();
        $Mailer  = $this->Registry->Mailer;
        $Mailer->setCache($this->Registry->Cache);

        $varsBody = array('{username}'   => $this->obj['username'],
                          '{title}'      => $this->Question['title'],
                          '{body}'       => $this->Question['intro'],
                          '{link}'       => $this->Question->getUrl(),
                          '{site_title}' => $this->Registry->Ini->SITE_NAME,
                          '{home}'       => $homeUrl
        );

        $tagsString = \implode(', ', $this->Question['a_tags']);

        $subj = new TranslatableSubject('email.subject.new_question_tagged', array('{tags}' => $tagsString));
        $body = new TranslatableBody('email.body.new_question_tagged', $varsBody);
        $coll = $this->collUsers;
        d('before shutdown function in TagFollowers');

        $func = function() use($askerID, $Mailer, $subj, $body, $aTags, $coll)
        {
            /**
             * Find all users who follow any of the tags
             * but not following the asker
             * and not themselves the asker //
             */
            $where = array(
                '_id'   => array('$ne' => $askerID),
                'a_f_t' => array('$in' => $aTags),
                'a_f_u' => array('$nin' => array(0 => $askerID)),
                'ne_ft' => array('$ne' => true)
            );

            $cur   = $coll->find($where, array('email' => true, 'locale' => true));
            $count = $cur->count();
            if ($count > 0) {

                $Mailer->mailFromCursor($cur, $subj, $body);
            }

        };

        \Lampcms\runLater($func);

        return $this;
    }


    /**
     * Notify all followers if question
     * asker.
     *
     * @return object $this
     */
    protected function notifyUserFollowers()
    {

        $uid = $this->obj->getOwnerId();
        d('uid: ' . $uid);

        /**
         * In case of Answer use different
         * templates for SUBJ and BODY
         *
         */
        $tpl        = 'email.body.answer_by_user';
        $updateType = 'answer';
        $body       = '';
        if ('onNewQuestion' === $this->eventName || 'onApprovedQuestion' === $this->eventName) {

            $body       = $this->obj['intro'];
            $tpl        = 'email.body.question_by_user';
            $updateType = 'question';
        }

        $varsBody = array('{username}' => $this->obj['username'],
                          '{title}'    => $this->Question['title'],
                          '{body}'     => $body,
                          '{link}'     => $this->obj->getUrl(),
                          '{home}'     => $this->Registry->Router->getHomePageUrl());

        $varsSubj = array('{username}' => $this->obj['username']);

        $subj = new TranslatableSubject('email.subject.new_' . $updateType . '_by', $varsSubj);
        $body = new TranslatableBody($tpl, $varsBody);

        $coll   = $this->collUsers;
        $Mailer = $this->Registry->Mailer;
        $Mailer->setCache($this->Registry->Cache);
        d('before shutdown function in UserFollowers');

        /**
         * This callback is not used
         * was just an idea to pass the callback to
         * Mailer->mail as a $to value
         *
         * @return null
         */
        $cursorCallback = function() use ($coll, $uid)
        {
            $cur   = $coll->find(array('a_f_u' => $uid, 'ne_fu' => array('$ne' => true)), array(Schema::EMAIL => true, Schema::LOCALE => true));
            $count = $cur->count();
            if ($count > 0) {

            }

            return null;
        };

        /**
         * No need to pass $viewerID because
         * user already cannot possibly be following himself
         * so excluding ViewerID is pointless here
         */
        $func = function() use($uid, $subj, $body, $coll, $Mailer)
        {

            $cur   = $coll->find(array('a_f_u' => $uid, 'ne_fu' => array('$ne' => true)), array('email' => true, 'locale' => true));
            $count = $cur->count();
            if ($count > 0) {

                $Mailer->mailFromCursor($cur, $subj, $body);
            }

        };

        \Lampcms\runLater($func);

        return $this;
    }


    /**
     * Notify all who follows the question
     * But exclude the Viewer - whoever just added
     * the new answer or whatever
     *
     *
     * and exclude all who follows the Viewer because all who
     * follows the Viewer will be notified via
     * the nofityUserFollowers
     *
     * @param int qid id of question
     *
     * @param int excludeUid UserID of user that should NOT
     *            be notified. Usually this is in a special case of when
     *            the answer or comment owner has already been notified
     *            so now we just have to exclude the same user in case same user
     *            is also the question author.
     *
     * @return object $this
     */
    protected function notifyQuestionFollowers($qid = null, $excludeUid = 0)
    {
        $viewerID = $this->Registry->Viewer->getUid();
        d('$viewerID: ' . $viewerID);

        $routerCallback = $this->Registry->Router->getCallback();

        /**
         * $qid can be passed here
         * OR in can be extracted from $this->Question
         */
        if ($qid) {
            $Question = new \Lampcms\Question($this->Registry);
            try {
                $Question->by_id((int)$qid);
            } catch ( \Exception $e ) {
                e('Unable to create Question by _id. Exception: ' . $e->getMessage() . ' in file: ' . $e->getFile() . ' on line: ' . $e->getLine());
                $Question = null;
            }
        } else {
            $Question = $this->Question;
        }

        if (null === $Question) {
            d('no $Question');

            return $this;
        }

        $subj = ('onNewAnswer' === $this->eventName || 'onApprovedAnswer' === $this->eventName ) ? 'email.subject.question_answer' : 'email.subject.question_comment';
        $body = ('onNewAnswer' === $this->eventName || 'onApprovedAnswer' === $this->eventName) ? 'email.body.question_answer' : 'email.body.question_comment';

        $siteUrl    = $this->Registry->Ini->SITE_URL;
        $updateType = ('onNewAnswer' === $this->eventName || 'onApprovedAnswer' === $this->eventName) ? 'answer' : 'comment';
        $username   = ('onNewAnswer' === $this->eventName || 'onApprovedAnswer' === $this->eventName) ? $this->obj['username'] : $this->aInfo['username'];

        if ('onNewAnswer' === $this->eventName || 'onApprovedAnswer' === $this->eventName) {
            $url = $this->obj->getUrl();
        } else {
            $url = $siteUrl . '{_WEB_ROOT_}/{_viewquestion_}/{_QID_PREFIX_}' . $this->aInfo['i_qid'] . '/#c' . $this->aInfo['_id'];
            $url = $routerCallback($url);
        }

        d('url: ' . $url);

        $content = ('onNewAnswer' !== $this->eventName) ? "\n____\n" . \strip_tags($this->aInfo['b']) . "\n" : '';

        $varsBody = array('{username}'   => $username,
                          '{title}'      => $this->Question['title'],
                          '{body}'       => $content,
                          '{link}'       => $url,
                          '{site_title}' => $this->Registry->Ini->SITE_NAME,
                          '{home}'       => $this->Registry->Router->getHomePageUrl());

        $body = new TranslatableBody($body, $varsBody);
        $subj = new TranslatableSubject($subj);

        $Mailer = $this->Registry->Mailer;
        $Mailer->setCache($this->Registry->Cache);

        /**
         * MongoCollection USERS
         *
         * @var object MongoCollection
         */
        $coll = $this->collUsers;
        d('before shutdown function for question followers');

        /**
         * Get array of followers for this question
         */
        $aFollowers = $Question['a_flwrs'];

        if (!empty($aFollowers)) {
            $func = function() use($updateType, $viewerID, $aFollowers, $subj, $body, $coll, $Mailer, $excludeUid)
            {

                /**
                 * Remove $viewerID from aFollowers
                 * Remove excludeID from aFollowers
                 * Removing these userIDs from
                 * the find $in condition is guaranteed to not
                 * have these IDs in result and is much better
                 * than adding extra $ne or $nin conditions
                 * on these uids to find()
                 *
                 */
                if (false !== $key = array_search($viewerID, $aFollowers)) {
                    array_splice($aFollowers, $key, 1);
                }

                if (!empty($excludeUid)) {
                    if (false !== $key = array_search($excludeUid, $aFollowers)) {
                        array_splice($aFollowers, $key, 1);
                    }
                }

                array_unique($aFollowers);

                /**
                 * Find all users who follow this question
                 * and
                 * are not also following the Viewer
                 *
                 * In case of comment we should not exclude
                 * Viewer followers because Viewer followers are NOT
                 * notified on user comment
                 *
                 */
                if ('comment' !== $updateType) {
                    /**
                     * Condition: Find all users with _id in array of $aFollowers
                     * AND NOT FOLLOWING viewer (who is the author of this answer)
                     * because they will also be notified of this answer
                     * in a separate email that is sent to all followers of Viewer
                     */
                    $cur = $coll->find(array('_id' => array('$in' => $aFollowers), 'a_f_u' => array('$nin' => array(0 => $viewerID)), 'ne_fq' => array('$ne' => true)), array('email' => true, 'locale' => true));
                } else {
                    $cur = $coll->find(array('_id' => array('$in' => $aFollowers), 'ne_fq' => array('$ne' => true)), array('email' => true, 'locale' => true));
                }

                $count = $cur->count();

                if ($count > 0) {

                    $Mailer->mailFromCursor($cur, $subj, $body);

                }
            };

            \Lampcms\runLater($func);
        }

        return $this;
    }

}
