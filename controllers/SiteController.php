<?php

namespace app\controllers;

use app\models\ContactForm;
use app\models\LoginForm;
use Twilio\Rest\Client;
use Twilio\Twiml;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller {
	/**
	 * {@inheritdoc}
	 */
	public function behaviors() {
		return [
			'access' => [
				'class' => AccessControl::className(),
				'only'  => [ 'logout' ],
				'rules' => [
					[
						'actions' => [ 'logout' ],
						'allow'   => true,
						'roles'   => [ '@' ],
					],
				],
			],
			'verbs'  => [
				'class'   => VerbFilter::className(),
				'actions' => [
					'logout' => [ 'post' ],
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function actions() {
		return [
			'error'   => [
				'class' => 'yii\web\ErrorAction',
			],
			'captcha' => [
				'class'           => 'yii\captcha\CaptchaAction',
				'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
			],
		];
	}

	public function beforeAction($action) {

		if ( in_array( $action->id, [ 'notify', 'receivecall', 'respond' ] ) ) {
			Yii::$app->controller->enableCsrfValidation = false;
		}

		return parent::beforeAction($action);
	}


	/**
	 * Displays homepage.
	 *
	 * @return string
	 */
	public function actionIndex() {
		return $this->render( 'index' );
	}

	/**
	 * TwiML to notify the restaurant of an order
	 *
	 * return $string
	 */
	public function actionNotify() {

		$order_number  = "ORDER0001";
		$customer_name = "NAME OF CUSTOMER";
		$response      = new Twiml();
		$response->say( 'Hello, you have a new customer order from ' . $customer_name . ' reference ' . $order_number . '. Please check your merchant app or control panel for order details. Press 1 to confirm this message.' );

		\Yii::$app->response->format = \yii\web\Response::FORMAT_XML;

		echo $response;
	}

	/**
	 * make the call to the restaurant
	 */
	public function actionMakecall() {

		// Find your Account Sid and Auth Token at twilio.com/console
		$sid    = "ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX";
		$token  = "your_auth_token";
		$twilio = new Client( $sid, $token );

		$call = $twilio->calls
			->create( "+14155551212", // the restaurant's phone number
				"+15017122661", // voice enable number from your console
				array( "url" => "BASE_URL/notify" ) // path to the notification url
			);
	}

	/**
	 * the webhook for receiving calls
	 */
	public function actionReceivecall() {

		$response = new TwiML();
		// Use the <Gather> verb to collect user input
		$gather = $response->gather( array( 'numDigits' => 1, 'action' => 'BASE_URL/respond' ) );

		// use the <Say> verb to request input from the user
		$gather->say( 'Thanks for calling our restaurant. For french fries, press 1. For energy drinks, press 2.' );

		// If the user doesn't enter input, loop
		$response->redirect( 'BASE_URL/receivecall' );

		// Render the response as XML in reply to the webhook request
		\Yii::$app->response->format = \yii\web\Response::FORMAT_XML;

		echo $response;
	}

	/**
	 * handling the user inputs
	 */
	public function actionRespond() {

		$response = new TwiML();

		// If the user entered digits, process their request
		if ( array_key_exists( 'Digits', $_POST ) ) {
			switch ( $_POST['Digits'] ) {
				case 1:
					$response->say( 'Your order for french fries has been received.It will be delivered soon.Thank you!' );
					break;
				case 2:
					$response->say( 'Your order for energy drinks has been received.It will be delivered soon.Thank you!' );
					break;
				default:
					$response->say( 'Sorry, I don\'t understand that choice.' );
					$response->redirect( 'BASE_URL/receivecall' );
			}
		} else {
			// If no input was sent, redirect to the /voice route
			$response->redirect( 'BASE_URL/receivecall' );
		}

		\Yii::$app->response->format = \yii\web\Response::FORMAT_XML;
		echo $response;

	}

	/**
	 * Login action.
	 *
	 * @return Response|string
	 */
	public function actionLogin() {
		if ( ! Yii::$app->user->isGuest ) {
			return $this->goHome();
		}

		$model = new LoginForm();
		if ( $model->load( Yii::$app->request->post() ) && $model->login() ) {
			return $this->goBack();
		}

		$model->password = '';

		return $this->render( 'login', [
			'model' => $model,
		] );
	}

	/**
	 * Logout action.
	 *
	 * @return Response
	 */
	public function actionLogout() {
		Yii::$app->user->logout();

		return $this->goHome();
	}

	/**
	 * Displays contact page.
	 *
	 * @return Response|string
	 */
	public function actionContact() {
		$model = new ContactForm();
		if ( $model->load( Yii::$app->request->post() ) && $model->contact( Yii::$app->params['adminEmail'] ) ) {
			Yii::$app->session->setFlash( 'contactFormSubmitted' );

			return $this->refresh();
		}

		return $this->render( 'contact', [
			'model' => $model,
		] );
	}

	/**
	 * Displays about page.
	 *
	 * @return string
	 */
	public function actionAbout() {
		return $this->render( 'about' );
	}
}
