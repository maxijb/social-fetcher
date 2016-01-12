<?php
/*
Plugin Name: Social Fetcher
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: The Plugin's Version Number, e.g.: 1.0
Author: Name Of The Plugin Author
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/

/*  Copyright YEAR  PLUGIN_AUTHOR_NAME  (email : PLUGIN AUTHOR EMAIL)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require "facebook-social-fetcher.php";
require "youtube-social-fetcher.php";
require "flickr-social-fetcher.php";


error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);



$object = new SocialFetcher();

//add a hook into the admin header to check if the user has agreed to the terms and conditions.
add_action('admin_head',  array($object, 'adminHeader'));

//add footer code
add_action( 'admin_footer',  array($object, 'adminFooter'));

// Hook for adding admin menus
add_action('admin_menu',  array($object, 'addMenu'));

//This will create [yourshortcode] shortcode
add_shortcode('yourshortcode', array($object, 'shortcode'));

class SocialFetcher{

    private $yt_account_options_key = "social-fetcher-yt-account";
    private $fr_account_options_key = "social-fetcher-fr-account";
   


    public function adminHeader() {
      return "HEADER";
    }

    /**
     * This will create a menu item under the option menu
     * @see http://codex.wordpress.org/Function_Reference/add_options_page
     */
    public function addMenu(){
        wp_register_style( 'SocialFetcher', plugins_url('stylesheet.css', __FILE__) );
        add_options_page('Social Fetcher Options', 'Social Fetcher', 'manage_options', 'social-fetcher-options', array($this, 'optionPage'));
        add_options_page('Social Fetcher Manage Posts', 'Social Fetcher Posts', 'manage_options', 'social-fetcher-manage-posts', array($this, 'managePosts'));
        add_options_page('Social Fetcher Load Data Now', 'Social Fetcher Posts Load', 'manage_options', 'social-fetcher-load-posts', array($this, 'loadPosts'));
    }

    /**
     * This is where you add all the html and php for your option page
     * @see http://codex.wordpress.org/Function_Reference/add_options_page
     */
    public function optionPage(){
        $fbFetcher = new FacebookSocialFetcher();
        
        //Validate change of YT account
        if ($_POST['yt-account']) {
            update_option($this->yt_account_options_key, $_POST['yt-account']);
        }

        //Validate change of YT account
        if ($_POST['fr-account']) {
            update_option($this->fr_account_options_key, $_POST['fr-account']);
        } 
        ?>
        <div class="wrap">
         <div id="icon-options-general"></div>
         <h2><?php _e( ' Theme Options' ) //your admin panel title ?></h2>
         <ul>
             <li>
                Facebook 
                
                <a class='button button-primary' href="<?=$fbFetcher->getLoginUrl()?>"> <? _e("Link and auth account")?></a>
             </li>
             <li>Flickr 
                <form action="" method="POST">
                    <input type='text' id='fr-account' name="fr-account" value="<?=get_option( $this->fr_account_options_key, "" )?>"/>
                    <input type="submit" value="<? _e("Link and update id")?>" class="button button-primary">
                </form>
             </li>
             <li>Youtube
                <form action="" method="POST">
                    <input type='text' id='yt-account' name="yt-account" value="<?=get_option( $this->yt_account_options_key, "" )?>"/>
                    <input type="submit" value="<? _e("Link and update account")?>" class="button button-primary">
                </form>
             </li>
         </ul>
         <p><span>Theme version</span></p>
         
     </div>
     <?
    }


    public function managePosts(){

        $type = isset($_GET[sftype]) ? $_GET[sftype] : "yt-video";
        $offset = isset($_GET[offset]) ? $_GET[offset] : 0;
        $selected = 'selected="selected"';
        $pagination = 20;
        ?>
        <h2>Manage social posts: 
            <form method="GET" action="" style="display:inline">
                <input type='hidden' name="page" value="social-fetcher-manage-posts" />
                <select id="social-type" name="sftype" onchange="this.parentNode.submit()">
                    <option value="yt-video" <? if ($type == "yt-video") echo $selected ?>>Youtube</option>
                    <option value="fr-pic" <? if ($type == "fr-pic") echo $selected ?>>Flickr</option>
                    <option value="fb-post" <? if ($type == "fb-post") echo $selected ?>>Facebook</option>
                </select>
            </form>
        </h2>

        <?
// SELECT SQL_CALC_FOUND_ROWS * FROM tbl_name
//     -> WHERE id > 100 LIMIT 10;
// mysql> 
        $ids = array();
        $items = array();
        $meta = array();
        $result = mysql_query("SELECT SQL_CALC_FOUND_ROWS * FROM wp_posts 
        WHERE post_type = '$type' ORDER BY post_date DESC LIMIT $offset, $pagination");
        while ($r = mysql_fetch_assoc($result)) {
            array_push($ids, $r[ID]);
            array_push($items, $r);
        }

        //total results
        $totalResults = mysql_fetch_row(mysql_query("SELECT FOUND_ROWS()"))[0];

        //meta

        $consulta = "SELECT post_id, meta_key, meta_value FROM
        wp_postmeta b WHERE post_id IN (".join(', ', $ids).")";
        $results = mysql_query($consulta);
        while ($r = mysql_fetch_assoc($results)) {
            if (empty($meta[$r[post_id]])) $meta[$r[post_id]] = array();
            $meta[$r[post_id]][$r[meta_key]] = $r[meta_value];
        }
        ?>
            <style type="text/css">
                 #social_fecther_table {
                    border-collapse: collapse;
                    width: 100%;
                    margin-right: 10px;
                 }
                #social_fecther_table td {
                    padding: 10px;
                    border: 1px #ccc solid;
                    text-align: center;
                }
                #social_fecther_table td.description {
                    text-align: left;
                }
                #social_fecther_table td img {
                    max-width: 120px;
                }
                #social_fetcher_pagination {
                    width: 100%;
                    clear: both;
                    margin: 20px 0 0 0;
                }
                #social_fetcher_pagination .pagination {
                    padding: 5px 10px;
                    border: 1px #666 solid;
                    background: #ddd;
                }
                #social_fetcher_pagination .pagination.disabled {
                    background: white;
                    border: 0;
                }


            </style>

        <?


        echo "<table id='social_fecther_table'>";
        echo "<tr><th>ID</th><th>Pic</th><th>Description</th><th>Actions</th></tr>";
        foreach ($items as $item) {
            $id = $item[ID];
            ?>
            <tr>
                <td>
                    <?=$id?>
                </td>
                <td>
                    <? if (!empty($meta[$id]['small-pic'])) echo "<img class='thumb' src='".$meta[$id]['small-pic']."' />"; ?>
                </td>
                <td class='description'>
                    <? if (!empty($item[post_title])) echo "<h4>".$item[post_title]."</h4>"; ?>
                    <? if (!empty($item[post_content])) echo "<div>".substr($item[post_content], 0, 100)."...</div>"; ?>
                </td>
                <td>
                </td>
            </tr>
            <?
        }
        echo "</table>
        <div id='social_fetcher_pagination'>";

        $curUrl = "http://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        $index = strpos($curUrl, "&offset=");
        if ($index !== false) {
            $curUrl = substr($curUrl, 0, $index);
        }
        $maxPage = $totalResults / $pagination;
        $actual = $offset / $pagination + 1;
        for ($i = 1; $i <= $maxPage; $i++) {
            if ($i - $actual < 5  && $i - $actual > -5) {

                $nextOffset = ($i - 1) * $pagination;
                if ($i != $actual) echo "<a class='pagination' href='$curUrl&offset=$nextOffset'>$i</a>";
                else echo "<span class='pagination disabled'>$i</span>";
            }
        }
        echo "</div>";

    }

    public function loadPosts(){
        // echo "add your option page html here or include another php file";
        $frFetcher = new FlickrSocialFetcher();
        $frFetcher->fetchData();
        $ytFetcher = new YoutubeSocialFetcher();
        $ytFetcher->fetchData();
        $fbFetcher = new FacebookSocialFetcher();
        $fbFetcher->fetchData();
    }

    /**
     * this is where you add the code that will be returned wherever you put your shortcode
     * @see http://codex.wordpress.org/Shortcode_API
     */
    public function shortcode(){
        return "add your image and html here...";
    }
}
?>