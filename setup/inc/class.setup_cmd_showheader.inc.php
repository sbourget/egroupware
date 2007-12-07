<?php
/**
 * eGgroupWare setup - show/return the header.inc.php
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.admin_cmd_check_acl.inc.php 24709 2007-11-27 03:20:28Z ralfbecker $ 
 */

/**
 * setup command: show/return the header.inc.php
 * 
 * Has no constructor, as we have no arguments beside the header admin user and password,
 * which get set via setup_cmd::set_header_secret($user,$pw)
 */
class setup_cmd_showheader extends setup_cmd 
{
	/**
	 * Constructor
	 *
	 * @param boolean $data=true send only the remote_hash, domain and webserver_url and not the complete header
	 */
	function __construct($data=true)
	{
		if (!is_array($data))
		{
			$data = array(
				'hash_only' => $data,
			);
		}
		//echo __CLASS__.'::__construct()'; _debug_array($data);
		admin_cmd::__construct($data);
	}

	/**
	 * show/return the header.inc.php
	 * 
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string serialized $GLOBALS defined in the header.inc.php
	 * @throws Exception(lang('Wrong credentials to access the header.inc.php file!'),2);
	 * @throws Exception('header.inc.php not found!');
	 */
	function exec($check_only=false)
	{
		if ($this->remote_id && $check_only) return true;	// cant check for the remote site locally!
		
		$this->_check_header_access();

		if ($check_only) return true;

		$egw_info_backup = $GLOBALS['egw_info'];
		$GLOBALS['egw_info'] = array (
			'flags' => array(
				'noapi' => true,
			),
		);
		if (!($header = file_get_contents(EGW_SERVER_ROOT.'/header.inc.php')))
		{
			throw new Exception('header.inc.php not found!');
		}
		eval(str_replace(array('<?php','perfgetmicrotime'),array('','perfgetmicrotime2'),$header));

		// unset the flags, they are not part of  the header
		unset($GLOBALS['egw_info']['flags']);

		// include the api version of this instance
		$GLOBALS['egw_info']['server']['versions']['phpgwapi'] = $egw_info_backup['server']['versions']['phpgwapi'];
		
		// fetching the install id's stored in the database
		foreach($GLOBALS['egw_domain'] as $domain => &$data)
		{
			$data += $this->_fetch_config($data);
			try {
				// it's saver to only send the remote_hash and not install_id and config_pw
				$data['remote_hash'] = admin_cmd::remote_hash($data['install_id'],$data['config_passwd']);
			}
			catch(Exception $e) {
				if ($data['install_id']) $data['error'] .= $e->getMessage();
			}
			if ($this->hash_only)
			{
				$data = array(
					'remote_hash' => $data['remote_hash'],
					'webserver_url' => $data['webserver_url'],
				)+($data['error'] ? array(
					'error' => $data['error'],				
				) : array());
			}
		}
		if ($this->hash_only)
		{
			$ret = array('egw_domain' => $GLOBALS['egw_domain']);
		}
		else
		{
			$ret = array(
				'egw_info' => $GLOBALS['egw_info'],
				'egw_domain' => $GLOBALS['egw_domain'],
				'EGW_SERVER_ROOT' => EGW_SERVER_ROOT,
				'EGW_INCLUDE_ROOT' => EGW_INCLUDE_ROOT,
			);
		}
		$GLOBALS['egw_info'] = $egw_info_backup;
		
		return $ret;
	}
	
	/**
	 * Fetch the install_id, and webserver_url of a domain from the DB
	 *
	 * @param array $data with values for keys 'db_name', 'db_host', 'db_port', 'db_user', 'db_pass', 'db_type'
	 * @return array with values for keys install_id, webserver_url
	 */
	private function _fetch_config(array $data)
	{
		$db = new egw_db();

		ob_start();		// not available db connection echos a lot grab ;-)
		$err_rep = error_reporting(0);

		$config = array();
		try {
			$db->connect($data['db_name'],$data['db_host'],$data['db_port'],$data['db_user'],$data['db_pass'],$data['db_type']);
			$db->set_app('phpgwapi');
			$db->select('egw_config','config_name,config_value',array(
				'config_name'=>array('install_id','webserver_url'),
				'config_app'=>'phpgwapi',
			),__LINE__,__FILE__);
			while (($row = $db->row(true)))
			{
				$config[$row['config_name']] = $row['config_value'];
			}
		}
		catch (Exception $e) {
			$config['error'] = strip_tags($e->getMessage());
		}
		// restoring the db connection, seems to be necessary when we run via remote execution
		$GLOBALS['egw']->db->disconnect();
		$GLOBALS['egw']->db->connect();
		
		error_reporting($err_rep);
		ob_end_clean();

		return $config;
	}
}
