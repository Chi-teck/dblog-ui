<?php

namespace Drupal\dblog_ui\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * DBlog UI route subscriber.
 */
class DblogUiRouteSubscriber extends RouteSubscriberBase {

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
