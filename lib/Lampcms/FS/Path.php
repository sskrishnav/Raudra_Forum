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


namespace Lampcms\FS;

/**
 * Class for preparing and resolving file path
 * based on id using dec2hex and hex2dec file storage
 * scheme.
 *
 * @author Dmitri Snytkine
 *
 */
class Path
{

    /**
     * It is used to verify or create the path of the file (using HEX logic)
     *
     * @param int    $intResourceId  resource id
     *                               the path will be created based on this integer
     *
     * @param string $destinationDir directory in which
     *                               the path will be created.
     *
     * @param bool   $bReturnFullPath
     *
     * @throws \Lampcms\DevException
     * @throws \InvalidArgumentException
     * @return string a hex path (without file extension)
     * OR a full path, including the destinationDir prefix
     * if $bReturnFullPath param is true
     */
    public static final function prepare($intResourceId, $destinationDir = '', $bReturnFullPath = false)
    {

        if (!is_numeric($intResourceId)) {
            throw new \InvalidArgumentException('$intResourceId must be numeric, ideally an integer. Was: ' . $intResourceId);
        }

        /**
         * Resource id is converted to hex number
         */
        $destinationDir    = \trim((string)$destinationDir);
        $strHex            = dechex((int)$intResourceId);
        $strHex            = strtoupper($strHex);
        $arrTemp           = array();
        $intCount          = 0;
        $strPath           = '';
        $strFullPathToOrig = '';
        do {
            $intCount++;
            $intRes = preg_match("/([0-9A-F]{1,2})([0-9A-F]*)/", $strHex, $arrTemp);

            d('$arrTemp: ' . \json_encode($arrTemp));

            $strPath .= '' . $arrTemp[1];
            if ($arrTemp && ('' !== $arrTemp[2])) {
                $strHex = $arrTemp[2];
                $strPath .= '/';
                //   PATH to Location
                $strFullPathToOrig = $destinationDir . $strPath;

                d('$strFullPathToOrig: ' . $strFullPathToOrig);

                if (!\file_exists($strFullPathToOrig) && !\is_dir($strFullPathToOrig)) {
                    if (!\mkdir($strFullPathToOrig, 0777)) {
                        throw new \Lampcms\DevException('Cannot create directory ' . $strFullPathToOrig);
                    }
                }
            }
        } while ($intRes && ($intCount < 10) && ('' !== $arrTemp[2]));

        $ret = ($bReturnFullPath) ? $destinationDir . $strPath : $strPath;

        d(' $ret: ' . $ret);

        return $ret;
    }


    /**
     * Create directory based on current time, using Year, Month, Date format
     * for example 2012/11/24
     *
     * @param string       $basePath       path to directory in which to
     *                                     create desired directory structure
     * @param string       $subDir         sub directory name. For image uploads subdirectories
     *                                     are names by userID, for example /176/ for user 176
     * @param bool         $returnFullPath if true then return full path, including $basePath,
     *                                     otherwise return only the created directory structure
     *
     * @throws \Lampcms\DevException if unable to create directory
     * @return string full path or relative path to created directory
     *
     */
    public static function prepareByTimestamp($basePath, $subDir = null, $returnFullPath = true)
    {
        $D    = new \DateTime('now');
        $path = $D->format('Y') . DIRECTORY_SEPARATOR . $D->format('m') . DIRECTORY_SEPARATOR . $D->format('d');

        if(is_string($subDir)){
            $subDir = \trim($subDir, '/\\');
            $basePath .= DIRECTORY_SEPARATOR.$subDir;
            if(!\file_exists($basePath)){
                if(false === @mkdir($basePath)){
                    throw new \Lampcms\DevException('Unable to create directory: ' . $basePath);
                }
            }
        }

        $fullPath = $basePath . DIRECTORY_SEPARATOR . $path;
        if (!\file_exists($fullPath)) {
            $res = @mkdir($fullPath, 0777, true);
        } else {
            $res = true;
        }

        $path = $subDir.DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR;

        if (!$res) {
            throw new \Lampcms\DevException('Unable to create directory: ' . $path);
        }

        return ($returnFullPath) ? $fullPath : $path;
    }


    /**
     * Converts the hex path to integer
     * for example:
     * 3E/A
     * is converted to 1002
     *
     * @param string $hex
     * a hex-like path
     *
     * @return integer
     *
     */
    public static function hex2dec($hex)
    {
        $hex = str_replace('/', '', $hex);

        return hexdec($hex);
    }

}
