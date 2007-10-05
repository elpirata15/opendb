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
class XMLImportPluginHandler
{
	var $importPlugin;
	var $fileHandler;

	// We want to send startElement name, attribs and any PCDATA as a single unit.
	var $_startElementName;
	var $_startElementAttribs;
	var $_characterData;

	// stores the first error encountered.
	var $_error;

	function XMLImportPluginHandler(&$importPlugin, &$fileHandler)
	{
		$this->importPlugin =& $importPlugin;
		$this->fileHandler =& $fileHandler;
	}

	function handleImport()
	{
		$parser = xml_parser_create('');
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, FALSE);
		xml_set_object($parser, $this);
		xml_set_element_handler($parser, "_start_element", "_end_element");
		xml_set_character_data_handler($parser, "_characters");

		while (($data = $this->fileHandler->readLine())!==FALSE)
		{
			if(!xml_parse($parser, $data, $this->fileHandler->isEof()))
			{
				$this->_error = get_opendb_lang_var('xml_error', array('xml_error_string'=>xml_error_string(xml_get_error_code($parser)), 'xml_error_line'=>xml_get_current_line_number($parser)));
				return FALSE;
			}
		}

		xml_parser_free($parser);

		return TRUE;
	}

	function _start_element($parser, $name, $attribs)
	{
		// if any character data waiting to be sent, send it now.
		if(strlen($this->_startElementName)>0)
		{
			$this->importPlugin->start_element(
								$this->_startElementName,
								$this->_startElementAttribs,
								trim($this->_characterData));
		}

		$this->_startElementName = $name;
		$this->_startElementAttribs = $attribs;
		$this->_characterData = NULL;
	}

	function _end_element($parser, $name)
	{
		// if any character data waiting to be sent, send it now.
		if(strlen($this->_startElementName)>0)
		{
			$this->importPlugin->start_element(
								$this->_startElementName,
								$this->_startElementAttribs,
								trim($this->_characterData));

			$this->_startElementName = NULL;
			$this->_startElementAttribs = NULL;
			$this->_characterData = NULL;
		}

		$this->importPlugin->end_element($name);
	}

	function _characters($parser, $data)
	{
		$this->_characterData .= $data;
	}

	function getError()
	{
		return $this->_error;
	}
}
?>