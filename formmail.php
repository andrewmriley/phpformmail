<?PHP

define('VERSION','Classic v1.04.0');

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

$referers = array('example.com','www.example.com');

$valid_env = array('REMOTE_HOST', 'REMOTE_ADDR', 'REMOTE_USER', 'HTTP_USER_AGENT');

#############################################################################

$recipients = $referers;
$errors = array();
$invis_array = array('recipient','subject','required','redirect',
		     'print_blank_fields','env_report','sort',
		     'missing_fields_redirect','title','bgcolor',
		     'text_color','link_color','alink_color',
		     'vlink_color','background','subject','title',
		     'link','css','return_link_title',
		     'return_link_url','recipient_cc','recipient_bcc',
                     'priority','redirect_values');

/****************************************************************
 * fake_in_array() is only used in PHP3 since PHP4 has a native	*
 * in_array.  Depending on what version of PHP you are running	*
 * the script will determine what is the best function to run 	*
 * --- THER IS NO LONGER ANY REASON TO DELETE THIS FUNCTION ---	*
 * Function renamed in 1.04.0					*
 ****************************************************************/

function fake_in_array($needle,$haystack){
	$found = false;
	while (list($key,$val) = each ($haystack)){
		if ($needle == $val)
			$found = true;
	}
	return $found;
}

/****************************************************************
 * fill_data() is a gernic function to assign data.		*
 ****************************************************************/

function fill_data(&$from, $to, $tag = ''){
	if(!isset($from))
		$from = $to;
	if($tag != '')
		$from = ' ' . $tag . ': ' . $from . ';';
}


/****************************************************************
 * check_referer() breaks up the enviromental variable		*
 * HTTP_REFERER by "/" and then checks to see if the second	*
 * member of the array (from the explode) matches any of the	*
 * domains listed in the $referers array (declaired at top)	*
 ****************************************************************/

function check_referer($referers){
	global $errors, $in_array_func;
	if (count($referers)){
		if(getenv('HTTP_REFERER')){
			$temp = explode('/', getenv('HTTP_REFERER'));
			$found = $in_array_func($temp[2], $referers);
			if (!$found){
				$errors[] = 'You are coming from an unauthorized domain.';
				error_log('[PHPFormMail] Illegal Referer. (' . getenv('HTTP_REFERER') . ')', 0);
			}
			return $found;
		} else {
			$errors[] = 'Sorry, but I cannot figure out who sent you here.  Your browser is not sending an HTTP_REFERER.';
			error_log('[PHPFormMail] HTTP_REFERER not defined. Browser: ' . getenv('HTTP_USER_AGENT') . '; Client IP: ' . getenv('REMOTE_ADDR') . '; Request Method: ' . getenv('REQUEST_METHOD') . ';', 0);
			return false;
		}
	} else {
		$errors[] = 'You have no referers defined.  All submissions will be denied.';
		error_log('[PHPFormMail] You have no referers defined.  All submissions will be denied.', 0);
		return false;
	}
}

/****************************************************************
 * check_recipients() breaks up the recipents e-mail addresses	*
 * and then crossrefrences the domains that are legal referers	*
 * Function added in 1.3.1					*
 ****************************************************************/

