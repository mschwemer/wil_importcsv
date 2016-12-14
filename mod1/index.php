<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005 Oliver Weiß (weiss@carraldo.de)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Module 'Import CSV' for the 'wil_importcsv' extension.
 *
 * @author	Oliver Weiß <weiss@carraldo.de>
 */


	// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require ("conf.php");
require ($BACK_PATH."init.php");
$LANG->includeLLFile("EXT:wil_importcsv/mod1/locallang.xml");
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.
	// DEFAULT initialization of a module [END]

class tx_wilimportcsv_module1 extends t3lib_SCbase {
	var $pageinfo;
	var $table; //The table to import the data in
	var $pageTSConfig; //TS Config of the page

	var $insertArray = array();
	var $hiddenFileInput = '';
    /**
     * * @var array collected errors
     */
    protected $arErrors = array();


	/**
	 * @return	[type]		...
	 */
	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		parent::init();

		/*
		if (t3lib_div::_GP("clear_all_cache"))	{
			$this->include_once[]=PATH_t3lib."class.t3lib_tcemain.php";
		}
		*/
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	[type]		...
	 */
	function menuConfig()	{
		global $LANG;
		$this->MOD_MENU = Array (
			"function" => Array (
				"1" => $LANG->getLL("function1"),
				"2" => $LANG->getLL("function2"),
				"3" => $LANG->getLL("function3"),
			)
		);
		parent::menuConfig();
	}

		// If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	/**
	 * Main function of the module. Write the content to $this->content
	 *
	 * @return	[type]		...
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		$this->table = htmlspecialchars(t3lib_div::_GP("table"));
		$this->pageTSConfig = t3lib_BEfunc::getPagesTSconfig($this->id);

		$this->modconfarray = t3lib_BEfunc::getModTSconfig($this->id, 'mod.web_txwilimportcsvM1');
		$this->modconfarray = $this->modconfarray["properties"];

		if (!isset( $this->modconfarray['uniqueField'] ) )
			$this->modconfarray['uniqueField'] = 'uid';

