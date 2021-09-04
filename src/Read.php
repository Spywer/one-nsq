<?php

namespace OneNsq;

use Swoole\Client as SwooleSocketClient;

class Read
{
    /**
     * @var resource;
     */
    private $conn;

    /**
     * Write constructor.
     * @param resource
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function val()
    {
		// 1048576 - 1МБ
		
		$str = $this->conn->recv(1048576, SwooleSocketClient::MSG_PEEK | SwooleSocketClient::MSG_WAITALL);
		
		if ($str === false || !isset($str[0])) {
            throw new Exception('read from fail', Exception::CODE_READ_FAIL);
        }
			
		//var_dump(addslashes($str));
		
        return $str;
    }
	
	public function responseFix($str)
    {
		if (str_ends_with($str, Protocol::OK)) {
			
			return Protocol::OK;
			
		} else if (str_ends_with($str, Protocol::HEARTBEAT)) {
			
			$string = preg_replace('#^\{.+?\}#', '', $str);
			
			// Срабатывает при чтении и добавлении сообщения
			if(substr($string, 8, 2) == 'OK') {
				
				$string = substr($string, 14);
				$string = substr($string, 0, -11);
				$string = substr($string, 4);
				
				$message = new Data($string);
				
				if(empty($message->id) || empty($message->attempts)) {
					return Protocol::HEARTBEAT;
				}
				
				return $message;
			}
			
			return Protocol::HEARTBEAT;
		}
		
		return $str;
	}

    /**
     * @return Data|string
     * @throws Exception
     */
    public function valFixed()
    {
		$ret = $this->val();

        $code = unpack('N', substr($ret, 4, 4))[1];
        $ret  = substr($ret, 8);
		
		//var_dump($code);
		
        if ($code === Protocol::FRAME_TYPE_RESPONSE) {
            return $this->responseFix($ret);
        } else if ($code === Protocol::FRAME_TYPE_ERROR) {
            throw new Exception('err msg : ' . $ret, Exception::CODE_MSG_ERR);
        } else if ($code === Protocol::FRAME_TYPE_MESSAGE) {
            return new Data($ret);
        } else {
            throw new Exception('undefined code : ' . $code . ' msg:' . $ret, Exception::CODE_CODE_ERR);
        }
    }

}