<?php

namespace WpMVC\Queue;

defined( 'ABSPATH' ) || exit;

use WP_Background_Process;

abstract class Sequence extends WP_Background_Process {
    protected $sequence_item = [];

    abstract protected function get_item( $item );

    protected function each_item_minimum_time(): int {
        return 7; //seconds
    }

    /**
     * @return bool|int
     */
    protected function sleep_on_rest_time() {
        return false;
    }

    abstract protected function perform_sequence_task( $item );

    protected function triggered_error( ?array $error ){}

    public function __construct() {
        register_shutdown_function( [$this, 'handle_fatal_errors'] );
        parent::__construct();
    }

    public function handle_fatal_errors() {
        $error = error_get_last();

        if ( $error && ! empty( $this->sequence_item ) ) {
            static::triggered_error( $error );
        }
    }

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

    protected function get_rest_time() {
        return ( $this->start_time + $this->get_default_time_limit() ) - time();
    }

    protected function get_default_time_limit() {
        //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
        return apply_filters( $this->identifier . '_default_time_limit', 20 );
    }
}