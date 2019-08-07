<?php

declare(strict_types=1);

// set base dir
define('__ROOT__', dirname(dirname(__FILE__)));

// load ips constants
require_once __ROOT__ . '/libs/ips.constants.php';

/**
 * Class RoborockIO
 * Xiaomi Mi Vacuum Cleaner I/O Device.
 */
class RoborockIO extends IPSModule
{
    // constants
    const hello_msg = '21310020ffffffffffffffffffffffffffffffffffffffffffffffffffffffff';
    const port_udp = 54321;
    const unknown = '00000000';
    const timeout_send = 2;
    const timeout_discover = 5;

    // private properties
    private $token;
    private $ip;
    private $socket;
    private $attempts = 0;

    private $time_diff = 0;
    private $first_request = true;

    private $magic = '2131';
    private $length = '';
    private $unknown1 = '00000000';
    private $devicetype = '';
    private $serial = '';
    private $timestamp = '';
    private $checksum = '';
    private $key = '';
    private $iv = '';

    /**
     * close socket on destruction.
     */
    public function __destruct()
    {
        if ($this->socket) {
            socket_close($this->socket);
        }
    }

    /**
     * create instance.
     *
     * @return bool|void
     */
    public function Create()
    {
        parent::Create();

        // initiate buffer
        $this->SetBuffer('message_id', '0');
        $this->SetBuffer('queue', '[]');

        // register timer
        $this->RegisterTimer('RoborockQueue', 0, 'RoborockIO_HandleQueue(' . $this->InstanceID . ');');

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    /**
     * apply changes from configuration form.
     *
     * @return bool|void
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        // register Webhook
        $this->RegisterWebhook('/hook/Roborock');

        $this->SetTimerInterval('RoborockQueue', 200);

        // set status to 102, due no configuration
        $this->SetStatus(102);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {

        switch ($Message) {
            case IM_CHANGESTATUS:
                if ($Data[0] === IS_ACTIVE) {
                    $this->ApplyChanges();
                }
                break;

            case IPS_KERNELMESSAGE:
                if ($Data[0] === KR_READY) {
                    $this->ApplyChanges();
                }
                break;

            default:
                break;
        }
    }

    /**
     * receive from children.
     *
     * @param string $JSONString
     *
     * @return string json data
     */
    public function ForwardData($JSONString)
    {
        // receive data
        $data = json_decode($JSONString);
        $payload = $data->Buffer;

        // debug log
        $this->_debug('forwarded data', json_encode($data->Buffer));

        // return immediately on discover request
        if ($payload->method == 'discover') {
            return json_encode($this->Discover($payload->ip));
        }

        // send & receive command immediately
        if ($payload->immediate) {
            return json_encode($this->Send($payload));
        } // otherwise, append to queue
        else {
            $queue = json_decode($this->GetBuffer('queue'));
            $queue[] = $payload;

            // save queue
            $this->SetBuffer('queue', json_encode($queue));
        }

        return true;
    }

    /**
     * Queue Handler.
     *
     * @return void
     */
    public function HandleQueue()
    {
        // get current queue
        $queue = json_decode($this->GetBuffer('queue'));

        if ($queue) {
            // reset queue
            $this->SetBuffer('queue', '[]');

            // loop queue
            foreach ($queue as $item) {
                // short timeout
                IPS_Sleep(100);

                // receive data
                $buffer = $this->Send($item);

                // break loop on invalid requests
                if (!$buffer) {
                    return;
                }

                // append data
                if (is_array($buffer)) {
                    $buffer['method'] = $item->method;
                    $buffer['token'] = $this->token;
                }

                // send to children
                $this->SendDataToChildren(json_encode([
                    'DataID'     => '{36FF43CE-F065-DD20-F1A8-A7C99C25D7A2}',
                    'InstanceID' => (int) $item->InstanceID,
                    'Buffer'     => $buffer
                ]));
            }
        }
    }

    /**
     * send command & receive response.
     *
     * @param $payload
     *
     * @return string json data
     */
    protected function Send($payload)
    {
        $this->attempts++;

        // parse data
        $this->token = $this->_validateToken($payload->token);
        $this->ip = $payload->ip;
        $message = [
            'id'     => null,
            'method' => $payload->method,
            'params' => $payload->params
        ];

        // remove params, when empty
        if (!$message['params']) {
            unset($message['params']);
        }

        // debug log
        if ($this->first_request) {
            $this->_debug('token used', $this->token);
        }

        // proceed on valid token
        if ($this->token) {
            // send HELLO
            if ($this->SendHello()) {
                // set message id
                $message['id'] = $this->_getMessageId();

                $this->_debug('socket [message]', json_encode($message));

                // build message
                $packet = hex2bin($this->_buildMessage($message));
                $this->_debug('socket [packet]', $packet, 1);

                // send message to socket
                if ($bytes = socket_sendto($this->socket, $packet, strlen($packet), 0, $this->ip, self::port_udp)) {
                    $this->_debug('socket [send]', $bytes . ' bytes');
                } else {
                    $this->SocketErrorHandler();
                }

                // receive data from socket
                $buffer = '';
                if ($bytes = @socket_recvfrom($this->socket, $buffer, 4096, 0, $remote_ip, $remote_port) !== false) {
                    $this->_debug('socket [receive]', $bytes . ' bytes from ' . $remote_ip . ':' . $remote_port);

                    // parse message
                    $message = $this->_parseMessage(bin2hex($buffer));

                    // decrypt data
                    $data_decrypted = trim($this->_decrypt($message));
                    $this->_debug('raw data', $data_decrypted);

                    // validate json response
                    if ($result = $this->_validateResponse($data_decrypted)) {
                        $this->attempts = 0;
                        return $result;
                    } // on invalid response, retry attempt
                    elseif ($this->attempts < 3) {
                        return $this->Retry($payload);
                    } // return false
                    else {
                        return false;
                    }
                } else {
                    if ($this->attempts == 1) {
                        return $this->Retry($payload);
                    }
                    $this->SocketErrorHandler();
                }
            }
        }

        return false;
    }

    /**
     * Send commands and forward response to children.
     *
     * @param int          $instance_id
     * @param array|string $data
     * @param bool         $doTimeout
     *
     * @return bool
     */
    protected function SendData(int $instance_id, $data, $doTimeout = true)
    {
        // get instance settings
        $token = IPS_GetProperty($instance_id, 'token');
        $ip = IPS_GetProperty($instance_id, 'ip');

        // check $data
        if (!is_array($data)) {
            $data = [
                'method' => $data
            ];
        }

        // build payload
        $payload = [
            'token'     => $token,
            'ip'        => $ip,
            'method'    => $data['method'],
            'params'    => isset($data['params']) ? $data['params'] : [],
            'immediate' => true
        ];

        $payload = (object) $payload;

        // send command to robot
        $buffer = $this->Send($payload);

        // append data
        if (is_array($buffer)) {
            $buffer['method'] = $payload->method;
            $buffer['token'] = $payload->token;
        }

        // send buffer to children
        $this->SendDataToChildren(json_encode([
            'DataID'     => '{36FF43CE-F065-DD20-F1A8-A7C99C25D7A2}',
            'InstanceID' => (int) $instance_id,
            'Buffer'     => $buffer
        ]));

        // sleep on specific commands
        if ($doTimeout) {
            if ($payload->method == 'app_rc_start') {
                IPS_Sleep(5000);
            } elseif ($payload->method == 'app_rc_move') {
                IPS_Sleep(500);
            }
        }

        return true;
    }

    /**
     * Retry Message Send.
     *
     * @param array $payload
     *
     * @return string
     */
    private function Retry($payload = [])
    {
        IPS_Sleep(1000);
        $this->_increaseMessageId(100);
        $this->_debug('socket [response]', 'invalid response, retrying request...');
        return $this->Send($payload);
    }

    /**
     * send HELLO message initially.
     *
     * @param string $discover_ip
     *
     * @return array|bool
     */
    protected function SendHello($discover_ip = null)
    {
        // check if hello message was already sent
        if (!$this->first_request && !$discover_ip) {
            return true;
        }

        // get ip
        $ip = $discover_ip ? $discover_ip : $this->ip;

        // create socket
        $this->SocketCreate();

        // send timeout
        $this->SocketSetTimeout($discover_ip ? self::timeout_discover : self::timeout_send);

        // initiate HELLO message
        $this->_debug('socket [HELLO]', $ip);

        // build hello message
        $hello_packet = hex2bin(self::hello_msg);

        // send hello message
        if ($bytes = socket_sendto($this->socket, $hello_packet, strlen($hello_packet), 0, $ip, self::port_udp)) {
            $this->_debug(($discover_ip ? 'discover' : 'socket') . ' [response]', $bytes . ' bytes sent to ' . $ip . ':' . self::port_udp);
        } else {
            $this->SocketErrorHandler();
        }

        // receive response
        $buffer = '';
        if (($bytes = @socket_recvfrom($this->socket, $buffer, 4096, 0, $remote_ip, $remote_port)) !== false) {
            $this->_debug(($discover_ip ? 'discover' : 'socket') . ' [response]', $bytes . ' bytes received from ' . $remote_ip . ':' . $remote_port);

            // parse message
            $message = bin2hex($buffer);
            $hello = $this->_parseMessage($message);

            if ($hello) {
                $this->_debug('socket [HELLO]', json_encode($hello));
            }

            // return HELLO message on discover
            if ($discover_ip) {
                return $hello;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Discover device and get token.
     *
     * @param string $ip
     *
     * @return array|bool
     */
    protected function Discover(string $ip)
    {
        // send HELLO and retrieve token
        if ($discover = $this->SendHello($ip)) {
            $token = isset($discover['token']) ? $discover['token'] : false;
            return $token;
        }

        return false;
    }

    /**
     * creates an udp socket.
     */
    protected function SocketCreate()
    {
        /** do nothing, if socket was already created */
        if ($this->socket) {
            $this->_debug('socket [instance]', 'already created');
        } /** create socket */
        elseif ($this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
            $this->_debug('socket [instance]', 'created');
        } /** error handling */
        else {
            $this->SocketErrorHandler();
        }
    }

    /**
     * sends a receive timeout to socket.
     *
     * @param int $timeout
     */
    protected function SocketSetTimeout($timeout = 2)
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeout, 'usec' => 0])) {
            $this->_debug('socket [settings]', 'set timeout to ' . $timeout . 's');
        } else {
            $this->SocketErrorHandler();
        }
    }

