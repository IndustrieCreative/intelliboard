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
 * @website    https://intelliboard.net/
 */

require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/gradelib.php');

class intelliboard_courses_grades_table extends table_sql {
    public $scale_real;

    function __construct($uniqueid, $search = '') {
        global $CFG, $PAGE, $DB, $USER;

        parent::__construct($uniqueid);

        $headers = array();
        $columns = array();

        if(get_config('local_intelliboard', 'table_set_icg_c1')) {
            $columns[] =  'course';
            $headers[] =  get_string('course_name', 'local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_icg_c2')) {
            $columns[] =  'course_shortname';
            $headers[] =  get_string('shortname');
        }

        if(get_config('local_intelliboard', 'table_set_icg_c3')) {
            $columns[] =  'category';
            $headers[] =  get_string('category', 'local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_icg_c4')) {
            $columns[] =  'learners';
            $headers[] =  get_string('enrolled_completed_learners', 'local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_icg_c5')) {
            $columns[] =  'grade';
            $headers[] =  get_string('in21', 'local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_icg_c6')) {
            $columns[] =  'sections';
            $headers[] =  get_string('sections', 'local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_icg_c7')) {
            $columns[] =  'modules';
            $headers[] =  get_string('activities_resources', 'local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_icg_c8')) {
            $columns[] =  'visits';
            $headers[] =  get_string('visits', 'local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_icg_c9')) {
            $columns[] =  'timespend';
            $headers[] =  get_string('time_spent', 'local_intelliboard');
        }

        if(
            get_config('local_intelliboard', 'table_set_icg_c11') or
            get_config('local_intelliboard', 'table_set_icg_c12')
        ) {
            $columns[] =  'actions';
            $headers[] =  get_string('actions', 'local_intelliboard');
        }

        if(!$columns) {
            return false;
        }

        $this->define_headers($headers);
        $this->define_columns($columns);

        $sql = intelliboard_instructor_getcourses();
        $params = array('userid'=>$USER->id);

        if($search){
            $sql .= " AND " . $DB->sql_like('c.fullname', ":fullname", false, false);
            $params['fullname'] = "%$search%";
        }

        list($sql1, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        list($sql2, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter10')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);

        list($sql3, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        list($sql4, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        list($sql5, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        $grade_avg = intelliboard_grade_sql(true);
        $join_group_sql = intelliboard_group_aggregation_sql('ra.userid', $USER->id, 'ctx.instanceid');
        $join_group_sql2 = intelliboard_group_aggregation_sql('g.userid', $USER->id, 'gi.courseid');


        $sql33 = intelliboard_instructor_getcourses('l.courseid', false, 'l.userid');
        $sql44 = intelliboard_instructor_getcourses('ctx.instanceid', false, 'ra.userid');
        $sql55 = intelliboard_instructor_getcourses('gi.courseid', false, 'g.userid');
        $sql66 = intelliboard_instructor_getcourses('course', false, 'userid');

        $fields = "c.id, c.fullname as course, c.shortname as course_shortname,
                c.enablecompletion,
                ca.name AS category,
                (SELECT SUM(l.timespend)
                 FROM {local_intelliboard_tracking} l
                 WHERE l.courseid = c.id $sql33 AND l.userid IN (SELECT DISTINCT ra.userid
                                                          FROM {context} ctx
                                                            JOIN {role_assignments} ra ON ctx.id = ra.contextid
                                                            $join_group_sql
                                                          WHERE ctx.instanceid = c.id AND ctx.contextlevel = 50 AND ra.roleid $sql3)
                 ) AS timespend,
                 (SELECT SUM(l.visits)
                    FROM {local_intelliboard_tracking} l
                    WHERE l.courseid = c.id $sql33 AND l.userid IN (SELECT DISTINCT ra.userid
                                                                FROM {context} ctx
                                                                    JOIN {role_assignments} ra ON ctx.id = ra.contextid
                                                                    $join_group_sql
                                                                WHERE ctx.instanceid = c.id AND ctx.contextlevel = 50 AND ra.roleid $sql4)) AS visits,
                (SELECT COUNT(DISTINCT ra.userid) FROM {role_assignments} ra
                    LEFT JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                    $join_group_sql
                    WHERE ra.roleid $sql1 $sql44 AND ctx.instanceid = c.id) AS learners,
                (SELECT $grade_avg
                    FROM {grade_items} gi
                        JOIN {grade_grades} g ON g.itemid = gi.id AND g.finalgrade IS NOT NULL
                        $join_group_sql2
                    WHERE gi.itemtype = 'course' AND gi.courseid = c.id   $sql55) AS grade,
                (SELECT COUNT(DISTINCT userid)
                    FROM {course_completions}
                    WHERE timecompleted > 0 $sql66 AND course = c.id AND userid IN (SELECT DISTINCT ra.userid
                                                                              FROM {context} ctx
                                                                                JOIN {role_assignments} ra ON ctx.id = ra.contextid
                                                                                $join_group_sql
                                                                              WHERE ctx.instanceid = c.id AND ctx.contextlevel = 50 AND ra.roleid $sql5)) AS completed,
                (SELECT COUNT(id) FROM {course_modules} WHERE visible = 1 AND course = c.id) AS modules,
                (SELECT COUNT(id) FROM {course_sections} WHERE visible = 1 AND course = c.id) AS sections,
                '' as actions";
        $from = "{course} c
                LEFT JOIN {course_categories} ca ON ca.id = c.category";
        $where = "c.id > 0 $sql";


        $this->set_sql($fields, $from, $where, $params);
        $this->define_baseurl($PAGE->url);

        $this->scale_real = get_config('local_intelliboard', 'scale_real');
    }
    function col_visits($values) {
        return html_writer::tag("span", intval($values->visits), array("class"=>"info-average"));
    }
    function col_timespend($values) {
      return ($values->timespend) ? seconds_to_time($values->timespend) : '-';
    }
    function col_learners($values) {
        $learners = intval($values->learners);
        $completed = intval($values->completed);
        $progress = ($learners and $completed)?(($completed/$learners) * 100): 0;
        $progress = round($progress, 0);

        $html = html_writer::start_tag("div",array("class"=>"intelliboard-tooltip","title"=>"Learners: $learners | Completed: $completed"));
        $html .= html_writer::start_tag("div",array("class"=>"intelliboard-progress xxl"));
        $html .= html_writer::tag("span", "&nbsp;&nbsp;" . intval($values->learners)."/".intval($values->completed) . "&nbsp;&nbsp;", array("style"=>"width:{$progress}%"));
        $html .= html_writer::end_tag("div");
        $html .= html_writer::end_tag("div");
        return $html;
    }

    function col_grade($values) {
        if($this->scale_real>0){
            return $values->grade;
        }else{
            $html = html_writer::start_tag("div", array("class" => "grade"));
            $html .= html_writer::tag("div", "", array("class" => "circle-progress", "data-percent" => (int)$values->grade));
            $html .= html_writer::end_tag("div");
            return $html;
        }
    }
    function col_modules($values) {
        return intval($values->modules);
    }

    function col_course($values) {
        global $CFG;

        return html_writer::link(new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$values->id)), $values->course, array("target"=>"_blank"));
    }
    function col_actions($values) {
        global  $PAGE;

        $elements = [];
        $start = html_writer::start_tag("div",array("style"=>"width:200px; margin: 5px 0;"));

        if(get_config('local_intelliboard', 'table_set_icg_c11')) {
            $elements[] = html_writer::link(
                new moodle_url($PAGE->url, array('action'=>'learners', 'id'=>$values->id)),
                get_string('learners','local_intelliboard'),
                array(
                    'class' =>'btn btn-default',
                    'title' => get_string('learners','local_intelliboard')
                )
            );
        }

        if(get_config('local_intelliboard', 'table_set_icg_c12')) {
            $elements[] = html_writer::link(
                new moodle_url($PAGE->url, array('action'=>'activities', 'id'=>$values->id)),
                get_string('activities','local_intelliboard'),
                array(
                    'class' =>'btn btn-default',
                    'title' => get_string('activities','local_intelliboard')
                )
            );
        }

        return $start . implode("&nbsp", $elements) . html_writer::end_tag("div");
    }
}

class intelliboard_activities_grades_table extends table_sql {
    public $scale_real;

    function __construct($uniqueid, $courseid = 0, $search = '', $mod = 0) {
        global $CFG, $PAGE, $DB, $USER;

        parent::__construct($uniqueid);

        $columns = array();
        $headers = array();

        if(get_config('local_intelliboard', 'table_set_iag_c1')) {
            $columns[] =  'activity';
            $headers[] =  get_string('activity_name','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_iag_c2')) {
            $columns[] =  'module';
            $headers[] =  get_string('type','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_iag_c3')) {
            $columns[] =  'completed';
            $headers[] =  get_string('in6','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_iag_c4')) {
            $columns[] =  'grade';
            $headers[] =  ucfirst(get_string('average_grade','local_intelliboard'));
        }

        if(get_config('local_intelliboard', 'table_set_iag_c5')) {
            $columns[] =  'visits';
            $headers[] =  get_string('visits','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_iag_c6')) {
            $columns[] =  'timespend';
            $headers[] =  get_string('time_spent','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_iag_c7')) {
            $columns[] =  'actions';
            $headers[] =  get_string('actions','local_intelliboard');
        }

        if(!$columns) {
            return false;
        }

        $this->define_headers($headers);
        $this->define_columns($columns);

        $params = array('c1'=>$courseid, 'c2'=>$courseid, 'c3'=>$courseid, 'c4'=>$courseid);
        $sql = "";
        if ($search) {
            $sql .= " AND " . $DB->sql_like('m.name', ":activity", false, false);
            $params['activity'] = "%$search%";
        }
        if ($mod) {
            $sql .= " AND cm.module IN (1,15,16,17,20,23)";
        }
        list($sql1, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);

        $sql_columns = "";
        $modules = $DB->get_records_sql("SELECT m.id, m.name FROM {modules} m WHERE m.visible = 1");
        foreach($modules as $module){
            $sql_columns .= " WHEN m.name='{$module->name}' THEN (SELECT name FROM {".$module->name."} WHERE id = cm.instance)";
        }
        $sql_columns =  ($sql_columns) ? ", CASE $sql_columns ELSE 'none' END AS activity" : "'' AS activity";
        $grade_avg = intelliboard_grade_sql(true);
        $completion = intelliboard_compl_sql("cm.", false);
        $join_group_sql = intelliboard_group_aggregation_sql('g.userid', $USER->id, 'gi.courseid');
        $join_group_sql2 = intelliboard_group_aggregation_sql('cm.userid', $USER->id, 'm.course');
        $join_group_sql3 = intelliboard_group_aggregation_sql('ra.userid', $USER->id, 'ctx.instanceid');


        $sql33 = intelliboard_instructor_getcourses('gi.courseid', false, 'g.userid');
        $sql44 = intelliboard_instructor_getcourses('m.course', false, 'cm.userid');
        $sql55 = intelliboard_instructor_getcourses('courseid', false, 'userid');

        $fields = "
                cm.id,
                cm.course,
                m.name as module,
                cmc.completed,
                g.grade,
                l.visits,
                l.timespend,
                '' as actions
                $sql_columns";

        $from = "{course_modules} cm
                LEFT JOIN {modules} m ON m.id = cm.module
                LEFT JOIN (SELECT gi.iteminstance, gi.itemmodule, $grade_avg AS grade
                            FROM {grade_items} gi
                             JOIN {grade_grades} g ON g.itemid = gi.id AND g.finalgrade IS NOT NULL
                             $join_group_sql
                            WHERE gi.itemtype = 'mod' AND gi.courseid = :c1 $sql33
                            GROUP BY gi.iteminstance, gi.itemmodule
                           ) as g ON g.iteminstance = cm.instance AND g.itemmodule = m.name
                LEFT JOIN (SELECT cm.coursemoduleid, COUNT(cm.id) AS completed
                            FROM {course_modules_completion} cm
                                JOIN {course_modules} m ON m.id=cm.coursemoduleid
                                $join_group_sql2
                            WHERE $completion $sql44
                            GROUP BY cm.coursemoduleid) cmc ON cmc.coursemoduleid = cm.id
                LEFT JOIN (SELECT param, SUM(visits) AS visits, SUM(timespend) AS timespend
                            FROM {local_intelliboard_tracking}
                            WHERE page='module' $sql55 AND courseid = :c2 AND userid IN (SELECT DISTINCT ra.userid
                                                                                    FROM {context} ctx
                                                                                        JOIN {role_assignments} ra ON ctx.id = ra.contextid
                                                                                        $join_group_sql3
                                                                                    WHERE ctx.instanceid = :c4 AND ctx.contextlevel = 50 AND ra.roleid $sql1) GROUP BY param) l ON l.param=cm.id";
        $where = "cm.visible = 1 AND cm.course = :c3 $sql";

        $this->set_sql($fields, $from, $where, $params);
        $this->define_baseurl($PAGE->url);

        $this->scale_real = get_config('local_intelliboard', 'scale_real');
    }

    function col_grade($values) {
        if($this->scale_real>0){
            return $values->grade;
        }else{
            $html = html_writer::start_tag("div", array("class" => "grade"));
            $html .= html_writer::tag("div", "", array("class" => "circle-progress", "data-percent" => (int)$values->grade));
            $html .= html_writer::end_tag("div");
            return $html;
        }
    }
    function col_module($values) {
      return get_string('modulename', $values->module);
    }
    function col_completed($values) {
      return intval($values->completed);
    }
    function col_visits($values) {
        return html_writer::tag("span", intval($values->visits), array("class"=>"info-average"));
    }
    function col_timespend($values) {
      return ($values->timespend) ? seconds_to_time($values->timespend) : '-';
    }
    function col_activity($values) {
        global $CFG, $PAGE;

        return html_writer::link(new moodle_url("$CFG->wwwroot/mod/$values->module/view.php", array('id'=>$values->id)), $values->activity, array("target"=>"_blank"));
    }
    function col_actions($values) {
        global  $PAGE;

        return html_writer::link(new moodle_url($PAGE->url, array('action'=>'activity', 'cmid'=>$values->id, 'id'=>$values->course)), get_string('grades','local_intelliboard'), array('class' =>'btn btn-default', 'title' =>get_string('grades','local_intelliboard')));
    }
}

class intelliboard_activity_grades_table extends table_sql {
    public $scale_real;

    function __construct($uniqueid, $cmid = 0, $courseid = 0, $search = '') {
        global $CFG, $PAGE, $DB, $USER;

        parent::__construct($uniqueid);

        $columns = []; $headers = [];

        if(get_config('local_intelliboard', 'table_set_iag1_c1')) {
            $columns[] = 'learner';
            $headers[] = get_string('learner_name','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_iag1_c2')) {
            $columns[] = 'email';
            $headers[] = get_string('email');
        }

        if(get_config('local_intelliboard', 'table_set_iag1_c3')) {
            $columns[] = 'timecompleted';
            $headers[] = get_string('status','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_iag1_c4')) {
            $columns[] = 'grade';
            $headers[] = get_string('grade','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_iag1_c5')) {
            $columns[] = 'graded';
            $headers[] = get_string('graded','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_iag1_c6')) {
            $columns[] =  'visits';
            $headers[] =  get_string('visits','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_iag1_c7')) {
            $columns[] =  'timespend';
            $headers[] =  get_string('time_spent','local_intelliboard');
        }

        if(!$columns) {
            return false;
        }

        $this->define_headers($headers);
        $this->define_columns($columns);

        $params = array('cmid'=>$cmid, 'courseid'=>$courseid);
        $sql = "";
        if($search){
            $sql .= " AND " . $DB->sql_like('u.firstname', ":firstname", false, false);
            $params['firstname'] = "%$search%";
        }
        list($sql_roles, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        $grade_single = intelliboard_grade_sql();
        $join_group_sql = intelliboard_group_aggregation_sql('ra.userid', $USER->id, 'e.instanceid');


        $sql55 = intelliboard_instructor_getcourses('c.id', false, 'u.id');

        $fields = "ra.id, ra.userid, c.id AS courseid,
            $grade_single AS grade,
            CASE WHEN g.timemodified > 0 THEN g.timemodified ELSE g.timecreated END AS graded,
            cc.timemodified AS timecompleted,
            cc.completionstate,
            u.email,
            CONCAT(u.firstname, ' ', u.lastname) as learner,
            l.timespend, l.visits";
        $from = "{role_assignments} ra
                LEFT JOIN {context} e ON e.id = ra.contextid AND e.contextlevel = 50
                $join_group_sql
                LEFT JOIN {user} u ON u.id = ra.userid
                LEFT JOIN {course} c ON c.id = e.instanceid
                LEFT JOIN {course_modules} cm ON cm.id = :cmid
                LEFT JOIN {modules} m ON m.id = cm.module
                LEFT JOIN {course_modules_completion} cc ON cc.coursemoduleid = cm.id AND cc.userid = ra.userid
                LEFT JOIN {grade_items} gi ON gi.itemtype = 'mod' AND gi.itemmodule = m.name AND gi.iteminstance = cm.instance
                LEFT JOIN {grade_grades} g ON g.userid = u.id AND g.itemid = gi.id AND g.finalgrade IS NOT NULL
                LEFT JOIN {local_intelliboard_tracking} l ON l.userid = u.id AND l.param = cm.id AND l.page = 'module'";
        $where = "ra.roleid $sql_roles $sql55 AND e.instanceid = :courseid $sql";

        $this->set_sql($fields, $from, $where, $params);
        $this->define_baseurl($PAGE->url);
        $this->scale_real = get_config('local_intelliboard', 'scale_real');
    }

    function col_grade($values) {
        if($this->scale_real>0){
            return $values->grade;
        }else{
            $html = html_writer::start_tag("div", array("class" => "grade"));
            $html .= html_writer::tag("div", "", array("class" => "circle-progress", "data-percent" => (int)$values->grade));
            $html .= html_writer::end_tag("div");
            return $html;
        }
    }
    function col_timecompleted($values) {
        if ($values->completionstate == 3) {
            return get_string('failed','local_intelliboard');
        } elseif ($values->completionstate == 2) {
           return get_string('passed','local_intelliboard');
        } elseif ($values->timecompleted and $values->completionstate == 1) {
            return get_string('completed_on','local_intelliboard', date('m/d/Y', $values->timecompleted));
        } else {
            return get_string('incomplete','local_intelliboard');
        }
    }
    function col_visits($values) {
        return html_writer::tag("span", intval($values->visits), array("class"=>"info-average"));
    }
    function col_timespend($values) {
      return ($values->timespend) ? seconds_to_time($values->timespend) : '-';
    }
    function col_graded($values) {
      return ($values->graded) ? date('m/d/Y', $values->graded) : '';
    }
    function col_learner($values) {
        global $CFG, $PAGE;

        return html_writer::link(new moodle_url($PAGE->url, array('action'=>'learner', 'userid'=>$values->userid, 'id'=>$values->courseid)), $values->learner);
    }
    function col_actions($values) {
        global  $PAGE;

        return html_writer::link(new moodle_url($PAGE->url, array('action'=>'learner', 'userid'=>$values->userid, 'id'=>$values->courseid)), get_string('grades','local_intelliboard'), array('class' =>'btn btn-default', 'title' =>get_string('grades','local_intelliboard')));
    }
}


class intelliboard_learners_grades_table extends table_sql {
    public $scale_real;

    function __construct($uniqueid, $courseid = 0, $search = '') {
        global $CFG, $PAGE, $DB, $USER;

        parent::__construct($uniqueid);

        $columns = [];
        $headers = [];

        if(get_config('local_intelliboard', 'table_set_ilg_c1')) {
            $columns[] = 'learner';
            $headers[] = get_string('learner_name','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg_c2')) {
            $columns[] = 'email';
            $headers[] = get_string('email');
        }

        if(get_config('local_intelliboard', 'table_set_ilg_c3')) {
            $columns[] = 'enrolled';
            $headers[] = get_string('enrolled','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg_c4')) {
            $columns[] = 'timeaccess';
            $headers[] = get_string('in16','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg_c5')) {
            $columns[] = 'timecompleted';
            $headers[] = get_string('status','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg_c6')) {
            $columns[] = 'grade';
            $headers[] = get_string('grade','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg_c7')) {
            $columns[] = 'progress';
            $headers[] = get_string(
                'completed_activities_resources','local_intelliboard'
            );
        }

        if(get_config('local_intelliboard', 'table_set_ilg_c8')) {
            $columns[] =  'visits';
            $headers[] =  get_string('visits','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg_c9')) {
            $columns[] =  'timespend';
            $headers[] =  get_string('time_spent','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg_c10')) {
            $columns[] =  'actions';
            $headers[] =  get_string('actions','local_intelliboard');
        }

        if(!$columns) {
            return false;
        }

        $this->define_headers($headers);
        $this->define_columns($columns);

        $params = array('c1'=>$courseid, 'c2'=>$courseid);
        $sql = "";
        if($search){
            $sql .= " AND " . $DB->sql_like("CONCAT(u.firstname, ' ', u.lastname)", ":learner", false, false);
            $params['learner'] = "%$search%";
        }
        list($sql_roles, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        $grade_single = intelliboard_grade_sql();
        $completion = intelliboard_compl_sql("cmc.");
        $join_group_sql = intelliboard_group_aggregation_sql('ra.userid', $USER->id, 'e.instanceid');

        $sql33 = intelliboard_instructor_getcourses('c.id', false, 'u.id');
        $sql .= get_filter_usersql("u.");

        $fields = "t.*";
        $from = "(SELECT MAX(ra.id) AS id,
            MAX(ra.userid) AS userid,
            MAX(c.id) as courseid,
            MAX(ra.timemodified) as enrolled,
            MAX(ul.timeaccess) AS timeaccess,
            $grade_single AS grade,
            MAX(cc.timecompleted) AS timecompleted,
            u.email,
            CONCAT(u.firstname, ' ', u.lastname) as learner,
            MAX(l.timespend) AS timespend,
            MAX(l.visits) AS visits,
            MAX(cmc.progress) AS progress,
            '' as actions FROM {role_assignments} ra
                LEFT JOIN {context} e ON e.id = ra.contextid AND e.contextlevel = 50
                LEFT JOIN {user} u ON u.id = ra.userid
                LEFT JOIN {course} c ON c.id = e.instanceid
                LEFT JOIN {user_lastaccess} ul ON ul.courseid = c.id AND ul.userid = u.id
                LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ra.userid
                LEFT JOIN {grade_items} gi ON gi.itemtype = 'course' AND gi.courseid = c.id
                LEFT JOIN {grade_grades} g ON g.userid = u.id AND g.itemid = gi.id AND g.finalgrade IS NOT NULL
                LEFT JOIN (SELECT cmc.userid, COUNT(DISTINCT cmc.id) as progress FROM {course_modules_completion} cmc, {course_modules} cm WHERE cm.visible = 1 AND cmc.coursemoduleid = cm.id $completion AND cm.completion > 0 AND cm.course = :c1 GROUP BY cmc.userid) cmc ON cmc.userid = u.id

                LEFT JOIN (SELECT t.userid,t.courseid, sum(t.timespend) as timespend, sum(t.visits) as visits FROM
                    {local_intelliboard_tracking} t GROUP BY t.courseid, t.userid) l ON l.courseid = c.id AND l.userid = u.id
                $join_group_sql  WHERE ra.roleid $sql_roles AND e.instanceid = :c2 $sql $sql33 GROUP BY ra.userid, c.id) t";
        $where = "t.id > 0";

        $this->set_sql($fields, $from, $where, $params);
        $this->define_baseurl($PAGE->url);
        $this->scale_real = get_config('local_intelliboard', 'scale_real');
    }

    function col_grade($values) {
        if($this->scale_real>0){
            return $values->grade;
        }else{
            $html = html_writer::start_tag("div",array("class"=>"grade"));
            $html .= html_writer::tag("div", "", array("class"=>"circle-progress", "data-percent"=>(int)$values->grade));
            $html .= html_writer::end_tag("div");
            return $html;
        }
    }
     function col_progress($values) {
        return intval($values->progress);
    }
    function col_visits($values) {
        return html_writer::tag("span", intval($values->visits), array("class"=>"info-average"));
    }
    function col_timespend($values) {
      return ($values->timespend) ? seconds_to_time($values->timespend) : '-';
    }
    function col_timecompleted($values) {
      return ($values->timecompleted) ? get_string('completed_on','local_intelliboard', date('m/d/Y', $values->timecompleted)) : get_string('incomplete','local_intelliboard');
    }
    function col_enrolled($values) {
      return ($values->enrolled) ? date('m/d/Y', $values->enrolled) : '';
    }
    function col_timeaccess($values) {
      return ($values->timeaccess) ? date('m/d/Y', $values->timeaccess) : '';
    }
    function col_learner($values) {
        global $CFG, $PAGE;

        return html_writer::link(new moodle_url($PAGE->url, array('action'=>'learner', 'userid'=>$values->userid, 'id'=>$values->courseid)), $values->learner);
    }
    function col_actions($values) {
        global  $PAGE;


        return html_writer::link(new moodle_url($PAGE->url, array('search'=>'','action'=>'learner', 'userid'=>$values->userid, 'id'=>$values->courseid)), get_string('grades','local_intelliboard'), array('class' =>'btn btn-default', 'title' =>get_string('grades','local_intelliboard')));
    }
}
class intelliboard_learner_grades_table extends table_sql {
    public $scale_real;

    function __construct($uniqueid, $userid = 0, $courseid = 0, $search = '', $mod = 0) {
        global $CFG, $PAGE, $DB;

        parent::__construct($uniqueid);

        $columns = []; $headers = [];

        if(get_config('local_intelliboard', 'table_set_ilg1_c1')) {
            $columns[] = 'activity';
            $headers[] = get_string('activity_name','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg1_c2')) {
            $columns[] = 'module';
            $headers[] = get_string('type','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg1_c3')) {
            $columns[] = 'grade';
            $headers[] = get_string('grade','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg1_c4')) {
            $columns[] =  'graded';
            $headers[] =  get_string('graded','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg1_c5')) {
            $columns[] = 'timecompleted';
            $headers[] = get_string('status','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg1_c6')) {
            $columns[] =  'visits';
            $headers[] =  get_string('visits','local_intelliboard');
        }

        if(get_config('local_intelliboard', 'table_set_ilg1_c7')) {
            $columns[] =  'timespend';
            $headers[] =  get_string('time_spent','local_intelliboard');
        }

        if(!$columns) {
            return false;
        }

        $this->define_headers($headers);
        $this->define_columns($columns);

        $params = array(
            'u1'=>$userid,
            'u2'=>$userid,
            'u3'=>$userid,
            'c1'=>$courseid,
            'c2'=>$courseid
        );
        $sql = "";
        if ($search) {
            $sql .= " AND " . $DB->sql_like('m.name', ":activity", false, false);
            $params['activity'] = "%$search%";
        }
        if ($mod) {
            $sql .= " AND cm.module IN (1,15,16,17,20,23)";
        }

        $sql_columns = "";
        $modules = $DB->get_records_sql("SELECT m.id, m.name FROM {modules} m WHERE m.visible = 1");
        foreach($modules as $module){
            $sql_columns .= " WHEN m.name='{$module->name}' THEN (SELECT name FROM {".$module->name."} WHERE id = cm.instance)";
        }
        $sql_columns =  ($sql_columns) ? ", CASE $sql_columns ELSE 'none' END AS activity" : "'' AS activity";
        $grade_single = intelliboard_grade_sql();
        $completion = intelliboard_compl_sql("cmc.");

        $fields = "
            cm.id,
            m.name as module,
            cmc.timemodified as timecompleted,
            $grade_single AS grade,
            CASE WHEN g.timemodified > 0 THEN g.timemodified ELSE g.timecreated END AS graded,
            l.visits,
            l.timespend
            $sql_columns";

        $from = "{course_modules} cm
            LEFT JOIN {modules} m ON m.id = cm.module
            LEFT JOIN {grade_items} gi ON gi.iteminstance = cm.instance AND gi.itemmodule = m.name AND gi.itemtype = 'mod'
            LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = :u1
            LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id $completion AND cmc.userid = :u2
            LEFT JOIN {local_intelliboard_tracking} l ON l.param=cm.id AND l.page='module' AND l.courseid=:c1 AND l.userid= :u3";
        $where = "cm.visible = 1 AND cm.course = :c2 $sql";

        $this->set_sql($fields, $from, $where, $params);
        $this->define_baseurl($PAGE->url);
        $this->scale_real = get_config('local_intelliboard', 'scale_real');
    }

    function col_grade($values) {
        if($this->scale_real>0){
            return $values->grade;
        }else{
            $html = html_writer::start_tag("div", array("class" => "grade"));
            $html .= html_writer::tag("div", "", array("class" => "circle-progress", "data-percent" => (int)$values->grade));
            $html .= html_writer::end_tag("div");
            return $html;
        }
    }

    function col_visits($values) {
        return html_writer::tag("span", intval($values->visits), array("class"=>"info-average"));
    }
    function col_timespend($values) {
      return ($values->timespend) ? seconds_to_time($values->timespend) : '-';
    }
    function col_timecompleted($values) {
      return ($values->timecompleted) ? get_string('completed_on','local_intelliboard', date('m/d/Y', $values->timecompleted)) : get_string('incomplete','local_intelliboard');
    }
    function col_graded($values) {
      return ($values->graded) ? date('m/d/Y', $values->graded) : '';
    }
}

class intelliboard_sessions_grades_table extends table_sql {
    public $scale_real;

    function __construct($uniqueid, $search = '') {
        global $CFG, $PAGE, $DB, $USER;

        parent::__construct($uniqueid);

        $columns = array('session_name');
        $headers = array(get_string('session_name','local_intelliboard'));

        $columns[] = 'session_time';
        $headers[] = get_string('session_time','local_intelliboard');

        $columns[] = 'course';
        $headers[] = get_string('course_name','local_intelliboard');

        $columns[] =  'category';
        $headers[] =  get_string('category','local_intelliboard');

        $columns[] =  'learners';
        $headers[] =  get_string('enrolled_completed_learners','local_intelliboard');

        $columns[] =  'grade';
        $headers[] =  get_string('in21','local_intelliboard');

        $columns[] =  'sections';
        $headers[] =  get_string('sections','local_intelliboard');

        $columns[] =  'modules';
        $headers[] =  get_string('activities_resources','local_intelliboard');

        $columns[] =  'visits';
        $headers[] =  get_string('visits','local_intelliboard');

        $columns[] =  'timespend';
        $headers[] =  get_string('time_spent','local_intelliboard');

        $columns[] =  'actions';
        $headers[] =  get_string('actions','local_intelliboard');

        $this->define_headers($headers);
        $this->define_columns($columns);

        $sql = intelliboard_instructor_getcourses();
        $params = array('userid'=>$USER->id, 'suserid'=>$USER->id);

        if($search){
            $sql .= " AND " . $DB->sql_like('c.fullname', ":fullname", false, false);
            $params['fullname'] = "%$search%";
        }

        list($sql1, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        list($sql2, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter10')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);

        list($sql3, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        list($sql4, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        list($sql5, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        $grade_avg = intelliboard_grade_sql(true);

        $usersin = "SELECT ei.userid
                      FROM {local_intellicart_logs} il
                 LEFT JOIN {enrol_intellicart} ei ON il.checkoutid = ei.checkoutid AND il.userid = ei.userid
                     WHERE il.type = 'product' AND ei.unenroldate = 0 AND ei.courseid = c.id AND il.sessionid = s.id";

        $fields = "s.id, s.name as session_name, s.timestart as session_time, s.timeend,
                c.id as courseid, c.fullname as course,
                c.enablecompletion,
                ca.name AS category,
                (SELECT SUM(l.timespend) FROM {local_intelliboard_tracking} l WHERE l.courseid = c.id AND l.userid IN (SELECT DISTINCT ra.userid FROM {role_assignments} ra, {context} ctx WHERE ctx.id = ra.contextid AND ctx.instanceid = c.id AND ctx.contextlevel = 50 AND ra.roleid $sql3)) AS timespend,
                 (SELECT SUM(l.visits) FROM {local_intelliboard_tracking} l WHERE l.courseid = c.id AND l.userid IN (SELECT DISTINCT ra.userid FROM {role_assignments} ra, {context} ctx WHERE ctx.id = ra.contextid AND l.userid IN ($usersin) AND ctx.instanceid = c.id AND ctx.contextlevel = 50 AND ra.roleid $sql4)) AS visits,
                (SELECT COUNT(DISTINCT ra.userid) FROM {role_assignments} ra
                    LEFT JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                    WHERE ra.roleid $sql1 AND ctx.instanceid = c.id AND ra.userid IN ($usersin)) AS learners,
                (SELECT $grade_avg
                    FROM {grade_items} gi, {grade_grades} g
                    WHERE gi.itemtype = 'course' AND g.itemid = gi.id AND g.finalgrade IS NOT NULL AND gi.courseid = c.id AND g.userid IN ($usersin)) AS grade,
                (SELECT COUNT(DISTINCT userid) FROM {course_completions} WHERE timecompleted > 0 AND course = c.id AND userid IN (SELECT DISTINCT ra.userid FROM {role_assignments} ra, {context} ctx WHERE ctx.id = ra.contextid AND ctx.instanceid = c.id AND ctx.contextlevel = 50  AND userid IN ($usersin) AND ra.roleid $sql5)) AS completed,
                (SELECT COUNT(id) FROM {course_modules} WHERE visible = 1 AND course = c.id) AS modules,
                (SELECT COUNT(id) FROM {course_sections} WHERE visible = 1 AND course = c.id) AS sections,
                '' as actions";
        $from = " {local_intellicart_sessions} s
                LEFT JOIN {local_intellicart_products} ip ON ip.id = s.productid
                LEFT JOIN {local_intellicart_relations} ic ON ic.productid = ip.id AND ic.type = 'course'
                LEFT JOIN {course} c ON c.id = ic.instanceid AND ic.type = 'course'
                LEFT JOIN {course_categories} ca ON ca.id = c.category";
        $where = "s.id > 0 AND s.id IN (SELECT instanceid FROM {local_intellicart_users} WHERE userid = :suserid AND type = 'sessioninstructor' AND status = 1) $sql";
        $this->set_sql($fields, $from, $where, $params);
        $this->define_baseurl($PAGE->url);

        $this->scale_real = get_config('local_intelliboard', 'scale_real');
    }
    function col_visits($values) {
        return html_writer::tag("span", intval($values->visits), array("class"=>"info-average"));
    }
    function col_session_time($values) {

        if (userdate($values->session_time, '%Y/%m/%d') != userdate($values->timeend, '%Y/%m/%d')) {
            $time = userdate($values->session_time, get_string('strftimedate', 'langconfig')) . ', ' .
                    userdate($values->session_time, get_string('strftimetime', 'langconfig')) . ' - ' .
                    userdate($values->timeend, get_string('strftimedate', 'langconfig')) . ', ' .
                    userdate($values->timeend, get_string('strftimetime', 'langconfig'));
        } else {
            $time = userdate($values->session_time, get_string('strftimedate', 'langconfig')) . ', ' .
                    userdate($values->session_time, get_string('strftimetime', 'langconfig')) . ' - ' .
                    userdate($values->timeend, get_string('strftimetime', 'langconfig'));
        }

        return $time;
    }
    function col_timespend($values) {
        return ($values->timespend) ? seconds_to_time($values->timespend) : '-';
    }
    function col_learners($values) {
        $learners = intval($values->learners);
        $completed = intval($values->completed);
        $progress = ($learners and $completed)?(($completed/$learners) * 100): 0;
        $progress = round($progress, 0);

        $html = html_writer::start_tag("div",array("class"=>"intelliboard-tooltip","title"=>"Learners: $learners | Completed: $completed"));
        $html .= html_writer::start_tag("div",array("class"=>"intelliboard-progress xxl"));
        $html .= html_writer::tag("span", "&nbsp;&nbsp;" . intval($values->learners)."/".intval($values->completed) . "&nbsp;&nbsp;", array("style"=>"width:{$progress}%"));
        $html .= html_writer::end_tag("div");
        $html .= html_writer::end_tag("div");
        return $html;
    }

    function col_grade($values) {
        if($this->scale_real>0){
            return $values->grade;
        }else{
            $html = html_writer::start_tag("div", array("class" => "grade"));
            $html .= html_writer::tag("div", "", array("class" => "circle-progress", "data-percent" => (int)$values->grade));
            $html .= html_writer::end_tag("div");
            return $html;
        }
    }
    function col_modules($values) {
        return intval($values->modules);
    }

    function col_course($values) {
        global $CFG;

        return html_writer::link(new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$values->id)), $values->course, array("target"=>"_blank"));
    }
    function col_actions($values) {
        global  $PAGE;

        $html = html_writer::start_tag("div",array("style"=>"width:200px; margin: 5px 0;"));
        $html .= html_writer::link(new moodle_url($PAGE->url, array('action'=>'learners', 'id'=>$values->id, 'courseid'=>$values->courseid)), get_string('learners','local_intelliboard'), array('class' =>'btn btn-default', 'title' => get_string('learners','local_intelliboard')));
        $html .= "&nbsp";
        $html .= html_writer::link(new moodle_url($PAGE->url, array('action'=>'activities', 'id'=>$values->id, 'courseid'=>$values->courseid)), get_string('activities','local_intelliboard'), array('class' =>'btn btn-default', 'title' => get_string('activities','local_intelliboard')));
        $html .= html_writer::end_tag("div");
        return $html;
    }
}

class intelliboard_sessions_activities_grades_table extends table_sql {
    public $scale_real;

    function __construct($uniqueid, $sessionid = 0, $courseid = 0, $search = '', $mod = 0) {
        global $CFG, $PAGE, $DB;

        parent::__construct($uniqueid);

        $columns = array('activity');
        $headers = array(get_string('activity_name','local_intelliboard'));

        $columns[] =  'module';
        $headers[] =  get_string('type','local_intelliboard');

        $columns[] =  'completed';
        $headers[] =  get_string('in6','local_intelliboard');

        $columns[] =  'grade';
        $headers[] =  ucfirst(get_string('average_grade','local_intelliboard'));

        $columns[] =  'visits';
        $headers[] =  get_string('visits','local_intelliboard');

        $columns[] =  'timespend';
        $headers[] =  get_string('time_spent','local_intelliboard');

        $columns[] =  'actions';
        $headers[] =  get_string('actions','local_intelliboard');

        $this->define_headers($headers);
        $this->define_columns($columns);

        $params = array('c1'=>$courseid, 'c2'=>$courseid, 'c3'=>$courseid, 'c4'=>$courseid);
        $sql = "";
        if ($search) {
            $sql .= " AND " . $DB->sql_like('m.name', ":activity", false, false);
            $params['activity'] = "%$search%";
        }
        if ($mod) {
            $sql .= " AND cm.module IN (1,15,16,17,20,23)";
        }
        list($sql1, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);

        $params['sid1'] = $sessionid;
        $params['csid1'] = $courseid;
        $params['sid2'] = $sessionid;
        $params['csid2'] = $courseid;
        $params['sid3'] = $sessionid;
        $params['csid3'] = $courseid;

        $sql_columns = "";
        $modules = $DB->get_records_sql("SELECT m.id, m.name FROM {modules} m WHERE m.visible = 1");
        foreach($modules as $module){
            $sql_columns .= " WHEN m.name='{$module->name}' THEN (SELECT name FROM {".$module->name."} WHERE id = cm.instance)";
        }
        $sql_columns =  ($sql_columns) ? ", CASE $sql_columns ELSE 'none' END AS activity" : "'' AS activity";
        $grade_avg = intelliboard_grade_sql(true);
        $completion = intelliboard_compl_sql("", false);


        $usersin = "SELECT ei.userid
                      FROM {local_intellicart_logs} il
                 LEFT JOIN {enrol_intellicart} ei ON il.checkoutid = ei.checkoutid AND il.userid = ei.userid
                     WHERE il.type = 'product' AND ei.unenroldate = 0 AND ei.courseid = :csid AND il.sessionid = :sid";

        $fields = "
                cm.id,
                cm.course,
                m.name as module,
                cmc.completed,
                g.grade,
                l.visits,
                l.timespend,
                '' as actions
                $sql_columns";

        $from = "{course_modules} cm
                LEFT JOIN {modules} m ON m.id = cm.module
                LEFT JOIN (SELECT gi.iteminstance, gi.itemmodule, $grade_avg AS grade FROM {grade_items} gi, {grade_grades} g WHERE gi.itemtype = 'mod' AND g.itemid = gi.id AND g.finalgrade IS NOT NULL AND gi.courseid = :c1 AND g.userid IN (SELECT ei.userid
                      FROM {local_intellicart_logs} il
                 LEFT JOIN {enrol_intellicart} ei ON il.checkoutid = ei.checkoutid AND il.userid = ei.userid
                     WHERE il.type = 'product' AND ei.unenroldate = 0 AND ei.courseid = :csid1 AND il.sessionid = :sid1) GROUP BY gi.iteminstance, gi.itemmodule) as g ON g.iteminstance = cm.instance AND g.itemmodule = m.name
                LEFT JOIN (SELECT coursemoduleid, COUNT(id) AS completed FROM {course_modules_completion} WHERE $completion AND userid IN (SELECT ei.userid
                      FROM {local_intellicart_logs} il
                 LEFT JOIN {enrol_intellicart} ei ON il.checkoutid = ei.checkoutid AND il.userid = ei.userid
                     WHERE il.type = 'product' AND ei.unenroldate = 0 AND ei.courseid = :csid2 AND il.sessionid = :sid2) GROUP BY coursemoduleid) cmc ON cmc.coursemoduleid = cm.id
                LEFT JOIN (SELECT param, SUM(visits) AS visits, SUM(timespend) AS timespend FROM {local_intelliboard_tracking} WHERE page='module' AND courseid = :c2 AND userid IN (SELECT DISTINCT ra.userid FROM {role_assignments} ra, {context} ctx WHERE ctx.id = ra.contextid AND ctx.instanceid = :c4 AND ctx.contextlevel = 50 AND ra.roleid $sql1) AND userid IN (SELECT ei.userid
                      FROM {local_intellicart_logs} il
                 LEFT JOIN {enrol_intellicart} ei ON il.checkoutid = ei.checkoutid AND il.userid = ei.userid
                     WHERE il.type = 'product' AND ei.unenroldate = 0 AND ei.courseid = :csid3 AND il.sessionid = :sid3) GROUP BY param) l ON l.param=cm.id";
        $where = "cm.visible = 1 AND cm.course = :c3 $sql";

        $this->set_sql($fields, $from, $where, $params);
        $this->define_baseurl($PAGE->url);

        $this->scale_real = get_config('local_intelliboard', 'scale_real');
    }

    function col_grade($values) {
        if($this->scale_real>0){
            return $values->grade;
        }else{
            $html = html_writer::start_tag("div", array("class" => "grade"));
            $html .= html_writer::tag("div", "", array("class" => "circle-progress", "data-percent" => (int)$values->grade));
            $html .= html_writer::end_tag("div");
            return $html;
        }
    }
    function col_module($values) {
        return get_string('modulename', $values->module);
    }
    function col_completed($values) {
        return intval($values->completed);
    }
    function col_visits($values) {
        return html_writer::tag("span", intval($values->visits), array("class"=>"info-average"));
    }
    function col_timespend($values) {
        return ($values->timespend) ? seconds_to_time($values->timespend) : '-';
    }
    function col_activity($values) {
        global $CFG, $PAGE;

        return html_writer::link(new moodle_url("$CFG->wwwroot/mod/$values->module/view.php", array('id'=>$values->id)), $values->activity, array("target"=>"_blank"));
    }
    function col_actions($values) {
        global  $PAGE;

        return html_writer::link(new moodle_url($PAGE->url, array('action'=>'activity', 'cmid'=>$values->id, 'id'=>$values->course)), get_string('grades','local_intelliboard'), array('class' =>'btn btn-default', 'title' =>get_string('grades','local_intelliboard')));
    }
}

class intelliboard_sessions_learners_grades_table extends table_sql {
    public $scale_real;

    function __construct($uniqueid, $sessionid = 0, $courseid = 0, $search = '') {
        global $CFG, $PAGE, $DB;

        parent::__construct($uniqueid);

        $columns[] = 'learner';
        $headers[] = get_string('learner_name','local_intelliboard');

        $columns[] = 'email';
        $headers[] = get_string('email');

        $columns[] = 'enrolled';
        $headers[] = get_string('enrolled','local_intelliboard');

        $columns[] = 'timeaccess';
        $headers[] = get_string('in16','local_intelliboard');

        $columns[] = 'timecompleted';
        $headers[] = get_string('status','local_intelliboard');

        $columns[] = 'grade';
        $headers[] = get_string('grade','local_intelliboard');

        $columns[] = 'progress';
        $headers[] = get_string('completed_activities_resources','local_intelliboard');

        $columns[] =  'visits';
        $headers[] =  get_string('visits','local_intelliboard');

        $columns[] =  'timespend';
        $headers[] =  get_string('time_spent','local_intelliboard');

        $columns[] =  'actions';
        $headers[] =  get_string('actions','local_intelliboard');

        $this->define_headers($headers);
        $this->define_columns($columns);

        $params = array('c1'=>$courseid, 'c2'=>$courseid, 'sessionid' => $sessionid);
        $sql = "";
        if($search){
            $sql .= " AND " . $DB->sql_like("CONCAT(u.firstname, ' ', u.lastname)", ":learner", false, false);
            $params['learner'] = "%$search%";
        }
        list($sql_roles, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        $grade_single = intelliboard_grade_sql();
        $completion = intelliboard_compl_sql("cmc.");

        $fields = "ra.id, ra.userid, c.id as courseid,
            ra.timemodified as enrolled,
            ul.timeaccess,
            $grade_single AS grade,
            cc.timecompleted,
            u.email,
            CONCAT(u.firstname, ' ', u.lastname) as learner, l.timespend, l.visits, cmc.progress,
            il.sessionid, '' as actions";
        $from = "{role_assignments} ra
                LEFT JOIN {context} e ON e.id = ra.contextid AND e.contextlevel = 50
                LEFT JOIN {user} u ON u.id = ra.userid
                LEFT JOIN {course} c ON c.id = e.instanceid
                LEFT JOIN {user_lastaccess} ul ON ul.courseid = c.id AND ul.userid = u.id
                LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ra.userid
                LEFT JOIN {grade_items} gi ON gi.itemtype = 'course' AND gi.courseid = c.id
                LEFT JOIN {grade_grades} g ON g.userid = u.id AND g.itemid = gi.id AND g.finalgrade IS NOT NULL
                LEFT JOIN (SELECT cmc.userid, COUNT(DISTINCT cmc.id) as progress FROM {course_modules_completion} cmc, {course_modules} cm WHERE cm.visible = 1 AND cmc.coursemoduleid = cm.id $completion AND cm.completion > 0 AND cm.course = :c1 GROUP BY cmc.userid) cmc ON cmc.userid = u.id
                LEFT JOIN {enrol_intellicart} ei ON ei.courseid = c.id AND ei.userid = u.id AND ei.unenroldate = 0
                LEFT JOIN {local_intellicart_logs} il ON il.checkoutid = ei.checkoutid AND il.userid = ei.userid AND il.type = 'product'
                LEFT JOIN (SELECT t.userid,t.courseid, sum(t.timespend) as timespend, sum(t.visits) as visits FROM
                    {local_intelliboard_tracking} t GROUP BY t.courseid, t.userid) l ON l.courseid = c.id AND l.userid = u.id";
        $where = "ra.roleid $sql_roles AND e.instanceid = :c2 AND il.sessionid = :sessionid $sql";

        $this->set_sql($fields, $from, $where, $params);
        $this->define_baseurl($PAGE->url);
        $this->scale_real = get_config('local_intelliboard', 'scale_real');
    }

    function col_grade($values) {
        if($this->scale_real>0){
            return $values->grade;
        }else{
            $html = html_writer::start_tag("div",array("class"=>"grade"));
            $html .= html_writer::tag("div", "", array("class"=>"circle-progress", "data-percent"=>(int)$values->grade));
            $html .= html_writer::end_tag("div");
            return $html;
        }
    }
    function col_progress($values) {
        return intval($values->progress);
    }
    function col_visits($values) {
        return html_writer::tag("span", intval($values->visits), array("class"=>"info-average"));
    }
    function col_timespend($values) {
        return ($values->timespend) ? seconds_to_time($values->timespend) : '-';
    }
    function col_timecompleted($values) {
        return ($values->timecompleted) ? get_string('completed_on','local_intelliboard', date('m/d/Y', $values->timecompleted)) : get_string('incomplete','local_intelliboard');
    }
    function col_enrolled($values) {
        return ($values->enrolled) ? date('m/d/Y', $values->enrolled) : '';
    }
    function col_timeaccess($values) {
        return ($values->timeaccess) ? date('m/d/Y', $values->timeaccess) : '';
    }
    function col_learner($values) {
        global $CFG, $PAGE;

        return html_writer::link(new moodle_url($PAGE->url, array('action'=>'learner', 'userid'=>$values->userid, 'courseid'=>$values->courseid, 'id'=>$values->sessionid)), $values->learner);
    }
    function col_actions($values) {
        global  $PAGE;


        return html_writer::link(new moodle_url($PAGE->url, array('search'=>'','action'=>'learner', 'userid'=>$values->userid, 'courseid'=>$values->courseid, 'id'=>$values->sessionid)), get_string('grades','local_intelliboard'), array('class' =>'btn btn-default', 'title' =>get_string('grades','local_intelliboard')));
    }
}

class intelliboard_sessions_activity_grades_table extends table_sql {
    public $scale_real;

    function __construct($uniqueid, $cmid = 0, $sessionid = 0, $courseid = 0, $search = '') {
        global $CFG, $PAGE, $DB;

        parent::__construct($uniqueid);

        $columns[] = 'learner';
        $headers[] = get_string('learner_name','local_intelliboard');

        $columns[] = 'email';
        $headers[] = get_string('email');

        $columns[] = 'timecompleted';
        $headers[] = get_string('status','local_intelliboard');

        $columns[] = 'grade';
        $headers[] = get_string('grade','local_intelliboard');

        $columns[] = 'graded';
        $headers[] = get_string('graded','local_intelliboard');

        $columns[] =  'visits';
        $headers[] =  get_string('visits','local_intelliboard');

        $columns[] =  'timespend';
        $headers[] =  get_string('time_spent','local_intelliboard');

        $this->define_headers($headers);
        $this->define_columns($columns);

        $params = array('cmid'=>$cmid, 'sessionid'=>$sessionid, 'courseid'=>$courseid);
        $sql = "";
        if($search){
            $sql .= " AND " . $DB->sql_like('u.firstname', ":firstname", false, false);
            $params['firstname'] = "%$search%";
        }
        list($sql_roles, $sql_params) = $DB->get_in_or_equal(explode(',', get_config('local_intelliboard', 'filter11')), SQL_PARAMS_NAMED, 'r');
        $params = array_merge($params,$sql_params);
        $grade_single = intelliboard_grade_sql();

        $fields = "ra.id, ra.userid, c.id AS courseid,
            $grade_single AS grade,
            CASE WHEN g.timemodified > 0 THEN g.timemodified ELSE g.timecreated END AS graded,
            cc.timemodified AS timecompleted,
            cc.completionstate,
            u.email,
            CONCAT(u.firstname, ' ', u.lastname) as learner,
            l.timespend, l.visits, il.sessionid";
        $from = "{role_assignments} ra
                LEFT JOIN {context} e ON e.id = ra.contextid AND e.contextlevel = 50
                LEFT JOIN {user} u ON u.id = ra.userid
                LEFT JOIN {course} c ON c.id = e.instanceid
                LEFT JOIN {course_modules} cm ON cm.id = :cmid
                LEFT JOIN {modules} m ON m.id = cm.module
                LEFT JOIN {course_modules_completion} cc ON cc.coursemoduleid = cm.id AND cc.userid = ra.userid
                LEFT JOIN {grade_items} gi ON gi.itemtype = 'mod' AND gi.itemmodule = m.name AND gi.iteminstance = cm.instance
                LEFT JOIN {grade_grades} g ON g.userid = u.id AND g.itemid = gi.id AND g.finalgrade IS NOT NULL
                LEFT JOIN {local_intelliboard_tracking} l ON l.userid = u.id AND l.param = cm.id AND l.page = 'module'
                LEFT JOIN {enrol_intellicart} ei ON ei.courseid = c.id AND ei.userid = u.id AND ei.unenroldate = 0
                LEFT JOIN {local_intellicart_logs} il ON il.checkoutid = ei.checkoutid AND il.userid = ei.userid AND il.type = 'product' ";
        $where = "ra.roleid $sql_roles AND e.instanceid = :courseid AND il.sessionid = :sessionid $sql";

        $this->set_sql($fields, $from, $where, $params);
        $this->define_baseurl($PAGE->url);
        $this->scale_real = get_config('local_intelliboard', 'scale_real');
    }

    function col_grade($values) {
        if($this->scale_real>0){
            return $values->grade;
        }else{
            $html = html_writer::start_tag("div", array("class" => "grade"));
            $html .= html_writer::tag("div", "", array("class" => "circle-progress", "data-percent" => (int)$values->grade));
            $html .= html_writer::end_tag("div");
            return $html;
        }
    }
    function col_timecompleted($values) {
        if ($values->completionstate == 3) {
            return get_string('failed','local_intelliboard');
        } elseif ($values->completionstate == 2) {
            return get_string('passed','local_intelliboard');
        } elseif ($values->timecompleted and $values->completionstate == 1) {
            return get_string('completed_on','local_intelliboard', date('m/d/Y', $values->timecompleted));
        } else {
            return get_string('incomplete','local_intelliboard');
        }
    }
    function col_visits($values) {
        return html_writer::tag("span", intval($values->visits), array("class"=>"info-average"));
    }
    function col_timespend($values) {
        return ($values->timespend) ? seconds_to_time($values->timespend) : '-';
    }
    function col_graded($values) {
        return ($values->graded) ? date('m/d/Y', $values->graded) : '';
    }
    function col_learner($values) {
        global $CFG, $PAGE;

        return html_writer::link(new moodle_url($PAGE->url, array('action'=>'learner', 'userid'=>$values->userid, 'id'=>$values->sessionid, 'courseid'=>$values->courseid)), $values->learner);
    }
    function col_actions($values) {
        global  $PAGE;

        return html_writer::link(new moodle_url($PAGE->url, array('action'=>'learner', 'userid'=>$values->userid, 'id'=>$values->sessionid, 'courseid'=>$values->courseid)), get_string('grades','local_intelliboard'), array('class' =>'btn btn-default', 'title' =>get_string('grades','local_intelliboard')));
    }
}
