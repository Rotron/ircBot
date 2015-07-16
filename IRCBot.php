<?php

set_time_limit(0);
ini_set('display_errors', 'on');

$config = array(
	'server' => 'irc.dev',
	'port'   => 6667,
	'nick'   => 'First-Thought',
	'name'   => 'Giver-of-Will',
	'room'   => 'dev'
	//	'pass' => 'meh',
);

class IRCBot
{
	//This is going to hold our TCP/IP connection
	var $socket;

	//This is going to hold all of the messages both server and client
	var $ex = array();

	function __construct($config)
	{
		$this->socket = fsockopen($config['server'], $config['port']);
		$this->nick = $config['nick'];
		$this->login($config);
		$this->main(true);
	}

	function login($config)
	{
		$this->send_data('USER', $config['nick'] . ' >>>PROMETHEUS<<< ' . $config['nick'] . ' :' . $config['name']);
		$this->send_data('NICK', $config['nick']);
	}

	/*
	 This is the workhorse function, grabs the data from the server and displays on the browser
	*/
	function main($first = null)
	{
		$data = fgets($this->socket, 128);
		echo nl2br($data);
		flush();
		$this->ex = explode(' ', $data);

		if($first)
		{
			$this->send_data('JOIN', '#dev');
//      sleep(2);
//      $this->send_data('PRIVMSG', '#dev :ACKNOWLEDGE//SUBMIT! <unit> may engage <FIRST-THOUGHT\\GIVER-OF-WILL> with the following words\\commands: prometheus, say <words>, prometheus, punch <unit>');
		}

		if($this->ex[0] == 'PING')
		{
			$this->send_data('PONG', $this->ex[1]); //Plays ping-pong with the server to stay connected.
		}

    $user = null;
    if(preg_match("/^:(.+)\!/", $this->ex[0], $matches))
    {
      var_dump($matches);
      $user = $matches[1];
    }

    var_dump($this->ex);

		if(isset($this->ex[3]) && $this->ex[3] == ":prometheus,")
		{
			$command = str_replace(array(chr(10), chr(13)), '', $this->ex[4]);
      echo "Command: " . $command;
			switch($command) //List of commands the bot responds to from a user.
			{
				case 'say':
					$this->speak();
					break;
        case 'punch':
          $this->punch($this->ex[5], $user);
          break;
				case ':!join':
					$this->join_channel($this->ex[4]);
					break;
				case ':!quit':
					$this->send_data('QUIT', 'Eliminate//offline//burst->all->invaders!');
					break;
				case ':!op':
					$this->op_user();
					break;
				case ':!deop':
					$this->op_user('', '', false);
					break;
				case ':!voice':
					$this->voice_user();
					break;
				case ':!devoice':
					$this->voice_user('', '', false);
					break;
				case ':!protect':
					$this->protect_user();
					break;
			}
		}

		$this->main();
	}

	function send_data($cmd, $msg = null) //displays stuff to the broswer and sends data to the server.
	{
		if($msg == null)
		{
			fputs($this->socket, $cmd . "\r\n");
			echo '<strong>' . $cmd . '</strong><br />';
		}
		else
		{
			fputs($this->socket, $cmd . ' ' . $msg . "\r\n");
			echo '<strong>' . $cmd . ' ' . $msg . '</strong><br />';
		}
	}

	function join_channel($channel) //Joins a channel, used in the join function.
	{
		if(is_array($channel))
		{
			foreach($channel as $chan)
			{
				$this->send_data('JOIN', $chan);
			}
		}
		else
		{
			$this->send_data('JOIN', $channel);
		}
	}

	function protect_user($user = '')
	{
		if($user == '')
		{
			if(php_version() >= '5.3.0')
			{
				$user = strstr($this->ex[0], '!', true);
			}
			else
			{
				$length = strstr($this->ex[0], '!');
				$user = substr($this->ex[0], 0, $length);
			}
		}
		$this->send_data('MODE', $this->ex[2] . ' +a ' . $user);
	}

	function op_user($channel = '', $user = '', $op = true)
	{
		if($channel == '' || $user == '')
		{
			if($channel == '')
			{
				$channel = $this->ex[2];
			}

			if($user == '')
			{
				if(php_version() >= '5.3.0')
				{
					$user = strstr($this->ex[0], '!', true);
				}
				else
				{
					$length = strstr($this->ex[0], '!');
					$user = substr($this->ex[0], 0, $length);
				}
			}
		}

		if($op)
		{
			$this->send_data('MODE', $channel . ' +o ' . $user);
		}
		else
		{
			$this->send_data('MODE', $channel . ' -o ' . $user);
		}
	}

	function speak()
	{
		$args = null;
		for($i = 5; $i < count($this->ex); $i++)
		{
			//				print($this->ex[$i]);
			$args .= $this->ex[$i] . ' ';
		}

		if($this->ex[2] == $this->nick)
		{
			preg_match('/:(.*)!/', $this->ex[0], $matches);
			$this->send_data('PRIVMSG ' . $matches[1] . " :", $args);
		}
		else
		{
			$this->send_data('PRIVMSG #dev' . " :", $args);
		}
	}

  function punch($target, $initiator)
  {
    if($target != "jfranklin")
    {
      $this->send_data("ME #dev :/me punches $target");
    }
    else
    {
      $this->send_data("ME #dev :/me punches $initiator");
    }
  }

	function args2str($carry, $item)
	{
		return $carry . " " . $item;
	}
}

$bot = new IRCBot($config);
