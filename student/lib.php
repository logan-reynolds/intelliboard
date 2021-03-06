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

/**
 * This plugin provides access to Moodle data in form of analytics and reports in real time.
 *
 *
 * @package    local_intelliboard
 * @copyright  2017 IntelliBoard, Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @website    http://intelliboard.net/
 */

defined('MOODLE_INTERNAL') || die();

function intelliboard_data($type, $userid) {
    global $DB, $USER;

    $data = array();
    $page = optional_param($type.'_page', 0, PARAM_INT);
    $search = clean_raw(optional_param('search', '', PARAM_RAW));
    $t = optional_param('type', '', PARAM_ALPHANUMEXT);
    $perpage = 10;
    $start = $page * $perpage;
    $params = array();
    $query = "";

    if($type == 'assignment'){
        $sql = "";
        if($search and $t == 'assignment'){
            $sql .= " AND (" . $DB->sql_like('c.fullname', ":fullname", false, false);
            $sql .= " OR " . $DB->sql_like('a.name', ":name", false, false);
            $sql .= ")";
            $params['fullname'] = "%$search%";
            $params['name'] = "%$search%";
        }
        if($USER->activity_courses){
            $sql .= " AND c.id = :activity_courses";
            $params['activity_courses'] = intval($USER->activity_courses);
        }else{
            $sql .= " AND c.id IN (SELECT e.courseid FROM {user_enrolments} ue, {enrol} e WHERE ue.userid = :userid1 AND e.id = ue.enrolid )";
            $params['userid1'] = $USER->id;
        }
        if($USER->activity_time !== -1){
            list($timestart, $timefinish) = get_timerange($USER->activity_time);
            $sql .= " AND a.duedate BETWEEN :timestart AND :timefinish";
            $params['timestart'] = $timestart;
            $params['timefinish'] = $timefinish;
        }
        $query = "SELECT a.id, a.name, a.duedate, c.fullname, (g.finalgrade/g.rawgrademax)*100 as grade, cmc.completionstate, cm.id as cmid
                    FROM {course} c, {assign} a
                        LEFT JOIN {modules} m ON m.name = 'assign'
                        LEFT JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = a.id
                        LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid2
                        LEFT JOIN {grade_items} gi ON gi.itemmodule = m.name AND gi.iteminstance = a.id
                        LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = :userid3
                    WHERE c.id = a.course AND cm.visible = 1 AND c.visible = 1 $sql ORDER BY a.duedate DESC";
        $params['userid2'] = $userid;
        $params['userid3'] = $userid;

        $data = $DB->get_records_sql($query, $params, $start, $perpage);
    }elseif ($type == 'quiz') {
        $sql = "";
        if($search and $t == 'quiz'){
            $sql .= " AND (" . $DB->sql_like('c.fullname', ":fullname", false, false);
            $sql .= " OR " . $DB->sql_like('a.name', ":name", false, false);
            $sql .= ")";
            $params['fullname'] = "%$search%";
            $params['name'] = "%$search%";
        }

        if($USER->activity_courses){
            $sql .= " AND c.id = :activity_courses";
            $params['activity_courses'] = intval($USER->activity_courses);
        }else{
            $sql .= " AND c.id IN (SELECT e.courseid FROM {user_enrolments} ue, {enrol} e WHERE ue.userid = :userid AND e.id = ue.enrolid )";
            $params['userid'] = $USER->id;
        }
        if($USER->activity_time !== -1){
            list($timestart, $timefinish) = get_timerange($USER->activity_time);
            $sql .= " AND a.timeclose BETWEEN :timestart AND :timefinish";
            $params['timestart'] = $timestart;
            $params['timefinish'] = $timefinish;
        }
        $query = "SELECT a.id, a.name, a.timeclose, c.fullname, (g.finalgrade/g.rawgrademax)*100 as grade, cmc.completionstate, cm.id as cmid
                  FROM {course} c, {quiz} a
                    LEFT JOIN {modules} m ON m.name = 'quiz'
                    LEFT JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = a.id
                    LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid2
                    LEFT JOIN {grade_items} gi ON gi.itemmodule = m.name AND gi.iteminstance = a.id
                    LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = :userid3
                  WHERE c.id = a.course AND cm.visible = 1 AND c.visible = 1 $sql ORDER BY a.timeclose DESC";
        $params['userid2'] = $userid;
        $params['userid3'] = $userid;

        $data = $DB->get_records_sql($query, $params, $start, $perpage);
    }elseif ($type == 'course') {
        $sql = "";
        if($search and $t == 'course'){
            $sql .= " AND " . $DB->sql_like('c.fullname', ":fullname", false, false);
            $params['fullname'] = "%$search%";
        }
        $query = "SELECT c.id, (g.finalgrade/g.rawgrademax)*100 AS grade, c.fullname, ue.timemodified, cc.timecompleted, m.modules, cm.completedmodules
                  FROM {user_enrolments} ue
                    LEFT JOIN {enrol} e ON e.id = ue.enrolid
                    LEFT JOIN {course} c ON c.id = e.courseid
                    LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
                    LEFT JOIN (SELECT course, count(id) as modules FROM {course_modules} WHERE visible = 1 AND completion = 1 GROUP BY course) m ON m.course = c.id
                    LEFT JOIN (SELECT cm.course, cmc.userid, count(DISTINCT cmc.id) as completedmodules FROM {course_modules} cm, {course_modules_completion} cmc WHERE cm.id = cmc.coursemoduleid AND cmc.completionstate = 1 AND cm.visible = 1 AND cm.completion = 1 GROUP BY cm.course, cmc.userid) cm ON cm.course = c.id AND cm.userid = ue.userid
                    LEFT JOIN (SELECT courseid, sum(timespend) AS duration FROM {local_intelliboard_tracking} WHERE userid = :userid1 AND courseid > 0 GROUP BY courseid ) l ON l.courseid = c.id
                    LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
                    LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = ue.userid
                  WHERE ue.userid = :userid2 AND c.visible = 1 $sql GROUP BY c.id ORDER BY c.fullname";

        $params['userid1'] = $userid;
        $params['userid2'] = $userid;
        $data = $DB->get_records_sql($query, $params, $start, $perpage);
    }elseif ($type == 'courses') {
        $sql = "";
        if($search){
            $sql .= " AND " . $DB->sql_like('c.fullname', ":fullname", false, false);
            $params['fullname'] = "%$search%";
        }

        $res = $DB->get_record_sql("SELECT COUNT(cm.id) as certificates FROM {course_modules} cm, {modules} m WHERE m.name = 'certificate' AND cm.module = m.id AND cm.visible = 1");
        $sql_select = "";
        $sql_join = "";
        if($res->certificates){
            $sql_select = ", ce.certificates";
            $sql_join = "LEFT JOIN (SELECT c.course, COUNT(ci.id) AS certificates FROM {certificate} c, {certificate_issues} ci WHERE c.id = ci.certificateid AND ci.userid = :userid1 GROUP BY c.course) ce ON ce.course = c.id";
            $params['userid1'] = $userid;
        }else{
            $sql_select = ",'' as certificates";
        }
        $query = "SELECT c.id, (g.finalgrade/g.rawgrademax)*100 AS grade, gc.average, c.fullname, ca.name as category, ue.timemodified, cc.timecompleted, m.modules, cm.completedmodules, l.duration $sql_select
                  FROM {user_enrolments} ue
                    LEFT JOIN {enrol} e ON e.id = ue.enrolid LEFT JOIN {course} c ON c.id = e.courseid
                    LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
                    LEFT JOIN (SELECT course, count(id) as modules FROM {course_modules} WHERE visible = 1 AND completion = 1 GROUP BY course) m ON m.course = c.id
                    LEFT JOIN (SELECT cm.course, cmc.userid, count(cmc.id) as completedmodules FROM {course_modules} cm, {course_modules_completion} cmc WHERE cm.id = cmc.coursemoduleid AND cmc.completionstate = 1 AND cm.visible = 1 AND cm.completion = 1 GROUP BY cm.course, cmc.userid) cm ON cm.course = c.id AND cm.userid = ue.userid
                    LEFT JOIN (SELECT courseid, sum(timespend) AS duration FROM {local_intelliboard_tracking} WHERE userid = :userid2 AND courseid > 0 GROUP BY courseid ) l ON l.courseid = c.id
                    LEFT JOIN {course_categories} ca ON ca.id = c.category
                    LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
                    LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = ue.userid
                    LEFT JOIN (SELECT gi.courseid, AVG( (g.finalgrade/g.rawgrademax)*100) AS average FROM {grade_items} gi, {grade_grades} g WHERE gi.itemtype = 'course' AND g.itemid = gi.id AND g.finalgrade IS NOT NULL GROUP BY gi.courseid) as gc ON gc.courseid = c.id
                    $sql_join
                  WHERE ue.userid = :userid3 AND c.visible = 1 $sql GROUP BY c.id ORDER BY c.fullname";
        $params['userid2'] = $userid;
        $params['userid3'] = $userid;

        $data = $DB->get_records_sql($query, $params, $start, $perpage);
    }

    $count = $DB->count_records_sql("SELECT COUNT(*) FROM ($query) AS x", $params);
    $pagination = get_pagination($count, $page, $perpage, $type);

    return array("pagination"=>$pagination, "data"=>$data);
}

