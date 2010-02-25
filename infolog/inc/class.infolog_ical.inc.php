<?php
/**
 * InfoLog - iCalendar Parser
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @package infolog
 * @subpackage syncml
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/lib/core.php';

/**
 * InfoLog: Create and parse iCal's
 *
 */
class infolog_ical extends infolog_bo
{
	/**
	 * @var array $priority_egw2ical conversion of the priority egw => ical
	 */
	var $priority_egw2ical = array(
		0 => 9,		// low
		1 => 5,		// normal
		2 => 3,		// high
		3 => 1,		// urgent
	);

	/**
	 * @var array $priority_ical2egw conversion of the priority ical => egw
	 */
	var $priority_ical2egw = array(
		9 => 0,	8 => 0, 7 => 0,	// low
		6 => 1, 5 => 1, 4 => 1, 0 => 1,	// normal
		3 => 2,	2 => 2,	// high
		1 => 3,			// urgent
	);

	/**
	 * @var array $priority_egw2funambol conversion of the priority egw => funambol
	 */
	var $priority_egw2funambol = array(
		0 => 0,		// low
		1 => 1,		// normal
		2 => 2,		// high
		3 => 2,		// urgent
	);

	/**
	 * @var array $priority_funambol2egw conversion of the priority funambol => egw
	 */
	var $priority_funambol2egw = array(
		0 => 0,		// low
		1 => 1,		// normal
		2 => 3,		// high
	);

	/**
	 * manufacturer and name of the sync-client
	 *
	 * @var string
	 */
	var $productManufacturer = 'file';
	var $productName = '';

	/**
	* Shall we use the UID extensions of the description field?
	*
	* @var boolean
	*/
	var $uidExtension = false;

	/**
	 * user preference: use server timezone for exports to device
	 *
	 * @var boolean
	 */
	var $useServerTZ = false;

	/**
	 * Client CTCap Properties
	 *
	 * @var array
	 */
	var $clientProperties;

	/**
	 * Set Logging
	 *
	 * @var boolean
	 */
	var $log = false;
	var $logfile="/tmp/log-infolog-vcal";


	/**
	 * Constructor
	 *
	 * @param array $_clientProperties		client properties
	 */
	function __construct(&$_clientProperties = array())
	{
		parent::__construct();
		if ($this->log) $this->logfile = $GLOBALS['egw_info']['server']['temp_dir']."/log-infolog-vcal";
		$this->clientProperties = $_clientProperties;
	}

