<?php
require_once ("admin-header.php");
//require_once("../include/check_post_key.php");

if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_problem_editor'])  )) {
  echo "<a href='../loginpage.php'>Please Login First!</a>";
  exit(1);
}

if (isset($OJ_LANG)) {
  require_once("../lang/$OJ_LANG.php");
}

require_once ("../include/const.inc.php");
require_once ("../include/problem.php");
?>

<?php
?>

<hr>
&nbsp;&nbsp;- Import Problem ... <br>
&nbsp;&nbsp;- 如果导入失败，请参考 <a href="https://github.com/zhblue/hustoj/blob/master/wiki/FAQ.md#%E5%90%8E%E5%8F%B0%E5%AF%BC%E5%85%A5%E9%97%AE%E9%A2%98%E5%A4%B1%E8%B4%A5" target="_blank">FAQ</a>。
<br><br>

<?php
function startsWith( $haystack, $needle ) {
     $length = strlen( $needle );
     return substr( $haystack, 0, $length ) === $needle;
}
function endsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    if( !$length ) {
        return true;
    }
    return substr( $haystack, -$length ) === $needle;
}
function strip($Node, $TagName) {
  $len=mb_strlen($TagName);
  $i=mb_strpos($Node,"<".$TagName.">");
  $j=mb_strpos($Node,"</".$TagName.">");

  return mb_substr($Node,$i+$len+2,$j-($i+$len+2));
}
function get_extension($file) {
  $info = pathinfo($file);
  return $info['extension'];
}

function getAttribute($Node, $TagName,$attribute) {
  return $Node->children()->$TagName->attributes()->$attribute;
}

function hasProblem($title) {
  //return false;	
  $md5 = md5($title);
  $sql = "SELECT 1 FROM problem WHERE md5(title)=?";  
  $result = pdo_query($sql, $md5);
  $rows_cnt = count($result);		
  //echo "row->$rows_cnt";			
  return ($rows_cnt>0);
}

function mkpta($pid,$prepends,$node) {
  $language_ext = $GLOBALS['language_ext'];
  $OJ_DATA = $GLOBALS['OJ_DATA'];

  foreach ($prepends as $prepend) {
    $language = $prepend->attributes()->language;
    $lang = getLang($language);
    $file_ext = $language_ext[$lang];
    $basedir = "$OJ_DATA/$pid";
    $file_name = "$basedir/$node.$file_ext";
    file_put_contents($file_name,$prepend);
  }
}


function import_dir($json) {
  global $OJ_DATA,$OJ_SAE,$OJ_REDIS,$OJ_REDISSERVER,$OJ_REDISPORT,$OJ_REDISQNAME,$domain,$DOMAIN;
  $qduoj_problem=json_decode($json);
  echo( $qduoj_problem->{'problem'}->{'title'})."<br>";

    $title = $qduoj_problem->{'problem'}->{'title'};

    $time_limit = floatval($qduoj_problem->{'problem'}->{'timeLimit'});
    $unit = "ms";
    //echo $unit;

    if ($unit=='ms')
      $time_limit /= 1000;

    $memory_limit =  floatval($qduoj_problem->{'problem'}->{'memoryLimit'});
    $unit = "M";

    if ($unit=='kb')
      $memory_limit /= 1024;

    $description = $qduoj_problem->{'problem'}->{'description'};
    $input = $qduoj_problem->{'problem'}->{'input'};
    $output = $qduoj_problem->{'problem'}->{'output'};
    $sample_input = strip($qduoj_problem->{'problem'}->{'examples'},"input");
    $sample_output = strip($qduoj_problem->{'problem'}->{'examples'},"output");
//    echo $sample_input."<br>";
//    echo $sample_output;
    $hint = $qduoj_problem->{'problem'}->{'hint'};
    $source = $qduoj_problem->{'problem'}->{'source'};				
    $spj=0;
    
    $pid = addproblem($title, $time_limit, $memory_limit, $description, $input, $output, $sample_input, $sample_output, $hint, $source, $spj, $OJ_DATA);
    return $pid;
}


