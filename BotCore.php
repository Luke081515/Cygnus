<?php
require_once __DIR__ . '/Password.php';
/** BotCore.php
* Central file of the Cygnus-Framework
* Most functions get loaded from this file.
* @author Freddy2001 <freddy2001@wikipedia.de>, Hgzh, Luke081515 <luke081515@tools.wmflabs.org>, MGChecker <hgasuser@gmail.com>
* @requires extensions: JSON
* @version V2.1 alpha
*/
class Core extends Password {
	protected $username;
	protected $password;
	protected $curlHandle;
	protected $site;
	protected $protocol;
	protected $job;
	protected $assert;
	protected $mail;
	protected $mailcontent;
	private $version = 'Cygnus-Framework V2.1 alpha';
	private $ua;
	private $maxlag;

	/** initcurl
	* initializes curl
	* this function usually should be called first
	* creates the connection object
	* does the login if the bot isn't explicitly told not to by $loadSettings
	* if that's not the case, it reads the login data of settings.json and does the login afterwards
	* @author Hgzh / MGChecker
	* @param $job - name of the job; used for the internal storage of cookies
	* @param $account - name of the accounts in settings.json
	* @param $assert - [optional: bot] if set to 'user' edits can be made without botflag
	* @param $loadSettings - [optional: true] if the settings shall be loaded or another way will be used to login
	*/
	public function initcurl($account, $job, $pUseHTTPS = true, $assert = 'bot') {
		if ($assert !== 'bot' && $assert !== 'user')
			throw new Exception('assert has to be \'bot\' or \'user\'');
		$this->assert = $assert;
		$this->start($account);
		$this->job = $job;
		if ($pUseHTTPS === true)
			$this->protocol = 'https';
		else
			$this->protocol = 'http';
		// init curl
		$curl = curl_init();
		if ($curl === false)
			throw new Exception('Curl initialization failed.');
		else
			$this->curlHandle = $curl;
		$this->login();
		echo "\n***** Starting up....\nVersion: " . $this->version . "\n*****";
		$this->ua = "User:" . $this->username . " - " . $this->job . " - " . $this->version;
		// change if you need more, default is 5
		$this->setMaxlag(5);
	}
	/** initcurlArgs
	* Use this function instead of initcurl if you want to use args or console to tell the bot the password
	* @author Luke081515
	* @param $job - name of the job; useful for saving the cookies
	* @param $pUseHTTPS - [optional: true] if false, http will be used
	* @param $assert - [optional: bot] if set to 'user' instead, you can use a bot without flag
	*/
	public function initcurlArgs($job, $pUseHTTPS = true, $assert = 'bot') {
		if ($assert !== 'bot' && $assert !== 'user')
			exit(1);
		$this->assert = $assert;
		$this->job = $job;
		if ($pUseHTTPS === true)
			$this->protocol = 'https';
		else
			$this->protocol = 'http';
		// init curl
		$curl = curl_init();
		if ($curl === false)
			throw new Exception('Curl initialization failed.');
		else
			$this->curlHandle = $curl;
		echo "\n***** Starting up....\nVersion: " . $this->version . "\n*****";
		$this->ua = "User:" . $this->username . " - " . $this->job . " - " . $this->version;
	}
	public function __construct($account, $job, $pUseHTTPS = true) {
	}
	public function __destruct() {
		curl_close($this->curlHandle);
	}
	/** httpRequest
	* does http(s) requests
	* mostly used to communicate with the API
	* @param $pArguments - API params you want to exectute (starts normally with action=)
	* @param $job - used to find the right cookies. just use $this->job
	* @param $pMethod - [optional: POST] Method of the request. For querys, you just use GET
	* @param $pTarget - [optional: w/api.php] use this,
		if Special:Version shows something different than w/api.php for using the api
	* @author Hgzh
	* @returns answer of the API
	*/
	protected function httpRequest($arguments, $job, $method = 'POST', $target = 'w/api.php') {
		$baseURL = $this->protocol . '://' .
				   $this->site . '/' .
				   $target;
		$method = strtoupper($method);
		if ($arguments != '') {
			if ($method === 'POST') {
				$requestURL = $baseURL;
				$postFields = $arguments;
			} else if ($method === 'GET') {
				$requestURL = $baseURL . '?' .
							  $arguments;
			} else
				throw new Exception('Unknown http request method.');
		}
		if (!$requestURL)
			throw new Exception('No arguments for http request found.');
		// set curl options
		curl_setopt($this->curlHandle, CURLOPT_USERAGENT, $this->ua);
		curl_setopt($this->curlHandle, CURLOPT_URL, $requestURL);
		curl_setopt($this->curlHandle, CURLOPT_ENCODING, 'UTF-8');
		curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, realpath('Cookies' . $job . '.tmp'));
		curl_setopt($this->curlHandle, CURLOPT_COOKIEJAR, realpath('Cookies' . $job . '.tmp'));
		// if posted, add post fields
		if ($method === 'POST' && $postFields != '') {
			curl_setopt($this->curlHandle, CURLOPT_POST, 1);
			curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $postFields);
		} else {
			curl_setopt($this->curlHandle, CURLOPT_POST, 0);
			curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, '');
		}
		// perform request
		$success = false;
		for ($i = 1; $i <= 18; $i++) {
			$rqResult = curl_exec($this->curlHandle);
			if ($rqResult !== false) {
				$success = true;
				break 1;
			} else {
				echo "\nCurl request with arguments \"" . $arguments . "\" to " . $this->site . " failed (try: $i out of 18)" . curl_error($this->curlHandle);
				sleep(10);
			}
		}
		if ($success === true)
			return $rqResult;
		else
			throw new Exception('Curl request definitively failed: ' . curl_error($this->curlHandle));
	}
	/** requireToken
	* query the api for the token
	* @author Hgzh / Luke081515 / MGChecker
	* @param $type - [optional: csrf] - type of the token (see the api docs for details)
	* @returns requested token
	*/
	public function requireToken($type = 'csrf') {
		if ($type === "login")  // No assert on login
			$result = $this->httpRequest('action=query&format=json&maxlag=' . $this->maxlag . '&meta=tokens&type=' . $type, $this->job, 'GET');
		else
			$result = $this->httpRequest('action=query&format=json&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&meta=tokens&type=' . $type, $this->job, 'GET');
		$tree = json_decode($result, true);
		$tokenname = $type . "token";
		try {
			$token = $tree['query']['tokens'][$tokenname];
		} catch (Exception $e) {
			throw new Excpetion('You filed an invalid token request.' . $e->getMessage());
		}
		if ($token === '')
			throw new Exception('Could not receive login token.');
		return $token;
	}
	/** login
	* used for login
	* do not use this function, use initcurl/initcurlArgs instead
	* @author Hgzh
	*/
	protected function login() {
		$lgToken = $this->requireToken('login');
		// perform login
		$result = $this->httpRequest('action=login&maxlag=' . $this->maxlag . '&format=json&lgname=' . urlencode($this->username) .
			'&lgpassword=' . urlencode($this->password) .
			'&lgtoken=' . urlencode($lgToken), $this->job);
		$tree = json_decode($result, true);
		$lgResult = $tree['login']['result'];
		// manage result
		if ($lgResult == 'Success')
			return true;
		else
			throw new Exception('Login failed with message: ' . $lgResult);
	}
	/** logout
	* Does a logout
	*/
	public function logout() {
		$this->httpRequest('action=logout', $this->job);
	}
	/** DO NOT USE this function
	* This is for unit-tests only
	*/
	public function setSite($site) {
		$this->site = $site;
	}
	/** DO NOT USE this function
	* This is for unit-tests only
	*/
	public function setUsername($username) {
		$this->username = $username;
	}
	/** DO NOT USE this function
	* This is for unit-tests only
	*/
	public function setPassword($password) {
		$this->password = $password;
	}
	/** start
	* Searches for the data from Password.php, does the login after that
	* this function is used by initcurl, use initcurl instead
	* @author Luke081515
	*/
	public function start($account) {
		$a = 0;
		$Found = false;
		$this->init();
		$LoginName = unserialize($this->getLoginName());
		$LoginHost = unserialize($this->getLoginHost());
		$LoginAccount = unserialize($this->getLoginAccount());
		$LoginPassword = unserialize($this->getLoginPassword());
		$Mail = unserialize($this->getMail());
		while (isset($LoginName[$a])) {
			if ($LoginName[$a] === $account) {
				$this->site = $LoginHost[$a];
				$this->username = $LoginAccount[$a];
				$this->password = $LoginPassword[$a];
				$this->mail = $Mail[$a];
				$Found = true;
			}
			$a++;
		}
		if (!$Found) {
			throw new Exception('No matching credentials available.');
		}
	}
	/** checkResult
	* this is an internal method
	* this method gets called if there is an error in executing an action
	* depending on the code, it executes different actions
	* @author Luke081515
	* @param $result - errorcode of the api
	* @returns fail - edit failed, a retry would not be useful
	* @returns retry - try it again, it may work
	* @returns conflict - there is an edit conflict
	*/
	private function checkResult($result) {
		if ($result === 'maxlag' || $result === 'readonly' || $result === 'unknownerror-nocode' || $result === 'unknownerror' || $result === 'ratelimited') {
			echo '\nEdit failed. Reason: $result. Please try again';
			return 'retry';
		} else if ($result === 'blocked' || $result === 'confirmemail' || $result === 'autoblocked') {
			throw new Exception('You will not be able to edit soon. Reason: $result');
		} else if ($result === 'assertuserfailed' || $result === 'assertbotfailed') {
			$this->login();
			return 'retry';
		} else if ($result === 'editconflict') {
			echo '\nEditconflict detected';
			return 'conflict';
		} else {
			echo 'Action failed. Error: ' . $result;
			return 'fail';
		}
	}
	/** readPageEngine
	* for internal use only, used for readPage/readSection functions
	* @param $request - the data for the api to get the content of the page
	* @author Luke081515
	* @returns text of the page
	*/
	private function readPageEngine($request) {
		$page = json_decode($this->httpRequest($request, $this->job, 'GET'), true);
		$pageID = $page['query']['pageids'][0];
		return $page['query']['pages'][$pageID]['revisions'][0]['*'];
	}
	/** readPage
	* Returns the content of a page
	* @param $title - name of the page including namespaces
	* @author MGChecker
	* @returns content of the page
	*/
	public function readPage($title) {
		$request = 'action=query&prop=revisions&format=json&rvprop=content&rvlimit=1&rvcontentformat=text%2Fx-wiki&titles=' . urlencode($title) .
			'&rvdir=older&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&rawcontinue=&indexpageids=1';
		return $this->readPageEngine($request);
	}
	/** readPageId
	* Returns the content of a page
	* @param $pageID - ID of the page
	* @author MGChecker
	* @returns content of the page
	*/
	public function readPageID($pageID) {
		$request = 'action=query&prop=revisions&format=json&rvprop=content&rvlimit=1&rvcontentformat=text%2Fx-wiki&pageids=' . urlencode($pageID) .
			'&rvdir=older&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&rawcontinue=&indexpageids=1';
		return $this->readPageEngine($request);
	}
	/** readPageJs
	* Returns the content of a JS page
	* @param $title - title of the page
	* @author MGChecker
	* @returns text of the page
	*/
	public function readPageJs($title) {
		$request = 'action=query&prop=revisions&format=json&rvprop=content&rvlimit=1&rvcontentformat=text%2Fjavascript&titles=' . urlencode($title) .
			'&rvdir=older&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&rawcontinue=&indexpageids=1';
		return $this->readPageEngine($request);
	}
	/** readPageCss
	* Returns the content of a CSS page
	* @param $title - title of the page
	* @author MGChecker
	* @returns text of the page
	*/
	public function readPageCss($title) {
		$request = 'action=query&prop=revisions&format=json&rvprop=content&rvlimit=1&rvcontentformat=text%2Fcss&titles=' . urlencode($title) .
			'&rvdir=older&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&rawcontinue=&indexpageids=1';
		return $this->readPageEngine($request);
	}
	/** readSection
	* returns the content of a specified section
	* @param $title - name of the page
	* @param $section - number of the section
	* @author MGChecker
	* @returns text of the section
	*/
	public function readSection($title, $section) {
		$request = 'action=query&prop=revisions&format=json&rvprop=content&rvlimit=1&rvcontentformat=text%2Fx-wiki&rvdir=older&indexpageids=1&rvsection=' . urlencode($section) .
			'&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&titles=' . urlencode($title);
		return $this->readPageEngine($request);
	}
	/** getTableOfContents
	* returns the Table of Contents of a page
	* @param $page - Title of the page
	* @author Luke081515
	* @returns two-dimensional array
	* @returns first dimension: the section
	* @retuns second dimension:
	* 	[0] => level;
	* 	[1] => title of the section
	* 	[2] => section number at the table of contents (e.g. something like 7.5 as well);
	* 	[3] => section number as int;
	*/
	public function getTableOfContents($title) {
		$result = $this->httpRequest('action=parse&format=json&maxlag=' . $this->maxlag . '&assert=' . $this->assert .
			'&page=' . urlencode($title) . '&prop=sections', $this->job, 'GET');
		$Data = json_decode($result, true);
		$a = 0;
		while (isset($Data['parse']['sections'][$a]['level'])) {
			$ret[$a][0] = $Data['parse']['sections'][$a]['level'];
			$ret[$a][1] = $Data['parse']['sections'][$a]['line'];
			$ret[$a][2] = $Data['parse']['sections'][$a]['number'];
			$ret[$a][3] = $Data['parse']['sections'][$a]['index'];
			$a++;
		}
		return $ret;
	}
	/** editPageEngine
	* internal method which does the edit. please use one of the following functions instead
	* @param $title - name of the page
	* @param $content - new content
	* @param $summary - summary
	* @param $botflag - if true, the bot will use a botflag
	* @param $minorflag - if true the edit will get marked as minor
	* @param $noCreate - should the page get recreated in case that this is needed?
	* @param $sectionnumber - which section should get edited? (default => the whole page)
	* @param $overrideNobots - Should {{NoBots}} gets overriden?
	* @author Hgzh / Luke081515 / MGChecker
	* @returns unserialized answer of the api, if successful
	*/
	private function editPageEngine($title, $content, $summary, $botflag, $minorflag, $noCreate = 1, $sectionnumber = -1, $overrideNobots = false) {
		retry:
		if ($overrideNobots !== true) {
			if ($this->allowBots($this->readPage($title)) === false)
				return 'nobots';
		}
		$token = $this->requireToken();
		// perform edit
		$request = 'action=edit&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&format=json&bot=&title=' . urlencode($title) .
			'&text=' . urlencode($content) .
			'&token=' . urlencode($token) .
			'&summary=' . urlencode($summary) .
			'&bot=' . urlencode($botflag) .
			'&minor=' . urlencode($minorflag);
		if ($noCreate === 1)
			$request .= '&minor=' . urlencode($minorflag);
		if ($sectionnumber !== -1)
			$request .= '&section=' . urlencode($sectionnumber);
		$result = $this->httpRequest($request, $this->job);
		$tree = json_decode($result, true);
		$editres = $tree['edit']['result'];
		// manage result
		if ($editres == 'Success') {
			if (array_key_exists('nochange', $tree['edit'])) {
				return array('nochange');
			} else {
				return array($tree['edit']['oldrevid'], $tree['edit']['newrevid']);
			}
		}
		else {
			$Code = $this->checkResult($editres);
			if ($Code === 'fail')
				return 'fail';
			else if ($Code === 'retry')
				goto retry;
			else if ($Code === 'conflict')
				return 'conflict';
		}
	}
	/** editPage
	* edits a page
	* @param $title - title of the page
	* @param $content - new content
	* @param $summary - summary
	* @param $noCreate - should the page get recreated? default is no
	* @param $overrideNobots - should {{NoBots}} get overriden? default is no
	* @author Freddy2001 / Luke081515
	* @returns unserialized answer of the api, if successful
	*/
	public function editPage($title, $content, $summary, $noCreate = 1, $overrideNobots = false) {
		if ($this->assert == 'bot')
			$botflag = true;
		else
			$botflag = false;
		return $this->editPageEngine($title, $content, $summary, $botflag, false, $noCreate, -1, $overrideNobots);
	}
	/** editPageMinor
	* edits a page and marks the edit as minor
	* @param $title - title of the page
	* @param $content - new content
	* @param $summary - summary
	* @param $noCreate - should the page get recreated? default is no
	* @param $overrideNobots - should {{NoBots}} get overriden? default is no
	* @author Freddy2001 / Luke081515
	* @returns unserialized answer of the api, if successful
	*/
	public function editPageMinor($title, $content, $summary, $noCreate = 1, $overrideNobots = false) {
		if ($this->assert == 'bot')
			$botflag = true;
		else
			$botflag = false;
		return $this->editPageEngine($title, $content, $summary, $botflag, true, $noCreate, -1, $overrideNobots);
	}
	/** editPageD
	* edits a page
	* @param $title - title of the page
	* @param $content - new content
	* @param $summary - summary
	* @param $botflag - if true, the edit will get marked with a botflag
	* @param $minorflag - if true, the edit will get marked as minor
	* @param $noCreate - should the page get recreated? default is no
	* @param $overrideNobots - should {{NoBots}} get overriden? default is no
	* @author MGChecker / Luke081515
	* @returns unserialized answer of the api, if successful
	*/
	public function editPageD($title, $content, $summary, $botflag, $minorflag, $noCreate = 1, $overrideNobots = false) {
		return $this->editPageEngine($title, $content, $summary, $botflag, $minorflag, $noCreate, -1, $overrideNobots);
	}
	/** editSection
	* edits a section
	* @param $title - title of the page
	* @param $content - new content
	* @param $summary - summary
	* @param $sectionnumber - number of the section
	* @param $noCreate - should the page get recreated? default is no
	* @param $overrideNobots - should {{NoBots}} get overriden? default is no
	* @author Freddy2001 / MGChecker / Luke081515
	* @returns unserialized answer of the api, if successful
	*/
	public function editSection($title, $content, $summary, $sectionnumber, $noCreate = 1, $overrideNobots = false) {
		if ($this->assert == 'bot')
			$botflag = true;
		else
			$botflag = false;
		if ($sectionnumber < 0)
			throw new Exception('You selected a invalid section number. To edit a whole page, use editPage().');
		return $this->editPageEngine($title, $content, $summary, $botflag, false, $noCreate, $sectionnumber, $overrideNobots);
	}
	/** editSection
	* edits a section, marks the edit as minor
	* @param $title - title of the page
	* @param $content - new content
	* @param $summary - summary
	* @param $sectionnumber - number of the section
	* @param $noCreate - should the page get recreated? default is no
	* @param $overrideNobots - should {{NoBots}} get overriden? default is no
	* @author Freddy2001 / MGChecker / Luke081515
	* @returns unserialized answer of the api, if successful
	*/
	public function editSectionMinor($title, $content, $summary, $sectionnumber, $noCreate = 1, $overrideNobots = false) {
		if ($this->assert == 'bot')
			$botflag = true;
		else
			$botflag = false;
		if ($sectionnumber < 0)
			throw new Exception('You selected a invalid section number. To edit a whole page, use editPageMinor().');
		return $this->editPageEngine($title, $content, $summary, $botflag, true, $noCreate, $sectionnumber, $overrideNobots);
	}
	/** editPageD
	* edits a page
	* @param $title - title of the page
	* @param $content - new content
	* @param $summary - summary
	* @param $botflag - if true, the edit will get marked with a botflag
	* @param $minorflag - if true, the edit will get marked as minor
	* @param $sectionnumber - number of the section
	* @param $noCreate - should the page get recreated? default is no
	* @param $overrideNobots - should {{NoBots}} get overriden? default is no
	* @author MGChecker / Luke081515
	* @returns unserialized answer of the api, if successful
	*/
	public function editSectionD($title, $content, $summary, $sectionnumber, $botflag, $minorflag, $noCreate = 1, $overrideNobots = false) {
		if ($sectionnumber < 0)
			throw new Exception('You selected a invalid section number. To edit a whole page, use editPageD().');
		return $this->editPageEngine($title, $content, $summary, $botflag,  $minorflag, $noCreate, $sectionnumber, $overrideNobots);
	}
	/** movePage
	* moves a page
	* @param $oldTitle - old title of a page
	* @param $newTitle - new title of a page
	* @param $reason - reason for the action
	* @param - $bot (default: 0) - use a botflag?
	* @param - $movetalk (default: 1) - move the talk page as well?
	* @param - $noredirect - (default: 1) - create a redirect?
	* @returns serialized answer of the API
	*/
	public function movePage($oldTitle, $newTitle, $reason, $bot = 0, $movetalk = 1, $noredirect = 1) {
		$token = $this->requireToken();
		$data = 'action=move&format=json&assert=' . $this->assert . 'maxlag=' . $this->maxlag .
			'&from=' . urlencode($oldTitle) .
			'&to=' . urlencode($newTitle) .
			'&reason=' . urlencode($reason) .
			'&bot=' . $bot .
			'&movetalk=' . $movetalk .
			'&noredirect=' . $noredirect .
			'&token=' . urlencode($token);
		$result = $this->httpRequest($data, $this->job);
		return serialize(json_decode($result, true));
	}
	/** watch
	* Allows to put a page on your watchlist, or remove it
	* @param $title - title of the page
	* @param $unwatch - default 0 - if 1, the page will get removed from the list
	* @returns mixed - true if successful, otherwise the API error code
	*/
	public function watch ($title, $unwatch = 0) {
		$token = $this->requireToken('watch');
		$data = 'action=watch'
			. '&format=json'
			. '&unwatch=' . $unwatch
			. '&titles=' . urlencode($title)
			. '&token=' . urlencode($token)
			. '&assert=' . $this->assert
			. '&maxlag=' . $this->maxlag;
		$result = $this->httpRequest($data, $this->job);
		$result = json_decode($result, true);
		if (array_key_exists('error', $result))
			return $result['error']['code'];
		return true;
	}
	/** review
	* Marks the specified version as reviewed or not reviewed
	* @param $revid - the revid of the revision to approve/unapprove
	* @param $comment [optional: ''] - the comment to add for the log
	* @param $unapprove [optional: 0] - if 1: mark the rev as unreviewed instead of reviewed
	* @returns string - success if succesful, otherwise the API error-code
	*/
	public function review($revid, $comment = '', $unapprove = 0) {
		$token = $this->requireToken();
		$data = 'action=review&format=json&assert=' . $this->assert .
			'&maxlag=' . $maxlag .
			'&revid=' . urlencode($revid) .
			'&unapprove=' . urlencode($unapprove) .
			'&comment=' . urlencode($comment) .
			'&token=' . urlencode($token);
		$result = $this->httpRequest($data, $this->job);
		$result = json_decode($result, true);
		if (array_key_exists('error', $result)) {
			if ($result['error']['code'] === 'notreviewable')
				return $result['error']['code'];
			else
				return $this->checkResult($result['error']['code']);
		} else
			return 'success';
	}

	// User related functions
	/** getUserEditcount
	* returns the editcount of a user, false if the user does not exist
	* @author Luke081515
	* @param $username - The username of the user
	* @returs editcount as int if the user does exist, false if not
	*/
	public function getUserEditcount ($username) {
		$result = $this->httpRequest('action=query&format=json&list=users&usprop=editcount&ususers=' . urlencode($username), $this->job, 'GET');
		if (isset($result['query']['users'][0]['missing']))
			return false;
		$result = json_decode($result, true);
		return $result['query']['users'][0]['editcount'];
	}
	/** checkUserBlock
	* checks if a user is blocked
	* @author Luke081515
	* @param $username - The username of the user
	* @returs true if blocked, false if not
	*/
	public function checkUserBlock ($username) {
		$result = $this->httpRequest('action=query&format=json&list=blocks&bkusers=' . urlencode($username), $this->job, 'GET');
		if (strpos($result, "reason") !== false)
			return true;
		return false;
	}
	/** checkUserMail
	* checks if the user has the mail feature active
	* @author Luke081515
	* @param $username - The username of the user
	* @returs false if not or if the user does not exist, true otherwise
	*/
	public function checkUserMail ($username) {
		$result = $this->httpRequest('action=query&format=json&list=users&usprop=emailable&ususers=' . urlencode($username), $this->job, 'GET');
		if (isset($result['query']['users'][0]['missing']))
			return false;
		if (strpos($result, "emailable") !== false)
			return true;
		return false;
	}
	/** getCatMembers
	* reads out all category members of a category, including subcategories
	* works till you have more than 5000 subcategories per category
	* @author Luke081515
	* @param $kat - category, which should get analyzed
	* @param $onlySubCats - [optional: false] if true, only the subcategories will returned, not the pagetitles
	* @param $excludeWls - [optional: false] if true, you won't get categories with redirects
	* @returns false if the categories has no members, otherwise a serialized array with page titles
	*/
	public function getCatMembers($kat, $onlySubCats = false, $excludeWls = false) {
		$b = 0;
		$subCat[0] = $kat;
		$result = $this->httpRequest('action=query&list=categorymembers&format=json&cmtitle=' . urlencode($kat) .
			'&cmprop=title&cmtype=subcat&cmlimit=max&assert=' . $this->assert . '&maxlag=' . $this->maxlag .
			'&cmsort=sortkey&cmdir=ascending&rawcontinue=', $this->job, 'GET');
		$answer = json_decode($result, true);
		$a = 0;
		if (isset($answer['query']['categorymembers'][$a]['title'])) {
			$Sub = true;
			while (isset($answer['query']['categorymembers'][$a]['title'])) {
				$subCat[$b] = $answer['query']['categorymembers'][$a]['title'];
				$b++;
				$a++;
			}
		}
		$b = 0;
		$c = 0;
		if ($onlySubCats === true)
			return $subCat;
		if ($excludeWls === false) {
			while (isset($subCat[$b]))
			{
				$result = $this->httpRequest('action=query&list=categorymembers&format=json&cmtitle=' . urlencode($subCat[$b]) .
					'&cmprop=title&cmtype=page&cmlimit=max&cmsort=sortkey&cmdir=ascending&rawcontinue=', $this->job, 'GET');
				$answer = json_decode($result, true);
				$Cont = false;
				if (isset($answer['query-continue']['categorymembers']['cmcontinue'])) {
					$Continue = $answer['query-continue']['categorymembers']['cmcontinue'];
					$Cont = true;
				}
				while ($Cont === true) {
					$a = 0;
					if (isset($answer['query']['categorymembers'][$a]['title'])) {
						while (isset($answer['query']['categorymembers'][$a]['title'])) {
							$page[$c] = $answer['query']['categorymembers'][$a]['title'];
							$c++;
							$a++;
						}
					}
					$result = $this->httpRequest('action=query&list=categorymembers&format=json&cmcontinue=' . $Continue
						. '&cmtitle=' . urlencode($subCat[$b])
						. '&cmprop=title&cmtype=page&cmlimit=max&cmsort=sortkey&cmdir=ascending&rawcontinue=', $this->job, 'GET');
					$answer = json_decode($result, true);
					if (isset($answer['query-continue']['categorymembers']['cmcontinue'])) {
						$Continue = $answer['query-continue']['categorymembers']['cmcontinue'];
						$Cont = true;
					} else
						$Cont = false;
				}
				$a = 0;
				if (isset($answer['query']['categorymembers'][$a]['title']) === true) {
					while (isset($answer['query']['categorymembers'][$a]['title'])) {
						$page[$c] = $answer['query']['categorymembers'][$a]['title'];
						$c++;
						$a++;
					}
				}
				$b++;
			}
		} else {
			while (isset($subCat[$b])) {
				$result = $this->httpRequest('action=query&format=json&generator=categorymembers&gcmtitle=' . urlencode($subCat[$b]) .
					'&prop=info&gcmlimit=max&rawcontinue=&redirects', $this->job, 'GET');
				$answer = json_decode($result, true);
				$Cont = false;
				if (isset($answer['query-continue']['categorymembers']['gcmcontinue'])) {
					$Continue = $answer['query-continue']['categorymembers']['gcmcontinue'];
					$Cont = true;
				}
				while ($Cont === true) {
					$a = 0;
					if (isset($answer['query']['pages'][$a]['title'])) {
						while (isset($answer['query']['pages'][$a]['title'])) {
							$page[$c] = $answer['query']['pages'][$a]['title'];
							$c++;
							$a++;
						}
					}
					try {
						$result = $this->httpRequest('action=query&format=json&generator=categorymembers&gcmtitle=' . urlencode($subCat[$b]) .
							'&gmcontinue=' . $Continue .
							'&prop=info&gcmlimit=max&rawcontinue=&redirects', $this->job, 'GET');
					} catch (Exception $e) {
						throw $e;
					}
					$result = $this->httpRequest('action=query&format=json&generator=categorymembers&gcmtitle=' . urlencode($subCat[$b]) .
						'&gmcontinue=' . $Continue .
						'&prop=info&gcmlimit=max&rawcontinue=&redirects', $this->job, 'GET');
					$answer = json_decode($result, true);
					if (isset($answer['query-continue']['pages']['gcmcontinue'])) {
						$Continue = $answer['query-continue']['pages']['gcmcontinue'];
						$Cont = true;
					} else
						$Cont = false;
				}
				$a = 0;
				if (isset($answer['query']['pages'][$a]['title'])) {
					while (isset($answer['query']['pages'][$a]['title'])) {
						$page[$c] = $answer['query']['pages'][$a]['title'];
						$c++;
						$a++;
					}
				}
				$b++;
			}
		}
		if (!isset($page[0]))
				return false;
		else
			return serialize($page);
	}
	/** getPageCats
	* reads out all categories of a page
	* works till the page has more than 500 categories
	* @author Luke081515
	* @param $page - page that should get analyzed
	* @returns all categories as serialized array
	*/
	public function getPageCats($title) {
		$cats = $this->httpRequest('action=query&prop=categories&format=json&cllimit=max&titles=' . urlencode($title) .
			'&cldir=ascending&rawcontinue=&assert=' . $this->assert . '&maxlag=' .
			$this->maxlag . '&indexpageids=1', $this->job, 'GET');
		$cats = json_decode($cats, true);
		$pageID = $cats['query']['pageids'][0];
		$a = 0;
		while (isset($cats['query']['pages'][$pageID]['categories'][$a])) {
			$catResults[$a] = $cats['query']['pages'][$pageID]['categories'][$a];
			$a++;
		}
		if (!isset($catResults[0]))
			return false;
		return serialize($catResults);
	}
	/** getAllEmbedings
	* Returns a note that the method should get renamed.
	* Used Fatal instead of notice since renaming a method is not that difficult.
	* @author Luke081515
	* Should be removed at 2.2
	*/
	public function getAllEmbedings($templ) {
		throw new Exception ("This method is misspelled, this was the fault of the programmers, and"
			. "have now been fixed. Please rename your method to 'getAllEmbeddings', then it will work again.");
	}
	/** getAllEmbeddings
	* returns all embeddings of a page
	* @author Luke081515
	* @param name of the template
	* @returns false if not embedded, otherwise serialized array with pagetitles
	*/
	public function getAllEmbeddings($templ) {
		$b = 0;
		$Again = true;
		while ($Again === true) {
			if (isset($Continue))
				$data = 'action=query&list=embeddedin&format=json&eititle=' . urlencode($templ) .
					'&einamespace=0&eicontinue=' . urlencode($Continue) .
					'&eidir=ascending&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&eilimit=max&rawcontinue=';
			else
				$data = 'action=query&list=embeddedin&format=json&eititle=' . urlencode($templ) . '&einamespace=0&assert=' . $this->assert .
					'&maxlag=' . $this->maxlag . '&eidir=ascending&eilimit=max&rawcontinue=';
			$result = $this->httpRequest($data, $this->job, 'GET');
			$answer = json_decode($result, true);
			$a = 0;
			if (isset($answer['query-continue']['embeddedin']['eicontinue'])) {
				$Continue = $answer['query-continue']['embeddedin']['eicontinue'];
				$Again = true;
			}
			else
				$Again = false;
			if (isset($answer['query']['embeddedin'][$a]['title'])) {
				while (isset($answer['query']['embeddedin'][$a]['title'])) {
					$page[$b] = $answer['query']['embeddedin'][$a]['title'];
					$b++;
					$a++;
				}
			} else
				return false;
		}
		return serialize($page);
	}
	/** getAllPages
	* returns all pages of namespace
	* @author Luke081515
	* @param number of the namespace
	* @returns false if the namespace is empty, otherwise serialized array with pagetitles
	*/
	public function getAllPages($namespace) {
		$b = 0;
		$Again = true;
		while ($Again === true) {
			if (isset($Continue))
				$data = 'action=query&list=allpages&format=json&apcontinue=' . $Continue . '&apnamespace=' . $namespace .
					'&aplimit=max&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&apdir=ascending&rawcontinue=';
			else
				$data = 'action=query&list=allpages&format=json&apnamespace=' . $namespace .
					'&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&aplimit=max&apdir=ascending&rawcontinue=';
			$result = $this->httpRequest($data, $this->job, 'GET');
			$answer = json_decode($result, true);
			$a = 0;
			if (isset($answer['query-continue']['allpages']['apcontinue'])) {
				$Continue = $answer['query-continue']['allpages']['apcontinue'];
				$Again = true;
			} else
				$Again = false;
			if (isset($answer['query']['allpages'][$a]['title'])) {
				while (isset($answer['query']['allpages'][$a]['title'])) {
					$page[$b] = $answer['query']['allpages'][$a]['title'];
					$b++;
					$a++;
				}
			} else
				return false;
		}
		return serialize($page);
	}
	/** getPageID
	* returns the ID of a page
	* @author Luke081515
	* @param $page - name of the page
	* @returns int: PageID, bool: false if the page does not exist
	*/
	public function getPageID($title) {
		$data = 'action=query&format=json&assert=' . $this->assert . '&maxlag=' . $this->maxlag . '&prop=info&indexpageids=1&titles=' . urlencode($title);
		$result = $this->httpRequest($data, $this->job, 'GET');
		if (strpos ($result, 'missing') !== false)
			return false;
		$answer = json_decode($result, true);
		return $tree['query']['pageids'][0];
	}
	/** getLinks
	* returns all links that are located at a page, maximum 5000
	* @author Luke081515
	* @param $page - page that gets analyzed
	* @returns array with page titles
	*/
	public function getLinks($title) {
		$data = 'action=query&prop=links&format=json&assert=' . $this->assert .
			'&maxlag=' . $this->maxlag . '&pllimit=max&pldir=ascending&plnamespace=0&rawcontinue=&indexpageids=1&titles=' . urlencode($title);
		$result = json_decode($this->httpRequest($data, $this->job, 'GET'), true);
		while (isset($result['query']['pages'][$pageID]['links'][0]['title'])) {
		$pageID = $result['query']['pageids'][0];
		}
		if (isset($links[0]))
			return $links;
		return false;
	}
	/** getSectionTitle
	* returns the title and the number of a section
	* @author Freddy2001
	* @param title - name of the page
	* @param section - number of the section
	* @returns title and heading level as array
	*/
	public function getSectionTitle($title, $section) {
		$content = $this->readSection($title, $section);
		$sectionlevel = 5;
		while($sectionlevel > 1) {
			$searchnum = 1;
			$search = '=';
			while($searchnum < $sectionlevel) {
				$search = $search . '=';
				$searchnum++;
			}
			if (strpos(substr($content, strpos($content, '='), 5), $search) === false) {
				$sectionlevel--;
			} else {
				break;
			}
		}
		$content = substr($content, strpos($content, '=') + $sectionlevel);
		$content = substr($content, 0, strpos($content, '='));
		return ['title' => $content, 'level' => $sectionlevel, ];
	}
	/** getMaxlag
	* @author Luke081515
	* @returns $this->maxlag
	*/
	final public function getMaxlag() {
		return $this->maxlag;
	}
	/** setMaxlag
	* sets $this->maxlag
	* @author Luke081515
	* @param $maxlag - the maxlag to set
	*/
	final public function setMaxlag($maxlag) {
		if (is_int($maxlag))
			$this->maxlag = $maxlag;
		else
			throw new \Exception('The maxlag you specified is not a valid integer');
	}
	/** askOperator
	* asks a question on the console
	* @author Luke081515
	* @param $question - the question to display
	* @returns the response to the question
	*/
	public function askOperator($question) {
		echo $question;
		$handle = fopen ('php://stdin','r');
		$line = fgets($handle);
		return trim($line);
	}
	/** addMail
	* adds $content to a new line at the mail
	* @author Luke081515
	* @param $content - the content
	*/
	public function addMail($content) {
		$this->mailcontent = $this->mailcontent . '\n' . $content;
	}
	/** sendMail
	* sends the mail
	* clears the mail buffer
	* @author Luke081515
	* @param $subject - subject of the mail
	*/
	public function sendMail($subject) {
		mail($this->mail, $subject, $this->mailcontent);
		$this->mailcontent = '';
	}
	/** curlRequest
	* sends a curl request to a website
	* @author: Freddy2001
	* @param $url - URL of the page
	* @param $https - true:use https, false: use http
	*/
	protected function curlRequest($url, $https = true) {
		if ($https == true) {
			$protocol = 'https';
		} else {
			$protocol = 'http';
		}
		$baseURL = $protocol . '://' .
				   $url;
		$job = $baseURL;

		$curl = curl_init();

		if (!$baseURL)
			throw new Exception('no arguments for http request found.');
		// set curl options
		curl_setopt($curl, CURLOPT_USERAGENT, 'Cygnus');
		curl_setopt($curl, CURLOPT_URL, $baseURL);
		curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_COOKIEFILE, realpath('Cookies' . $job . '.tmp'));
		curl_setopt($curl, CURLOPT_COOKIEJAR, realpath('Cookies' . $job . '.tmp'));
		curl_setopt($curl, CURLOPT_POST, 0);
		curl_setopt($curl, CURLOPT_POSTFIELDS, '');

		// perform request
		$rqResult = curl_exec($curl);
		if ($rqResult === false)
			throw new Exception('curl request failed: ' . curl_error($curl));
		return $rqResult;
	}
	/** allowBots
	* checks if bots are not allowed, via Template {{nobots}}
	* adapted from https://en.wikipedia.org/wiki/Template:Bots#PHP
	* @param $text - Content to check
	* @returns false if bot is not allowed, otherwise true
	*/
	private function allowBots ($text) {
		if (preg_match('/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?'.preg_quote($this->username,'/').'.*?)\}\}/iS',$text))
			return false;
		if (preg_match('/\{\{(bots\|allow=all|bots\|allow=.*?'.preg_quote($this->username,'/').'.*?)\}\}/iS', $text))
			return true;
		if (preg_match('/\{\{(bots\|allow=.*?)\}\}/iS', $text))
			return false;
		return true;
	}

	/** Admin-functions
	* The following section includes functions which only sysops can use
	*/
	/** deletePage
	* Deletes a page
	* requires the "delete" right
	* @author Luke081515
	* @param $title - page to delete
	* @param $reason - the reason for the deletion, visible in the log
	* @returns - "success" if the deletion was successful, otherwise the error code of the api
	*/
	public function deletePage ($title, $reason) {
		$token = $this->requireToken();
		$data = 'action=delete&format=json' .
			'&title=' . urlencode($title) .
			'&reason=' . $reason .
			'&token=' . urlencode($token) .
			'&maxlag=' . $this->maxlag .
			'&assert=' . $this->assert;
		try {
			$result = $this->httpRequest($data, $this->job);
		} catch (Exception $e) {
			throw $e;
		}
		$result = json_decode($result, true);
		if (array_key_exists('error', $result))
			return $result['error']['code'];
		else
			return "success";
	}
	/** blockUser
	* Blocks a user or an IP
	* @author Luke081515
	* @param - $user - the user or IP to block
	* @param - $reason - the reason for the block
	* @param - $expiry - the expiry of the block
	* @param - $expiry - can be relative, like "5 months"
	* @param - $expiry - or can be absolute, like 2014-09-18T12:34:56Z, or never
	* @param - $anononly - if true, blocks only IPs, not logged in users
	* @param - $nocreate - disallows creation of accounts
	* @param - $autoblock - enables autoblock
	* @param - $noemail - blocks wikimail
	* @param - $hidename - hides the user
	* @param - $allowusertalk - allows the user to write on his own talkpage
	* @param - $reblock - overwrites existing blocks
	* @returns - "success" if successful, otherwise the API errorcode
	*/
	public function blockUser ($user, $reason, $expiry, $anononly = 1, $nocreate = 1, $autoblock = 1, $noemail = 0, $hidename = 0, $allowusertalk = 1, $reblock = 0) {
		$token = $this->requireToken();
		$data = 'action=block&format=json' .
			'&user=' . urlencode($user) .
			'&reason=' . urlencode($reason) .
			'&expiry=' . urlencode($expiry) .
			'&anononly=' . urlencode($anononly) .
			'&nocreate=' . urlencode($nocreate) .
			'&autoblock=' . urlencode($autoblock) .
			'&noemail=' . urlencode($noemail) .
			'&hidename=' . urlencode($hidename) .
			'&allowusertalk=' . urlencode($allowusertalk) .
			'&reblock=' . urlencode($reblock) .
			'&token=' . urlencode($token) .
			'&maxlag=' . $this->maxlag .
			'&assert=' . $this->assert;
		try {
			$result = $this->httpRequest($data, $this->job);
		} catch (Exception $e) {
			throw $e;
		}
		$result = json_decode($result, true);
		if (array_key_exists('error', $result))
			return $result['error']['code'];
		else
			return "success";
	}
	/** unblockUser
	* Unblocks a user or an IP
	* @author Luke081515
	* @param - $user - the user or IP to unblock
	* @param - $reason - the reason for the unblock
	* @returns - "success" if successful, otherwise the API errorcode
	*/
	public function unblockUser ($user, $reason) {
		$token = $this->requireToken();
		$data = 'action=unblock&format=json' .
			'&user=' . urlencode($user) .
			'&reason=' . urlencode($reason) .
			'&token=' . urlencode($token) .
			'&maxlag=' . $this->maxlag .
			'&assert=' . $this->assert;
		try {
			$result = $this->httpRequest($data, $this->job);
		} catch (Exception $e) {
			throw $e;
		}
		$result = json_decode($result, true);
		if (array_key_exists('error', $result))
			return $result['error']['code'];
		else
			return "success";
	}
	/** protectPage
	* Protects a page
	* @author Luke081515
	* @param - $title - the page to protect
	* @param - $reason - the reason to set
	* @param - $protections - pipe-seperated protection. Mention all levels you want to change
	* @param - $protections - to remove a protection, use all, e.g. "edit=all|move=sysop"
	* @param - $expiry - pipe-separated list of expiry timestamps in GNU timestamp format.
	* @param - $expiry - the first timestamp applies to the first protection in protections, the second to the second, etc.
	* @param - $expiry - the timestamps infinite, indefinite and never result in a protection that will never expire.
	* @param - $expiry - timestamps like next Monday 16:04:57 or 9:28 PM tomorrow are also allowed, see the GNU web site for details.
	* @param - $expiry - the number of expiry timestamps must equal the number of protections, or you'll get an error message
	* @param - $expiry - an exception to this rule is made for backwards compatibility: if you specify exactly one expiry timestamp, it'll apply to all protections
	* @param - $expiry - not setting this parameter is equivalent to setting it to infinite
	* @param - $expiry - if you are using all, the param does not matter, but you need to set it
	* @param - $cascade - uses cascade protection
	* @returns - "success" if successful, otherwise the API errorcode
	*/
	public function protectPage ($title, $reason, $protections, $expiry, $cascade) {
		$token = $this->requireToken();
		$data = 'action=protect&format=json' .
			'&page=' . urlencode($title) .
			'&reason=' . urlencode($reason) .
			'&protections=' . urlencode($protections) .
			'&expiry=' . urlencode($expiry) .
			'&cascade=' . urlencode($cascade) .
			'&token=' . urlencode($token) .
			'&maxlag=' . $this->maxlag .
			'&assert=' . $this->assert;
		try {
			$result = $this->httpRequest($data, $this->job);
		} catch (Exception $e) {
			throw $e;
		}
		$result = json_decode($result, true);
		if (array_key_exists('error', $result))
			return $result['error']['code'];
		else
			return "success";
	}
	/** stabilize
	* changes the settings who can review a page, and which version gets shown
	* @author Luke081515
	* @param $title - the page to change
	* @param $expiry - expiry timestamp in GNU timestamp format.
	**	The timestamps infinite, indefinite and never result in a protection that will never expire.
	**	Timestamps like next Monday 16:04:57 or 9:28 PM tomorrow are also allowed, see the GNU web site for details.
	* @param $default - which version should be shown? 'latest' or 'stable'?
	* @param $autoreview - who is allowed to review? 'all' or 'sysop'?
	* @param $review - review the current version? 0 or 1
	* @returns string - success or the error code
	*/
	public function stabilize($title, $expiry, $reason, $default, $autoreview, $review) {
		$token = $this->requireToken();
		$data = 'action=stabilize&format=json&maxlag=5&default=' . urlencode($default) .
			'&autoreview=' . urlencode($autoreview) .
			'&expiry=' . urlencode($expiry) .
			'&reason=' . urlencode($reason) .
			'&title=' . urlencode($title) .
			'&review=' . urlencode($review) .
			'&token=' . urlencode($token) .
			'&maxlag=' . $this->maxlag .
			'&assert=' . $this->assert;
		$result = $this->httpRequest($data, $this->job);
		$result = json_decode($result, true);
		if (array_key_exists('error', $result))
			return $result['error']['code'];
		else
			return "success";
	}
}
?>