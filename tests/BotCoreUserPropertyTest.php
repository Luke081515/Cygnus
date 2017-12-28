<?php
require_once __DIR__ . '/../TestCore.php';
use PHPUnit\Framework\TestCase;

/**
* @covers BotCore
* Tests for all read function
*/
final class BotCoreUserPropertyTest extends TestCase {
	public $params;
	public $Core;

	private function createLogin() {
		global $argv;
		$i = 0;
		for ($j = 1; isset($argv[$j]); $j++) {
			$params[$i] = $argv[$j];
			$i++;
		}
		return new TestCore($params);
	}
	/**
	* @covers BotCore::checkUserExistence
	*/
	public function testUserExistsSuccessful() {
		$Core = $this->createLogin();
		$actually = $Core->execute(array("checkUserExistence", "Luke081515"));
		$this->assertTrue($actually);
	}
	/**
	* @covers BotCore::checkUserExistence
	*/
	public function testUserDoesNotExists() {
		$Core = $this->createLogin();
		$actually = $Core->execute(array("checkUserExistence", "LukeO81515"));
		$this->assertFalse($actually);
	}
	/**
	* @covers BotCore::getUserEditcount
	*/
	public function testUserEditcountSuccessful() {
		$Core = $this->createLogin();
		$expected = 1;
		$actually = $Core->execute(array("getUserEditcount", "UTDummyUser"));
		$this->assertEquals($expected, $actually);
	}
	/**
	* @covers BotCore::getUserEditcount
	*/
	public function testUserEditcountDoesNotExists() {
		$Core = $this->createLogin();
		$actually = $Core->execute(array("getUserEditcount", "LukeO81515"));
		$this->assertFalse($actually);
	}
	/**
	* @covers BotCore::checkUserBlock
	*/
	public function testUserBlockBlocked() {
		$Core = $this->createLogin();
		$actually = $Core->execute(array("checkUserBlock", "ABlockedUser"));
		$this->assertTrue($actually);
	}
	/**
	* @covers BotCore::checkUserBlock
	*/
	public function testUserBlockNotBlocked() {
		$Core = $this->createLogin();
		$actually = $Core->execute(array("checkUserBlock", "Luke081515"));
		$this->assertFalse($actually);
	}
	/**
	* @covers BotCore::checkUserMail
	*/
	public function testUserMailSuccessful() {
		$Core = $this->createLogin();
		$actually = $Core->execute(array("checkUserMail", "Luke081515"));
		$this->assertTrue($actually);
	}
	/**
	* @covers BotCore::checkUserMail
	*/
	public function testUserMailMissing() {
		$Core = $this->createLogin();
		$actually = $Core->execute(array("checkUserMail", "UTDummyUser"));
		$this->assertFalse($actually);
	}
	/**
	* @covers BotCore::checkUserMail
	*/
	public function testUserMailUserMissing() {
		$Core = $this->createLogin();
		$actually = $Core->execute(array("checkUserMail", "LukeO81515"));
		$this->assertFalse($actually);
	}
	/**
	* @covers BotCore::getUserGender
	*/	
	public function testcheckUserGenderMale() {
		$Core = $this->createLogin();
		$expected = "male";
		$actually = $Core->execute(array("getUserGender", "Luke081515"));
		$this->assertEquals($expected, $actually);
	}
	/**
	* @covers BotCore::getUserGender
	*/	
	public function testcheckUserGenderFemale() {
		$Core = $this->createLogin();
		$expected = "female";
		$actually = $Core->execute(array("getUserGender", "Freddy2001"));
		$this->assertEquals($expected, $actually);
	}
	/**
	* @covers BotCore::getUserGender
	*/	
	public function testcheckUserGenderUnknown() {
		$Core = $this->createLogin();
		$expected = "unknown";
		$actually = $Core->execute(array("getUserGender", "UTAccount"));
		$this->assertEquals($expected, $actually);
	}
	/**
	* @covers BotCore::getUserGender
	*/	
	public function testcheckUserGenderNoSuchUser() {
		$Core = $this->createLogin();
		$actually = $Core->execute(array("getUserGender", "NonExistantUser"));
		$this->assertFalse($actually);
	}
}
?>