    /**
     * handles socket error messages.
     */
    protected function SocketErrorHandler()
    {
        $error_code = socket_last_error();
        $error_msg = socket_strerror($error_code);

        $this->_debug('socket [error]', $error_code . ' message: ' . $error_msg);
        exit(-1);
    }

    /***********************************************************
     * Helper methods
     ***********************************************************/

    /**
     * validate token length.
     *
     * @param string $token
     *
     * @return bool|null
     */
    private function _validateToken($token = null)
    {
        // validate token length
        if (strlen($token) === 32) {
            // set encryption key
            $this->key = md5(hex2bin($this->token));

            // set encryption vector
            $this->iv = md5(hex2bin($this->key . $this->token));

            // return token
            return $token;
        }

        return false;
    }

    /**
     * validate json response.
     *
     * @param string $json
     *
     * @return array|bool|mixed|string
     */
    private function _validateResponse(string $json)
    {
        $result = false;
        $data = @json_decode($json, true);

        // validate json
        if ($jsonErrCode = json_last_error() !== JSON_ERROR_NONE) {
            $jsonErrMsg = $this->_jsonLastErrorMsg();
            $this->_debug('data', 'json is not valid. Error: ' . $jsonErrMsg);
            if ($jsonErrCode == JSON_ERROR_CTRL_CHAR) {
                // handle control chars
                $this->_debug('data', 'trim()...');
                $result = @json_decode(trim($json), true);
            }
        } else {
            $result = $data;
        }

        // validate result
        if (!isset($result['result'])) {
            $this->_debug('data [error]', json_encode($result));
            $result = [
                'error' => $result
            ];
        } elseif (empty($result)) {
            $this->_debug('data [error]', 'no json received');
            $result = [
                'error' => 'no json data received!'
            ];
        }

        return $result;
    }

