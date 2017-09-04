<?php

namespace Drupal\dblog_ui\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * DBlog UI route subscriber.
 */
class DblogUiRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Ensure to run after the views route subscriber
    // @see \Drupal\views\EventSubscriber\RouteSubscriber
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -250];
    return $events;
  }


  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($collection->all() as $rn => $route) {
      if (strpos($route->getPath(), '/admin/reports/dblog') === 0) {
        $route->setDefault('_controller', '\Drupal\dblog_ui\Controller\DblogUiController::overview');
      }
    }
  }

}
