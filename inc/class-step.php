<?php
/**
 * Class CoursePress_Step
 *
 * @since 3.0
 * @package CoursePress
 */
class CoursePress_Step extends CoursePress_Unit {
	/**
	 * @var CoursePress_Unit The unit where the step belongs to.
	 */
	protected $unit;

	public function __construct( $step, $unit = false ) {
		if ( ! $step instanceof WP_Post )
			$step = get_post( $step );

		if ( ! $step instanceof  WP_Post ) {
			$this->is_error = true;

			return;
		}

		/*

		if ( $unit instanceof CoursePress_Unit ) {
			$this->__set( 'unit', $unit );
		} else {
			$unit = coursepress_get_unit( $step->post_parent );
			$this->__set( 'unit', $unit );
		}
		*/

		$this->__set( 'ID', $step->ID );
		$this->__set( 'post_title', $step->post_title );
		$this->__set( 'post_excerpt', $step->post_excerpt );
		$this->__set( 'post_content', $step->post_content );
		$this->__set( 'post_name', $step->post_name );
		$this->__set( 'unit_id', $step->post_parent );
		$this->__set( 'post_parent', $step->post_parent );
		$this->__set( 'course_id', get_post_field( 'post_parent', $step->post_parent ) );

		// Setup meta-data
		$this->setUpStepMeta();
	}

	protected function get_keys() {
		$keys = array(
			'module_order',
			'show_title',
			'mandatory',
			'assessable',
			'use_timer',
			'allow_retries',
			'retry_attempts',
			'minimum_grade',
			'duration',
			'module_type',
			'module_page',
            'show_content',
            'allowed_file_types'
		);

		return $keys;
	}

	function setUpStepMeta() {
		$keys = $this->get_keys();
		$id = $this->__get( 'ID' );

		foreach ( $keys as $key ) {
			$value = get_post_meta( $id, $key, true );

			if ( 'on' == $value ) {
				$value = true;
			}
			if ( 'module_type' == $key ) {
				if ( 'input-checkbox' == $value ) {
					$value = 'input-quiz';
				}
			}

			$this->__set( $key, $value );
			$this->__set( 'meta_' . $key, $value );
		}

		$this->__set( 'preview', true );
	}

	function get_settings() {
		$keys = $this->get_keys();
		$settings = array();

		foreach ( $keys as $key ) {
			$value = $this->__get( $key );
			$settings[ $key ] = $value;
		}

		return $settings;
	}

	function update_settings( $key, $value ) {
		$step_id = $this->__get( 'ID' );
		$settings = $this->get_settings();

		if ( true === $key ) {
			$settings = $value;

			foreach ( $settings as $key => $value ) {
				update_post_meta( $step_id, $key, $value );
				$this->__set( $key, $value );
			}
		} else {
			$settings[ $key ] = $value;
			update_post_meta( $step_id, $key, $value );
			$this->__set( $key, $value );
		}

		return $settings;
	}

	function get_the_title() {
		return $this->__get( 'post_title' );
	}

	function get_unit() {
		$unit_id = $this->__get( 'post_parent' );

		return coursepress_get_unit( $unit_id );
	}

	function get_permalink() {
		$module_number = $this->__get( 'module_page' );

		if ( ! $module_number ) {
			$module_number = 1;
		}
		$post_name = $this->__get( 'post_name' );
		$unit = $this->get_unit();
		$course = $unit->get_course();

		if ( $course->is_with_modules() && (int) $module_number > 0 ) {

			$modules = $unit->get_modules();

			if ( ! empty( $modules ) && $modules[$module_number] ) {
				$module = $modules[ $module_number ];

				return $module['url'] . trailingslashit( $post_name );
			}
		} else {
			if ( $this->unit ) {
				return $this->unit->get_unit_url() . trailingslashit( $post_name );
			}
		}
	}

	function is_answerable() {
		$module_type = $this->__get( 'module_type' );
		$is_answerable = preg_match( '%input-%', $module_type );

		return $is_answerable;
	}

	function has_seen_by( $user_id ) {
		$user = coursepress_get_user( $user_id );

		$step_id = $this->__get( 'ID' );
		$course_id = $this->__get( 'course_id' );
		$unit_id = $this->__get( 'unit_id' );

		if ( is_wp_error( $user )
			|| ! $user->is_enrolled_at( $course_id ) ) {
			return false;
		}

		$progress = $user->get_completion_data( $course_id );

		return coursepress_get_array_val( $progress, 'completion/' . $unit_id . '/modules_seen/' . $step_id );
	}

	function is_completed_by( $user_id = 0 ) {
		$user = coursepress_get_user( $user_id );

		$step_id   = $this->__get( 'ID' );
		$course_id = $this->__get( 'course_id' );
		$unit_id   = $this->__get( 'unit_id' );

		if ( is_wp_error( $user )
		     || ! $user->is_enrolled_at( $course_id )
		) {
			return false;
		}

		$step_progress = $user->get_step_progress( $course_id, $unit_id, $step_id );

		return (int) $step_progress >= 100;
	}

	function is_previous_step_completed_by( $user_id = 0 ) {
		$user = coursepress_get_user( $user_id );
		$course_id = $this->__get( 'course_id' );

		if ( is_wp_error( $user )
			|| ! $user->is_enrolled_at( $course_id ) )
			return false;

		if ( ( $prev = $this->__get( 'previousStep' ) ) ) {
			return $prev->has_completed_by( $user_id );
		}

		return true;
	}

