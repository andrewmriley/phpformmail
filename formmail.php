<?PHP
define('VERSION', 'Classic v1.07.2');
define('MANUAL', 'http://www.boaddrink.com/projects/phpformmail/readme.php');
define('CHECK_REFERER', true);

// +------------------------------------------------------------------------+
// | PHPFormMail                                                            |
// | Copyright (c) 1999 Andrew Riley (webmaster@boaddrink.com)              |
// |                                                                        |
// | This program is free software; you can redistribute it and/or          |
// | modify it under the terms of the GNU General Public License            |
// | as published by the Free Software Foundation; either version 2         |
// | of the License, or (at your option) any later version.                 |
// |                                                                        |
// | This program is distributed in the hope that it will be useful,        |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of         |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the         |
// | GNU General Public License for more details.                           |
// |                                                                        |
// | You should have received a copy of the GNU General Public License	    |
// | along with this program; if not, write to the Free Software            |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, |
// | USA.                                                                   |
// |                                                                        |
// +------------------------------------------------------------------------+
// |                                                                        |
// | If you run into any problems, pleas read the readme_formmail.txt.	    |
// | If that does not help, check out http://www.boaddrink.com.             |
// |                                                                        |
// | For more info, please visit http://www.boaddrink.com or read the       |
// | readme file included.                                                  |
// +------------------------------------------------------------------------+
// |                                                                        |
// | Value array fix by: Konrad Maqestieau                                  |
// | check_recipients reset() fix by: Don                                   |
// | servertime_offset code by: desolate                                    |
// |                                                                        |
// +------------------------------------------------------------------------+

// To change the address the e-mail comes from use define('FROM', 'Example Name <email@example.com>');
define('FROM', null);

$referers = array('www.example.com', 'example.com');

// $recipient_array format is $recipient_array = array('sometext'=>'email@example.com','asdf'=>'email2@example.com');
$recipient_array = array();

$valid_env = array('REMOTE_HOST', 'REMOTE_ADDR', 'REMOTE_USER', 'HTTP_USER_AGENT');

// +------------------------------------------------------------------------+
// | STOP EDITING! The only two required variables that need to be updated  |
// | are $referers and $valid_env                                           |
// +------------------------------------------------------------------------+

$errors = $fieldname_lookup = array();
$invis_array = array('recipient', 'subject', 'required', 'redirect',
    'print_blank_fields', 'env_report', 'sort',
    'missing_fields_redirect', 'title', 'bgcolor',
    'text_color', 'link_color', 'alink_color',
    'vlink_color', 'background', 'subject', 'title',
    'link', 'css', 'return_link_title',
    'return_link_url', 'recipient_cc', 'recipient_bcc',
    'priority', 'redirect_values', 'hidden', 'alias',
    'mail_newline', 'gmt_offset', 'alias_method',
    'subject_prefix');

/****************************************************************
 * fake_in_array() is only used in PHP3 since PHP4 has a native    *
 * in_array.  Depending on what version of PHP you are running    *
 * the script will determine what is the best function to run    *
 * --- THER IS NO LONGER ANY REASON TO DELETE THIS FUNCTION ---    *
 * Function renamed in 1.04.0                    *
 ****************************************************************/

function fake_in_array($needle, $haystack) {
    $found = false;
    foreach ($haystack as $val) {
        if ($needle == $val) {
            $found = true;
        }
    }
    return $found;
}

/****************************************************************
 * check_referer() breaks up the enviromental variable          *
 * HTTP_REFERER by "/" and then checks to see if the second     *
 * member of the array (from the explode) matches any of the    *
 * domains listed in the $referers array (declaired at top)     *
 ****************************************************************/

