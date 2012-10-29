<?php if(defined('SmallTAL')){$localVariables=array('defined'=>array(),'repeat'=>array(),'stack'=>array('defined'=>array(),'repeat'=>array()),'template'=>array());?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<?php $localVariables['template'][1]=(($tmpVariable=array('metal:use-macro',false))&&!$tmpVariable[1]);if($localVariables['template'][1]){SmallTAL_LocalVariablesPush($localVariables,'defined','testname',$tmpVariable[0]);}unset($tmpVariable);?><html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"> 
<head>
	<title><?php if(($localVariables['template'][0]=array('SmallTAL - '.((SmallTAL_NavigateTALESPath($localVariables['defined'],'testname',0,$tmpVariable)||SmallTAL_NavigateTALESPath($variables,'testname',0,$tmpVariable)||($tmpVariable=array(null,false))&&!$tmpVariable[1])?$tmpVariable[0]:null).' test',false))&&!$localVariables['template'][0][1]&&!is_null($localVariables['template'][0][0])){echo(str_replace(array('&','<','>'),array('&amp','&lt','&gt'),(is_bool($localVariables['template'][0][0])?($localVariables['template'][0][0]?1:0):$localVariables['template'][0][0])));}elseif($localVariables['template'][0][1]){?>SmallTAL - generic test<?php }unset($localVariables['template'][0]);?></title>
</head>
<body>
	<p>This is a metal p</p>
</body>
</html><?php if($localVariables['template'][1]){SmallTAL_LocalVariablesPop($localVariables,'defined','testname');unset($localVariables['template'][1]);}?>

<?php }?>