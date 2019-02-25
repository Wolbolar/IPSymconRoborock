<?php
// set base dir
define('__ROOT__', dirname(dirname(__FILE__)));

// load ips constants
require_once(__ROOT__ . '/libs/ips.constants.php');

/**
 * Class Roborock
 * Xiaomi Mi Vacuum Cleaner
 *
 * @ToDo Read Token (Xiaomi Mi App?)
 */
class Roborock extends IPSModule
{
	// state code mapper
	protected $state_codes = [
		0 => 'Unknown',
		1 => 'Starting up',
		2 => 'Sleeping',
		3 => 'Waiting',
		4 => 'Remote control',
		5 => 'Cleaning',
		6 => 'Returning to base',
		7 => 'Manual mode',
		8 => 'Charging',
		9 => 'Charging problem',
		10 => 'Pause',
		11 => 'Spot cleaning',
		12 => 'Malfunction',
		13 => 'Shutting down',
		14 => 'Software update',
		15 => 'Docking',
		100 => 'Full'
	];

	// error code mapper
	protected $error_codes = [
		0 => 'None',
		1 => 'Laser sensor fault',
		2 => 'Collision sensor error',
		3 => 'Wheel floating',
		4 => 'Cliff sensor fault',
		5 => 'Main brush blocked',
		6 => 'Side brush blocked',
		7 => 'Wheel blocked',
		8 => 'Device stuck',
		9 => 'Dust bin missing',
		10 => 'Filter blocked',
		11 => 'Magnetic field detected',
		12 => 'Low battery',
		13 => 'Charging problem',
		14 => 'Battery failure',
		15 => 'Wall sensor fault',
		16 => 'Uneven surface',
		17 => 'Side brush failure',
		18 => 'Suction fan failure',
		19 => 'Unpowered charging station',
		20 => 'Unknown'
	];

	protected $push_notifications = [
		[
			'enabled' => true,
			'state_id' => 'errors', // enable all error codes
			'name' => 'Error',
			'sound' => 'alarm'
		],
		[
			'enabled' => false,
			'state_id' => 5,
			'name' => 'Cleaning',
			'sound' => '' // empty = default sound
		],
		[
			'enabled' => false,
			'state_id' => 8,
			'name' => 'Charging',
			'sound' => ''
		],
		[
			'enabled' => true,
			'state_id' => 6,
			'name' => 'Returning to base',
			'sound' => ''
		],
		[
			'enabled' => false,
			'state_id' => 15,
			'name' => 'Docking',
			'sound' => ''
		]
	];

	// helper properties
	private $position = 0;

	/**
	 * create instance
	 * @return bool|void
	 */
	public function Create()
	{
		parent::Create();

		// connect to parent i/o device
		$this->ConnectParent('{4743ED9C-720B-D5EA-9B0C-0585803284F3}'); // IO Device

		// register public properties
		$this->RegisterPropertyString('ip', '');
		$this->RegisterPropertyString('token', '');
		$this->RegisterPropertyBoolean('fan_power', false);
		$this->RegisterPropertyBoolean('error_code', false);
		$this->RegisterPropertyBoolean('consumables', false);
		$this->RegisterPropertyBoolean('consumables_separate', false);
		$this->RegisterPropertyBoolean('dnd_mode', false);
		$this->RegisterPropertyBoolean('clean_area', false);
		$this->RegisterPropertyBoolean('clean_time', false);
		$this->RegisterPropertyBoolean('total_cleans', false);
		$this->RegisterPropertyBoolean('serial_number', false);
		$this->RegisterPropertyBoolean('timer_details', false);
		$this->RegisterPropertyBoolean('findme', false);
		$this->RegisterPropertyBoolean('extended_info', false);
		$this->RegisterPropertyBoolean('volume', false);
		$this->RegisterPropertyBoolean('timezone', false);
		$this->RegisterPropertyBoolean('remote', false);

		$this->RegisterPropertyInteger('notification_instance', 0);
		$this->RegisterPropertyString('notifications', $this->GetPushNotifications());
		$this->RegisterPropertyBoolean('setup_scripts', false);
		$this->RegisterPropertyInteger('script_category', 0);

		$this->RegisterPropertyBoolean('wifi_connected', false);
		$this->RegisterPropertyString('xiaomi_email', '');
		$this->RegisterPropertyString('xiaomi_pass', '');

		// register private properties
		$this->RegisterPropertyInteger('token_mode', -1);

		// register update timer
		$this->RegisterPropertyInteger('UpdateInterval', 15);
		$this->RegisterTimer('RoborockTimerUpdate', 0, 'Roborock_Update(' . $this->InstanceID . ');');

		// register kernel messages
		$this->RegisterMessage(0, IPS_KERNELMESSAGE);
	}

	/**
	 * apply changes from configuration form
	 * @return bool|void
	 */
	public function ApplyChanges()
	{
		parent::ApplyChanges();

		//  register profiles
		$this->RegisterProfileAssociation(
			'Roborock.Command',
			'Execute',
			'',
			'',
			0,
			4,
			0,
			0,
			1,
			[
				[0, $this->Translate('Start'), 'HollowLargeArrowRight', -1, 1],
				[1, $this->Translate('Pause'), 'Close', -1],
				[2, $this->Translate('Stop'), 'Close', -1],
				[3, $this->Translate('Spot'), 'Climate', -1],
				[4, $this->Translate('Charge'), 'Battery', -1],
				[5, $this->Translate('Locate'), 'Motion', -1]
			]
		);

		$this->RegisterProfileAssociation(
			'Roborock.Errorcode',
			'Information',
			'',
			'',
			0,
			20,
			0,
			0,
			1,
			'error_codes'
		);

		$this->RegisterProfileAssociation(
			'Roborock.State',
			'Information',
			'',
			'',
			0,
			15,
			0,
			0,
			1,
			'state_codes'
		);

		$this->RegisterProfileAssociation(
			'Roborock.Findme',
			'Robot',
			'',
			'',
			0,
			0,
			0,
			0,
			1,
			[
				[0, $this->Translate('find robot'), '', 0x3ADF00]
			]
		);

		$this->RegisterProfile('Roborock.Fanpower', 'Speedo', '', " %", 0, 100, 1, 0, 1);
		$this->RegisterProfile('Roborock.Cleanarea', 'Shuffle', '', " " . chr(109) . chr(178), 0, 0, 0, 1, 2);
		$this->RegisterProfile('Roborock.Totalcleans', 'Gauge', '', '', 0, 0, 0, 2, 1);
		$this->RegisterProfile('Roborock.Volume', 'Speaker', '', " %", 0, 100, 1, 0, 1);
		$this->RegisterProfile('Roborock.Battery', 'Battery', '', " %", 0, 100, 1, 0, 1);
		$this->RegisterProfile('Roborock.Consumable', 'Gear', '', " %", 0, 100, 1, 0, 1);

		// hidden, internal variables
		$variable_notification_id = $this->RegisterVariableString('last_notification_state', 'last_notification_state', '', 99);
		IPS_SetHidden($variable_notification_id, true);

		$variable_notification_id = $this->RegisterVariableString('last_notification_error', 'last_notification_error', '', 99);
		IPS_SetHidden($variable_notification_id, true);

		// Remote Control
		if ($this->ReadPropertyBoolean('remote')) {
			$id = $this->RegisterVariableString('remote', $this->Translate('Remote Control'), '~HTMLBox', $this->_getPosition());
			IPS_SetIcon($id, 'Move');
			$this->SetJoystickHtml();
		} else {
			$this->UnregisterVariable('remote');
		}

		// Current Coordinates
		$this->RegisterVariableString('coordinates', $this->Translate('Current Coordinates'), '', 98);

		// command
		$this->RegisterVariableInteger('command', $this->Translate('command'), 'Roborock.Command', $this->_getPosition());
		$this->EnableAction('command');

		// current state
		$this->RegisterVariableInteger('state', $this->Translate('State'), 'Roborock.State', $this->_getPosition());

		// current battery level
		$this->RegisterVariableInteger('battery', $this->Translate('Battery'), 'Roborock.Battery', $this->_getPosition());

		// fan power
		if ($this->ReadPropertyBoolean('fan_power')) {
			$this->RegisterVariableInteger('fan_power', $this->Translate('Fan Power'), 'Roborock.Fanpower', $this->_getPosition());
			$this->EnableAction('fan_power');
		} else {
			$this->UnregisterVariable('fan_power');
		}

		// volume
		if ($this->ReadPropertyBoolean('volume')) {
			$this->RegisterVariableInteger('volume', $this->Translate('Volume'), 'Roborock.Volume', $this->_getPosition());
			$this->EnableAction('volume');
		} else {
			$this->UnregisterVariable('volume');
		}

		// error code
		if ($this->ReadPropertyBoolean('error_code')) {
			$this->RegisterVariableInteger('error_code', $this->Translate('Error Code'), 'Roborock.Errorcode', $this->_getPosition());
			//$this->EnableAction('error_code');
		} else {
			$this->UnregisterVariable('error_code');
		}

		// consumables
		if ($this->ReadPropertyBoolean('consumables')) {
			$this->RegisterVariableString('consumables', $this->Translate('Consumables'), '~HTMLBox', $this->_getPosition());
		} else {
			$this->UnregisterVariable('consumables');
		}

		// consumables separate
		if ($this->ReadPropertyBoolean('consumables_separate')) {
			$this->RegisterVariableInteger('main_brush', $this->Translate('Main Brush'), 'Roborock.Consumable', $this->_getPosition());
			$this->RegisterVariableInteger('side_brush', $this->Translate('Side Brush'), 'Roborock.Consumable', $this->_getPosition());
			$this->RegisterVariableInteger('filter', $this->Translate('Filter'), 'Roborock.Consumable', $this->_getPosition());
			$this->RegisterVariableInteger('sensor', $this->Translate('Sensor'), 'Roborock.Consumable', $this->_getPosition());
		} else {
			$this->UnregisterVariable('main_brush');
			$this->UnregisterVariable('side_brush');
			$this->UnregisterVariable('filter');
			$this->UnregisterVariable('sensor');
		}

		// dnd mode
		if ($this->ReadPropertyBoolean('dnd_mode')) {
			$this->RegisterVariableBoolean('dnd_mode', $this->Translate('DND Mode'), '~Switch', $this->_getPosition());
			$this->EnableAction('dnd_mode');
			$this->RegisterVariableInteger('dnd_starttime', $this->Translate('DND Starttime'), '~UnixTimestampTime', $this->_getPosition());
			$this->EnableAction('dnd_starttime');
			$this->RegisterVariableInteger('dnd_endtime', $this->Translate('DND Endtime'), '~UnixTimestampTime', $this->_getPosition());
			$this->EnableAction('dnd_endtime');
		} else {
			$this->UnregisterVariable('dnd_mode');
			$this->UnregisterVariable('dnd_starttime');
			$this->UnregisterVariable('dnd_endtime');
		}

		// clean area
		if ($this->ReadPropertyBoolean('clean_area')) {
			$this->RegisterVariableFloat('clean_area', $this->Translate('Clean Area'), 'Roborock.Cleanarea', $this->_getPosition());
			$this->RegisterVariableFloat('total_clean_area', $this->Translate('Total Clean Area'), 'Roborock.Cleanarea', $this->_getPosition());
		} else {
			$this->UnregisterVariable('clean_area');
			$this->UnregisterVariable('total_clean_area');
		}

		// clean_time
		if ($this->ReadPropertyBoolean('clean_time')) {
			$this->RegisterVariableInteger('clean_time', $this->Translate('Clean Time'), '~UnixTimestampTime', $this->_getPosition());
			$this->RegisterVariableInteger('total_clean_time', $this->Translate('Total Clean Time'), '~UnixTimestampTime', $this->_getPosition());
			$this->RegisterVariableString('cleaning_records', $this->Translate('Cleaning Records'), '~HTMLBox', $this->_getPosition());

			$cleaning_records_tmp = $this->RegisterVariableString('cleaning_records_tmp', 'cleaning_records_tmp', '~HTMLBox', 99);
			IPS_SetHidden($cleaning_records_tmp, true);
		} else {
			$this->UnregisterVariable('clean_time');
			$this->UnregisterVariable('total_clean_time');
			$this->UnregisterVariable('cleaning_records');
			$this->UnregisterVariable('cleaning_records_tmp');
		}

		// total cleans
		if ($this->ReadPropertyBoolean('total_cleans')) {
			$this->RegisterVariableInteger('total_cleans', $this->Translate('Total Cleans'), 'Roborock.Totalcleans', $this->_getPosition());
			// $this->EnableAction('total_cleans');
		} else {
			$this->UnregisterVariable('total_cleans');
		}

		// serial number
		if ($this->ReadPropertyBoolean('serial_number')) {
			$id = $this->RegisterVariableString('serial_number', $this->Translate('Serial Number'), '', $this->_getPosition());
			IPS_SetIcon($id, 'Robot');
			// $this->EnableAction('serial_number');
		} else {
			$this->UnregisterVariable('serial_number');
		}

		// timer details
		if ($this->ReadPropertyBoolean('timer_details')) {
			$id = $this->RegisterVariableString('timer_details', $this->Translate('Timer Details'), '~HTMLBox', $this->_getPosition());
			IPS_SetIcon($id, "Clock");
			// $this->EnableAction('timer_details');
		} else {
			$this->UnregisterVariable('timer_details');
		}

		// Locate Robot
		if ($this->ReadPropertyBoolean('findme')) {
			$this->RegisterVariableInteger('findme', $this->Translate('find robot'), 'Roborock.Findme', $this->_getPosition());
			$this->EnableAction('findme');
		} else {
			$this->UnregisterVariable('findme');
		}

		// extended info
		if ($this->ReadPropertyBoolean('extended_info')) {
			$this->RegisterVariableString('hw_ver', $this->Translate('hardware version'), '', $this->_getPosition());
			$this->RegisterVariableString('fw_ver', $this->Translate('firmware version'), '', $this->_getPosition());
			$this->RegisterVariableString('ssid', $this->Translate('ssid'), '', $this->_getPosition());
			$this->RegisterVariableString('local_ip', $this->Translate('local ip'), '', $this->_getPosition());
			$this->RegisterVariableString('model', $this->Translate('model'), '', $this->_getPosition());
			$this->RegisterVariableString('mac', $this->Translate('mac'), '', $this->_getPosition());
		} else {
			$this->UnregisterVariable('hw_ver');
			$this->UnregisterVariable('fw_ver');
			$this->UnregisterVariable('ssid');
			$this->UnregisterVariable('local_ip');
			$this->UnregisterVariable('model');
			$this->UnregisterVariable('mac');
		}

		// Timezone
		if ($this->ReadPropertyBoolean('timezone')) {
			$this->RegisterVariableString('timezone', $this->Translate('Timezone'), '', $this->_getPosition());
		} else {
			$this->UnregisterVariable('timezone');
		}

		// receive data only for this instance
		$this->SetReceiveDataFilter('.*"InstanceID":' . $this->InstanceID . '.*');

		// run only, when kernel is ready
		if (IPS_GetKernelRunlevel() == KR_READY) {
			// validate configuration
			$valid_config = $this->ValidateConfiguration(true);

			// set interval
			$this->SetUpdateIntervall($valid_config);
		}
	}

