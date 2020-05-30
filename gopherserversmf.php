<?php
/*
 * Gopher for SMF
 * by: vbgamer45
 * https://wwww.smfhacks.com
 * https://github.com/VBGAMER45/gopherserversmf
 * Licensed: under MIT
 */

// Notes For SMF 2.0.x and SMF 2.1.x requires MySQLI, sockets enabled in PHP
set_time_limit(0);

// Constants to adjust
// Gopher Bind address either an IP or DOMAIN -
define("GOPHER_BINDADDRESS","127.0.0.1");
define("GOPHER_PORT","70");

// Database
define("SMF_DB_SERVER",'localhost');
define("SMF_DB",'smf');
define("SMF_DB_USERNAME",'smfuser');
define("SMF_DB_PASSWORD", '');
define("SMF_DB_PREFIX",'smf_');

// Site url
define("SITE_TITLE",'MY SMF Forum');
define("SITE_URL",'https://myforum.com');

// Display debugging information
define("DEBUG_MODE",1);


if (DEBUG_MODE)
{
	error_reporting(E_ALL);
	ini_set("display_errors",1);
}


ob_implicit_flush();

$db = '';


$gopherClient = array();

// nad0r1 at hush dot ai
// Based on https://www.php.net/manual/en/function.socket-read.php#79314

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
{
    echo "socket_create() failed " . socket_strerror(socket_last_error()) . "\n";
}

if (socket_bind($sock,GOPHER_BINDADDRESS, GOPHER_PORT) === false)
{
    echo "socket_bind() failed: " . socket_strerror(socket_last_error($sock)) . "\n";
}

if (socket_listen($sock, 5) === false)
{
    echo "socket_listen() failed" . socket_strerror(socket_last_error($sock)) . "\n";
}
//clients array
$clients = array();
$clientLastTime = array();

