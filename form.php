<?php

define('THIS_SCRIPT', "form.php");
define('IN_MYBB', 1);
require "./global.php";
require_once "./inc/class_formcreator.php";
require_once "./inc/class_captcha.php";
require_once MYBB_ROOT . "inc/datahandlers/pm.php";
require_once MYBB_ROOT . "inc/datahandlers/post.php";

$lang->load("formcreator");

$formcreator = new formcreator();

if ($formcreator->get_form($mybb->input['formid'])) {

    if ($formcreator->check_allowed() && $formcreator->active == 1) {
        add_breadcrumb($formcreator->name, "form.php?formid=" . $formcreator->formid);
        $display = true;

        if ($formcreator->width) {
            $stylewidth = "width:" . $formcreator->width . ";";
        }

        if ($formcreator->labelwidth) {
            $stylelabelwidth = "width:" . $formcreator->labelwidth . ";";
        }

        if ($formcreator->class) {
            $styleclass = $formcreator->class;
        }

        $formcreator->get_fields();

        if ($mybb->request_method == "post") {
            $error_array = array();

            foreach ($formcreator->fields as $field) {
                $field->default = $mybb->input["field_" . $field->fieldid];

                if ($field->required && empty($mybb->input["field_" . $field->fieldid])) {
                    $error_array[] = "'" . $field->name . "' ".$lang->fc_is_empty;
                }

                if ($field->regex && !preg_match("/" . $field->regex . "/", $mybb->input["field_" . $field->fieldid])) {
                    if(!empty($field->regexerror)){
                        $error_array[] = $field->regexerror;
                    }else{
                        $error_array[] = "'" . $field->name . "' ". $lang->fc_no_match_regex;
                    }
                    
                }

                if ($field->type == 12) {
                    $captcha = new captcha();
                    if ($captcha->validate_captcha() == false) {
                        // CAPTCHA validation failed
                        foreach ($captcha->get_errors() as $error) {
                            $error_array[] = $error;
                        }
                    }
                }
            }

            if (count($error_array) != 0) {
                $errors = inline_error($error_array);

            } else {
                $display = false;

                $subject = $formcreator->parse_subject();
                $message = $formcreator->parse_output();
                
                $uid = $mybb->user['uid'];
                $username = $mybb->user['username'];
                
                if($mybb->user['usergroup'] == 1 || empty($username)){
                    $username = "Guest";
                }

                if (!empty($formcreator->uid)) {
                    if ($user = get_user($formcreator->uid)) {
                        $uid = $user['uid'];
                        $username = $user['username'];
                    } elseif ($formcreator->uid == -1) {
                        $uid = -1;
                        $username = "Form Creator Bot";
                    }
                }

                // Send PM single user
                if ($formcreator->pmusers) {
                    $users = explode(",", $formcreator->pmusers);

                    foreach ($users as $user) {
                        if ($user_data = get_user($user)) {
                            $pmhandler = new PMDataHandler();
                            $pmhandler->admin_override = true;

                            $pm = array(
                                "subject" => $subject,
                                "message" => $message,
                                "icon" => "-1",
                                "toid" => $user_data['uid'],
                                "fromid" => $uid,
                                "do" => '',
                                "pmid" => '');
                            $pm['options'] = array(
                                "signature" => "0",
                                "disablesmilies" => "0",
                                "savecopy" => "0",
                                "readreceipt" => "0",
                                "allow_html" => 1);

                            $pmhandler->set_data($pm);
                            if ($pmhandler->validate_pm()) {
                                $pmhandler->insert_pm();
                            }
                        }
                    }
                }

                // Send PM groups
                if (count($formcreator->pmgroups) != 0 and !empty($formcreator->pmgroups[0])) {
                    $group_members = get_usergroup_users($formcreator->pmgroups);

                    foreach ($group_members as $user) {
                        $pmhandler = new PMDataHandler();
                        $pmhandler->admin_override = true;

                        $pm = array(
                            "subject" => $subject,
                            "message" => $message,
                            "icon" => "-1",
                            "toid" => $user['uid'],
                            "fromid" => $uid,
                            "do" => '',
                            "pmid" => '');
                        $pm['options'] = array(
                            "signature" => "0",
                            "disablesmilies" => "0",
                            "savecopy" => "0",
                            "readreceipt" => "0",
                            "allow_html" => 1);

                        $pmhandler->set_data($pm);
                        if ($pmhandler->validate_pm()) {
                            $pmhandler->insert_pm();
                        }
                    }
                }

                // Mail content
                /*
                if ($formcreator->mail) {
                if ($mybb->user['uid']) {
                $mybb->input['fromemail'] = $mybb->user['email'];
                $mybb->input['fromname'] = $mybb->user['username'];
                } else {
                $mybb->input['fromemail'] = $mybb->user['email'];
                //$mybb->input['fromname'] = $mybb->settings[''];
                }

                if ($mybb->settings['mail_handler'] == 'smtp') {
                $from = $mybb->input['fromemail'];
                } else {
                $from = "{$mybb->input['fromname']} <{$mybb->input['fromemail']}>";
                }

                $message = $lang->sprintf($lang->email_emailuser, $to_user['username'], $mybb->input['fromname'], $mybb->settings['bbname'], $mybb->settings['bburl'],
                $mybb->get_input('message'));
                my_mail($to_user['email'], $mybb->get_input('subject'), $message, $from, "", "", false, "text", "", $mybb->input['fromemail']);

                if ($mybb->settings['mail_logging'] > 0) {
                // Log the message
                $log_entry = array(
                "subject" => $db->escape_string($mybb->get_input('subject')),
                "message" => $db->escape_string($mybb->get_input('message')),
                "dateline" => TIME_NOW,
                "fromuid" => $mybb->user['uid'],
                "fromemail" => $db->escape_string($mybb->input['fromemail']),
                "touid" => $to_user['uid'],
                "toemail" => $db->escape_string($to_user['email']),
                "tid" => 0,
                "ipaddress" => $db->escape_binary($session->packedip),
                "type" => 1);
                $db->insert_query("maillogs", $log_entry);
                }
                }
                */

                // Post in Thread
                if ($formcreator->tid) {
                    if ($thread = get_thread($formcreator->tid)) {
                        $posthandler = new PostDataHandler();
                        $posthandler->action = "post";
                        $posthandler->admin_override = true;

                        $new_post = array(
                            "fid" => $thread['fid'],
                            "tid" => $thread['tid'],
                            "subject" => $subject,
                            "uid" => $uid,
                            "username" => $username,
                            "message" => $message,
                            "ipaddress" => $session->packedip,
                            "posthash" => "");

                        // Set up the thread options
                        $new_post['options'] = array(
                            "signature" => 'yes',
                            "emailnotify" => 'no',
                            "disablesmilies" => 'no');

                        $posthandler->set_data($new_post);

                        if ($posthandler->validate_post()) {
                            $post_info = $posthandler->insert_post();
                            $pid = $post_info['pid'];
                            
                            $post = get_post($pid);

                            $forumpermissions = forum_permissions($thread['fid']);

                            if ($forumpermissions['canviewthreads'] == 1 && $post['visible'] == 1) {
                                $url = get_post_link($pid, $thread['tid']);
                            }
                        }
                    }
                }

                // Thread in Forum
                if ($formcreator->fid) {
                    if ($forum = get_forum($formcreator->fid)) {
                        $posthandler = new PostDataHandler();
                        $posthandler->action = "thread";
                        $posthandler->admin_override = true;

                        $new_thread = array(
                            "fid" => $forum['fid'],
                            "subject" => $subject,
                            "prefix" => $formcreator->prefix,
                            "icon" => -1,
                            "uid" => $uid,
                            "username" => $username,
                            "message" => $message,
                            "ipaddress" => $session->packedip,
                            "posthash" => "");

                        // Set up the thread options
                        $new_thread['options'] = array(
                            "signature" => 'yes',
                            "emailnotify" => 'no',
                            "disablesmilies" => 'no');

                        $posthandler->set_data($new_thread);

                        if ($posthandler->validate_thread()) {
                            $thread_info = $posthandler->insert_thread();
                            $tid = $thread_info['tid'];
                            
                            $thread = get_thread($tid);
                            $post = get_post($thread['firstpost']);

                            $forumpermissions = forum_permissions($forum['fid']);

                            if ($forumpermissions['canviewthreads'] == 1 && $post['visible'] == 1) {
                                $url = get_thread_link($tid);
                            }
                        }
                    }
                }

                if ($url) {
                    redirect($url, $lang->fc_submitted, "", false);
                } else {
                    redirect($mybb->settings['bburl'], $lang->fc_submitted, "", false);
                }

            }
        }

        if ($display && count($formcreator->fields) != 0) {
            $formtitle = $formcreator->name;

            $formcontent = $formcreator->build_form();
        } elseif (count($formcreator->fields) == 0) {
            $formtitle = $formcreator->name;

            $formcontent = '<tr><td class="trow1" colspan="2">'.$lang->fc_no_fields.'</td></tr>';
        }

    } elseif ($formcreator->active == 0) {
        add_breadcrumb($formcreator->name, "form.php?formid=" . $formcreator->formid);

        $formtitle = "Form disabled";

        $formcontent = '<tr><td class="trow1" colspan="2">'.$lang->fc_form_disabled.'</td></tr>';
    } else {
        add_breadcrumb($formcreator->name, "form.php?formid=" . $formcreator->formid);

        $formtitle = "Access Denied";

        $formcontent = '<tr><td class="trow1" colspan="2">'.$lang->fc_form_no_permissions.'</td></tr>';
    }
} else {
    add_breadcrumb($lang->formcreator, "form.php");

    $formtitle = $lang->formcreator;
    $formcontent = '<tr><td class="trow1" colspan="2">'.$lang->fc_no_form_found.'</td></tr>';
}

eval("\$form = \"" . $templates->get("formcreator_container") . "\";");

eval("\$html = \"" . $templates->get("formcreator") . "\";");

output_page($html);

?>