	/**
	 * Exports one InfoLog tast to an iCalendar VTODO
	 *
	 * @param int $_taskID info_id
	 * @param string $_version='2.0' could be '1.0' too
	 * @param string $_method='PUBLISH'
	 * @return string/boolean string with vCal or false on error (eg. no permission to read the event)
	 */
	function exportVTODO($_taskID, $_version='2.0',$_method='PUBLISH')
	{
		if ($this->useServerTZ)
		{
			$date_format = 'server';
		}
		else
		{
			$date_format = 'ts';
		}
		if (!($taskData = $this->read($_taskID, true, $date_format))) return false;

		if ($taskData['info_id_parent'])
		{
			$parent = $this->read($taskData['info_id_parent']);
			$taskData['info_id_parent'] = $parent['info_uid'];
		}
		else
		{
			$taskData['info_id_parent'] = '';
		}

		if ($this->uidExtension)
		{
			if (!preg_match('/\[UID:.+\]/m', $taskData['info_des']))
			{
				$taskData['info_des'] .= "\n[UID:" . $taskData['info_uid'] . "]";
				if ($taskData['info_id_parent'] != '')
				{
					$taskData['info_des'] .= "\n[PARENT_UID:" . $taskData['info_id_parent'] . "]";
				}
			}
		}

		if (!empty($taskData['info_cat']))
		{
			$cats = $this->get_categories(array($taskData['info_cat']));
			$taskData['info_cat'] = $cats[0];
		}

		$taskData = $GLOBALS['egw']->translation->convert($taskData,
			$GLOBALS['egw']->translation->charset(), 'UTF-8');


		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($taskData)."\n",3,$this->logfile);
		}

		$vcal = new Horde_iCalendar;
		$vcal->setAttribute('VERSION',$_version);
		$vcal->setAttribute('METHOD',$_method);

		if ($this->useServerTZ)
		{
			$event = array('start' => $taskData['info_startdate'] ? $taskData['info_startdate'] : time());
			$serverTZ = calendar_ical::generate_vtimezone($event, $vcal);
		}
		else
		{
			$serverTZ = false;
		}

		$vevent = Horde_iCalendar::newComponent('VTODO',$vcal);

		if (!isset($this->clientProperties['SUMMARY']['Size']))
		{
			// make SUMMARY a required field
			$this->clientProperties['SUMMARY']['Size'] = 0xFFFF;
			$this->clientProperties['SUMMARY']['NoTruncate'] = false;
		}
		// set fields that may contain non-ascii chars and encode them if necessary
		foreach (array(
					'SUMMARY'     => $taskData['info_subject'],
					'DESCRIPTION' => $taskData['info_des'],
					'LOCATION'    => $taskData['info_location'],
					'RELATED-TO'  => $taskData['info_id_parent'],
					'UID'		  => $taskData['info_uid'],
					'CATEGORIES'  => $taskData['info_cat'],
				) as $field => $value)
		{
			if (isset($this->clientProperties[$field]['Size']))
			{
				$size = $this->clientProperties[$field]['Size'];
				$noTruncate = $this->clientProperties[$field]['NoTruncate'];
				if ($this->log && $size > 0)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
						"() $field Size: $size, NoTruncate: " .
						($noTruncate ? 'TRUE' : 'FALSE') . "\n",3,$this->logfile);
				}
				//Horde::logMessage("VTODO $field Size: $size, NoTruncate: " .
				//	($noTruncate ? 'TRUE' : 'FALSE'), __FILE__, __LINE__, PEAR_LOG_DEBUG);
			}
			else
			{
				$size = -1;
				$noTruncate = false;
			}
			$cursize = strlen($value);
			if (($size > 0) && $cursize > $size)
			{
				if ($noTruncate)
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
							"() $field omitted due to maximum size $size\n",3,$this->logfile);
					}
					//Horde::logMessage("VTODO $field omitted due to maximum size $size",
					//	__FILE__, __LINE__, PEAR_LOG_WARNING);
					continue; // skip field
				}
				// truncate the value to size
				$value = substr($value, 0, $size -1);
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
						"() $field truncated to maximum size $size\n",3,$this->logfile);
				}
				//Horde::logMessage("VTODO $field truncated to maximum size $size",
				//	__FILE__, __LINE__, PEAR_LOG_INFO);
			}

			if (empty($value) && ($size < 0 || $noTruncate)) continue;

			if ($field == 'RELATED-TO')
			{
				$options = array('RELTYPE'	=> 'PARENT');
			}
			else
			{
				$options = array();
			}

			if (preg_match('/[^\x20-\x7F]/', $value))
			{
				$options['CHARSET']	= 'UTF-8';
				switch ($this->productManufacturer)
				{
					case 'groupdav':
						if ($this->productName == 'kde')
						{
							$options['ENCODING'] = 'QUOTED-PRINTABLE';
						}
						else
						{
							$options['CHARSET'] = '';

							if (preg_match('/([\000-\012\015\016\020-\037\075])/', $value))
							{
								$options['ENCODING'] = 'QUOTED-PRINTABLE';
							}
							else
							{
								$options['ENCODING'] = '';
							}
						}
						break;
					case 'funambol':
						$options['ENCODING'] = 'FUNAMBOL-QP';
				}
			}
			$vevent->setAttribute($field, $value, $options);
		}

		if ($taskData['info_startdate'])
		{
			self::setDateOrTime($vevent, 'DTSTART', $taskData['info_startdate'], $serverTZ);
		}
		if ($taskData['info_enddate'])
		{
			self::setDateOrTime($vevent, 'DUE', $taskData['info_enddate'], $serverTZ);
		}
		if ($taskData['info_datecompleted'])
		{
			self::setDateOrTime($vevent, 'COMPLETED', $taskData['info_datecompleted'], $serverTZ);
		}

		$vevent->setAttribute('DTSTAMP',time());
		$vevent->setAttribute('CREATED',$GLOBALS['egw']->contenthistory->getTSforAction('infolog_task',$_taskID,'add'));
		$vevent->setAttribute('LAST-MODIFIED',$GLOBALS['egw']->contenthistory->getTSforAction('infolog_task',$_taskID,'modify'));
		$vevent->setAttribute('CLASS',$taskData['info_access'] == 'public' ? 'PUBLIC' : 'PRIVATE');
		$vevent->setAttribute('STATUS',$this->status2vtodo($taskData['info_status']));
		// we try to preserv the original infolog status as X-INFOLOG-STATUS, so we can restore it, if the user does not modify STATUS
		$vevent->setAttribute('X-INFOLOG-STATUS',$taskData['info_status']);
		$vevent->setAttribute('PERCENT-COMPLETE',$taskData['info_percent']);
		if ($this->productManufacturer == 'funambol' &&
			(strpos($this->productName, 'outlook') !== false
				|| strpos($this->productName, 'pocket pc') !== false))
		{
			$priority = (int) $this->priority_egw2funambol[$taskData['info_priority']];
		}
		else
		{
			$priority = (int) $this->priority_egw2ical[$taskData['info_priority']];
		}
		$vevent->setAttribute('PRIORITY', $priority);

		$vcal->addComponent($vevent);

		$retval = $vcal->exportvCalendar();
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($retval)."\n",3,$this->logfile);
		}
		// Horde::logMessage("exportVTODO:\n" . print_r($retval, true),
		//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
		return $retval;
	}

	/**
	 * set date-time attribute to DATE or DATE-TIME depending on value
	 * 	00:00 uses DATE else DATE-TIME
	 *
	 * @param Horde_iCalendar_* $vevent
	 * @param string $attr attribute name
	 * @param int $time as timestamp
	 * @param string $tzid=false timezone to use for client, false for user-time
	 */
	static function setDateOrTime(&$vevent, $attr, $time, $tzid=false)
	{
		$params = array();

		// check for date --> export it as such
		if (date('Hi', $time) == '0000')
		{
			$value = array(
				'year'  => date('Y', $time),
				'month' => date('m', $time),
				'mday'  => date('d', $time));
			$params['VALUE'] = 'DATE';
		}
		else
		{
			if (!$tzid || $tzid == 'UTC')
			{
				$value = $time;
			}
			else
			{
				$value = date('Ymd\THis', $time);
				$params['TZID'] = $tzid;
			}

		}
		$vevent->setAttribute($attr, $value, $params);
	}

	/**
	 * Import a VTODO component of an iCal
	 *
	 * @param string $_vcalData
	 * @param int $_taskID=-1 info_id, default -1 = new entry
	 * @param boolean $merge=false	merge data with existing entry
	 * @return int|boolean integer info_id or false on error
	 */
	function importVTODO(&$_vcalData, $_taskID=-1, $merge=false)
	{
		if (!($taskData = $this->vtodotoegw($_vcalData,$_taskID))) return false;

		// we suppose that a not set status in a vtodo means that the task did not started yet
		if (empty($taskData['info_status']))
		{
			$taskData['info_status'] = 'not-started';
		}

		if (empty($taskData['info_datecompleted']))
		{
			$taskData['info_datecompleted'] = 0;
		}

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($taskData)."\n",3,$this->logfile);
		}

		return $this->write($taskData, true, true, false);
	}

	/**
	 * Search a matching infolog entry for the VTODO data
	 *
	 * @param string $_vcalData		VTODO
	 * @param int $contentID=null 	infolog_id (or null, if unkown)
	 * @param boolean $relax=false 	if true, a weaker match algorithm is used
	 *
	 * @return array of infolog_ids of matching entries
	 */
	function searchVTODO($_vcalData, $contentID=null, $relax=false)
	{
		$result = array();

		$taskData = $this->vtodotoegw($_vcalData,$contentID);

		if ($taskData)
		{
			if ($contentID)
			{
				$taskData['info_id'] = $contentID;
			}
			$result = $this->findInfo($taskData, $relax, !$this->useServerTZ);
		}
		return $result;
	}

	/**
	 * Convert VTODO into a eGW infolog entry
	 *
	 * @param string $_vcalData 	VTODO data
	 * @param int $_taskID=-1		infolog_id of the entry
	 * @return array infolog entry or false on error
	 */
	function vtodotoegw($_vcalData, $_taskID=-1)
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($_taskID)\n" .
				array2string($_vcalData)."\n",3,$this->logfile);
		}

		$vcal = new Horde_iCalendar;
		if (!($vcal->parsevCalendar($_vcalData)))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					"(): No vCalendar Container found!\n",3,$this->logfile);
			}
			return false;
		}

		$version = $vcal->getAttribute('VERSION');

		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		$taskData = false;

		foreach ($vcal->getComponents() as $component)
		{
			if (!is_a($component, 'Horde_iCalendar_vtodo'))
			{
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
						"(): Not a vTODO container, skipping...\n",3,$this->logfile);
				}
				continue;
			}

			$taskData = array();
			$taskData['info_type'] = 'task';

			if ($_taskID > 0)
			{
				$taskData['info_id'] = $_taskID;
			}
			foreach ($component->_attributes as $attribute)
			{
				//$attribute['value'] = trim($attribute['value']);
				if (!strlen($attribute['value'])) continue;

				switch ($attribute['name'])
				{
					case 'CLASS':
						$taskData['info_access'] = strtolower($attribute['value']);
						break;

					case 'DESCRIPTION':
						$value = str_replace("\r\n", "\n", $attribute['value']);
						if (preg_match('/\s*\[UID:(.+)?\]/Usm', $value, $matches))
						{
							if (!isset($taskData['info_uid'])
									&& strlen($matches[1]) >= $minimum_uid_length)
							{
								$taskData['info_uid'] = $matches[1];
							}
							//$value = str_replace($matches[0], '', $value);
						}
						if (preg_match('/\s*\[PARENT_UID:(.+)?\]/Usm', $value, $matches))
						{
							if (!isset($taskData['info_id_parent'])
									&& strlen($matches[1]) >= $minimum_uid_length)
							{
								$taskData['info_id_parent'] = $this->getParentID($matches[1]);
							}
							//$value = str_replace($matches[0], '', $value);
						}
						$taskData['info_des'] = $value;
						break;

					case 'LOCATION':
						$taskData['info_location'] = str_replace("\r\n", "\n", $attribute['value']);
						break;

					case 'DUE':
						// eGroupWare uses date only
						$parts = @getdate($attribute['value']);
						$value = @mktime(0, 0, 0, $parts['mon'], $parts['mday'], $parts['year']);
						$taskData['info_enddate'] = $value;
						break;

					case 'COMPLETED':
						$taskData['info_datecompleted']	= $attribute['value'];
						break;

					case 'DTSTART':
						$taskData['info_startdate']	= $attribute['value'];
						break;

					case 'PRIORITY':
						if (0 <= $attribute['value'] && $attribute['value'] <= 9)
						{
							if ($this->productManufacturer == 'funambol' &&
								(strpos($this->productName, 'outlook') !== false
									|| strpos($this->productName, 'pocket pc') !== false))
							{
								$taskData['info_priority'] = (int) $this->priority_funambol2egw[$attribute['value']];
							}
							else
							{
								$taskData['info_priority'] = (int) $this->priority_ical2egw[$attribute['value']];
							}
						}
						else
						{
							$taskData['info_priority'] = 1;	// default = normal
						}
						break;

					case 'STATUS':
						// check if we (still) have X-INFOLOG-STATUS set AND it would give an unchanged status (no change by the user)
						foreach ($component->_attributes as $attr)
						{
							if ($attr['name'] == 'X-INFOLOG-STATUS') break;
						}
						$taskData['info_status'] = $this->vtodo2status($attribute['value'],
							$attr['name'] == 'X-INFOLOG-STATUS' ? $attr['value'] : null);
						break;

					case 'SUMMARY':
						$taskData['info_subject'] = str_replace("\r\n", "\n", $attribute['value']);
						break;

					case 'RELATED-TO':
						$taskData['info_id_parent'] = $this->getParentID($attribute['value']);
						break;

					case 'CATEGORIES':
						if (!empty($attribute['value']))
						{
							$cats = $this->find_or_add_categories(explode(',',$attribute['value']), $_taskID);
							$taskData['info_cat'] = $cats[0];
						}
						break;

					case 'UID':
						if (strlen($attribute['value']) >= $minimum_uid_length)
						{
							$taskData['info_uid'] = $attribute['value'];
						}
						break;

					case 'PERCENT-COMPLETE':
						$taskData['info_percent'] = (int) $attribute['value'];
						break;
				}
			}
			break;
		}
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($_taskID)\n" .
				($taskData ? array2string($taskData) : 'FALSE') . "\n",3,$this->logfile);
		}
		return $taskData;
	}

	/**
	 * Export an infolog entry as VNOTE
	 *
	 * @param int $_noteID		the infolog_id of the entry
	 * @param string $_type		content type (e.g. text/plain)
	 * @return string VNOTE representation of the infolog entry
	 */
	function exportVNOTE($_noteID, $_type)
	{
		if(!($note = $this->read($_noteID, true, 'server'))) return false;

		$note = $GLOBALS['egw']->translation->convert($note,
			$GLOBALS['egw']->translation->charset(), 'UTF-8');

		switch	($_type)
		{
			case 'text/plain':
				$txt = $note['info_subject']."\n\n".$note['info_des'];
				return $txt;

			case 'text/x-vnote':
				if (!empty($note['info_cat']))
				{
					$cats = $this->get_categories(array($note['info_cat']));
					$note['info_cat'] = $GLOBALS['egw']->translation->convert($cats[0],
						$GLOBALS['egw']->translation->charset(), 'UTF-8');
				}
				$vnote = new Horde_iCalendar_vnote();
				$vNote->setAttribute('VERSION', '1.1');
				foreach (array(	'SUMMARY'		=> $note['info_subject'],
								'BODY'			=> $note['info_des'],
								'CATEGORIES'	=> $note['info_cat'],
							) as $field => $value)
				{
					$options = array();
					if (preg_match('/[^\x20-\x7F]/', $value))
					{
						$options['CHARSET']	= 'UTF-8';
						switch ($this->productManufacturer)
						{
							case 'groupdav':
								if ($this->productName == 'kde')
								{
									$options['ENCODING'] = 'QUOTED-PRINTABLE';
								}
								else
								{
									$options['CHARSET'] = '';

									if (preg_match('/([\000-\012\015\016\020-\037\075])/', $value))
									{
										$options['ENCODING'] = 'QUOTED-PRINTABLE';
									}
									else
									{
										$options['ENCODING'] = '';
									}
								}
								break;
							case 'funambol':
								$options['ENCODING'] = 'FUNAMBOL-QP';
						}
					}
					$vevent->setAttribute($field, $value, $options);
				}
				if ($note['info_startdate'])
				{
					$vnote->setAttribute('DCREATED',$note['info_startdate']);
				}
				else
				{
					$vnote->setAttribute('DCREATED',$GLOBALS['egw']->contenthistory->getTSforAction('infolog_note',$_noteID,'add'));
				}
				$vnote->setAttribute('LAST-MODIFIED',$GLOBALS['egw']->contenthistory->getTSforAction('infolog_note',$_noteID,'modify'));

				#$vnote->setAttribute('CLASS',$taskData['info_access'] == 'public' ? 'PUBLIC' : 'PRIVATE');

				$retval = $vnote->exportvCalendar();
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
						array2string($retval)."\n",3,$this->logfile);
				}
				return $retval;
		}
		return false;
	}

	/**
	 * Import a VNOTE component of an iCal
	 *
	 * @param string $_vcalData
	 * @param string $_type		content type (eg.g text/plain)
	 * @param int $_noteID=-1 info_id, default -1 = new entry
	 * @param boolean $merge=false	merge data with existing entry
	 * @return int|boolean integer info_id or false on error
	 */
	function importVNOTE(&$_vcalData, $_type, $_noteID=-1, $merge=false)
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($_vcalData)."\n",3,$this->logfile);
		}

		if (!($note = $this->vnotetoegw($_vcalData, $_type, $_noteID))) return false;

		if($_noteID > 0) $note['info_id'] = $_noteID;

		if (empty($note['info_status'])) $note['info_status'] = 'done';

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($note)."\n",3,$this->logfile);
		}

		return $this->write($note, true, true, false);
	}

	/**
	 * Search a matching infolog entry for the VNOTE data
	 *
	 * @param string $_vcalData		VNOTE
	 * @param int $contentID=null 	infolog_id (or null, if unkown)
	 * @param boolean $relax=false 	if true, a weaker match algorithm is used
	 *
	 * @return infolog_id of a matching entry or false, if nothing was found
	 */
	function searchVNOTE($_vcalData, $_type, $contentID=null, $relax=false)
	{
		if (!($note = $this->vnotetoegw($_vcalData,$_type,$contentID))) return array();

		if ($contentID)	$note['info_id'] = $contentID;

		unset($note['info_startdate']);

		return $this->findInfo($note, $relax, !$this->useServerTZ);
	}

	/**
	 * Convert VTODO into a eGW infolog entry
	 *
	 * @param string $_data 	VNOTE data
	 * @param string $_type		content type (eg.g text/plain)
	 * @param int $_noteID=-1	infolog_id of the entry
	 * @return array infolog entry or false on error
	 */
	function vnotetoegw($_data, $_type, $_noteID=-1)
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($_type, $_noteID)\n" .
				array2string($_data)."\n",3,$this->logfile);
		}
		$note = false;

		switch ($_type)
		{
			case 'text/plain':
				$note = array();
				$note['info_type'] = 'note';
				$botranslation  =& CreateObject('phpgwapi.translation');
				$txt = $botranslation->convert($_data, 'utf-8');
				$txt = str_replace("\r\n", "\n", $txt);

				if (preg_match('/([^\n]+)\n\n(.*)/m', $txt, $match))
				{
					$note['info_subject'] = $match[1];
					$note['info_des'] = $match[2];
				}
				else
				{
					$note['info_subject'] = $txt;
				}
				break;

			case 'text/x-vnote':
				$vnote = new Horde_iCalendar;
				if (!$vcal->parsevCalendar($_data))	return false;
				$version = $vcal->getAttribute('VERSION');

				$components = $vnote->getComponent();
				foreach ($components as $component)
				{
					if (is_a($component, 'Horde_iCalendar_vnote'))
					{
						$note = array();
						$note['info_type'] = 'note';

						foreach ($component->_attributes as $attribute)
						{
							switch ($attribute['name'])
							{
								case 'BODY':
									$note['info_des'] = str_replace("\r\n", "\n", $attribute['value']);
									break;

								case 'SUMMARY':
									$note['info_subject'] = str_replace("\r\n", "\n", $attribute['value']);
									break;

								case 'CATEGORIES':
									if ($attribute['value'])
									{
										$cats = $this->find_or_add_categories(explode(',',$attribute['value']), $_noteID);
										$note['info_cat'] = $cats[0];
									}
									break;
							}
						}
					}
				}
		}
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($_type, $_noteID)\n" .
				($note ? array2string($note) : 'FALSE') ."\n",3,$this->logfile);
		}
		return $note;
	}

	/**
	 * Set the supported fields
	 *
	 * Currently we only store manufacturer and name
	 *
	 * @param string $_productManufacturer
	 * @param string $_productName
	 */
	function setSupportedFields($_productManufacturer='', $_productName='')
	{
		$state = &$_SESSION['SyncML.state'];
		if (isset($state))
		{
			$deviceInfo = $state->getClientDeviceInfo();
		}

		// store product manufacturer and name, to be able to use it elsewhere
		if ($_productManufacturer)
		{
			$this->productManufacturer = strtolower($_productManufacturer);
			$this->productName = strtolower($_productName);
		}

		if (isset($deviceInfo) && is_array($deviceInfo))
		{
			if (!isset($this->productManufacturer)
				|| $this->productManufacturer == ''
				|| $this->productManufacturer == 'file')
			{
				$this->productManufacturer = strtolower($deviceInfo['manufacturer']);
			}
			if (!isset($this->productName) || $this->productName == '')
			{
				$this->productName = strtolower($deviceInfo['model']);
			}
			if (isset($deviceInfo['uidExtension'])
				&& $deviceInfo['uidExtension'])
			{
					$this->uidExtension = true;
			}
			if (isset($deviceInfo['tzid']) &&
				$deviceInfo['tzid'])
			{
				$this->useServerTZ = ($deviceInfo['tzid'] == 1);
			}
		}

		if (in_array($this->productManufacturer,array('file','groupdav')))
		{
			$this->useServerTZ = true;
		}

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				'(' . $this->productManufacturer .
				', '. $this->productName .', ' .
				($this->useServerTZ ? 'SERVERTIME' : 'USERTIME') .
				")\n" , 3, $this->logfile);
		}

		Horde::logMessage('setSupportedFields(' . $this->productManufacturer . ', '
			. $this->productName .', ' .
			($this->useServerTZ ? 'SERVERTIME' : 'USERTIME') .')',
			__FILE__, __LINE__, PEAR_LOG_DEBUG);

	}
}