function check_recipients($recipients, $recipient_list){
	global $errors;
	$recipients_ok = true;
	$recipient_list = explode(',', $recipient_list);
	while(list(,$recipient) = each($recipient_list)){
		$recipient_domain = false;
		while((list(,$domain) = each($recipients)) && ($recipient_domain == false)){
			if(eregi('@' . $domain . '$', $recipient))
				$recipient_domain = true;
		}
		if($recipient_domain == false){
			$recipients_ok = false;
			error_log('[PHPFormMail] Illegal Recipient: ' . $recipient . ' from ' . getenv('HTTP_REFERER'), 0);
		}
	}
	if (!$recipients_ok)
		$errors[] = 'You are trying to send mail to a domain that is not allowed. (Check your recipients)';
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
	$request = 'HTTP_' . getenv('REQUEST_METHOD') . '_VARS';
	global $$request;
	while(list($key, $val) = each($$request)){
		if(is_array($val))
			$val = implode(', ',$val);
		$output[$key] = stripslashes($val);
	}
	return $output;
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
		if (isset($form['missing_fields_redirect'])){
			if(isset($form['redirect_values']))
				header('Location: ' . $form['missing_fields_redirect'] . '?' . getenv('QUERY_STRING') . "\r\n");
			else
				header('Location: ' . $form['missing_fields_redirect'] . "\r\n");
			exit;
		} else {
			fill_data($form['title'],'PHPFormMail - Error');
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
	if ((!isset($form['recipient'])) && (!isset($form['recipient_bcc']))) {
		$problem = false;
		$errors[] = 'There is no recipient to send this mail to.';
		error_log('[PHPFormMail] There is no recipient defined from ' . getenv('HTTP_REFERER'), 0);
	}
	if (isset($form['required'])){
		$required = split(',', $form['required']);
		while(list(,$val) = each($required)){
			$val = trim($val);
			$regex_field_name = $val . '_regex';
			if(!$form[$val]){
				$problem = false;
				$errors[] = 'Required value (<b>' . $val . '</b>) is missing.';
			} else if(isset($form[$regex_field_name])){
				if(!eregi($form[$regex_field_name],$form[$val])){
					$problem = false;
					$errors[] = 'Required value (<b>' . $val . '</b>) has an invalid format.';
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
		case 'alphabetic':
		case 'alpha':		ksort($form);
					break;
		case 'ralphabetic':
		case 'ralpha':		krsort($form);
					break;
		default:		if($col = strpos($form['sort'],':')){
						$form['sort'] = substr($form['sort'],($col + 1));
						$temp_sort_arr = explode(',', $form['sort']);
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
	global $form, $invis_array, $valid_env, $in_array_func;
	if(isset($form['realname']))
		$realname = $form['realname'];
	elseif(isset($form['firstname']) || isset($form['lastname']))
		$realname = trim($form['firstname'] . ' ' . $form['lastname']);
		
	$mailbody = "Below is the result of your feedback form.  It was submitted by\n";
	if(isset($realname))
		$mailbody.= $realname . ' (' . $form['email'] . ') on ' . date('F jS, Y') . "\n\n";
	else
		$mailbody.= $form['email'] . ' on ' . date('F jS, Y') . "\n\n";

	reset($form);
	while (list($key,$val) = each($form)){
		if ((!$in_array_func($key,$invis_array)) && ((isset($form['print_blank_fields'])) || ($val)))
			$mailbody .= $key . ': ' . $val . "\n";	
	}

	if (isset($form['env_report'])){
		$temp_env_report = explode(',', $form['env_report']);
		$mailbody .= "\n\n-------- Env Report --------\n";
		while(list(,$val) = each($temp_env_report)){
			if($in_array_func($val,$valid_env))
				$mailbody .= $val . ': ' . getenv($val) . "\n";
		}
	}

	if(!isset($form['recipient']))
		$form['recipient'] = '';

	// Append lines to $mail_header that you wish to be
	// added to the headers of the e-mail. (SMTP Format
	// with newline char ending each line)

	$mail_header = 'From: ' . $form['email'];
	if(isset($realname))
		$mail_header .= ' (' . $realname . ')';
	$mail_header .= "\n";
	if(isset($form['recipient_cc']))
		$mail_header .= 'Cc: ' . $form["recipient_cc"] . "\n";
	if(isset($form['recipient_bcc']))
		$mail_header .= 'Bcc: ' . $form['recipient_bcc'] . "\n";
	if(isset($form['priority']))
		$mail_header .= 'X-Priority: ' . $form['priority'] . "\n";
	else
		$mail_header .= "X-Priority: 3\n";
	$mail_header .= 'X-Mailer: PHPFormMail ' . VERSION . " (http://www.boaddrink.com)\n";

	$mail_status = mail($form['recipient'], $form['subject'], $mailbody, $mail_header);
	if(!$mail_status){
		 $errors[] = 'Message could not be sent due to an error while trying to send the mail.';
                 error_log('[PHPFormMail] Mail could not be sent due to an error while trying to send the mail.');
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
	print "  <meta name=\"robots\" content=\"noindex,nofollow\" />\n";
	print "  <title>" . $form["title"] . "</title>\n";
	print "  <style type=\"text/css\">\n";
	print "    BODY {" . $form['bgcolor'] . $form['text_color'] . "}\n";
	if(isset($form['link_color']))
		print "    A {" . $form['link_color'] . $form['bgcolor'] . "}\n";
	if(isset($form['alink_color']))
		print "    A:active {" . $form['alink_color'] . $form['bgcolor'] . "}\n";
	if(isset($form['vlink_color']))
		print "    A:visited {" . $form['vlink_color'] . $form['bgcolor'] . "}\n";
	print "    .title {font-size: 12pt; font-weight: bold}\n";
	print "  </style>\n";
	if(isset($form['css']))
		print "  <link rel=\"stylesheet\" href=\"" . $form['css'] . "\">\n";
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


// Determine (based on the PHP version) if we should use the native
// PHP4 in_array or the coded fake_in_array

if(phpversion() >= '4.0.0')
	$in_array_func = 'in_array';
else
	$in_array_func = 'fake_in_array';

$form = decode_vars();

fill_data($form['bgcolor'], '#FFFFFF', 'background-color');
fill_data($form['text_color'], '#000000', 'color');

fill_data($form['link_color'], '', 'color');
fill_data($form['alink_color'], '', 'color');
fill_data($form['vlink_color'], '', 'color');

check_referer($referers);
if(isset($form['recipient']))
	check_recipients($recipients, $form['recipient']);
if(isset($form['recipient_cc']))
	check_recipients($recipients, $form['recipient_cc']);
if(isset($form['recipient_bcc']))
	check_recipients($recipients, $form['recipient_bcc']);
check_required();

if(!$errors){
	fill_data($form['subject'],'WWW Form Submission');
	fill_data($form['email'],'email@example.com');

	if(isset($form['sort']))
		sort_fields();

	if(send_mail()){
		if (isset($form['redirect'])){
			if(isset($form['redirect_values']))
				header('Location: ' . $form['redirect'] . '?' . getenv('QUERY_STRING') . "\r\n");
			else
				header('Location: ' . $form['redirect'] . "\r\n");
			exit;
		} else {
			fill_data($form['title'],'PHPFormMail - Form Results');
			$output = "<p class=\"title\">The following information has been submitted:</p>\n";
			reset($form);
			while(list($key,$val) = each($form)){
				if ((!$in_array_func($key,$invis_array)) && ((isset($form['print_blank_fields'])) || ($val)))
					$output .= '<b>' . htmlspecialchars($key) . ':</b> ' . htmlspecialchars($val) . "<br />\n";
			}
			if(isset($form['return_link_url']) && isset($form['return_link_title']))
				$output .= '<p><a href="' . $form["return_link_url"] . '">'. $form["return_link_title"] . "</a></p>\n";
			output_html($output);
		}
	}
}

error($errors);

?>