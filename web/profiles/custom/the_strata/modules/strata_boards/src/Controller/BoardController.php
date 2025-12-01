<?php

namespace Drupal\strata_boards\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for board views.
 */
class BoardController extends ControllerBase {

  /**
   * Displays a board with its tickets organized by columns.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The board node.
   *
   * @return array
   *   A render array.
   */
  public function view(NodeInterface $node): array {
    // Get all column terms.
    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $columns = $term_storage->loadByProperties(['vid' => 'ticket_column']);
    uasort($columns, fn($a, $b) => $a->getWeight() <=> $b->getWeight());

    // Get all tickets for this board, sorted by weight then created date.
    $node_storage = $this->entityTypeManager()->getStorage('node');
    $ticket_ids = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'ticket')
      ->condition('field_ticket_board', $node->id())
      ->condition('status', 1)
      ->sort('field_ticket_weight', 'ASC')
      ->sort('created', 'ASC')
      ->execute();

    $tickets = $node_storage->loadMultiple($ticket_ids);

    // Organize tickets by column.
    $columns_data = [];
    foreach ($columns as $column) {
      $columns_data[$column->id()] = [
        'id' => $column->id(),
        'name' => $column->getName(),
        'tickets' => [],
      ];
    }

    foreach ($tickets as $ticket) {
      $status_field = $ticket->get('field_ticket_status');
      if (!$status_field->isEmpty()) {
        $column_id = $status_field->target_id;
        if (isset($columns_data[$column_id])) {
          $columns_data[$column_id]['tickets'][] = $this->buildTicketCard($ticket);
        }
      }
    }

