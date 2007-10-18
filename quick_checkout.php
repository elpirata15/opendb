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

include_once("./functions/email.php");
include_once("./functions/http.php");
include_once("./functions/utils.php");
include_once("./functions/borrowed_item.php");
include_once("./functions/item.php");
include_once("./functions/datetime.php");
include_once("./functions/item_attribute.php");
include_once("./functions/item_type.php");
include_once("./functions/widgets.php");
include_once("./functions/review.php");
include_once("./functions/scripts.php");
include_once("./functions/listutils.php");
include_once("./functions/status_type.php");
include_once("./functions/HTML_Listing.class.inc");
include_once("./functions/TitleMask.class.php");

function display_borrower_form($HTTP_VARS)
{
	// display owner_id input field.
	echo _theme_header(get_opendb_lang_var('item_quick_check_out'));
	echo("<h2>".get_opendb_lang_var('item_quick_check_out')."</h2>");
		
	// need to query for owner_id
	if(strlen($HTTP_VARS['borrower_id'])>0)
	{
		if(!is_user_valid($HTTP_VARS['borrower_id']) || !is_user_active($HTTP_VARS['borrower_id']))
		{
			echo(format_error_block(get_opendb_lang_var('invalid_borrower_user', 'user_id', $HTTP_VARS['borrower_id'])));
		}
		else // !is_user_allowed_to_borrow($HTTP_VARS['borrower_id']))
		{
			// display error because invalid user chosen.
			echo(format_error_block(get_opendb_lang_var('user_must_be_borrower', 'user_id', $HTTP_VARS['borrower_id'])));
		}
	}
		
	echo("\n<form action=\"$PHP_SELF\" method=\"GET\">");
	echo("\n<input type=\"hidden\" name=\"op\" value=\"checkout\">");
		
	echo("\n<table class=\"borrowerForm\">");
	if(get_opendb_config_var('borrow', 'admin_quick_checkout_borrower_lov')!==TRUE)
	{
		echo(get_input_field('borrower_id',
			NULL, // s_attribute_type
			get_opendb_lang_var('borrower'),
					"filtered(20,20,a-zA-Z0-9_.)", //input type.
					"Y", //compulsory!
			NULL,//value
			TRUE));
	}
	else
	{
		$results = fetch_user_rs(get_borrower_user_types_r(), NULL, "fullname", "ASC", FALSE, get_opendb_session_var('user_id'));
		if($results)
		{
			echo(
				format_field(get_opendb_lang_var('borrower'),
						NULL,
						custom_select('borrower_id', $results, '%fullname% (%user_id%)', 1, NULL, 'user_id')
					)
			);
		}
		else
		{
			echo(
				format_field(
					get_opendb_lang_var('borrower'),
					NULL,
					get_opendb_lang_var('no_records_found'))
				);
		}

	}
	echo("</table>");
		
	echo("<input type=\"submit\" value=\"".get_opendb_lang_var('continue')."\">");
	echo("</form>");
}

function is_item_instance_in_array($item_instance_r, $item_instance_rs) {
	if(is_array($item_instance_rs)) {
		reset($item_instance_rs);
		while(list(,$instance_r) = each($item_instance_rs)) {
			if($instance_r['item_id'] == $item_instance_r['item_id'] &&
					$instance_r['instance_no'] == $item_instance_r['instance_no']) {
				return TRUE;
			}
		}
	}
	
	//else
	return FALSE;
}

