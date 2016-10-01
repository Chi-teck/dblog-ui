<?php

namespace Drupal\dblog_ui\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for DB log UI routes.
 */
class DblogUiController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection;
   */
  protected $dbConnection;

  /**
   * The module_handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the controller object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Database\Connection $db_connection
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module_handler service.
   */
  public function __construct(DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager, Connection $db_connection, ModuleHandlerInterface $module_handler) {
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->dbConnection = $db_connection;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('module_handler')
    );
  }

  /**
   * Builds the response.
   */
  public function overview() {
    $build['content'] = ['#theme' => 'dblog_ui'];
    $build['#attached']['library'][] = 'dblog_ui/dblog_ui';
    $build['#attached']['drupalSettings']['dblogUi']['severityLevels'] = RfcLogLevel::getLevels();
    return $build;
  }

  /**
   * Returns list of log messages.
   */
  public function events(Request $request) {

    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query = $this->dbConnection->select('watchdog', 'w')
      ->extend('\Drupal\Core\Database\Query\PagerSelectExtender')
      ->fields('w')
      ->limit(50);

    // Apply sort parameters.
    $supplied_order = $request->query->get('order');
    $supplied_sort = $request->query->get('sort');

    if ($supplied_order) {
      $allowed_orders = [
        'type' => 'type',
        'date' => 'wid',
        'user' => 'uid',
      ];
      $allowed_sorts = [
        'asc' => 'ASC',
        'desc' => 'DESC',
      ];
      if (!isset($allowed_orders[$supplied_order], $allowed_sorts[$supplied_sort])) {
        throw new BadRequestHttpException();
      }

      $order = $allowed_orders[$supplied_order];
      $sort = $allowed_sorts[$supplied_sort];
    }
    else {
      $order = 'wid';
      $sort = 'DESC';
    }

    $query->orderBy($order, $sort);

    $type = $request->query->get('type');
    if ($type) {
      $query->condition('type', explode(',', $type), 'IN');
    }

    $severity = $request->query->get('severity');
    if (!is_null($severity)) {
      $query->condition('severity', explode(',', $severity), 'IN');
    }

    $total = $query->getCountQuery()->execute()->fetchField();
    $result = $query->execute();

    $severity_classes = static::getLogLevelClassMap();
    $data = [];
    foreach ($result as $event) {

      /** @var \Drupal\user\Entity\User $account */
      $account = $this->entityTypeManager->getStorage('user')->load($event->uid);
      $user = [];
      $user['name'] = $account->getDisplayName();
      $user['url'] = $account->isAuthenticated() ? $account->toUrl()->toString() : NULL;
      $data[] = [
        'id' => $event->wid,
        'type' => $event->type,
        'user' => $user,
        'date' => $this->dateFormatter->format($event->timestamp, 'short'),
        'message' => Unicode::truncate($this->formatMessage($event), 130, TRUE, TRUE),
        'severityClass' => $severity_classes[$event->severity],
        'link' => $event->link,
      ];
    }

    $this->moduleHandler->loadInclude('dblog', 'admin.inc');
    $filters = dblog_filters();

    $response = [
      'data' => $data,
      'total' => $total,
      'typeOptions' => isset($filters['type']['options']) ? array_keys($filters['type']['options']) : [],
    ];
    return new JsonResponse($response);
  }

  /**
   * Provides details about a specific log message.
   *
   * @param int $event_id
   *   Unique ID of the database log message.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A single database log entry.
   */
  public function eventDetails($event_id) {

    $event = $this->dbConnection->select('watchdog', 'w')
      ->fields('w')
      ->condition('wid', $event_id)
      ->execute()
      ->fetch();

    if (!$event) {
      throw new NotFoundHttpException();
    }

    /** @var \Drupal\user\Entity\User $account */
    $account = $this->entityTypeManager->getStorage('user')->load($event->uid);
    $user = [];
    $user['name'] = $account->getDisplayName();
    $user['url'] = $account->isAuthenticated() ? $account->toUrl()->toString() : NULL;

    $data = [
      'type' => $event->type,
      'user' => $user,
      'date' => $this->dateFormatter->format($event->timestamp, 'short'),
      'message' => $this->formatMessage($event),
      'severity' => $event->severity,
      'referrer' => $event->referer,
      'hostname' => $event->hostname,
      'location' => $event->location,
      'link' => $event->link,
    ];

    return new JsonResponse($data);
  }

  /**
   * Formats a database log message.
   *
   * @param object $event
   *   The record from the watchdog table. The object properties are: wid, uid,
   *   severity, type, timestamp, message, variables, link, name.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The formatted log message.
   */
  public function formatMessage($event) {
    $message = Xss::filterAdmin($event->message);
    $variables = @unserialize($event->variables);
    return $variables == NULL ? $message : t($message, $variables);
  }

  /**
   * Gets an array of log level classes.
   *
   * @return array
   *   An array of log level classes.
   */
  public static function getLogLevelClassMap() {
    return array(
      RfcLogLevel::DEBUG => 'dblog-debug',
      RfcLogLevel::INFO => 'dblog-info',
      RfcLogLevel::NOTICE => 'dblog-notice',
      RfcLogLevel::WARNING => 'dblog-warning',
      RfcLogLevel::ERROR => 'dblog-error',
      RfcLogLevel::CRITICAL => 'dblog-critical',
      RfcLogLevel::ALERT => 'dblog-alert',
      RfcLogLevel::EMERGENCY => 'dblog-emergency',
    );
  }

}

//usleep(598000);