    return [
      '#theme' => 'strata_board',
      '#board' => $node,
      '#columns' => $columns_data,
      '#add_ticket_url' => Url::fromRoute('strata_boards.add_ticket', ['node' => $node->id()])->toString(),
      '#attached' => [
        'library' => [
          'strata_boards/board',
        ],
        'drupalSettings' => [
          'strata_boards' => [
            'board_id' => $node->id(),
          ],
        ],
      ],
    ];
  }

  /**
   * Builds a ticket card render array.
   *
   * @param \Drupal\node\NodeInterface $ticket
   *   The ticket node.
   *
   * @return array
   *   A render array for the ticket card.
   */
  protected function buildTicketCard(NodeInterface $ticket): array {
    $description = '';
    if (!$ticket->get('field_ticket_description')->isEmpty()) {
      $description = $ticket->get('field_ticket_description')->value;
      if (strlen($description) > 200) {
        $description = substr($description, 0, 200) . '...';
      }
    }

    $deadline = NULL;
    if (!$ticket->get('field_ticket_deadline')->isEmpty()) {
      $deadline = $ticket->get('field_ticket_deadline')->date;
    }

    $type = NULL;
    if (!$ticket->get('field_ticket_type')->isEmpty()) {
      $type = $ticket->get('field_ticket_type')->entity;
    }

    $assigned_to = NULL;
    if (!$ticket->get('field_ticket_assigned_to')->isEmpty()) {
      $assigned_to = $ticket->get('field_ticket_assigned_to')->entity;
    }

    $comment_count = 0;
    if ($ticket->hasField('field_ticket_comments') && !$ticket->get('field_ticket_comments')->isEmpty()) {
      $comment_count = $ticket->get('field_ticket_comments')->comment_count;
    }

    $file_count = 0;
    if ($ticket->hasField('field_ticket_files') && !$ticket->get('field_ticket_files')->isEmpty()) {
      $file_count = $ticket->get('field_ticket_files')->count();
    }

    $created = $ticket->getCreatedTime();

    return [
      '#theme' => 'strata_ticket_card',
      '#ticket' => $ticket,
      '#title' => $ticket->getTitle(),
      '#description' => $description,
      '#deadline' => $deadline,
      '#ticket_type' => $type,
      '#assigned_to' => $assigned_to,
      '#ticket_url' => $ticket->toUrl()->toString(),
      '#comment_count' => (int) $comment_count,
      '#file_count' => (int) $file_count,
      '#created' => $created,
    ];
  }

  /**
   * Title callback for the board view.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The board node.
   *
   * @return string
   *   The board title.
   */
  public function title(NodeInterface $node): string {
    return $node->getTitle();
  }

  /**
   * Access callback for board routes.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function accessBoard(NodeInterface $node, AccountInterface $account): AccessResult {
    // Only allow access to board content type.
    if ($node->bundle() !== 'board') {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    return AccessResult::allowed()->addCacheableDependency($node);
  }

  /**
   * Updates a ticket's status via AJAX.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The ticket node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response.
   */
  public function updateTicketStatus(NodeInterface $node, Request $request): JsonResponse {
    if ($node->bundle() !== 'ticket') {
      return new JsonResponse(['error' => 'Invalid ticket'], 400);
    }

    if (!$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    $content = json_decode($request->getContent(), TRUE);
    $new_status_id = $content['status_id'] ?? NULL;

    if (!$new_status_id) {
      return new JsonResponse(['error' => 'Missing status_id'], 400);
    }

    // Verify the status is a valid ticket_column term.
    $term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($new_status_id);
    if (!$term || $term->bundle() !== 'ticket_column') {
      return new JsonResponse(['error' => 'Invalid status'], 400);
    }

    $node->set('field_ticket_status', $new_status_id);
    $node->save();

    return new JsonResponse([
      'success' => TRUE,
      'ticket_id' => $node->id(),
      'new_status_id' => $new_status_id,
    ]);
  }

  /**
   * Lists all boards.
   *
   * @return array
   *   A render array.
   */
  public function listBoards(): array {
    $node_storage = $this->entityTypeManager()->getStorage('node');
    $board_ids = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'board')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->execute();

    $boards = $node_storage->loadMultiple($board_ids);

    $items = [];
    foreach ($boards as $board) {
      // Count tickets per board.
      $ticket_count = $node_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'ticket')
        ->condition('field_ticket_board', $board->id())
        ->condition('status', 1)
        ->count()
        ->execute();

      $items[] = [
        'board' => $board,
        'ticket_count' => $ticket_count,
        'url' => Url::fromRoute('strata_boards.board_view', ['node' => $board->id()])->toString(),
      ];
    }

    return [
      '#theme' => 'strata_boards_list',
      '#boards' => $items,
      '#create_url' => Url::fromRoute('node.add', ['node_type' => 'board'])->toString(),
      '#attached' => [
        'library' => [
          'strata_boards/boards-list',
        ],
      ],
    ];
  }

  /**
   * Redirects to create a ticket pre-filled with the board.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The board node.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function addTicket(NodeInterface $node) {
    if ($node->bundle() !== 'board') {
      throw new NotFoundHttpException();
    }

    $url = Url::fromRoute('node.add', ['node_type' => 'ticket'], [
      'query' => ['board' => $node->id()],
    ]);

    return $this->redirect($url->getRouteName(), $url->getRouteParameters(), $url->getOptions());
  }

  /**
   * Updates the order of tickets within a column.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The column term.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response.
   */
  public function updateTicketOrder(TermInterface $term, Request $request): JsonResponse {
    if ($term->bundle() !== 'ticket_column') {
      return new JsonResponse(['error' => 'Invalid column'], 400);
    }

    $content = json_decode($request->getContent(), TRUE);
    $ticket_ids = $content['ticket_ids'] ?? [];

    if (empty($ticket_ids) || !is_array($ticket_ids)) {
      return new JsonResponse(['error' => 'Missing or invalid ticket_ids'], 400);
    }

    $node_storage = $this->entityTypeManager()->getStorage('node');

    // Update weights for all tickets in the order provided.
    foreach ($ticket_ids as $weight => $ticket_id) {
      $ticket = $node_storage->load($ticket_id);
      if ($ticket && $ticket->bundle() === 'ticket' && $ticket->access('update')) {
        $ticket->set('field_ticket_weight', $weight);
        $ticket->set('field_ticket_status', $term->id());
        $ticket->save();
      }
    }

    return new JsonResponse([
      'success' => TRUE,
      'column_id' => $term->id(),
      'ticket_count' => count($ticket_ids),
    ]);
  }

}
