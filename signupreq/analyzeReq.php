<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/user/filters/lib.php');
require_once('analyzeReqFunc.php');
require_once('debug.php');
require_once('./lang/'. $CFG->lang . '/auth_signupreq.php');

//require_once($CFG->dirroot . '/user/lib.php');
//require_once($CFG->libdir . '/authlib.php');
//require_once($CFG->libdir . '/adminlib.php');

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', '', PARAM_ALPHANUM);   //md5 confirmation hash
$confirmuser = optional_param('confirmuser', 0, PARAM_INT);
$sort = optional_param('sort', 'name', PARAM_ALPHANUM);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 30, PARAM_INT);        // how many per page
$ru = optional_param('ru', '2', PARAM_INT);            // show remote users
$lu = optional_param('lu', '2', PARAM_INT);            // show local users
$clIds = optional_param('id', array(), PARAM_INT); // checklist - marked ids
$clSubType = optional_param('submit', '', PARAM_ALPHANUM); // checklist - submission type

$title =  'Analyze Requests';
$url = new moodle_url("/auth/signupreq/analyzeReq.php");
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('administrationsite'), new moodle_url('/admin/search.php'));
$PAGE->navbar->add(get_string('users'));
$PAGE->navbar->add(get_string('accounts', 'auth_signupreq'));
$PAGE->navbar->add($title, new moodle_url('/auth/signupreq/analyzeReq.php'));


$sitecontext = context_system::instance();
$site = get_site();

if (!has_capability('moodle/user:update', $sitecontext) and !has_capability('moodle/user:delete', $sitecontext)) {
    print_error('nopermissions', 'error', '', 'edit/delete users');
}

$stredit = get_string('edit');
$strdelete = get_string('delete');
$strdeletecheck = get_string('deletecheck');
$strshowallusers = get_string('showallusers');
$strconfirm = get_string('confirm');

if (empty($CFG->loginhttps)) {
    $securewwwroot = $CFG->wwwroot;
} else {
    $securewwwroot = str_replace('http:', 'https:', $CFG->wwwroot);
}

$returnurl = new moodle_url('/auth/signupreq/analyzeReq.php', array('sort' => $sort, 'dir' => $dir, 'perpage' => $perpage, 'page' => $page));

// The $user variable is also used outside of these if statements.
$user = null;
if (!empty($clIds) and confirm_sesskey()) {
    $error = array();
    if(!strcmp($clSubType, $strconfirm))
        foreach($clIds as $confirmuser) confirmUser($sitecontext, $DB, $CFG, $confirmuser);
    else if(!strcmp($clSubType, $strdelete)) {
        foreach ($clIds as $delete) {
            $user = $DB->get_record('user', array('id' => $delete, 'mnethostid' => $CFG->mnet_localhost_id), '*', MUST_EXIST);
            if (!$user->deleted and !is_siteadmin($user->id))
                delete_user($user);
        }
    }
    redirect($returnurl);
} else if ($confirmuser and confirm_sesskey()) {
        $result = confirmUser($sitecontext, $DB, $CFG, $confirmuser);
        if ($result == AUTH_CONFIRM_OK or $result == AUTH_CONFIRM_ALREADY) {
            redirect($returnurl);
        } else {
            echo $OUTPUT->header();
            redirect($returnurl, get_string('usernotconfirmed', '', fullname($user, true)));
        }
} else if ($delete and confirm_sesskey())              // Delete a selected user, after confirmation
    deleteUser($sitecontext, $DB, $OUTPUT, $CFG, $delete, $returnurl, $confirm);

// create the user filter form
$ufiltering = new user_filtering();
echo $OUTPUT->header();

//ANALYZE CUSTOM FIELDS
$sCategoryField = 'categoria';
$sEmail = 'email';
$categoryFields = array();
$MatField;
$SEField;

// Add extra columns data, witch are not in user's main table
if ($categories = $DB->get_records('user_info_category', null, 'sortorder ASC')) {
    foreach ($categories as $category) {
        if ($fields = $DB->get_records('user_info_field', array('categoryid' => $category->id), 'sortorder ASC')) {
            foreach ($fields as $field) {
                if ($category->name == "Matrícula")
                    $MatField = $field; //happens just once
                else if ($category->name == "Seção de Ensino")
                    $SEField = $field; //again, happens just once
                else if ($category->id == 1) // Categorias
                    array_push($categoryFields, $field);
            }
        }
    }
    $MatField->oName = 'matricula';
    $SEField->oName = 'secaodeensino';
}
$extraFields = array($MatField, $SEField);
foreach ($extraFields as $field) $sExtraFields[] = $field->oName;
//ANALYZE CUSTOM FIELDS - END

// Carry on with the user listing
$context = context_system::instance();
// Get all user name fields as an array.
$allusernamefields = get_all_user_name_fields(false, null, null, null, true);
$columns = array_merge($allusernamefields, $sExtraFields, array($sEmail, $sCategoryField));

foreach ($columns as $key => $column) {
    if (!is_int($key)) $string[$column] = get_user_field_name($column);
    if ($sort != $column) {
        $columnicon = "";
        $columndir = "ASC";
    } else {
        $columndir = $dir == "ASC" ? "DESC" : "ASC";
        $columnicon = ($dir == "ASC") ? "sort_asc" : "sort_desc";
        $columnicon = $OUTPUT->pix_icon('t/' . $columnicon, get_string(strtolower($columndir)), 'core',
            ['class' => 'iconsort']);

    }
    $$column = "<a href=\"analyzeReq.php?sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
}

