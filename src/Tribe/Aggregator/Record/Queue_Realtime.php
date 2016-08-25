<?php

/**
 * Facilitates "realtime" processing of an import result insertion queue while the user
 * remains within the editor by means of an ajax update loop.
 */
class Tribe__Events__Aggregator__Record__Queue_Realtime {

	/** @var Tribe__Events__Aggregator__Record__Queue */
	protected $queue;

	/** @var int */
	protected $record_id;
	/**
	 * @var Tribe__Events__Ajax__Operations
	 */
	private $ajax_operations;

	/**
	 * @var Tribe__Events__Aggregator__Record__Queue_Processor
	 */
	private $queue_processor;

	/**
	 * The Queue_Realtime constructor method.
	 *
	 * @param Tribe__Events__Aggregator__Record__Queue|null           $queue An optional Queue instance.
	 * @param Tribe__Events__Ajax__Operations|null                 $ajax_operations An optional Ajax Operations instance.
	 * @param Tribe__Events__Aggregator__Record__Queue_Processor|null $queue_processor An optional Queue_Processor instance.
	 */
	public function __construct( Tribe__Events__Aggregator__Record__Queue $queue = null, Tribe__Events__Ajax__Operations $ajax_operations = null, Tribe__Events__Aggregator__Record__Queue_Processor $queue_processor = null ) {
		tribe_notice( 'aggregator-update-msg', array( $this, 'render_update_message' ), 'type=warning&dismiss=0' );

		add_action( 'wp_ajax_tribe_aggregator_realtime_update', array( $this, 'ajax' ) );
		$this->queue           = $queue;
		$this->ajax_operations = $ajax_operations ? $ajax_operations : new Tribe__Events__Ajax__Operations;
		$this->queue_processor = $queue_processor ? $queue_processor : Tribe__Events__Aggregator::instance()->queue_processor;
	}

	/**
	 * Adds additional data to the tribe_aggregator object (available to our JS).
	 */
	public function update_loop_vars() {
		$percentage = $this->queue->progress_percentage();

		$progress = $this->sanitize_progress( $percentage );
		$data     = array(
			'record_id'    => $this->record_id,
			'check'        => $this->get_ajax_nonce(),
			'completeMsg'  => __( 'Completed!', 'the-events-calendar' ),
			'progress'     => $progress,
			'progressText' => sprintf( __( '%d%% complete', 'the-events-calendar' ), $progress ),
		);

		wp_localize_script( 'tribe-ea-fields', 'tribe_aggregator_save', $data );

		return $data;
	}

	public function render_update_message() {
		if ( ! Tribe__Events__Aggregator__Page::instance()->is_screen() ) {
			return;
		}

		$processor = Tribe__Events__Aggregator::instance()->queue_processor;
		if ( ! $this->record_id = $processor->next_waiting_record( true ) ) {
			return false;
		}

		$this->queue = $this->queue ? $this->queue : new Tribe__Events__Aggregator__Record__Queue( $this->record_id );

		if ( $this->queue->is_empty() ) {
			return false;
		}

		$this->update_loop_vars();

		ob_start();
		$percent   = $this->sanitize_progress( $this->queue->progress_percentage() );
		$spinner   = '<img src="' . get_admin_url( null, '/images/spinner.gif' ) . '">';
		?>
		<div class="tribe-message">
			<p>
				<?php esc_html_e( 'Importing data is still occurring. Don&#146;t worry, you can safely navigate away &ndash; the process will resume in a bit in the background.', 'the-events-calendar' ); ?>
			</p>
		</div>
		<ul class="tracker">
			<li class="tracked-item track-created"><strong><?php esc_html_e( 'Created:', 'the-events-calendar' ); ?></strong> <span class="value"></span></li>
			<li class="tracked-item track-updated"><strong><?php esc_html_e( 'Updated:', 'the-events-calendar' ); ?></strong> <span class="value"></span></li>
			<li class="tracked-item track-skipped"><strong><?php esc_html_e( 'Skipped:', 'the-events-calendar' ); ?></strong> <span class="value"></span></li>
		</ul>
		<div class="progress-container">
			<div class="progress" title="<?php echo esc_html( sprintf( __( '%d%% complete', 'the-events-calendar' ), $percent ) ); ?>">
				<div class="bar"></div>
			</div>
			<img src="<?php echo esc_url( get_admin_url( null, '/images/spinner.gif' ) ); ?>">
		</div>
		<?php

		$html = ob_get_clean();

		return Tribe__Admin__Notices::instance()->render( 'aggregator-update-msg', $html );
	}

	/**
	 * Handle queue ajax requests
	 */
	public function ajax() {
		$this->record_id = (int) $_POST['record'];

		// Nonce check
		$this->ajax_operations->verify_or_exit( $_POST['check'], $this->get_ajax_nonce_action(), $this->get_unable_to_continue_processing_data() );

		// Load the queue
		$queue = $this->queue ? $this->queue : new Tribe__Events__Aggregator__Record__Queue( $this->record_id );

		if ( ! $queue->is_empty() ) {
			$this->queue_processor->set_current_queue( $queue );
			$this->queue_processor->process_batch( $this->record_id );
		}

		$done       = $queue->is_empty();
		$percentage = $queue->progress_percentage();

		$this->ajax_operations->exit_data( $this->get_progress_message_data( $queue, $percentage, $done ) );
	}

	/**
	 * @param $percentage
	 *
	 * @return int|string
	 */
	private function sanitize_progress( $percentage ) {
		if ( $percentage === true ) {
			return 100;
		}

		return is_numeric( $percentage ) ? intval( $percentage ) : 0;
	}

	/**
	 * @return string
	 */
	public function get_ajax_nonce() {
		return wp_create_nonce( $this->get_ajax_nonce_action() );
	}

	/**
	 * Generates the nonce action string on an event and user base.
	 *
	 * @param int|null $event_id An event post ID to override the instance defined one.
	 *
	 * @return string
	 */
	public function get_ajax_nonce_action( $record_id = null ) {
		$record_id = $record_id ? $record_id : $this->record_id;

		return 'tribe_aggregator_insert_items_' . $record_id . get_current_user_id();
	}

	/**
	 * @return mixed|string|void
	 */
	public function get_unable_to_continue_processing_data() {
		return json_encode( array(
			'html'     => __( 'Unable to continue inserting data. Please reload this page to continue/try again.', 'the-events-calendar' ),
			'progress' => false,
			'continue' => false,
			'complete' => false,
		) );
	}

	/**
	 * @param $percentage
	 * @param $done
	 *
	 * @return mixed|string|void
	 */
	public function get_progress_message_data( $queue, $percentage, $done ) {
		$data = array(
			'html'          => false,
			'progress'      => $percentage,
			'progress_text' => sprintf( __( '%d%% complete', 'the-events-calendar' ), $percentage ),
			'continue'      => ! $done,
			'complete'      => $done,
			'counts'        => array(
				'total'     => $queue->total(),
				'created'   => $queue->created(),
				'updated'   => $queue->updated(),
				'skipped'   => $queue->skipped(),
				'remaining' => $queue->count(),
			),
		);

		if ( $done ) {
			$messages = Tribe__Events__Aggregator__Tabs__New::instance()->get_result_messages( $queue->record, $queue->activity() );
			$data['complete_text'] = '<p>' . implode( ' ', $messages['success'] ) . '</p>';
		}

		return json_encode( $data );
	}
}