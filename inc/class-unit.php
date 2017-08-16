<?php
/**
 * Class CoursePress_Unit
 *
 * @since 3.0
 * @package CoursePress
 */
class CoursePress_Unit extends CoursePress_Utility {
	/**
	 * @var CoursePress_Course The parent course of this unit.
	 */
	protected $course;

	var $previous_link = '';
	var $next_link = '';

	public function __construct( $unit, $course = false ) {
		if ( ! $unit instanceof WP_Post ) {
			$unit = get_post( $unit );
		}

		if ( ! $unit instanceof WP_Post ) {
			return $this->wp_error();
		}

		//if ( $course instanceof CoursePress_Course ) {
		//	$this->__set( 'course', $course );
		//}

		$this->__set( 'ID', $unit->ID );
		$this->__set( 'post_title', $unit->post_title );
		$this->__set( 'post_content', $unit->post_content );
		$this->__set( 'post_name', $unit->post_name );
		$this->__set( 'post_parent', $unit->post_parent );
		$this->__set( 'course_id', $unit->post_parent );

		// Setup meta-data
		$this->setUpMeta();
	}

	function wp_error() {
		return new WP_Error( 'wrong_param', __( 'Unable to initialized CoursePress_Unit!', 'cp' ) );
	}

	function setupMeta() {
		$id = $this->__get( 'ID' );
        $defaults = array(
	        'unit_availability' => 'instant',
	        'unit_date_availability' => '',
	        'unit_delay_days' => 0,
	        'force_current_unit_completion' => false,
	        'force_current_unit_successful_completion' => false,
	        'visible' => true,
	        'preview' => true,
	        'unit_feature_image' => '',
	        'use_feature_image' => '',
	        'use_description' => false
        );

		$date_format = coursepress_get_option( 'date_format' );
		$time_now = current_time( 'timestamp' );

		foreach ( $defaults as $key => $default_value ) {
			$value = get_post_meta( $id, $key, true );
			$value = maybe_unserialize( $value );

			if ( ! $value ) {
			    $value = $default_value;
            }

			if ( 'unit_date_availability' == $key && ! is_array( $value ) ) {
				$timestamp = strtotime( $value, $time_now );
				$value = date_i18n( $date_format, $timestamp );
				$this->__set( 'unit_availability_date_timestamp', $timestamp );
				$this->__set( 'unit_availability_date', $value );
			}

			if ( 'on' == $value || 'yes' == $value )
				$value = true;

			$this->__set( $key, $value );
			$this->__set( 'meta_' . $key, $value );
		}

		$this->__set( 'preview', true );
	}

	function get_settings() {
		$defaults = array(
			'unit_availability' => 'instant',
			'unit_date_availability' => '',
			'unit_delay_days' => 0,
			'force_current_unit_completion' => false,
			'force_current_unit_successful_completion' => false,
			'visible' => true,
			'preview' => true,
			'unit_feature_image' => '',
			'use_feature_image' => '',
			'use_description' => false
		);

		$settings = array();

		foreach ( $defaults as $key => $value ) {
			$value = $this->__get( $key );
			$settings[ $key ] = $value;
		}

		return $settings;
	}

	function update_settings( $key, $value ) {
		$settings = $this->get_settings();

		if ( true === $key ) {
			$settings = $value;
		} else {
			$settings[ $key ] = $value;
		}

		$unit_id = $this->__get( 'ID' );

		foreach ( $settings as $key => $value ) {
			update_post_meta( $unit_id, $key, $value );
			$this->__set( $key, $value );
		}

		return $settings;
	}

	/**
	 * Helper method to get the unit's parent course object.
	 *
	 * @return CoursePress_Course|null|WP_Error
	 */
	function get_course() {
		if ( $this->__get( 'course' ) ) {
            return $this->__get('course');
        }

		$course_id = $this->__get( 'course_id' );
		$course = coursepress_get_course( $course_id );

		$this->__set( 'course', $course );

		return $course;
	}

	function get_the_title() {
		return $this->__get( 'post_title' );
	}

	function get_description() {
		if ( $this->__get( 'use_description' ) ) {
			$description = $this->__get( 'post_content' );

			// @todo: Filter description here

			return $description;
		}

		return null;
	}