// We need to check that alternativefullnameformat is not set to '' or language.
// We don't need to check the fullnamedisplay setting here as the fullname function call further down has
// the override parameter set to true.
$fullnamesetting = $CFG->alternativefullnameformat;
// If we are using language or it is empty, then retrieve the default user names of just 'firstname' and 'lastname'.
if ($fullnamesetting == 'language' || empty($fullnamesetting)) {
    // Set $a variables to return 'firstname' and 'lastname'.
    $a = new stdClass();
    $a->firstname = 'firstname';
    $a->lastname = 'lastname';
    // Getting the fullname display will ensure that the order in the language file is maintained.
    $fullnamesetting = get_string('fullnamedisplay', null, $a);
}

// Order in string will ensure that the name columns are in the correct order.
$usernames = order_in_string($allusernamefields, $fullnamesetting);
$fullnamedisplay = array();
foreach ($usernames as $name) {
    // Use the link from $$column for sorting on the user's name.
    $fullnamedisplay[] = ${$name};
}
// All of the names are in one column. Put them into a string and separate them with a /.
$fullnamedisplay = implode(' / ', $fullnamedisplay);
// If $sort = name then it is the default for the setting and we should use the first name to sort by.
if ($sort == "name") {
    // Use the first item in the array.
    $sort = reset($usernames);
}

list($extrasql, $params) = $ufiltering->get_sql_filter();
if (strlen($extrasql)) $extrasql .= " AND confirmed=0 ";
else $extrasql = " confirmed=0 ";
$users = get_users_listing('', $dir, 0, '', '', '', '',
    $extrasql, $params, $context);
//$users = get_users_listing('', $dir, $page*$perpage, $perpage, '', '', ''
addExtraFields($users, $extraFields, $categoryFields, $sCategoryField, $sort, $dir);
$users = array_slice($users, $page * $perpage, ($page + 1) * $perpage);

$usercount = get_users(false);
$usersearchcount = get_users(false, '', false, null, "", '', '', '', '', '*', $extrasql, $params);

if ($extrasql !== '') {
    echo $OUTPUT->heading("$usersearchcount / $usercount " . get_string('users'));
    $usercount = $usersearchcount;
} else {
    echo $OUTPUT->heading("$usercount " . get_string('users'));
}

$strall = get_string('all');

$baseurl = new moodle_url('/auth/signupreq/analyzeReq.php', array('sort' => $sort, 'dir' => $dir, 'perpage' => $perpage));
echo $OUTPUT->paging_bar($usercount, $page, $perpage, $baseurl);

flush();
if (!$users) {
    $match = array();
    echo $OUTPUT->heading(get_string('nousersfound'));

    $table = NULL;

} else {

    if (empty($mnethosts)) {
        $mnethosts = $DB->get_records('mnet_host', null, 'id', 'id,wwwroot,name');
    }
    //echo "<script src=\"scripts.js\">";
    echo "<script type=\"text/javascript\" src=\"scripts.js\"></script>";
    $table = new html_table();
    $table->head = array();
    $table->colclasses = array();
    $table->head[] = "<input type=\"checkbox\" onclick=\"" . "checkAll(this)" . "\">";
    $table->head[] = $fullnamedisplay;
    $table->attributes['class'] = 'admintable generaltable';
    //$table->head[] = $city;
    //$table->head[] = $country;
    $table->head[] = ${$sEmail};
    foreach ($sExtraFields as $name)
        $table->head[] = ${$name};
    $table->head[] = ${$secaodeensino};
    $table->head[] = get_string('edit');
    $table->colclasses[] = 'centeralign';
    $table->head[] = "";
    $table->colclasses[] = 'centeralign';

    $table->id = "users";
    $sKey = sesskey();
    foreach ($users as $user) {
        $buttons = array();

        // delete button
        if (has_capability('moodle/user:delete', $sitecontext)) {
            if (is_mnet_remote_user($user) or $user->id == $USER->id or is_siteadmin($user)) {
                // no deleting of self, mnet accounts or admins allowed
            } else {
                $url = new moodle_url($returnurl, array('delete' => $user->id, 'sesskey' => $sKey));
                $buttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/delete', $strdelete));
            }
        }

        if (has_capability('moodle/user:update', $sitecontext)) {
            // edit button
            // prevent editing of admins by non-admins
            if (is_siteadmin($USER) or !is_siteadmin($user)) {
                $url = new moodle_url($securewwwroot . '/user/editadvanced.php', array('id' => $user->id, 'course' => $site->id));
                $buttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/edit', $stredit));
            }

            // confirm user button
            $url = new moodle_url($returnurl, array('confirmuser' => $user->id, 'sesskey' => $sKey));
            $buttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/approve', $strconfirm));
        }

        $fullname = fullname($user, true);
        $row = array();
        $row[] = "<input type=\"checkbox\" name=\"id[]\" value=\"$user->id\">";
        $row[] = "<a href=\"../../user/view.php?id=$user->id&amp;course=$site->id\">$fullname</a>";
        $row[] = $user->email;

        foreach ($extraFields as $field)
                $row[] = $user->{$field->oName};


        $row[] = $user->categoria;
        $row[] = implode(' ', $buttons);
        $table->data[] = $row;
    }
}

// add filters
$ufiltering->display_add();
$ufiltering->display_active();

if (!empty($table)) {
    echo html_writer::start_tag('div', array('class' => 'no-overflow'));
    echo "<form action=\"\" method=\"post\">";
    echo html_writer::table($table);
    echo "<input type=\"hidden\" name=\"sesskey\" value=\"$sKey\">";
    echo "<input type=\"submit\" name=\"submit\" value=\"$strdelete\">";
    echo "<input type=\"submit\" name=\"submit\" value=\"$strconfirm\">";
    echo "</form>";
    echo html_writer::end_tag('div');
    echo $OUTPUT->paging_bar($usercount, $page, $perpage, $baseurl);
}
echo $OUTPUT->footer();
