<?PHP

define("VERSION","Classic v1.03.1");

#############################################################################
# PHPFormMail - Something we've allways had...				    #
# Copyright (c) 1999 Andrew Riley (webmaster@boaddrink.com)		    #
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

$referers = array("boaddrink.com","www.boaddrink.com","boaddrink.org","www.boaddrink.org");

$valid_env = array("REMOTE_HOST", "REMOTE_ADDR", "REMOTE_USER", "HTTP_USER_AGENT");

#############################################################################

$recipients = $referers;
$errors = array();
$invis_array = array("recipient","subject","required","redirect",
		     "print_blank_fields","env_report","sort",
		     "missing_fields_redirect","title","bgcolor",
		     "text_color","link_color","alink_color",
		     "vlink_color","background","subject","title",
		     "link","css","return_link_title",
		     "return_link_url","recipient_cc","recipient_bcc",
                     "priority");


/****************************************************************
 * Due to PHP3 not having a function to find if an		*
 * element is in an array, I created a native version		*
 * of in_array that mimics PHP4's in_array function.		*
 * You sould only uncomment this function if you use		*
 * PHP3.  If you uncomment it in PHP4, you'll get errors.	*
 ****************************************************************/

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


/****************************************************************
 * fill_data() is a gernic function to assign data.		*
 ****************************************************************/

function fill_data(&$from, $to, $tag = NULL){
	if(!isset($from))
		$from = $to;
	if(isset($tag))
		$from = " " . $tag . ": " . $from . ";";
}


/****************************************************************
 * check_referer() breaks up the enviromental variable		*
 * HTTP_REFERER by "/" and then checks to see if the second	*
 * member of the array (from the explode) matches any of the	*
 * domains listed in the $referers array (declaired at top)	*
 ****************************************************************/

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

/****************************************************************
 * check_recipeints() breaks up the recipents e-mail addresses	*
 * and then crossrefrences the domains that are legal referers	*
 * Added 1.3.1							*
 ****************************************************************/

function check_recipients($recipients, $recipient_list){
	$recipients_ok = true;
	$recipient_list = explode(",", $recipient_list);
	while(list(,$recipient) = each($recipient_list)){
		$recipient_domain = false;
		while((list(,$domain) = each($recipients)) && ($recipient_domain == false)){
			if(eregi("@" . $domain . "$",$recipient))
				$recipient_domain = true;
		}
		if($recipient_domain == false)
			$recipients_ok = false;
	}
	if (!$recipients_ok){
		global $errors;
		$errors[] = "You are trying to send mail to a domain that is not allowed.";
		error_log("[PHPFormMail] Illegal Recipient: " . $recipient . " from " . getenv("HTTP_REFERER"), 0);
	}
	return $recipients_ok;
}

/****************************************************************
 * decode_vars() is used to assign all of the variables passed	*
 * into the form to a generic variable.  Allthough there are	*
 * two official form actions, POST and GET, I decided to use	*
 * this variable method so if more actions are invented, I	*
 * wouldn't have to change anything.				*
 *								*
 * In the first line, the request methood is assigned to	*
 * $request with HTTP_ and _VARS appended to it.		*
 * In the second line uses PHPs variable variable.		*
 * It's basically addressing the variable $HTTP_POST_VARS or	*
 * $HTTP_GET_VARS and returning that.  Read more about		*
 * variable variables in the PHP documentation.			*
 ****************************************************************/

function decode_vars(){
	$request = "HTTP_" . getenv("REQUEST_METHOD") . "_VARS";
	global $$request;
	return $$request;
}


/****************************************************************
 * error() is our generic error function.			*
 * When called, it checks for errors in the $errors array and	*
 * depending on $form["missing_fields_redirect"] will either	*
 * print out the errors by calling the function output_html()	*
 * or it will redirect to the location specified in		*
 * $form["missing_fields_redirect"].				*
 ****************************************************************/