if ($_FILES ["fps"] ["error"] > 0) {
  echo "&nbsp;&nbsp;- Error: ".$_FILES ["fps"] ["error"]."File size is too big, change in PHP.ini<br />";
}
else {
  $tempdir = sys_get_temp_dir()."/import_hydro".time();	
  mkdir($tempdir);
  $tempfile = $_FILES ["fps"] ["tmp_name"];
  if (get_extension( $_FILES ["fps"] ["name"])=="zip") {
    echo "&nbsp;&nbsp;- zip file, only HydroOJ exported file is supported<hr>\n";
    $resource = zip_open($tempfile);
    $save_path="";
    $i = 1;
    $pid=$title=$description=$input=$output=$sample_input=$sample_output=$hint=$source=$spj="";
    $type="normal";
    while ($dir_resource = zip_read($resource)) {
      if (zip_entry_open($resource,$dir_resource)) {
        $file_name = $path.zip_entry_name($dir_resource);
        $file_path = substr($file_name,0,strrpos($file_name, "/"));
        if (!is_dir($file_name)) {
		$file_size = zip_entry_filesize($dir_resource);
		$file_content = zip_entry_read($dir_resource,$file_size);
		if(basename($file_name)=="problem.yaml"){
			$hydrop=yaml_parse($file_content);	
			$title=$hydrop['title'];	
			$source=implode(" ",$hydrop['tag']);	
			echo "<hr>".htmlentities($file_name." $title $source");
		}else if(basename($file_name)=="problem_zh.md"||basename($file_name)=="problem.md"){
			$regex = '/<(?!div)/';
                        $file_content = preg_replace($regex, '＜',$file_content);
                        $regex = '/(?<!div)>\s?/';
                        $file_content = preg_replace($regex, '＞', $file_content);
                        $file_content = str_replace("&", '＆', $file_content);
			
//			if(strpos($file_content,"##")===false) 
//				$description=$file_content;
  //                      else 
			$description="<span class='md auto_select'>".$file_content."</span>";
			$description=preg_replace('/{{ select\(\d+\) }}/', "", $description); 
			if($save_path){
				$description=str_replace("file://",$save_path."/",$description);
			
		//		echo htmlentities($description);
			}

			//echo htmlentities("$description");
			if(!hasProblem($title)){
    				$pid = addproblem($title,1,128, $description, $input, $output, $sample_input, $sample_output, $hint, $source, $spj, $OJ_DATA);
				mkdir($OJ_DATA."/$pid/");
			}else{
				echo "skiped $title";
				$pid=0;
			}
			echo "PID:$pid";
		}else if(basename($file_name)=="config.yaml"){
			$hydrop=yaml_parse($file_content);	
			if($hydrop['type']=="objective"){
				$type="objective";	
				echo $type.":dump answers";
				$ansi=1;
				$out="";
				while($hydrop['answers'][$ansi]!=""){
					$out.= $ansi." [".$hydrop['answers'][$ansi][1]."] ";
					$out.= $hydrop['answers'][$ansi][0]."\n";
					$ansi++;
				}
				//echo htmlentities($out);
				if($pid>0){
					file_put_contents($OJ_DATA."/$pid/data.out",$out);
					file_put_contents($OJ_DATA."/$pid/data.in",($ansi-1)."\n");
				}
				$template="";
				for($i=1;$i<$ansi;$i++){
					$template.=$i."\n";
				}
				file_put_contents($OJ_DATA."/$pid/template.c",$template);
				pdo_query("update problem set spj=2 where problem_id=?",$pid);
			
			}else{
				if(endsWith($hydrop['time'],"ms")){
					$hydrop['time']=substr($hydrop['time'],0,-2);
					$hydrop['time']=floatval($hydrop['time'])/1000;
				}else if(endsWith($hydrop['time'],"s")){
					$hydrop['time']=substr($hydrop['time'],0,-1);
					$hydrop['time']=floatval($hydrop['time']);
				}
				$time=floatval($hydrop['time']);
				$memory=floatval($hydrop['memory']);
				$iofile=$hydrop['filename'];
				if($pid!=""&&$iofile!=""){
					file_put_contents($OJ_DATA."/$pid/input.name",$iofile.".in\n");
					file_put_contents($OJ_DATA."/$pid/output.name",$iofile.".out\n");

				}
				if($time>0) pdo_query("update problem set time_limit=?,memory_limit=? where problem_id=?",$time,$memory,$pid);
			}
			//echo $time."s-".${memory}."m";
		}else if($pid!="" && strpos($file_path,"testdata") !== false && basename($file_name) != "testdata" ){
	  		echo ".";
			$dataname=basename($file_name);
			if(endsWith($dataname,".txt")){
				$pattern = '/input([0-9]*).txt/i';
				$dataname=preg_replace($pattern, '\\1.in', $dataname);
				$pattern = '/output([0-9]*).txt/i';
				$dataname=preg_replace($pattern, '\\1.out', $dataname);
			}else if(endsWith($dataname,"put")){
				$dataname=substr($dataname,0,-3);	
			}else if(endsWith($dataname,".ans")){
				$dataname=substr($dataname,0,-3)."out";	
			}
		        file_put_contents($OJ_DATA."/$pid/".$dataname,$file_content);
		}else if(strpos($file_path,"additional_file") !== false && basename($file_name) != "additional_file" ){
		                        	
		//	  echo $file_name."[$pid]<br>";
			  $ext = strtolower(get_extension($file_name));

			  if (!stristr(",jpeg,jpg,svg,png,gif,bmp",$ext)) {
			    $ext = "bad";
			    continue;
			  }else{
			  
				$ymd ="/upload/".$domain."/". date("Ymd");
				$save_path = $ymd ;
				//新文件名
				$new_file_name = basename($file_name);
				$newpath = $save_path."/".$new_file_name;
				 if ($OJ_SAE)
				    $newpath = "saestor://web".$newpath;
				 else
				    $newpath="..".$newpath;
				}
				if(!file_exists(dirname($newpath))){
					mkdir(dirname($newpath),0750,true);
				}
				file_put_contents($newpath,$file_content);
		//		echo ($newpath)."<br>";
			  
			  }
		
		}
	}else{

	
       zip_entry_close($dir_resource);
      }
    }
    zip_close($resource);
  
  /*
    $resource = zip_open($tempfile);

    $i = 1;
    while ($dir_resource = zip_read($resource)) {
      if (zip_entry_open($resource,$dir_resource)) {
        $file_name = $path.zip_entry_name($dir_resource);
        $file_path = substr($file_name,0,strrpos($file_name, "/"));
        if (!is_dir($file_name)) {
          $file_size = zip_entry_filesize($dir_resource);
          $file_content = zip_entry_read($dir_resource,$file_size);
	  if(get_extension($file_name)=="json")
	  {
		  $pid=import_json($file_content);
		  $dir=$tempdir."/".basename($file_name,".json");
		  mkdir("$OJ_DATA/$pid");
		  system ("mv $dir/* $OJ_DATA/$pid/");
		  system ("rmdir $dir");
	  }else{
//	  	echo "$file_name"."<br>";
//		mkdir($tempdir."/".dirname($file_name));
//		file_put_contents($tempdir."/".$file_name,$file_content);
	  }
	}else{
	  echo $file_name;
	}
       zip_entry_close($dir_resource);
      }
    }
    zip_close($resource);
   */
    unlink ( $_FILES ["fps"] ["tmp_name"] );
    system ("rmdir $tempdir");
  }
  else {
  echo ($tempfile);
  }
 
}
 

?>
