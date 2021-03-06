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
 * Class responsible for
 * upserting (update or insert)
 * array of tags that belong to one question
 * into QUESTION_TAGS collection
 *
 * @author Dmitri Snytkine
 *
 */
class Qtagscounter extends LampcmsObject
{

    /**
     * Mongo collection QUESTION_TAGS
     *
     * @var object of type MongoCollection for QUESTION_TAGS
     */
    protected $coll;

    public function __construct(Registry $Registry)
    {
        $this->Registry = $Registry;
        $this->coll = $Registry->Mongo->QUESTION_TAGS;
    }


    /**
     * Parse one question, upserting its' tags
     * into QUESTION_TAGS collection
     *
     * Why do we care so much about having accurate timestamp
     * of the last time tag was inserted?
     *
     * It's because we use it when showing recent tags
     * block. We use timestamps as offset to show tags
     * in the past week or month or whatever.
     *
     * We don't want to rely on timestamp from
     * external API. But then we will have mismatch of timestamps
     * if API timestamp is way off, then our tags counter
     * is updated with out time while question stamped with
     * API's timestamp so count of n for tags will be wrong
     * when we actually viewing the page.
     *
     * We must either trust external API's timestamp or don't
     *  trust it. We can't trust it in one collection
     *  and not trust it in another.
     *  It will be a lot easier if we just
     *  trust external API's timestamp
     *
     * @todo we can use this as post-echo method
     *
     * @param Question $Question
     * @param array    $aExtra
     *
     * @return object $this
     */
    public function parse(Question $Question, array $aExtra = array())
    {
        $aTags = $Question['a_tags'];
        $time = $Question['i_ts'];

        if (!empty($aTags)) {
            $set = array('i_ts' => $time, 'hts' => date("F j, Y g:i a"));
            if (!empty($aExtra)) {
                $set = $set + $aExtra;
                // d('new $set: '.print_r($set, 1));
            }

            $this->coll->ensureIndex(array('tag' => 1), array('unique' => true));
            $this->coll->ensureIndex(array('i_count' => 1));

            foreach ($aTags as $tag) {
                /**
                 * Sanity check, even though there aren't supposed
                 * to be any way to sneak an empty tag into
                 * a question, but just in case... because it's just that important
                 * to not allow empty values or Mongo will throw Exception
                 */
                if (!empty($tag)) {
                    try {
                        $this->coll->update(array("tag" => $tag), array('$inc' => array("i_count" => 1), '$set' => $set), array("upsert" => true));
                    } catch (\MongoException $e) {
                        //e('unable to upsert into QUESTION_TAGS : '.$e->getMessage());
                    }
                }
            }
        }

        return $this;
    }


    /**
     * When question is deleted or retagged
     * we must update the collection
     * to decrease number of tags count to account
     * for this removed question
     *
     * @param Question $Question
     *
     * @throws \InvalidArgumentException
     * @return object $this;
     */
    public function removeTags($Question)
    {
        if (!is_array($Question) && (!($Question instanceof \Lampcms\Question))) {
            throw new \InvalidArgumentException('$Question must be array OR instance of Question. was: ' . gettype($Question));
        }

        $aTags = (is_array($Question)) ? $Question : $Question['a_tags'];

        if (!empty($aTags)) {
            /**
             * In theory the $tag will exist
             * because it has been inserted there when
             * this question was created and the count is at least 1
             *
             *The only possible problem is that the count will be set
             *to 0 instead of deleting a tag, but this
             *is not really a problem we can just not show tags
             *that have 0 count
             */
            foreach ($aTags as $tag) {
                if (!empty($tag)) {
                    try {
                        /**
                         * Do this extra step to find current count
                         * then remove tag if count is NOT positive integer,
                         * otherwise decrease it
                         *
                         * This way we avoid the possibility of setting
                         * the i_count to 0 or -1
                         */
                        $a = $this->coll->findOne(array('tag' => $tag), array('i_count'));
                        d('a: ' . var_export($a, 1));
                        if ($a && ($a['i_count'] > 0)) {
                            $this->coll->update(array("tag" => $tag), array('$inc' => array("i_count" => -1)));
                        } else {
                            $this->coll->remove(array("tag" => $tag), array('safe' => true));
                        }
                    } catch (\MongoException $e) {
                        e('unable to update (decrease count) QUESTION_TAGS : ' . $e->getMessage());
                    }
                }
            }
        }

        return $this;
    }
}