function check_referer($referers) {
    global $errors;
    if (count($referers)) {
        if (getenv('HTTP_REFERER')) {
            $temp = explode('/', getenv('HTTP_REFERER'));
            $found = false;
            foreach($referers as $stored_referer) {
                if (eregi('^' . $stored_referer . '$', $temp[2])) {
                    $found = true;
                }
            }
            if (!$found) {
                $errors[] = '1|You are coming from an unauthorized domain.  Please read the manual section titled &quot;<a href="' . MANUAL . '#setting_up" target="_blank">Setting Up the PHPFormMail Script</a>&quot;.';
                error_log('[PHPFormMail] Illegal Referer. (' . getenv('HTTP_REFERER') . ')', 0);
            }
            return $found;
        } else {
            $errors[] = '0|Sorry, but I cannot figure out who sent you here.  Your browser is not sending an HTTP_REFERER.  This could be caused by a firewall or browser that removes the HTTP_REFERER from each HTTP request you submit.';
            error_log('[PHPFormMail] HTTP_REFERER not defined. Browser: ' . getenv('HTTP_USER_AGENT') . '; Client IP: ' . getenv('REMOTE_ADDR') . '; Request Method: ' . getenv('REQUEST_METHOD') . ';', 0);
            return false;
        }
    } else {
        $errors[] = '1|There are no referers defined.  All submissions will be denied.  Please read the manual section titled &quot;<a href="' . MANUAL . '#setting_up" target="_blank">Setting Up the PHPFormMail Script</a>&quot;.';
        error_log('[PHPFormMail] You have no referers defined.  All submissions will be denied.', 0);
        return false;
    }
}

/****************************************************************
 * check_recipients() breaks up the recipents e-mail addresses  *
 * and then crossrefrences the domains that are legal referers  *
 * Function added in 1.3.1                                      *
 ****************************************************************/

function check_recipients($recipient_list) {
    global $errors, $referers;
    $recipients_ok = true;
    $recipient_list = explode(',', $recipient_list);
    foreach ($recipient_list as $recipient) {
        $recipient_domain = false;
        $recipient = trim($recipient);
        reset($referers);
        foreach ($referers as  $stored_domain) {
            if (eregi('^[_\.a-z0-9-]*@' . $stored_domain . '$', $recipient)) {
                $recipient_domain = true;
            }
        }
        if ($recipient_domain == false) {
            $recipients_ok = false;
            error_log('[PHPFormMail] Illegal Recipient: ' . $recipient . ' from ' . getenv('HTTP_REFERER'), 0);
        }
    }
    if (!$recipients_ok) {
        $errors[] = '1|You are trying to send mail to a domain that is not in the allowed recipients list.   Please read the manual section titled &quot;<a href="' . MANUAL . '#setting_up" target="_blank">Setting Up the PHPFormMail Script</a>&quot;.';
    }
    return join(',', $recipient_list);
}

/****************************************************************
 * map_recipients() takes the array and maps them to the proper *
 * e-mail addresses from $recipient_array.  If this function is *
 * called then the e-mail addresses are not checked against the *
 * referer array.                                               *
 * Function added in 1.7.0                                      *
 ****************************************************************/

function map_recipients($recipient_list)
{
    global $errors, $recipient_array;
    $recipients_ok = true;
    $recipient_list = explode(',', $recipient_list);
    foreach ($recipient_list as $val) {
        $val = trim($val);
        if (isset($recipient_array[$val])) {
            $output[] = $recipient_array[$val];
        } else {
            $recipients_ok = false;
        }
    }
    if (!$recipients_ok) {
        $errors[] = '1|You are trying to send mail to an address that is not listed in the recipient array.';
    }
    if (isset($output)) {
        return join(',', $output);
    } else {
        return null;
    }
}

/****************************************************************
 * decode_vars() is used to assign all of the variables passed  *
 * into the form to a generic variable.  Allthough there are    *
 * two official form actions, POST and GET, I decided to use    *
 * this variable method so if more actions are invented, I      *
 * wouldn't have to change anything.                            *
 *                                                              *
 * In the first line, the request methood is assigned to        *
 * $request with HTTP_ and _VARS appended to it.                *
 * In the second line uses PHPs variable variable.              *
 * It's basically addressing the variable $HTTP_POST_VARS or    *
 * $HTTP_GET_VARS and returning that.  Read more about          *
 * variable variables in the PHP documentation.                 *
 ****************************************************************/

function decode_vars() {
    if (isset($_REQUEST)) {
        $request = '_' . getenv('REQUEST_METHOD');
    } else {
        $request = 'HTTP_' . getenv('REQUEST_METHOD') . '_VARS';
    }
    global $$request;
    if (count($$request) > 0) {
        foreach ($$request as $key => $val) {
            if (is_array($val)) {
                $val = implode(', ', $val);
            }
            $output[$key] = stripslashes($val);
        }
        return $output;
    } else {
        return array();
    }
}


/****************************************************************
 * error() is our generic error function.                       *
 * When called, it checks for errors in the $errors array and   *
 * depending on $form["missing_fields_redirect"] will either    *
 * print out the errors by calling the function output_html()   *
 * or it will redirect to the location specified in             *
 * $form["missing_fields_redirect"].                            *
 ****************************************************************/

