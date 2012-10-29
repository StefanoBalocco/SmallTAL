<?php
/*
 * Copyright (c) 2012, Stefano Balocco <Stefano.Balocco@gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice, this
 *     list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *   * Redistributions in binary form must include, without any additional cost,
 *     the source code.
 *   * Neither the name of the copyright holders nor the names of its contributors
 *     may be used to endorse or promote products derived from this software
 *     without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

include( "SmallTAL.php" );

class SmallTAL_Tests extends PHPUnit_Framework_TestCase
{
	static $smalltalVariables = array
	(
		"text" => "SmallTAL test",
		"boolF" => false,
		"boolT" => true,
		"arrayFull" => array
		(
			array
			(
				"name" =>"First",
				"description" => "First element description"
			),
			array
			(
				"name" =>"Middle",
				"description" => "Element in the middle"
			),
			array
			(
				"name" =>"Last",
				"description" => "Last element description"
			)
		),
		"arrayEmpty" => array( )
	);

	static $smalltalDirectories = array
	(
		"templates" => "tests/templates",
		"cache" => "tests/cache",
		"temp" => "tests/temp"
	);

	private function DoTALTest( $page )
	{
		ob_start( );
		$returnValue = SmallTAL( "tal", $page, self::$smalltalVariables, self::$smalltalDirectories );
		$output = ob_get_clean( );
		$this->assertTrue( is_array( $returnValue ) );
		$this->assertEquals( $returnValue[ 'errorCode' ], 0 );
		$this->assertCount( 1, $returnValue );
		$this->assertEquals( file_get_contents("tests/results/tal." . $page . ".html" ), $output );
	}

	public function testContent( )
	{
		$this->DoTALTest( "content" );
	}

	public function testReplace( )
	{
		$this->DoTALTest( "replace" );
	}

	public function testOmittag( )
	{
		$this->DoTALTest( "omit-tag" );
	}

	public function testAttributes( )
	{
		$this->DoTALTest( "attributes" );
	}

	public function testRepeat( )
	{
		$this->DoTALTest( "repeat" );
	}

	public function testCondition( )
	{
		$this->DoTALTest( "condition" );
	}

	public function testDefine( )
	{
		$this->DoTALTest( "define" );
	}

	public function testMetal( )
	{
		$this->DoTALTest( "metal" );
	}
}
?>