	function get_summary( $length = 220 ) {
			$description = $this->get_description();

			if ( ! empty( $description ) ) {
				$description = wp_strip_all_tags( $description );
				$length++;

				if ( mb_strlen( $description ) > $length ) {
					$sub = mb_substr( $description, 0, $length - 5 );
					$words = explode( ' ', $sub );
					$cut = ( mb_strlen( $words[ count( $words ) - 1 ] ) );

					if ( $cut < 0 )
						return mb_substr( $sub, 0, $cut );
					else
						return $sub;
				}

				return $description;
			}

		return null;
	}

	function get_feature_image_url() {
		if ( $this->__get( 'use_feature_image' ) ) {
			return $this->__get( 'unit_feature_image' );
		}

		return null;
	}

	function get_feature_image( $width = 150, $height = 150 ) {
		$feature_image = $this->get_feature_image_url();

		if ( ! empty( $feature_image ) ) {
			$feature_image = $this->create_html(
				'img',
				array(
					'src' => esc_url( $feature_image ),
					'width' => $width,
					'height' => $height,
					'alt' => 'unit-feature-image',
					'class' => 'unit-feature-image',
				)
			);

			return $feature_image;
		}

		return null;
	}

	function is_available() {
		$availability = $this->__get( 'unit_availability' );
		$time_now = current_time( 'timestamp' );
		$available = false;

		if ( 'instant' == $availability ) {
			$available = true;
		} elseif ( 'on_date' == $availability ) {
			$date = $this->__get( 'unit_availability_date' );

			if ( $time_now >= $date )
				$available = true;

		} elseif ( 'after_delay' == $availability ) {
			$course_start = $this->course->__get( 'course_start_date_timestamp' );
			$days = (int) $this->__get( 'unit_delay_days' );

			if ( $days > 0 ) {
				$days = $course_start + ( $days * DAY_IN_SECONDS );

				if ( $time_now >= $days )
					$available = true;
			}
		}

		return $available;
	}

	public function is_accessible_by( $user_id = 0 ) {
		$user = coursepress_get_user( $user_id );
		$available = $this->is_available();

		if ( ! $available )
			return false;

		$previousUnit = $this->__get( 'previousUnit' );

		if ( ! $previousUnit ) {
			return $available;
		}

		$course_id = $this->__get( 'post_parent' );
		$previous_unit_id = $previousUnit->__get( 'ID' );
		$force_unit_completion = $this->__get( 'force_current_unit_completion' );

		if ( $force_unit_completion
		     && ! $user->is_unit_completed( $course_id, $previous_unit_id ) )
				return false;

		$force_unit_pass = $this->__get( 'force_current_unit_successful_completion' );

		if ( $force_unit_pass
			&& ! $user->has_pass_course_unit( $course_id, $previous_unit_id ) )
				return false;

		return $available;
	}

	function is_module_accessible_by( $user_id, $module ) {
		$user = coursepress_get_user( $user_id );

		if ( is_wp_error( $user ) )
			return false;

		if ( ! $module['previous_module'] )
			return true;

		$previous_module = $module['previous_module'];
		$course_id = $this->__get( 'post_parent' );
		$unit_id = $this->__get( 'ID' );

		return $user->is_module_completed( $course_id, $unit_id, $previous_module['id'] );
	}

	function get_unit_url() {
		$course = $this->get_course();

		if ( $course ) {
			$unit_slug = coursepress_get_setting( 'slugs/units', 'units' );
			$post_name = $this->__get( 'post_name' );

			return $course->get_permalink() . trailingslashit( $unit_slug ) . trailingslashit( $post_name );
		}

		return null;
	}

	function get_permalink() {
		return $this->get_unit_url();
	}

	function get_previous_unit() {
		$previous = $this->__get( 'previousUnit' );

		if ( ! $previous ) {
			$course = $this->get_course();
			$units = $course->get_units();

			if ( $units ) {
				$prevs = array();

				foreach ( $units as $unit ) {
					$prevs[] = $unit;

					if ( $this->__get( 'ID' ) == $unit->__get( 'ID' ) ) {
						break;
					}
				}
				array_pop( $prevs );
				$previous = array_pop( $prevs );
			}
		} else {
			return $previous;
		}

		if ( $previous && $previous->__get( 'ID' ) != $this->__get( 'ID' ) ) {
			$this->__set( 'previousUnit', $previous );
			return $previous;
		}

		return false;
	}