function error() {
    global $form, $natural_form, $errors;
    if (isset($form['missing_fields_redirect'])) {
        if (isset($form['redirect_values'])) {
            header('Location: ' . $form['missing_fields_redirect'] . '?' . getenv('QUERY_STRING') . "\r\n");
        } else {
            header('Location: ' . $form['missing_fields_redirect'] . "\r\n");
        }
    } else {
        if (!isset($form['title'])) {
            $form['title'] = 'PHPFormMail - Error';
        }
        $output = "<h1>The following errors were found:</h1>\n<ul>\n";
        $crit_error = 0;
        foreach ($errors as $val) {
            list($crit, $message) = explode('|', $val);
            $output .= '  <li>' . $message . "</li>\n";
            if ($crit == 1) {
                $crit_error = 1;
            }
        }
        $output .= "</ul>\n";
        if ($crit_error == 1) {
            $output .= "<div class=\"crit\">PHPFormMail has experienced errors that must be fixed by the webmaster. Mail will NOT be sent until these issues are resolved.  Once these issues are resolved, you will have to resubmit your form to PHPFormMail for the mail to be sent.</div><div class=\"returnlink\">Please use the <a href=\"javascript: history.back();\">back</a> button to return to the site.</div>\n";
        } else {
            $output .= "<div class=\"returnlink\">Please use the <a href=\"javascript: history.back();\">back</a> button to correct these errors.</div>\n";
        }
        output_html($output);
    }
}

/****************************************************************
 * check_required() is the function that checks all required    *
 * fields to see if they are empty or match the provided regex  *
 * string (regex checking added in 1.02.0).                     *
 *                                                              *
 * Should a required variable be empty or not match the regex   *
 * pattern, a error will be added to the global $errors array.  *
 ****************************************************************/

function check_required() {
    global $form, $errors, $invis_array, $fieldname_lookup;
    $problem = true;
    if ((!isset($form['recipient'])) && (!isset($form['recipient_bcc']))) {
        $problem = false;
        $errors[] = '1|There is no recipient to send this mail to.  Please read the manual section titled &quot;<a href="' . MANUAL . '#recipient" target="_blank">Form Configuration - Recipient</a>&quot;.';
        error_log('[PHPFormMail] There is no recipient defined from ' . getenv('HTTP_REFERER'), 0);
    }
    if (isset($form['required'])) {
        $required = split(',', $form['required']);
        foreach ($required as $val) {
            $val = trim($val);
            $regex_field_name = $val . '_regex';
            if ((!isset($form[$val])) || (isset($form[$val]) && (strlen($form[$val]) < 1))) {
                $problem = false;
                if (isset($fieldname_lookup[$val])) {
                    $field = $fieldname_lookup[$val];
                } else {
                    $field = $val;
                }
                $errors[] = '0|Required value (<b>' . $field . '</b>) is missing.';
            } else if (isset($form[$regex_field_name])) {
                if (!eregi($form[$regex_field_name], $form[$val])) {
                    $problem = false;
                    $errors[] = '0|Required value (<b>' . $fieldname_lookup[$val] . '</b>) has an invalid format.';
                }
                $invis_array[] = $regex_field_name;
            }
        }
    }
    return $problem;
}


/****************************************************************
 * sort_fields() is responsable for sorting all fields in $form *
 * depending $form["sort"].                                     *
 * There are three main sort methods: alphabetic, reverse       *
 * alphabetic, and user supplied.                               *
 *                                                              *
 * The user supplied method is formatted "order:name,email,etc".*
 * The text "order" is required and the fields are comma        *
 * sepperated. ("order" is legacy from the PERL version.) If    *
 * the user supplied method leaves fields out of the comma      *
 * sepperated list, the remaining fields will be appended to    *
 * the end of the orderd list in the order they appear in the   *
 * form.                                                        *
 * Function added in 1.02.0                                     *
 ****************************************************************/

