<?php
/**
 * Sequence class for background processing.
 *
 * @package WpMVC\Queue
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Queue;

defined( 'ABSPATH' ) || exit;

use WP_Background_Process;

/**
 * Class Sequence
 *
 * Abstract class for handling sequential background tasks.
 * Extends WP_Background_Process to provide enhanced sequence handling.
 *
 * @package WpMVC\Queue
 */
abstract class Sequence extends WP_Background_Process {
    /**
     * The current sequence item being processed.
     *
     * @var array
     */
    protected $sequence_item = [];

    /**
     * Get the next item in the sequence.
     *
     * @param  mixed  $item  Current item.
     * @return mixed
     */
    abstract protected function get_item( $item );

    /**
     * Get the minimum time required between processing each item.
     *
     * @return int Time in seconds.
     */
    protected function each_item_minimum_time(): int {
        return 7; //seconds
    }

    /**
     * Determine if the process should sleep during rest time.
     *
     * @return bool|int
     */
    protected function sleep_on_rest_time() {
        return false;
    }

    /**
     * Perform the actual sequence task logic.
     *
     * @param  mixed  $item  The item to process.
     * @return mixed
     */
    abstract protected function perform_sequence_task( $item );

    /**
     * Handle errors triggered during sequence processing.
     *
     * @param  array|null  $error  The error details.
     * @return void
     */
    protected function triggered_error( ?array $error ){}

    /**
     * Sequence constructor.
     *
     * Registers shutdown function to handle fatal errors and calls parent constructor.
     */
    public function __construct() {
        register_shutdown_function( [$this, 'handle_fatal_errors'] );
        parent::__construct();
    }

    /**
     * Handle fatal errors occurring during processing.
     *
     * Captures last error and triggers the triggered_error hook if an item was being processed.
     *
     * @return void
     */
    public function handle_fatal_errors() {
        $error = error_get_last();

        if ( $error && ! empty( $this->sequence_item ) ) {
            static::triggered_error( $error );
        }
    }

    /**
     * Process a single task item.
     *
     * Handles the execution, optional sleeping for rest time, and determining the next item.
     *
     * @param  mixed  $item  The item to process.
     * @return mixed  The next item to process or false if sequence ends.
     */
    protected function task( $item ) {
        $this->sequence_item = $item;
        $task_result         = $this->perform_sequence_task( $item );

        if ( ! $task_result ) {
            $this->sequence_item = [];
            return $task_result;
        }

        $sleep_on_rest_time = static::sleep_on_rest_time();

        if ( $sleep_on_rest_time ) {
            $rest_time = $this->get_rest_time();
            if ( $rest_time <= static::each_item_minimum_time() ) {
                sleep( $rest_time );
            }
        }

        return $this->get_item( $item );
    }

    /**
     * Get the remaining time before the process limit is reached.
     *
     * @return int Remaining time in seconds.
     */
    protected function get_rest_time() {
        return ( $this->start_time + $this->get_default_time_limit() ) - time();
    }

    /**
     * Get the default time limit for the process execution.
     *
     * @return int Time limit in seconds.
     */
    protected function get_default_time_limit() {
        //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
        return apply_filters( $this->identifier . '_default_time_limit', 20 );
    }
}