<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if (!class_exists('Student')) {

    class Student extends WP_User {

        var $first_name = '';
        var $last_name = '';
        var $courses_number = 0;
        var $details = array();

        function __construct($ID, $name = '') {
            global $wpdb;

            if ($ID != 0) {
                parent::__construct($ID, $name);
            }

            /* Set meta vars */

            $this->first_name = get_user_meta($ID, 'first_name', true);
            $this->last_name = get_user_meta($ID, 'last_name', true);
            $this->courses_number = $this->get_courses_number();
        }

        function Student($ID, $name = '') {

            $this->__construct($ID, $name);
        }

        //Check if the user is alrady enrolled in the course
        function user_enrolled_in_course($course_id) {
            if (get_user_meta($this->ID, 'enrolled_course_date_' . $course_id, true)) {
                return true;
            } else {
                return false;
            }
        }

        //Enroll student in the course
        function enroll_in_course($course_id, $class = '', $group = '') {

            $current_time = current_time('mysql');

            update_user_meta($this->ID, 'enrolled_course_date_' . $course_id, $current_time); //Link courses and student (in order to avoid custom tables) for easy MySql queries (get courses stats, student courses, etc.)
            update_user_meta($this->ID, 'enrolled_course_class_' . $course_id, $class);
            update_user_meta($this->ID, 'enrolled_course_group_' . $course_id, $group);

            return true;
            //TO DO: add new payment status if it's paid
        }

        //Unenroll student from the course
        function unenroll_from_course($course_id, $keep_unenrolled_record = true) {

            $current_time = current_time('mysql');

            delete_user_meta($this->ID, 'enrolled_course_date_' . $course_id);
            delete_user_meta($this->ID, 'enrolled_course_class_' . $course_id);
            delete_user_meta($this->ID, 'enrolled_course_group_' . $course_id);

            if ($keep_unenrolled_record) {
                update_user_meta($this->ID, 'unenrolled_course_date_' . $course_id, $current_time); //keep a record of all unenrolled students
            }
        }

        //Unenroll from all courses

        function unenroll_from_all_courses() {
            $courses = $this->get_enrolled_courses_ids();

            foreach ($courses as $course_id) {
                $this->unenroll_from_course($course_id);
            }
        }

        function get_enrolled_courses_ids() {
            global $wpdb;
            $enrolled_courses = array();
            $courses = $wpdb->get_results("SELECT meta_key FROM $wpdb->usermeta WHERE meta_key LIKE 'enrolled_course_date_%' AND user_id = " . $this->ID, OBJECT);

            foreach ($courses as $course) {
                $enrolled_courses[] = str_replace('enrolled_course_date_', '', $course->meta_key);
            }

            return $enrolled_courses;
        }

        //Get number of courses student enrolled in
        function get_courses_number() {
            global $wpdb;
            $courses_count = $wpdb->get_var("SELECT COUNT(*) as cnt FROM $wpdb->usermeta WHERE user_id = " . $this->ID . " AND meta_key LIKE 'enrolled_course_date_%'");
            return $courses_count;
        }

        function delete_student() {
            wp_delete_user($this->ID); //without reassign
        }

        function has_access_to_course($course_id = '', $user_id = '') {
            global $wpdb;

            if ($user_id == '') {
                $user_id = get_current_user_id();
            }

            if ($course_id == '') {
                return false;
            }

            $courses_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) as cnt FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = '%s'", $user_id, 'enrolled_course_date_' . $course_id));

            if ($courses_count >= 1) {
                return true;
            } else {
                return false;
            }
        }

        function update_student_data($student_data) {
            if (wp_update_user($student_data)) {
                return true;
            } else {
                return false;
            }
        }

        function update_student_group($course_id, $group) {
            if (update_user_meta($this->ID, 'enrolled_course_group_' . $course_id, $group)) {
                return true;
            } else {
                return false;
            }
        }

        function update_student_class($course_id, $class) {
            if (update_user_meta($this->ID, 'enrolled_course_class_' . $course_id, $class)) {
                return true;
            } else {
                return false;
            }
        }

        function add_student($student_data) {
            $student_data['role'] = 'student';
            return wp_insert_user($student_data);
        }

    }

}
?>