function error($errors){
	global $form, $natural_form;
	if ($errors){
		if (isset($form["missing_fields_redirect"])){
			$args = compile_url_args($natural_form);
			Header(  "Location: ". $form["missing_fields_redirect"] . $args);
			exit;
		} else {
			fill_data($form["title"],"PHPFormMail - Error");
			$output = "<p class=\"title\">The following errors were found:</p>\n";
			while (list(,$val) = each ($errors)) {
				$output .= $val . "<br />\n";
			}
			$output .=  "<p>Please use the <a href=\"javascript: history.back();\">back</a> button to correct these errors.</p>\n";
			output_html($output);
			exit;
		}
	}
}


/****************************************************************
 * compile_url_args() is used to create the arguments from	*
 * $form for sending to the "Thank you" and redirected "Error"	*
 * pages. This allows for the "Thank you/Error" pages to	*
 * properly report errors and if needed, record the values to a	*
 * different medium.						*
 * Function added in 1.03.0					*
 ****************************************************************/

function compile_url_args($form){
	$output = "?";
	while(list($key,$val) = each($form)){
		if(is_array($val)){

			// I'm not happy with my array support. With the
			// way I currently have it, it can only handle
			// 1D arrays.

			while(list($key1,$val1) = each($val)){
				$output .= urlencode($key ."[" . $key1 . "]") . "=" . urlencode($val1) . "&";
			}
		} else
			$output .= urlencode($key) . "=" . urlencode($val) . "&";
	}
	return substr($output,0,-1);
}


/****************************************************************
 * check_required() is the function that checks all required	*
 * fields to see if they are empty or match the provided regex	*
 * string (regex checking added in 1.02.0).			*
 *								*
 * Should a required variable be empty or not match the regex	*
 * pattern, a error will be added to the global $errors array.	*
 ****************************************************************/

