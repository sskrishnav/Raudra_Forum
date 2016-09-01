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

namespace Lampcms\Mongo\Schema;

/**
 * Constants defined here are for
 * defining status id for Question and Answer
 *
 */
class Resource
{

    const PRIMARY = '_id';

    const POSTER_ID = 'i_uid';

    const CATEGORY_ID = 'i_cat';

    const IP_ADDRESS = 'ip';

    const STATUS = 'status';

    /**
     * This field is a Status id of Question or Answer
     * This indicates POSTED, PENDING or DELETED status
     * type is integer
     */
    const RESOURCE_STATUS_ID = 'i_status';

    /**
     * ID of user (moderator) who approved this resource
     *
     */
    const APPROVED_BY_ID = 'i_approved_by';

    const APPROVED_BY_USERNAME = 'approved_by';

    /**
     * Timestamp of when resource
     * was set as approved
     */
    const APPROVED_TIMESTAMP = 'i_approved_ts';


    const CREATED_TIMESTAMP = 'i_ts';

    const DELETED_TIMESTAMP = 'i_del_ts';

    /**
     * Array of followers
     * this is array of integers (user ids)
     */
    const FOLLOWERS = 'a_flwrs';


    const COMMENTS_ARRAY = 'a_comments';

    /**
     * count of followers
     */
    const NUM_FOLLOWERS = 'i_flwrs';

    /**
     * Status of normal Question or answer
     */
    const POSTED = 1;

    /**
     * Status of pending Question or Answer
     */
    const PENDING = 2;

    /**
     * Status of deleted Question or Answer
     */
    const DELETED = 4;
}
