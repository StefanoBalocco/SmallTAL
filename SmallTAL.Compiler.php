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

if( defined( 'SmallTAL' ) && !defined( 'SmallTAL_Compiler' ) && extension_loaded( 'pcre' ) && ( '' == ini_get( 'short_open_tag' ) ) )
{
	// Parse TALES path expressions
	function SmallTAL_Compiler_ParseTALESPathExpression( $pathExpression, $attributes, $target )
	{
		$returnValue = null;
		$paths = explode( '|', $pathExpression );
		$pathsCount = count( $paths );
		$parentheses = 0;
		$continue = true;
		for( $i = 0; $continue && ( $i < $pathsCount ); $i++ )
		{
			$path = array
			(
				'pieces' => explode( '/', $paths[ $i ] ),
				'keywordPosition' => 1
			);
			if( ( $path[ 'quantity' ] = count( $path[ 'pieces' ] ) ) > 0 )
			{
				// If the first piece isn't CONTEXT, I should check for variables.
				// But if is CONTEXTS, I should check for keywords in the array element 1 (if exists),
				// so the default value of keyword position is 1, and is adjusted if needed.
				if( 'CONTEXTS' != $path[ 'pieces' ][ 0 ] )
				{
					// First I need to check for variables, then I can check for keywords

					// The first variable type that I need to check is a defined variable
					$returnValue .= 'SmallTAL_NavigateTALESPath($localVariables[\'defined\'],\'' . trim( $paths[ $i ] ) . '\',0,' . $target . ')||';

					// Then for any variable given to the template engine
					$returnValue .= 'SmallTAL_NavigateTALESPath($variables,\'' . trim( $paths[ $i ] ) . '\',0,' . $target . ')' . ( ( $pathsCount > $i  ) ? '||' : null );

					// Is not CONTEXT, so I should check since the start
					$path[ 'keywordPosition' ]--;
				}
				if( array_key_exists( $path[ 'keywordPosition' ], $path[ 'pieces' ] ) )
				{
					switch( trim( $path[ 'pieces' ][ $path[ 'keywordPosition' ] ] ) )
					{
						case 'attrs':
						{
							if( ( 2 == ( $path[ 'quantity' ] - $path[ 'keywordPosition' ] ) ) && is_array( $attributes ) && array_key_exists( $path[ 'pieces' ][ 1 + $path[ 'keywordPosition' ] ], $attributes ) )
							{
								$returnValue .= '(' . $target . '=array(' . ( isset( $attributes[ $path[ 'pieces' ][ 1 + $path[ 'keywordPosition' ] ] ] ) ? '\'' . $attributes[ $path[ 'pieces' ][ 1 + $path[ 'keywordPosition' ] ] ] . '\'' : 'null' ) . ',false))';
								// If attributes is in the path, and can be checked, I suppose that nothing else should be checked.
								$continue = false;
							}
							break;
						}
						case 'nothing':
						{
							$returnValue .= '(' . $target . '=array(null,false))';
							// If "nothing" is in the path I suppose that nothing else should be checked.
							$continue = false;
							break;
						}
						case 'default':
						{
							$returnValue .= '(' . $target . '=array(null,true))';
							// If default is in the path I suppose that nothing else should be checked.
							$continue = false;
							break;
						}
						case 'options':
						{
							if( $path[ 'quantity' ] > $path[ 'keywordPosition' ] )
							{
								// Actually is a mirror of a normal path, because nothing else can be passed to
								// the template engine.
								$returnValue .= 'SmallTAL_NavigateTALESPath($variables,\'' . $paths[ $i ] . '\',' . $path[ 'keywordPosition' ] + 1 . ',' . $target . ')' . ( ( $pathsCount > $i ) ? '||' : null );
							}
							break;
						}
						case 'repeat':
						{
							if( $path[ 'quantity' ] > $path[ 'keywordPosition' ] )
							{
								$parentheses++;
								$returnValue .= '(';
								$variableName = '$localVariables[\'repeat\']';
								// I avoid the piece in the switch.
								for( $j = $path[ 'keywordPosition' ] + 1; $j < $path[ 'quantity' ]; $j++ )
								{
									$returnValue .= 'array_key_exists(\'' . $path[ 'pieces' ][ $i ] . '\',' . $variableName . ')' . ( $path[ 'quantity' ] > ( $i + 1 ) ? '&&is_array(' . $variableName . '[\'' . $path[ 'pieces' ][ $i ] . '\'])&&' : '?array(' . $variableName . '[\'' . $path[ 'pieces' ][ $i ] . '\'],false):' );
									$variableName .= '[\'' . $path[ 'pieces' ][ $i ] . '\']';
								}
							}
						}
					}
				}
			}
		}
		if( $continue )
		{
			$returnValue .= '('. $target . '=array(null,false))';
		}
		for( $i = 0; $i < $parentheses; $i++ )
		{
			$returnValue .= ')';
		}
		return $returnValue;
	}

	// Parse TALES expressions
	function SmallTAL_Compiler_ParseTALES( $expression, $attributes, $target, $bool = false, $defaultIsFalse = true, $reverseBool = false )
	{
		$returnValue = null;
		// I need to remove any ' in the variable name, to avoid php injection
		// I could simply backslash it, but I prefer this way.
		$expression = str_replace( '\'', '&#039', $expression );
		$type = 'path';
		$value = $expression;
		if( false !== strpos( $expression, ':' ) )
		{
			list( $type, $value ) = explode( ':', $expression, 2 );
		}
		if( 'string' == $type )
		{
			preg_match_all( '/\$\{?([\w\/]+)\}?/', $value, $variables, PREG_OFFSET_CAPTURE );
			if( isset( $variables ) && ( false !== $variables ) && ( 2 == count( $variables ) ) && ( 0 < count( $variables[ 0 ] ) ) )
			{
				for( $i = count( $variables[ 0 ] ) - 1; 0 <= $i; $i-- )
				{
					$value = substr_replace( $value, '\'.((' . SmallTAL_Compiler_ParseTALESPathExpression( $variables[ 1 ][ $i ][ 0 ], $attributes, '$tmpVariable' ) . '&&!$tmpVariable[1])?$tmpVariable[0]:null).\'', $variables[ 0 ][ $i ][ 1 ], strlen( $variables[ 0 ][ $i ][ 0 ] ) );
				}
			}
			$returnValue = '(' . $target . '=array(\'' . $value . '\',false))';
		}
		else
		{
			// Path is the default
			$returnValue = SmallTAL_Compiler_ParseTALESPathExpression( $value, $attributes, $target );
			if( 'not' == $type )
			{
				$bool = true;
				$reverseBool = true;
			}
		}
		// "Not" is evalutated here.
		return( ( $bool ? '(' : null ) . $returnValue . ( $bool ? ')&&' . ( $reverseBool ? '!' : null ) . sprintf( '(' . ( $defaultIsFalse ? '!' : null ) . '%1$s[1]' . ( $defaultIsFalse ? '&&' : '||(' ) . '!is_null(%1$s[0])&&((is_bool(%1$s[0])&&%1$s[0])||(is_string(%1$s[0])&&(0<strlen(%1$s[0]))||(is_numeric(%1$s[0])&&(0<%1$s[0]))))' . ( $defaultIsFalse ? null : ')' ) . ')', $target ) : null ) );
	}

	function SmallTAL_Compiler_FindEndTag( $content, $tag, $start, &$closeLength = 0 )
	{
		$returnValue = $start;
		$closeLength = 0;
		$closed = false;
		$count = 1;
		$match = array( );
		$position = $start;
		while( !$closed )
		{
			// Searching for the closing tag
			if( 1 == preg_match( sprintf( '/<(?:(\/)%1$s|%1$s(?:\s+[^<>]*)?)>/i', $tag ), $content, $match, PREG_OFFSET_CAPTURE, $position ) )
			{
				if( array_key_exists( 1, $match ) )
				{
					$count--;
				}
				else
				{
					$count++;
				}
				$position = $match[ 0 ][ 1 ] + strlen( $match[ 0 ][ 0 ] );
				if( $count == 0 )
				{
					$closed = true;
					$returnValue = $position;
					$closeLength = strlen( $match[ 0 ][ 0 ] );
				}
			}
			else
			{
				//The tag isn't closed, but I consider it as self-closed
				$closed = true;
			}
		}
		return $returnValue;
	}

	function SmallTAL_Compiler_ParseMETAL( &$returnValue, &$compilerData, &$content, $removeMacros = false )
	{
		// Searching for the metal:define-macro attribute
		if( preg_match_all( sprintf( $compilerData[ 'regexp' ][ 'tagWithAttribute' ], 'metal:define-macro' ), $content, $results, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) )
		{
			for( $i = count( $results ) - 1; $i > -1; $i-- )
			{
				$position = $results[ $i ][ 0 ][ 1 ] + strlen( $results[ $i ][ 0 ][ 0 ] );
				if( !array_key_exists( 6, $results[ $i ] ) )
				{
					$position = SmallTAL_Compiler_FindEndTag( $content, $results[ $i ][ 1 ][ 0 ], $position );
				}
				$macroName = $results[ $i ][ 4 ][ 0 ];
				// Already exists, overriding but alert about it.
				if( array_key_exists( $macroName, $compilerData[ 'metal' ] ) )
				{
					SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_DuplicatedMacro' ), $macroName );
				}
				// Removing the metal:define-macro tag and adding the macro
				$compilerData[ 'metal' ][ $macroName ] = preg_replace( sprintf( $compilerData[ 'regexp' ][ 'tagWithAttribute' ], 'metal:define-macro' ), '<${1}${2}${5}${6}>', substr( $content, $results[ $i ][ 0 ][ 1 ], $position - $results[ $i ][ 0 ][ 1 ] ), -1, $count );
				if( $removeMacros && ( $closed ) )
				{
					$content = substr_replace( $content, '', $results[ $i ][ 0 ][ 1 ], $position - $results[ $i ][ 0 ][ 1 ] );
				}
			}
		}
		return $returnValue;
	}

	function SmallTAL_Compiler( &$returnValue, $template, $page, $variables = null, $directories = null )
	{
		$compilerData = array
		(
			'metal' => array
			(
			),
			'keywords' => array
			(
				'define',
				'condition',
				'repeat',
				'replace',
				'content',
				'omit-tag',
				'attributes'
			),
			'regexp' => array
			(
				'tag' => '/<((?:\w+:)?\w+)(\s+[^<>]+?)?\s*(\/)?>/i',
				'tagWithTal' => '/<((?:\w+:)?\w+)(\s+[^<>]+?)??\s+tal:(?:define|condition|repeat|replace|content|attributes|omit-tag)=([\'"])(.*?)\3(\s+[^<>]+?)??\s*(\/)?>/i',
				'tagWithAttribute' => '/<((?:\w+:)?\w+)(\s+[^<>]+?)??\s+%s=([\'"])(.*?)\3(\s+[^<>]+?)??\s*(\/)?>/i',
				'xmlns' => '/<html(\s+[^<>]+?)??\s+xmlns:%s=([\'"])(.*?)\2(\s+[^<>]+?)??\s*(\/)?>/i',
				'xmlDeclaration' => '/^(?:\s*<(\?xml.*?\?>))/',
				'tagAttributes' => '/(?<=\s)((?:[\w-]+\:)?[\w-]+)=(?:([\'"])(.*?)\2|([^>\s\'"]+))/'
			)
		);
		if( file_exists( $directories[ 'templates' ] ) && is_dir( $directories[ 'templates' ] ) && file_exists( $directories[ 'templates' ] . '/' . $template ) && is_dir( $directories[ 'templates' ] . '/' . $template ) && ( file_exists( $directories[ 'temp' ] ) || mkdir( $directories[ 'temp' ], 0700 ) ) && is_dir( $directories[ 'temp' ] ) )
		{
			if( file_exists( $directories[ 'templates' ] . '/' . $template . '.metal.html' ) && ( false !== ( $metalContent = file_get_contents( $directories[ 'templates' ] . '/' . $template . '.metal.html' ) ) ) )
			{
				SmallTAL_Compiler_ParseMETAL( $returnValue, $compilerData, $metalContent, false );
			}
			if( file_exists( $directories[ 'templates' ] . '/' . $template . '/' . $page . '.html' ) && ( false !== ( $content = file_get_contents( $directories[ 'templates' ] . '/' . $template . '/' . $page . '.html' ) ) ) )
			{
				SmallTAL_Compiler_ParseMETAL( $returnValue, $compilerData, $content, true );
				$results = array( );
				// Searching for the metal:use-macro attribute
				if( preg_match_all( sprintf( $compilerData[ 'regexp' ][ 'tagWithAttribute' ], 'metal:use-macro' ), $content, $results, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) )
				{
					for( $i = count( $results ) - 1; $i > -1; $i-- )
					{
						if( array_key_exists( $results[ $i ][ 4 ][ 0 ], $compilerData[ 'metal' ] ) )
						{
							$position = $results[ $i ][ 0 ][ 1 ] + strlen( $results[ $i ][ 0 ][ 0 ] );
							if( !array_key_exists( 6, $results[ $i ] ) )
							{
								$position = SmallTAL_Compiler_FindEndTag( $content, $results[ $i ][ 1 ][ 0 ], $position );
							}
							$tmpVariable = substr( $content, $results[ $i ][ 0 ][ 1 ], $position - $results[ $i ][ 0 ][ 1 ] );
							$macro = $compilerData[ 'metal' ][ $results[ $i ][ 4 ][ 0 ] ];
							// Searching for slots
							if( !array_key_exists( 6, $results[ $i ] ) )
							{
								$resultsSlots = array( );
								$slots = array( );
								if( preg_match_all( sprintf( $compilerData[ 'regexp' ][ 'tagWithAttribute' ], 'metal:fill-slot' ), $tmpVariable, $resultsSlots, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) )
								{
									for( $j = count( $resultsSlots ) - 1; $j > -1; $j-- )
									{
										if( array_key_exists( $resultsSlots[ $j ][ 4 ][ 0 ], $slots ) )
										{
											SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_DuplicatedSlot' ), $slotName );
										}
										$slots[ $resultsSlots[ $j ][ 4 ][ 0 ] ] = preg_replace( sprintf( $compilerData[ 'regexp' ][ 'tagWithAttribute' ], 'metal:fill-slot' ), '<${1}${2}${5}${6}>', substr( $tmpVariable, $resultsSlots[ $j ][ 0 ][ 1 ], SmallTAL_Compiler_FindEndTag( $tmpVariable, $resultsSlots[ $j ][ 1 ][ 0 ], $resultsSlots[ $j ][ 0 ][ 1 ] + strlen( $resultsSlots[ $j ][ 0 ][ 0 ] ) ) - $resultsSlots[ $j ][ 0 ][ 1 ] ), -1, $count );
									}
								}
								if( 0 < count( $slots ) )
								{
									if( preg_match_all( sprintf( $compilerData[ 'regexp' ][ 'tagWithAttribute' ], 'metal:define-slot' ), $macro, $resultsSlots, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) )
									{
										for( $j = count( $resultsSlots ) - 1; $j > -1; $j-- )
										{
											if( array_key_exists( $resultsSlots[ $j ][ 4 ][ 0 ], $slots ) )
											{
												$macro = str_replace( substr( $macro, $resultsSlots[ $j ][ 0 ][ 1 ], SmallTAL_Compiler_FindEndTag( $compilerData[ 'metal' ][ $macroName ], $resultsSlots[ $j ][ 1 ][ 0 ], $resultsSlots[ $j ][ 0 ][ 1 ] + strlen( $resultsSlots[ $j ][ 0 ][ 0 ] ) ) - $resultsSlots[ $j ][ 0 ][ 1 ] ), $resultsSlots[ $j ][ 4 ][ 0 ], $macro );
											}
											else
											{
												SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_UnknownSlot' ), $results[ $j ][ 4 ][ 0 ] );
											}
										}
									}
								}
							}
							$content = str_replace( $tmpVariable, $macro, $content );
						}
						else
						{
							SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_UnknownMacro' ), $results[ $i ][ 4 ][ 0 ] );
						}
					}
				}
				$output = '<?php if(defined(\'SmallTAL\')){$localVariables=array(\'defined\'=>array(),\'repeat\'=>array(),\'stack\'=>array(\'defined\'=>array(),\'repeat\'=>array()),\'template\'=>array());?>';
				$localVariablesIndex = 0;
				$count = 0;

				//TODO: Variables optimization round
				if( null != $content )
				{
					if( ( $tagsCount = preg_match_all( $compilerData[ 'regexp' ][ 'tagWithTal' ], $content, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) )
					{
						$localVariablesIndex = 0;
						for( $i = $tagsCount - 1; $i > -1; $i-- )
						{
							$tagAttributes = array( );
							$attributes = array( );
							if( ( $attributesCount = preg_match_all( $compilerData[ 'regexp' ][ 'tagAttributes' ], $tags[ $i ][ 0 ][ 0 ], $attributes, PREG_SET_ORDER ) ) )
							{
								for( $j = 0; $j < $attributesCount; $j++ )
								{
									$tagAttributes[ $attributes[ $j ][ 1 ] ] = ( ( empty( $attributes[ $j ][ 2 ] ) && array_key_exists( 4, $attributes[ $j ] ) ) ? $attributes[ $j ][ 4 ] : $attributes[ $j ][ 3 ] );
								}
							}
							unset( $attributes );
							$changes = array
							(
								'attributes' => array( ),
								'outside' => array
								(
									'pre' => null,
									'post' => null
								),
								'inside' => array
								(
									'pre' => null,
									'post' => null
								)
							);
							foreach( $compilerData[ 'keywords' ] as $keyword )
							{
								if( array_key_exists( 'tal:' . $keyword, $tagAttributes ) )
								{
									switch( $keyword )
									{
										case 'define':
										{
											$tmpVariable = array( );
											$tmpVariable[ 'count' ] = preg_match_all( '/[\s]*(?:(?:(local|world)[\s]+)?(.+?)[\s]+(.+?)[\s]*(?:(?<!;);(?!;)|$))+?/', $tagAttributes[ 'tal:define' ], $tmpVariable[ 'elements' ], PREG_SET_ORDER );
											for( $j = 0; $j < $tmpVariable[ 'count' ]; $j++ )
											{
												$tmpVariable[ 'name' ] = str_replace( '\'', '&#039', $tmpVariable[ 'elements' ][ $j ][ 2 ] );
												$changes[ 'outside' ][ 'pre' ] .= '$localVariables[\'template\'][' . $localVariablesIndex . ']=(' . SmallTAL_Compiler_ParseTALES( $tmpVariable[ 'elements' ][ $j ][ 3 ], $tagAttributes, '$tmpVariable' ) . '&&!$tmpVariable[1]);if($localVariables[\'template\'][' . $localVariablesIndex++ . ']){SmallTAL_LocalVariablesPush($localVariables,\'defined\',\'' . $tmpVariable[ 'name' ] . '\',$tmpVariable[0]);}unset($tmpVariable);';
												if( 'world' != $tmpVariable[ 'elements' ][ $j ][ 1 ] )
												{
													$changes[ 'outside' ][ 'post' ] = 'if($localVariables[\'template\'][' . ( $localVariablesIndex - 1 ) . ']){SmallTAL_LocalVariablesPop($localVariables,\'defined\',\'' . $tmpVariable[ 'name' ] . '\');unset($localVariables[\'template\'][' . ( $localVariablesIndex - 1 ) . ']);}' . $changes[ 'outside' ][ 'post' ];
												}
											}
											unset( $tmpVariable );
											break;
										}
										case 'condition':
										{
											$changes[ 'outside' ][ 'pre' ] .= 'if(' . SmallTAL_Compiler_ParseTALES( $tagAttributes[ 'tal:condition' ], $tagAttributes, '$localVariables[\'template\'][' . $localVariablesIndex . ']', true, true ) . '){';
											$changes[ 'outside' ][ 'post' ] = '}unset($localVariables[\'template\'][' . $localVariablesIndex++ . ']);' . $changes[ 'outside' ][ 'post' ];
											break;
										}
										case 'repeat':
										{
											$tmpVariable[ 'elements' ] = explode( " ", $tagAttributes[ 'tal:repeat' ], 2 );
											$tmpVariable[ 'name' ] = str_replace( '\'', '&#039', $tmpVariable[ 'elements' ][ 0 ] );
											$changes[ 'outside' ][ 'pre' ] .= sprintf( 'if((%1$s)&&($localVariables[\'template\'][%2$d][1]||is_array($localVariables[\'template\'][%2$d][0]))){$localVariables[\'template\'][%3$d]=array(false,false);if(!$localVariables[\'template\'][%2$d][1]){($localVariables[\'template\'][%3$d][0]=true)&&SmallTAL_LocalVariablesPush($localVariables,\'repeat\',\'%4$s\',null);($localVariables[\'template\'][%3$d][1]=true)&&SmallTAL_LocalVariablesPush($localVariables,\'defined\',\'%4$s\',null);$localVariables[\'repeat\'][\'%4$s\'][\'index\']=-1;$localVariables[\'repeat\'][\'%4$s\'][\'length\']=count($localVariables[\'template\'][%2$d][0]);}do{if(!$localVariables[\'template\'][%2$d][1]){$localVariables[\'defined\'][\'%4$s\']=array_shift($localVariables[\'template\'][%2$d][0]);$localVariables[\'repeat\'][\'%4$s\'][\'index\']++;$localVariables[\'repeat\'][\'%4$s\'][\'number\']=$localVariables[\'repeat\'][\'%4$s\'][\'index\']+1;$localVariables[\'repeat\'][\'%4$s\'][\'even\']=($localVariables[\'repeat\'][\'%4$s\'][\'number\']%%2?true:false);$localVariables[\'repeat\'][\'%4$s\'][\'odd\']=!$localVariables[\'repeat\'][\'%4$s\'][\'even\'];$localVariables[\'repeat\'][\'%4$s\'][\'start\']=($localVariables[\'repeat\'][\'%4$s\'][\'index\']?false:true);$localVariables[\'repeat\'][\'%4$s\'][\'end\']=(($localVariables[\'repeat\'][\'%4$s\'][\'number\']==$localVariables[\'repeat\'][\'%4$s\'][\'length\'])?true:false);$localVariables[\'repeat\'][\'%4$s\'][\'letter\']=SmallTAL_NumberToLetter($localVariables[\'repeat\'][\'%4$s\'][\'index\']);$localVariables[\'repeat\'][\'%4$s\'][\'Letter\']=strtoupper($localVariables[\'repeat\'][\'%4$s\'][\'letter\']);}', SmallTAL_Compiler_ParseTALES( $tmpVariable[ 'elements' ][ 1 ], $tagAttributes, '$localVariables[\'template\'][' . $localVariablesIndex . ']' ), $localVariablesIndex++, $localVariablesIndex, $tmpVariable[ 'name' ] );
											$changes[ 'outside' ][ 'post' ] = sprintf( '}while(!$localVariables[\'template\'][%1$d][1]&&!empty($localVariables[\'template\'][%1$d][0]));$localVariables[\'template\'][%2$d][0]&&SmallTAL_LocalVariablesPop($localVariables,\'repeat\',\'%3$s\');$localVariables[\'template\'][%2$d][1]&&SmallTAL_LocalVariablesPop($localVariables,\'defined\',\'%3$s\');unset($localVariables[\'template\'][%1$d],$localVariables[\'template\'][%2$d]);}', $localVariablesIndex - 1, $localVariablesIndex++, $tmpVariable[ 'name' ] ) . $changes[ 'outside' ][ 'post' ];
											break;
										}
										case 'replace':
										case 'content':
										{
											if( ( 'content' == $keyword ) || ( ( 'replace' == $keyword ) && !array_key_exists( 'tal:content', $tagAttributes ) ) )
											{
												$tmpVariable = array( );
												if( ( 'structure ' == substr( $tagAttributes[ 'tal:' . $keyword ], 0, 10 ) ) || ( 'text ' == substr( $tagAttributes[ 'tal:' . $keyword ], 0, 5 ) ) )
												{
													$tmpVariable[ 'parameters' ] = explode( " ", ( ( 'content' == $keyword ) ? $tagAttributes[ 'tal:content' ] : $tagAttributes[ 'tal:replace' ] ), 2 );
												}
												else
												{
													$tmpVariable[ 'parameters' ] = array( "text", $tagAttributes[ 'tal:' . $keyword ] );
												}
												$tmpVariable[ 'pre' ] =  'if(' . SmallTAL_Compiler_ParseTALES( $tmpVariable[ 'parameters' ][ 1 ], $tagAttributes, '$localVariables[\'template\'][' . $localVariablesIndex . ']' ) . '&&!$localVariables[\'template\'][' . $localVariablesIndex . '][1]&&!is_null($localVariables[\'template\'][' . $localVariablesIndex . '][0])){echo(' . ( 'text' == $tmpVariable[ 'parameters' ][0] ? 'str_replace(array(\'&\',\'<\',\'>\'),array(\'&amp\',\'&lt\',\'&gt\'),' : null ) . '(is_bool($localVariables[\'template\'][' . $localVariablesIndex . '][0])?($localVariables[\'template\'][' . $localVariablesIndex . '][0]?1:0):$localVariables[\'template\'][' . $localVariablesIndex . '][0])'. ( 'text' == $tmpVariable[ 'parameters' ][0] ? ')' : null ). ');}elseif($localVariables[\'template\'][' . $localVariablesIndex . '][1]){';
												$tmpVariable[ 'post' ] = '}unset($localVariables[\'template\'][' . $localVariablesIndex++ . ']);';
												if( 'content' == $keyword )
												{
													$changes[ 'inside' ][ 'pre' ] = $tmpVariable[ 'pre' ] . $changes[ 'inside' ][ 'pre' ];
													$changes[ 'inside' ][ 'post' ] .= $tmpVariable[ 'post' ];
												}
												else
												{
													$changes[ 'outside' ][ 'pre' ] .= $tmpVariable[ 'pre' ];
													$changes[ 'outside' ][ 'post' ] = $tmpVariable[ 'post' ] . $changes[ 'outside' ][ 'post' ];
												}
												unset( $tmpVariable );
											}
											break;
										}
										case 'attributes':
										{
											if( !array_key_exists( 'tal:content', $tagAttributes ) )
											{
												$tmpVariable = array( );
												$tmpVariable[ 'count' ] = preg_match_all( '/(?:[\s]*(.+?)[\s]+(.+?)[\s]*(?:(?<!;);(?!;)|$))+?/', $tagAttributes[ 'tal:attributes' ], $tmpVariable[ 'elements' ], PREG_SET_ORDER );
												for( $j = 0; $j < $tmpVariable[ 'count' ]; $j++ )
												{
													$changes[ 'attributes' ][ $tmpVariable[ 'elements' ][ $j ][ 1 ] ] = $tmpVariable[ 'elements' ][ $j ][ 2 ];
												}
											}
											break;
										}
										case 'omit-tag':
										{
											$changes[ 'outside' ][ 'pre' ] .= '$localVariables[\'template\'][' . $localVariablesIndex . ']=!(' . SmallTAL_Compiler_ParseTALES( $tagAttributes[ 'tal:omit-tag' ], $tagAttributes, '$tmpVariable', true ) . ');unset($tmpVariable);if($localVariables[\'template\'][' . $localVariablesIndex . ']){';
											// If isn't selfclosed OR if I already need to put some code between starting and closing tag, I put some data "inside"
											// TODO: a regexp to remove ? >< ?php and {}
											if( !array_key_exists( 6, $tags[ $i ] ) || isset( $changes[ 'inside' ][ 'pre' ] ) || isset( $changes[ 'inside' ][ 'post' ] ) || 0 < count( $changes[ 'attributes' ] ) )
											{
												$changes[ 'inside' ][ 'pre' ] = '}' . $changes[ 'inside' ][ 'pre' ];
												$changes[ 'inside' ][ 'post' ] .= 'if($localVariables[\'template\'][' . $localVariablesIndex . ']){';
											}
											$changes[ 'outside' ][ 'post' ] = '}unset($localVariables[\'template\'][' . $localVariablesIndex++ . ']);' . $changes[ 'outside' ][ 'post' ];
											break;
										}
									}
								}
							}
							if( 0 < count( $changes[ 'attributes' ] ) || isset( $changes[ 'outside' ][ 'pre'] ) || isset( $changes[ 'outside' ][ 'post'] ) || isset( $changes[ 'inside' ][ 'pre'] ) || isset( $changes[ 'inside' ][ 'post'] ) )
							{
								$tmpVariable = array( );
								$tmpVariable[ 'startTag' ] = $tags[ $i ][ 0 ][ 0 ];
								$tmpVariable[ 'attributes' ] = null;
								foreach ( $changes[ 'attributes' ] as $name => $value )
								{
									$tmpVariable[ 'attributes' ] .= '<?php if(' . SmallTAL_Compiler_ParseTALES( $value, $tagAttributes, '$tmpVariable' ) . ( !array_key_exists( $name, $tagAttributes ) ? '&&!$tmpVariable[1]' : null ) . '){?> ' . $name . '="<?php echo('. ( array_key_exists( $name, $tagAttributes ) ? '$tmpVariable[1]?\'' . str_replace( '\'', '&#039', $tagAttributes[ $name ] ) . '\':' : null ) . '(is_bool($tmpVariable[0])?($tmpVariable[0]?1:0):$tmpVariable[0]));?>"<?php }unset($tmpVariable);?>';
								}
								foreach( $tagAttributes as $name => $value )
								{
									if( array_key_exists( $name, $changes[ 'attributes' ] ) || ( 'xmlns:tal' == $name ) || ( 'tal:' == substr( $name, 0, 4 ) ) )
									{
										$tmpVariable[ 'startTag' ] = preg_replace( sprintf( $compilerData[ 'regexp' ][ 'tagWithAttribute' ], $name ), '<${1}${2}${5}${6}>', $tmpVariable[ 'startTag' ] );
									}
								}
								if( $tags[ $i ][ 0 ][ 0 ] != $tmpVariable[ 'startTag' ] )
								{
									$content = substr_replace( $content, $tmpVariable[ 'startTag' ], $tags[ $i ][ 0 ][ 1 ], strlen( $tags[ $i ][ 0 ][ 0 ] ) );
								}
								$tmpVariable[ 'startLength' ] = strlen( $tmpVariable[ 'startTag' ] );
								$tmpVariable[ 'endPosition' ] = $tags[ $i ][ 0 ][ 1 ] + $tmpVariable[ 'startLength' ];
								$tmpVariable[ 'endLength' ] = 0;
								$tmpVariable[ 'startTagSelfClosed' ] = array_key_exists( 6, $tags[ $i ] );
								$tmpVariable[ 'startTag' ] = substr( $tmpVariable[ 'startTag' ], 0, -( $tmpVariable[ 'startTagSelfClosed' ] ? 2 : 1 ) );
								if( $tmpVariable[ 'startTagSelfClosed' ] && ( isset( $changes[ 'inside' ][ 'pre'] ) || isset( $changes[ 'inside' ][ 'post'] ) ) )
								{
									$content = substr_replace( $content, $tmpVariable[ 'startTag' ] . '></' . $tags[ $i ][ 1 ][ 0 ] . '>', $tags[ $i ][ 0 ][ 1 ], $tmpVariable[ 'startLength' ] );
									$tmpVariable[ 'startTagSelfClosed' ] = false;
									$tmpVariable[ 'endPosition' ] += strlen( $tags[ $i ][ 1 ][ 0 ] ) + 2;
									$tmpVariable[ 'endLength' ] = strlen( $tags[ $i ][ 1 ][ 0 ] ) + 3;
									$tmpVariable[ 'startLength' ] -= 1;
								}
								elseif( !$tmpVariable[ 'startTagSelfClosed' ] )
								{
									$tmpVariable[ 'endPosition' ] = SmallTAL_Compiler_FindEndTag( $content, $tags[ $i ][ 1 ][ 0 ], $tmpVariable[ 'endPosition' ], $tmpVariable[ 'endLength' ] );
								}
								// If is an empty node and we need to write inside it, we need an optimization round
								if( ( ( $tmpVariable[ 'endPosition' ] - $tmpVariable[ 'endLength' ] ) == ( $tags[ $i ][ 0 ][ 1 ] + strlen( $tmpVariable[ 'startTag' ] ) + ( $tmpVariable[ 'startTagSelfClosed' ] ? 2 : 1 ) ) ) && isset( $changes[ 'inside' ][ 'pre' ] ) && isset( $changes[ 'inside' ][ 'post' ] ) )
								{
									$changes[ 'inside' ][ 'pre' ] .= $changes[ 'inside' ][ 'post' ];
									$changes[ 'inside' ][ 'post' ] = null;
									$changes[ 'inside' ][ 'pre' ] = str_replace( 'else{}', '', $changes[ 'inside' ][ 'pre' ] );
								}
								if( !$tmpVariable[ 'startTagSelfClosed' ] || isset( $changes[ 'inside' ][ 'post' ] ) || isset( $changes[ 'outside' ][ 'post' ] ) )
								{
									$content = substr_replace( $content, ( isset( $changes[ 'inside' ][ 'post' ] ) ? '<?php ' . $changes[ 'inside' ][ 'post' ] . '?>' : null ) . substr( $content, $tmpVariable[ 'endPosition' ] - $tmpVariable[ 'endLength' ], $tmpVariable[ 'endLength' ] ) . ( isset( $changes[ 'outside' ][ 'post' ] ) ? '<?php ' . $changes[ 'outside' ][ 'post' ] . '?>' : null ), $tmpVariable[ 'endPosition' ] - $tmpVariable[ 'endLength' ], $tmpVariable[ 'endLength' ] );
								}
								if( isset( $tmpVariable[ 'attributes' ] ) || isset( $changes[ 'outside' ][ 'pre' ] ) || isset( $changes[ 'inside' ][ 'pre' ] ) )
								{
									$content = substr_replace( $content, ( isset( $changes[ 'outside' ][ 'pre' ] ) ? '<?php ' . $changes[ 'outside' ][ 'pre' ] . '?>' : null ) . $tmpVariable[ 'startTag' ] . $tmpVariable[ 'attributes' ] . ( $tmpVariable[ 'startTagSelfClosed' ] ? ' /' : null ) . '>' . ( isset( $changes[ 'inside' ][ 'pre' ] ) ? '<?php ' . $changes[ 'inside' ][ 'pre' ] . '?>' : null ), $tags[ $i ][ 0 ][ 1 ], $tmpVariable[ 'startLength' ] );
								}
								unset( $tmpVariable );
							}
						}
					}
				}
				$content = preg_replace( array( sprintf( $compilerData[ 'regexp' ][ 'xmlns' ], 'tal' ), sprintf( $compilerData[ 'regexp' ][ 'xmlns' ], 'metal' ) ), '<html${1}${4}${5}>', $content );
				$output .= str_replace( "?>\n", "?>\n\n", str_replace( "\r", '', $content ) ) . '<?php }?>';

				$file = null;
				if( ( false !== ( $temporaryFilename = tempnam( $directories[ 'temp' ], 'TAL' ) ) ) && ( false !== ( $file = fopen( $temporaryFilename, 'wb' ) ) ) )
				{
					fwrite( $file, $output );
					fclose( $file );
					if( ( file_exists( $directories[ 'cache' ] ) || mkdir( $directories[ 'cache' ], 0700 ) ) && is_dir( $directories[ 'cache' ] ) && ( file_exists( $directories[ 'cache' ] . '/' . $template ) || mkdir( $directories[ 'cache' ] . '/' . $template, 0700 ) ) && is_dir( $directories[ 'cache' ] . '/' . $template ) )
					{
						if( !rename( $temporaryFilename, $directories[ 'cache' ] . '/' . $template . '/' . $page . '.php' ) )
						{
							SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_Rename' ), $temporaryFilename );
						}
					}
					else
					{
						SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_Cache' ), null );
					}
				}
				else
				{
					SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_TemporaryFile' ), null );
				}
			}
			else
			{
				SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_Template' ), null );
			}
		}
		else
		{
			SmallTAL_AddError( $returnValue, constant( 'SmallTAL_Error_Path' ), null );
		}
	}

	define( 'SmallTAL_Compiler', '1.0' );
}

if( false )
{
	class SmallTAL_Compiler
	{
		private $talData = array
		(
			'repeat' => array
			(
				'inside' => array
				(
					'pre' => 'if(%1$s&&!$localVariables[\'template\'][%2$d][1]&&is_array($localVariables[\'template\'][%2$d][0])){$localVariables[\'template\'][%3$d]=array(SmallTAL_LocalVariablesPush($localVariables,\'repeat\',\'%4$s\',null),SmallTAL_LocalVariablesPush($localVariables,\'defined\',\'%4$s\',null));$localVariables[\'repeat\'][\'%4$s\'][\'index\']=-1;$localVariables[\'repeat\'][\'%4$s\'][\'length\']=count($localVariables[\'template\'][%2$d][0]);foreach($localVariables[\'template\'][%2$d][0] as $localVariables[\'defined\'][\'%4$s\']){$localVariables[\'repeat\'][\'%4$s\'][\'index\']++;$localVariables[\'repeat\'][\'%4$s\'][\'number\']=$localVariables[\'repeat\'][\'%4$s\'][\'index\']+1;$localVariables[\'repeat\'][\'%4$s\'][\'odd\']=($localVariables[\'repeat\'][\'%4$s\'][\'number\']%2?true:false);$localVariables[\'repeat\'][\'%4$s\'][\'even\']=!$localVariables[\'repeat\'][\'%4$s\'][\'odd\'];$localVariables[\'repeat\'][\'%4$s\'][\'start\']=($localVariables[\'repeat\'][\'%4$s\'][\'index\']?false:true);$localVariables[\'repeat\'][\'%4$s\'][\'end\']=($localVariables[\'repeat\'][\'%4$s\'][\'number\']==$localVariables[\'repeat\'][\'%4$s\'][\'length\']?true:false);$localVariables[\'repeat\'][\'%4$s\'][\'letter\']=SmallTAL_NumberToLetter($localVariables[\'repeat\'][\'%4$s\'][\'index\']);$localVariables[\'repeat\'][\'%4$s\'][\'Letter\']=strtoupper($localVariables[\'repeat\'][\'%4$s\'][\'letter\']);',
					'post' => '}if($localVariables[\'template\'][%1$d][0]){SmallTAL_LocalVariablesPop($localVariables,\'repeat\',\'%2$s\');}if($localVariables[\'template\'][%1$d][1]){SmallTAL_LocalVariablesPop($localVariables,\'define\',\'%2$s\');}}else{'
				)
			)
		);
	}
}
?>
