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

  private $shitlist = array(
    "jarvis",
    "ai",
    "jQuery"
  );
  public $defenses = array(
    "punches jfranklin in the FACE" => 'blocks %1$s\'s punch, and then cuts %1$s in half with a well-placed laser',
    "roundhouse kicks  jfranklin in the FACE" => "catches %s's foot, and then incinerates his leg with napalm"
  );

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

  public $listening = true;
	/*
	 This is the workhorse function, grabs the data from the server and displays on the browser
	*/
	function main($first = null)
	{

    $this->send_data('JOIN', '#dev');
    sleep(2);
    //$this->send_data('PRIVMSG', '#dev :ACKNOWLEDGE//SUBMIT! <unit> may engage <FIRST-THOUGHT\\\\GIVER-OF-WILL> with the following words\\\\commands: prometheus, say <words>, prometheus, md5 <words>, prometheus, sha1 <words>, prometheus, crc32 <words>');

    while($this->listening === true) {
      $data = fgets($this->socket, 128);
      echo $data;
      flush();
      $this->ex = explode(' ', $data);
      // var_dump($this->ex);

      // Plays ping-pong with the server to stay connected.
      if($this->ex[0] == 'PING')
      {
        $this->send_data('PONG', $this->ex[1]);
      }

      // Get the user that issued the last command
      $user = null;
      if(preg_match("/^:(.+)\!/", $this->ex[0], $matches))
      {
        $user = $matches[1];
      }
      //echo "--\nUSER: $user\n--\n";

      // Block attacks from Jarvis @ jfranklin
     if(isset($this->shitlist[$user]))
     {
        $attack = $this->_isAttacking($this->ex);
        print "\n--------\nATTACK: $attack\n--------\n";
        if($attack !== false)
        {
          $this->defendAgainst($this->ex[2], $user, $attack);
          $this->send_data("PRIVMSG $user :Better luck next time, <$user//metagen//heretic>");
          sleep(1);
          $this->send_data("PRIVMSG #dev :<unit><$user///dirt-bag> was :destroyed\\\\burst\\\\offlined: for >>impudence<< -> ACKNOWLEDGE//SUBMIT!");
        }
        else
        {
          $this->action("#dev", "throws a shield up, protecting jfranklin");
          $this->send_data("#dev your insolence has been noted, $user");
        }
     }

      // If jfranklin quits, disconnect.
      if($user == "jfranklin" && $this->ex[1] == "QUIT")
      {
        $this->quit($user);
      }

      // Listen for commands
      if(isset($this->ex[3]) && $this->ex[3] == ":prometheus,")
      {
        $command = str_replace(array(chr(10), chr(13)), '', $this->ex[4]);
        switch($command) //List of commands the bot responds to from a user.
        {
          case 'say':
            $this->speak();
            break;
          case 'punch':
            $this->punch(rtrim($this->ex[5]), rtrim($user));
            break;
          case 'md5':
            $this->returnMd5();
            break;
          case 'sha1':
            $this->returnSha1();
            break;
          case 'crc32':
            $this->returnCrc32();
            break;
          case ':!join':
            $this->join_channel($this->ex[4]);
            break;
          case 'quit':
            $this->quit($user);
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
    }
	}

	function send_data($cmd, $msg = null) //displays stuff to the broswer and sends data to the server.
	{
		if($msg == null)
		{
			fputs($this->socket, $cmd . "\r\n");
			echo 'Command' . $cmd . "\n";
		}
		else
		{
			fputs($this->socket, $cmd . ' ' . $msg . "\r\n");
			echo $cmd . ' ' . $msg;
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
    var_dump($target);
    var_dump($initiator);
    if($target != "jfranklin")
    {
      $this->action("#dev", "punches $target");
    }
    else
    {
      //$this->send_data("PRIVMSG #dev :" . chr(1) . "ACTION punches $initiator " . chr(1));
      $this->action("#dev", "laughs, and then punches $initiator");
      $this->send_data("PRIVMSG jfranklin :$initiator has just tried to punch you");
    }
  }

/**
   * Sends a CTCP ACTION (/me) command to a nick or channel.
   * @param string $target - Channel name or user nick
   * @param string $text - Text of the action to perform
   * @return void
   */
  public function action($target, $text)
  {
    $this->send_data("PRIVMSG $target", " :" .chr(1) . 'ACTION ' . rtrim($text) . ' ' . chr(1));
  }

  function returnMd5()
  {
		$args = null;
		for($i = 5; $i < count($this->ex); $i++)
		{
			$args .= $this->ex[$i] . ' ';
		}
    $this->send_data("PRIVMSG", "#dev " . md5($args));
  }

  function returnSha1()
  {
		$args = null;
		for($i = 5; $i < count($this->ex); $i++)
		{
			$args .= $this->ex[$i] . ' ';
		}
    $this->send_data("PRIVMSG", "#dev " . sha1($args));
  }

  function returnCrc32()
  {
		$args = null;
		for($i = 5; $i < count($this->ex); $i++)
		{
			$args .= $this->ex[$i] . ' ';
		}
    $this->send_data("PRIVMSG", "#dev " . crc32($args));
  }

  function quit($user)
  {
    if($user == "jfranklin") {
      $this->listening = false;
      $this->send_data('QUIT', 'Eliminate//offline//burst->all->invaders!');
    }
    else
    {
      $this->action("#dev", "chuckles");
      $this->send_data("PRIVMSG jfranklin :$user just tried to make me quit");
    }
  }

	function args2str($carry, $item)
	{
		return $carry . " " . $item;
	}

  // Pass in $this->ex
  function _isAttacking($attack)
  {
    $args = "";
		for($i = 4; $i < count($this->ex); $i++)
		{
			$args .= $attack[$i] . ' ';
		}
    // Remove whitespace
    $attack = trim($args);
    // Remove unprintable, strange characters
    $attack = preg_replace('/[\x00-\x1F\x7F]/', '', $attack);

    if(array_key_exists($attack, $this->defenses))
    {
      return $attack;
    }
    elseif(strpos($attack, "jfranklin") !== false)
    {
      file_put_contents("undefended_attacks.txt", $attack . "\n", FILE_APPEND);
      return false;
    }
    else
    {
      return false;
    }
  }

  function defendAgainst($channel, $user, $attack)
  {
    echo $user;
    echo $attack;
    echo $this->defenses[$attack];
    $this->action($channel, sprintf($this->defenses[$attack], $user));
  }
}

$bot = new IRCBot($config);