function sort_fields() {
    global $form;
    switch ($form['sort']) {
        case 'alphabetic':
        case 'alpha':
            ksort($form);
            break;
        case 'ralphabetic':
        case 'ralpha':
            krsort($form);
            break;
        default:
            if ($col = strpos($form['sort'], ':')) {
                $form['sort'] = substr($form['sort'], ($col + 1));
                $temp_sort_arr = explode(',', $form['sort']);
                for ($x = 0; $x < count($temp_sort_arr); $x++) {
                    $out[$temp_sort_arr[$x]] = $form[$temp_sort_arr[$x]];
                    unset($form[$temp_sort_arr[$x]]);
                }
                $form = array_merge($out, $form);
            }
    }
    return true;
}


/****************************************************************
 * alias_fields() creates a lookup array so we can use Aliases  *
 * for the field names.     If a alias is not available, the    *
 * lookup array is filled with the form field's name            *
 * Function added in 1.05.0                                     *
 ****************************************************************/

function alias_fields() {
    global $form, $fieldname_lookup;
    foreach ($form as $key => $val) {
        $fieldname_lookup[$key] = $key;
    }
    reset($form);
    if (isset($form['alias'])) {
        $aliases = explode(',', $form['alias']);
        foreach ($aliases as $val) {
            $temp = explode('=', $val);
            $fieldname_lookup[trim($temp[0])] = trim($temp[1]);
        }
    }
    return true;
}


/****************************************************************
 * send_mail() the function that parses the data into SMTP      *
 * format and sends the e-mail.                                 *
 ****************************************************************/

function send_mail() {
    global $form, $invis_array, $valid_env, $fieldname_lookup, $errors;

    $email_replace_array = "\r|\n|to:|cc:|bcc:";

    if (!isset($form['subject']))
        $form['subject'] = 'WWW Form Submission';
    if (isset($form['subject_prefix']))
        $form['subject'] = $form['subject_prefix'] . $form['subject'];
    if (!isset($form['email']))
        $form['email'] = 'email@example.com';

    switch ($form['mail_newline']) {
        case 2:
            $mail_newline = "\r";
            break;
        case 3:
            $mail_newline = "\r\n";
            break;
        default:
            $mail_newline = "\n";
    }

    if (isset($form['gmt_offset']) && ereg('^(\\-|\\+)?([0-9]{1}|(1{1}[0-2]{1}))$', $form['gmt_offset'])) {
        $mkseconds = mktime(gmdate('H') + $form['gmt_offset']);
        $mail_date = gmdate('F jS, Y', $mkseconds) . ' at ' . gmdate('h:iA', $mkseconds) . ' (GMT ' . $form['gmt_offset'] . ').';
    } else
        $mail_date = date('F jS, Y') . ' at ' . date('h:iA (T).');

    if (isset($form['realname']))
        $realname = eregi_replace($email_replace_array, '', $form['realname']);
    elseif (isset($form['firstname']) || isset($form['lastname']))
        $realname = eregi_replace($email_replace_array, '', trim($form['firstname'] . ' ' . $form['lastname']));

    $mailbody = 'Below is the result of your feedback form.  It was submitted by' . $mail_newline;
    if (isset($realname))
        $mailbody .= $realname . ' (' . $form['email'] . ') on ' . $mail_date . $mail_newline . $mail_newline;
    else
        $mailbody .= $form['email'] . ' on ' . $mail_date . $mail_newline . $mail_newline;

    reset($form);

    foreach ($form as $key => $val) {
        if ((!in_array($key, $invis_array)) && ((isset($form['print_blank_fields'])) || ($val))) {
            if (($form['alias_method'] == 'email') || ($form['alias_method'] == 'both'))
                $mailbody .= $fieldname_lookup[$key];
            else
                $mailbody .= $key;
            $mailbody .= ': ' . $val . $mail_newline;
        }
    }

    if (isset($form['env_report'])) {
        $temp_env_report = explode(',', $form['env_report']);
        $mailbody .= $mail_newline . $mail_newline . '-------- Env Report --------' . $mail_newline;
        foreach ($temp_env_report as $val) {
            if (in_array($val, $valid_env))
                $mailbody .= eregi_replace($email_replace_array, '', $val) . ': ' . eregi_replace($email_replace_array, '', getenv($val)) . $mail_newline;
        }
    }

    if (!isset($form['recipient']))
        $form['recipient'] = '';

    // Append lines to $mail_header that you wish to be
    // added to the headers of the e-mail. (SMTP Format
    // with newline char ending each line)

    $mail_header = 'Return-Path: ' . eregi_replace($email_replace_array, '', $return_path) . $mail_newline;
    if (FROM != null)
        $mail_header .= 'From: ' . FROM . $mail_newline;
    $mail_header .= 'Reply-to: ';
    if (isset($realname))
        $mail_header .= $realname . ' <' . eregi_replace($email_replace_array, '', $form['email']) . '>' . $mail_newline;
    else
        $mail_header .= eregi_replace($email_replace_array, '', $form['email']) . $mail_newline;
    if (isset($form['recipient_cc']))
        $mail_header .= 'Cc: ' . eregi_replace($email_replace_array, '', $form['recipient_cc']) . $mail_newline;
    if (isset($form['recipient_bcc']))
        $mail_header .= 'Bcc: ' . eregi_replace($email_replace_array, '', $form['recipient_bcc']) . $mail_newline;
    if (isset($form['priority']))
        $mail_header .= 'X-Priority: ' . ereg_replace($email_replace_array, '', $form['priority']) . $mail_newline;
    else
        $mail_header .= 'X-Priority: 3' . $mail_newline;
    $mail_header .= 'X-Mailer: PHPFormMail ' . VERSION . ' (http://www.boaddrink.com)' . $mail_newline;
    $mail_header .= 'X-Sender-IP: ' . eregi_replace($email_replace_array, '', getenv('REMOTE_ADDR')) . $mail_newline;
    $mail_header .= 'X-Referer: ' . eregi_replace($email_replace_array, '', getenv('HTTP_REFERER')) . $mail_newline;

    $form['subject'] = eregi_replace($email_replace_array, '', $form['subject']);

    if (eregi("MIME-|Content-|boundary", $mail_header . $mailbody . $form['subject']) == 0) {
        $mail_header .= 'Content-Type: text/plain; charset=utf-8' . $mail_newline;
        $mail_status = mail(eregi_replace($email_replace_array, '', $form['recipient']), $form['subject'], $mailbody, $mail_header);
        if (!$mail_status) {
            $errors[] = '1|Message could not be sent due to an error while trying to send the mail.';
            error_log('[PHPFormMail] Mail could not be sent due to an error while trying to send the mail.');
        } else {
            error_log('[PHPFormMail] Normal e-mail sent from IP ' . getenv('REMOTE_ADDR'));
        }
    } else {
        $mail_status = true;
        error_log('[PHPFormMail] Injection characters found from IP ' . getenv('REMOTE_ADDR') . '. Silently dropped');
    }
    return $mail_status;
}