function get_new_checkout_item_instance_rs($alt_item_id, $checkout_item_instance_rs)
{
	$alt_item_id = trim($alt_item_id);
	
	$attribute_type = get_opendb_config_var('borrow.checkout', 'alt_id_attribute_type');
	if(strlen($attribute_type)>0) {
		$results = fetch_item_instance_for_attribute_val_rs($alt_item_id, $attribute_type);
		if($results) {
			$item_instance_rs = array();
			
			while($item_r = db_fetch_assoc($results)) {
				if(!is_item_instance_in_array($item_instance_r, $item_instance_rs) {
					$item_instance_rs[] = $item_r;
				}
			}
			db_free_result($results);
			
			return $item_instance_rs;
		}
	} else {
		if(preg_match("/([0-9]+)\.([0-9]+)/", $alt_item_id, $matches) ||
				preg_match("/([0-9]+)/", $alt_item_id, $matches))
		{
			$item_instance_r = array('item_id'=>$item_id, 'instance_no'=>$instance_no);
			
			if(!is_item_instance_in_array($item_instance_r, $item_instance_rs) {
				$item_instance_r = fetch_item_instance_r($item_instance_r['item_id'], $item_instance_r['instance_no']);
				if(is_array($item_instance_r))
				{
					$item_instance_rs[] = $item_instance_r;
					return $item_instance_rs;
				}
			}
		}
	}
	
	// item not found
	return FALSE;
}

function get_decoded_item_instance_rs($item_instance_list_r)
{
	$item_instance_rs = array();
	if(is_array($item_instance_list_r)) {
		reset($item_instance_list_r);
		while(list(,$item_id_and_instance_no) = each($item_instance_list_r)) {
			if(strlen($item_id_and_instance_no)>0) {
				$item_instance_r = get_item_id_and_instance_no($item_id_and_instance_no);
				if(is_not_empty_array($item_instance_r)) {
					$item_instance_r = fetch_item_instance_r($item_instance_r['item_id'], $item_instance_r['instance_no']);
					if(is_array($item_instance_r)) {
						$item_instance_rs[] = $item_instance_r;
					}
				}
			}
		}
	}
	return $item_instance_rs;
}

function update_checkout_item_instance_rs($HTTP_VARS, &$error)
{
	
	
	$item_instance_rs = get_new_checkout_item_instance_rs($alt_item_id, $checkout_item_instance_rs);
	if(is_array($item_instance_rs))
	{
		while(list(,$item_instance_r) = each($item_instance_rs))
		{
			if(!is_item_borrowed($item_instance_r['item_id'], $item_instance_r['instance_no']))
			{
				if($item_instance_r['owner_id'] != $HTTP_VARS['borrower_id'])
				{
					$status_type_r = fetch_status_type_r($item_instance_r['item_id'], $item_instance_r['instance_no']);
					if($status_type_r['borrow_ind'] == 'Y')
					{
						$checkout_item_instance_rs[] = $item_instance_r;
					}
					else
					{
						$error[] = get_opendb_lang_var('s_status_type_items_cannot_be_borrowed', 's_status_type_desc', $status_type_r['description']);
					}
				}
				else
				{
					$error[] = get_opendb_lang_var('user_is_owner_of_item');
				}
			}
			else
			{
				$error[] = get_opendb_lang_var('item_is_already_checked_out');
			}
		}//while
		
		// TODO - re-encode checkout_item_instance_rs
	}
	else
	{
		$error[] = get_opendb_lang_var('item_not_found');
	}
	
		//}
		//else
		//{
		//	$error = get_opendb_lang_var('item_is_already_selected');
		//}
//	}
	
	$item_reservation_rs = array();
	
	if(is_array($HTTP_VARS['checkout_item_instance_rs']))
	{
		while(list(,$item_id_and_instance_no) = each($HTTP_VARS['checkout_item_instance_rs']))
		{
			if(strlen($item_id_and_instance_no)>0)
			{
				$item_id_and_instance_no_r = get_item_id_and_instance_no($item_id_and_instance_no);
				if(is_not_empty_array($item_id_and_instance_no_r))
				$item_reservation_rs[] = fetch_item_instance_r($item_id_and_instance_no_r['item_id'], $item_id_and_instance_no_r['instance_no']);
			}
		}
	}
	
	return $item_reservation_rs;
}

if(is_site_enabled())
{
	if (is_opendb_valid_session())
	{
		if(is_user_admin(get_opendb_session_var('user_id'),get_opendb_session_var('user_type')))
		{
			if(get_opendb_config_var('borrow', 'enable')!==FALSE)
			{
				if($HTTP_VARS['op'] == 'checkout')
				{
					$admin_quick_check_out_error = NULL;
					
					if(strlen($HTTP_VARS['borrower_id'])==0 ||
							!is_user_valid($HTTP_VARS['borrower_id']) ||
							!is_user_active($HTTP_VARS['borrower_id']) ||
							!is_user_allowed_to_borrow($HTTP_VARS['borrower_id']))
					{
						display_borrower_form($HTTP_VARS);
					}
					else
					{
						$page_title = get_opendb_lang_var('item_quick_check_out_for_fullname', array('user_id'=>$HTTP_VARS['borrower_id'], 'fullname'=>fetch_user_name($HTTP_VARS['borrower_id'])));
							
						$item_checkout_instance_rs = update_item_checkout_instance_rs(
								get_decoded_item_instance_rs($HTTP_VARS['checkout_item_instance_rs']), 
								$admin_quick_check_out_error);

						//$HTTP_VARS['checkout_item_instance_rs'] = 
						//		get_encoded_item_instance_rs($item_checkout_instance_rs);
						
						$listingObject =& new HTML_Listing($PHP_SELF, $HTTP_VARS);
						$listingObject->setNoRowsMessage(get_opendb_lang_var('no_records_found'));
							
						if(is_numeric($listingObject->getItemsPerPage()))
						{
							// Get actual total
							$listingObject->setTotalItems(count($item_reservation_rs));
						}
							
						if(is_array($item_reservation_rs))
						{
							sort_item_listing(
								$item_reservation_rs,
								$listingObject->getCurrentOrderBy(),
								$listingObject->getCurrentSortOrder());

							// Now get the bit we actually want for this page.
							if(is_numeric($listingObject->getItemsPerPage()))
							{
								$item_reservation_rs = array_slice(
									$item_reservation_rs,
									$listingObject->getStartIndex(),
									$listingObject->getItemsPerPage());
							}

							// Ensure we are at the start of the array.
							if(is_array($item_reservation_rs))
								reset($item_reservation_rs);
						}
							
						echo(_theme_header($page_title));
						echo('<h2>'.$page_title.' '.$page_image.'</h2>');
							
						echo(get_listings_javascript());

						if(strlen($admin_quick_check_out_error)>0)
						{
							echo(format_error_block($admin_quick_check_out_error));
						}

						// include the input field and other processing here.
						echo("\n<table class=\"borrowerForm\">");
						echo("\n<form action=\"$PHP_SELF\" method=\"POST\">");
						echo("\n<input type=\"hidden\" name=\"op\" value=\"checkout\">");
						echo("\n<input type=\"hidden\" name=\"page_no\" value=\"\">");//dummy
						echo("\n<input type=\"hidden\" name=\"borrower_id\" value=\"".$HTTP_VARS['borrower_id']."\">");
							
						echo(get_input_field('item_instance',
						NULL, // s_attribute_type
						get_opendb_lang_var('item_id'),
										"number(10,10)", //input type.
										"N", //compulsory!
						NULL,//value
						TRUE));
						echo("\n</table>");
							
						echo("<input type=submit value=\"".get_opendb_lang_var('add_item')."\">");
							
						if(is_not_empty_array($HTTP_VARS['checkout_item_instance_rs']))
						{
							echo("<input type=button onclick=\"doFormSubmit(this.form, 'item_borrow.php', 'quick_check_out')\" value=\"".get_opendb_lang_var('check_out_item(s)')."\">");
							echo(get_url_fields(NULL, array('checkout_item_instance_rs'=>$HTTP_VARS['checkout_item_instance_rs'])));
						}
						echo("</form>");
							
						echo("<div id=\"adminQuickCheckOutListing\">");
						$listingObject->startListing($page_title);

						$listingObject->addHeaderColumn(get_opendb_lang_var('type'), 's_item_type');
						$listingObject->addHeaderColumn(get_opendb_lang_var('title'), 'title');
						$listingObject->addHeaderColumn(get_opendb_lang_var('owner'), 'owner');
							
						if(get_opendb_config_var('borrow', 'duration_support'))
						{
							$listingObject->addHeaderColumn(get_opendb_lang_var('borrow_duration'), 'borrow_duration', FALSE);
						}
							
						if(is_not_empty_array($item_reservation_rs))
						{
							while(list(,$borrowed_item_r) = each($item_reservation_rs))
							{
								$listingObject->startRow();
									
								$listingObject->addItemTypeImageColumn($borrowed_item_r['s_item_type']);
								$listingObject->addTitleColumn($borrowed_item_r);
								$listingObject->addUserNameColumn($borrowed_item_r['owner_id'], array('bi_sequence_number'=>$borrowed_item_r['sequence_number']));
									
								if(is_numeric($borrowed_item_r['borrow_duration']) && $borrowed_item_r['borrow_duration']>0)
								{
									$duration_attr_type_r = fetch_sfieldtype_item_attribute_type_r($borrowed_item_r['s_item_type'], 'DURATION');
									$listingObject->addDisplayColumn(
										$duration_attr_type_r['s_attribute_type'],
										NULL,
										$duration_attr_type_r['display_type'],
										$borrowed_item_r['borrow_duration']);
								}
								else
								{
									$listingObject->addColumn(get_opendb_lang_var('undefined'));
								}
									
								$listingObject->endRow();
							}
						}
							
						$listingObject->endListing();
						echo("</div>");
							
						echo("<ul class=\"listingControls\">");
						if(get_opendb_config_var('listings', 'allow_override_show_item_image')!==FALSE)
						{
							echo("<li>".getToggleControl(
								$PHP_SELF,
								$HTTP_VARS,
								get_opendb_lang_var('show_item_image'),
											'show_item_image', ifempty($HTTP_VARS['show_item_image'], 
								get_opendb_config_var('listings', 'show_item_image')==TRUE?'Y':'N'))."</li>");
						}
						echo("</ul>");
					}
				}
				else
				{
					echo _theme_header(get_opendb_lang_var('operation_not_available'));
					echo("<p class=\"error\">".get_opendb_lang_var('operation_not_available')."</p>");
				}
			}//borrow functionality disabled.
			else
			{
				echo _theme_header(get_opendb_lang_var('borrow_not_supported'));
				echo("<p class=\"error\">".get_opendb_lang_var('borrow_not_supported')."</p>");
				echo _theme_footer();
			}
		}//no non-admins allowed allowed!
		else if(is_site_public_access_enabled())
		{
			// provide login at this point
			redirect_login($PHP_SELF, $HTTP_VARS);
		}
		else
		{
			echo(_theme_header(get_opendb_lang_var('not_authorized_to_page')));
			echo("<p class=\"error\">".get_opendb_lang_var('not_authorized_to_page')."</p>");
			echo(_theme_footer());
		}
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