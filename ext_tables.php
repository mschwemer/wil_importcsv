<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (TYPO3_MODE=="BE")	{

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule("web","txwilimportcsvM1","",\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY)."mod1/");
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
	# ***************************************************************************************
	# CONFIGURATION of extension "CSV Import"
	# ***************************************************************************************
#  Max size allowed
mod.web_txwilimportcsvM1.maxsize = 50000
#  Which tables should be possible? Komma seperated list. If set to "ALL", it will allow all tables, which the user has rights for and are allowed on the page type. If set to "IGNORE", it will also ignore the user and page type restriction, but this is not recommended.
mod.web_txwilimportcsvM1.tables = tt_content,tt_calender,tt_news,tt_address
#  Should "types" defined in the TCA be possible to select (1) or should we take the default value (0)?
mod.web_txwilimportcsvM1.selectTCAtype = 1
#  If you set this to 1, you ignore all user restriction settings and allow everything for the selected tables. Be aware that it is a very, very dangerous thing, especially if you combine it with a "IGNORE" in tables settings above and let unexperienced users to work with it.
mod.web_txwilimportcsvM1.ignoreTCARestriction = 0
#  If you set this to 1, you allow replacing data in the table, which means that you can define a column for "uid" and the system will overwrite the row with this value. Make sure that the table has an unique column named "uid".
mod.web_txwilimportcsvM1.allowOverwriteRows = 0
  ');



}
?>
