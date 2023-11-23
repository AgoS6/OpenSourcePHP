<?php

namespace Koseu\Controllers;

use Koseu\Core\Application;
use Tsugi\Util\U;
use Tsugi\Util\LTI;
use Tsugi\Core\LTIX;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Discussions {

    const ROUTE = '/discussions';

    public static function routes(Application $app, $prefix=self::ROUTE) {
        $app->router->get($prefix, 'Discussions@get');
        $app->router->get($prefix.'/', 'Discussions@get');
        $app->router->get($prefix.'_launch/{anchor}', function(Request $request, $anchor = null) use ($app) {
            return Discussions::launch($app, $anchor);
        });
    }

    public function get(Request $request)
    {
        global $CFG, $OUTPUT;

        if ( ! isset($CFG->lessons) ) {
            die_with_error_log('Cannot find lessons.json ($CFG->lessons)');
        }

        // Load the Lesson
        $l = new \Tsugi\UI\Lessons($CFG->lessons);

        $OUTPUT->header();
        $OUTPUT->bodyStart();
        $menu = false;
        $OUTPUT->topNav();
        $OUTPUT->flashMessages();
        $l->renderDiscussions(false);
        $OUTPUT->footer();


    }

    public static function launch(Application $app, $anchor=null)
    {
        global $CFG;
        $tsugi = $app['tsugi'];

        $path = U::rest_path();
        $redirect_path = U::addSession($path->parent);
        if ( $redirect_path == '') $redirect_path = '/';

        if ( ! isset($CFG->lessons) ) {
            $app->tsugiFlashError(__('Cannot find lessons.json ($CFG->lessons)'));
            return redirect($redirect_path);
        }

        /// Load the Lesson
        $l = new \Tsugi\UI\Lessons($CFG->lessons);
        if ( ! $l ) {
            $app->tsugiFlashError(__('Cannot load lessons.'));
            return redirect($redirect_path);
        }

        $lti = $l->getLtiByRlid($anchor);
        if ( ! $lti ) {
            $app->tsugiFlashError(__('Cannot find lti resource link id'));
            return redirect($redirect_path);
        }

        	// Check that the session has the minimums...
        $secret = filter_input(INPUT_SESSION, 'secret', FILTER_SANITIZE_STRING);
        $context_key = filter_input(INPUT_SESSION, 'context_key', FILTER_SANITIZE_STRING);
        $user_key = filter_input(INPUT_SESSION, 'user_key', FILTER_SANITIZE_STRING);
        $displayname = filter_input(INPUT_SESSION, 'displayname', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_SESSION, 'email', FILTER_SANITIZE_EMAIL);
         
        if ($secret && $context_key && $user_key && $displayname && $email) {
            // All good
        } else {
            $app->tsugiFlashError(__('Missing session data required for launch'));
            return redirect($redirect_path);
        }
         
        $oauth_consumer_key = filter_input(INPUT_SESSION, 'oauth_consumer_key', FILTER_SANITIZE_STRING);
        $key = $oauth_consumer_key ? $oauth_consumer_key : false;
         
        $secret = false;
        if ($secret) {
            $secret = LTIX::decrypt_secret($secret);
        }
        
        $resource_link_id = $lti->resource_link_id;
        $parms = array(
            'lti_message_type' => 'basic-lti-launch-request',
            'resource_link_id' => $resource_link_id,
            'resource_link_title' => $lti->title,
            'tool_consumer_info_product_family_code' => 'tsugi',
            'tool_consumer_info_version' => '1.1',
            'context_id' => filter_input(INPUT_SESSION, 'context_key', FILTER_SANITIZE_STRING) ? filter_input(INPUT_SESSION, 'context_key', FILTER_SANITIZE_STRING) : null,
            'context_label' => $CFG->context_title,
            'context_title' => $CFG->context_title,
            'user_id' => filter_input(INPUT_SESSION, 'user_key', FILTER_SANITIZE_STRING) ? filter_input(INPUT_SESSION, 'user_key', FILTER_SANITIZE_STRING) : null,
            'lis_person_name_full' => filter_input(INPUT_SESSION, 'displayname', FILTER_SANITIZE_STRING);,
            'lis_person_contact_email_primary' => filter_input(INPUT_SESSION, 'email', FILTER_SANITIZE_STRING);,
            'roles' => 'Learner'
        );
        if ( isset($_SESSION['avatar']) ) $parms['user_image'] = $_SESSION['avatar'];

        if ( isset($lti->custom) ) {
            foreach($lti->custom as $custom) {
                if ( isset($custom->value) ) {
                    $parms['custom_'.$custom->key] = $custom->value;
                }
                if ( isset($custom->json) ) {
                    $parms['custom_'.$custom->key] = json_encode($custom->json);
                }
            }
        }

        $return_url = $path->parent . '/' . str_replace('_launch', '', $path->controller) ;
        $parms['launch_presentation_return_url'] = $return_url;

        $sess_key = 'tsugi_top_nav_'.$CFG->wwwroot;
        if ( isset($_SESSION[$sess_key]) ) {
            $parms['ext_tsugi_top_nav'] = $_SESSION[$sess_key];
        }

        $form_id = "tsugi_form_id_".bin2Hex(openssl_random_pseudo_bytes(4));
        $parms['ext_lti_form_id'] = $form_id;

        $endpoint = $lti->launch;
        \Tsugi\UI\Lessons::absolute_url_ref($endpoint);
        $parms = LTI::signParameters($parms, $endpoint, "POST", $key, $secret,
            "Finish Launch", $CFG->wwwroot, $CFG->servicename);

        $content = LTI::postLaunchHTML($parms, $endpoint, false /*debug */);
        print($content);
        return "";
    }

}