    /**
     * generate almost unique message id.
     *
     * @return int
     */
    private function _getMessageId()
    {
        return $this->_increaseMessageId();
    }

    /**
     * increment message id.
     *
     * @param int $delta
     *
     * @return int
     */
    private function _increaseMessageId($delta = 1)
    {
        // read last message id
        $message_id = (int) $this->GetBuffer('message_id');

        // increment by $delta
        $message_id = ($message_id + $delta);

        // if message id is 9999, reset to 0
        if ($message_id >= 9999) {
            $message_id = 0;
        }

        // save to buffer
        $this->SetBuffer('message_id', strval($message_id));

        // return message id
        return $message_id;
    }

    /**
     * build socket message.
     *
     * @param $command
     *
     * @return string
     */
    private function _buildMessage($command)
    {
        if (!is_string($command)) {
            $command = json_encode($command);
        }

        $data = $this->_encrypt($command);
        $this->length = sprintf('%04x', (int) strlen($data) / 2 + 32);
        $this->timestamp = sprintf('%08x', time() + $this->time_diff);
        $packet = $this->magic . $this->length . $this->unknown1 . $this->devicetype . $this->serial . $this->timestamp . $this->token . $data;
        $this->checksum = md5(hex2bin($packet));
        $packet = $this->magic . $this->length . $this->unknown1 . $this->devicetype . $this->serial . $this->timestamp . $this->checksum . $data;

        return $packet;
    }