	function get_next_unit() {
		$next = $this->__get( 'nextUnit' );

		if ( ! $next ) {
			$course = $this->get_course();
			$units = $course->get_units();
			$found = false;

			if ( $units ) {
				foreach ( $units as $unit ) {
					if ( $found ) {
						$next = $unit;
						break;
					}

					if ( $this->__get( 'ID' ) == $unit->__get( 'ID' ) ) {
						$found = true;
					}
				}
			}
		} else {
			return $next;
		}

		if ( $next && $next->__get( 'ID' ) != $this->__get( 'ID' ) ) {
			$this->__set( 'nextUnit', $next );

			return $next;
		}

		return false;
	}

	function get_previous_module( $module_id ) {
		$modules = $this->get_modules();
		$prevs = array();
		$prev = false;

		if ( $modules ) {
			foreach ( $modules as $module ) {
				$prevs[] = $module;

				if ( $module['id'] == $module_id ) {
					break;
				}
			}
			array_pop( $prevs );
			$prev = array_pop( $prevs );
		}

		if ( $prev && $prev['id'] != $module_id ) {
			return $prev;
		}
		return false;
	}

	function get_next_module( $module_id ) {
		$modules = $this->get_modules();
		$next = false;

		if ( $modules ) {
			$found = false;

			foreach ( $modules as $module ) {

				if ( $found ) {
					$next = $module;
					break;
				}

				if ( $module['id'] == $module_id ) {
					$found = true;
				}
			}
		}

		if ( $next && $next['id'] != $module_id ) {
			return $next;
		}

		return false;
	}

	function get_modules() {
		if ( $this->__get( 'unit_modules_list' ) ) {
			return $this->__get( 'unit_modules_list' );
		}

		$id = $this->__get( 'ID' );
		$modules = get_post_meta( $id, 'course_modules', true );

		if ( empty( $modules ) ) {
			$modules = array();
			// Call legacy grouping style
			$pages = get_post_meta( $id, 'page_title', true );
			$page_descriptions = get_post_meta( $id, 'page_description', true );

			if ( ! empty( $pages ) ) {
				foreach ( $pages as $page_id => $page_title ) {
					$page_number = str_replace( 'page_', '', $page_id );

					$modules[ $page_number ] = array(
						'title' => $page_title,
						'preview' => true,
						'description' => coursepress_get_array_val( $page_descriptions, $page_id ),
					);
				}
			}
			// @todo: Save then delete
		}

		$previous_module = false;
		foreach ( $modules as $pos => $module ) {
			$slug = sanitize_title( $module['title'], '' );
			$module['id'] = $pos;
			$module['slug'] = $slug;
			$module['url'] = $this->get_unit_url() . trailingslashit( $slug );
			//$module['previous_module'] = $previous_module;
			$modules[ $pos ] = $module;
			$previous_module = $module;
		}

		$this->__set( 'unit_modules_list', $modules );

		return $modules;
	}

	/**
	 * Get course modules with all it's child steps.
	 *
	 * @param bool $published
	 *
	 * @return array|mixed
	 */
	function get_modules_with_steps( $published = true ) {
		if ( $this->__get( 'unit_modules_with_steps' ) ) {
            return $this->__get('unit_modules_with_steps');
        }

		$modules = $this->get_modules();

		foreach ( $modules as $pos => $module ) {
			$module['steps'] = $this->get_steps( $published, true, $pos );
			$modules[ $pos ] = $module;
		}

		$this->__set( 'unit_modules_with_steps', $modules );

		return $modules;
	}