	/**
	 * Handle Kernel Messages
	 * @param int $TimeStamp
	 * @param int $SenderID
	 * @param int $Message
	 * @param array $Data
	 * @return bool|void
	 */
	public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
	{
		if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
			// validate configuration & set interval
			$valid_config = $this->ValidateConfiguration();
			$this->SetUpdateIntervall($valid_config);
		}
	}

	/**
	 * validate configuration
	 * @param bool $extended_validation
	 * @return bool
	 */
	private function ValidateConfiguration($extended_validation = false)
	{
		// check if configuration is complete
		if (!$this->CheckConfiguration()) {
			$this->SetStatus(201);
			return false;
		}

		// read properties
		$ip = $this->ReadPropertyString('ip');

		// check for valid ip address
		if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
			$this->SetStatus(203);
			return false;
		}

		// ping ip
		/*
				if (!Sys_Ping($ip, 1000)) {
					$this->SetStatus(203);
					return false;
				}
		*/
		// check token
		if (!$this->ValidateToken()) {
			$this->SetStatus(205);
			return false;
		}

		// get device info
		if ($extended_validation) {
			$serial = $this->RequestData('get_serial_number', [
				'immediate' => true
			]);

			if (!$serial) {
				$this->SetStatus(206);
				return false;
			}
		}

		// check category
		if ($this->ReadPropertyBoolean("setup_scripts") && $this->ReadPropertyInteger("script_category") == 0) {
			$this->SetStatus(209);
			return false;
		} else if ($this->ReadPropertyBoolean("setup_scripts")) {
			$this->SetupScripts();
		}

		// yay, configuration is valid! =)
		$this->SetStatus(102);
		return true;
	}

	/**
	 * set / unset update interval
	 * @param bool $enable
	 */
	protected function SetUpdateIntervall($enable = true)
	{
		$interval = $enable ? ($this->ReadPropertyInteger('UpdateInterval') * 1000) : 0;
		$this->SetTimerInterval('RoborockTimerUpdate', $interval);
	}

	/**
	 * Setup Scripts
	 */
	protected function SetupScripts()
	{
		$this->CreateRoborockScript("Roborock Start", "Roborock_Start_Script", $this->CreateStartScript());
		$this->CreateRoborockScript("Roborock Stop", "Roborock_Stop_Script", $this->CreateStopScript());
		$this->CreateRoborockScript("Roborock Pause", "Roborock_Pause_Script", $this->CreatePauseScript());
		$this->CreateRoborockScript("Roborock Locate", "Roborock_Locate_Script", $this->CreateLocateScript());
		$this->CreateRoborockScript("Roborock Charge", "Roborock_Charge_Script", $this->CreateChargeScript());
		$this->CreateRoborockScript("Roborock Clean Spot", "Roborock_CleanSpot_Script", $this->CreateCleanSpotScript());
		$this->CreateRoborockScript("Roborock Reset Mainbrush", "Roborock_ResetMainbrush_Script", $this->CreateResetMainbrushScript());
		$this->CreateRoborockScript("Roborock Reset Filter", "Roborock_ResetFilter_Script", $this->CreateResetFilterScript());
		$this->CreateRoborockScript("Roborock Reset Sensors", "Roborock_ResetSensors_Script", $this->CreateResetSensorsScript());
		$this->CreateRoborockScript("Roborock Reset Sidebrush", "Roborock_ResetSidebrush_Script", $this->CreateResetSideBrushScript());
	}

	/**
	 * Create a Roborock Script
	 * @param $Scriptname
	 * @param $Ident
	 * @param $Script
	 * @return int
	 */
	protected function CreateRoborockScript($Scriptname, $Ident, $Script)
	{
		$MainCatID = $this->ReadPropertyInteger("script_category");
		$ScriptID = @IPS_GetObjectIDByIdent($Ident, $MainCatID);

		if ($ScriptID === false) {
			$ScriptID = IPS_CreateScript(0);
			IPS_SetName($ScriptID, $Scriptname);
			IPS_SetParent($ScriptID, $MainCatID);
			IPS_SetIdent($ScriptID, $Ident);
			IPS_SetScriptContent($ScriptID, $Script);
		}
		return $ScriptID;
	}

	private function CreateStartScript()
	{
		$Script = '<?
Roborock_Start(' . $this->InstanceID . ');		
?>';
		return $Script;
	}

	private function CreateStopScript()
	{
		$Script = '<?
Roborock_Stop(' . $this->InstanceID . ');		
?>';
		return $Script;
	}

	private function CreatePauseScript()
	{
		$Script = '<?
Roborock_Pause(' . $this->InstanceID . ');		
?>';
		return $Script;
	}

	private function CreateLocateScript()
	{
		$Script = '<?
Roborock_Locate(' . $this->InstanceID . ');		
?>';
		return $Script;
	}

	private function CreateChargeScript()
	{
		$Script = '<?
Roborock_Charge(' . $this->InstanceID . ');		
?>';
		return $Script;
	}

	private function CreateCleanSpotScript()
	{
		$Script = '<?
Roborock_CleanSpot(' . $this->InstanceID . ');		
?>';
		return $Script;
	}

	private function CreateResetFilterScript()
	{
		$Script = '<?
Roborock_Reset_Filter(' . $this->InstanceID . ');		
?>';
		return $Script;
	}

	private function CreateResetMainbrushScript()
	{
		$Script = '<?
Roborock_Reset_Mainbrush(' . $this->InstanceID . ');		
?>';
		return $Script;
	}

	private function CreateResetSideBrushScript()
	{
		$Script = '<?
Roborock_Reset_Sidebrush(' . $this->InstanceID . ');		
?>';
		return $Script;
	}

	private function CreateResetSensorsScript()
	{
		$Script = '<?
Roborock_Reset_Sensors(' . $this->InstanceID . ');		
?>';
		return $Script;
	}

	/**
	 * Update data
	 */
	public function Update()
	{
		if ($this->ValidateConfiguration()) {
			// Update state
			$this->Get_State();

			// update serial number, once
			if ($this->ReadPropertyBoolean('serial_number') && !GetValueString($this->GetIDForIdent('serial_number'))) {
				$this->Get_Serial_Number();
			}

			// update consumables
			if ($this->ReadPropertyBoolean('consumables') || $this->ReadPropertyBoolean('consumables_separate')) {
				$this->Get_Consumables();
			}

			// update clean summary
			if ($this->ReadPropertyBoolean('clean_time')) {
				$this->GetCleanSummary();
			}

			// update dnd mode
			if ($this->ReadPropertyBoolean('dnd_mode')) {
				$this->Get_DND_Mode();
			}

			// update extended info
			if ($this->ReadPropertyBoolean('extended_info')) {
				$this->GetDeviceInfo();
			}

			// update timer details
			if ($this->ReadPropertyBoolean('timer_details')) {
				$this->Get_Timer_Details();
			}

			// update volume
			if ($this->ReadPropertyBoolean('volume')) {
				$this->Get_SoundVolume();
			}

			// update timezone, once
			if ($this->ReadPropertyBoolean('timezone') && !GetValueString($this->GetIDForIdent('timezone'))) {
				$this->GetTimezone();
			}
		}
	}

	/**
	 * Send request to parent instance
	 * @param string $method
	 * @param array $options
	 * @return array|bool
	 */
	protected function RequestData(string $method, array $options = [])
	{
		// build payload
		$payload = [
			'InstanceID' => $this->InstanceID,
			'token' => $this->ReadPropertyString('token'),
			'ip' => $this->ReadPropertyString('ip'),
			'immediate' => false,
			'method' => $method,
			'params' => []
		];

		// force immediate option on ips sender
		$_IPS = isset($_IPS) ? $_IPS : [];
		if (
			in_array($_IPS['SENDER'], ['Execute', 'Variable'])
			|| ($_IPS['SELF'] > 0 && $_IPS['SELF'] != $this->InstanceID)
		) {
			$payload['immediate'] = true;
		}

		// merge payload & options
		$buffer = $this->_merge($payload, $options);

		// send to i/o device
		$this->_debug('send', json_encode($buffer));

		if ($io = @$this->SendDataToParent(json_encode(['DataID' => '{F7DC50D6-DCE6-27CE-49B2-A363593EBB3B}', 'Buffer' => $buffer]))) {
			// receive data on immediately requests
			if ($buffer['immediate']) {
				// merge buffer
				$buffer = $this->_merge(
					$buffer,
					json_decode($io, true)
				);

				// return data
				return $this->ExecuteCallback($buffer);
			}

			return true;
		}

		return false;
	}

	/**
	 * Receive and update data
	 * @param string $JSONString
	 * @return bool|void
	 */
	public function ReceiveData($JSONString)
	{
		// convert json payload to array
		$payload = json_decode($JSONString, true);

		// extract buffer
		$buffer = $payload['Buffer'];

		// check token and save, if diffs from current one
		$current_token = $this->ReadPropertyString('token');
		if ($buffer['token'] && strlen($buffer['token']) === 32 && $buffer['token'] != $current_token) {
			IPS_SetProperty($this->InstanceID, 'token', $buffer['token']);
			IPS_ApplyChanges($this->InstanceID);
		}

		// execute callback
		$this->ExecuteCallback($buffer);
	}

	/**
	 * Check if a callback exist and execute method
	 * @param $buffer
	 * @return mixed
	 */
	private function ExecuteCallback($buffer)
	{
		// check if callback exists
		$callback = strtr(strtolower($buffer['method']), ['.' => '_']) . '_callback';
		if (method_exists($this, $callback)) {
			$this->_debug('receive', $callback . ': ' . json_encode($buffer));
			return call_user_func([$this, $callback], $buffer);
		} else {
			$this->_debug('receive', json_encode($buffer));
		}

		// return original buffer, when no callback was found
		return $buffer;
	}

	/**
	 * validate token
	 * @return bool
	 */
	public function ValidateToken()
	{
		$token = $this->ReadPropertyString('token');

		// convert token on 96 byte length
		if (strlen($token) == 96) {
			$secret = str_repeat("\0", 16);
			$token = openssl_decrypt(hex2bin($token), 'aes-128-ecb', $secret, OPENSSL_RAW_DATA);

			// save property
			IPS_SetProperty($this->InstanceID, 'token', $token);
			IPS_ApplyChanges($this->InstanceID);
		}

		// return true, when token length is 32 byte
		if (strlen($token) == 32) {
			return true;
		}

		return false;
	}

	/**
	 * check if sound files are installing
	 * @return array
	 */
	public function sound_progress()
	{
		return $this->RequestData('get_sound_progress', [
			'immediate' => true
		]);
	}

	/**
	 * start cleaning
	 * @return bool
	 */
	public function Start()
	{
		$this->SetRoborockValue('command', 0);
		return $this->RequestData('app_start');
	}

	/**
	 * stop cleaning
	 * @return bool
	 */
	public function Stop()
	{
		$this->SetRoborockValue('command', 2);
		return $this->RequestData('app_stop');
	}

	/**
	 * start spot cleaning
	 * @return bool
	 */
	public function CleanSpot()
	{
		$this->SetRoborockValue('command', 3);
		return $this->RequestData('app_spot');
	}

	/**
	 * pause cleaning
	 * @return bool
	 */
	public function Pause()
	{
		$this->SetRoborockValue('command', 1);
		return $this->RequestData('app_pause');
	}

	/**
	 * return to dock
	 * @return bool
	 */
	public function Charge()
	{
		$this->SetRoborockValue('command', 4);
		return $this->RequestData('app_charge');
	}

	/**
	 * locate vacuum cleaner by voice message
	 * @return mixed
	 */
	public function Locate()
	{
		$this->SetRoborockValue('command', 5);
		return $this->RequestData('find_me');
	}

	// Consumables time remaining in %

	/**
	 * get consumables time remaining in %
	 * @return array
	 */
	public function Get_Consumables()
	{
		return $this->RequestData('get_consumable');
	}

	/**
	 * reset conmsumables
	 * @param string $part filter|mainbrush|sidebrush|sensors
	 * @return bool
	 */
	public function Reset_Consumable(string $part)
	{
		return $this->RequestData('reset_consumable', [
			'params' => [$part]
		]);
	}

	/**
	 * reset filter
	 * @return bool
	 */
	public function Reset_Filter()
	{
		return $this->Reset_Consumable('filter');
	}

	/**
	 * reset mainbrush
	 * @return bool
	 */
	public function Reset_Mainbrush()
	{
		return $this->Reset_Consumable('mainbrush');
	}

	/**
	 * reset sidebrush
	 * @return bool
	 */
	public function Reset_Sidebrush()
	{
		return $this->Reset_Consumable('sidebrush');
	}

	/**
	 * reset conmsumables
	 * @return bool
	 */
	public function Reset_Sensors()
	{
		return $this->Reset_Consumable('sensors');
	}

	/**
	 * get clean summary
	 * @return bool
	 */
	public function GetCleanSummary()
	{
		return $this->RequestData('get_clean_summary');
	}

	/**
	 * get clean record by record id
	 * @param int|array $record_id
	 * @return array
	 */
	protected function GetCleanRecord($record_id)
	{
		return $this->RequestData('get_clean_record', [
			'params' => is_array($record_id) ? $record_id : [(int)$record_id]
		]);
	}

	/**
	 * get clean record map
	 * @return bool
	 */
	public function GetCleanRecordMap()
	{
		return $this->RequestData('get_clean_record_map');
	}

	/**
	 * get map
	 * @return bool
	 */
	public function GetMap()
	{
		return $this->RequestData('get_map_v1');
	}

	/**
	 * get current state
	 * @return array
	 */
	public function Get_State()
	{
		return $this->RequestData('get_status');
	}

	/**
	 * get serial number
	 * @return string
	 */
	public function Get_Serial_Number()
	{
		return $this->RequestData('get_serial_number');
	}

	/**
	 * get current dnd mode
	 * @return array
	 */
	public function Get_DND_Mode()
	{
		return $this->RequestData('get_dnd_timer');
	}

	/**
	 * set dnd timer, 24 hour notation
	 * @param int $starthour
	 * @param int $startminutes
	 * @param int $endhour
	 * @param int $endminutes
	 * @return bool
	 */
	public function SetDNDTimer(int $starthour, int $startminutes, int $endhour, int $endminutes)
	{
		return $this->RequestData('set_dnd_timer', [
			'params' => [
				(int)$starthour,
				(int)$startminutes,
				(int)$endhour,
				(int)$endminutes
			]
		]);
	}

	/**
	 * disable dnd mode
	 * @return bool
	 */
	public function DisableDND()
	{
		return $this->RequestData('close_dnd_timer');
	}


	/**
	 * Set Timer
	 * @param int $hour two digits
	 * @param int $minute two digits
	 * @param string $repetition once|weekdays|weekends|every day
	 * @return array|bool
	 */
	public function Set_Timer(int $hour, int $minute, string $repetition)
	{
		$timerid = time();
		return $this->RequestData('set_timer', [
			'params' => [[$timerid, [$minute . ' ' . $hour . ' * * ' . $this->_getTimerRepetition($repetition), ['start_clean', '']]]]
		]);
	}

	/**
	 * enable timer
	 * @param $timerid
	 * @return bool
	 */
	public function EnableTimer(string $timerid)
	{
		return $this->RequestData('upd_timer', [
			'params' => [$timerid, 'on']
		]);
	}

	/**
	 * disable timer
	 * @param $timerid
	 * @return bool
	 */
	public function DisableTimer(string $timerid)
	{
		return $this->RequestData('upd_timer', [
			'params' => [$timerid, 'off']
		]);
	}

	/**
	 * get timer details
	 * @return array
	 */
	public function Get_Timer_Details()
	{
		return $this->RequestData('get_timer');
	}

	/**
	 * delete a timer
	 * @param $timerid
	 * @return bool
	 */
	public function DeleteTimer(string $timerid)
	{
		return $this->RequestData('del_timer', [$timerid]);
	}

	/**
	 * get timezone
	 * @return string
	 */
	public function GetTimezone()
	{
		return $this->RequestData('get_timezone');
	}

	/**
	 * set timezone to europe
	 * @return bool
	 */
	public function SetTimezoneEurope()
	{
		return $this->RequestData('set_timezone', ['Europe/Amsterdam']);
	}

	/**
	 * install *.pkg sound package by url
	 * @param string $sound_url
	 * @return bool
	 */
	protected function InstallSound(string $sound_url)
	{
		return $this->RequestData('dnld_install_sound', [
			'params' => [$sound_url]
		]);
	}

	/**
	 * set sound level
	 * @param int $level
	 * @return bool
	 */
	public function SetSoundLevel(int $level)
	{
		return $this->RequestData('get_current_sound', [
			'params' => [$level]
		]);
	}

	/**
	 * Get fan power
	 * @return int
	 */
	public function Get_Fan_Power()
	{
		return $this->RequestData('get_custom_mode');
	}

	/**
	 * set fan power (Quiet=38, Balanced=60, Turbo=77, Full Speed=90)
	 * @param int $power
	 * @return bool
	 */
	public function Set_Fan_Power(int $power)
	{
		$this->SetRoborockValue('fan_power', $power);
		return $this->RequestData('set_custom_mode', [
			'params' => [$power]
		]);
	}

	/**
	 * move robot to direction
	 * @param int $direction -100..100
	 * @param int $velocity 0..100
	 * @param int|null $time in ms
	 * @return bool
	 */
	public function Move_Direction(int $direction, int $velocity, int $time = NULL)
	{
		if (empty($time)) {
			$time = 1000;
		}

		$this->StartRemoteControl();
		$result = $this->RequestData('app_rc_move', [
			'params' => [
				'omega' => $direction,
				'velocity' => $velocity,
				'seqnum' => 'sequence',
				'duration' => $time
			]
		]);
		$this->StopRemoteControl();

		return $result;
	}

	/**
	 * start remote control
	 * @return bool
	 */
	protected function StartRemoteControl()
	{
		return $this->RequestData('app_rc_start');
	}

	/**
	 * stop remote control
	 * @return bool
	 */
	protected function StopRemoteControl()
	{
		return $this->RequestData('app_rc_end');
	}

	/**
	 * get current gateway
	 * @return bool|mixed
	 */
	public function GetGateway()
	{
		return $this->RequestData('get_gateway');
	}

	/**
	 * Update Firmware Over Air
	 * @param string $firmware
	 * @return array|bool
	 */
	public function UpdateFirmwareOverAir(string $firmware)
	{
		$ip = $this->GetHostIP();
		$port = 3777;
		return $this->RequestData('miIO.ota', [
			'params' => [
				'mode' => 'normal',
				'install' => '1',
				'app_url' => 'http://' . $ip . ':' . $port . '/user/roborock/' . $firmware,
				'file_md5' => md5($firmware),
				'proc' => 'dnld install'
			]
		]);
	}

	/**
	 * Get IP IP-Symcon
	 * @return string
	 */
	protected function GetHostIP()
	{
		$ip = exec("sudo ifconfig eth0 | grep 'inet Adresse:' | cut -d: -f2 | awk '{ print $1}'");
		if ($ip == "") {
			$ipinfo = Sys_GetNetworkInfo();
			$ip = $ipinfo[0]['IP'];
		}
		return $ip;
	}

	/**
	 * Update Firmware Over Air Progress
	 * @return array|bool
	 */
	public function UpdateFirmwareOverAirProgress()
	{
		$ota_progress = $this->RequestData('miIO.get_ota_progress')[0];
		return $ota_progress;
	}

	/**
	 * Update Firmware Over Air Status
	 * @return array|bool
	 */
	public function UpdateFirmwareOverAirStatus()
	{
		$ota_state = $this->RequestData('miIO.get_ota_state')[0];
		return $ota_state;
	}

	/**
	 * Roborock Vacuum 2 clean zone with coordinates for area, use a rectangle with values for the lower left corner and the upper right corner
	 * @param int $lower_left_corner_x
	 * @param int $lower_left_corner_y
	 * @param int $upper_right_corner_x
	 * @param int $upper_right_corner_y
	 * @param int $number
	 * @return bool
	 */
	public function ZoneClean(int $lower_left_corner_x, int $lower_left_corner_y, int $upper_right_corner_x, int $upper_right_corner_y, int $number)
	{
		return $this->RequestData('app_zoned_clean', [
			'params' => [[
				$lower_left_corner_x,
				$lower_left_corner_y,
				$upper_right_corner_x,
				$upper_right_corner_y,
				$number
			]]
		]);
	}


	/** Roborock Vacuum 2 clean multiple zone with coordinates for area, use a rectangle with values for the lower left corner and the upper right corner
	 * $multizone = '[['.$lower_left_corner_x.','. $lower_left_corner_y.','. $upper_right_corner_x.','. $upper_right_corner_y.','. $number.'],['. $lower_left_corner_x1.','. $lower_left_corner_y1.','.	$upper_right_corner_x1.','. $upper_right_corner_y1.','. $number.']]';
	 * @param string $multizone
	 * @return array|bool
	 */
	public function ZoneCleanMulti(string $multizone)
	{
		$multizone = json_decode($multizone, true);
		return $this->RequestData('app_zoned_clean', [
			'params' => $multizone
		]);
	}



	/**
	 * Roborock Vacuum 2 go to coordinates
	 * @param float $x
	 * @param float $y
	 * @return bool
	 */
	public function GotoTarget(float $x, float $y)
	{
		return $this->RequestData('app_goto_target', [
			'params' => [
				$x,
				$y
			]
		]);
	}

	/**
	 * get device info
	 * @return array
	 */
	public function GetDeviceInfo()
	{
		return $this->RequestData('miIO.info');
	}

	/**
	 * toggle remote control
	 * @param bool $state
	 */
	public function Toggle_State(bool $state)
	{
		if ($state) {
			$this->Start();
		} else {
			$this->Stop();
		}
	}

	/**
	 * enable, disable or delete an existing timer
	 * @param string $command on|off|delete
	 * @param int $timerid
	 */
	protected function Change_Timer(string $command, $timerid)
	{
		if ($command == 'on') {
			$this->EnableTimer($timerid);
		} elseif ($command == 'off') {
			$this->DisableTimer($timerid);
		} elseif ($command == 'delete') {
			$this->DeleteTimer($timerid);
		}
	}

	// @ToDo: change timer

	/**
	 * change the time for an existing timer
	 * @param string $time
	 * @return bool
	 */
	public function Change_Timer_Time(string $time)
	{
		$payload = '' . $time;
		return $this->RequestData($payload);
	}

	// @ToDo: change timer

	/**
	 * change the days for an existing timer
	 * @param string $date
	 * @return bool
	 */
	public function Change_Timer_Date(string $date)
	{
		$payload = '' . $date;
		return $this->RequestData($payload);
	}

	/**
	 * enable / disable dnd mode
	 * @param bool $state
	 */
	public function Set_DND(bool $state)
	{
		$this->SetRoborockValue('dnd_mode', $state);

		if ($state) {
			$start_time_string = GetValueFormatted($this->GetIDForIdent('dnd_starttime'));
			$time = explode(':', $start_time_string);
			$start_hour = (int)$time[0];
			$start_minutes = (int)$time[1];
			$end_time_string = GetValueFormatted($this->GetIDForIdent('dnd_endtime'));
			$time = explode(':', $end_time_string);
			$end_hour = (int)$time[0];
			$end_minutes = (int)$time[1];
			$this->SetDNDTimer($start_hour, $start_minutes, $end_hour, $end_minutes);
		} else {
			$this->DisableDND();
		}
	}

	/**
	 * set dnd start time
	 * @param string $starttime
	 */
	public function Set_DND_Start(string $starttime)
	{
		$unixtime = strtotime($starttime);
		$this->Set_DND_StartInt($unixtime);
	}

	protected function Set_DND_StartInt($starttime)
	{
		$start_hour = (int)date('H', $starttime);
		$start_minutes = (int)date('i', $starttime);
		$this->SetRoborockValue('dnd_starttime', $starttime);
		$end_time_string = GetValueFormatted($this->GetIDForIdent('dnd_endtime'));
		$time = explode(':', $end_time_string);
		$end_hour = (int)$time[0];
		$end_minutes = (int)$time[1];
		$this->SetDNDTimer($start_hour, $start_minutes, $end_hour, $end_minutes);
	}

	/**
	 * set dnd end time
	 * @param string $endtime
	 */
	public function Set_DND_End(string $endtime)
	{
		$unixtime = strtotime($endtime);
		$this->Set_DND_EndInt($unixtime);
	}

	protected function Set_DND_EndInt($endtime)
	{
		$end_hour = (int)date('H', $endtime);
		$end_minutes = (int)date('i', $endtime);
		$this->SetRoborockValue('dnd_endtime', $endtime);
		$starttime = GetValueFormatted($this->GetIDForIdent('dnd_starttime'));
		$time = explode(':', $starttime);
		$start_hour = (int)$time[0];
		$start_minutes = (int)$time[1];
		$this->SetDNDTimer($start_hour, $start_minutes, $end_hour, $end_minutes);
	}

	/**
	 * get sounds
	 * @return bool
	 */
	public function Get_Sound()
	{
		return $this->RequestData('get_current_sound');
	}

	/**
	 * get sound volume
	 * @return int
	 */
	public function Get_SoundVolume()
	{
		return $this->RequestData('get_sound_volume');
	}

	/**
	 * set sound volume
	 * @param int $volume
	 * @return bool
	 */
	public function Set_SoundVolume(int $volume)
	{
		$this->SetRoborockValue('volume', $volume);
		return $this->RequestData('change_sound_volume', [
			'params' => [(int)$volume]
		]);
	}

	/**
	 * webfront request actions
	 * @param string $Ident
	 * @param $Value
	 * @return bool|void
	 */
	public function RequestAction($Ident, $Value)
	{
		switch ($Ident) {
			case 'command':
				if ($Value == 0) {
					$this->Start();
				} elseif ($Value == 1) {
					$this->Pause();
				} elseif ($Value == 2) {
					$this->Stop();
				} elseif ($Value == 3) {
					$this->CleanSpot();
				} elseif ($Value == 4) {
					$this->Charge();
				} elseif ($Value == 5) {
					$this->Locate();
				}
				break;
			case 'findme':
				$this->Locate();
				break;
			case 'dnd_mode':
				$this->Set_DND($Value);
				break;
			case 'dnd_starttime':
				$this->Set_DND_StartInt($Value);
				break;
			case 'dnd_endtime':
				$this->Set_DND_EndInt($Value);
				break;
			case 'volume':
				$this->Set_SoundVolume($Value);
				break;
			case 'fan_power':
				$this->Set_Fan_Power($Value);
				break;
			default:
				$this->_debug('request action', 'Invalid $Ident <' . $Ident . '>');
		}
	}

	/**
	 * register profiles
	 * @param $Name
	 * @param $Icon
	 * @param $Prefix
	 * @param $Suffix
	 * @param $MinValue
	 * @param $MaxValue
	 * @param $StepSize
	 * @param $Digits
	 * @param $Vartype
	 */
	protected function RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Vartype)
	{

		if (!IPS_VariableProfileExists($Name)) {
			IPS_CreateVariableProfile($Name, $Vartype); // 0 boolean, 1 int, 2 float, 3 string,
		} else {
			$profile = IPS_GetVariableProfile($Name);
			if ($profile['ProfileType'] != $Vartype) {
				$this->_debug('profile', 'Variable profile type does not match for profile ' . $Name);
			}
		}

		IPS_SetVariableProfileIcon($Name, $Icon);
		IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
		IPS_SetVariableProfileDigits($Name, $Digits); //  Nachkommastellen
		IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize); // string $ProfilName, float $Minimalwert, float $Maximalwert, float $Schrittweite
	}

	/**
	 * register profile association
	 * @param $Name
	 * @param $Icon
	 * @param $Prefix
	 * @param $Suffix
	 * @param $MinValue
	 * @param $MaxValue
	 * @param $Stepsize
	 * @param $Digits
	 * @param $Vartype
	 * @param $Associations
	 */
	protected function RegisterProfileAssociation($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype, $Associations)
	{
		if (is_array($Associations) && sizeof($Associations) === 0) {
			$MinValue = 0;
			$MaxValue = 0;
		}
		$this->RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype);

		if (is_array($Associations)) {
			foreach ($Associations AS $Association) {
				IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
			}
		} else {
			$Associations = $this->$Associations;
			foreach ($Associations AS $code => $association) {
				IPS_SetVariableProfileAssociation($Name, $code, $this->Translate($association), $Icon, -1);
			}
		}

	}

	/**
	 * checks, if configuration is complete
	 * @return bool
	 */
	private function CheckConfiguration()
	{
		// if token is valid, everything is ok
		if ($this->ReadPropertyString('token')) {
			return true;
		}

		if (
			// configuration is not finished
			$this->ReadPropertyInteger('token_mode') === -1
			// token mode: Xiaomi App
			|| (
				$this->ReadPropertyInteger('token_mode') == 1
				&& (
					!$this->ReadPropertyString('xiaomi_email')
					|| !$this->ReadPropertyString('xiaomi_pass')
					|| !$this->GetTokenFromXiaomi()
				)
			)
			// token mode: WiFi Discover
			|| (
				$this->ReadPropertyInteger('token_mode') == 2
				&& (
					!$this->ReadPropertyBoolean('wifi_connected')
					|| !$this->GetTokenFromDiscover()
				)
			)
			// token mode: enter ip & token manually
			|| (
				$this->ReadPropertyInteger('token_mode') == 3
				&& (
					!$this->ReadPropertyString('ip')
					|| !$this->ReadPropertyString('token')
				)
			)
		) {
			return false;
		}

		return true;
	}

	public function SendPushNotificationTest(int $state_id, int $error_id, bool $force_send)
	{
		$this->SendPushNotification($state_id, $error_id, $force_send);
	}

	/**
	 * Send push notifications
	 * @param string $state_id
	 * @param int $error_id
	 * @param bool $force_send
	 * @return bool
	 */
	protected function SendPushNotification($state_id = 'errors', $error_id = 0, $force_send = false)
	{
		// get codes by state_id
		if ($state_id == 'errors') {
			$codes = $this->error_codes;
			$state_id = $error_id;
			$prefix = $this->Translate('Error') . ': ';

			$notification_ident = 'last_notification_error';
		} else {
			$codes = $this->state_codes;
			$prefix = '';

			$notification_ident = 'last_notification_state';
		}

		// check notification
		$last_notification = GetValueString($this->GetIDForIdent($notification_ident));
		$this->SetRoborockValue($notification_ident, $state_id);

		// return false, when last notification is the same as current notification or id is 0
		if (($last_notification == $state_id && !$force_send) || $state_id == 0) {
			return false;
		}

		// check notification instance (webfront)
		if ($instance_id = $this->ReadPropertyInteger('notification_instance')) {
			// get notification settings
			if ($notifications = @json_decode($this->ReadPropertyString('notifications'), true)) {
				// loop notifications and search for current state
				foreach ($notifications AS $notification) {
					if ($notification['state_id'] == $state_id) {
						// check if notification is enabled
						if ($notification['enabled'] || $force_send) {
							// send notification
							if ($state_id > 0 && isset($codes[$state_id])) {
								// build message
								$title = IPS_GetName($this->InstanceID); // instance name
								$message = $prefix . $this->Translate($codes[$state_id]);

								// send notification
								WFC_PushNotification($instance_id, $title, $message, $notification['sound'], 0);
							}
						}

						// break loop
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get push notifications
	 * @return string json encoded settings
	 */
	protected function GetPushNotifications()
	{
		// translate default notifications
		$notifications = $this->push_notifications;
		foreach ($notifications AS &$notification) {
			$notification['name'] = $this->Translate($notification['name']);
		}

		// merge with current settings
		if ($current_notifications = @$this->ReadPropertyString('notifications')) {
			$current_notifications = json_decode($current_notifications, true);
			foreach ($current_notifications AS $current) {
				// loop and replace settings
				foreach ($notifications AS &$n)
					if ($n['state_id'] == $current['state_id']) {
						$n['sound'] = $current['sound'];
						$n['enabled'] = $current['enabled'];

						break;
					}
			}
		}

		return json_encode($notifications);
	}



	/***********************************************************
	 * Configuration Form
	 ***********************************************************/

	/**
	 * build configuration form
	 * @return string
	 */
	public function GetConfigurationForm()
	{
		// update status, when configuration is not complete
		if (!$this->CheckConfiguration()) {
			$this->SetStatus(201);
		}

		// return current form
		return json_encode([
			'elements' => $this->FormHead(),
			'actions' => $this->FormActions(),
			'status' => $this->FormStatus()
		]);
	}

	/**
	 * return form configurations on configuration step
	 * @return array
	 */
	protected function FormHead()
	{
		$token = $this->ReadPropertyString('token');
		$token_mode = $this->ReadPropertyInteger('token_mode');

		$form = [
			[
				'type' => 'Image',
				'image' => 'data:image/png;base64, iVBORw0KGgoAAAANSUhEUgAAAHoAAAB4CAYAAAA9kebvAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA3ZpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDoxZmUzOTk2NS0wMDg2LWNmNDUtOWYyZS0xMzk3NmM5NDBkN2QiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6QzMwQzZBODRGQTNGMTFFODg1RkFENjI2NjgwQjI1OEQiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6QzMwQzZBODNGQTNGMTFFODg1RkFENjI2NjgwQjI1OEQiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENDIDIwMTUgKFdpbmRvd3MpIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6MWZlMzk5NjUtMDA4Ni1jZjQ1LTlmMmUtMTM5NzZjOTQwZDdkIiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjFmZTM5OTY1LTAwODYtY2Y0NS05ZjJlLTEzOTc2Yzk0MGQ3ZCIv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'
			],
			[
				'name' => 'token_mode',
				'type' => 'Select',
				'caption' => 'Token Mode',
				'options' => [
					[
						'label' => 'Please choose',
						'value' => -1
					],
					/*
						[
							'label' => 'Xiaomi Home Login (not working)',
							'value' => 1
						],
						[
							'label' => 'WiFi Discover',
							'value' => 2
						],
					*/
					[
						'label' => 'IP & Token',
						'value' => 3
					]
				]
			]
		];

		if ($token_mode == 1) {
			$form = array_merge_recursive(
				$form,
				[
					[
						'type' => 'Label',
						'label' => 'Enter the credentials of your Xiaomi Home Account below.'
					],
					[
						'name' => 'xiaomi_email',
						'type' => 'ValidationTextBox',
						'caption' => 'E-Mail'
					],
					[
						'name' => 'xiaomi_pass',
						'type' => 'PasswordTextBox',
						'caption' => 'Password'
					],
				]
			);
		} else if ($token_mode == 2) {
			$form = array_merge_recursive(
				$form,
				[
					[
						'type' => 'Label',
						'label' => '1. Reset the wifi of your robot by pressing the power and home button for 5 seconds simultaneously.'
							. "\r\n"
							. '2. Connect the wifi to the hotspot of your robot, e.g. rockrobo-vacuum-v1_miapXXXX'
							. "\r\n"
							. '3. Confirm checkbox below and apply changes'
					],
					[
						'type' => 'Label',
						'label' => ''
					],
					[
						'name' => 'wifi_connected',
						'type' => 'CheckBox',
						'caption' => 'connected to rockrobo hotspot.'
					]
				]
			);
		} else if ($token_mode == 3) {
			$form = array_merge_recursive(
				$form,
				[
					[
						'type' => 'Label',
						'label' => 'Enter the ip address and token of your robot.'
					],
					[
						'name' => 'ip',
						'type' => 'ValidationTextBox',
						'caption' => 'IP address Roborock'
					],
					[
						'name' => 'token',
						'type' => 'ValidationTextBox',
						'caption' => 'Token'
					]
				]
			);
		}

		if ($token) {
			$form = array_merge_recursive(
				$form,
				[
					[
						'type' => 'Label',
						'label' => 'Update Interval Roborock'
					],
					[
						'name' => 'UpdateInterval',
						'type' => 'IntervalBox',
						'caption' => 'Seconds'
					],
					[
						'type' => 'ExpansionPanel',
						'caption' => 'Push Notifications',
						'items' => [
							[
								'type' => 'Label',
								'label' => 'Push Notifications'
							],
							[
								'name' => 'notification_instance',
								'type' => 'SelectInstance',
								'caption' => 'Webfront Configurator'
							],
							[
								'type' => 'List',
								'name' => 'notifications',
								'caption' => 'Push Notifications',
								'rowCount' => count($this->push_notifications),
								'add' => false,
								'delete' => false,
								'sort' => [
									'column' => 'name',
									'direction' => 'ascending'
								],
								'columns' => [
									[
										'name' => 'enabled',
										'label' => 'Enabled',
										'width' => '100px',
										'edit' => [
											'type' => 'CheckBox',
											'caption' => 'Enable Push Notification'
										]
									],
									[
										'name' => 'name',
										'label' => 'Notification',
										'width' => 'auto',
										'save' => true
									],
									[
										'name' => 'sound',
										'label' => 'Notification Sound',
										'width' => '170px',
										'edit' => [
											'type' => 'Select',
											'options' => [
												[
													'label' => 'default',
													'value' => ''
												],
												[
													'label' => 'alarm',
													'value' => 'alarm'
												],
												[
													'label' => 'bell',
													'value' => 'bell'
												],
												[
													'label' => 'boom',
													'value' => 'boom'
												],
												[
													'label' => 'buzzer',
													'value' => 'buzzer'
												],
												[
													'label' => 'connected',
													'value' => 'connected'
												],
												[
													'label' => 'dark',
													'value' => 'dark'
												],
												[
													'label' => 'digital',
													'value' => 'digital'
												],
												[
													'label' => 'drums',
													'value' => 'drums'
												],
												[
													'label' => 'duck',
													'value' => 'duck'
												],
												[
													'label' => 'full',
													'value' => 'full'
												],
												[
													'label' => 'happy',
													'value' => 'happy'
												],
												[
													'label' => 'horn',
													'value' => 'horn'
												],
												[
													'label' => 'inception',
													'value' => 'inception'
												],
												[
													'label' => 'kazoo',
													'value' => 'kazoo'
												],
												[
													'label' => 'roll',
													'value' => 'roll'
												],
												[
													'label' => 'siren',
													'value' => 'siren'
												],
												[
													'label' => 'space',
													'value' => 'space'
												],
												[
													'label' => 'trickling',
													'value' => 'trickling'
												],
												[
													'label' => 'turn',
													'value' => 'turn'
												]
											]
										]
									],
									[
										'name' => 'state_id',
										'label' => 'State ID',
										'width' => 'auto',
										'save' => true,
										'visible' => false
									]
								]
							]
						]
					],
					[
						'type' => 'ExpansionPanel',
						'caption' => 'Enabled options',
						'items' => [
							[
								'type' => 'Label',
								'label' => 'Enabled options'
							],
							[
								'name' => 'fan_power',
								'type' => 'CheckBox',
								'caption' => 'Fan Power'
							],
							[
								'name' => 'error_code',
								'type' => 'CheckBox',
								'caption' => 'Error Code'
							],
							[
								'name' => 'consumables',
								'type' => 'CheckBox',
								'caption' => 'Consumables'
							],
							[
								'name' => 'consumables_separate',
								'type' => 'CheckBox',
								'caption' => 'Consumables (Separate Variables)'
							],
							[
								'name' => 'dnd_mode',
								'type' => 'CheckBox',
								'caption' => 'DND Mode (Do not disturb)'
							],
							[
								'name' => 'clean_area',
								'type' => 'CheckBox',
								'caption' => 'Clean Area'
							],
							[
								'name' => 'clean_time',
								'type' => 'CheckBox',
								'caption' => 'Clean Time'
							],
							[
								'name' => 'total_cleans',
								'type' => 'CheckBox',
								'caption' => 'Total Cleans'
							],
							[
								'name' => 'serial_number',
								'type' => 'CheckBox',
								'caption' => 'Serial Number'
							],
							[
								'name' => 'timer_details',
								'type' => 'CheckBox',
								'caption' => 'Timer Details'
							],
							[
								'name' => 'findme',
								'type' => 'CheckBox',
								'caption' => 'find robot'
							],
							[
								'name' => 'volume',
								'type' => 'CheckBox',
								'caption' => 'Volume'
							],
							[
								'name' => 'timezone',
								'type' => 'CheckBox',
								'caption' => 'Timezone'
							],
							[
								'name' => 'remote',
								'type' => 'CheckBox',
								'caption' => 'Remote Control'
							]
						]
					],
					[
						'type' => 'ExpansionPanel',
						'caption' => 'Install scripts',
						'items' => $this->SelectionSkripts()
					]
				]
			);
		}
		return $form;
	}

	protected function SelectionSkripts()
	{
		$setup_scripts = $this->ReadPropertyBoolean("setup_scripts");
		$form = [
			[
				'type' => 'Label',
				'label' => 'Install scripts'
			],
			[
				'name' => 'setup_scripts',
				'type' => 'CheckBox',
				'caption' => 'Setup scripts'
			]
		];
		if ($setup_scripts) {
			$form = array_merge_recursive(
				$form,
				[
					[
						'name' => 'script_category',
						'type' => 'SelectCategory',
						'caption' => 'Script category'
					]
				]
			);
		}
		return $form;
	}

	/**
	 * return form actions by token
	 * @return array
	 */
	protected function FormActions()
	{
		$token = $this->ReadPropertyString('token');
		$token_mode = $this->ReadPropertyInteger('token_mode');

		$form = [];
		if ($token_mode == 1) {
			$form = [
				[
					'type' => 'Button',
					'label' => 'Xiaomi Login Test',
					'onClick' => 'Roborock_GetTokenFromXiaomi($id);'
				]
			];
		} else if ($token) {
			$form = [
				[
					'type' => 'Button',
					'label' => 'Update',
					'onClick' => 'Roborock_Update($id);'
				],
				[
					'type' => 'Button',
					'label' => 'find robot',
					'onClick' => 'Roborock_Locate($id);'
				],
				[
					'type' => 'Button',
					'label' => 'Start',
					'onClick' => 'Roborock_Start($id);'
				],
				[
					'type' => 'Button',
					'label' => 'Stop',
					'onClick' => 'Roborock_Stop($id);'
				],
				[
					'type' => 'Button',
					'label' => 'Pause',
					'onClick' => 'Roborock_Pause($id);'
				],
				[
					'type' => 'Button',
					'label' => 'Charge',
					'onClick' => 'Roborock_Charge($id);'
				],
				[
					'type' => 'Button',
					'label' => 'Clean Spot',
					'onClick' => 'Roborock_CleanSpot($id);'
				],
				[
					'type' => 'Button',
					'label' => 'Reset Filter',
					'onClick' => 'Roborock_Reset_Filter($id);'
				],
				[
					'type' => 'Button',
					'label' => 'Reset Mainbrush',
					'onClick' => 'Roborock_Reset_Mainbrush($id);'
				],
				[
					'type' => 'Button',
					'label' => 'Reset Sidebrush',
					'onClick' => 'Roborock_Reset_Sidebrush($id);'
				],
				[
					'type' => 'Button',
					'label' => 'Reset Sensors',
					'onClick' => 'Roborock_Reset_Sensors($id);'
				],
				[
					'type' => 'Button',
					'label' => 'Push Notification Test',
					'onClick' => 'Roborock_SendPushNotificationTest($id, 5, 0, true);'
				],
				/*
					[
						'type' => 'Button',
						'label' => 'Update Joystick - TEST',
						'onClick' => 'Roborock_SetJoystickHtml($id);'
					]
				*/
			];
		}

		return $form;
	}

	/**
	 * return from status
	 * @return array
	 */
	protected function FormStatus()
	{
		$form = [
			[
				'code' => 101,
				'icon' => 'inactive',
				'caption' => 'Creating instance.'
			],
			[
				'code' => 102,
				'icon' => 'active',
				'caption' => 'Roborock created.'
			],
			[
				'code' => 104,
				'icon' => 'inactive',
				'caption' => 'interface closed.'
			],
			[
				'code' => 201,
				'icon' => 'inactive',
				'caption' => 'Please follow the instructions.'
			],
			[
				'code' => 202,
				'icon' => 'error',
				'caption' => 'IP address must not empty.'
			],
			[
				'code' => 203,
				'icon' => 'error',
				'caption' => 'No valid IP address.'
			],
			[
				'code' => 204,
				'icon' => 'error',
				'caption' => 'connection to Roborock lost.'
			],
			[
				'code' => 205,
				'icon' => 'error',
				'caption' => 'field must not be empty.'
			],
			[
				'code' => 206,
				'icon' => 'inactive',
				'caption' => 'no roborock was found on that ip and token.'
			],
			[
				'code' => 207,
				'icon' => 'inactive',
				'caption' => 'Please set up wifi settings on your robot and enter the new ip address.'
			],
			[
				'code' => 208,
				'icon' => 'inactive',
				'caption' => 'Token must have a lenght of 32 or 96.'
			],
			[
				'code' => 209,
				'icon' => 'error',
				'caption' => 'no category selected.'
			]
		];

		return $form;
	}

	/***********************************************************
	 * Helper methods
	 ***********************************************************/

	/**
	 * updates remote variable with joystick html
	 */
	public function SetJoystickHtml()
	{
		$joystick = file_get_contents(__ROOT__ . '/libs/joystick.html');
		$joystick = str_replace('[instance_id]', $this->InstanceID, $joystick);
		$this->SetValue('remote', $joystick);
	}

	/**
	 * check for variable and set value
	 * @param $ident
	 * @param $value
	 */
	private function SetRoborockValue($ident, $value)
	{
		if (@$this->GetIDForIdent($ident))
			$this->SetValue($ident, $value);
	}

	/**
	 * convert time into unix time
	 * @param $time
	 * @return false|int
	 */
	private function _convertToUnixtime($time)
	{
		$timestring = gmdate('H:i:s', $time);
		$unixtime = strtotime($timestring);

		return $unixtime;
	}

	/**
	 * convert seconds to human readable time
	 * @param int $inputSeconds
	 * @return string
	 */
	private function _convertSecondsToTime($inputSeconds = 0)
	{
		$secondsInAMinute = 60;
		$secondsInAnHour = 60 * $secondsInAMinute;
		$secondsInADay = 24 * $secondsInAnHour;

		// extract days
		$days = floor($inputSeconds / $secondsInADay);

		// extract hours
		$hourSeconds = $inputSeconds % $secondsInADay;
		$hours = floor($hourSeconds / $secondsInAnHour);

		// extract minutes
		$minuteSeconds = $hourSeconds % $secondsInAnHour;
		$minutes = floor($minuteSeconds / $secondsInAMinute);

		// extract the remaining seconds
		$remainingSeconds = $minuteSeconds % $secondsInAMinute;
		$seconds = ceil($remainingSeconds);

		// build time
		$time = '';
		if ($days) {
			$time .= ', ' . $days . ' ' . $this->Translate('Day' . ($days == 1 ? '' : 's'));
		}

		if ($hours) {
			$time .= ', ' . $hours . ' ' . $this->Translate('Hour' . ($hours == 1 ? '' : 's'));
		}

		if ($minutes) {
			$time .= ', ' . $minutes . ' ' . $this->Translate('Minute' . ($minutes == 1 ? '' : 's'));
		}

		if (empty($time)) {
			$time .= ', ' . $seconds . ' ' . $this->Translate('Second' . ($seconds == 1 ? '' : 's'));
		}

		return substr($time, 2);
	}

	/**
	 * add leading zeros to number
	 * @param int|string $number
	 * @param int $padding
	 * @return string
	 */
	private function _zeroPadding($number, $padding = 2)
	{
		return str_pad((string)$number, $padding, '0', STR_PAD_LEFT);
	}

	/**
	 * send debug log
	 * @param string $notification
	 * @param string $message
	 * @param int $format 0 = Text, 1 = Hex
	 */
	private function _debug(string $notification = NULL, string $message = NULL, $format = 0)
	{
		$this->SendDebug($notification, $message, $format);
	}

	/**
	 * merge arrays with key attention
	 * @param array $data Array to be merged
	 * @param mixed $merge Array to merge with. The argument and all trailing arguments will be array cast when merged
	 * @return array Merged array
	 */
	private function _merge(array $data, $merge)
	{
		$args = array_slice(func_get_args(), 1);
		$return = $data;

		foreach ($args as &$curArg) {
			$stack[] = [(array)$curArg, &$return];
		}
		unset($curArg);

		while (!empty($stack)) {
			foreach ($stack as $curKey => &$curMerge) {
				foreach ($curMerge[0] as $key => &$val) {
					if (!empty($curMerge[1][$key]) && (array)$curMerge[1][$key] === $curMerge[1][$key] && (array)$val === $val) {
						$stack[] = [&$val, &$curMerge[1][$key]];
					} elseif ((int)$key === $key && isset($curMerge[1][$key])) {
						$curMerge[1][] = $val;
					} else {
						$curMerge[1][$key] = $val;
					}
				}
				unset($stack[$curKey]);
			}
			unset($curMerge);
		}
		return $return;
	}

	/**
	 * return incremented position
	 * @return int
	 */
	private function _getPosition()
	{
		$this->position++;
		return $this->position;
	}

	/**
	 * convert data array to html table
	 * @param array $data
	 * @return string
	 */
	private function _convertDataToTable($data = [])
	{
		$prepend = isset($values['prepend']) ? $data['prepend'] : '';

		// build table
		$html = <<<EOF
                <style>
                    .robotable th,
                    .robotable td {
                        padding: .5em .8em;
                    }
                    .robotable .th { text-align:left; white-space: nowrap; }
                    .robotable tr.th:nth-child(odd) {background: rgba(0,0,0,0.4)}
                    .robotable tr:nth-child(odd) {background: rgba(0,0,0,0.2)}
                    .unicode, {border:0;background:transparent;padding:0;color:#FFF;text-decoration:none}
                    .unicode.red {color:red}
                    .unicode.green {color:green}
                    .separator { background: rgba(0,0,0,0.3);font-weight:bold;font-size:1.2em }
                </style>
                $prepend
			<table class="robotable" cellpadding="0" cellspacing="0" width="100%">
EOF;

		// build table head
		if (isset($data['table']['head'])) {
			$html .= '<tr class="th">';
			foreach ($data['table']['head'] AS $th) {
				$options = '';
				if (is_array($th)) {
					$options = ' ' . $th[1];
					$th = $th[0];
				}

				$html .= '<th class="th" ' . $options . '>' . $this->Translate($th) . '</th>';
			}

			$html .= '</tr>';
		}

		// build table body
		if (isset($data['table']['head'])) {
			foreach ($data['table']['body'] AS $tr) {
				$html .= '<tr>';
				foreach ($tr AS $td) {
					$options = '';
					if (is_array($td)) {
						$options = ' ' . $td[1];
						$td = $td[0];
					}

					$html .= '<td' . $options . '>' . $this->Translate($td) . '</td>';
				}

				$html .= '</tr>';
			}
		}

		$html .= '</table>';

		return $html;
	}

	/**
	 * Get description timer day
	 * @param $day_of_week
	 * @return string
	 */
	private function _getTimerDay($day_of_week)
	{
		if ($day_of_week == "*") {
			$timer_day = "once";
		} elseif ($day_of_week == "1,2,3,4,5") {
			$timer_day = "weekdays";
		} elseif ($day_of_week == "0,6") {
			$timer_day = "weekends";
		} elseif ($day_of_week == "0,1,2,3,4,5,6") {
			$timer_day = "every day";
		} else {
			$timer_day = "custom";
		}
		$timer_day = $this->Translate($timer_day);
		return $timer_day;
	}


	/**
	 * Get repetition for timer
	 * @param $repetitionstring
	 * @return string
	 */
	private function _getTimerRepetition($repetitionstring)
	{
		if ($repetitionstring == "once") {
			$repetition = "*";
		} elseif ($repetitionstring == "weekdays") {
			$repetition = "1,2,3,4,5";
		} elseif ($repetitionstring == "weekends") {
			$repetition = "0,6";
		} elseif ($repetitionstring == "every day") {
			$repetition = "0,1,2,3,4,5,6";
		} else {
			$repetition = "*";
		}
		return $repetition;
	}

	/**
	 * get token by discovering 192.168.8.1
	 * @return bool
	 */
	private function GetTokenFromDiscover()
	{
		// check option if wifi is connected to robots hotspot
		if (!$this->ReadPropertyBoolean('wifi_connected')) {
			return false;
		}

		// discover device & retrieve token
		$token = $this->RequestData('discover', [
			'ip' => '192.168.8.1',
			'immediate' => true
		]);

		// reset wifi_connected checkbox
		IPS_SetProperty($this->InstanceID, 'wifi_connected', false);

		// apply changes
		IPS_ApplyChanges($this->InstanceID);

		// check token
		if ($token && strlen($token) === 32) {
			// save ip & token
			IPS_SetProperty($this->InstanceID, 'ip', '192.168.8.1');
			IPS_SetProperty($this->InstanceID, 'token', $token);

			// set token mode to manual
			IPS_SetProperty($this->InstanceID, 'token_mode', 3);

			// apply changes
			IPS_ApplyChanges($this->InstanceID);

			echo sprintf($this->Translate('Token %s were found! Please configure the Wifi in Mi Home app now and update IP-Address.'), $token);
			exit(-1);
		}

		return strlen($token) == 32;
	}

	// Xiaomi App Login Test
	public function GetTokenFromXiaomi()
	{
		// read properties
		$email = $this->ReadPropertyString('xiaomi_email');
		$pass = $this->ReadPropertyString('xiaomi_pass');

		// urls
		$login_url = 'https://account.xiaomi.com/pass/serviceLogin?sid=xiaomiio&_json=true';
		$login_action = 'https://account.xiaomi.com/pass/serviceLoginAuth2';

		// params
		$useragent = 'Android-7.0-5.1.5-SMG950F-G950FXXU1AQL5-03D9BEC8F7BEB3CD6393F80F8D29E24E5D5EAA20-665C1350487FABCA59BB28C012593A65-1290191941 APP/xiaomi.smartphone';

		// init curl
		$ch = curl_init($login_url);

		// set options
		curl_setopt_array($ch, [
			CURLOPT_TIMEOUT => 10,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HTTPHEADER => [
				'User-Agent: ' . $useragent,
				'deviceId: ' . $this->InstanceID,
				'Cookie: sdkVersion=Android-0.0.0-Alpha; userId=' . $email
			]
		]);

		// set login post fields
		$loginData = [
			'_json' => true,
			'callback' => NULL,
			'sid' => NULL,
			'qs' => NULL,
			'_sign' => NULL,
			'serviceParam' => json_encode([
				'checkSafePhone' => false
			]),
			'user' => $email,
			'hash' => strtoupper(md5($pass)),
		];

		// call login page
		$params = curl_exec($ch);
		$params = json_decode(str_replace('&&&START&&&', '', $params), true);

		foreach ($params AS $param => $value) {
			if (array_key_exists($param, $loginData) && is_null($loginData[$param])) {
				$loginData[$param] = $value;
			}
		}

		// login
		usleep(rand(200, 1000));

		curl_setopt_array($ch, [
			CURLOPT_REFERER => $login_url,
			CURLOPT_URL => $login_action,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($loginData),
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HTTPHEADER => [
				'User-Agent: ' . $useragent
			]
		]);

		curl_exec($ch);

		// get app auth url
		curl_setopt_array($ch, [
			CURLOPT_URL => $login_url,
			CURLOPT_POST => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HTTPHEADER => [
				'User-Agent: ' . $useragent
			]
		]);

		$location = curl_exec($ch);
		$location = json_decode(str_replace('&&&START&&&', '', $location), true);
		$location = $location['location'];

		// auth on app location
		curl_setopt($ch, CURLOPT_URL, $location);
		curl_setopt($ch, CURLOPT_HEADER, true);
		$login = curl_exec($ch);


		if ($login == 'ok') {
			// proceed
		}
		/*
		 *
		 sid=xiaomiio&hash=B392AE630E040A071CAE478383A98AB7&callback=https://api.io.mi.com/sts&qs=%3Fsid%3Dxiaomiio%26_json%3Dtrue
		 envKey=anN7qfiKaEjzYnWDc78Oo8HOIK2p_3mYbNetWPEhD8vsfKOLmVgsXwtaJTKQf7xsf0_1ppfH2ErAM2PJwyZXKwqO3-UfELGlo9Mqfvq_FsQpnowe5oatzJXEnGvbhLQ0FdycN0czkBed-Ni-3HUBiXDyNlr-5tRrpjsLogxErP4=
		 user=frank@codeking.de
		 _sign=t8AemvX80ORqoJmGfHjYAcJr0Ms=
		 env=O18h1ovpSoxvlRrqSFPv2PECdyhsrhHwUzAYiwGMTkWNuIYqfKLlFAl8bpWAztMlvIxdgjNskb05E/RwRVwon9eMGo0CA97lmfZJpdj/wgfPxFyLeHvZrs4phSlnyzpczF512ZAAO5UQLLP8pGt3hyjupxBXwV7zuUNKtfyjsMvbfQGXItpRUo3CRxRMPs4rEibGUJnjnHGbMs5zgXy+mz4gC17xOLt1fMZbjHdcWGut3AIxMIZ+Hj0bjBBne37HSPnbVEPmuF9tqcNoloW0IvYWucaUnFi2yw1u1wXdmoauCzwNPpveiTFUFU2i8W2xSY7snIWCG6kGQJOB5WQBlaQe7BzXvLEAuAnP6NJk9V/uMJmsp9JXR5v9VbmEmJsjPVEVdpl5iGnRqwdRgAanDU1UOMEs9qQGZ+w1xRJhs9bz5B+3x+2shDt3r+eHMCLv
		 _json=true

		data=J2buC7gj7zhYRMk7UZ0PDQmxe/D9x5qgaqNrhMdsAqFVQFEu7k16DzBmryW3jwemsf7L6mEmEvYRpcMUVmTW7QJ6AKqoSJLkMOb2naoQplEZMwaIBV23RPGnzyMWaB4=
		rc4_hash__=xr4OZu0vAVc5PJXYaiNIWn5KJJxvfZLdcyqdoA==
		signature=jtih92ACgiag7y6RxvUYTmwd640
		&_nonce=ddINEdDO7VEBgi8W&ssecurity=9yiDX/On7p9wH+r1fdGgcQ==
		 */
		// get devices
		curl_setopt_array($ch, [
			CURLOPT_URL => 'https://api.io.mi.com/app/location/area_prop_info_v2',
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query([
				'data' => json_encode([
					'area_id' => '101010100'
				])
			]),
		]);

		$devices = curl_exec($ch);

		var_dump('https://api.io.mi.com/app/location/area_prop_info_v2', $devices);
		exit;


		// close curl
		curl_close($ch);

		return false;
	}

	/***********************************************************
	 * Callbacks
	 ***********************************************************/

	/**
	 * Callback: Serial Number
	 * @param array $data
	 * @return string
	 */
	protected function get_serial_number_callback(array $data)
	{
		$serial = isset($data['result'][0]['serial_number']) ? $data['result'][0]['serial_number'] : '';
		$this->SetRoborockValue('serial_number', $serial);

		return $serial;
	}

	/**
	 * Callback: Timezone
	 * @param array $data
	 * @return string
	 */
	protected function get_timezone_callback(array $data)
	{
		$timezone = isset($data['result'][0]) ? $data['result'][0] : '';
		$this->SetRoborockValue('timezone', $timezone);

		return $timezone;
	}

	/**
	 * Callback: Device Info
	 * @param array $data
	 * @return array
	 */
	protected function miio_info_callback(array $data)
	{
		if (isset($data['result'])) {
			$info = $data['result'];

			$hardware_version = $info['hw_ver'];
			$this->SetRoborockValue('hw_ver', $hardware_version);

			$firmware_version = $info['fw_ver'];
			$this->SetRoborockValue('fw_ver', $firmware_version);

			$ssid = $info['ap']['ssid'];
			$this->SetRoborockValue('ssid', $ssid);

			$ip = $info['netif']['localIp'];
			$this->SetRoborockValue('local_ip', $ip);

			$model = $info['model'];
			$this->SetRoborockValue('model', $model);

			$mac = $info['mac'];
			$this->SetRoborockValue('mac', $mac);

			// return values
			return [
				'hardware_version' => $hardware_version,
				'firmware_version' => $firmware_version,
				'ssid' => $ssid,
				'ip' => $ip,
				'model' => $model,
				'mac' => $mac
			];
		}

		// fallback
		return [
			'hardware_version' => NULL,
			'firmware_version' => NULL,
			'ssid' => NULL,
			'ip' => NULL,
			'model' => NULL,
			'mac' => NULL
		];
	}

	/**
	 * Callback: Status
	 * @param array $data
	 * @return array
	 */
	protected function get_status_callback(array $data)
	{
		if (isset($data['result'][0])) {
			// update values
			$battery = intval($data['result'][0]['battery']);
			$this->SetRoborockValue('battery', $battery);

			$state = intval($data['result'][0]['state']);
			if ($state == 8 && $battery == 100) {
				$this->SetRoborockValue('state', 100);
			} else {
				$this->SetRoborockValue('state', $state);
			}

			$clean_area = floatval($data['result'][0]['clean_area']) / 1000000; // cm2 -> m2
			$this->SetRoborockValue('clean_area', $clean_area);

			$clean_time = $this->_convertToUnixtime(intval($data['result'][0]['clean_time'])); // sec
			$this->SetRoborockValue('clean_time', $clean_time);

			$error_code = intval($data['result'][0]['error_code']);
			$this->SetRoborockValue('error_code', $error_code);

			$fan_power = intval($data['result'][0]['fan_power']);
			$this->SetRoborockValue('fan_power', $fan_power);

			// send push notifications
			$this->SendPushNotification($state);
			$this->SendPushNotification('errors', $error_code);

			// return values
			return [
				'state' => $state,
				'battery' => $battery,
				'clean_area' => $clean_area,
				'clean_time' => $clean_time,
				'error_code' => $error_code,
				'fan_power' => $fan_power
			];
		}

		// fallback
		return [
			'state' => NULL,
			'battery' => NULL,
			'clean_area' => NULL,
			'clean_time' => NULL,
			'error_code' => NULL,
			'fan_power' => NULL
		];
	}

	/**
	 * Callback: Consumables
	 * @param array $data
	 * @return array
	 */
	protected function get_consumable_callback(array $data)
	{
		if (isset($data['result'][0])) {
			$total_main_brush_work_time = 300; // hours
			$total_side_brush_work_time = 200; // hours
			$total_filter_work_time = 150; // hours
			$total_sensor_dirty_time = 30; // hours

			$main_brush_work_time = $data['result'][0]['main_brush_work_time'];
			$side_brush_work_time = $data['result'][0]['side_brush_work_time'];
			$filter_work_time = $data['result'][0]['filter_work_time'];
			$sensor_dirty_time = $data['result'][0]['sensor_dirty_time'];

			$main_brush_work_percent = round(100 - (100 / ($total_main_brush_work_time * 3600) * $main_brush_work_time));
			$side_brush_work_percent = round(100 - (100 / ($total_side_brush_work_time * 3600) * $side_brush_work_time));
			$filter_work_percent = round(100 - (100 / ($total_filter_work_time * 3600) * $filter_work_time));
			$sensor_dirty_percent = round(100 - (100 / ($total_sensor_dirty_time * 3600) * $sensor_dirty_time));

			$consumables = [
				[
					$this->Translate('main brush'),
					$main_brush_work_percent . '%'
				],
				[
					$this->Translate('side brush'),
					$side_brush_work_percent . '%'
				],
				[
					$this->Translate('filter'),
					$filter_work_percent . '%'
				],
				[
					$this->Translate('sensor'),
					$sensor_dirty_percent . '%'
				]
			];

			$html = $this->_convertDataToTable([
				'table' => [
					'head' => [
						$this->Translate('Consumable'),
						$this->Translate('Consumption (%)')
					],
					'body' => $consumables
				]
			]);


			// consumables
			if ($this->ReadPropertyBoolean('consumables')) {
				$this->SetRoborockValue('consumables', $html);
			}

			// consumables separate
			if ($this->ReadPropertyBoolean('consumables_separate')) {
				$this->SetRoborockValue('main_brush', $main_brush_work_percent);
				$this->SetRoborockValue('side_brush', $side_brush_work_percent);
				$this->SetRoborockValue('filter', $filter_work_percent);
				$this->SetRoborockValue('sensor', $sensor_dirty_percent);
			}

			// return values
			return [
				'main_brush' => $main_brush_work_percent,
				'side_brush' => $side_brush_work_percent,
				'filter' => $filter_work_percent,
				'sensor_dirty' => $sensor_dirty_percent
			];
		}

		// fallback
		return [
			'main_brush_work_time' => NULL,
			'side_brush_work_time' => NULL,
			'filter_work_time' => NULL,
			'sensor_dirty_time' => NULL
		];
	}

	/**
	 * Callback: Clean Summary
	 * @param array $data
	 * @return array
	 */
	protected function get_clean_summary_callback(array $data)
	{
		if (isset($data['result'][0])) {
			$total_cleaning_time = $this->_convertToUnixtime(intval($data['result'][0])); // sec
			$this->SetRoborockValue('total_clean_time', $total_cleaning_time);

			$area_cleaned = floatval($data['result'][1]) / 1000000; // cm2 -> m2
			$this->SetRoborockValue('total_clean_area', $area_cleaned);

			$cleanups = intval($data['result'][2]);
			$this->SetRoborockValue('total_cleans', $cleanups);

			$clean_records = $data['result'][3];
			$this->SetBuffer('CleanRecords', json_encode($clean_records));

			// update clean record details
			foreach ($clean_records AS $record_id) {
				$this->GetCleanRecord($record_id);
			}

			// return values
			return [
				'total_cleaning_time' => $total_cleaning_time,
				'area_cleaned' => $area_cleaned,
				'cleanups' => $cleanups,
				'clean_records' => $clean_records
			];
		}

		// fallback
		return [
			'total_cleaning_time' => NULL,
			'cleanups' => NULL,
			'area_cleaned' => NULL,
			'clean_records' => NULL
		];
	}

	/**
	 * Callback: Clean Record Details
	 * @param array $data
	 * @return array
	 */
	protected function get_clean_record_callback(array $data)
	{
		if (isset($data['result'][0])) {
			$record = $data['result'][0];

			$start_time = $record[0];
			$end_time = $record[1];
			$cleaning_duration = $record[2];
			$area = floatval($record[3]) / 1000000; //cm2 -> m2
			$errors = $record[4];
			$completed = $record[5];

			$data = [
				'starttime' => $start_time,
				'endtime' => $end_time,
				'cleaningduration' => $cleaning_duration,
				'area' => $area,
				'errors' => $errors,
				'completed' => $completed
			];

			// return wen duration was 0s
			if ($cleaning_duration == 0) {
				return $data;
			}

			// update html, when enabled
			if ($this->ReadPropertyBoolean('clean_time')) {
				$html_data = [
					$data['starttime'] => $data
				];

				if ($tmp_data = @GetValueString(@$this->GetIDForIdent('cleaning_records_tmp'))) {
					$tmp_data = json_decode($tmp_data, true);

					// merge temporary data with html data
					$html_data = $this->_merge(
						$tmp_data,
						$html_data
					);

					// sort by key (time)
					krsort($html_data);

					// show last 5 records, only
					if (count($html_data) > 5) {
						$html_data = array_slice($html_data, 0, 5, true);
					}
				}

				$this->SetRoborockValue('cleaning_records_tmp', json_encode($html_data));

				// build html
				$cleaning_records = [];
				foreach ($html_data AS $clean_record) {
					$start_time = $clean_record['starttime'];
					$start_hour = date('H', $start_time);
					$clean_day = date('l', $start_time);
					$clean_date = date('d.m.', $start_time);
					$start_minutes = date('i', $start_time);
					$end_time = $clean_record['endtime'];
					$end_hour = date('H', $end_time);
					$end_minutes = date('i', $end_time);
					$cleaning_duration = $this->_convertSecondsToTime($clean_record['cleaningduration']);
					$area = number_format($clean_record['area'], 1, ',', '.');
					$errors = $clean_record['errors'];
					$completed = $clean_record['completed'];

					$cleaning_records[] = [
						$this->Translate($clean_day),
						$clean_date . ' ' . $start_hour . ':' . $start_minutes . ' - ' . $end_hour . ':' . $end_minutes,
						$cleaning_duration,
						$area . ' m<sup>2</sup>',
						($errors ? '<span class="unicode red">✖</span>' : '-'),
						($completed ? '<span class="unicode green">✔</span>' : '<span class="unicode red">✖</span>')
					];
				}

				// build html table
				$html = $this->_convertDataToTable([
					'table' => [
						'head' => [
							$this->Translate('Day'),
							$this->Translate('Date'),
							$this->Translate('Cleaning Duration'),
							$this->Translate('Area'),
							$this->Translate('Errors'),
							$this->Translate('Completed'),
						],
						'body' => $cleaning_records
					]
				]);

				// save html table
				$this->SetRoborockValue('cleaning_records', $html);
			}

			return $data;
		} else {
			return [];
		}
	}

	/**
	 * Callback: DND Timer
	 * @param array $data
	 * @return array
	 */
	protected function get_dnd_timer_callback(array $data)
	{
		if (isset($data['result'][0])) {
			$dnd_state = boolval($data['result'][0]['enabled']);
			$end_hour = $this->_zeroPadding($data['result'][0]['end_hour']);
			$end_minute = $this->_zeroPadding($data['result'][0]['end_minute']);
			$start_hour = $this->_zeroPadding($data['result'][0]['start_hour']);
			$start_minute = $this->_zeroPadding($data['result'][0]['start_minute']);

			$start_time = $start_hour . ':' . $start_minute;
			$start_unixtime = strtotime($start_time);
			$this->SetRoborockValue('dnd_starttime', $start_unixtime);

			$end_time = $end_hour . ':' . $end_minute;
			$end_unixtime = strtotime($end_time);

			$this->SetRoborockValue('dnd_endtime', $end_unixtime);
			$this->SetRoborockValue('dnd_mode', $dnd_state);

			// return values
			return [
				'start' => $start_time,
				'start_unixtime' => $start_unixtime,
				'end' => $end_time,
				'end_unixtime' => $end_unixtime
			];
		}

		// fallback
		return [
			'start' => NULL,
			'start_unixtime' => NULL,
			'end' => NULL,
			'end_unixtime' => NULL
		];
	}

	/**
	 * Callback: Timer
	 * @param array $data
	 * @return array
	 */
	protected function get_timer_callback(array $data)
	{
		if (isset($data['result'])) {
			$timers = $data['result'];
			if (empty($timers)) {
				// save html table
				$this->SetRoborockValue('timer_details', "");
				return array("timer" => "no timer set");

			} else {
				$timer_list = array();
				foreach ($timers as $key => $timer) {
					$setuptime = $timer[0];// setup time of this schedule (Unix time)
					// $setuptimestring = date('h:i:s',$setuptime);
					$timer_active = $timer[1];// Is this schedule active
					$timing = $timer[2];
					$time_detail = $timing[0];
					$command = $timing[1][0];
					// $unknown = $timing[1][1];
					$timer_data = explode(" ", $time_detail);
					$minute = $timer_data[0];
					if ($minute == "0") {
						$minute = "00";
					}
					$hour = $timer_data[1];
					$day_of_month = $timer_data[2];
					$month = $timer_data[3];
					$day_of_week = $timer_data[4];
					$repetition = $this->_getTimerDay($day_of_week);
					$time_string = $hour . ":" . $minute;

					$timer_entry[] = [
						$time_string . '<br>' . $repetition,
						$timer_active
					];
					$timer_list[$setuptime]["timer_active"] = $timer_active;
					$timer_list[$setuptime]["minute"] = $minute;
					$timer_list[$setuptime]["hour"] = $hour;
					$timer_list[$setuptime]["day_of_month"] = $day_of_month;
					$timer_list[$setuptime]["month"] = $month;
					$timer_list[$setuptime]["time_string"] = $time_string;
					$timer_list[$setuptime]["repetition"] = $repetition;
					$timer_list[$setuptime]["command"] = $command;
				}

				// build html table
				$html = $this->_convertDataToTable([
					'table' => [
						'head' => [
							$this->Translate('Timer'),
							$this->Translate('Status'),
						],
						'body' => $timer_entry
					]
				]);

				// save html table
				$this->SetRoborockValue('timer_details', $html);

				// return values
				return $timer_list;
			}
		}

		// fallback
		return [];
	}

	/**
	 * Callback: Fan Power
	 * @param array $data
	 * @return int
	 */
	protected function get_custom_mode_callback(array $data)
	{
		if (isset($data['result'][0])) {
			$fan_power = $data['result'][0];
			$this->SetRoborockValue('fan_power', $fan_power);

			return $fan_power;
		}

		// fallback
		return 0;
	}

	/**
	 * Callback: Get Sound Volume
	 * @param array $data
	 * @return int
	 */
	protected function get_sound_volume_callback(array $data)
	{
		if (isset($data['result'][0])) {
			$volume = $data['result'][0];
			$type = gettype($volume);
			if ($type == "integer") {
				$this->SetRoborockValue('volume', $volume);
			}


			return $volume;
		}

		// fallback
		return 0;
	}

	/**
	 * Callback: Change Sound Volume
	 * @param array $data
	 * @return bool
	 */
	protected function change_sound_volume_callback(array $data)
	{
		// start & stop device quickly, to check volume
		if (in_array(GetValueInteger('state'), [2, 3, 8, 10, 15, 100])) {
			$this->Start();
			$this->Stop();
		}

		// fallback
		return true;
	}

	/**
	 * Callback: Start Remote Control
	 * @param array $data
	 * @return bool
	 */
	protected function app_rc_start_callback(array $data)
	{
		// update state to 'Remote Control'
		$this->SetRoborockValue('state', 4);
		return true;
	}

	/**
	 * Callback: Stop Remote Control
	 * @param array $data
	 * @return bool
	 */
	protected function app_rc_end_callback(array $data)
	{
		// update state to 'Waiting'
		$this->SetRoborockValue('state', 3);
		return true;
	}

	/**
	 * Callback: Clean Record Map
	 * @param array $data
	 */
	protected function get_clean_record_map_callback(array $data)
	{
	}

	/**
	 * Callback: Map v1 (currently not working, only returns "retry")
	 * @param array $data
	 */
	protected function get_map_v1_callback(array $data)
	{
	}

	/**
	 * Callback: Sound Progress
	 * @param array $data
	 * @return bool|array
	 */
	protected function get_sound_progress_callback(array $data)
	{
		return isset($data['result'][0]) ? $data['result'][0] : false;
	}

	/**
	 * Callback: Current Coordinates
	 * @param array $data
	 */
	protected function coordinates_callback(array $data)
	{
		$this->SetValue('coordinates', 'x: ' . $data['x'] . ' y: ' . $data['y'] * 20);
	}

	/***********************************************************
	 * Migrations
	 ***********************************************************/

	/**
	 * Polyfill for IP-Symcon 4.4 and older
	 * @param $Ident
	 * @param $Value
	 */
	protected function SetValue($Ident, $Value)
	{
		if (IPS_GetKernelVersion() >= 5) {
			parent::SetValue($Ident, $Value);
		} else if ($id = @$this->GetIDForIdent($Ident)) {
			SetValue($id, $Value);
		}
	}

}