    /**
     * parse socket message.
     *
     * @param $message
     *
     * @return array
     */
    private function _parseMessage($message)
    {
        $data = [];

        $this->magic = substr($message, 0, 4);
        $this->length = substr($message, 4, 4);
        $this->unknown1 = substr($message, 8, 8);
        $this->devicetype = substr($message, 16, 4);
        $this->serial = substr($message, 20, 4);
        $this->timestamp = substr($message, 24, 8);
        $this->checksum = substr($message, 32, 32);

        // retrieve token
        if (($this->length == '0020') && (strlen($message) / 2 == 32)) {
            // get new token
            $tmp_token = $this->_validateToken(substr($message, 32, 32));

            // set new token, if valid
            if (!stristr($tmp_token, 'fffffffff')) {
                $this->token = $data['token'] = $tmp_token;
            }

            // calculate time diff between client and server
            $time_diff = hexdec($this->timestamp) - time();

            if ($this->first_request && $time_diff != 0) {
                $this->time_diff = $time_diff;
            }

            $this->first_request = false;
        } // get data
        else {
            $data_length = strlen($message) - 64;
            if ($data_length > 0) {
                $data = substr($message, 64, $data_length);
            }
        }

        return $data;
    }

    /**
     * encrypt data.
     *
     * @param $data
     *
     * @return string
     */
    protected function _encrypt($data)
    {
        return bin2hex(openssl_encrypt($data, 'AES-128-CBC', hex2bin($this->key), OPENSSL_RAW_DATA, hex2bin($this->iv)));
    }

    /**
     * decrypt data.
     *
     * @param $data
     *
     * @return string
     */
    protected function _decrypt($data)
    {
        return openssl_decrypt(hex2bin($data), 'AES-128-CBC', hex2bin($this->key), OPENSSL_RAW_DATA, hex2bin($this->iv));
    }

    /**
     * send debug log.
     *
     * @param string $notification
     * @param string $message
     * @param int    $format       0 = Text, 1 = Hex
     */
    private function _debug(string $notification = null, string $message = null, $format = 0)
    {
        $this->SendDebug($notification, $message, $format);
    }

    /**
     * retrieve last json error message.
     *
     * @return mixed|string
     */
    protected function _jsonLastErrorMsg()
    {
        if (!function_exists('json_last_error_msg')) {
            function json_last_error_msg()
            {

                static $ERRORS = [JSON_ERROR_NONE => 'No error has occurred',
                    JSON_ERROR_DEPTH              => 'The maximum stack depth has been exceeded',
                    JSON_ERROR_STATE_MISMATCH     => 'Invalid or malformed JSON',
                    JSON_ERROR_CTRL_CHAR          => 'Control character error, possibly incorrectly encoded',
                    JSON_ERROR_SYNTAX             => 'Syntax error',
                    JSON_ERROR_UTF8               => 'Malformed UTF-8 characters, possibly incorrectly encoded'];

                $error = json_last_error();
                return isset($ERRORS[$error]) ? $ERRORS[$error] : 'Unknown error';
            }
        }

        return json_last_error_msg();
    }