	/**
	 * Get course steps without parent modules.
	 *
	 * @param bool $published
	 * @param bool $with_module
	 * @param bool $module_id
	 *
	 * @return array
	 */
	function get_steps( $published = true, $with_module = false, $module_id = false ) {
		global $CoursePress_Core;

		$key = implode( '-', array( $published, $with_module, $module_id ) );
		$key = 'unit_steps_list' . $key;

		if ( $this->__get( $key ) ) {
			return $this->__get( $key );
		}

		$args = array(
			'post_type' => $CoursePress_Core->step_post_type,
			'post_status' => $published ? 'publish' : 'any',
			'posts_per_page' => -1,
			'post_parent' => $this->__get( 'ID' ),
			'suppress_filter' => true,
			'orderby' => 'menu_order',
			'order' => 'ASC',
		);

		if ( $with_module ) {
			$args['meta_key'] = 'module_page';
			$args['meta_value_num'] = intval($module_id);
		}

		$results = get_posts( $args );
		$steps = array();

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$stepClass = coursepress_get_course_step( $result->ID );

				if ( ! is_wp_error( $stepClass ) && is_object( $stepClass ) ) {
					if ( 'input-form' === $stepClass->__get( 'module_type' ) ) {
						// @todo: Handle form module?
						continue;
					}
					$steps[ $result->ID ] = $stepClass;
				}
			}
		}

		$this->__set( $key, $steps );

		return $steps;
	}

	function get_module_by_slug( $slug ) {
		$modules = $this->get_modules();

		if ( $modules ) {
			foreach ( $modules as $module ) {
				if ( ! empty( $module['slug'] ) && $slug == $module['slug'] ) {
					return $module;
				}
			}
		}

		return false;
	}

	function get_module_by_id( $module_id ) {
		$modules = $this->get_modules();

		if ( isset( $modules[ $module_id ] ) ) {
			return $modules[ $module_id ];
		}

		return null;
	}

	function get_step_by_id( $step_id ) {
		$step_class = coursepress_get_course_step( $step_id );

		if ( $step_class ) {
			$step_class->__set( 'unit', $this );
		}

		return $step_class;
	}

	function get_unit_structure( $items_only = true, $show_details = false ) {
		$course = $this->get_course();
		$course_id = $course->__get( 'ID' );
		$unit_id = $this->__get( 'ID' );
		$with_modules = $course->is_with_modules();
		$user = coursepress_get_user();
		$user_id = $user->__get( 'ID' );
		$has_access = $user->has_access_at( $course_id );
		$is_student = $user->is_enrolled_at( $course_id );
		$is_available = $this->is_available();
		$is_accessible = $this->is_accessible_by( $user_id );
		$unit_locked = $is_student && ( ! $is_available || ! $is_accessible );

		$unit_title = $this->get_the_title();
		$unit_url = esc_url_raw( $this->get_unit_url() );
		$unit_suffix = '';
		$unit_structure = '';
		$unit_duration = 0;
		$unit_class = array( 'unit' );

		if ( $has_access ) {
			$unit_title = $this->create_html( 'a', array( 'href' => $unit_url ), $unit_title );
		} elseif ( $is_student ) {
			if ( ! $is_available ) {
				$unit_class[] = 'unit-locked';
				$label        = sprintf( __( 'Opens %s', 'cp' ), $this->__get( 'unit_availability_date' ) );
				$unit_suffix  .= $this->create_html( 'span', array( 'class' => 'unit-date' ), $label );
			} elseif ( ! $is_accessible ) {
				$unit_class[] = 'unit-locked';
			} else {
				$unit_class[]  = 'has-progress';
				$unit_progress = $user->get_unit_progress( $course_id, $unit_id );
				$unit_title    = $this->create_html( 'a', array( 'href' => $unit_url ), $unit_title );

				if ( $user->is_unit_completed( $course_id, $unit_id ) ) {
					$unit_class[] = 'unit-seen unit-completed';
				} elseif ( $user->is_unit_seen( $course_id, $unit_id ) ) {
					$unit_class[] = 'unit-seen';
				}

				if ( $unit_progress > 0 ) {
					$unit_progress /= 100;
				}

				$attr = array(
					'class'                      => 'unit-progress',
					'data-value'                 => $unit_progress,
				);

				/**
				 * Fire to allow changes on unit progress wheel attributes
				 * before printing the unit progress.
				 *
				 * @since 2.0
				 *
				 * @param array $attr An array of wheel attributes.
				 */
				$attr = apply_filters( 'coursepress_unit_progress_wheel_atts', $attr );
				$unit_suffix .= coursepress_progress_wheel( $attr );
			}
		} elseif ( $this->__get( 'preview' ) ) {
			$attr        = array(
				'href'   => add_query_arg( 'preview', true, $unit_url ),
				'class'  => 'preview',
				'target' => '_blank',
			);
			$unit_suffix .= $this->create_html( 'a', $attr, __( 'Preview', 'cp' ) );
		}

		if ( ! empty( $unit_duration ) && ( ! $has_access || ! $is_student ) ) {
			$unit_suffix = $this->create_html( 'span', array( 'class' => 'timer' ), $unit_duration ) . $unit_suffix;
		}

		if ( $show_details ) {
			$unit_title = $this->get_feature_image() . $unit_title;

			$description = $this->get_summary();

			if ( ! empty( $description ) )
				$unit_title .= $this->create_html(
					'div',
					array( 'class' => 'unit-description' ),
					$description
				);
		}

		$unit_title = $this->create_html( 'div', array( 'class' => 'unit-title' ), $unit_title . $unit_suffix );

		$attr = array( 'class' => implode( ' ', $unit_class ) );

		if ( ! $unit_locked ) {
			if ( $with_modules ) {
				$modules = $this->get_modules_with_steps( ! $has_access );

				if ( $modules ) {
					$module_structures = '';
					foreach ( $modules as $module ) {
						$module_structure = $this->get_module_structure( $module, false );
						$module_structures .= $this->create_html( 'li', false, $module_structure );
					}
					$unit_structure .= $this->create_html( 'ul', array( 'class' => 'tree module-tree' ), $module_structures );
				}
			} else {
				$steps = $this->get_steps( ! $has_access );
				$unit_structure .= $this->get_steps_structure( $steps );
			}
		}

		if ( $items_only ) {
			$unit_structure = $this->create_html( 'div', $attr, $unit_structure );
		} else {
			$unit_structure = $this->create_html( 'div', $attr, $unit_title . $unit_structure );
		}

		return $unit_structure;
	}

	function get_module_structure( $module, $items_only = true ) {
		$module_structure = '';
		$module_locked = false;
		$course = $this->get_course();
		$course_id = $course->__get( 'ID' );
		$unit_id = $this->__get( 'ID' );
		$user = coursepress_get_user();
		$user_id = $user->__get( 'ID' );
		$has_access = $user->has_access_at( $course_id );
		$is_student = $user->is_enrolled_at( $course_id );

		$module_suffix = '';
		$module_id = $module['id'];
		$module_title = $module['title'];
		$module_class = array( 'module' );
		$module_url = esc_url_raw( $module['url'] );

		if ( $has_access ) {
			$module_title = $this->create_html( 'a', array( 'href' => $module_url ), $module_title );
		} elseif ( $is_student ) {
			if ( ! $this->is_module_accessible_by( $user_id, $module ) ) {
				$module_class[] = 'module-locked';
				$module_locked = true;
			} else {
				if ( $user->is_module_completed( $course_id, $unit_id, $module_id ) ) {
					$module_class[] = 'module-seen module-completed';
					$module_title = $this->create_html( 'a', array( 'href' => $module_url ), $module_title );
				} elseif ( $user->is_module_seen( $course_id, $unit_id, $module_id ) ) {
					$module_class[] = 'module-seen';
				}
			}
		} else {
			if ( $module['preview'] ) {
				$module_class[] = 'has-preview';

				$attr = array(
					'href' => add_query_arg( 'preview', true, $module_url ),
					'class' => 'preview',
				);
				$module_suffix .= $this->create_html( 'a', $attr, __( 'Preview' ) );
			}
		}

		$module_title = $this->create_html( 'div', array( 'class' => 'module-title' ), $module_title . $module_suffix );

		if ( ! $module_locked && ! empty( $module['steps'] ) ) {
			$module_structure .= $this->get_steps_structure( $module['steps'] );
		}

		$attr = array( 'class' => implode( ' ', $module_class ) );

		if ( $items_only ) {
			$module_structure = $this->create_html( 'div', $attr, $module_structure );
		} else {
			$module_structure = $this->create_html( 'div', $attr, $module_title . $module_structure );
		}

		return $module_structure;
	}

	protected function get_steps_structure( $steps ) {
		$steps_structure = '';
		$course = $this->get_course();
		$course_id = $course->__get( 'ID' );
		$unit_id = $this->__get( 'ID' );
		$user = coursepress_get_user();
		$user_id = $user->__get( 'ID' );
		$has_access = $user->has_access_at( $course_id );
		$is_student = $user->is_enrolled_at( $course_id );

		foreach ( $steps as $step ) {
			$step_id = $step->__get( 'ID' );
			$step_title = $step->__get( 'post_title' );
			$step_url = esc_url( $step->get_permalink() );
			$step_suffix = '';
			$step_class = array( 'course-step' );

			if ( ! $step->is_show_title() ) {
				continue;
			}

			if ( $has_access ) {
				$attr = array( 'href' => $step_url );
				$step_title = $this->create_html( 'a', $attr, $step_title );
			} elseif ( $is_student ) {
				$step_title = $this->create_html( 'a', array( 'href' => $step_url ), $step_title );
				if ( ! $step->is_accessible_by( $user_id ) ) {
					$step_class[] = 'step-locked';
					$step_title = $step->__get( 'post_title' );
				} elseif ( $user->is_step_completed( $course_id, $unit_id, $step_id ) ) {
					$step_class[] = 'step-seen step-completed';
				} elseif ( $user->is_step_seen ( $course_id, $unit_id, $step_id ) ) {
					$step_class[] = 'step-seen';
				}
			} elseif ( $step->__get( 'preview' ) ) {
				$attr = array( 'href' => add_query_arg( 'preview', 1, $step_url ), 'class' => 'preview' );
				$step_suffix .= $this->create_html( 'a', $attr, __( 'Preview', 'cp' ) );
			}

			$attr = array( 'class' => implode( ' ', $step_class ) );
			$steps_structure .= $this->create_html( 'li', $attr, $step_title . $step_suffix );
		}

		if ( ! empty( $steps_structure ) ) {
			$attr = array( 'class' => 'tree step-tree' );
			$steps_structure = $this->create_html( 'ol', $attr, $steps_structure );
		}

		return $steps_structure;
	}

	/**
	 * Duplicate current Unit and set given course ID.
	 *
	 * This class object is created based on a WP_Post object. So using the current
	 * course post data, create new post of type "unit". If success, then copy the
	 * unit metadata to newly created course post.
	 *
	 * @param int $course_id Course ID of the unit.
	 *
	 * @return bool Success or Fail?
	 */
	function duplicate_unit( $course_id = 0 ) {

		// If in case unit post object is not and ID not found, bail.
		// Unit ID is set when this class is instantiated.
		if ( empty( $this->ID ) ) {

			/**
			 * Perform actions if the duplication was failed.
			 *
			 * Note: We don't have unit ID here.
			 *
			 * @since 3.0
			 */
			do_action( 'coursepress_unit_duplicate_failed', false );

			return false;
		}

		// If course id is empty, current unit's course id will be used.
		if ( empty( $course_id ) ) {
			$course_id = $this->course_id;
		}

		/**
		 * Allow unit duplication to be cancelled when filter returns true.
		 *
		 * @since 1.2.2
		 */
		if ( apply_filters( 'coursepress_unit_cancel_duplicate', false, $this->ID ) ) {

			/**
			 * Perform actions if the duplication was cancelled.
			 *
			 * @since 1.2.2
			 */
			do_action( 'coursepress_unit_duplicate_cancelled', $this->ID );

			return false;
		}

		// Copy of current course object.
		$new_unit = $this;

		// Unset the ID, otherwise it will update the existing unit.
		unset( $new_unit->ID );

		// Set basic data.
		$new_unit->post_author = get_current_user_id();
		$new_unit->post_status = 'private';
		$new_unit->post_parent = $course_id;

		// Attempt to create new post of type "course".
		$new_unit_id = wp_insert_post( $new_unit );
		// If unit creation was success.
		if ( ! empty( $new_unit_id ) ) {

			// Copy the old course metadata to duplicated course.
			$unit_metas = get_post_meta( $this->ID );
			if ( ! empty( $unit_metas ) ) {
				foreach ( $unit_metas as $key => $value ) {
					$value = array_pop( $value );
					$value = maybe_unserialize( $value );
					update_post_meta( $new_unit_id, $key, $value );
				}
			}

			// @todo: Implement module duplication.

			// Set the course ID to new course.
			update_post_meta( $new_unit_id, 'course_id', $course_id );

			/**
			 * Perform action when the unit is duplicated.
			 *
			 * @param int $new_unit_id New unit ID.
			 * @param int $this->ID Old unit ID.
			 *
			 * @since 1.2.2
			 */
			do_action( 'coursepress_unit_duplicated', $new_unit_id, $this->ID );

			return true;
		}

		/**
		 * Perform actions if the duplication was failed.
		 *
		 * @param int $this->ID Old unit ID.
		 *
		 * @since 3.0
		 */
		do_action( 'coursepress_unit_duplicate_failed', $this->ID );

		return false;
	}
}
