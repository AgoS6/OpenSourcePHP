<?php

namespace Koseu\Controllers;

use Tsugi\Lumen\Application;
use Symfony\Component\HttpFoundation\Request;

use \Tsugi\Grades\GradeUtil;

class Assignments {

    const ROUTE = '/assignments';

    public static function routes(Application $app, $prefix=self::ROUTE) {
        $app->router->get($prefix, 'Assignments@get');
        $app->router->get($prefix.'/', 'Assignments@get');
    }

    public function get(Request $request)
    {
        global $CFG, $OUTPUT;

        if ( ! isset($CFG->lessons) ) {
            die_with_error_log('Cannot find lessons.json ($CFG->lessons)');
        }

        // Load the Lesson
        $l = new \Tsugi\UI\Lessons($CFG->lessons);

        // Load all the Grades so far
        $allgrades = array();
        $id = (int)$_SESSION['id'];
        $contextId = $_SESSION['context_id'];
        if ( isset($id) && isset($contextId)) {
            $rows = GradeUtil::loadGradesForCourse($id, $contextId);
            foreach($rows as $row) {
                $allgrades[$row['resource_link_id']] = $row['grade'];
            }
        }

        $OUTPUT->header();
        $OUTPUT->bodyStart();
        $menu = false;
        $OUTPUT->topNav();
        $OUTPUT->flashMessages();
        $l->renderAssignments($allgrades, false);
        $OUTPUT->footer();
    }
}
