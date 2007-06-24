<?php
/* 	
	Open Media Collectors Database
	Copyright (C) 2001,2006 by Jason Pell

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

// This must be first - includes config.php
require_once("./include/begin.inc.php");

include_once("./functions/database.php");
include_once("./functions/auth.php");
include_once("./functions/logging.php");

include_once("./functions/utils.php");
include_once("./functions/datetime.php");
include_once("./functions/http.php");
include_once("./functions/user.php");
include_once("./functions/review.php");
include_once("./functions/borrowed_item.php");
include_once("./functions/item.php");
include_once("./functions/widgets.php");
include_once("./functions/item_type.php");
include_once("./functions/listutils.php");
include_once("./functions/status_type.php");
include_once("./functions/export.php");
include_once("./functions/scripts.php");
include_once("./functions/item_attribute.php");
include_once("./functions/TitleMask.class.php");
include_once("./functions/item_display.php");
include_once("./functions/site_plugin.php");

if(is_site_enabled())
{
	if (is_opendb_valid_session())
	{
		if(is_numeric($HTTP_VARS['instance_no']))
			$item_r = fetch_item_instance_r($HTTP_VARS['item_id'], $HTTP_VARS['instance_no']);
	
		if(is_not_empty_array($item_r))
		{
			if($item_r['owner_id'] == get_opendb_session_var('user_id') || 
								is_item_instance_viewable($item_r['s_status_type'], $error))
			{
			    $titleMaskCfg = new TitleMask('item_display');

			    $page_title = $titleMaskCfg->expand_item_title($item_r);
				echo _theme_header($page_title, $HTTP_VARS['inc_menu']);
				
				echo(get_popup_javascript());
				echo(get_common_javascript());
				echo(get_tabs_javascript());
				
				echo ("<h2>".$page_title." ".get_item_image($item_r['s_item_type'], $item_r['item_id'])."</h2>");
                
				// ---------------- Display IMAGE attributes ------------------------------------
				// Will bypass the display of images if the config.php get_opendb_config_var('item_display', 'show_item_image')
				// variable has been explicitly set to FALSE.  This means that if the variable does 
				// not exist, this block should still execute.
				if(get_opendb_config_var('item_display', 'show_item_image')!==FALSE)
				{
					// Here we need to get the image_attribute_type and check if it is set.
					$results = fetch_item_attribute_type_rs($item_r['s_item_type'], 'IMAGE');
					if($results)
					{
						$coverimages_rs = NULL;
						
						while($image_attribute_type_r = db_fetch_assoc($results))
						{
							$imageurl = fetch_attribute_val($item_r['item_id'], $item_r['instance_no'], $image_attribute_type_r['s_attribute_type'], $image_attribute_type_r['order_no']);

							$imageurl = get_file_attribute_url($item_r['item_id'], $item_r['instance_no'], $image_attribute_type_r, $imageurl);
							
							// If an image is specified.
							if(strlen($imageurl)>0)
							{
								$coverimages_rs[] = array(
									'file'=>file_cache_get_image_r($imageurl, 'display'),
									'prompt'=>$image_attribute_type_r['prompt']);
							}//if(strlen($imageurl)>0)
						}
						db_free_result($results);
						
						// provide default if no images
						if($coverimages_rs == NULL)
						{
							$coverimages_rs[] = array('file'=>file_cache_get_image_r(NULL, 'display'));
						}
						
						echo("<ul class=\"coverimages\">");
						while(list(,$coverimage_r) = each($coverimages_rs))
						{
							echo("<li>");
							$file_r = $coverimage_r['file'];
							
							if(strlen($file_r['fullsize']['url'])>0)
							{
								// a dirty hack!
								if(starts_with($file_r['url'], 'file://'))
								{
									$parsed_r = parse_upload_file_url($file_r['url']);
									$file_r['url'] = $parsed_r['filename'];
								}
								
								echo("<a href=\"".$file_r['url']."\" onclick=\"popup('".$file_r['fullsize']['url']."', 400, 300); return false;\">");
							}
							echo("<img src=\"".$file_r['thumbnail']['url']."\" border=0 title=\"".htmlspecialchars($coverimage_r['prompt'])."\" ");
							
							if(is_numeric($file_r['thumbnail']['width']))
								echo(' width="'.$file_r['thumbnail']['width'].'"');
							if(is_numeric($file_r['thumbnail']['height']))
								echo(' height="'.$file_r['thumbnail']['height'].'"');
							
							echo(">");
							if(strlen($file_r['fullsize']['url'])>0)
							{
								echo("</a>");
							}
							echo("</li>");
						}
						echo("</ul>");
					}
				}
				
				$cfgIsTabbedLayout = get_opendb_config_var('item_display', 'tabbed_layout');
				
				$otherTabsClass="tabContent";
				if($cfgIsTabbedLayout!==FALSE)
				{
					$otherTabsClass="tabContentHidden";
				}
				
				echo("<div class=\"tabContainer\">");
				if($cfgIsTabbedLayout!==FALSE)
				{
					echo("<ul class=\"tabMenu\" id=\"tab-menu\">");
					echo("<li id=\"menu-details\" class=\"activeTab\" onclick=\"return activateTab('details', 'tab-menu', 'tab-content', 'activeTab', 'tabContent')\">".get_opendb_lang_var('details')."</li>");
					echo("<li id=\"menu-instance_info\" onclick=\"return activateTab('instance_info', 'tab-menu', 'tab-content', 'activeTab', 'tabContent')\">".get_opendb_lang_var('instance_info')."</li>");
					echo("<li id=\"menu-reviews\" onclick=\"return activateTab('reviews', 'tab-menu', 'tab-content', 'activeTab', 'tabContent')\">".get_opendb_lang_var('review(s)')."</li>");
					echo("</ul>");
				}
								
				echo("<div id=\"tab-content\">");
				
				echo("<div class=\"tabContent\" id=\"details\">");
				
				$average = fetch_review_rating($item_r['item_id']);
				if($average!==FALSE)
				{
					echo("<p class=\"rating\">");
					echo (get_opendb_lang_var('rating').": ");
					$attribute_type_r = fetch_attribute_type_r('S_RATING');
					echo get_display_field(
							$attribute_type_r['s_attribute_type'],
							NULL,
							'review()',
							$average,
							FALSE);
					echo("</p>");
				}
				else
				{
					echo("<p class=\"norating\">".get_opendb_lang_var('no_rating')."</p>");	
				}
				
				// Do all the attributes.  Ignore any attributes that have an input_type of hidden.
				$results = fetch_item_attribute_type_rs($item_r['s_item_type'], 'not_instance_field_types');
				if($results)
				{
					echo("<table>");
					while($item_attribute_type_r = db_fetch_assoc($results))
					{
						// If display_type == '' AND input_type == 'hidden' we set to 'hidden'
						$display_type = trim($item_attribute_type_r['display_type']);
						
						if(($HTTP_VARS['mode'] == 'printable' && $item_attribute_type_r['printable_ind'] != 'Y') ||
								(strlen($display_type)==0 && $item_attribute_type_r['input_type'] == 'hidden'))
						{
							// We allow the get_display_field to handle hidden variable, in case at some stage
							// we might want to change the functionality of 'hidden' to something other than ignore.
							$display_type = 'hidden';
						}
						
						if($item_attribute_type_r['s_field_type'] == 'ITEM_ID')
							$value = $item_r['item_id'];
						else if(is_multivalue_attribute_type($item_attribute_type_r['s_attribute_type']))
							$value = fetch_attribute_val_r($item_r['item_id'], $item_r['instance_no'], $item_attribute_type_r['s_attribute_type'], $item_attribute_type_r['order_no']);
						else
							$value = fetch_attribute_val($item_r['item_id'], $item_r['instance_no'], $item_attribute_type_r['s_attribute_type'],  $item_attribute_type_r['order_no']);

						// Only show attributes which have a value.
						if(is_not_empty_array($value) || (!is_array($value) && strlen($value)>0))
						{
							$item_attribute_type_r['display_type'] = $display_type;
							
							$field = get_item_display_field(
									$item_r,
									$item_attribute_type_r,
									$value,
									FALSE);

							if(strlen($field)>0)
							{
								echo "\n<tr><th class=\"prompt\" scope=\"row\">".$item_attribute_type_r['prompt'].":</th>".
									"<td class=\"data\">".$field."</td></tr>";
							}
						}
					}
					db_free_result($results);
					
					echo("\n</table>");
				}
				
				// ---------------------Site Link Block -----------------------
				echo(get_site_plugin_links($page_title, $item_r));
				// -------------------------------------------------------------
				
				echo("</div>");
				
				echo("<div class=\"$otherTabsClass\" id=\"instance_info\">");
				echo(get_instance_info_block($item_r));
				echo get_related_items_block($item_r, $HTTP_VARS);
				echo("</div>");
			
				echo("<div class=\"$otherTabsClass\" id=\"reviews\">");
				echo(get_item_review_block($item_r));
				echo("</div>"); // end of review
				
				echo("</div>"); // end of tab content
				echo("</div>");  // end of tabContainer
			}
			else
			{
				echo _theme_header($error);
				echo("<p class=\"error\">".$error."</p>");
			}
		}
		else
		{
			echo _theme_header(get_opendb_lang_var('item_not_found'));
			echo("<p class=\"error\">".get_opendb_lang_var('item_not_found')."</p>");
		}//$item_r found
		
		if(is_export_plugin(get_opendb_config_var('item_display', 'export_link')))
		{
			$footer_links_r[] = array(url=>"export.php?op=export&plugin=".get_opendb_config_var('item_display', 'export_link')."&item_id=".$item_r['item_id']."&instance_no=".$item_r['instance_no'], text=>get_opendb_lang_var('type_export_item_record', 'type', get_display_export_type(get_opendb_config_var('item_display', 'export_link'))));
		}
			
		// Include a Back to Listing link.
		if($HTTP_VARS['listing_link'] === 'y' && is_array(get_opendb_session_var('listing_url_vars')))
		{
			$footer_links_r[] = array(url=>"listings.php?".get_url_string(get_opendb_session_var('listing_url_vars')),text=>get_opendb_lang_var('back_to_listing'));
		}
	
		echo(format_footer_links($footer_links_r));
		
		echo _theme_footer();
	}
	else
	{
		// invalid login, so login instead.
		redirect_login($PHP_SELF, $HTTP_VARS);
	}
}//if(is_site_enabled())
else
{
	echo _theme_header(get_opendb_lang_var('site_is_disabled'), FALSE);
	echo("<p class=\"error\">".get_opendb_lang_var('site_is_disabled')."</p>");
	echo _theme_footer();
}

// Cleanup after begin.inc.php
require_once("./include/end.inc.php");
?>
