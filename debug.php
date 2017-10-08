<?php
// +----------------------------------------------------------------------+
// | 调试助手.开发过程中使用。不要出现在上线产品中.                           |                                                    |
// +----------------------------------------------------------------------+
// | Copyright (c) 2017 深圳有声有色网络科技有限公司                        |
// +----------------------------------------------------------------------+
// | 作者: daqi <768287201@qq.com>                                        |
// +----------------------------------------------------------------------+
ini_set('display_errors','On');
error_reporting(E_ALL);

$html_begin = <<<EOT
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml">
		<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>代码调试|函数调用追踪|查看对象</title>
		</head>
		<body>
			<div><pre style='background-color:#f60'>
EOT;

$html_end = <<<EOD
	</pre></div></body></html>
EOD;


/**
 * @param $obj
 * @param int $level
 * @param string $proName
 * @param array $exludes
 * @return array
 * 用途: 获取某个对象的所有属性(1-3级)，包括私有属性，暂时只支持3级以下
 * 用法:
 *      g(对象，1，[属性1，属性2，属性3])
 *      g(对象，1，'all',['属性1'，‘属性2']) 排除属性1和属性2
 */
function g($obj,$level=1,$proName='all',$exludes=[]){
    return _getProperties($obj,$level,$proName,$exludes);
}


/**
 * @param $var
 * @param bool $append
 * @param string $filename
 * 用途: 以页面的形式打印内容，调试用
 */
function p2f($var, $append = false, $filename = "1.html")
{
    global $html_begin,$html_end;
    ob_start();
    echo $html_begin;
    //todo:静态属性
    print_r($var, false);
    echo $html_end;
    $info = ob_get_contents();
    ob_end_clean();
    $info = _clean($info);
    _saveFile($info,$filename,$append);
}

/**
 * @param bool $append
 * @param string $path
 * 用途： 打印函数的调用回溯信息
 */
function d2f($append=false,$filename='2.html'){
    global $html_begin,$html_end;
    ob_start();
    echo $html_begin;
    $debug_arr =debug_backtrace();
    foreach($debug_arr as $key=>&$val){
        foreach ($val as $key1=>&$val1){
            if($key1=='object') $val[$key1]=get_class($val1).'对象实例';
            if($key1=='args'){
                foreach ($val1 as $key2=>&$val2){
                    if(is_object($val2)) {
                        $val1[$key2]=get_class($val2).'对象实例';
                    }
                    if(is_array($val2)){
                        foreach ($val2 as $key3 =>$val3){
                            if(is_object($val3)) {
                                $val2[$key3]=get_class($val3).'对象实例';
                            }
                        }
                    }
                }
            }
        }
    }
    print_r($debug_arr);
    echo $html_end;
    $info=ob_get_contents();
    ob_end_clean();
    _saveFile($info,$filename,$append);
}

function d2f2($append=false,$filename='3.html'){
    global $html_begin,$html_end;
    ob_start();
    echo $html_begin;
    $a = debug_backtrace();
    $out = array();
    foreach ($a as $key=>$val){
        if(!isset($val['file'])) continue;
        $out[]=$val['file'].' ['.$val['line'].'] ::'.$val['function'];
    }
    print_r($out);
    echo $html_end;
    $info=ob_get_contents();
    ob_end_clean();
    _saveFile($info,$filename,$append);
}

function _getProperties($obj,$level=1,$proName='all',$exludes=[]){
    $level = (int)$level;
    if($level<1 || $level>3){
        throw new Exception('暂时只支持1-3级');
    }
    $reflect = new \ReflectionObject($obj);
    //获取所有属性的值
    $props=[];
    if($proName==='all'){
        $props = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_STATIC);
        //获取某个或某些属性的值

    }else{
        $targets = array($proName);
        foreach($targets as $pr){
            $props[]=$reflect->getProperty($pr);
        }
    }

    //排除
    if(!empty($exludes) && is_array($exludes)){
        foreach($props as $k=>$v){
            if(in_array($v->getName(),$exludes)){
                unset($props[$k]);

            }
        }
    }

    $_tmp= array();
    $_sublevel=$level-1;

    foreach ($props as $t=>$pro){
        $pro->setAccessible(true);
        $name = $pro->getName();
        $value = $pro->getValue($obj);
        if($level==1){
            if(is_object($value))	 $value=get_class($value).'对象';
            if(is_array($value)) {
                foreach ($value as $k=>&$v){
                    if(is_object($v))	 $v=get_class($v).'对象';
                }
            }

        }else{
            if(is_object($value))	 $value=getProperties($value,$_sublevel);
            if(is_array($value)) {
                foreach ($value as $k=>&$v){
                    if(is_object($v))	 $v=getProperties($v,$_sublevel);
                }
            }
        }

        $_tmp[$name]=$value;
    }
    return $_tmp;

}


