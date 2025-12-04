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
    // Get the selected entity type for this board.
    $entity_type = 'ticket';
    $entity_type_label = 'Ticket';
    if ($node->hasField('field_board_entity_type') && !$node->get('field_board_entity_type')->isEmpty()) {
      $entity_type = $node->get('field_board_entity_type')->value;
      $node_type = $this->entityTypeManager()->getStorage('node_type')->load($entity_type);
      if ($node_type) {
        $entity_type_label = $node_type->label();
      }
    }

    // Get columns from the board's field_board_columns, or fall back to all columns.
    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');

    if ($node->hasField('field_board_columns') && !$node->get('field_board_columns')->isEmpty()) {
      // Use only the selected columns, maintaining the order from the field.
      $columns = [];
      foreach ($node->get('field_board_columns') as $item) {
        if ($item->entity) {
          $columns[$item->target_id] = $item->entity;
        }
      }
    }
    else {
      // Fall back to all columns from the vocabulary.
      $columns = $term_storage->loadByProperties(['vid' => 'board_column']);
      uasort($columns, fn($a, $b) => $a->getWeight() <=> $b->getWeight());
    }

    // Get all items for this board, sorted by created date.
    $node_storage = $this->entityTypeManager()->getStorage('node');
    $query = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $entity_type)
      ->condition('field_board_ref', $node->id())
      ->condition('status', 1)
      ->sort('created', 'ASC');

    // Add weight sorting if the field exists for this entity type.
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $entity_type);
    if (isset($field_definitions['field_ticket_weight'])) {
      $query->sort('field_ticket_weight', 'ASC');
    }

    $item_ids = $query->execute();
    $items = $node_storage->loadMultiple($item_ids);

    // Organize items by column.
    $columns_data = [];
    foreach ($columns as $column) {
      $columns_data[$column->id()] = [
        'id' => $column->id(),
        'name' => $column->getName(),
        'tickets' => [],
      ];
    }

    foreach ($items as $item) {
      if ($item->hasField('field_board_status') && !$item->get('field_board_status')->isEmpty()) {
        $column_id = $item->get('field_board_status')->target_id;
        if (isset($columns_data[$column_id])) {
          $columns_data[$column_id]['tickets'][] = $this->buildItemCard($item, $entity_type);
        }
      }
    }

    // Render the description field.
    $description = NULL;
    if ($node->hasField('field_board_description') && !$node->get('field_board_description')->isEmpty()) {
      $description = $node->get('field_board_description')->view([
        'label' => 'hidden',
        'type' => 'text_default',
      ]);
    }

    return [
      '#theme' => 'strata_board',
      '#board' => $node,
      '#description' => $description,
      '#columns' => $columns_data,
      '#entity_type' => $entity_type,
      '#entity_type_label' => $entity_type_label,
      '#add_ticket_url' => Url::fromRoute('node.add', ['node_type' => $entity_type], ['query' => ['board' => $node->id()]])->toString(),
      '#all_tickets_url' => Url::fromRoute('view.strata_tickets.page_board_tickets', ['node' => $node->id()])->toString(),
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
   * Builds an item card render array for any content type.
   *
   * @param \Drupal\node\NodeInterface $item
   *   The node item.
   * @param string $entity_type
   *   The content type machine name.
   *
   * @return array
   *   A render array for the item card.
   */
  protected function buildItemCard(NodeInterface $item, string $entity_type): array {
    // Try to get description from various possible field names.
    $description = '';
    $description_fields = [
      'field_ticket_description',
      'field_notice_description',
      'field_violation_details',
      'body',
    ];
    foreach ($description_fields as $field_name) {
      if ($item->hasField($field_name) && !$item->get($field_name)->isEmpty()) {
        $description = $item->get($field_name)->value;
        if (strlen($description) > 200) {
          $description = substr($description, 0, 200) . '...';
        }
        break;
      }
    }

    // Try to get deadline from various possible field names.
    $deadline = NULL;
    $deadline_fields = [
      'field_ticket_deadline',
      'field_violation_deadline',
    ];
    foreach ($deadline_fields as $field_name) {
      if ($item->hasField($field_name) && !$item->get($field_name)->isEmpty()) {
        $deadline = $item->get($field_name)->date;
        break;
      }
    }

    // Try to get type from various possible field names.
    $type = NULL;
    $type_fields = [
      'field_ticket_type',
      'field_violation_type',
    ];
    foreach ($type_fields as $field_name) {
      if ($item->hasField($field_name) && !$item->get($field_name)->isEmpty()) {
        $type = $item->get($field_name)->entity;
        break;
      }
    }

    // Try to get assigned user.
    $assigned_to = NULL;
    if ($item->hasField('field_ticket_assigned_to') && !$item->get('field_ticket_assigned_to')->isEmpty()) {
      $assigned_to = $item->get('field_ticket_assigned_to')->entity;
    }

    // Get comment count if available.
    $comment_count = 0;
    $comment_fields = [
      'field_ticket_comments',
    ];
    foreach ($comment_fields as $field_name) {
      if ($item->hasField($field_name) && !$item->get($field_name)->isEmpty()) {
        $comment_count = $item->get($field_name)->comment_count;
        break;
      }
    }

    // Get file count from various possible field names.
    $file_count = 0;
    $file_fields = [
      'field_ticket_files',
      'field_notice_files',
      'field_violation_files',
    ];
    foreach ($file_fields as $field_name) {
      if ($item->hasField($field_name) && !$item->get($field_name)->isEmpty()) {
        $file_count = $item->get($field_name)->count();
        break;
      }
    }

    $created = $item->getCreatedTime();

    return [
      '#theme' => 'strata_ticket_card',
      '#ticket' => $item,
      '#title' => $item->getTitle(),
      '#description' => $description,
      '#deadline' => $deadline,
      '#ticket_type' => $type,
      '#assigned_to' => $assigned_to,
      '#ticket_url' => $item->toUrl()->toString(),
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
   * Updates an item's status via AJAX.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node item.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response.
   */
  public function updateTicketStatus(NodeInterface $node, Request $request): JsonResponse {
    // Verify the node has field_board_status.
    if (!$node->hasField('field_board_status')) {
      return new JsonResponse(['error' => 'Invalid content type'], 400);
    }

    if (!$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    $content = json_decode($request->getContent(), TRUE);
    $new_status_id = $content['status_id'] ?? NULL;

    if (!$new_status_id) {
      return new JsonResponse(['error' => 'Missing status_id'], 400);
    }

    // Verify the status is a valid board_column term.
    $term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($new_status_id);
    if (!$term || $term->bundle() !== 'board_column') {
      return new JsonResponse(['error' => 'Invalid status'], 400);
    }

    $node->set('field_board_status', $new_status_id);
    $node->save();

    return new JsonResponse([
      'success' => TRUE,
      'item_id' => $node->id(),
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
      // Get the entity type for this board.
      $entity_type = 'ticket';
      if ($board->hasField('field_board_entity_type') && !$board->get('field_board_entity_type')->isEmpty()) {
        $entity_type = $board->get('field_board_entity_type')->value;
      }

      // Count items per board based on its entity type.
      $item_count = $node_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $entity_type)
        ->condition('field_board_ref', $board->id())
        ->condition('status', 1)
        ->count()
        ->execute();

      $items[] = [
        'board' => $board,
        'ticket_count' => $item_count,
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
   * Redirects to create content pre-filled with the board.
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

    // Get the selected entity type for this board.
    $entity_type = 'ticket';
    if ($node->hasField('field_board_entity_type') && !$node->get('field_board_entity_type')->isEmpty()) {
      $entity_type = $node->get('field_board_entity_type')->value;
    }

    $url = Url::fromRoute('node.add', ['node_type' => $entity_type], [
      'query' => ['board' => $node->id()],
    ]);

    return $this->redirect($url->getRouteName(), $url->getRouteParameters(), $url->getOptions());
  }

  /**
   * Updates the order of items within a column.
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
    if ($term->bundle() !== 'board_column') {
      return new JsonResponse(['error' => 'Invalid column'], 400);
    }

    $content = json_decode($request->getContent(), TRUE);
    $item_ids = $content['ticket_ids'] ?? [];

    if (empty($item_ids) || !is_array($item_ids)) {
      return new JsonResponse(['error' => 'Missing or invalid ticket_ids'], 400);
    }

    $node_storage = $this->entityTypeManager()->getStorage('node');

    // Update status and weights (if available) for all items in the order provided.
    foreach ($item_ids as $weight => $item_id) {
      $item = $node_storage->load($item_id);
      if ($item && $item->hasField('field_board_status') && $item->access('update')) {
        // Update weight if the field exists.
        if ($item->hasField('field_ticket_weight')) {
          $item->set('field_ticket_weight', $weight);
        }
        $item->set('field_board_status', $term->id());
        $item->save();
      }
    }

    return new JsonResponse([
      'success' => TRUE,
      'column_id' => $term->id(),
      'item_count' => count($item_ids),
    ]);
  }

}
