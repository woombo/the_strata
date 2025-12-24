<?php

namespace Drupal\strata_base\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Restricts access to configured node types for anonymous users.
 */
class NodeAccessSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a NodeAccessSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(AccountProxyInterface $current_user, ConfigFactoryInterface $config_factory) {
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 28],
      KernelEvents::EXCEPTION => ['onException', 100],
    ];
  }

  /**
   * Redirects anonymous users away from restricted node types.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    if ($this->currentUser->isAuthenticated()) {
      return;
    }

    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    if ($route_name !== 'entity.node.canonical') {
      return;
    }

    $node = $request->attributes->get('node');
    if (!$node instanceof NodeInterface) {
      return;
    }

    if ($this->isRestricted($node)) {
      $url = Url::fromRoute('<front>')->toString();
      $response = new RedirectResponse($url);
      $event->setResponse($response);
    }
  }

  /**
   * Redirects anonymous users on access denied for restricted node types.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();

    if (!$exception instanceof AccessDeniedHttpException) {
      return;
    }

    if ($this->currentUser->isAuthenticated()) {
      return;
    }

    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    if ($route_name !== 'entity.node.canonical') {
      return;
    }

    $node = $request->attributes->get('node');
    if (!$node instanceof NodeInterface) {
      return;
    }

    if ($this->isRestricted($node)) {
      $url = Url::fromRoute('<front>')->toString();
      $response = new RedirectResponse($url);
      $event->setResponse($response);
    }
  }

  /**
   * Checks if the node type is restricted.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if the node type is restricted, FALSE otherwise.
   */
  protected function isRestricted(NodeInterface $node): bool {
    $config = $this->configFactory->get('strata_base.settings');
    $restricted_types = $config->get('restricted_content_types') ?? [];

    return in_array($node->bundle(), $restricted_types, TRUE);
  }

}
