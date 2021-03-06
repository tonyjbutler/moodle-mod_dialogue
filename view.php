<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('lib.php');
require_once('locallib.php');

$id         = required_param('id', PARAM_INT);
$state      = optional_param('state', null, PARAM_ALPHA);
$show       = optional_param('show', null, PARAM_ALPHA);
$page       = optional_param('page', 0, PARAM_INT);
$sort       = optional_param('sort', 'latest', PARAM_ALPHANUM);
$direction  = optional_param('direction', 'asc', PARAM_ALPHA);

if ($id) {
    if (! $cm = get_coursemodule_from_id('dialogue', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $activityrecord = $DB->get_record('dialogue', array('id' => $cm->instance))) {
        print_error('invalidid', 'dialogue');
    }
    if (! $course = $DB->get_record('course', array('id' => $activityrecord->course))) {
        print_error('coursemisconf');
    }
} else {
    print_error('missingparameter');
}

$context = context_module::instance($cm->id);

require_login($course, false, $cm);

// use cached params for toggle button groups
$state      = dialogue_get_cached_param('state', $state, dialogue::STATE_OPEN);
$show       = dialogue_get_cached_param('show', $show, dialogue::SHOW_MINE);

// now set params on pageurl will later be set on $PAGE
$pageparams = array('id' => $cm->id, 'state' => $state, 'show' => $show, 'page' => $page, 'sort' => $sort);
$pageurl    = new moodle_url('/mod/dialogue/view.php', $pageparams);

$PAGE->set_pagetype('mod-dialogue-view-index');
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($activityrecord->name));
$PAGE->set_heading(format_string($course->fullname));

// check if needs to be upgraded
if (dialogue_cm_needs_upgrade($cm->id)) {
    $link = new moodle_url('/course/view.php', array('id' => $COURSE->id));
    notice(get_string('upgrademessage', 'dialogue'), $link);
    exit;
}

dialogue_load_bootstrap_js();// load javascript if not bootstrap theme

$PAGE->requires->yui_module('moodle-mod_dialogue-clickredirector',
                            'M.mod_dialogue.clickredirector.init', array($cm->id));

$dialogue = new dialogue($cm, $course, $activityrecord);
$conversationlist = new dialogue_conversations($dialogue, $state);
if ($show == dialogue::SHOW_EVERYONE) {
    $conversationlist->set_view_any();
}
$activegroup = groups_get_activity_group($cm, true);
if ($activegroup) {
    $conversationlist->set_group($activegroup);
}
$conversationlist->set_order($sort, $direction);
$total = $conversationlist->matches();
$rs = $conversationlist->fetch_page($page);
$pagination = new paging_bar($total, $page, dialogue::PAGINATION_PAGE_SIZE, $pageurl);

$renderer = $PAGE->get_renderer('mod_dialogue');

echo $OUTPUT->header();
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}

$groupmode = groups_get_activity_groupmode($cm);
if ($groupmode == SEPARATEGROUPS or $groupmode == VISIBLEGROUPS) {
    echo $OUTPUT->notification(get_string('groupmodenotifymessage', 'dialogue'), 'notifymessage');
}
$groupsurl = clone($pageurl);
$groupsurl->remove_params('page'); // clear page
echo groups_print_activity_menu($cm, $groupsurl, true);
echo html_writer::empty_tag('br');

// render tab navigation, toggle button groups and order by dropdown
echo $renderer->tab_navigation($dialogue);
echo $renderer->state_button_group();
echo $renderer->show_button_group();
if ($dialogue->config->allowdisplaybystudent) {
    echo $renderer->display_by_student_checkbox();
}

echo $renderer->list_sortby(dialogue_conversations::get_sort_options(), $sort, $direction);
echo $renderer->conversations($conversationlist, $page);
echo $OUTPUT->footer($course);
$logurl = new moodle_url('view.php', array('id' =>  $cm->id));
add_to_log($course->id, 'dialogue', 'view', $logurl->out(false), $activityrecord->name, $cm->id);