		$this->uploadfilename = $_FILES ? $_FILES['filename']['name'] : t3lib_div::_GP("file");
		//v($_FILES['filename']['tmp_name']);
		// move the file in the tmp folder
		$this->uploadfile = t3lib_div::upload_to_tempfile($_FILES['filename']['tmp_name']);
		if ($this->uploadfile) {
		    $source = @fopen($this->uploadfile,"r");
		    while (!feof($source))   {
			$temp =fgetcsv ($source,10000,t3lib_div::_GP('seperator') );
			$this->hiddenFileInput .= '<input type="hidden" value="'.str_replace('"',"'",urlencode(serialize($temp))).'" name="line[]" />';
			$this->insertArray[] = $temp;
		    }
		    if (is_file($this->uploadfile))
			unlink($this->uploadfile);
		}
		else if (is_array(t3lib_div::_GP("line"))) {
		    foreach (t3lib_div::_GP("line") as $line)
			$this->insertArray[] = unserialize(urldecode(str_replace("'",'"',$line)));
		}


		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($BE_USER->user["admin"] && !$this->id))	{

				// Draw the header.
			$this->doc = t3lib_div::makeInstance("bigDoc");
			$this->doc->styleSheetFile2 = $GLOBALS["temp_modPath"].'/style.css';
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form='<form action="" method="POST" enctype="multipart/form-data">';

				// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{
						document.location = URL;
					}
				</script>
			';
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = '.intval($this->id).';
				</script>
			';

			$headerSection = $this->doc->getHeader("pages",$this->pageinfo,$this->pageinfo["_thePath"])."<br>".$LANG->sL("LLL:EXT:lang/locallang_core.xml:labels.path").": ".t3lib_div::fixed_lgd_cs($this->pageinfo["_thePath"],50);

			$this->content.=$this->doc->startPage($LANG->getLL("title"));
			$this->content.=$this->doc->header($LANG->getLL("title"));
			$this->content.=$this->doc->spacer(5);
			 /* $this->content.=$this->doc->section("",$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,"SET[function]",$this->MOD_SETTINGS["function"],$this->MOD_MENU["function"])));
			$this->content.=$this->doc->divider(5);  */
      $this->content.=$this->doc->section("",$this->doc->funcMenu($headerSection,""));
			$this->content.=$this->doc->divider(5);

			// Render content:
			$this->moduleContent();


			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section("",$this->doc->makeShortcutIcon("id",implode(",",array_keys($this->MOD_MENU)),$this->modconfarray["name"]));
			}

			$this->content.=$this->doc->spacer(10);
		} else {
				// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance("mediumDoc");
			$this->doc->backPath = $BACK_PATH;

			$this->content.=$this->doc->startPage($LANG->getLL("title"));
			$this->content.=$this->doc->header($LANG->getLL("title"));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	[type]		...
	 */
	function printContent()	{

		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content
	 *
	 * @return	[type]		...
	 */
	function moduleContent() {

		global $LANG,$GLOBALS,$TCA;
		if ($this->table)
			$this->TCA = $GLOBALS["TCA"][$this->table];
		if (is_array($this->TCA)) {
	        $overrideTCAConfig = $this->modconfarray["overrideTCAConfig."][$this->table.'.'];
	        foreach ($this->TCA["columns"] as $field => $val) {
	            $overrideTCAConfigfield = (is_array($overrideTCAConfig[$field . '.'])) ? $overrideTCAConfig[$field . '.'] : array();
	            $this->TCA["columns"][$field]["config"] = (is_array($this->TCA["columns"][$field]["config"])) ? $this->TCA["columns"][$field]["config"] : array();
				$this->TCA["columns"][$field]["config"] = array_merge($this->TCA["columns"][$field]["config"],$overrideTCAConfigfield);
	        }
	    }

		if (!$this->id)  //No page in the page tree selected
			$content.='<p>'.$LANG->getLL("instruction").'</p>';
		else {
			// Switch the action variable
			switch (t3lib_div::_GP("action")) {
				case 1: //Form filled and submitted
					if ($this->checkRequired()) //everything fine
					  $content.=$this->associateFields();
					else //Required Fields not filled
					  $content.=$this->showUploadForm();
				break;
				case 2: //JavaFunc: Type changed
					$content.=$this->associateFields();
				break;
				case 3: // Write Arrays and Preview
					$content.=$this->preview();
				break;
				case 4: //Write into the table
					//check, if the table should be cleared
					if (t3lib_div::_GP("truncate_table")) {
					    if (true !== $this->truncateTable(t3lib_div::_GP("table"))) {
						break;
					    }
					}
					$viewupdates = $this->writeData(t3lib_div::_GP("replaceArray"),1);
					$viewinserts = $this->writeData(t3lib_div::_GP("insertarray"),0);
					if (is_array ($tables)) {
						$content.='<h1>'.$LANG->getLL("headerForeignTables").'</h1>';
						foreach ($tables as $tablename=>$valarray) {
							$content.='<h2>'.$tablename.'</h2>
									<table style="border: 1px solid grey;">';
						foreach ($valarray as $uid=>$label)
							$content.='<tr><td style="background:#eee">'.$uid.'</td><td style="background:#fff">'.$label.'</td></tr>';
						$content.='</table>';
						}
					}
					if (is_array($viewinserts)) {
						$content.='<h1>'.$LANG->getLL("headerWritten").' '.$this->table.'</h1>';
						$content.='<table style="border: 1px solid grey;">';//writing the headerrow and
						foreach ($viewinserts[$GLOBALS['TYPO3_DB']->sql_insert_id()] as $key2=>$val2)
							$headerrow.='<th>'.$key2.'</th>';
						foreach ($viewinserts as $key=>$val) {
							$row.='<tr><td style="background:#eee">'.$key.'</td>';
							foreach ($viewinserts[$GLOBALS['TYPO3_DB']->sql_insert_id()] as $key2=>$val2) {
								$fieldval = strlen($val[$key2]) > 20 ? substr($val[$key2],0,20).'...' : $val[$key2];
								$row.='<td style="background:#fff">'.$fieldval.'</td>';
							}
							$row.='</tr>';
						}
						$content.='<tr><th>&nbsp;</th>'.$headerrow.'</tr>'.$row;
						unset($headerrow);
						unset($row);
						$content.='</table>';
						$content.='<p style="text-align: center; color: green;"><b>'.count($viewinserts).' '.$LANG->getLL("successWritten").'</b></p>';
					}
				//Now the Updated Rows
					if (is_array($viewupdates)) {
						$content.='<h1>'.$LANG->getLL("headerUpdate").' '.$this->table.'</h1>';
						$content.='<table style="border: 1px solid grey;">';//writing the headerrow and
						foreach ($viewupdates as $key=>$val) {
							$row.='<tr><td style="background:#eee">'.$key.'</td>';
							$headerrow = '';
							foreach ($val as $key2=>$val2)  {
								$headerrow.='<th>'.$key2.'</th>';
								$fieldval = strlen($val2) > 20 ? substr($val2,0,20).'...' : $val2;
								$row.='<td style="background:#fff">'.$fieldval.'</td>';
							}
							$row.='</tr>';
						}
						$row.='<tr><td style="background:#eee">'.$key.'</td>';
						$content.='<tr><th>'. $this->modconfarray['uniqueField'] .'</th>'.$headerrow.'</tr>'.$row;
						$content.='</table>';
						$content.='<p style="text-align: center; color: green;"><b>'.count($viewupdates).' '.$LANG->getLL("successUpdate").'</b></p>';
					}

				default: //Show the Upload Form
					$content.=$this->showUploadForm();
			}
		}


	//$content.=t3lib_div::view_array($array_in)
        $this->checkErrors();
		$this->content .= $content;
	}

	/**
	 * Shows the form for uploading
	 *
	 * @return	string		content
	 */
	function showUploadForm () {
		global $LANG,$BE_USER;
		//Instance of pageSelect class found in class.t3lib_page.php
		$page = t3lib_div::makeInstance('t3lib_pageSelect');
		$page->init(false); //initialize
		$page_arr=$page->getPage($this->id);
		$doktype=$page_arr["doktype"];

		if (!strcmp("ALL",$this->modconfarray['tables']) || !strcmp("IGNORE",$this->modconfarray['tables'])) { //Configuration allows all tables
			$result = mysql_list_tables(TYPO3_db);
			while ($row = mysql_fetch_row($result))
				$tables[$row[0]]=$row[0];
		}
		else { //Otherwise we take the tables as listed in the commalist
			$arr=explode(",",$this->modconfarray['tables']);
			foreach ($arr as $val)
				$tables[$val]=$val;
		}

		foreach ($tables as $val) {
			if ($this->isTableAllowedForThisPage($page_arr, $val)) {
			    $strTableName = $LANG->sL($GLOBALS['TCA'][$val]['ctrl']['title']);
			    $tables_ok[$val] = $strTableName . ' (' . $val . ')';
			}
		}
		if (is_array($tables_ok)) {
			$content.='<h3>'.$LANG->getLL('desc').'</h3>';
			$content.='<p>'.$LANG->getLL('instructionFile').'</p>';
			$content.='<table>
				<tr>
				  <td><label for="filename">'.$LANG->getLL("selectFile").'</label></td>
				  <td><input type="file" id="filename" name="filename" size="50" /></td>
				</tr>
				<tr>
				  <td><label for="seperator">'.$LANG->getLL("selectSeperator").'</label></td>
				  <td><input type="input" id="seperator" name="seperator" size="1" value="';
			$sep= t3lib_div::_GP("seperator") ? t3lib_div::_GP("seperator") : ';';
			$content.= $sep.'" /></td>
						</tr>
						<tr>
						  <td><label for="table">'.$LANG->getLL("selectTable").'</label></td>
                          <td>';
		if (!strcmp(count($tables_ok), 1)) //If there is only one table, we skip the selectorbox and select this one
		    	$content .=  reset($tables_ok).'<input type="hidden" name="table" value="'.key($tables_ok).'" />';
		else
			$content .= $this->selectInput("table",$tables_ok,$this->table);
		$content.='</td>
			    </tr>
			    <tr>
			      <td><label for="checkbox_truncate_table">'.$LANG->getLL("truncateTable").'</label></td>
			      <td><input type="checkbox" id="checkbox_truncate_table" name="truncate_table" /></td>
						</tr>
						<tr class="navigation_row">
						  <td>&nbsp;</td>
						  <td>
							<input type="hidden" name="action" value="1" />
							<input type="submit" onclick="return checkall();" value="'.$LANG->getLL("submitButton").'" />
						  </td>
						</tr>
					   </table>';
		}
		else
			$content.='<p>'.$LANG->getLL('noTableFound').'</p>';

		$content .= $this->hiddenFileInput;

		return $content;
	}

	/**
	 * Checks, if a table is allowed to fill data in
	 *
	 * @param	[type]		$pid_row: ...
	 * @param	[type]		$checkTable: ...
	 * @return	[type]		...
	 */
	function isTableAllowedForThisPage($pid_row, $checkTable){
		global $PAGES_TYPES, $BE_USER;
		if (!strcmp("IGNORE",$this->modconfarray['tables'])) // If "IGNORE" is set we just return
			return $checkTable;
		// Do we have the table in the db?
		$tables_pres = $GLOBALS['TYPO3_DB']->admin_get_tables() ;
		if (!array_key_exists($checkTable,$tables_pres))
			return false;

		// be_users and be_groups may not be created.
		if ($checkTable=='be_users' || $checkTable=='be_groups')
			return false;

		//Is the user allowed to modify the tables?
		if ($BE_USER->check('tables_modify',$checkTable)==0)
			return false;

		// Checking doktype:
		$doktype = intval($pid_row['doktype']);
		if (!$allowedTableList = $PAGES_TYPES[$doktype]['allowedTables'])
			$allowedTableList = $PAGES_TYPES['default']['allowedTables'];
		if (strstr($allowedTableList,'*') || t3lib_div::inList($allowedTableList,$checkTable))// If all tables or the table is listed as a allowed type, return true
			return true;
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function associateFields () {
		global $TCA, $LANG;
		$content.='<h3>'.$LANG->getLL('headerAssociate').'</h3>';
		$content.='<p>'.$LANG->getLL('instructionAssociate').'</p>';
		$type_field=$this->TCA["ctrl"]["type"];

		//v($this->TCA);
		//Selected Type:
		$seltype= $type_field ? $this->TCA["columns"][$type_field]["config"]["default"] : key($this->TCA["types"]);// Standard types. But we probably overwrite it in the next step.
	  //Selector of the different types according to type-Field. This type could be either an array defined in TCA or a foreign table.
		if (!strcmp(1,$this->modconfarray['selectTCAtype'])  && $type_field && (!strcmp(1,$this->modconfarray['ignoreTCARestriction']) || !strcmp(1,$GLOBALS['BE_USER']->check('non_exclude_fields',$this->table.":".$type_field))) ) { //only if set in conf.php and types are defined in TCA and (user is allowed to change the typefield or ignoreTCA is set in conf)
			$type_arr["NULL"]="";
		    $foreigntable = $this->TCA["columns"][$type_field]["config"]["foreign_table"];
			t3lib_div::loadTCA($foreigntable);
		    $label = $TCA[$foreigntable]["ctrl"]["label"];
			if ($foreigntable) { //Type is a foreign table
				$where = '1=1';
				$where .= t3lib_BEfunc::deleteClause($foreigntable);
				$res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery("uid,".$TCA[$foreigntable]["ctrl"]["label"],$foreigntable,$where);
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$type_arr[$row["uid"]] = $row[$TCA[$foreigntable]["ctrl"]["label"]]; //This is for creating the selectbox
					$type_uids[$row["uid"]] = $row["uid"]; //and this is for making all types possible later
				}
			}
			else { //Type is an array defined in TCA
				foreach ($this->TCA["columns"][$type_field]["config"]["items"] as $key => $val) {
					//if (!strcmp(1,$GLOBALS['BE_USER']->check('explicit_allowdeny',$table.":".$type_field.":".$val[1].":DENY"))) //We must filter the explicitly denied fields
					$type_arr[$val[1]] = $LANG->sL($val[0],1); //This is for creating the selectbox
					$type_uids[] = $key; //and this is for making all types possible later
				}
			}
			$addparams = '&seperator='.t3lib_div::_GP("seperator").'&table='.$this->table.'&action=2&file='.$this->uploadfilename;

			//Depending on the given values, we fill the variable $type
			if (strcmp('',t3lib_div::_GP("type")))//type variable was sent with gpvar
				$seltype=  t3lib_div::_GP("type");
			elseif (array_key_exists("default",$this->TCA["columns"][$type_field]["config"]))//there is a default value set in TCA
				$seltype= $this->TCA["columns"][$type_field]["config"]["default"];
			else //otherwise we just take the first one
				$seltype=key($this->TCA["types"]);
			$content.= '<h2>'.$LANG->getLL("headerType").'</h2>';
			$content.= '<p>'.$LANG->getLL("instructionType").'</p>';
			$content.= '<p>'.$LANG->sL(t3lib_BEfunc::getItemLabel($this->table,$type_field),1).
			t3lib_BEfunc::getFuncMenu ($this->id,"type",$seltype, $type_arr,$script = '',$addparams).'</p>';
		}
		// Now we can get the fields according to the selected type
		// If type selection is blank ($type=0) we create an array of all possible fields
		if (!strcmp($seltype,"NULL") && $type_uids) {
			$fields_arr = array();
			foreach ($type_uids as $val) {
			   $fields= explode(",",$this->TCA["types"][$val]["showitem"]);
			   $fields_arr = array_merge($fields_arr,$this->createFieldsArrayFromTCA($fields));
			}
			//v($TCA[$this->table]);
		}
		   // we need to merge this array while leaving double entries out
		else  {
			//v($TCA[$this->table]);
			$fields= explode(",",$this->TCA["types"][$seltype]["showitem"]);
			//v($fields);
			$fields_arr = $this->createFieldsArrayFromTCA($fields);
			//v($fields_arr);
			if (strcmp("",$this->TCA["ctrl"]["type"])) { //If we have a type value
				unset ($fields_arr[$this->TCA["ctrl"]["type"]]); //If type is selected above, we don't need it here, but we provide the value selected above
				$content.='<input type="hidden" name="typefield['.$this->TCA["ctrl"]["type"].']" value="'.$seltype.'" />';
			}
		}
		$content.='<table class="associate_table">
				  <tr>
					<th>'.$LANG->getLL("HeaderFile").$this->uploadfilename.'</th>
					<th>&nbsp;</th>
					<th>'.$LANG->getLL("HeaderTable").$this->table.'</th>
					<th>'.$LANG->getLL("HeaderSeperator").'</th>
				  </tr>';

	 /*  print_r ($TCA[$this->table]); */
		foreach ($this->insertArray[0] as $n => $val) {
			$class = ($n % 2 != 0) ? 'unequal' : 'equal';
			$content.='<tr class="'.$class.'">
						<td>'.$val.'</td>
						<td><img src="arrow-right.gif" /></td>
						<td>'.$this->selectInput("fields[".$n."]", $fields_arr, strtolower($val)).'</td>
						<td><input type="input" name="fieldseperator['.$n.']" size="1" value="'.t3lib_div::_GP("fieldseperator[".$n."]").'" /></td>
					 </tr>';
		}
		$content.='<tr height="20">
				  <td><a href="index.php?&id='.$this->id.'&table='.$this->table.'&seperator='.t3lib_div::_GP("seperator").'">'.$LANG->getLL("backButton").'</a></td>
				  <td>&nbsp;</td>
				  <td>
					<input type="submit" value="'.$LANG->getLL("previewButton").'" /></p>
					<input type="hidden" name="action" value="3" />
					<input type="hidden" name="table" value="'.$this->table.'" />
					<input type="hidden" name="file" value="'.$this->uploadfilename.'" />
					<input type="hidden" name="seperator" value="'.t3lib_div::_GP("seperator").'" />
                    <input type="hidden" name="truncate_table" value="'.t3lib_div::_GP("truncate_table").'" />
				  </td>
				</tr>
			  </table>';
	  /* if ($this->writeAddresses())
		  $this->status = "<br /><br /><font color='red'><strong>".$LANG->getLL("upSuccess")."</strong></font>"; */

		$content .= $this->hiddenFileInput;

		return $content;
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function preview() {
		global $LANG, $TCA,$TYPO3_CONF_VARS;
		$beCharset = $TYPO3_CONF_VARS["BE"]["forceCharset"] ? $TYPO3_CONF_VARS["BE"]["forceCharset"] : 'iso-8859-1';
		$specials = t3lib_div::_GP('specials');
		$fieldseperator = t3lib_div::_GP('fieldseperator');
		unset($this->insertArray[0]); //skip first line
		$nrzeile=1;
		$error=array();
		foreach ($this->insertArray as $temp) {
			// Looping through the lines of the file
			// Every value will be written in the array $insertfields
			$insertfields[$nrzeile]=array();
			$insertfields[$nrzeile]=t3lib_div::_GP('typefield');//this is the typefield, which comes from GP:Vars
			//$temp =fgetcsv ($source,10000,t3lib_div::_GP('seperator') );
			// $content.=t3lib_div::view_array($temp);
			$value=0;
			foreach (t3lib_div::_GP('fields') as $nrspalte => $field) { //$nrspalte: number of column in file $field: Field name of the table,
				if ($field) {
					$configfield = $this->TCA["columns"][$field]["config"];
                    if ($temp[$nrspalte]) //is there a value in the column?
						$value=1;
					$temp[$nrspalte] = trim(str_replace('"',"'",$temp[$nrspalte]));//remove the quotes
					$insertfields[$nrzeile][$field] = $temp[$nrspalte];  //we put the value in the array
					 //we put the value in the array
					if ($this->modconfarray['charSet'] &&  $this->modconfarray['charSet'] != $beCharset)
						$insertfields[$nrzeile][$field] = iconv ($this->modconfarray['charSet'], $beCharset, $temp[$nrspalte]);
                        //$insertfields[$nrzeile][$field] = utf8_encode($temp[$nrspalte]);
					else
						$insertfields[$nrzeile][$field] = $temp[$nrspalte];
					if (!strcmp("input",$configfield["type"])) { //input-field: we look for evaluation-fields
						$specials = explode(",",$configfield["eval"]);
                        $set = true;
						// if evaluation fields are set, we overwrite the array again.
                        foreach($specials as $func)    {
                            switch($func)   {
        						case "required":
                                    if (!strcmp("",$temp[$nrspalte])) {
                                        $set = false;
                                        $error[$nrzeile][$field] = $LANG->getLL("requiredmissing").': '.$temp[$nrspalte];
                                    }
                                break;
        						case "trim":
        						    $insertfields[$nrzeile][$field] = trim($insertfields[$nrzeile][$field]);
                                break;
        						case "date":
        							unset ($date);
        							if (strcmp($temp[$nrspalte],"")) {
        								if (substr_count($temp[$nrspalte],".") >= 2 )
        									$date=explode(".",$temp[$nrspalte]);
        								elseif (substr_count($temp[$nrspalte],"-") >= 2 )
        									$date=explode("-",$temp[$nrspalte]);
        								else
        									$error[$nrzeile][$field] = $LANG->getLL("wrongDateFormat");
        							}
        							$insertfields[$nrzeile][$field] = is_array($date) ? mktime(0,0,0,$date[1],$date[0],$date[2]) : "";
                                break;
        						/* Thanks to Björn Didwischus for datetime code */
        						case "datetime":
        							if (strcmp($temp[$nrspalte],"")) {
        								if (ereg ("([0-9]{1,2}).([0-9]{1,2}).([0-9]{4}) ([0-9]{1,2}):([0-9]{1,2})", $temp[$nrspalte], $regs)) {
        									$insertfields[$nrzeile][$field] = mktime($regs[4],$regs[5],0,$regs[2],$regs[1],$regs[3]);
        								} else if (ereg ("([0-9]{1,2}).([0-9]{1,2}).([0-9]{4})", $temp[$nrspalte], $regs)) {
        									$insertfields[$nrzeile][$field] = mktime(0,0,0,$regs[2],$regs[1],$regs[3]);
        								} else {
        									$error[$nrzeile][$field] = $LANG->getLL("wrongTimeFormat");
        									$insertfields[$nrzeile][$field] =  "";
        								}
        							}
                                break;
        						case "time":
        							unset ($time);
        							if (strcmp($temp[$nrspalte],"")) {
        								if (substr_count($temp[$nrspalte],":") >= 1)
        									$time=explode(":",$temp[$nrspalte]);
        								elseif (substr_count($temp[$nrspalte],".") >= 1)
        									$time=explode(".",$temp[$nrspalte]);
        								else
        									$error[$nrzeile][$field] = $LANG->getLL("wrongTimeFormat");
        							}
        							$insertfields[$nrzeile][$field] = is_array($time) ? ($time[0]*3600)+($time[1]*60) : "";
                                break;
                                case 'upper':
                                    $insertfields[$nrzeile][$field] = strtoupper($insertfields[$nrzeile][$field]);
                                break;
                                case 'lower':
                                    $insertfields[$nrzeile][$field] = strtolower($insertfields[$nrzeile][$field]);
                                break;
                                case 'nospace':
                                    $insertfields[$nrzeile][$field] = str_replace(' ','',$insertfields[$nrzeile][$field]);
                                break;
                                case 'alpha':
                                    $insertfields[$nrzeile][$field] = ereg_replace('[^a-zA-Z]','',$insertfields[$nrzeile][$field]);
                                break;
                                case 'num':
                                    $insertfields[$nrzeile][$field] = ereg_replace('[^0-9]','',$insertfields[$nrzeile][$field]);
                                break;
                                case 'alphanum':
                                    $insertfields[$nrzeile][$field] = ereg_replace('[^a-zA-Z0-9]','',$insertfields[$nrzeile][$field]);
                                break;
                                case 'alphanum_x':
                                    $insertfields[$nrzeile][$field] = ereg_replace('[^a-zA-Z0-9_-]','',$insertfields[$nrzeile][$field]);
                                break;
                                default:
                                    if (substr($func, 0, 3) == 'tx_')       {
                                            $evalObj = t3lib_div::getUserObj($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals'][$func].':&'.$func);
                                            if (is_object($evalObj) && method_exists($evalObj, 'evaluateFieldValue'))       {
                                                    $insertfields[$nrzeile][$field] = $evalObj->evaluateFieldValue($insertfields[$nrzeile][$field], $is_in, $set);
                                            }
                                    }
                                break;
                            }
                        }
                        if (!$set)
                            $insertfields[$nrzeile][$field] = '';
					}
					if (!strcmp("select",$configfield["type"])) {//select-field: get the int values
						if ($configfield["foreign_table"]) { //is it a reference to another table?
							if ($fieldseperator[$nrspalte]) //if we have a seperator, we explode
								$temp[$nrspalte] = explode($fieldseperator[$nrspalte], $temp[$nrspalte]);
							else //else we take a seperator, which will not occur
								$temp[$nrspalte] = explode('                                          ', $temp[$nrspalte]);
							foreach ($temp[$nrspalte] as $temp_key => $temp_val) {//Loop through the seperated (exploded) field
								$temp_val = trim($temp_val);
								if (strcmp("",$temp_val) && is_numeric($temp_val)) { //if it is numeric, we look if the uid exists in foreign table
									$where = 'uid='.$temp_val.' ';
									$where .= t3lib_BEfunc::deleteClause($configfield["foreign_table"]) . ' ';
									$where .= $configfield["foreign_table_where"];
									$search = array("###STORAGE_PID###","###CURRENT_PID###");
									$replace = array($this->pageinfo["storage_pid"],$this->id);
									$where =str_replace($search,$replace,$where);
									$res2 = $GLOBALS["TYPO3_DB"]->exec_SELECTquery("uid",$configfield["foreign_table"],$where);
									if (!$GLOBALS["TYPO3_DB"]->sql_num_rows($res2)>0)
										$error[$nrzeile][$field] = $LANG->getLL("noMatchingUIDFound");
								}
								if (strcmp("",$temp_val) && !is_numeric($temp_val)) { //if it is not an int, we try to get the integers
									$labelfield = $TCA[$configfield["foreign_table"]]["ctrl"]["label"];
									$where = $labelfield.' LIKE "%'.$temp_val.'%" ';
									$where .= t3lib_BEfunc::deleteClause($configfield["foreign_table"]) . ' ';
									$where .= $configfield["foreign_table_where"];
									$search = array("###STORAGE_PID###","###CURRENT_PID###");
									$replace = array($this->pageinfo["storage_pid"],$this->id);
									$where =str_replace($search,$replace,$where);
									$res2 = $GLOBALS["TYPO3_DB"]->exec_SELECTquery("uid",$configfield["foreign_table"],$where);
									$row2 = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res2);
									if (intval($row2["uid"])) //If we find a reference, we write the uid in the match array
										$match[$field][$nrzeile][$temp_key] = $row2["uid"];
									else { //if not, we write another array
										$nomatch[$field][$nrzeile][$temp_key] = $temp_val;
										$nomatch[$field]["table"] = $configfield["foreign_table"];
										$nomatch[$field]["foreignfield"] = $labelfield;
										$nomatch_unique[$field][$nrzeile.'-'.$temp_key] = $temp_val;
									}
								}
							}
						}
					}
					if (is_array($nomatch[$field]))
						$nomatch_unique[$field] = array_unique($nomatch_unique[$field]);
				}
			}
			if ($value==0) //no column contained any data
				$error[$nrzeile]["empty"]=$LANG->getLL("nothingToWrite");
			$nrzeile++;
		}
		/* $content.=t3lib_div::view_array($insertfields);
			   $content.=t3lib_div::view_array($nomatch);
			$content.="<hr>";*/
		// the last entry is always empty, so we can destroy it
		unset($insertfields[count($insertfields)]);
		$bInsertAll = (bool) t3lib_div::_GP("truncate_table");
		foreach ($insertfields as $nrspalte=>$field_array) { //Loop through insertfields, means: writing the lines. $nrspalte: number of column in file $field_array: array
			$tablerow.='<tr>';
			$tablerow.='<td style="background:#eee" >'.$nrspalte.'</td>';
			if ($error[$nrspalte]["empty"])
				$tablerow.= '<td colspan=99 style="color: red; background:#eee">'.$LANG->getLL("error").' '.$error[$nrspalte]["empty"].'</td>';

			/* Thanks to Andreas Weber for input */
			else {
				# if (!$bInsertAll && !empty($field_array['uid']) && $this->checkUidForReplacement($field_array['uid'],$this->table)) //is the field "uid" filled in and replacement is set, then replace
				$uniqueField = $this->modconfarray['uniqueField'];
				if (!$bInsertAll && !empty($field_array[$uniqueField]) && $this->checkUidForReplacement($field_array[$uniqueField],$this->table)) //is the field "uid" filled in and replacement is set, then replace
 					$arrayType = 'replaceArray';
				else
					$arrayType = 'insertarray';
				$message = 'OK';
				$messageColor = 'green';
				foreach ($field_array as $field=>$field_val) {//Loop: writing the fields in each line
					if ($arrayType=='replaceArray') {
						$message = $LANG->getLL("updateDataRow");
						$messageColor = 'orange';
					}
					else {
						$message = $LANG->getLL("newDataRow");
					}
					$background = '#fff';
					$num = "";
					if ($error[$nrspalte][$field]) { //Is there an error message for the field, we write it
						$tempfield = '<span style="color: red; ">'.$error[$nrspalte][$field].'</span>';
						$message = $LANG->getLL("error");
						$messageColor = 'red';
					}
					else {
						$tempfield='<table style="height:100%">';
						if (is_array($match[$field][$nrspalte])) { //is there a match in the field, we write the tempfield and add some hiddenfields
							foreach ($match[$field][$nrspalte] as $key => $match_value) { //the fields,
								$tempfield.='<tr><td>'.$match_value.'</td></tr>';
								$hiddenfields.='<input type="hidden" name="'.$arrayType.'['.$nrspalte.'][val]['.$field.']['.$key.']" value="'.$match_value.'" />';
							}
						}
						if (is_array($nomatch[$field][$nrspalte])) { //is there a nomatch in the field, we add tempfield and add some hiddenfields
							foreach ($nomatch[$field][$nrspalte] as $key => $nomatch_arr) {
								if ($nomatch_unique[$field][$nrspalte.'-'.$key]) {//completely new field
									$tempfield.='<tr><td style="background:#ffff33"><input type="checkbox" name="'.$arrayType.'['.$nrspalte.'][new]['.$nomatch[$field]["table"].']['.$key.']['.$nomatch[$field]["foreignfield"].']" value="'.$nomatch_arr.'" checked="checked" />'.$nomatch_arr.'</td></tr>';
									$hiddenfields.='<input type="hidden" name="'.$arrayType.'['.$nrspalte.'][ref]['.$field.']" value="'.$nomatch[$field]["table"].'" />'; // this hiddenfield is only for identifiying the field for writing in foreign table
								}
								else { //not found in foreigntable, but in array written before
									$num = array_search ($nomatch_arr,$nomatch_unique[$field]);
									$tempfield.='<tr><td style="background:#ffff99; color: #666">=>[row Nr. '.strtok($num, "-").']</td></tr>';
									$hiddenfields.='<input type="hidden" name="'.$arrayType.'['.$nrspalte.'][ref]['.$nomatch[$field]["table"].']['.$field.']['.$key.']" value="'.$nomatch_arr.'" />';
								}
							}
						}
						$tempfield.='</table>';
						if (!is_array($match[$field][$nrspalte]) && !is_array($nomatch[$field][$nrspalte])) {// Neither match nor nomatch is present, we write the field itself, but not longer than 50 characters long
							$field_val = htmlspecialchars($field_val);
							$tempfield = strlen($field_val) > 50 ? substr($field_val,0,50).'...' : $field_val;
							$hiddenfields.='<input type="hidden" name="'.$arrayType.'['.$nrspalte.'][val]['.$field.']" value="'.$field_val.'" />';
						}
						$endfield = '<td style="color: green; background:#eee">OK</td>';
					}
					$tablerow.='<td style="background:'.$background.'">'.$tempfield.'</td>';
				}
				$tablerow.= '<td style="color: '.$messageColor.'; background:#eee">'.$message.'</td>';
			}
			$tablerow.='</tr>';
		}
		if (is_array($nomatch))
			$content.='<p style="background:#ffff99">'.$LANG->getLL("foreignTableNotFound").'</p>';
		$content.='<h1>'.$LANG->getLL("preview").'</h1>
					<table style="border: 1px solid grey;">
					<tr><th>&nbsp;</th>';
		$typefield = t3lib_div::_GP('typefield');
		if (is_array($typefield))
			$content.='<th style="background:#FFF" >'.key($typefield).'</th>';
		foreach (t3lib_div::_GP('fields') as $val)
			if ($val)
				$content.='<th style="background:#FFF" >'.$val.'</th>';
		$content.='<th>&nbsp;</th></tr>';
		$content.=$tablerow;
		$content.='</table>
				<input type="hidden" name="action" value="4" />
				<input type="hidden" name="table" value="'.$this->table.'" />
				<input type="hidden" name="truncate_table" value="'.t3lib_div::_GP("truncate_table").'" />
				'.$hiddenfields.'
				<div style="float:left"> <a href="javascript:history.back()">'.$LANG->getLL("backButton").'</a></div>
				<div style="text-align:center">
				<input type="submit" value="'.$LANG->getLL("writeButton").'" /></div>';

		$content .= $this->hiddenFileInput;

		return $content;
    }

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function checkRequired() {
		global $LANG;
		if (!$_FILES) {
			$this->arErrors[] = $LANG->getLL("missingFile");
			return false;
		}
		if (!t3lib_div::_GP('seperator')) {
			$this->arErrors[] = $LANG->getLL("missingSeperator");
			return false;
		}
		if (!$this->table) {
			$this->arErrors[] = $LANG->getLL("missingTable");
			return false;
		}
		if (!$this->checkUpload())
			return false;

		// So far everything is fine
		return true;
	}


      /**
	* Procedure to upload the file into the folder csvaddrimport/uploads
	*
	* @return	bool		true if the file has been successfully uploaded, false elsewhere
	*/
	function checkUpload()	{
		global $LANG;
		// This is a security check: should not happen (blocked by Javascript before the post)
		/* if (!t3lib_div::_POST('syspages'))
		{
			$this->status="<br /><br /><font color='red'><strong>".$LANG->getLL("emptySyspages")."</strong></font>";
			return false;
		} */

		// Check for file extension (only CSV is allowed)
		if (!preg_match("/.csv\$/i", $_FILES['filename']['name'])) {
			$this->arErrors[] = $LANG->getLL("wrongMime");
			return false;
		}

		// Check for size
		if ($this->is_too_big($_FILES['filename']['size'])) {
			$this->arErrors[] = $LANG->getLL("fileTooBig");
			return false;
		}

		// Upload the file
		if(!$this->uploadfile)	{
			$this->arErrors[] = $LANG->getLL("upError");
			return false;
		}

		// So far everything is fine
		return true;
	}



/**
 * Create Fields from the TCA
 *
 * @return	unknown
 */
	function createFieldsArrayFromTCA($fields)    {
		$tableTSConfig = $this->pageTSConfig['TCEFORM.'][$this->table.'.'];
		global $LANG;
		$temp_arr = array();
		foreach ($fields as $val) {
			$val_arr=explode(";",$val);
			$val=trim($val_arr[0]);
			if ($val_arr[2]) { //palettes are defined -> we have to extend the array
				$pal_fields= explode(",",$this->TCA["palettes"][$val_arr[2]]["showitem"]);
				foreach ($pal_fields as $palval)
					$temp_arr[]=trim($palval);
			}
			$temp_arr[]=$val;
		}

		foreach ($temp_arr as $val)	{
			if (strcmp(1,$this->TCA["columns"][$val]["exclude"]) ||  $GLOBALS['BE_USER']->check('non_exclude_fields',$this->table.":".$val) || !strcmp(1,$this->modconfarray['ignoreTCARestriction'])) {//field is not exclude field or user is allowed to fill the exclude field of we ignore the TCA restriction
				if ($val=="--div--") //handle the --div-- and --palette-- specials
					$fields_arr[]= "---";
				elseif ($val!="--palette--")
					if (!$tableTSConfig[$val.'.']['disabled']) // we only write the field, if it is not disabled via TS Config
						$fields_arr[$val]= $LANG->sL(t3lib_BEfunc::getItemLabel($this->table,$val),1).'   ('.$val.')';
			}
		}

		// If "allowOverwriteRows" is set, we append the uid field
		if (!strcmp(1,$this->modconfarray['allowOverwriteRows'])) {
			# was $fields_arr["uid"] = $LANG->getLL("replaceUid");
			$fields_arr[$this->modconfarray['uniqueField']] = $LANG->getLL("replaceUid");
		}
		return $fields_arr;
	}


		/**
 * [Describe function...]
 *
 * @param	[type]		$name: ...
 * @param	[type]		$array: ...
 * @param	[type]		$selected: ...
 * @param	[type]		$size: ...
 * @param	[type]		$emptyItem: ...
 * @return	[type]		...
 */
  function selectInput($name, $array, $selected=""){
		$out .= '<select name="'.$name.'" >';
		$out .= '<option value="">&nbsp;</option>';
		foreach($array as $value => $label) {
			$sel = ((string)$value == (string)$selected)?" selected='true'":"";
			$out .= '<option value="'.$value.'" '.$sel.'>'.$label.'</option>';
		}
		$out .= '</select>';

		return $out;
	}




	/**
 * Function to verify if a source file is too big to be imported
 * Reads the limit from the configuration file
 *
 * @param	int		$filesize        dimension of the source file
 * @return	bool		true if the dimension is less than the limit, false elsewhere
 */
	function is_too_big($filesize)	{
		return $filesize > $this->modconfarray['maxsize'];
	}


     /**
 * Function to verify if the mime type of the source file is allowed
 * Reads the allowed mime list from the configuration file
 *
 * @param	string		$mime        Mime type of the source file
 * @return	bool		True if the mime type is allowed, false elsewhere
 */
	function checkUidForReplacement($field_val,$table) {
		$uniqueField = $this->modconfarray['uniqueField'];
		# was: $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery("uid",$table,'uid='.$field_val);
		$res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery($uniqueField,$table,$uniqueField.'='.$field_val);
		if ($GLOBALS["TYPO3_DB"]->sql_num_rows($res)>0)
			return true;
		else
			return false;

	}

	 /**
 * Write the Data array in the table
 *
 * @param	array		Array to insert
 * @param	bool		0: Insert new, 1: Update
 * @return	array		Array to view the results
 */

	function writeData($insertarray,$type) {
		//t3lib_div::print_array($insertarray);
		if (is_array($insertarray)) {
			foreach ($insertarray as $insertfields) {
				$mm_table = array();
				$insertfields["val"]["pid"] = $this->id;
				if ($this->TCA["ctrl"]["tstamp"]) $insertfields["val"][$this->TCA["ctrl"]["tstamp"]] = time();
				//New entries in foreign tables
				if (strcmp("",$insertfields["new"])) {
					foreach ($insertfields["new"] as $key=>$val) { //$key: name of foreigntable, $val:array
						$field = array_search($key,$insertfields["ref"]); // get the reference field of local table
						foreach ($val as $val2) { // $val2:array:field, $value
							$val2["pid"] = $this->id;
							if ($this->TCA["ctrl"]["tstamp"]) $val2[$this->TCA["ctrl"]["tstamp"]] = time();
							if ($this->TCA["ctrl"]["crdate"]) $val2[$this->TCA["ctrl"]["crdate"]] = time();
							$GLOBALS['TYPO3_DB']->exec_INSERTquery($key,$val2); //write the new value in the foreign table
							$insertfields["val"][$field][] = $GLOBALS['TYPO3_DB']->sql_insert_id(); // extend the array with the values with the id just written
							if ($GLOBALS['TYPO3_DB']->sql_insert_id())
								$tables[$key][$GLOBALS['TYPO3_DB']->sql_insert_id()] = array_shift ($val2); //we save all new entries in this array $tables for later (search, output)
							unset ($insertfields["ref"][$field]);
						}
					}
				}
			 // $content.=t3lib_div::view_array($tables);
				//References to foreign tables just written before
				if (strcmp("",$insertfields["ref"]))
					foreach ($insertfields["ref"] as $key=>$val) { //$key: foreign table, $val: array
						if (is_array($val)) {//maybe we have deselected the field in the preview mode
							foreach ($val as $key2=>$val2) { // $key2: local field, $val2: array
								foreach ($val2 as $val3) // $key2: local field, $val2: value
									$insertfields["val"][$key2][] = array_search($val3,$tables[$key]);
							}
						}
					}

				//If there is a uid field this has to be removed, because we never write in this field. But we keep the value for later.
				#$uid = $insertfields["val"]["uid"];
				#unset ($insertfields["val"]["uid"]);
				$uniqueField = $this->modconfarray['uniqueField'];
				$uniqueFieldValue =  $insertfields["val"][$uniqueField];
				unset ($insertfields["val"][$uniqueField]);
				// Felder bei foreigntables
				foreach ($insertfields["val"] as $key=>$val) {
				    if (is_array($val)) {
						$insertfields["val"][$key] = implode(",",$val);
				    }
					// MM Relations
					if ($this->TCA["columns"][$key]["config"]["MM"]) {
						$mm_foreign_field = explode(",",$insertfields["val"][$key]);
						$mm_table[$key] = array("mm_table" => $this->TCA["columns"][$key]["config"]["MM"],
												"mm_foreign_uid" => $mm_foreign_field) ;
						$insertfields["val"][$key] = count($mm_foreign_field);
					}

				}
				if (!strcmp(0,$type)) {
				    if ($this->TCA["ctrl"]["crdate"])
					$insertfields["val"][$this->TCA["ctrl"]["crdate"]] = time();
					// if $uniqueField is cnum or email etc. $uniqueField  has to written in new
				    if($uniqueField != 'uid')
						$insertfields["val"][$uniqueField] = $uniqueFieldValue;
					// bei mm-tabellen wird die anzahl der relationen geschrieben
				    $GLOBALS['TYPO3_DB']->exec_INSERTquery($this->table,$insertfields["val"]);
				} else
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->table,$uniqueField.'='.$uniqueFieldValue,$insertfields["val"]);

				if ($strError = $GLOBALS['TYPO3_DB']->sql_error()) //Messages
				    $this->arErrors[] = $strError;
				else {
					if (!strcmp(0,$type))
						$id= $GLOBALS['TYPO3_DB']->sql_insert_id();
					else
						$id = $uniqueFieldValue;
					$viewinserts[$id]=$insertfields["val"]; // Only to display the table

					// MM Relations
					foreach ($mm_table as $val) {
						// Löschen der alten Einträge
						$GLOBALS['TYPO3_DB']->exec_DELETEquery($val["mm_table"],'uid_local = '.$id);
						foreach ($val["mm_foreign_uid"] as $key=>$foreign_id)
							$GLOBALS['TYPO3_DB']->exec_INSERTquery($val["mm_table"],array("uid_local" => $id,
																						  "uid_foreign" => $foreign_id,
																						  "sorting" => $key));

					}
				}
			}
		}
		//t3lib_div::print_array($insertarray);

		return $viewinserts;
	}


   /**
     * Truncates a table
     *
     * @param string $strTable name of table
     *
     * @return boolean success
     */
    private function truncateTable($strTable)    {
        return $GLOBALS['TYPO3_DB']->sql_query(
            'TRUNCATE TABLE `' . $GLOBALS['TYPO3_DB']->quoteStr($strTable, $strTable) . '`'
        );
    }



    /**
     * Adds collected error messages to the content output
     *
     * @return void
     */
    private function checkErrors()    {
        foreach ($this->arErrors as $strError) {
            $this->content
                .= '<p style="color: red; font-weight: bold;">' . $strError . '</p>';
        }
    }

}




if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wil_importcsv/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wil_importcsv/mod1/index.php']);
}




// Make instance:
$SOBE = t3lib_div::makeInstance('tx_wilimportcsv_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>