/**
 * 获取调试文件的保存路径，用户根据自己的实际情况调整保存目录
 */
function _saveFile($info,$filename,$append){
    $saveDir = getcwd(); //部分框架当前工作目录不是public下，如zend，请在这里做修改
    is_dir($saveDir) || mkdir($saveDir, 0777); //如果文件夹不存在，先建文件夹
    $saveFile = $saveDir . DIRECTORY_SEPARATOR . $filename; //如果文件不存在，先新建一个再使用file_put_contents
    if(!is_file($saveFile)){
        if( ($TxtRes=fopen ($saveFile,"w+")) === FALSE){
            echo("创建可写文件：".$tmpDir . $filename."失败");
            exit();
        }
    }
    $flag = ($append)?FILE_APPEND:LOCK_EX;
    file_put_contents($saveFile,$info,$flag);
}



/**
 * @param $str
 * @return mixed
 * 用途:  此方法用于清理打印对象生成的空行等
 */
function _clean($str)
{
    $patterns        = [];
    $patterns[0]     = '/\*RECURSION\*/';
    $patterns[1]     = '/\n\n/';
    $patterns[2]     = '/(Array|Object)\n\s*\(/';
    $patterns[3]     = '/\(\s+\)/';
    $replacements    = [];
    $replacements[0] = '';
    $replacements[1] = "\n";
    $replacements[2] = '$1(';
    $replacements[3] = '()';
    return preg_replace($patterns, $replacements, $str);
}

/**
 * @param $printr
 * @return array
 * 用途：对象转为数组
 */
function object2class($printr) {
    $newarray = array();
    $a[0] = &$newarray;
    if (preg_match_all('/^\s+\[(\w+).*\] => (.*)\n/m', $printr, $match)) {
        foreach ($match[0] as $key => $value) {
            (int)$tabs = substr_count(substr($value, 0, strpos($value, "[")), "        ");
            if ($match[2][$key] == 'Array' || substr($match[2][$key], -6) == 'Object') {
                $a[$tabs+1] = &$a[$tabs][$match[1][$key]];
            }
            else {
                $a[$tabs][$match[1][$key]] = $match[2][$key];
            }
        }
    }
    return $newarray;
}

function object2array($printr) {
    $newarray = array();
    $a[0] = &$newarray;
    if (preg_match_all('/^\s+\[(\w+).*\] => (.*)\n/m', $printr, $match)) {
        foreach ($match[0] as $key => $value) {
            (int)$tabs = substr_count(substr($value, 0, strpos($value, "[")), "        ");
            if ($match[2][$key] == 'Array' || substr($match[2][$key], -6) == 'Object') {
                $a[$tabs+1] = &$a[$tabs][$match[1][$key]];
            }
            else {
                $a[$tabs][$match[1][$key]] = $match[2][$key];
            }
        }
    }
    return $newarray;
}


function myerror($errno, $errstr, $errfile, $errline) {
      switch ($errno) {
          case E_ERROR:
          case E_PARSE:
          case E_CORE_ERROR:
          case E_COMPILE_ERROR:
          case E_USER_ERROR:
            ob_end_clean();
            $errorStr = "$errstr ".$errfile." 第 $errline 行.";
            p2f($errorStr,1,'5.html');
            break;
          case E_STRICT:
          case E_USER_WARNING:
          case E_USER_NOTICE:
          default:
           // $errorStr = "[$errno] $errstr ".$errfile." 第 $errline 行.";
            break;
      }
}