do {
    $read = array();
    $read[] = $sock;
	$write = NULL;
	$except = NULL;
	$tv_sec = 5;
    $read = array_merge($read,$clients);

    // Set up a blocking call to socket_select
    if(socket_select($read,$write, $except, $tv_sec) < 1)
    {
        //    SocketServer::debug("Problem blocking socket_select?");
        continue;
    }

    // Handle new Connections
    if (in_array($sock, $read)) {

        if (($msgsock = socket_accept($sock)) === false) {
            echo "socket_accept() failed: " . socket_strerror(socket_last_error($sock)) . "\n";
            break;
        }
        else
		{
			$peerAddress = '';
			$peerPort = '';
			 socket_getpeername($msgsock,$peerAddress,$peerPort);
			echo 'New Client: ' . $peerAddress . "\n";
		}
        $found = 0;
        foreach($clients as $key => $tmp)
		{
			if (!isset($clients[$key]))
			{
				$clients[$key] = $msgsock;
				$found =  1;
				echo 'Reusing Client: ' . $key . "\n";
			}
		}
		if ($found == 0)
			$clients[] = $msgsock;


    }
  //  $i = 0;

    // Handle Input
    foreach ($clients as $key => $client) { // for each client
		/*
		if (!empty($clientLastTime[$key]) && ($clientLastTime[$key]+ 60) > time())
		{
			  echo "Closing Client: $key";
					unset($gopherClient[$key]);
				unset($clients[$key]);
                socket_close($client);
                continue;
		}
*/

        if (in_array($client, $read)) {
            if (false === ($buf = socket_read($client, 2048, PHP_NORMAL_READ))) {
               echo "socket_read() failed: " . socket_strerror(socket_last_error($client)) . "\n";
                //break 2;
				unset($gopherClient[$key]);
				unset($clients[$key]);
                socket_close($client);
                break;

            }
            if (!$buf = trim($buf)) {
               continue;
            }

            // Not used by gopher just for use in console apps.
            if ($buf == 'quit') {
                unset($clients[$key]);
                socket_close($client);
                break;
            }

            // Output for console
            echo "Client: $key Gopher: $buf\n";

            $clientLastTime[$key] = time();

            if (empty($gopherClient[$key]))
            {
            	$gopherClient[$key] = new  Gopher_Server();
            	$gopherClient[$key]->setHostname(GOPHER_BINDADDRESS);
		$gopherClient[$key]->setPort(GOPHER_PORT);
            }

            $tokens = explode("\t",$buf);

            $tokenParts =explode('/',$tokens[0]);

            if (DEBUG_MODE)
			{
				print_r($tokenParts);
				echo 'After Split: ' . '/' . $tokenParts[1] . "\n";
			}

			if ($tokenParts[1] == 'caps.txt')
			{
				echo 'FOUND CAPS';
				// Show text file
				$fileData = file_get_contents(dirname(__FILE__).'/caps.txt');
				$fileData .= chr(32) . "\r\n". "." . "\r\n" . chr(0);
				socket_write($client, $fileData, strlen($fileData));
				break;
			}

            $tokens[0] = '/' . $tokenParts[1];

            // Display the Main Listing
            if ($tokens[0] == '/' || $tokens[0] == '/forums')
            {

				// Show forum boards!!
				$db = ConnectDB();
				$db_prefix = SMF_DB_PREFIX;

				$gopherClient[$key]->WriteDirectory("Return " . SITE_TITLE . " - Home","/");
				$gopherClient[$key]->WriteText("---------------------------------------------------------");
				
				$gopherClient[$key]->WriteHTMLLink(SITE_URL,SITE_URL);
				$gopherClient[$key]->WriteText("---------------------------------------------------------");


				$typeDisplay = $tokenParts[2];
				if (empty($typeDisplay))
				{
					// Show categories


				$request1 = sitequery("SELECT
					c.ID_CAT, c.cat_order, c.name
				FROM {$db_prefix}categories AS c
				ORDER BY c.cat_order ASC", $db);
				while ($row1 = mysqli_fetch_assoc($request1))
				{

					$catid = $row1['ID_CAT'];

					$request2 = sitequery("SELECT
                                                        b.name, b.num_posts, b.ID_BOARD, b.ID_CAT, b.child_level, b.ID_PARENT, b.board_order
                                                FROM {$db_prefix}boards AS b
                                                WHERE (FIND_IN_SET(-1, b.member_groups) != 0) AND  $catid = b.ID_CAT Order by b.board_order asc 
						", $db);

					$b_count = mysqli_affected_rows($db);
					if ($b_count !=0)
					{
							$gopherClient[$key]->WriteText("*****************************************************");
							$gopherClient[$key]->WriteText($row1['name']);
							$gopherClient[$key]->WriteText("*****************************************************");

						// List the forums and subforums
						while ($row2 = mysqli_fetch_assoc($request2))
						{
							$gopherClient[$key]->WriteDirectory($row2['name'],"/forums/b/" . $row2['ID_BOARD']);
							$gopherClient[$key]->WriteText("Total Posts: " . $row2['num_posts']);

						}

					}


				}



				}

				// Topic listing and sub boards
				if ($typeDisplay == 'b')
				{
					$boardid = (int) $tokenParts[3];

					$start = 0;

					if (!empty($tokenParts[4]))
						$start = (int) $tokenParts[4];

					$request = sitequery("
					SELECT
						b.name, b.num_topics
					FROM {$db_prefix}boards AS b
					WHERE b.ID_BOARD = $boardid  AND (FIND_IN_SET(-1, b.member_groups) != 0)", $db);

					$row = mysqli_fetch_assoc($request);

					if (mysqli_num_rows($request) == 0)
						$gopherClient[$key]->WriteText('The topic or board you are looking for appears to be either missing or off limits to you');
					else
					{



				$totalResult = sitequery("SELECT
							count(*) as total 
						FROM {$db_prefix}messages AS m, {$db_prefix}topics AS t
						WHERE m.ID_BOARD = $boardid AND m.ID_MSG = t.ID_FIRST_MSG",$db);
				$totalRow = mysqli_fetch_assoc($totalResult);

				$totalRecords = $totalRow['total'];

				$totalPages = $totalRecords / 10;

				$startpos = ($start * 10);

						// Lets show the board

						$request2 = sitequery("
						SELECT
							m.subject, t.ID_TOPIC, t.num_replies
						FROM {$db_prefix}messages AS m, {$db_prefix}topics AS t
						WHERE m.ID_BOARD = $boardid AND m.ID_MSG = t.ID_FIRST_MSG
						ORDER BY t.ID_LAST_MSG DESC
						LIMIT $startpos,10",$db);
						$i = 0;
						while($row2 = mysqli_fetch_assoc($request2))
						{
							$i++;
							$gopherClient[$key]->WriteDirectory($row2['subject'],"/forums/p/" . $row2['ID_TOPIC']);
							$gopherClient[$key]->WriteText($row2['num_replies'] . ' replies');

						}

						$gopherClient[$key]->WriteText("*****************************************************");
						// Show previous page
						if ($start > 1)
						{
							$gopherClient[$key]->WriteDirectory("Previous Page","/forums/b/$boardid/" . ($start - 1));
						}

						if ($totalPages > 1  && $start < $totalPages)
						{
							if ($start == 0)
								$start = 1;

							$gopherClient[$key]->WriteDirectory("Next Page","/forums/b/$boardid/" . ($start + 1));
						}


					}



				}

				//  View Post
				if ($typeDisplay == 'p')
				{
					$topicid = (int) $tokenParts[3];
					$start = 0;

					if (!empty($tokenParts[4]))
						$start = (int) $tokenParts[4];

					$request = sitequery("
					SELECT
						m.subject, t.num_replies, b.name, b.ID_BOARD, m.ID_BOARD
					FROM ({$db_prefix}messages AS m, {$db_prefix}topics AS t,
					{$db_prefix}boards AS b)
					WHERE b.ID_BOARD = m.ID_BOARD AND t.ID_TOPIC = $topicid AND m.ID_MSG = t.ID_FIRST_MSG AND (FIND_IN_SET(-1, b.member_groups) != 0)",$db);
					$row = mysqli_fetch_assoc($request);
					if (mysqli_num_rows($request) == 0)
						$gopherClient[$key]->WriteText('The topic or board you are looking for appears to be either missing or off limits to you');
					else
					{



				$totalResult = sitequery("SELECT
							count(*) as total 
						FROM {$db_prefix}messages AS m
						LEFT JOIN {$db_prefix}boards AS b ON(b.ID_BOARD = m.ID_BOARD)
						WHERE m.ID_TOPIC = $topicid AND (FIND_IN_SET(-1, b.member_groups) != 0)",$db);
				$totalRow = mysqli_fetch_assoc($totalResult);

				$totalRecords = $totalRow['total'];

				$totalPages = $totalRecords / 10;

				$startpos = ($start * 10);


						// Get all posts in a topic
						$request2 = sitequery("
					SELECT
						m.subject, m.poster_name, m.body, m.poster_time, m.ID_MSG 
						FROM {$db_prefix}messages AS m
						LEFT JOIN {$db_prefix}boards AS b ON(b.ID_BOARD = m.ID_BOARD)
						WHERE m.ID_TOPIC = $topicid AND (FIND_IN_SET(-1, b.member_groups) != 0) ORDER BY m.ID_MSG ASC LIMIT $start,10", $db);


						while ($row2 = mysqli_fetch_assoc($request2))
						{
							$gopherClient[$key]->WriteText("#Post#: " . $row2['ID_MSG'] . "--------------------------------------------------");
							$gopherClient[$key]->WriteText($row2['subject']);
							$gopherClient[$key]->WriteText('By: ' . $row2['poster_name'] . ' Date: ' . date("F j, Y, g:i a",$row2['poster_time']));
							$gopherClient[$key]->WriteText("---------------------------------------------------------");
							$row2['body'] = replaceBBcodes($row2['body']);
							$lines = explode("<br />",$row2['body']);
							foreach($lines as $line)
							{

								$line = wordwrap($line,64,"<br />",false);
								// Start of link code

								$reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
								preg_match_all($reg_exUrl,  $line, $matches);
								$usedPatterns = array();
								foreach ($matches[0] as $pattern)
								{
									if (!array_key_exists($pattern, $usedPatterns))
									{
										$usedPatterns[$pattern] = true;
										 $line = str_replace($pattern, "<br />$pattern<br />",  $line);

									//	$tmp = explode('\n',$line);
										//foreach($tmp as $line2);


									}
								}

								$splitLine = explode("<br />",$line);
								foreach($splitLine as $tmpLine)
								{
									if (!empty($tmpLine))
									{
										$tmpLine = trim($tmpLine);

										if (substr_count($tmpLine,"http://") > 0 || substr_count($tmpLine,"https://"))
										{

											$gopherClient[$key]->WriteHTMLLink($tmpLine,$tmpLine);
										}
										else
											$gopherClient[$key]->WriteText($tmpLine);
									}
								}











							}


						}

						$gopherClient[$key]->WriteText("*****************************************************");
						// Show previous page
						if ($start > 1)
						{
							$gopherClient[$key]->WriteDirectory("Previous Page", "/forums/p/$topicid/" . ($start - 1));
						}

						if ($totalPages > 1 && $start < $totalPages)
						{
							if ($start == 0)
								$start = 1;

							$gopherClient[$key]->WriteDirectory("Next Page", "/forums/p/$topicid/" . ($start + 1));
						}

					}


				}



				$response = $gopherClient[$key]->ReturnResponse();
				socket_write($client, $response, strlen($response));

			}
			else
			{
				$gopherClient[$key]->WriteDirectory(SITE_TITLE . " - Home","/");
				$gopherClient[$key]->WriteText("---------------------------------------------------------");
				$gopherClient[$key]->WriteText("Nothing found here. Is Earl here?????");
				$gopherClient[$key]->WriteText("---------------------------------------------------------");

				$response = $gopherClient[$key]->ReturnResponse();
				socket_write($client, $response, strlen($response));
			}




        }

    }
} while (true);

socket_close($sock);


function ConnectDB()
{
  	global $db;

	$db = mysqli_connect(SMF_DB_SERVER,  SMF_DB_USERNAME, SMF_DB_PASSWORD, SMF_DB) or die("Unable to connect to database!");

	return $db;

}

function sitequery($query,$link)
{
		global $db;

		if (empty($link))
			$link = $db;


		$result = mysqli_query($link,$query);

		return $result;
}



class Gopher_Server {
	/*
by: evert https://github.com/evert/PHPGopherServer
Modified by vbgamer45
Licensed under MIT.

A blogpost with more information can be found here:
http://www.rooftopsolutions.nl/blog/100


	*/
    /**
     * hostname of this server
     *
     * @var string
     */
    private $hostname = '';

    private $dataItems = array();


    /**
     * default port
     *
     * @var int
     */
    private $port = 70;

    /**
     * ASCII based textfile
     */
    const G_TEXTFILE        = '0';

    /**
     * Directory
     */
    const G_DIRECTORY       = '1';

    /**
     * CSO phonebook (old and unsupported protocol)
     */
    const G_CSO             = '2';

    /**
     * Error message (unsupported)
     */
    const G_ERROR           = '3';

    /**
     * Macintosh binhex file
     */
    const G_MACFILE         = '4';

    /**
     * MS-DOS binary
     */
    const G_DOSFILE         = '5';

    /**
     * Unix UUEncoded file
     */
    const G_UUENCODED       = '6';

    /**
     * Link to a search service
     */
    const G_SEARCH          = '7';

    /**
     * Link to a telnet server
     */
    const G_TELNET          = '8';

    /**
     * Unix binary file
     */
    const G_BINARY          = '9';

    /**
     * Redundant server (unsupported)
     */
    const G_REDUNDANTSERVER = '+';

    /**
     * Link to a TN3270 terminal (unsupported)
     */
    const G_TN3270          = 'T';

    /**
     * Link to a GIF image
     */
    const G_GIF             = 'g';

    /**
     * Link to any other image
     */
    const G_IMAGE           = 'I';

    /**
     * In-line informational text
     */
    const G_TEXT            = 'i';

    /**
     * HTML file
     */
    const G_HTML            = 'h';

    /**
     * Set the hostname (required!)
     *
     * @param string $hostname
     * @return void
     */
    public function setHostName($hostname)
	{

        $this->hostname = $hostname;

    }

    public function setPort($port)
	{

        $this->port = $port;

    }

    function ReturnResponse()
	{
		$data = $this->encodeResponse($this->dataItems);
		
		// clear response
		$this->dataItems = array();
		
		return $data . "\r\n";
	}
	

    public function WriteText($text)
	{
		if (strlen($text) > 64)
		{
			$tmp = str_split($text,64);
			
			foreach($tmp as $line)
			{
				$this->dataItems[] =   array(self::G_TEXT,     $line);
			}
			
		}
		else
			$this->dataItems[] =   array(self::G_TEXT,     $text);
	}
	
    public function WriteDirectory($title,$location)
	{
		if (strlen($title) > 64)
		{
			$title = substr($title,0,62) . '...';
		}		
		$this->dataItems[] =   array(self::G_DIRECTORY, $title, $location);
	}
	
    public function WriteSearch($title,$location)
	{
		if (strlen($title) > 64)
		{
			$title = substr($title,0,62) . '...';
		}		
		$this->dataItems[] =   array(self::G_SEARCH, $title, $location);
	}


    public function WriteHTMLLink($title,$location)
	{
		$location = "URL:" . $location;
		$this->dataItems[] =   array(self::G_HTML, $title, $location);
	}
    /**
     * encode the response in a gopher format
     *
     * @param array $data
     * @return string
     */
    public function encodeResponse($data)
    {

        $raw = '';
        foreach($data as $item)
        {

            // here we gather information about an item and fill in the missing fields
            $type     = $item[0];
            $title    = $item[1];
            $location = isset($item[2])?$item[2]:$title;
            $server   = isset($item[3])?$item[3]:$this->hostname;
            $port     = isset($item[4])?$item[4]:$this->port;

            switch($type)
	    {
                // If the type is text, we will leave the other items empty
                case self::G_TEXT :
                    $location = 'fake';
                    $server = '(NULL)';
                    $port = '0';
                    break;
            }

            // The tab-seperated directory item
            $raw.=$type . $title . "\t" . $location . "\t" . $server . "\t" . $port . "\n";

        }
        // A dot on the end of the request
        $raw.=".";
        return $raw;

    }

  }


function un_htmlspecialchars($string)
{
	// SMF 2.0.17 BSD
	static $translation;

	if (!isset($translation))
		$translation = array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES)) + array('&#039;' => '\'', '&nbsp;' => ' ');

	return strtr($string, $translation);
}


 function replaceBBcodes($text)
    {
		$text = un_htmlspecialchars($text);

		// BBcode array
		$find = array(
			'~\[b\](.*?)\[/b\]~s',
			'~\[i\](.*?)\[/i\]~s',
			'~\[u\](.*?)\[/u\]~s',
			'~\[size=([^"><]*?)\](.*?)\[/size\]~s',
			'~\[color=([^"><]*?)\](.*?)\[/color\]~s',
			'~\[url=((?:ftp|https?)://[^"><]*?)\](.*?)\[/url\]~s',
			'~\[url\]((?:ftp|https?)://[^"><]*?)\[/url\]~s',
			'~\[img width=(0-9*) height=(0-9*)\](https?://[^"><]*?\.(?:jpg|jpeg|gif|png|bmp))\[/img\]~s',
			'~\[img\](https?://[^"><]*?\.(?:jpg|jpeg|gif|png|bmp))\[/img\]~s',


		);

		$replace = array(
			'$1', //b
			'$1', //i
			'$1', //u
			'$2', // size
			'$2', // olor
			'$2<br />$1',  //url=
			'$1', // url
			'$3', //img
			'$1',
		);

		return preg_replace($find, $replace, $text);
	}