function get_timerange($time){
    if($time == 0){
        $timestart = strtotime('-1 week');
        $timefinish = time();
    }elseif($time == 1){
        $timestart = strtotime('-1 month');
        $timefinish = time();
    }elseif($time == 2){
        $timestart = strtotime('-4 month');
        $timefinish = time();
    }elseif($time == 3){
        $timestart = strtotime('-6 month');
        $timefinish = time();
    }elseif($time == 4){
        $timestart = strtotime('-1 year');
        $timefinish = time();
    }else{
        $timestart = strtotime('-14 days');
        $timefinish = strtotime('+14 days');
    }
    return array($timestart,$timefinish);
}
function get_pagination($count = 0, $page = 0, $perpage = 15, $type = 'intelliboard') {
    global $OUTPUT, $PAGE;

    $pages = (int)ceil($count/$perpage);
    if ($pages == 1 || $pages == 0) {
        return '';
    }
    $link = new moodle_url($PAGE->url, array());
    return $OUTPUT->paging_bar($count, $page, $perpage, $link, $type.'_page');
}

function intelliboard_learner_course_progress($courseid, $userid){
    global $DB;

    $timestart = strtotime('-30 days');
    $timefinish = time();

    $data = array();
    $params = array();
    $params['userid'] = $userid;
    $params['timestart'] = $timestart;
    $params['timefinish'] = $timefinish;
    $params['courseid'] = $courseid;
    $data[] = $DB->get_records_sql("SELECT floor(g.timemodified / 86400) * 86400 as timepoint, AVG((g.finalgrade/g.rawgrademax)*100) as grade
                                    FROM {grade_items} gi, {grade_grades} g
                                    WHERE gi.id = g.itemid AND g.userid = :userid AND gi.itemtype = 'mod' AND g.finalgrade IS NOT NULL AND g.timemodified BETWEEN :timestart AND :timefinish AND gi.courseid = :courseid
                                    GROUP BY floor(g.timemodified / 86400) * 86400 ORDER BY g.timemodified", $params);

    $data[] = $DB->get_records_sql("SELECT floor(g.timemodified / 86400) * 86400 as timepoint, AVG((g.finalgrade/g.rawgrademax)*100) as grade
                                    FROM {grade_items} gi, {grade_grades} g
                                    WHERE gi.id = g.itemid AND g.userid != :userid AND gi.itemtype = 'mod' AND g.finalgrade IS NOT NULL AND g.timemodified BETWEEN :timestart AND :timefinish AND gi.courseid = :courseid
                                    GROUP BY floor(g.timemodified / 86400) * 86400 ORDER BY g.timemodified", $params);
    return $data;
}
function intelliboard_learner_progress($time, $userid){
    global $DB;

    list($timestart, $timefinish) = get_timerange($time);

    $data = array();
    $params = array();
    $params['userid'] = $userid;
    $params['timestart'] = $timestart;
    $params['timefinish'] = $timefinish;
    $data[] = $DB->get_records_sql("SELECT floor(g.timemodified / 86400) * 86400 as timepoint, AVG((g.finalgrade/g.rawgrademax)*100) as grade
                                    FROM {grade_items} gi, {grade_grades} g
                                    WHERE gi.id = g.itemid AND g.userid = :userid AND gi.itemtype = 'mod' AND g.finalgrade IS NOT NULL AND g.timemodified BETWEEN :timestart AND :timefinish
                                    GROUP BY floor(g.timemodified / 86400) * 86400 ORDER BY g.timemodified", $params);

    $data[] = $DB->get_records_sql("SELECT floor(g.timemodified / 86400) * 86400 as timepoint, AVG((g.finalgrade/g.rawgrademax)*100) as grade
                                    FROM {grade_items} gi, {grade_grades} g
                                    WHERE gi.id = g.itemid AND g.userid != :userid AND gi.itemtype = 'mod' AND g.finalgrade IS NOT NULL AND g.timemodified BETWEEN :timestart AND :timefinish
                                    GROUP BY floor(g.timemodified / 86400) * 86400 ORDER BY g.timemodified", $params);
    return $data;
}

function intelliboard_learner_courses($userid){
    global $DB;

    $params = array();
    $params['userid1'] = $userid;
    $params['userid2'] = $userid;
    $data = $DB->get_records_sql("SELECT c.id, (g.finalgrade/g.rawgrademax)*100 AS grade, gc.average, c.fullname, l.duration, '0' AS duration_calc
                                  FROM {user_enrolments} ue
                                    LEFT JOIN {enrol} e ON e.id = ue.enrolid
                                    LEFT JOIN {course} c ON c.id = e.courseid
                                    LEFT JOIN (SELECT courseid, sum(timespend) AS duration FROM {local_intelliboard_tracking} WHERE userid = :userid1 AND courseid > 0 GROUP BY courseid ) l ON l.courseid = c.id
                                    LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
                                    LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = ue.userid
                                    LEFT JOIN (SELECT gi.courseid, AVG( (g.finalgrade/g.rawgrademax)*100) AS average FROM {grade_items} gi, {grade_grades} g WHERE gi.itemtype = 'course' AND g.itemid = gi.id AND g.finalgrade IS NOT NULL GROUP BY gi.courseid) as gc ON gc.courseid = c.id
                                  WHERE ue.userid = :userid2 AND c.visible = 1 GROUP BY c.id", $params);
    $d = 0;
    foreach($data as $c){
        $d = ($c->duration > $d)?$c->duration:$d;
    }
    if($d){
        foreach($data as $c){
            $c->duration_calc =  (intval($c->duration)/$d)*100;
        }
    }
    return $data;
}

function intelliboard_learner_totals($userid){
    global $DB;

    $params = array();
    $params['userid1'] = $userid;
    $params['userid2'] = $userid;
    $params['userid3'] = $userid;
    $params['userid4'] = $userid;
    $params['userid5'] = $userid;
    $params['userid6'] = $userid;
    $params['userid7'] = $userid;
    $params['userid8'] = $userid;
    $params['userid9'] = $userid;
    $params['userid10'] = $userid;

    return $DB->get_record_sql("SELECT
                                    (SELECT count(distinct e.courseid) FROM {user_enrolments} ue, {enrol} e WHERE e.status = 0 AND ue.status = 0 AND ue.userid = :userid1 AND e.id = ue.enrolid) AS enrolled,
                                    (SELECT count(id) FROM {message} WHERE useridto = :userid2) AS messages,
                                    (SELECT count(distinct course) FROM {course_completions} WHERE userid = :userid3 AND timecompleted > 0) AS completed,
                                    (SELECT count(distinct e.courseid) FROM {user_enrolments} ue, {enrol} e WHERE e.status = 0 AND ue.status = 0 AND ue.userid = :userid4 AND e.id = ue.enrolid AND (e.courseid IN (
                                        SELECT distinct cm.course FROM {course_modules_completion} cmc, {course_modules} cm WHERE cmc.coursemoduleid = cm.id and cmc.userid = :userid5) OR e.courseid IN (
                                            SELECT distinct gi.courseid FROM {grade_items} gi, {grade_grades} g WHERE g.userid = :userid6 AND g.finalgrade IS NOT NULL AND gi.id = g.itemid)) AND e.courseid NOT IN (
                                                SELECT distinct course FROM {course_completions} WHERE userid = :userid7 AND timecompleted > 0)) as inprogress,
                                    (SELECT AVG((g.finalgrade/g.rawgrademax)*100) FROM {grade_items} gi, {grade_grades} g WHERE gi.itemtype = 'course' AND g.userid != :userid8 AND g.finalgrade IS NOT NULL AND gi.id = g.itemid AND gi.courseid IN (
                                        SELECT count(distinct e.courseid) FROM {user_enrolments} ue, {enrol} e WHERE e.status = 0 AND ue.status = 0 AND ue.userid = :userid9 AND e.id = ue.enrolid)) as average,
                                    (SELECT AVG((g.finalgrade/g.rawgrademax)*100) FROM {grade_items} gi, {grade_grades} g WHERE gi.itemtype = 'course' AND g.userid = :userid10 AND g.finalgrade IS NOT NULL AND gi.id = g.itemid) as grade", $params);
}
function intelliboard_learner_course($userid, $courseid){
    global $DB;

    $params = array();
    $params['userid1'] = $userid;
    $params['userid2'] = $userid;
    $params['userid3'] = $userid;
    $params['courseid'] = $courseid;
    return $DB->get_record_sql("SELECT c.id, c.fullname, ul.timeaccess, c.enablecompletion, cc.timecompleted, (g.finalgrade/g.rawgrademax)*100 AS grade
                                FROM {course} c
                                  LEFT JOIN {user_lastaccess} ul ON ul.courseid = c.id AND ul.userid = :userid1
                                  LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = :userid2
                                  LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
                                  LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = :userid3
                                WHERE c.id = :courseid", $params);
}
function intelliboard_learner_modules($userid){
    global $DB;

    $params = array();
    $params['userid1'] = $userid;
    $params['userid2'] = $userid;
    $params['userid3'] = $userid;
    return $DB->get_records_sql("SELECT m.id, m.name, count(distinct cm.id) as modules , count(distinct cmc.id) as completed_modules, count(distinct l.id) as start_modules, sum(l.timespend) as duration
                                  FROM {modules} m, {course_modules} cm
                                    LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid1 AND cmc.completionstate = 1
                                    LEFT JOIN {local_intelliboard_tracking} l ON l.page = 'module' AND l.userid = :userid2 AND l.param = cm.id
                                  WHERE cm.visible = 1 AND cm.module = m.id and cm.course IN (
                                    SELECT distinct e.courseid FROM {enrol} e, {user_enrolments} ue WHERE ue.userid = :userid3 AND e.id = ue.enrolid) GROUP BY m.id", $params);
}
