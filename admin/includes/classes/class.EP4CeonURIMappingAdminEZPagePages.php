<?php

/**
 * Ceon URI Mapping Zen Cart EZ-Pages Admin Functionality.
 *
 * This file contains a class which handles the functionality for EZ-page pages within the Zen Cart admin.
 *
 * @package     ceon_uri_mapping
 * @author      Conor Kerr <zen-cart.uri-mapping@ceon.net>
 * @copyright   Copyright 2008-2012 Ceon
 * @copyright   Copyright 2003-2007 Zen Cart Development Team
 * @copyright   Portions Copyright 2003 osCommerce
 * @link        http://ceon.net/software/business/zen-cart/uri-mapping
 * @license     http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version     $Id: class.CeonURIMappingAdminEZPagePages.php 1054 2012-09-22 15:45:15Z conor $
 */

if (!defined('IS_ADMIN_FLAG')) {
	die('Illegal Access');
}

/**
 * Load in the parent class if not already loaded
 */
require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.CeonURIMappingAdminEZPages.php');


// {{{ CeonURIMappingAdminEZPagePages

/**
 * Handles the URI mappings for EZ-pages.
 *
 * @package     ceon_uri_mapping
 * @author      Conor Kerr <zen-cart.uri-mapping@ceon.net>
 * @copyright   Copyright 2008-2012 Ceon
 * @copyright   Copyright 2003-2007 Zen Cart Development Team
 * @copyright   Portions Copyright 2003 osCommerce
 * @link        http://ceon.net/software/business/zen-cart/uri-mapping
 * @license     http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
class EP4CeonURIMappingAdminEZPagePages extends CeonURIMappingAdminEZPages
{
	// {{{ properties
		
	/**
	 * Maintains a copy of the current URI mappings entered/generated for the EZ-page.
	 *
	 * @var     array
	 * @access  protected
	 */
	var $_uri_mappings = array();
	
	/**
	 * Maintains a copy of any previous URI mappings for the EZ-page.
	 *
	 * @var     array
	 * @access  protected
	 */
	var $_prev_uri_mappings = array();
	
	/**
	 * Flag indicates whether or not auto-generation of URI mappings has been selected for the EZ-page.
	 *
	 * @var     boolean
	 * @access  protected
	 */
	var $_uri_mapping_autogen = false;
	
	/**
	 * Maintains a list of any URI mappings for the EZ-page which clash with existing mappings.
	 *
	 * @var     array
	 * @access  protected
	 */
	var $_clashing_mappings = array();
	
	// }}}
	
	
	// {{{ Class Constructor
	
	/**
	 * Creates a new instance of the EP4CeonURIMappingAdminEZPagePages class.
	 * 
	 * @access  public
	 */
	function EP4CeonURIMappingAdminEZPagePages()
	{
		// Load the language definition file for the current language
		@include_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/' . 'ceon_uri_mapping_ezpage_pages.php');
		
		if (!defined('CEON_URI_MAPPING_TEXT_EZPAGES_URI') && $_SESSION['language'] != 'english') {
			// Fall back to english language file
			@include_once(DIR_WS_LANGUAGES . 'english/' . 'ceon_uri_mapping_ezpage_pages.php');
		}
		
		parent::CeonURIMappingAdminEZPages();
	}
	
	// }}}
	
	
	// {{{ insertUpdateHandler()
	
	/**
	 * Handles the Ceon URI Mapping functionality when an EZ-page is being inserted or updated.
	 *
	 * @access  public
	 * @param   integer   $page_id   The ID of the EZ-page.
	 * @param   integer   $page_title   The name for the EZ-Page.
	 * @param   array     $page_titles_array   The names for the EZ-Page for the languages used by the store.
	 * @return  none
	 */
	function insertUpdateHandler($page_id, $page_title, $prev_uri_mappings, $uri_mappings, $page_titles_array = null, $uri_mapping_autogen = NULL)
	{
		global $messageStack;
		
		$uri_mapping_autogen = (isset($uri_mapping_autogen) ? true : false);
		
		$languages = zen_get_languages();
		
		for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
			$prev_uri_mapping = trim($prev_uri_mappings[$languages[$i]['id']]);

			$uri_mapping_autogen = ((!zen_not_null($prev_uri_mapping) && EP4_AUTOCREATE_EZ_FROM_BLANK == '1') || EP4_AUTORECREATE_EZ_EXISTING == '1' || (EP4_AUTORECREATE_EZ_EXISTING == '2' && (EP4_AUTOCREATE_EZ_FROM_BLANK == '1' || (EP4_AUTOCREATE_EZ_FROM_BLANK == '0' && zen_not_null($prev_uri_mapping)))));
			
			// Handle multilanguage EZ-Pages 
			if (!is_null($page_titles_array)) {
				$page_title = $page_titles_array[$languages[$i]['id']];
			}
			
			// Auto-generate the URI if requested
			if ($uri_mapping_autogen) {
				$uri_mapping = $this->autogenEZPageURIMapping((int) $page_id, $page_title,
					$languages[$i]['code'], $languages[$i]['id']);

				if (EP4_AUTOCREATE_EZ_FROM_BLANK == '0' && (EP4_AUTORECREATE_EZ_EXISTING == '2') && !zen_not_null($prev_uri_mapping)) {
					//Cycle through languages, where previous is not blank, current = previous
					$uri_mapping = $prev_uri_mapping;
				}

				if (EP4_AUTOCREATE_EZ_FROM_BLANK == '1' && (EP4_AUTORECREATE_EZ_EXISTING == '0') && zen_not_null($prev_uri_mapping)) {
					//Cycle through languages, where previous is not blank, current = previous
					$uri_mapping = $prev_uri_mapping;
				}
				
				if ($uri_mapping == CEON_URI_MAPPING_GENERATION_ATTEMPT_FOR_EZ_PAGE_WITH_NO_NAME) {
					// Can't generate the URI because of missing "uniqueness" data
					$failure_message = sprintf(CEON_URI_MAPPING_TEXT_ERROR_AUTOGENERATION_EZ_PAGE_HAS_NO_NAME,
						ucwords($languages[$i]['name']));
					
//					$messageStack->add_session($failure_message, 'error');
					
					continue;
				}
			} else {
				$uri_mapping = $uri_mappings[$languages[$i]['id']];
			}
			
			if (strlen($uri_mapping) > 1) {
				// Make sure URI mapping is relative to root of site and does not have a trailing slash or any
				// illegal characters
				$uri_mapping = $this->_cleanUpURIMapping($uri_mapping);
			}
			
			$insert_uri_mapping = false;
			$update_uri_mapping = false;
			
			if ($uri_mapping != '') {
				// Check if the URI mapping is being updated or does not yet exist
				if ($prev_uri_mapping == '') {
					$insert_uri_mapping = true;
				} else if ($prev_uri_mapping != $uri_mapping) {
					$update_uri_mapping = true;
				}
			}
			
			if ($insert_uri_mapping || $update_uri_mapping) {
				if ($update_uri_mapping && EP4_EXPORT_EZ_ONLY != 1) {
					// Consign previous mapping to the history, so old URI mapping isn't broken
					$this->makeURIMappingHistorical($prev_uri_mapping, $languages[$i]['id']);
				}
				
				// Add the new URI mapping
				$uri = $uri_mapping;
				
				$main_page = FILENAME_EZPAGES;
				
				if (EP4_EXPORT_EZ_ONLY == 1) {
					$uri_mappings[$languages[$i]['id']] = $uri_mapping;
				}
				
				if (EP4_EXPORT_EZ_ONLY != 1){
				$mapping_added =
					$this->addURIMapping($uri, $languages[$i]['id'], $main_page, null, $page_id);
				
				if ($mapping_added == CEON_URI_MAPPING_ADD_MAPPING_SUCCESS) {
					if ($insert_uri_mapping) {
						$success_message = sprintf(CEON_URI_MAPPING_TEXT_EZ_PAGE_MAPPING_ADDED,
							ucwords($languages[$i]['name']),
							'<a href="' . HTTP_SERVER . $uri . '" target="_blank">' . $uri . '</a>');
					} else {
						$success_message = sprintf(CEON_URI_MAPPING_TEXT_EZ_PAGE_MAPPING_UPDATED,
							ucwords($languages[$i]['name']),
							'<a href="' . HTTP_SERVER . $uri . '" target="_blank">' . $uri . '</a>');
					}

					$uri_mappings[$languages[$i]['id']] = $uri_mapping;

					$messageStack->add_session($success_message, 'success');
					
				} else {
					if ($mapping_added == CEON_URI_MAPPING_ADD_MAPPING_ERROR_MAPPING_EXISTS) {
						$failure_message = sprintf(CEON_URI_MAPPING_TEXT_ERROR_ADD_MAPPING_EXISTS,
							ucwords($languages[$i]['name']),
							'<a href="' . HTTP_SERVER . $uri . '" target="_blank">' . $uri . '</a>');
						
					} else if ($mapping_added == CEON_URI_MAPPING_ADD_MAPPING_ERROR_DATA_ERROR) {
						$failure_message = sprintf(CEON_URI_MAPPING_TEXT_ERROR_ADD_MAPPING_DATA,
							ucwords($languages[$i]['name']), $uri);
					} else {
						$failure_message = sprintf(CEON_URI_MAPPING_TEXT_ERROR_ADD_MAPPING_DB,
							ucwords($languages[$i]['name']), $uri);
					}
					
					//$messageStack->add_session($failure_message, 'error');
				}}
			} else if ($prev_uri_mapping != '' && $uri_mapping == '') {
				// No URI mapping, consign existing mapping to the history, so old URI mapping isn't broken
				if (EP4_EXPORT_EZ_ONLY != 1) {
					$this->makeURIMappingHistorical($prev_uri_mapping, $languages[$i]['id']);
				
				$success_message = sprintf(CEON_URI_MAPPING_TEXT_EZ_PAGE_MAPPING_MADE_HISTORICAL,
					ucwords($languages[$i]['name']));
					//$messageStack->add_session($success_message, 'caution');
				}

				$uri_mappings[$languages[$i]['id']] = $uri_mapping;
			}
		}
		return $uri_mappings;
	}
	
	// }}}
	
	
	// {{{ deleteConfirmHandler()
	
	/**
	 * Handles the Ceon URI Mapping functionality when an EZ-page is being deleted.
	 *
	 * @access  public
	 * @param   integer   $page_id   The ID of the EZ-page.
	 * @return  none
	 */
	function deleteConfirmHandler($page_id)
	{
		$selections = array(
			'main_page' => FILENAME_EZPAGES,
			'associated_db_id' => (int) $page_id
			);
		
		$this->deleteURIMappings($selections);
	}
	
	// }}}
	
	
	// {{{ configureEnvironment()
	
	/**
	 * Simply configures the Ceon URI Mapping variables when the EZ-Page admin page is being displayed/used.
	 *
	 * @access  public
	 * @return  none
	 */
	function configureEnvironment($ezID = NULL, $prev_uri_mappings = NULL, $uri_mappings = NULL, $uri_mapping_autogen = NULL)
	{
		// Get any current EZ-Page URI mappings from the database if necessary
		if (isset($ezID) && true /*empty($_POST)*/) {
			$columns_to_retrieve = array(
				'language_id',
				'uri'
				);
			
			$selections = array(
				'main_page' => FILENAME_EZPAGES,
				'associated_db_id' => (int) $ezID,
				'current_uri' => 1
				);
			
			$prev_uri_mappings_result = $this->getURIMappingsResultset($columns_to_retrieve, $selections);
			
			while (!$prev_uri_mappings_result->EOF) {
				$this->_prev_uri_mappings[$prev_uri_mappings_result->fields['language_id']] =
					$prev_uri_mappings_result->fields['uri'];
				
				$prev_uri_mappings_result->MoveNext();
			}
			
			$this->_uri_mappings = $this->_prev_uri_mappings;
			
		} else {
			// Use the URI mappings passed in the POST variables
			$this->_prev_uri_mappings = $prev_uri_mappings;
			$this->_uri_mappings = $uri_mappings;
			
			$this->_uri_mapping_autogen = (isset($uri_mapping_autogen) ? true : false);
		}
		return $this->_uri_mappings;
	}
	
	// }}}
	
	
	// {{{ buildEZPageURIMappingFields()
	
	/**
	 * Builds the input fields for adding/editing URI mappings for EZ-Pages.
	 *
	 * @access  public
	 * @return  string   The source for the input fields.
	 */
	function buildEZPageURIMappingFields()
	{
		$languages = zen_get_languages();
		
		$num_uri_mappings = sizeof($this->_uri_mappings);
		
		$num_languages = sizeof($languages);
		
		$uri_mapping_input_fields .= '<tr>
				<td colspan="2">' . zen_draw_separator('pixel_trans.gif', '1', '10') . '</td>
			  </tr>
			  <tr>
				<td colspan="2">' . zen_draw_separator('pixel_black.gif', '100%', '2') . '</td>
			  </tr>
			  <tr>
				<td class="main" valign="top" style="padding-top: 0.5em;">' .
				CEON_URI_MAPPING_TEXT_EZ_PAGE_URI . '</td>
				<td class="main" style="padding-top: 0.5em; padding-bottom: 0.5em;">' . "\n";
		
		for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
			$uri_mapping_input_fields .= "<p>";
			
			if (!isset($this->_prev_uri_mappings[$languages[$i]['id']])) {
				$this->_prev_uri_mappings[$languages[$i]['id']] = '';
			}
			
			if (!isset($this->_uri_mappings[$languages[$i]['id']])) {
				$this->_uri_mappings[$languages[$i]['id']] = '';
			}
			
			$uri_mapping_input_fields .= zen_draw_hidden_field('prev-uri-mappings[' . $languages[$i]['id'] . ']',
				$this->_prev_uri_mappings[$languages[$i]['id']]);
			
			$uri_mapping_input_fields .= zen_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] .
				'/images/' . $languages[$i]['image'], $languages[$i]['name']) . '&nbsp;' .
				zen_draw_input_field('uri-mappings[' . $languages[$i]['id'] . ']',
				$this->_uri_mappings[$languages[$i]['id']], 'size="60"');
			
			$uri_mapping_input_fields .= "</p>\n";
		}
		
		$uri_mapping_input_fields .= "<p>";
		
		if ($this->_autogenEnabled()) {
			if ($num_languages == 1) {
				$autogen_message = CEON_URI_MAPPING_TEXT_EZ_PAGE_URI_AUTOGEN;
			} else {
				$autogen_message = CEON_URI_MAPPING_TEXT_EZ_PAGE_URIS_AUTOGEN;
			}
			
			if ($num_uri_mappings == 0) {
				$autogen_selected = true;
			} else {
				$autogen_selected = false;
				
				if ($num_uri_mappings == 1) {
					$autogen_message .= '<br />' . CEON_URI_MAPPING_TEXT_URI_AUTOGEN_ONE_EXISTING_MAPPING;
				} else if ($num_uri_mappings == $num_languages) {
					$autogen_message .= '<br />' . CEON_URI_MAPPING_TEXT_URI_AUTOGEN_ALL_EXISTING_MAPPINGS;
				} else {
					$autogen_message .= '<br />' . CEON_URI_MAPPING_TEXT_URI_AUTOGEN_SOME_EXISTING_MAPPINGS;
				}
			}
			
			if (!$autogen_selected && $this->_uri_mapping_autogen) {
				$autogen_selected = true;
			}
			
			$uri_mapping_input_fields .=
				zen_draw_checkbox_field('uri-mapping-autogen', '1', $autogen_selected) . ' ' . $autogen_message;
		} else {
			$uri_mapping_input_fields .= CEON_URI_MAPPING_TEXT_URI_AUTOGEN_DISABLED;
		}
		
		$uri_mapping_input_fields .= "</p>";
		
		$uri_mapping_input_fields .= "\n\t\t</td>\n\t</tr>";
		
		$uri_mapping_input_fields .= '<tr>
				<td colspan="2">' . zen_draw_separator('pixel_black.gif', '100%', '2') . '</td>
			  </tr>';
		
		return $uri_mapping_input_fields;
	}
	
	/// }}}
}

// }}}