/****************************************************************
 * output_html() is used to output all HTML to the browser.     *
 * This function is called if there is an error or for the      *
 * "Thank You" page if neither are declaired as redirects.      *
 *                                                              *
 * While called output_html() it actually outputs valid XHTML   *
 * 1.0 documents.                                               *
 * Function added in 1.02.0                                     *
 ****************************************************************/

function output_html($body) {
    global $form;

    $bgcolor = isset($form['bgcolor']) ? ('background-color: ' . htmlspecialchars($form['bgcolor']) . ';') : ('background-color: #FFF;');
    $background = isset($form['background']) ? ('background-image: url(' . htmlspecialchars($form['background']) . ');') : NULL;
    $text_color = isset($form['text_color']) ? ('color: ' . htmlspecialchars($form['text_color']) . ';') : ('color: #000;');
    $link_color = isset($form['link_color']) ? ('color: ' . htmlspecialchars($form['link_color']) . ';') : NULL;
    $alink_color = isset($form['alink_color']) ? ('color: ' . htmlspecialchars($form['alink_color']) . ';') : NULL;
    $vlink_color = isset($form['vlink_color']) ? ('color: ' . htmlspecialchars($form['vlink_color']) . ';') : NULL;

    print "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
    print "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en-US\" lang=\"en-US\">\n";
    print "<head>\n";
    print "  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n";
    print "  <meta name=\"robots\" content=\"noindex,nofollow\" />\n";
    print "  <title>" . htmlspecialchars($form['title']) . "</title>\n";
    print "  <style type=\"text/css\">\n";
    print "    BODY {" . trim($bgcolor . ' ' . $text_color . ' ' . $background) . "}\n";
    if (isset($link_color))
        print "    A {" . $link_color . "}\n";
    if (isset($alink_color))
        print "    A:active {" . $alink_color . "}\n";
    if (isset($vlink_color))
        print "    A:visited {" . $vlink_color . "}\n";
    print "    h1 {font-size: 14pt; font-weight: bold; margin-bottom: 20pt}\n";
    print "    .crit {font-size: 12pt; font-weight: bold; color: #F00; margin-bottom: 10pt;}\n";
    print "    .returnlink {font-size: 12pt; margin-top: 20pt; margin-bottom: 20pt;}\n";
    print "    .validbutton {margin-top: 20pt; margin-bottom: 20pt;}\n";
    print "  </style>\n";
    if (isset($form['css']))
        print "  <link rel=\"stylesheet\" href=\"" . htmlspecialchars($form['css']) . "\">\n";
    print "</head>\n\n";
    print "<body>\n";
    print "<!-- PHPFormMail from http://www.boaddrink.com -->\n";
    print $body;
    print "<div class=\"validbutton\"><a href=\"http://validator.w3.org/check/referer\" target=\"_blank\"><img src=\"http://www.w3.org/Icons/valid-xhtml10\" style=\"border:0;width:88px;height:31px\" alt=\"Valid XHTML 1.0!\" /></a></div>\n";
    print "</body>\n";
    print "</html>";
}