function check_required(){
	global $form, $errors, $invis_array;
	$problem = true;
	if ((!$form["recipient"]) && (!$form["recipient_bcc"])) {
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
			} else if(isset($form[$regex_field_name])){
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


/****************************************************************
 * sort_fields() is responsable for sorting all fields in $form	*
 * depending $form["sort"].					*
 * There are three main sort methods: alphabetic, reverse	*
 * alphabetic, and user supplied.				*
 *								*
 * The user supplied method is formatted "order:name,email,etc".*
 * The text "order" is required and the fields are comma	*
 * sepperated. ("order" is legacy from the PERL version.) If	*
 * the user supplied method leaves fields out of the comma	*
 * sepperated list, the remaining fields will be appended to	*
 * the end of the orderd list in the order they appear in the	*
 * form.							*
 * Function added in 1.02.0					*
 ****************************************************************/

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


/****************************************************************
 * send_mail() the function that parses the data into SMTP	*
 * format and sends the e-mail.					*
 ****************************************************************/

function send_mail(){
	global $form, $invis_array, $valid_env;
	$mailbody = "Below is the result of your feedback form.  It was submitted by\n";
	$mailbody.= $form["realname"]. " (" .$form["email"].") on " . date("F dS, Y") ."\n\n";

	reset($form);
	while (list($key,$val) = each($form)){
		if ((!in_array($key,$invis_array)) && ((isset($form["print_blank_fields"])) || ($val))){
			if(is_array($val))
				$val = implode(", ", $val);
			$mailbody .= $key . ": " . stripslashes($val) . "\n";
		}
	}

	if (isset($form["env_report"])){
		$temp_env_report = explode(",",$form["env_report"]);
		$mailbody .= "\n\n-------- Env Report --------\n";
		while(list(,$val) = each($temp_env_report)){
			if(in_array($val,$valid_env))
				$mailbody .= $val . ": " . getenv($val) . "\n";
		}
	}

	// Append lines to $mail_header that you wish to be
	// added to the headers of the e-mail. (SMTP Format
	// with newline char ending each line)

	$mail_header = "From: " .$form["email"]. " (" .$form["realname"]. ")\n";
	if(isset($form["recipient_cc"]))
		$mail_header .= "Cc: " . $form["recipient_cc"] . "\n";
	if(isset($form["recipient_bcc"]))
		$mail_header .= "Bcc: " . $form["recipient_bcc"] . "\n";
	if(isset($form["priority"]))
		$mail_header .= "X-Priority: " . $form["priority"] . "\n";
	else
		$mail_header .= "X-Priority: 3\n";
	$mail_header .= "X-Mailer: PHPFormMail " . VERSION . " (http://www.boaddrink.com)\n";

	$mail_status = mail($form["recipient"],$form["subject"],$mailbody,$mail_header);
	if(!$mail_status){
		 $errors[] = "Message could not be sent due to an error while trying to send the mail.";
                 error_log("[PHPFormMail] Mail could not be sent due to an error while trying to send the mail.");
	}
	return $mail_status;
}


/****************************************************************
 * output_html() is used to output all HTML to the browser.	*
 * This function is called if there is an error or for the	*
 * "Thank You" page if neither are declaired as redirects.	*
 *								*
 * While called output_html() it actually outputs valid XHTML	*
 * 1.0 documents.						*
 * Function added in 1.02.0					*
 ****************************************************************/

function output_html($body){
	global $form;
	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
	print "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en-US\" lang=\"en-US\">\n";
	print "<head>\n";
	print "  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=us-ascii\" />\n";
	print "  <title>" . $form["title"] . "</title>\n";
	print "  <style type=\"text/css\">\n";
	print "    BODY {" . $form["bgcolor"] . $form["text_color"] . "}\n";
	if(isset($form["link_color"]))
		print "    A {" . $form["link_color"] . $form["bgcolor"] . "}\n";
	if(isset($form["alink_color"]))
		print "    A:active {" . $form["alink_color"] . $form["bgcolor"] . "}\n";
	if(isset($form["vlink_color"]))
		print "    A:visited {" . $form["vlink_color"] . $form["bgcolor"] . "}\n";
	print "    .title {font-size: 12pt; font-weight: bold}\n";
	print "  </style>\n";
	if(isset($form["css"]))
		print "  <link rel=\"stylesheet\" href=\"" . $form["css"] . "\">\n";
	print "</head>\n\n";
	print "<body>\n";
	print "<!-- PHPFormMail " . VERSION . " from http://www.boaddrink.com -->\n";
	print $body;
	print "<p>\n";
	print "  <a href=\"http://validator.w3.org/check/referer\" target=\"_blank\"><img src=\"http://www.w3.org/Icons/valid-xhtml10\" style=\"border:0;width:88px;height:31px\" alt=\"Valid XHTML 1.0!\" /></a>\n";
	print "</p>\n";
	print "</body>\n";
	print "</html>";
}

// $form is array we work with.
// $natural_form is the array that does not get changed.
// This is for redirects that need the data in an origional form.

$form = $natural_form = decode_vars();

fill_data($form["bgcolor"],"#FFFFFF","background-color");
fill_data($form["text_color"],"#000000","color");

fill_data($form["link_color"],NULL,"color");
fill_data($form["alink_color"],NULL,"color");
fill_data($form["vlink_color"],NULL,"color");

check_referer($referers);
if(isset($form["recipient"]))
	check_recipients($recipients,$form["recipient"]);
if(isset($form["recipient_cc"]))
	check_recipients($recipients,$form["recipient_cc"]);
if(isset($form["recipient_bcc"]))
	check_recipients($recipients,$form["recipient_bcc"]);
check_required();

if(!$errors){
	fill_data($form["subject"],"WWW Form Submission");
	fill_data($form["email"],"email@example.com");
	fill_data($form["realname"],"Unknown Stranger");

	if(isset($form["sort"]))
		sort_fields();

	if(send_mail()){
		if (isset($form["redirect"])){
			$args = compile_url_args($natural_form);
			Header("Location: " . $form["redirect"] . $args);
			exit;
		} else {
			fill_data($form["title"],"PHPFormMail - Form Results");
			$output = "<p class=\"title\">The following information has been submitted:</p>\n";
			reset($form);
			while(list($key,$val) = each($form)){
				if (!in_array($key,$invis_array)){
					if(is_array($val))
						$val = implode(", ", $val);
					$output .= "<b>" . htmlspecialchars($key) . ":</b> " . htmlspecialchars(stripslashes($val)) . "<br />\n";
				}
			}
			if(isset($form["return_link_url"]) && isset($form["return_link_title"]))
				$output .= "<p><a href=\"" . $form["return_link_url"] . "\">". $form["return_link_title"] . "</a></p>\n";
			output_html($output);
		}
	}
}

error($errors);

?>