    /**
     * Process Webhook Data.
     */
    protected function ProcessHookData()
    {
        // set defaults
        $cmd = isset($_GET['cmd']) ? $_GET['cmd'] : false;
        $instance_id = isset($_GET['id']) ? $_GET['id'] : false;

        // check instance id
        if (!$instance_id) {
            die('Instance ID Missing ($_GET[\'id\'])');
        } elseif (!@IPS_GetObject(intval($instance_id))) {
            die('Instance ID ' . $instance_id . ' does not exist!');
        }

        // handle commands
        switch ($cmd):
            case 'remote':
                $rotation = (float) (isset($_GET['rotation']) ? $_GET['rotation'] : 0);
                $speed = (float) (isset($_GET['speed']) ? $_GET['speed'] : 0.1);
                $start = isset($_GET['start']) ? true : false;
                $end = isset($_GET['end']) ? true : false;

                if ($start) {
                    $this->SendData(
                        intval($instance_id),
                        'app_rc_start'
                    );
                } elseif ($end) {
                    $this->SendData(
                        intval($instance_id),
                        'app_rc_end'
                    );
                } elseif ($rotation) {
                    $this->SendData(
                        intval($instance_id),
                        'app_rc_start',
                        false
                    );

                    $this->SendData(
                        intval($instance_id),
                        [
                            'method' => 'app_rc_move',
                            'params' => [
                                [
                                    'omega'    => $rotation,
                                    'velocity' => $speed,
                                    'seqnum'   => 1,
                                    'duration' => 1000
                                ]
                            ]
                        ]
                    );
                }
                break;
            // upload map
            default:
                if (!isset($_FILES['image']['tmp_name']) || !file_exists($_FILES['image']['tmp_name']) || $_FILES['image']['name'] != 'latest.png') {
                    die('Image missing!');
                } elseif (!isset($_FILES['coordinates']['tmp_name']) || !file_exists($_FILES['coordinates']['tmp_name'])) {
                    die('Coordinates missing!');
                } else {
                    // validate uploaded image
                    if ($im = @imagecreatefrompng($_FILES['image']['tmp_name'])) {
                        // define transparent color
                        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);

                        // get image size
                        $s = getimagesize($_FILES['image']['tmp_name']);
                        $center_x = ($s[0] / 2);
                        $center_y = ($s[1] / 2);

                        // rotate image by -90°
                        $im = imagerotate($im, -90, $transparent, true);

                        // convert coordinates from file to points
                        $x = 0;
                        $y = 0;
                        $img_x = 0;
                        $img_y = 0;
                        foreach (file($_FILES['coordinates']['tmp_name']) as $line) {
                            if (strstr($line, 'estimate')) {
                                $d = explode('estimate', $line);
                                $d = trim($d[1]);

                                list($y, $x) = explode(' ', $d, 3);

                                // calculate pixel from center of the image, with offset
                                $img_x = $center_x + ($x * 20);
                                $img_y = $center_y + ($y * 20);

                                // draw pixel to image
                                imagesetpixel($im, $img_x, $img_y, imagecolorallocate($im, 125, 125, 125));
                            }
                        }

                        // draw current position
                        imagefilledellipse($im, $img_x, $img_y - 3, 8, 8, imagecolorallocate($im, 220, 0, 0));

                        // rotate image back by 90°
                        $im = imagerotate($im, 90, $transparent, true);

                        // save transparency
                        imagesavealpha($im, true);

                        // save image
                        imagepng($im, $_FILES['image']['tmp_name']);
                        imagedestroy($im);

                        // reopen image
                        $im = @imagecreatefrompng($_FILES['image']['tmp_name']);

                        // crop background
                        if ($cropped = imagecropauto($im, IMG_CROP_DEFAULT)) {
                            imagepng($cropped, $_FILES['image']['tmp_name']);
                            imagedestroy($cropped);
                        }

                        imagedestroy($im);

                        // create media image, if not exists
                        $media_file = 'Map.' . $instance_id . '.png';

                        if (!$media_id = @IPS_GetMediaIDByFile($media_file)) {
                            $media_id = IPS_CreateMedia(1);
                            IPS_SetName($media_id, $this->Translate('Map'));
                        }

                        // move to instance
                        IPS_SetParent($media_id, intval($instance_id));

                        // update media content
                        IPS_SetMediaFile($media_id, $media_file, false);
                        IPS_SetMediaContent($media_id, base64_encode(file_get_contents($_FILES['image']['tmp_name'])));

                        // send coordinates to children
                        if ($x && $y) {
                            $this->SendDataToChildren(json_encode([
                                'DataID'     => '{36FF43CE-F065-DD20-F1A8-A7C99C25D7A2}',
                                'InstanceID' => (int) $instance_id,
                                'Buffer'     => [
                                    'token'  => false,
                                    'method' => 'coordinates',
                                    'x'      => $x,
                                    'y'      => $y
                                ]
                            ]));
                        }
                    }

                    // unlink temp files
                    unlink($_FILES['image']['tmp_name']);
                    unlink($_FILES['coordinates']['tmp_name']);
                }
                break;
        endswitch;
    }

    /**
     * Register Webhook.
     *
     * @param string $webhook
     * @param bool   $delete
     */
    protected function RegisterWebhook($webhook, $delete = false)
    {
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');

        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $found = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['Hook'] == $webhook) {
                    if ($hook['TargetID'] == $this->InstanceID && !$delete)
                        return;
                    elseif ($delete && $hook['TargetID'] == $this->InstanceID) {
                        continue;
                    }

                    $hooks[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $hooks[] = ['Hook' => $webhook, 'TargetID' => $this->InstanceID];
            }

            IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }

    /***********************************************************
     * Migrations
     ***********************************************************/

    /**
     * Polyfill for IP-Symcon 4.4 and older.
     *
     * @param string $Ident
     * @param mixed  $Value
     */
    protected function SetValue($Ident, $Value)
    {
        if (IPS_GetKernelVersion() >= 5) {
            parent::SetValue($Ident, $Value);
        } elseif ($id = @$this->GetIDForIdent($Ident)) {
            SetValue($id, $Value);
        }
    }
}
