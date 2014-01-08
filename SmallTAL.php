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

if( !defined( 'SmallTAL' ) )
{
	define( 'SmallTAL', '1.0' );
	define( 'SmallTAL_Error_None', 0 );
	define( 'SmallTAL_Error_Path', 1 );
	define( 'SmallTAL_Error_Template', 2 );
	define( 'SmallTAL_Error_Cache', 4 );
	define( 'SmallTAL_Error_TemporaryFile', 8 );
	define( 'SmallTAL_Error_Rename', 16 );
	define( 'SmallTAL_Error_NullNode', 32 );
	define( 'SmallTAL_Error_EndTagNotFound', 64 );
	define( 'SmallTAL_Error_DuplicatedMacro', 128 );

	function SmallTAL_AddError( &$returnValue, $code, $message = null )
	{
		if( !is_array( $returnValue ) )
		{
			$returnValue = array( );
		}
		if( !array_key_exists( 'errorCode', $returnValue ) )
		{
			$returnValue[ 'errorCode' ] = constant( 'SmallTAL_Error_None' );
		}
		$returnValue[ 'errorCode' ] |= $code;
		if( !is_null( $message ) )
		{
			if( !array_key_exists( $code, $returnValue ) || !is_array( $returnValue[ $code ] ) )
			{
				$returnValue[ $code ] = array( );
			}
			$returnValue[ $code ][ ] = $message;
		}
	}

	function SmallTAL( $template, $page, $variables = null, $directories = null )
	{
		$returnValue = array( 'errorCode' => constant( 'SmallTAL_Error_None' ) );
		if( is_null( $variables ) || !is_array( $variables ) )
		{
			$variables = array( );
		}
		if( array_key_exists( 'CONTEXT', $variables ) )
		{
			unset( $variables[ 'CONTEXT' ] );
		}
		if( is_null( $directories ) || !is_array( $directories ) )
		{
			$directories = array( );
		}
		$elements= array
		(
			'templates',
			'cache',
			'temp'
		);
		foreach( $elements as $element )
		{
			if( !array_key_exists( $element, $directories ) )
			{
				$directories[ $element ] = $element;
			}
			if( DIRECTORY_SEPARATOR == $directories[ $element ][ strlen( $directories[ $element ] ) - 1 ] )
			{
				$directories[ $element ] = substr( $directories[ $element ], 0, -1 );
			}
		}
		if( file_exists( $directories[ 'templates' ] . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR . $page . '.html' ) )
		{
			if( ( file_exists( $directories[ 'cache' ] ) || mkdir( $directories[ 'cache' ], 0700 ) ) && is_dir( $directories[ 'cache' ] ) && ( ( file_exists( $directories[ 'cache' ] . DIRECTORY_SEPARATOR . $template ) || mkdir( $directories[ 'cache' ] . DIRECTORY_SEPARATOR . $template, 0700 ) ) && is_dir( $directories[ 'cache' ] . DIRECTORY_SEPARATOR . $template ) ) )
			{
				if( file_exists( $directories[ 'cache' ] . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR . $page . '.php' ) )
				{
					$filesMTime = array
					(
						'templateFile' => filemtime( $directories[ 'templates' ] . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR . $page . '.html' ),
						'metalFile' => ( file_exists( $tmpVariable = $directories[ 'templates' ] . DIRECTORY_SEPARATOR . $template . '.metal.html' ) ? filemtime( $tmpVariable ) : time( ) ),
						'smallTAL' => filemtime( __FILE__ ),
						'smallTALCompiler' => filemtime( 'SmallTAL.Compiler.php' )
					);
					$tmpVariable = filemtime( $directories[ 'cache' ] . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR . $page . '.php' );
					$delete = false;
					while( !$delete && ( $fileMTime = array_pop( $filesMTime ) ) )
					{
						if( $tmpVariable < $fileMTime )
						{
							$delete = true;
						}
					}
					if( $delete )
					{
						unlink( $directories[ 'cache' ] . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR . $page . '.php' );
					}
				}
				if( !file_exists( $directories[ 'cache' ] . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR . $page . '.php' ) )
				{
					require_once( 'SmallTAL.Compiler.php' );
					SmallTAL_Compiler( $returnValue, $template, $page, $variables, $directories );
				}
				if( file_exists( $directories[ 'cache' ] . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR . $page . '.php' ) )
				{
					include( $directories[ 'cache' ] . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR . $page . '.php' );
				}
				else
				{
					SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_Cache' ), $directories[ 'cache' ] . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR . $page . '.php' );
				}
			}
			else
			{
				SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_Path' ), null );
			}
		}
		else
		{
				SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_Template' ), $directories[ 'templates' ] . DIRECTORY_SEPARATOR . $template . DIRECTORY_SEPARATOR . $page . '.html' );
		}
		return $returnValue;
	}

	function SmallTAL_NavigateTALESPath( $variable, $path, $start, &$target )
	{
		$pathPieces = explode( '/', $path );
		$pathCount = count( $pathPieces );
		$returnValue = true;
		$target = array( null, false );
		for( $i = $start; $returnValue && ( $i < $pathCount ); $i++ )
		{
			if( is_array( $variable ) && array_key_exists( $pathPieces[ $i ], $variable ) )
			{
				$variable = $variable[ $pathPieces[ $i ] ];
			}
			elseif( is_object( $variable ) )
			{
				if( method_exists( $variable, $pathPieces[ $i ] ) )
				{
					$variable = $variable->$pathPieces[ $i ]( );
				}
				elseif( property_exists( $variable, $pathPieces[ $i ] ) )
				{
					$variable = $variable->$pathPieces[ $i ];
				}
				else
				{
					$returnValue = false;
				}
			}
			else
			{
				$returnValue = false;
			}
		}
		if( $returnValue )
		{
			$target[ 0 ] = $variable;
		}
		return $returnValue;
	}

	function SmallTAL_LocalVariablesPush( &$localVariables, $type, $name, $newContent )
	{
		$returnValue = false;
		if( array_key_exists( $name, $localVariables[ $type ] ) )
		{
			$localVariables[ 'stack' ][ $type ][ $name ][] = $localVariables[ $type ][ $name ];
			$returnValue = true;
		}
		$localVariables[ $type ][ $name ] = $newContent;
		return $returnValue;
	}

	function SmallTAL_LocalVariablesPop( &$localVariables, $type, $name )
	{
		$returnValue = false;
		if( array_key_exists( $name, $localVariables[ 'stack' ][ $type ] ) && ( 0 < count( $localVariables[ 'stack' ][ $type ][ $name ] ) ) )
		{
			$localVariables[ $type ][ $name ] = array_pop( $localVariables[ 'stack' ][ $type ][ $name ] );
			$returnValue = true;
		}
		elseif( array_key_exists( $name, $localVariables[ $type ] ) )
		{
			unset( $localVariables[ $type ][ $name ] );
		}
		return $returnValue;
	}

	function SmallTAL_NumberToLetter( $number )
	{
		$returnValue = null;
		while( $number > 25 )
		{
			$character = $number % 26;
			$number = ( $number - $character ) / 26;
			$returnValue = chr( 97 + $character ) . $returnValue;
		}
		$returnValue = chr( ( null == $returnValue ? 97 : 96 ) + $number ) . $returnValue;
		return $returnValue;
	}

	function SmallTAL_ArabicToRoman( $number )
	{
		$returnValue = null;
		if( $number > 0 )
		{
			$romanNumbers = array
			(
				'M' => 1000,
				'CM' => 900,
				'D' => 500,
				'CD' => 400,
				'C' => 100,
				'XC' => 90,
				'L' => 50,
				'XL' => 40,
				'X' => 10,
				'IX' => 9,
				'V' => 5,
				'IV' => 4,
				'I' => 1,
			);
			foreach( $romanNumbers as $name => $value )
			{
				$returnValue .= str_repeat( $name, intval( $number / $value ) );
				$number %= $value;
			}
		}
		return $returnValue;
	}
}
