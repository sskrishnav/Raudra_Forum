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

/**
 *
 * Form for add/edit category admin page
 * It displays 2-columns: form in left column
 * and list of categories in right.
 * List of categories will be sortable via drag/drop
 * @author Dmitri Snytkine
 *
 */
class tplEditcats extends Lampcms\Template\Simple
{

    protected static $vars = array(
        'cat_title' => '',
        'manage_category' => '',
        'cat_title_desc' => '',
        'cat_slug' => '',
        'catslug_desc' => '',
        'cat_desc' => '',
        'catdesc_desc' => '',
        'catonly_checked' => '',
        'catonly' => '',
        'catonly_desc' => '',
        'submit' => '',
        'reset' => '',
        'categories' => '',
        'cat_list' => '',
        'upload' => '',
        'save' => '',
        'sort_manual' => '@@Double click on category to edit. Drag and drop to sort. When done click@@ "@@Save Sort order@@"'
    );

    protected static $tpl = '<div class="yui3-g">
		<div class="yui3-u-1-3">
			<form name="edit_category" method="POST" id="id_edit_category" enctype="multipart/form-data" action="{_WEB_ROOT_}/"
				accept-charset="utf-8">
				<div id="add_category">
					<h3>{manage_category}</h3>
					<div class="form_error"></div>
					<input type="hidden" name="a" value="editcategory">
					<input type="hidden" name="category_id" value="">
					<div class="form_el1">
						<label for="id_cattitle">{cat_title}</label>: <span class="f_err"
							id="cattitle_e"></span><br> <input id="id_cattitle"
							type="text" name="cattitle" size="40" value="">
						<div id="cattitle_d" class="caption">{cat_title_desc}</div>
					</div>
					<div class="form_el1">
						<label for="id_catslug">{cat_slug}</label>: <span class="f_err"
							id="catslug_e"></span><br> <input id="id_catslug"
							type="text" name="catslug" size="40" value="">
						<div id="catslug_d" class="caption">{catslug_desc}</div>
					</div>
					<div class="form_el1">
						<label for="id_catdesc">{cat_desc}</label>: <span class="f_err"
							id="catdesc_e"></span><br>
						<textarea name="catdesc" cols="45" rows="3" class="com_bo"
							style="display: block; padding: 2px;" id="id_catdesc"></textarea>
						<div id="catdesc_d" class="caption">{catdesc_desc}</div>
					</div>
					<div class="form_el1">
						<input id="id_catonly"
							type="checkbox" name="catonly" {catonly_checked}> <label for="id_catonly">{catonly}</label>
						<div id="catonly_d" class="caption">{catonly_desc}</div>
					</div>
					<div class="form_el1">
						<input id="id_active"
							type="checkbox" name="active" {active_checked}> <label for="active">{active}</label>
						<div id="active_d" class="caption">{active_desc}</div>
					</div>
					{upload}
					<div class="form_el1">
					{parent_select}
					</div>
					<div class="form_el1">
						<input id="cat_submit" type="submit"
							value="{submit}" class="btn_comment"> <input type="reset"
							value="{reset}" id="reset_cat" class="btn btn-m reset">
					</div>

				</div>
			</form>
		</div>

		<div class="yui3-u-2-3">
			<div class="cb catlist">
				<h3>{categories}</h3>
				<div class="info hide">{sort_manual}</div>
				<div class="cb fl cateditor">
				<ol class="sortable">
				{cat_list}
				</ol>
				</div>
				<div id="save_order"><input type="button" class="btn_comment hide" id="save_nested" value="{save}"></div>
			</div>
		</div>
	</div>';
}
