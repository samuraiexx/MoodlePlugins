<?php
/**
 * Confirm the new user as registered.
 *
 * @param user $user
 * @return auth_confirm_code*/
require_once('debug.php');
require_once('mailFunc.php');

require_once($CFG->libdir . '/authlib.php');

function user_confirm($user) {
    global $DB;

    if (!empty($user)) {
        if ($user->auth != 'signupreq') return AUTH_CONFIRM_ERROR;
        else if ($user->confirmed) return AUTH_CONFIRM_ALREADY;
        else {
            try {
                $DB->set_field("user", "confirmed", 1, array("id" => $user->id));
                return AUTH_CONFIRM_OK;
            } catch (Exception $e){
                return AUTH_CONFIRM_ERROR;
            }
        }
    }
    return AUTH_CONFIRM_ERROR;
}


function addExtraFields(&$users, $extraFields, $categoryFields, $sCategoryField, $sort, $dir)
{
    foreach($users as &$user){ // Adds extra fields in the user variable
        global $CFG;
        foreach($extraFields as $field) {
            require_once($CFG->dirroot . '/user/profile/field/' . $field->datatype . '/field.class.php');
            $newfield = 'profile_field_' . $field->datatype;
            $formfield = new $newfield($field->id, $user->id);
            $user->{$field->oName} = $formfield->data;
        }
        $category = " ";
        foreach($categoryFields as $field) {
            require_once($CFG->dirroot . '/user/profile/field/' . $field->datatype . '/field.class.php');
            $newfield = 'profile_field_' . $field->datatype;
            $formfield = new $newfield($field->id, $user->id);
            if($formfield->data == 1){
                $category = $field->name;
                break;
            }
            $user->{$sCategoryField} = $category;
        }
    }

    usort($users, function($u1, $u2) use ($sort, $dir) {
        $a = $u1->{$sort};
        $b = $u2->{$sort};
        if ($a == $b) $ret = 0;
        else $ret = ($a < $b) ? -1 : 1;
        return strcmp($dir, 'ASC') ? $ret : -$ret;
    });
}

function confirmUser(&$sitecontext, &$DB, &$CFG, &$confirmuser){
    $user = null;
    try {
        require_capability('moodle/user:update', $sitecontext);
        if (!$user = $DB->get_record('user', array('id' => $confirmuser, 'mnethostid' => $CFG->mnet_localhost_id))) {
            print_error('nousers');
        }
    } catch(Exception $e){
        return AUTH_CONFIRM_ERROR;
    }

    $status = user_confirm($user);
    if($status == AUTH_CONFIRM_OK)
        send_mail($user, 'confirm');

    return $status;
}


function deleteUser(&$sitecontext, &$DB, &$OUTPUT, &$CFG, &$delete, &$returnurl, &$confirm)
{
    global $DB;
    require_capability('moodle/user:delete', $sitecontext);

    $user = $DB->get_record('user', array('id' => $delete, 'mnethostid' => $CFG->mnet_localhost_id), '*', MUST_EXIST);

    if ($user->deleted) {
        print_error('usernotdeleteddeleted', 'error');
    }
    if (is_siteadmin($user->id)) {
        print_error('useradminodelete', 'error');
    }

    if ($confirm != md5($delete)) {
        echo $OUTPUT->header();
        $fullname = fullname($user, true);
        echo $OUTPUT->heading(get_string('deleteuser', 'admin'));

        $optionsyes = array('delete' => $delete, 'confirm' => md5($delete), 'sesskey' => sesskey());
        $deleteurl = new moodle_url($returnurl, $optionsyes);
        $deletebutton = new single_button($deleteurl, get_string('delete'), 'post');

        echo $OUTPUT->confirm(get_string('deletecheckfull', '', "'$fullname'"), $deletebutton, $returnurl);
        echo $OUTPUT->footer();
        die;
    } else if (data_submitted()) {
        send_mail($user,'reject');
        if (delete_user($user)) {
            \core\session\manager::gc(); // Remove stale sessions.
            redirect($returnurl);
        } else {
            \core\session\manager::gc(); // Remove stale sessions.
            echo $OUTPUT->header();
            echo $OUTPUT->notification($returnurl, get_string('deletednot', '', fullname($user, true)));
        }
    }
}


