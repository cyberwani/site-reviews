<?php

/**
 * @package   GeminiLabs\SiteReviews
 * @copyright Copyright (c) 2016, Paul Ryley
 * @license   GPLv2 or later
 * @since     1.0.0
 * -------------------------------------------------------------------------------------------------
 */

namespace GeminiLabs\SiteReviews;

use GeminiLabs\SiteReviews\App;

class Router
{
	/**
	 * @var App
	 */
	protected $app;

	public function __construct( App $app )
	{
		$this->app = $app;
	}

	/**
	 * @param string $prefix
	 * @param string $action
	 *
	 * @return string
	 */
	public function getMethodName( $prefix, $action )
	{
		$callback = function( $matches ) {
			return strtoupper( $matches[1] );
		};

		return $prefix . preg_replace_callback( '/[-_](.)/', $callback, strtolower( $action ) );
	}

	public function routeAjaxRequests()
	{
		$request = $_REQUEST['request'];

		if( isset( $request[ $this->app->prefix ]['action'] ) ) {
			$request = $request[ $this->app->prefix ];
		}

		// All ajax requests are triggered by a single action hook,
		// each route is determined by the request["action"].
		if( !isset( $request['action'] ) ) {
			wp_die();
		}

		// Nonce url is localized in "GeminiLabs\SiteReviews\Handlers\EnqueueAssets"
		check_ajax_referer( sprintf( '%s-ajax-nonce', $this->app->id ) );

		$request['ajax_request'] = true;

		// undo damage done by javascript: encodeURIComponent()
		array_walk_recursive( $request, function( &$value ) {
			$value = stripslashes( $value );
		});

		$ajaxController = $this->app->make( 'Controllers\AjaxController' );
		$method         = $this->getMethodName( 'ajax', $request['action'] );

		if( is_callable([ $ajaxController, $method ]) ) {
			$ajaxController->$method( $request );
		}
		else {
			do_action( 'site-reviews/router/ajax/request', $method, $request );
		}

		wp_die();
	}

	public function routePostRequests()
	{
		// get the request data that is prefixed with the app prefix
		$request = filter_input( INPUT_POST, $this->app->prefix, FILTER_DEFAULT , FILTER_REQUIRE_ARRAY );

		if( !isset( $request['action'] ) )return;

		$nonce = ( filter_input( INPUT_POST, 'option_page' ) == $request['action'] && filter_input( INPUT_POST, 'action' ) == 'update' )
			? $request['action'] . '-options'
			: $request['action'];

		check_admin_referer( $nonce );

		switch( $request['action'] ) {
			case 'clear-log':
				$this->app->make( 'Controllers\MainController' )->postClearLog();
				break;

			case 'download-log':
				$this->app->make( 'Controllers\MainController' )->postDownloadLog();
				break;

			case 'download-system-info':
				$this->app->make( 'Controllers\MainController' )->postDownloadSystemInfo( $request['system-info'] );
				break;

			case 'post-review':
				$this->app->make( 'Controllers\ReviewController' )->postSubmitReview( $request );
				break;

			default:
				do_action( 'site-reviews/router/post/request', $request['action'], $request );
				break;
		}
	}

	public function routeWebhookRequests()
	{
		$request = filter_input( INPUT_GET, sprintf( '%s-hook', $this->app->id ) );

		if( !$request )return;
	}
}
