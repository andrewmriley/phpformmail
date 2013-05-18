<?PHP

define("VERSION","Classic v1.02.0");

#############################################################################
# PHPFormMail - Something we've allways had...				    #
# Copyright (c) 1999 Andrew Riley (boad@boaddrink.com)			    #
#									    #
# This program is free software; you can redistribute it and/or 	    #
# modify it under the terms of the GNU General Public License		    #
# as published by the Free Software Foundation; either version 2	    #
# of the License, or (at your option) any later version.		    #
#									    #
# This program is distributed in the hope that it will be useful,	    #
# but WITHOUT ANY WARRANTY; without even the implied warranty of	    #
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the 	    #
# GNU General Public License for more details.				    #
#									    #
# You should have received a copy of the GNU General Public License	    #
# along with this program; if not, write to the Free Software		    #
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307,    #
# USA.									    #
#									    #
#############################################################################
#									    #
# If you run into any problems, pleas read the readme_formmail.txt.	    #
# If that does not help, check out http://www.boaddrink.com.		    #
#									    #
# For more info, please visit http://www.boaddrink.com or read the readme   #
# file included.							    #
#############################################################################
#									    #
# Value array fix by: Konrad Maqestieau (konrad@shortcircuit.be)	    #
#									    #
#############################################################################
# $referers allows forms to be located only on servers which are defined    #
# in this field.							    #

$referers = array("127.0.0.1","boaddrink.com","www.boaddrink.com");

$valid_env = array("REMOTE_HOST", "REMOTE_ADDR", "REMOTE_USER", "HTTP_USER_AGENT");

#############################################################################

$errors = array();
$invis_array = array("recipient","subject","required","redirect",
		     "print_blank_fields","env_report","sort",
		     "missing_fields_redirect","title","bgcolor",
		     "text_color","link_color","alink_color",
		     "vlink_color","background","subject","title",
		     "link","css","return_link_title",
		     "return_link_url");


// START removing the /* and */ for PHP 3.X

/*function in_array($needle,$haystack){
	$found = false;
	while (list($key,$val) = each ($haystack)){
		if ($needle == $val){
			$found = true;
		}
	}
	return $found;
}*/

// STOP removing the /* and */ for PHP 3.X

function fill_data(&$from,$to,$tag=""){
	if(!$from)
		$from = $to;
	if($tag)
		$from = " " . $tag . "=\"" . $from . "\"";
}

function check_referer($referers){
	if (count($referers)){
		$temp = explode("/",getenv("HTTP_REFERER"));
		$found = in_array($temp[2],$referers);
		if (!$found){
			global $errors;
			$errors[] = "You are comming from an unauthorized domain.";
			error_log("[PHPFormMail] Illegal Referer. (".getenv("HTTP_REFERER").")", 0);
		}
		return $found;
	} else
		return true; //Not a good idea, if empty, it will allow it.
}

function decode_vars(){
	$request = "HTTP_" . getenv("REQUEST_METHOD") . "_VARS";
	global $$request;
	return $$request;
}

function error($errors){
	global $form;
	if ($errors){
		if ($form["missing_fields_redirect"]){
			Header(  "Location: ". $form["missing_fields_redirect"]);
			exit;
		} else {
			$output = "<p class=\"title\">The following errors were found:</p>\n";
			while (list(,$val) = each ($errors)) {
				$output .= $val . "<br />\n";
			}
			$output .=  "<p>Please use the <a href=\"javascript: history.back();\">back</a> button to correct these errors.</p>\n";
			output_html($output);
		}
	}
}

function check_required(){
	global $form;
	global $errors;
	global $invis_array;
	$problem = true;
	if (!$form["recipient"]) {
		$problem = false;
		$errors[] = "There is no recipient to send this mail to.";
	}
	if ($form["required"]){
		$required = split(",",$form["required"]);
		while(list(,$val) = each($required)){
			$regex_field_name = $val . "_regex";
			if(!$form[$val]){
				$problem = false;
				$errors[] = "Required value (<b>" . $val . "</b>) is missing.";
			} else	if(isset($form[$regex_field_name])){
				if(!eregi($form[$regex_field_name],$form[$val])){
					$problem = false;
					$errors[] = "Required value (<b>" . $val . "</b>) has an invalid format.";
				}
				$invis_array[] = $regex_field_name;
			}
		}
	}
	return $problem;
}