	function is_show_title() {
		return $this->__get( 'show_title' );
	}

	function is_required() {
		return $this->__get( 'mandatory' );
	}

	function is_assessable() {
		return $this->__get( 'assessable' );
	}

	function get_course() {
		$unit = $this->get_unit();

		return $unit->get_course();
	}

	function get_user_response( $user_id = 0 ) {
		$user = coursepress_get_user( $user_id );
		$unit = $this->get_unit();
		$course = $unit->get_course();
		$response = $user->get_response( $course->ID, $unit->ID, $this->__get( 'ID' ) );

		return ! empty( $response ) ? $response['response'] : false;
	}

	function get_previous_step() {
		$user = coursepress_get_user();
		$unit = $this->get_unit();
		$course = $unit->get_course();
		$with_modules = $course->is_with_modules();
		$has_access = $user->has_access_at( $course->__get( 'ID' ) );
		$module_page = $this->__get( 'module_page' );
		$steps = $unit->get_steps( ! $has_access, $with_modules, $module_page );
		$prev = false;

		if ( $steps ) {
			$previous = array();

			foreach ( $steps as $step ) {
				$previous[] = $step;

				if ( $step->__get('ID') == $this->__get( 'ID' ) ) {
					break;
				}
			}
			array_pop( $previous );
			$prev = array_pop( $previous );
		}

		return $prev;
	}

	function is_preview() {
		return ! empty( $_REQUEST['preview'] );
	}

	function get_next_step() {
		$user = coursepress_get_user();
		$unit = $this->get_unit();
		$course = $unit->get_course();
		$with_modules = $course->is_with_modules();
		$has_access = $user->has_access_at( $course->__get( 'ID' ) );
		$module_page = $this->__get( 'module_page' );
		$steps = $unit->get_steps( ! $has_access, $with_modules, $module_page );
		$next = false;

		if ( $steps ) {
			$found = false;

			foreach ( $steps as $step ) {
				if ( $found ) {
					$next = $step;
					break;
				}

				if ( $step->__get('ID') == $this->__get( 'ID' ) ) {
					$found = true;
				}
			}
		}

		return $next;
	}

	/** Must be overriden in a sub class */
	function get_question() {}

	/** Must be overriden in a sub class */
	function get_answer_template( $user_id = 0 ) {
		$template = '';

		if ( $this->is_answerable() ) {
			$unit = $this->get_unit();
			$course = $unit->get_course();
			$user = coursepress_get_user( $user_id );
			$status = $user->get_step_grade_status( $course->ID, $unit->ID, $this->ID );
			$statuses = array(
				'failed' => __( 'Failed', 'cp' ),
				'pass' => __( 'Pass', 'cp' ),
				'pending' => __( 'Pending', 'cp' ),
			);

			if ( ! empty( $status ) ) {
				$template .= coursepress_create_html(
					'span',
					array( 'class' => 'step-status step-status-' . $status ),
					$statuses[ $status ]
				);
			}

			$allow_retries = $this->__get( 'allow_retries' );

			if ( 'pass' !== $status && $allow_retries ) {
				$template .= coursepress_create_html(
					'button',
					array(
						'type' => 'button',
						'class' => 'button cp-button cp-button-retry'
					),
					__( 'Retry', 'cp' )
				);
			}
		}

		return $template;
	}

	function validate_response( $response = array() ) {}

	function template( $user_id = 0 ) {
		$template = '';
		$user = coursepress_get_user( $user_id );
		$course = coursepress_get_course();
		$course_id = $course->__get( 'ID' );
		$class = 'course-module-step-template step-template-' . $this->__get( 'module_type' );

		if ( ! $user->is_enrolled_at( $course_id ) && ! $this->is_preview() ) {
			$template .= coursepress_create_html( 'p', array(), __( 'You are not enrolled to this course!', 'cp' ) );

			return $template;
		}

		if ( $this->is_show_title() ) {
			$attr = array( 'class' => 'module-step-title' );
			$template .= $this->create_html( 'h4', $attr, $this->get_the_title() );
		}

		if ( $this->is_required() ) {
			$required = $this->create_html( 'span', false, __( '* Required', 'cp' ) );
			$template .= $this->create_html( 'div', array( 'class' => 'required' ), $required );
		}
		$error = coursepress_get_cookie( 'cp_step_error' );

		if ( $error ) {
			$template .= $this->create_html(
				'p',
				array( 'class' => 'error cp-error' ),
				$error
			);
		}

		$attr = array( 'class' => 'course-module-step-description' );
		$description = apply_filters( 'the_content', $this->__get( 'post_content' ) );
		$template .= $this->create_html( 'div', $attr, $description );

		$question = $this->get_question();

		if ( ! empty( $question ) ) {
			$attr = array( 'class' => 'course-module-step-question' );
			$template .= $this->create_html( 'div', $attr, $question );
		}

		$response = $this->get_user_response( $user->ID );
		if ( $this->has_seen_by( $user->ID ) && ! empty( $response ) ) {
			$answer_template = $this->get_answer_template( $user->ID );
			$class .= ' module-step-seen';
		}

		if ( ! empty( $answer_template ) ) {
			$attr = array( 'class' => 'course-module-answer' );
			$template .= $this->create_html( 'div', $attr, $answer_template );
		}

		return $this->create_html( 'div', array( 'class' => $class ), $template );
	}
}
