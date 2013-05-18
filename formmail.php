<?PHP

#############################################################################
#                                                        PHPFormMail v0.03b #
# PHPFormMail - Something we've allways had...                              #
# Copyright (c) 1999 Andrew Riley (boad@boaddrink.com)                      #
#                                                                           #
# This program is free software; you can redistribute it and/or             #
# modify it under the terms of the GNU General Public License               #
# as published by the Free Software Foundation; either version 2            #
# of the License, or (at your option) any later version.                    #
#                                                                           #
# This program is distributed in the hope that it will be useful,           #
# but WITHOUT ANY WARRANTY; without even the implied warranty of            #
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             #
# GNU General Public License for more details.                              #
#                                                                           #
# You should have received a copy of the GNU General Public License         #
# along with this program; if not, write to the Free Software               #
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307,    #
# USA.                                                                      #
#                                                                           #
#############################################################################
#                                                                           #
# If you run into any problems, pleas read the readme_formmail.txt.         #
# If that does not help, check out http://www.boaddrink.com.                #
#                                                                           #
# For more info, please visit http://www.boaddrink.com or read the readme   #
# file included.                                                            #
#############################################################################

# $referers allows forms to be located only on servers which are defined     #
# in this field.                                                             #

$referers = array("boaddrink.com","www.boaddrink.com");

#############################################################################

$errors = array();
$invis_array = array("recipient","subject","required","redirect",
                     "print_blank_fields","env_report",
                     "missing_fields_redirect","title","bgcolor",
                     "text_color","link_color","alink_color",
                     "vlink_color","background","subject","title",
                     "link","css","return_link_title",
                     "return_link_url");

function in_array($needle,$haystack){                   //remove for PHP4.0... it"s not in 3
  $found = false;
  while (list($key,$val) = each ($haystack)){
    if ($needle == $val){
      $found = true;
    }
  }
  return $found;
}

function fill_data(&$from,$to,$tag=""){
  $temp = $from;
  if(!$from){
    $temp = $to;
  }
  if($tag){
    $temp = " ".$tag."=\"".$temp."\"";
  }
  $from = $temp;
}

function check_referer($referers){
  if (count($referers)){
    global $errors;
    $found = false;
    $temp = explode("/",getenv("HTTP_REFERER"));
    $referer = $temp[2];
    for ($x=0; $x < count($referers); $x++){
      if ($referers[$x] == $referer){
        $found = true;
      }
    }
    if (!$found){
      $errors[] = "You are comming from an unauthorized domain.";
      error_log("[PHPFormMail] Illegal Referer. (".getenv("HTTP_REFERER").")", 0); 
    }
    return $found;
  } else {
    return true; //Not a good idea, if empty, it will allow it.
  }
}

function decode_vars(){
  global $HTTP_POST_VARS;
  global $HTTP_GET_VARS;
  if (getenv("REQUEST_METHOD") == "GET"){
    $a = $HTTP_GET_VARS;
  } else {
    $a = $HTTP_POST_VARS;
  }
  return $a;
}

function strip_bad_stuff($data){
  while(list($key,$val) = each ($data)){
    if ($val){
      $temp = $val;
      $data[$key] = eregi_replace("<!--(.|\n)*-->","[SSI REMOVED]",$val);
      if ($temp != $data[$key]){
        error_log("[PHPFormMail] SSI Found and Removed. (".getenv("REMOTE_ADDR").")", 0); 
      }
    }
  }
  return $data;
}

function error($errors){
  global $form;
  if ($errors){
    if ($form["missing_fields_redirect"]){
      Header(  "Location: ".$form["missing_fields_redirect"]);
    } else {
      print "<HTML>\n<HEAD>\n";
      print "<TITLE>Error</TITLE>\n";
      if($form["css"]){
        print "<link rel=\"stylesheet\" href=\"".$form["css"]."\">\n";
      }
      print "</HEAD>\n\n";
      print "<BODY".$form["bgcolor"].$form["text_color"].$form["link_color"].$form["alink_color"].$form["vlink_color"].$form["background"]."\">\n";
      print "<H2>The following errors were found:</H2><BR>\n";
      while (list($key,$val) = each ($errors)) {
        print $val . "<BR>\n";
      }
      print "<BR>Please use the <a href=\"javascript: history.back();\">back</a> button to correct these errors.<BR>\n";
      print "</BODY>\n</HTML>";
    }
  }
}