$form = decode_vars();

if (count($form) > 0) {

    // PFMA remove if block
    // Determine (based on the PHP version) if we should use the native
    // PHP4 in_array or the coded fake_in_array

    if (phpversion() >= '4.0.0')
        $in_array_func = 'in_array';
    else
        $in_array_func = 'fake_in_array';

    if ($use_field_alias = isset($form['alias']))
        alias_fields();

    if (CHECK_REFERER == true)
        check_referer($referers);
    else
        error_log('[PHPFormMail] HTTP_REFERER checking is turned off.  Referer: ' . getenv('HTTP_REFERER') . '; Client IP: ' . getenv('REMOTE_ADDR') . ';', 0);

    // This is used for another variable function call
    if ((count($recipient_array) > 0) == true)
        $recipient_function = 'map_recipients';
    else
        $recipient_function = 'check_recipients';

    if (isset($form['recipient']))
        $form['recipient'] = $recipient_function($form['recipient']);
    if (isset($form['recipient_cc']))
        $form['recipient_cc'] = $recipient_function($form['recipient_cc']);
    if (isset($form['recipient_bcc']))
        $form['recipient_bcc'] = $recipient_function($form['recipient_bcc']);

    check_required();

    if (!$errors) {

        if (isset($form['sort']))
            sort_fields();

        if (isset($form['hidden'])) {
            // PFMA REMOVE 1
            $form['hidden'] = str_replace(' ', '', $form['hidden']);
            $form['hidden'] = explode(',', $form['hidden']);
            // PFMA ADD $form['hidden'] = array_map('trim', $form['hidden']);
        }

        if (send_mail()) {
            if (isset($form['redirect'])) {
                if (isset($form['redirect_values']))
                    header('Location: ' . $form['redirect'] . '?' . getenv('QUERY_STRING') . "\r\n");
                else
                    header('Location: ' . $form['redirect'] . "\r\n");
            } else {
                if (!isset($form['title']))
                    $form['title'] = 'PHPFormMail - Form Results';
                $output = "<h1>The following information has been submitted:</h1>\n";
                reset($form);
                foreach ($form as $key => $val) {
                    if ((!$in_array_func($key, $invis_array)) && ((isset($form['print_blank_fields'])) || ($val))) {
                        $output .= '<div class="field"><b>';
                        if (($use_field_alias) && ($form['alias_method'] != 'email'))
                            $output .= htmlspecialchars($fieldname_lookup[$key]);
                        else
                            $output .= htmlspecialchars($key);
                        if ((isset($form['hidden'])) && ($in_array_func($key, $form['hidden'])))
                            $output .= ":</b> <i>(hidden)</i></div>\n";
                        else
                            $output .= ':</b> ' . nl2br(htmlspecialchars(stripslashes($val))) . "</div>\n";
                    }
                }
                if (isset($form['return_link_url']) && isset($form['return_link_title']))
                    $output .= '<div class="returnlink"><a href="' . $form["return_link_url"] . '">' . $form["return_link_title"] . "</a></div>\n";
                output_html($output);
            }
        }
    }
} else {
    $errors[] = '0|Nothing was sent by a form. (No data was sent by POST or GET method.)  There is nothing to process here.';
    error_log('[PHPFormMail] No data sent by POST or GET method. (' . getenv('HTTP_REFERER') . ')', 0);
}

if (count($errors) > 0) {
    error();
}
