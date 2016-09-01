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


namespace Lampcms;

/**
 * Helper class for
 * formatting timestamp
 * into date and time string
 */
class TimeFormatter
{

    const NONE = 0;

    const SHORT = 1;

    const MEDIUM = 2;

    const LONG = 4;

    const FULL = 8;

    protected static $intlFormatMap;

    /**
     * Map constants of this class to
     * constants of IntlDateFormatter class
     *
     * @static
     * @return array
     */
    protected static function getIntlFormatMap()
    {
        if (!isset(self::$intlFormatMap)) {
            self::$intlFormatMap = array(
                self::NONE   => \IntlDateFormatter::NONE,
                self::SHORT  => \IntlDateFormatter::SHORT,
                self::MEDIUM => \IntlDateFormatter::MEDIUM,
                self::LONG   => \IntlDateFormatter::LONG,
                self::FULL   => \IntlDateFormatter::FULL
            );
        }

        return self::$intlFormatMap;
    }


    /**
     * Format timestamp according to
     * user's locale
     * Use intl extension if available, else use
     * php's date() formatting
     *
     * @static
     *
     * @param string $locale
     * @param int    $timestamp
     * @param int    $dateFormat
     * @param int    $timeFormat
     *
     * @return string
     */
    public static function formatTime($locale, $timestamp, $dateFormat = self::LONG, $timeFormat = self::SHORT)
    {
        /**
         * If NO intl extension OR locale is en_US then use php's date() function
         * to format.
         *  || 0 === strncmp('en', $_SESSION['locale'])
         */
        if (\extension_loaded('intl') && $locale !== '') {
            $map       = self::getIntlFormatMap();
            $df        = $map[$dateFormat];
            $tf        = $map[$timeFormat];
            $Formatter = new \IntlDateFormatter($locale, $df, $tf, \date_default_timezone_get());
            $res       = $Formatter->format($timestamp);
        } else {
            $format = self::getTimeFormat($dateFormat, $timeFormat);
            $res    = \date($format, $timestamp);
        }

        return $res;
    }


    /**
     * Get time formatting string
     * that can be used with php's date() function
     *
     * @static
     *
     * @param $dateFormat
     * @param $timeFormat
     *
     * @return string
     */
    protected static function getTimeFormat($dateFormat, $timeFormat)
    {
        switch ( $dateFormat . $timeFormat ) {

            case self::FULL . self::SHORT:
                $format = 'l, F j, Y h:i A';
                break;

            case self::FULL . self::MEDIUM:
                $format = 'l, F j, Y h:i:s A';
                break;

            case self::FULL . self::LONG:
            case self::FULL . self::FULL:
                $format = 'l, F j, Y h:i:s A \G\M\TP';
                break;

            case self::FULL . self::NONE:
                $format = 'l, F j';
                break;

            case self::LONG . self::SHORT:
                $format = 'F j, Y h:i A';
                break;

            case self::LONG . self::MEDIUM:
                $format = 'F j, Y h:i:s A';
                break;

            case self::LONG . self::LONG:
            case self::LONG . self::FULL:
                $format = 'F j, Y h:i:s A \G\M\TP';
                break;

            case self::LONG . self::NONE:
                $format = 'F j, Y';
                break;

            case self::MEDIUM . self::SHORT:
                $format = 'M j, Y h:i A';
                break;

            case self::MEDIUM . self::MEDIUM:
                $format = 'M j, Y h:i:s A';
                break;

            case self::MEDIUM . self::LONG:
            case self::MEDIUM . self::FULL:
                $format = 'M j, Y h:i:s A \G\M\TP';
                break;

            case self::MEDIUM . self::NONE:
                $format = 'M j, Y';
                break;

            case self::SHORT . self::SHORT:
                $format = 'm/j/y h:i A';
                break;

            case self::SHORT . self::MEDIUM:
                $format = 'm/j/y h:i:s A';
                break;

            case self::SHORT . self::LONG:
            case self::SHORT . self::FULL:
                $format = 'm/j/y h:i:s A \G\M\TP';
                break;

            case self::SHORT . self::NONE:
                $format = 'm/j/y';
                break;

            default:
                $format = 'Y-m-d H:i';
        }

        return $format;
    }
}