function sort_fields(){
	global $form;
	switch($form["sort"]){
		case "alphabetic":
		case "alpha":		ksort($form);
					break;
		case "ralphabetic":
		case "ralpha":		krsort($form);
					break;
		default:		if($col = strpos($form["sort"],":")){
						$form["sort"] = substr($form["sort"],($col + 1));
						$temp_sort_arr = explode(",",$form["sort"]);
						for($x = 0; $x < count($temp_sort_arr); $x++){
							$out[$temp_sort_arr[$x]] = $form[$temp_sort_arr[$x]];
							unset($form[$temp_sort_arr[$x]]);
						}
						$form = array_merge($out,$form);
					}
	}
	return true;
}

function send_mail(){
	global $form;
	global $invis_array;
	global $valid_env;
	$mailbody = "Below is the result of your feedback form.  It was submitted by\n";
	$mailbody.= $form["realname"]. " (" .$form["email"].") on " . date("F dS, Y") ."\n\n";

	reset($form);
	while (list($key,$val) = each($form)){
		if ((!in_array($key,$invis_array)) && (($form["print_blank_fields"]) || ($val))){
			if(is_array($val))
				$val = implode(", ", $val);
			$mailbody .= $key . ": " . stripslashes($val) . "\n";
		}
	}

	if ($form["env_report"]){
		$temp_env_report = explode(",",$form["env_report"]);
		$mailbody .= "\n\n-------- Env Report --------\n";
		while(list(,$val) = each($temp_env_report)){
			if(in_array($val,$valid_env))
				$mailbody .= $val . ": " . getenv($val) . "\n";
		}
	}

	$mail_status = @mail($form["recipient"],$form["subject"],$mailbody,"From: " .$form["email"]. " (" .$form["realname"]. ")\nX-Mailer: PHPFormMail " . VERSION . " (http://www.boaddrink.com)");
	if(!$mail_status){
		 $errors[] = "Mail could not be sent due to mailserver configuration problems.";
                 error_log("[PHPFormMail] Mail could not be sent due to mailserver configuration problems.");
	}
}

function output_html($body){
	global $form;
	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
	print "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en-US\" lang=\"en-US\">\n";
	print "<head>\n";
	print "  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=us-ascii\" />\n";
	print "  <title>" . $form["title"] . "</title>\n";
	print "  <style type=\"text/css\">\n";
	print "    .title {font-size: 12pt; font-weight: bold}\n";
	print "  </style>\n";
	if($form["css"])
		print "  <link rel=\"stylesheet\" href=\"" . $form["css"] . "\">\n";
	print "</head>\n\n";
	print "<body" . $form["bgcolor"] . $form["text_color"] . $form["link_color"] . $form["alink_color"] . $form["vlink_color"] . $form["background"] . ">\n";
	print "<!-- PHPFormMail " . VERSION . " from http://www.boaddrink.com -->\n";
	print $body;
	print "<p>\n";
	print "  <a href=\"http://validator.w3.org/check/referer\" target=\"_blank\"><img src=\"http://www.w3.org/Icons/valid-xhtml10\" style=\"border:0;width:88px;height:31px\" alt=\"Valid XHTML 1.0!\" /></a>\n";
	print "</p>\n";
	print "</body>\n";
	print "</html>";
}

$form = decode_vars();
fill_data($form["bgcolor"],"#FFFFFF","bgcolor");
fill_data($form["text_color"],"#000000","text");
fill_data($form["link_color"],"#0000FF","link");
fill_data($form["alink_color"],"#FF0000","alink");
fill_data($form["vlink_color"],"#000099","vlink");
fill_data($form["background"],"","background");
fill_data($form["title"],"Form Results");

if(check_referer($referers) && check_required()){
	fill_data($form["subject"],"WWW Form Submission");
	fill_data($form["email"],"exampleemail@example.com");
	fill_data($form["realname"],"Unknown Stranger");

	if($form["sort"])
		sort_fields();

	send_mail();

	if ($form["redirect"]){
		Header("Location: ".$form["redirect"]);
		exit;
	} else {
		$output = "<p class=\"title\">The following information has been submitted:</p>\n";
		reset($form);
		while(list($key,$val) = each($form)){
			if (!in_array($key,$invis_array)){
				if(is_array($val))
					$val = implode(", ", $val);
				$output .= "<b>" . htmlspecialchars($key) . ":</b> " . htmlspecialchars(stripslashes($val)) . "<br />\n";
			}
		}
		if($form["return_link_url"] && $form["return_link_title"])
			$output .= "<p><a href=\"" . $form["return_link_url"] . "\">". $form["return_link_title"] . "</a></p>\n";
		output_html($output);
	}
}

error($errors);
?>