function check_required($form){
  global $errors;
  $problem = true;
  if (!$form["recipient"]) {
    $problem = false;
    $errors[] = "There is no recipient to send this mail to.";
  }
  if ($form["required"]){
    $splitstring = split(",",$form["required"]);
    for($x=0; $x < count($splitstring); $x++){
      if (!$form[$splitstring[$x]]) {
        $problem = false;
        $errors[] = "Required value (<b>" . $splitstring[$x] . "</b>) is missing.";
      }
    }
  }
  return $problem;
}

function send_mail($form,$invis_array){
  $toarray = explode(",",$form["recipient"]);
  $mailbody = "Below is the result of your feedback form.  It was submitted by\n";
  $mailbody.= $form["realname"]. " (" .$form["email"].") on " . date("F dS, Y") ."\n\n";

     while (list($key,$val) = each ($form)){
       if (!in_array($key,$invis_array)){
         if (($form["print_blank_fields"]) || ($val)){
           $mailbody .= $key.": ". stripslashes($val) ."\n";
         }
       }
     }
     
     if ($form["env_report"]){
       $mailbody .= "\n\n-------- Env Report --------\n";
       $mailbody .= "REMOTE_HOST: ".getenv("REMOTE_HOST")."\n";
       $mailbody .= "REMOTE_ADDR: ".getenv("REMOTE_ADDR")."\n";
       $mailbody .= "REMOTE_USER: ".getenv("REMOTE_USER")."\n";
       $mailbody .= "HTTP_USER_AGENT: ".getenv("HTTP_USER_AGENT")."\n";
     }

    for ($tobla = 0; $tobla < count($toarray); $tobla++){
      mail($toarray[$tobla],$form["subject"],$mailbody,"From: " .$form["email"]. " (" .$form["realname"]. ")\nX-Mailer: PHPFormMail (http://www.boaddrink.com)");
    }
}

$form = strip_bad_stuff(decode_vars());
fill_data($form["bgcolor"],"#FFFFFF","bgcolor");
fill_data($form["text_color"],"#000000","text");
fill_data($form["link_color"],"#0000FF","link");
fill_data($form["alink_color"],"#FF0000","alink");
fill_data($form["vlink_color"],"#000099","vlink");
fill_data($form["background"],"","background");

if(check_referer($referers) && check_required($form)){
  fill_data($form["subject"],"WWW Form Submission");
  fill_data($form["email"],"formmail@boaddrink.com");
  fill_data($form["realname"],"Unknown Stranger");
  fill_data($form["title"],"Form Results");
    
  send_mail($form,$invis_array);
    
  if ($form["redirect"]){
    Header("Location: ".$form["redirect"]);
  } else {
    print "<HTML>\n<HEAD>\n";
    print "<TITLE>".$form["title"]."</TITLE>\n";
    if($form["css"]){
      print "<link rel=\"stylesheet\" href=\"".$form["css"]."\">\n";
    }
    print "</HEAD>\n\n";
    print "<BODY".$form["bgcolor"].$form["text_color"].$form["link_color"].$form["alink_color"].$form["vlink_color"].$form["background"]."\">\n";
    print "<H2>The following information has been submitted:</H2><BR>\n";
    while (list($key,$val) = each ($form)) {
      if (!in_array($key,$invis_array)){
        print "<b>".$key .":</b> ".$val . "<BR>\n";
      }
    }
    if($form["return_link_url"] && $form["return_link_title"]){
      print "<BR><a href=\"".$form["return_link_url"]."\">".$form["return_link_title"]."</a>\n";
    }
    print "</BODY>\n</HTML>";
  }
}

error($errors);
?>
